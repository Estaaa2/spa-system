<?php

declare(strict_types=1);

namespace App\Services\Navigation;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * The platform-admin sidebar as data (sidebar & RBAC rework Phase 3), in the
 * same shape as App\Services\Navigation\Sidebar so both render through
 * layouts/partials/sidebar-items. Answer Phase 2 Q4 → A:
 *
 *   Dashboard
 *   Review:   Registered Spas (pending verifications), ID Reviews (pending), Subscriptions
 *   Platform: Registered Users, Roles & Permissions
 *
 * Admin permissions are global Spatie permissions (routes use `permission:`),
 * so links check $user->can(), not the branch-aware check.
 */
final class AdminSidebar
{
    /** @return array{top: list<array>, zones: list<array>, activeGroup: ?string} */
    public static function build(User $user, ?Request $request = null): array
    {
        $request ??= request();
        $link = fn (...$args) => Sidebar::makeLink($request, ...$args);

        // Pending counts, cached 60 s like the business badges (Phase 4, Q2 → A).
        $badges = NavBadges::forAdmin($user);

        $top = $user->can('view admin dashboard')
            ? [$link('dashboard', 'Dashboard', 'admin.dashboard', ['admin.dashboard'], 'fa-gauge-high')]
            : [];

        $groups = [
            'review' => ['label' => 'Review', 'icon' => 'fa-clipboard-check', 'links' => array_filter([
                $user->can('view registered spas')
                    ? $link('registered-spas', 'Registered Spas', 'admin.registered-spas.index', ['admin.registered-spas.*'], 'fa-spa')
                    : null,
                $user->can('verify staff identity')
                    ? $link('id-reviews', 'ID Reviews', 'admin.identity-verifications.index', ['admin.identity-verifications.*'], 'fa-id-card')
                    : null,
                $user->can('view registered spas')
                    ? $link('subscriptions', 'Subscriptions', 'admin.subscriptions.index', ['admin.subscriptions.*'], 'fa-receipt')
                    : null,
            ])],
            'platform' => ['label' => 'Platform', 'icon' => 'fa-sliders', 'links' => array_filter([
                $user->can('view registered users')
                    ? $link('users', 'Registered Users', 'admin.users.index', ['admin.users.*'], 'fa-users')
                    : null,
                $user->can('view system roles')
                    ? $link('roles', 'Roles & Permissions', 'admin.roles-permissions.index', ['admin.roles-permissions.*'], 'fa-key')
                    : null,
            ])],
        ];

        foreach ($groups as $g => $group) {
            $groups[$g]['links'] = array_map(fn ($l) => Sidebar::withBadge($l, $badges), $group['links']);
        }

        $items = Sidebar::items($groups);
        $zones = $items === [] ? [] : [['key' => 'admin', 'label' => 'Administration', 'items' => $items]];

        return ['top' => $top, 'zones' => $zones, 'activeGroup' => Sidebar::activeGroup($zones)];
    }
}
