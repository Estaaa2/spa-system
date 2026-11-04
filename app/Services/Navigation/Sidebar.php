<?php

declare(strict_types=1);

namespace App\Services\Navigation;

use App\Models\Branch;
use App\Models\Spa;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * The business sidebar as data (sidebar & RBAC rework Phase 2). One permission-
 * driven structure for every role (decision D1); navigation.blade.php only
 * renders it, and the login landing reads it.
 *
 * Layout (answers P1 = A2, P2 = A, P3 = A, P4 = A):
 *  - top:   Dashboard, My Payslips, then the role's pinned links. Pinned links
 *           are MOVED out of their group (no duplicates), and only branch-zone
 *           links can be pinned, so a whole-spa page never sits under the
 *           branch label.
 *  - zones: "branch" (labelled with the current branch, follows the switcher)
 *           and "spa" ("Whole spa": spa-wide Payroll and Business). Branch-only
 *           roles get only the branch zone.
 *  - groups are built children-first: a group with no visible link is not
 *    rendered, and a group with exactly one link is flattened into a plain
 *    link (decision D2).
 *
 * Every link's gate is the same check its route makes (Phase 1 alignment):
 * the branch permission, plus the plan feature where the route has one.
 */
final class Sidebar
{
    /** Pinned links per role (findings §13, answer P4), in display order. */
    public const PINNED = [
        // Phase 2 Q1 → A: the owner pins what front-desk roles pin, so Reports keeps both its pages.
        'owner'        => ['book', 'appointments', 'schedule'],
        'manager'      => ['book', 'appointments', 'schedule'],
        'receptionist' => ['book', 'appointments', 'schedule'],
        'therapist'    => ['appointments', 'schedule', 'attendance', 'performance'],
        'hr'           => ['staff', 'applicants', 'attendance'],
        'hr_head'      => ['staff', 'attendance', 'pay-runs'],
        'finance'      => ['revenue', 'billing', 'vendor-bills'],
        'finance_head' => ['pay-runs', 'revenue', 'billing'],
    ];

    /** @var array<string, bool> */
    private array $features = [];

    private function __construct(
        private readonly User $user,
        private readonly ?Spa $spa,
        private readonly ?Branch $branch,
        private readonly bool $branchLocked,
        private readonly Request $request,
    ) {
    }

    /**
     * @return array{
     *   top: list<array>, zones: list<array{key: string, label: string, items: list<array>}>,
     *   activeGroup: ?string, landingUrl: ?string
     * }
     */
    public static function build(User $user, ?Branch $currentBranch, bool $branchLocked, ?Request $request = null): array
    {
        return (new self($user, $user->spa, $currentBranch, $branchLocked, $request ?? request()))->assemble();
    }

    /** First page a staff member should land on after login (answer P4). */
    public static function landingUrl(User $user): string
    {
        $branchId = $user->currentBranchId();
        $branch = $branchId ? Branch::query()->find($branchId) : null;
        $locked = $branch && $user->spa?->hasAccess() ? $branch->lockReason() !== null : false;

        return self::build($user, $branch, $locked)['landingUrl'] ?? route('profile.edit');
    }

    // ── Assembly ────────────────────────────────────────────────────────────

    private function assemble(): array
    {
        $role = $this->user->getRoleNames()->first();
        $spaWide = $this->user->isSpaWide();

        $groups = $this->groups($spaWide);

        // Locked branch: only payroll (spa-wide, ALWAYS_ALLOWED) and the Whole spa zone stay.
        if ($this->branchLocked) {
            foreach ($groups as $key => $group) {
                if ($group['zone'] === 'branch' && $key !== 'payroll') {
                    unset($groups[$key]);
                }
            }
        }

        // Badges (Phase 4): counts for the links that have items needing action.
        // None for a locked branch — its pages are closed anyway.
        $badges = $this->branchLocked ? [] : NavBadges::forUser($this->user, $this->branch?->id);
        foreach ($groups as $gKey => $group) {
            foreach ($group['links'] as $lKey => $link) {
                $groups[$gKey]['links'][$lKey] = self::withBadge($link, $badges);
            }
        }

        // Pinned: take branch-zone links out of their groups, in the role's order.
        $pinned = [];
        if (! $this->branchLocked) {
            foreach (self::PINNED[$role] ?? [] as $linkKey) {
                foreach ($groups as $gKey => $group) {
                    if ($group['zone'] !== 'branch' || ! isset($group['links'][$linkKey])) {
                        continue;
                    }
                    $pinned[] = $group['links'][$linkKey];
                    unset($groups[$gKey]['links'][$linkKey]);
                }
            }
        }

        $top = array_values(array_filter([
            $this->dashboardLink(),
            $this->myPayslipsLink(),
        ]));
        $top = [...$top, ...$pinned];

        $zones = [];
        foreach (['branch', 'spa'] as $zoneKey) {
            $items = self::items(array_filter($groups, fn ($g) => $g['zone'] === $zoneKey));

            if ($items === []) {
                continue;
            }

            $zones[] = [
                'key'   => $zoneKey,
                'label' => $zoneKey === 'branch'
                    ? ($this->branch?->name ?? $this->spa?->name ?? 'This branch')
                    : 'Whole spa',
                'items' => $items,
            ];
        }

        $activeGroup = self::activeGroup($zones);

        return [
            'top'         => $top,
            'zones'       => $zones,
            'activeGroup' => $activeGroup,
            'landingUrl'  => $this->landing($top, $zones),
        ];
    }

    /**
     * Groups → render items, children-first (shared with AdminSidebar): a group
     * with no link is dropped, a group with one link becomes that link (D2).
     * `badgeTotal` feeds the collapsed-group needs-action dot.
     *
     * @param  array<string, array{label: string, icon: string, links: array}>  $groups
     * @return list<array>
     */
    public static function items(array $groups): array
    {
        $items = [];
        foreach ($groups as $key => $group) {
            $links = array_values($group['links']);
            if ($links === []) {
                continue;
            }
            if (count($links) === 1) {
                $items[] = ['type' => 'link'] + $links[0];
                continue;
            }
            $items[] = [
                'type'       => 'group',
                'key'        => $key,
                'label'      => $group['label'],
                'icon'       => $group['icon'],
                'links'      => $links,
                'active'     => (bool) array_filter($links, fn ($l) => $l['active']),
                'badgeTotal' => array_sum(array_map(fn ($l) => (int) ($l['badge'] ?? 0), $links)),
            ];
        }

        return $items;
    }

    /** Active group key across zones, or null. */
    public static function activeGroup(array $zones): ?string
    {
        foreach ($zones as $zone) {
            foreach ($zone['items'] as $item) {
                if ($item['type'] === 'group' && $item['active']) {
                    return $item['key'];
                }
            }
        }

        return null;
    }

    /** Puts a NavBadges entry (if any) on a link. */
    public static function withBadge(array $link, array $badges): array
    {
        $b = $badges[$link['key']] ?? null;

        return array_merge($link, [
            'badge'       => $b['count'] ?? null,
            'badgeLabel'  => $b['label'] ?? null,
            'badgeUrgent' => (bool) ($b['urgent'] ?? false),
        ]);
    }

    /** One link entry (shared with AdminSidebar). */
    public static function makeLink(Request $request, string $key, string $label, string $route, array $activePatterns, string $icon, ?string $chip = null, array $params = [], ?int $badge = null, ?string $badgeLabel = null): array
    {
        return [
            'key'        => $key,
            'label'      => $label,
            'url'        => route($route, $params),
            'active'     => $request->routeIs(...$activePatterns),
            'icon'       => $icon,
            'chip'       => $chip,
            'badge'       => $badge,
            'badgeLabel'  => $badgeLabel,
            'badgeUrgent' => false,
        ];
    }

    /** Dashboard, else the first pinned link, else the first link anywhere (answer P4). */
    private function landing(array $top, array $zones): ?string
    {
        foreach ($top as $link) {
            if ($link['key'] !== 'my-payslips') {
                return $link['url'];
            }
        }
        foreach ($zones as $zone) {
            foreach ($zone['items'] as $item) {
                return $item['type'] === 'link' ? $item['url'] : $item['links'][0]['url'];
            }
        }

        return $top[0]['url'] ?? null;
    }

    // ── Groups and links ────────────────────────────────────────────────────

    /** @return array<string, array{zone: string, label: string, icon: string, links: array<string, array>}> */
    private function groups(bool $spaWide): array
    {
        $isOwner = $this->user->hasRole('owner');
        $can = fn (string $permission) => $this->user->hasBranchPermission($permission);

        $defs = [
            'bookings' => ['branch', 'Bookings', 'fa-calendar-check', [
                $can('book appointments') ? $this->link('book', 'Book an Appointment', 'booking', ['booking'], 'fa-calendar-plus') : null,
                $can('view appointments') ? $this->link('appointments', 'Appointments', 'appointments.index', ['appointments.*'], 'fa-calendar-check') : null,
                $can('view schedule') ? $this->link('schedule', 'Schedule', 'schedule.index', ['schedule.*'], 'fa-calendar-days') : null,
                // Therapists and "Performs treatments" staff (gate act-as-treatment-provider).
                $this->user->isTreatmentProvider() ? $this->link('performance', 'My Performance', 'therapist.performance', ['therapist.performance'], 'fa-chart-simple') : null,
            ]],
            'people' => ['branch', 'People', 'fa-users', [
                $can('view staff') ? $this->link('staff', 'Staff', 'staff.index', ['staff.*'], 'fa-users') : null,
                ($can('view attendance') || $can('view leave requests') || $can('create leave requests') || $can('edit leave requests'))
                    ? $this->link('attendance', 'Attendance & Leave', 'attendance.index', ['attendance.*'], 'fa-user-clock') : null,
                ($this->feature('manpower') && $can('view deployments'))
                    ? $this->link('deployment', 'Staff Deployment', 'deployment.index', ['deployment.*'], 'fa-people-arrows') : null,
            ]],
            'hiring' => ['branch', 'Hiring', 'fa-user-plus', $this->feature('manpower') ? [
                $can('view hiring') ? $this->link('hiring', 'Application Form', 'hiring.index', ['hiring.*'], 'fa-file-signature') : null,
                $can('view applications') ? $this->link('applicants', 'Applicants', 'applications.index', ['applications.*'], 'fa-id-card') : null,
                $can('view interviews') ? $this->link('interviews', 'Interviews', 'interviews.index', ['interviews.*'], 'fa-comments') : null,
            ] : []],
            // Spa-wide roles see and run payroll for every branch, so it lives in the
            // Whole spa zone for them; branch HR / Finance see their own branch only.
            'payroll' => [$spaWide ? 'spa' : 'branch', 'Payroll', 'fa-money-check-dollar', ($this->feature('payroll') && $can('view payroll')) ? [
                $this->link('pay-runs', 'Pay Runs', 'payroll.index', ['payroll.index', 'payroll.runs.*', 'payroll.payslips.*', 'payroll.lines.*'], 'fa-money-check-dollar'),
                $this->link('staff-pay', 'Staff Pay', 'payroll.staff.index', ['payroll.staff.*'], 'fa-user-tag'),
                $this->link('payroll-setup', 'Payroll Setup', 'payroll.setup.index', ['payroll.setup.*'], 'fa-sliders'),
            ] : []],
            'catalog' => ['branch', 'Catalog & Stock', 'fa-boxes-stacked', [
                $can('view services') ? $this->link('services', 'Services', 'services.index', ['services.*', 'promos.*'], 'fa-hand-holding-heart') : null,
                ($this->feature('inventory_basic') && $can('view inventory'))
                    ? $this->link('products', 'Product Inventory', 'inventory.products', ['inventory.products'], 'fa-box') : null,
                ($this->feature('inventory_full') && $can('view inventory'))
                    ? $this->link('batches', 'Product Batches', 'inventory.batches', ['inventory.batches'], 'fa-layer-group') : null,
                ($this->feature('inventory_basic') && $can('view inventory logs'))
                    ? $this->link('product-logs', 'Product Logs', 'inventory.logs', ['inventory.logs'], 'fa-clock-rotate-left') : null,
                ($this->feature('inventory_full') && $can('view stock transfers'))
                    ? $this->link('transfers', 'Stock Transfers', 'inventory.transfers', ['inventory.transfers*'], 'fa-right-left') : null,
                ($this->feature('inventory_full') && $can('view replenishment'))
                    ? $this->link('replenishment', 'Replenishment', 'inventory.replenishment.index', ['inventory.replenishment.*'], 'fa-arrows-rotate') : null,
            ]],
            'purchasing' => ['branch', 'Purchasing', 'fa-truck-ramp-box', [
                // Suppliers are shared by every branch of the spa (SupplierController filters by spa only).
                ($this->feature('procurement') && $can('view suppliers'))
                    ? $this->link('suppliers', 'Suppliers', 'procurement.suppliers.index', ['procurement.suppliers.*'], 'fa-truck-field', 'All branches') : null,
                ($this->feature('procurement') && $can('view purchase requests'))
                    ? $this->link('purchase-requests', 'Purchase Requests', 'procurement.purchase-requests.index', ['procurement.purchase-requests.*'], 'fa-clipboard-list') : null,
                ($this->feature('procurement') && $can('view purchase orders'))
                    ? $this->link('purchase-orders', 'Purchase Orders', 'procurement.purchase-orders.index', ['procurement.purchase-orders.*'], 'fa-file-invoice') : null,
                ($this->feature('inventory_full') && $can('view goods receipts'))
                    ? $this->link('goods-receipts', 'Goods Receipts', 'inventory.goods-receipts.index', ['inventory.goods-receipts.*'], 'fa-dolly') : null,
            ]],
            'finance' => ['branch', 'Finance', 'fa-wallet', $this->feature('finance') ? [
                $can('view revenue') ? $this->link('revenue', 'Revenue', 'revenue.index', ['revenue.*'], 'fa-sack-dollar') : null,
                ($this->feature('billing_page') && $can('view billing'))
                    ? $this->link('billing', 'Billing & Expenses', 'billing.index', ['billing.*'], 'fa-file-invoice-dollar') : null,
                $can('view vendor bills') ? $this->link('vendor-bills', 'Vendor Bills', 'vendor-bills.index', ['vendor-bills.*'], 'fa-receipt') : null,
            ] : []],
            'reports' => ['branch', 'Reports', 'fa-chart-pie', $this->feature('insights') ? [
                $can('view reports') ? $this->link('reports', 'Reports', 'reports.index', ['reports.*'], 'fa-chart-line') : null,
                $can('view decision support') ? $this->link('decision-support', 'Decision Support', 'decision-support.index', ['decision-support.*'], 'fa-lightbulb') : null,
            ] : []],
            'branch-settings' => ['branch', 'Branch Settings', 'fa-store', [
                (($can('edit branch general') || $can('edit branch hours') || $can('edit branch profile')) && $this->branch)
                    ? $this->link('edit-branch', 'Edit Branch', 'branches.edit', ['branches.edit'], 'fa-pen-to-square', null, [$this->branch]) : null,
                // Non-owners reach the branch details here; owners use All Branches under Business.
                (! $isOwner && $can('view branches'))
                    ? $this->link('branch-details', 'Branch Details', 'branches.index', ['branches.index', 'branches.show'], 'fa-circle-info') : null,
                $isOwner ? $this->link('roles', 'Roles & Permissions', 'owner.roles-permissions.index', ['owner.roles-permissions.*'], 'fa-user-shield') : null,
            ]],
            'business' => ['spa', 'Business', 'fa-building', $isOwner ? [
                ($this->feature('all_branches') && $can('view branches'))
                    ? $this->link('all-branches', 'All Branches', 'branches.index', ['branches.index', 'branches.show'], 'fa-code-branch') : null,
                $this->link('spa-profile', 'Spa Profile', 'owner.spa-profile.edit', ['owner.spa-profile.*'], 'fa-spa'),
                $this->link('subscription', 'Subscription & Billing', 'owner.subscription.index', ['owner.subscription.*'], 'fa-credit-card'),
            ] : []],
        ];

        $groups = [];
        foreach ($defs as $key => [$zone, $label, $icon, $links]) {
            $keyed = [];
            foreach (array_filter($links) as $link) {
                $keyed[$link['key']] = $link;
            }
            $groups[$key] = ['zone' => $zone, 'label' => $label, 'icon' => $icon, 'links' => $keyed];
        }

        return $groups;
    }

    private function dashboardLink(): ?array
    {
        if ($this->branchLocked || ! $this->user->can('view business dashboard')) {
            return null;
        }

        return $this->link('dashboard', 'Dashboard', 'dashboard', ['dashboard'], 'fa-gauge-high');
    }

    /** Self-service payslips: active staff record, or any released payslip (unchanged rule). */
    private function myPayslipsLink(): ?array
    {
        $user = $this->user;
        if (! $user->spa_id) {
            return null;
        }

        $staffIds = \App\Models\Staff::withTrashed()->where('user_id', $user->id)->where('spa_id', $user->spa_id)->pluck('id');
        if ($staffIds->isEmpty()) {
            return null;
        }

        $show = \App\Models\Staff::whereIn('id', $staffIds)
                ->where('employment_status', 'active')
                ->whereHas('branch', fn ($q) => $q->where('spa_id', $user->spa_id))
                ->exists()
            || \App\Models\Payslip::whereIn('staff_id', $staffIds)
                ->whereHas('payrollRun', fn ($q) => $q
                    ->where('spa_id', $user->spa_id)
                    ->where('status', \App\Models\PayrollRun::STATUS_RELEASED))
                ->exists();

        return $show ? $this->link('my-payslips', 'My Payslips', 'my-payslips.index', ['my-payslips.*'], 'fa-file-invoice-dollar') : null;
    }

    /**
     * @param  list<string>  $activePatterns  route-name patterns for request()->routeIs()
     * @return array{key: string, label: string, url: string, active: bool, icon: string, chip: ?string, badge: ?int, badgeLabel: ?string}
     */
    private function link(string $key, string $label, string $route, array $activePatterns, string $icon, ?string $chip = null, array $params = []): array
    {
        // Badges: Phase 4 (NavBadges) fills them for the business side.
        return self::makeLink($this->request, $key, $label, $route, $activePatterns, $icon, $chip, $params);
    }

    private function feature(string $feature): bool
    {
        return $this->features[$feature] ??= (bool) ($this->spa?->hasFeature($feature) ?? false);
    }
}
