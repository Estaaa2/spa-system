@php
    use Illuminate\Support\Str;

    $user = Auth::user();
    $spa = $user?->spa;

    // The owner, HR Head and Finance Head switch branches; everyone else sees their own.
    $canSwitchBranches = (bool) ($user?->canSwitchBranches() ?? false);

    if ($canSwitchBranches) {
        $branches = $spa?->branches ?? collect();
    } else {
        $branches = $spa?->branches
            ? $spa->branches->where('id', $user->branch_id)
            : collect();
    }

    $branches = $branches
        ->sortBy(function ($branch) {
            return [
                $branch->is_main ? 0 : 1,
                strtolower((string) $branch->name),
            ];
        })
        ->values();

    $mainBranch = $branches->firstWhere('is_main', true);
    $firstBranch = $mainBranch ?? $branches->first();

    $currentBranchId = session('current_branch_id');

    $currentBranch = $branches->firstWhere('id', $currentBranchId)
        ?? $firstBranch;

    $can = fn($permission) => $user?->hasBranchPermission($permission) ?? false;

    $canInsights = $spa?->hasFeature('insights') ?? false;
    $canInventoryBasic = $spa?->hasFeature('inventory_basic') ?? false;
    $canInventoryFull = $spa?->hasFeature('inventory_full') ?? false;
    $canPayrollPlan = $spa?->hasFeature('payroll') ?? false;
    $canFinancePlan = $spa?->hasFeature('finance') ?? false;
    $canProcurementPlan = $spa?->hasFeature('procurement') ?? false;
    $canManpowerPlan = $spa?->hasFeature('manpower') ?? false;
    $canBillingPlan = $spa?->hasFeature('billing_page') ?? false;

    $canStartTrial = $spa?->canStartTrial() ?? false;
    $currentPlan = $spa?->currentPlan() ?? 'expired';

    // trial | active | grace | locked | new (see Spa::billingState()).
    $billingState = $spa?->billingState() ?? 'new';

    /*
    * A verified first-time owner has not expired.
    * They simply have not selected their free trial yet.
    */
    if ($canStartTrial) {
        $currentPlan = 'trial_available';
    }

    $currentPlanName = match (true) {
        $currentPlan === 'trial_available' => 'Trial Available',
        $currentPlan === 'expired' => 'Expired',
        $billingState === 'trial' => config("plans.{$currentPlan}.name", ucfirst($currentPlan)) . ' Trial',
        $billingState === 'grace' => 'Grace Period',
        default => config("plans.{$currentPlan}.name", ucfirst($currentPlan)),
    };

    $planBadgeClasses = match (true) {
        $currentPlan === 'trial_available' =>
            'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300',

        $billingState === 'grace' =>
            'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300',

        $currentPlan === 'premium' =>
            'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',

        $currentPlan === 'basic' =>
            'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',

        default =>
            'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300',
    };

    // Owner-only reminder above the menu: last week of the trial (when no
    // paid period is queued yet) and the grace period.
    $billingNotice = null;

    if ($spa && ($user?->hasRole('owner') ?? false)) {
        if ($billingState === 'trial' && $spa->scheduledPeriods()->isEmpty() && $spa->trialDaysLeft() <= 7) {
            $trialDaysLeft = $spa->trialDaysLeft();

            $billingNotice = [
                'text' => "{$currentPlanName} · {$trialDaysLeft} day" . ($trialDaysLeft === 1 ? '' : 's')
                    . ' left. Choose a plan before ' . $spa->trial_ends_at->format('M d') . ' to keep your spa running.',
                'action' => 'Choose a plan',
            ];
        } elseif ($billingState === 'grace') {
            $atRisk = $spa->bookingsAffectedByLock()?->count() ?? 0;

            $billingNotice = [
                'text' => 'Your plan has ended. Your spa locks on ' . $spa->graceEndsAt()->format('M d')
                    . ($atRisk > 0 ? " and {$atRisk} upcoming booking" . ($atRisk === 1 ? '' : 's') . ' fall after that.' : '.'),
                'action' => 'Renew now',
            ];
        }
    }

    $canAllBranches = $spa?->hasFeature('all_branches') ?? false;

    // Same check the role('owner') directive performs, in a form the markup can reuse.
    $isOwner = (bool) ($user?->hasRole('owner') ?? false);

    // ── Branch lock ──────────────────────────────────────────────────────────
    // A locked branch shows no branch-scoped links. The same rule is enforced
    // on the server by EnsureBranchOperational, so typing a URL does not work.
    $branchLockReason = ($spa && $currentBranch && $spa->hasAccess())
        ? $currentBranch->lockReason()
        : null;

    $branchLocked = $branchLockReason !== null;

    $branchLockMessage = match ($branchLockReason) {
        'verification' => 'This branch is locked until an administrator approves its documents.',
        'documents_expired' => 'This branch is locked because its Business Permit has expired.',
        'plan_limit' => 'This branch is not covered by your current plan.',
        default => null,
    };

    // ── Sidebar structure (sidebar & RBAC rework Phase 2) ───────────────────
    // Built once in App\Services\Navigation\Sidebar: zones, groups, pinned links,
    // one-link groups flattened, empty groups dropped. This file only renders it.
    // cached(): the page header's Help button may already have built it this request.
    $nav = $user
        ? \App\Services\Navigation\Sidebar::cached($user, $currentBranch, $branchLocked)
        : ['top' => [], 'zones' => [], 'activeGroup' => null, 'landingUrl' => null];

    // Which group owns the current page. Consumed by the collapsed-group dot and,
    // via @json, by the sidebar Alpine component. Never re-derive it in JS.
    $activeSection = $nav['activeGroup'];

    // Branch switcher (decision D6): only for roles that can switch AND a spa with
    // two or more branches. Otherwise the branch name is shown as plain text.
    $showSwitcher = $canSwitchBranches && $branches->count() >= 2;

    // "Manage branches" shortcut inside the switcher: the owner's All Branches page.
    $canManageBranches = $isOwner && $canAllBranches && ($user?->hasBranchPermission('view branches') ?? false);

    // The logo opens the same page login lands on: never a page the user can't open
    // (e.g. the dashboard while the current branch is locked — Phase 6 matrix).
    $brandHref = $nav['landingUrl'] ?? route('profile.edit');

    $mobileContextLabel = $currentBranch?->name ?? $spa?->name ?? 'Levictas';

    // Help & Guides (help guides Phase 1): business users only, never admin or customers (D8).
    $showHelpLink = \App\Support\Guides\GuideCatalog::isBusinessUser($user);

    // Mobile app link (mobile app Phase 2, Q1 B / Q3 A). The app's login only
    // accepts therapists and staff marked "Performs treatments" (plus customers),
    // so they get "Get the mobile app". Owners can't use it, so theirs points
    // them to their therapists. Everyone else: no link.
    $appLinkLabel = null;
    if ($user && $user->isTreatmentProvider()) {
        $appLinkLabel = 'Get the mobile app';
    } elseif ($user && $user->hasRole('owner')) {
        $appLinkLabel = 'Get the app for your therapists';
    }
@endphp

<style>
    @media (max-width: 767.98px) {
        #app-sidebar {
            transform: translateX(-100%);
        }

        #app-sidebar.is-open {
            transform: translateX(0);
        }
    }

    /* Defensive: harmless if app.blade.php already declares this in <head>. */
    [x-cloak] {
        display: none !important;
    }

    .overflow-y-auto::-webkit-scrollbar {
        width: 6px;
    }

    .overflow-y-auto::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 3px;
    }

    .overflow-y-auto::-webkit-scrollbar-thumb {
        background: #c1c1c1;
        border-radius: 3px;
    }

    /* Firefox has no ::-webkit-scrollbar; these two properties are its equivalent. */
    .overflow-y-auto {
        scrollbar-width: thin;
        scrollbar-color: #c1c1c1 #f1f1f1;
    }

    @media (prefers-color-scheme: dark) {
        .overflow-y-auto::-webkit-scrollbar-track {
            background: #374151;
        }

        .overflow-y-auto::-webkit-scrollbar-thumb {
            background: #6b7280;
        }

        .overflow-y-auto {
            scrollbar-color: #6b7280 #374151;
        }
    }

    [x-collapse] {
        overflow: hidden;
        transition: all 0.3s ease-in-out;
    }

    .relative .fa-crown {
        font-size: 0.5rem;
        filter: drop-shadow(0 1px 1px rgba(0, 0, 0, 0.3));
    }
</style>

<div x-data="sidebar" @keydown.escape.window="open = false" class="flex h-screen bg-gray-100 dark:bg-gray-900">

    <a href="#main-content"
        class="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-[60] focus:px-4 focus:py-2 focus:rounded-lg focus:bg-white focus:text-[#6F5430] focus:ring-2 focus:ring-[#8B7355] dark:focus:bg-gray-800 dark:focus:text-[#C4A97D]">
        Skip to content
    </a>

    <!-- MOBILE TOPBAR -->
    <div
        class="fixed top-0 z-40 flex items-center justify-between w-full px-2 bg-white border-b h-14 md:hidden dark:bg-gray-800 dark:border-gray-700">
        <button type="button" @click="open = !open" x-ref="drawerToggle"
            aria-label="Toggle navigation" aria-controls="app-sidebar"
            :aria-expanded="open ? 'true' : 'false'"
            class="inline-flex items-center justify-center flex-shrink-0 text-gray-700 rounded-lg w-11 h-11 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700">
            <i class="text-xl fa-solid fa-bars"></i>
        </button>

        <!-- Mobile Branch Switcher (D6: only when there is more than one branch to switch to) -->
        @if ($showSwitcher)
            <div class="relative pr-2">
                <button @click="mobileBranchesOpen = !mobileBranchesOpen"
                    class="flex items-center px-2 space-x-2 text-gray-700 rounded-lg h-11 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700">
                    <span class="text-sm font-medium truncate max-w-[120px]" x-text="selectedBranch"></span>
                    <i class="text-xs fa-solid fa-chevron-down" :class="mobileBranchesOpen ? 'rotate-180' : ''"></i>
                </button>

                <div x-show="mobileBranchesOpen" @click.outside="mobileBranchesOpen = false" x-cloak
                    x-transition:enter="transition ease-out duration-100"
                    x-transition:enter-start="transform opacity-0 scale-95"
                    x-transition:enter-end="transform opacity-100 scale-100"
                    x-transition:leave="transition ease-in duration-75"
                    x-transition:leave-start="transform opacity-100 scale-100"
                    x-transition:leave-end="transform opacity-0 scale-95"
                    class="absolute right-0 z-50 w-56 mt-2 origin-top-right bg-white rounded-md shadow-lg dark:bg-gray-800 ring-1 ring-black/5">
                    <div class="py-1" role="menu">
                        <div class="px-4 py-1 text-xs font-medium text-gray-500 dark:text-gray-400">SWITCH BRANCH</div>

                        @foreach ($branches as $branch)
                            <button
                                @click="
                                selectedBranch = {{ Js::from($branch->name) }};
                                selectedBranchId = {{ $branch->id }};
                                mobileBranchesOpen = false;
                                switchBranch({{ $branch->id }});
                            "
                                :class="selectedBranchId == {{ $branch->id }} ? 'bg-gray-100 dark:bg-gray-700' : ''"
                                class="flex items-center w-full px-4 py-2 text-sm text-left text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-700">
                                @if ($branch->is_main)
                                    <i class="w-4 mr-2 text-yellow-500 fa-solid fa-crown" title="Main Branch"></i>
                                @else
                                    <i class="w-4 mr-2 text-gray-500 fa-solid fa-store dark:text-gray-400"></i>
                                @endif

                                <div class="flex-1 min-w-0">
                                    <span class="truncate">{{ $branch->name }}</span>
                                    @if ($branch->location)
                                        <p class="text-xs text-gray-500 truncate dark:text-gray-400">
                                            {{ Str::limit($branch->location, 20) }}</p>
                                    @endif
                                </div>

                                <span x-show="selectedBranchId == {{ $branch->id }}"
                                    class="flex-shrink-0 ml-2 text-blue-600 dark:text-blue-400">
                                    <i class="fa-solid fa-check"></i>
                                </span>
                            </button>
                        @endforeach

                        @if ($canManageBranches)
                            <div class="px-4 py-2 text-xs text-gray-500 border-t dark:text-gray-400 dark:border-gray-700">
                                <a href="{{ route('branches.index') }}"
                                    class="flex items-center text-blue-600 hover:text-blue-800 dark:text-blue-400">
                                    <i class="w-4 mr-1 fa-solid fa-cog"></i>
                                    Manage Branches
                                </a>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @else
            {{-- Roles pinned to one branch have no switcher, so the bar would otherwise be empty. --}}
            <div class="flex items-center min-w-0 gap-2 pr-3">
                <i class="text-sm text-gray-500 fa-solid fa-location-dot dark:text-gray-400"></i>
                <span class="text-sm font-medium text-gray-700 truncate dark:text-gray-200">{{ $mobileContextLabel }}</span>
            </div>
        @endif
    </div>

    <!-- SIDEBAR -->
    {{-- z-50 so the mobile overlay (z-40) can sit above the topbar without covering the drawer. --}}
    {{-- Drawer accessibility (Phase 2, D10): on phones a closed drawer is inert (its links
         can't be tabbed into off-screen); an open one traps Tab, locks the page scroll,
         closes on Escape and gives focus back to the menu button. --}}
    <aside id="app-sidebar"
        class="fixed inset-y-0 left-0 z-50 w-64 transition-transform duration-200 bg-white border-r motion-reduce:transition-none dark:bg-gray-800 dark:border-gray-700"
        :class="open ? 'is-open' : ''"
        x-effect="$el.inert = isMobile && !open"
        @keydown.tab="trapFocus($event)"
        :role="isMobile ? 'dialog' : null"
        :aria-modal="isMobile && open ? 'true' : null"
        aria-label="Main navigation">
        <div class="flex flex-col h-full">

            <!-- Brand with Branch Switcher -->
            <div class="flex-shrink-0 border-b dark:border-gray-700">
                <div class="flex items-start justify-between gap-2 py-4 pl-6 pr-3 md:pr-6">
                    <a href="{{ $brandHref }}" class="flex items-center flex-1 min-w-0 space-x-3">
                        <img src="{{ asset('images/1.png') }}" class="flex-shrink-0 object-cover w-10 h-10 rounded-md" alt="Levictas">
                        <div class="min-w-0">
                            {{-- Spa names are user-entered and can be long: cap at two lines instead of pushing the layout.
                                 No `block` here: it comes later in the built CSS and would cancel line-clamp's display. --}}
                            <span title="{{ $spa?->name ?? 'Spa Management' }}"
                                class="text-xl font-semibold leading-tight break-words line-clamp-2 text-[#8B7355] dark:text-white font-['Playfair_Display']">
                                {{ $spa?->name ?? 'Spa Management' }}
                            </span>
                            <p class="text-xs tracking-widest text-gray-500 dark:text-gray-400">SPA | WELLNESS</p>
                        </div>
                    </a>

                    <button type="button" @click="open = false" aria-label="Close navigation"
                        class="inline-flex items-center justify-center flex-shrink-0 -mt-1 text-gray-700 rounded-lg w-11 h-11 md:hidden hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700">
                        <i class="text-lg fa-solid fa-xmark"></i>
                    </button>
                </div>

                <!-- BRANCH SWITCHER (D6: can switch AND 2+ branches; otherwise plain text below) -->
                @if ($showSwitcher)
                    <div class="px-6 pb-4">
                        <div class="relative">
                            <button @click="branchesDropdown = !branchesDropdown"
                                class="flex items-center justify-between w-full px-4 py-3 text-sm text-left transition-colors rounded-lg bg-gray-50 hover:bg-gray-100 dark:bg-gray-700/50 dark:hover:bg-gray-700 dark:text-gray-200">
                                <div class="flex items-center flex-1 min-w-0">
                                    <i
                                        class="flex-shrink-0 mr-3 text-gray-500 fa-solid fa-location-dot dark:text-gray-400"></i>
                                    <div class="flex-1 min-w-0">
                                        {{-- Name gets the full first line; the badge sat beside it and cut names to 3 letters. --}}
                                        <p class="font-medium truncate" x-text="selectedBranch" :title="selectedBranch"></p>

                                        <div class="flex items-center min-w-0 gap-2 mt-0.5">
                                            <span class="px-2 py-0.5 text-[10px] font-semibold rounded-full shrink-0 {{ $planBadgeClasses }}">
                                                {{ $currentPlanName }}
                                            </span>
                                            <span class="text-xs text-gray-500 truncate dark:text-gray-400">
                                                {{ $branches->count() }} {{ Str::plural('branch', $branches->count()) }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <i class="flex-shrink-0 ml-2 text-xs transition-transform duration-200 fa-solid fa-chevron-down"
                                    :class="branchesDropdown ? 'transform rotate-180' : ''"></i>
                            </button>

                            <div x-show="branchesDropdown" @click.outside="branchesDropdown = false" x-cloak
                                x-transition:enter="transition ease-out duration-100"
                                x-transition:enter-start="transform opacity-0 scale-95"
                                x-transition:enter-end="transform opacity-100 scale-100"
                                x-transition:leave="transition ease-in duration-75"
                                x-transition:leave-start="transform opacity-100 scale-100"
                                x-transition:leave-end="transform opacity-0 scale-95"
                                class="absolute left-0 right-0 z-50 mt-1 overflow-y-auto origin-top bg-white rounded-lg shadow-lg dark:bg-gray-800 ring-1 ring-black/5 max-h-96">
                                <div class="py-2">
                                    <div
                                        class="flex items-center justify-between px-4 py-2 border-b dark:border-gray-700">
                                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400">SELECT
                                            BRANCH</span>
                                        <span class="text-xs text-gray-500 dark:text-gray-400">{{ $branches->count() }}
                                            total</span>
                                    </div>

                                    <div class="py-1">
                                        @foreach ($branches as $branch)
                                            <button
                                                @click="
                                                    selectedBranch = {{ Js::from($branch->name) }};
                                                    selectedBranchId = {{ $branch->id }};
                                                    branchesDropdown = false;
                                                    switchBranch({{ $branch->id }});
                                                "
                                                class="flex items-center w-full px-4 py-3 text-sm text-left hover:bg-gray-50 dark:hover:bg-gray-700 group"
                                                :class="selectedBranchId == {{ $branch->id }} ?
                                                    'bg-blue-50 dark:bg-blue-900/20' : ''">
                                                <div class="flex items-center flex-1 min-w-0">
                                                    <div class="flex-shrink-0 mr-3">
                                                        @if ($branch->is_main)
                                                            <div class="relative">
                                                                <i class="text-yellow-500 fa-solid fa-store"
                                                                    title="Main Branch"></i>
                                                                <i
                                                                    class="absolute text-xs -top-1 -right-1 fa-solid fa-crown"></i>
                                                            </div>
                                                        @else
                                                            <i class="text-gray-500 fa-solid fa-store dark:text-gray-400"></i>
                                                        @endif
                                                    </div>

                                                    <div class="flex-1 min-w-0">
                                                        <p class="font-medium text-gray-900 truncate dark:text-white"
                                                            :class="selectedBranchId == {{ $branch->id }} ?
                                                                'text-blue-600 dark:text-blue-400' : ''">
                                                            {{ $branch->name }}
                                                        </p>

                                                        @if ($branch->location)
                                                            <p
                                                                class="text-xs text-gray-500 truncate dark:text-gray-400">
                                                                {{ Str::limit($branch->location, 25) }}</p>
                                                        @endif

                                                        @if ($branch->phone)
                                                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                                                {{ $branch->phone }}</p>
                                                        @endif
                                                    </div>
                                                </div>

                                                {{-- The plan is spa-wide, so it is shown once on the trigger, not per branch. --}}
                                                <span x-show="selectedBranchId == {{ $branch->id }}"
                                                    class="flex-shrink-0 ml-2 text-blue-600 dark:text-blue-400">
                                                    <i class="fa-solid fa-check"></i>
                                                </span>
                                            </button>
                                        @endforeach
                                    </div>

                                    @if ($canManageBranches)
                                        <div class="pt-1 mt-1 border-t dark:border-gray-700">
                                            <a href="{{ route('branches.index') }}"
                                                class="flex items-center justify-center px-4 py-2 text-sm text-center text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-700">
                                                <i class="w-4 mr-2 fa-solid fa-cog"></i>
                                                Manage All Branches
                                            </a>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @elseif ($branches->isNotEmpty())
                    {{-- One branch, or a role pinned to its branch: the same box, but not a control. --}}
                    <div class="px-6 pb-4">
                        <div class="flex items-center w-full px-4 py-3 text-sm rounded-lg bg-gray-50 dark:bg-gray-700/50 dark:text-gray-200">
                            <i class="mr-3 text-gray-500 shrink-0 fa-solid fa-location-dot dark:text-gray-400" aria-hidden="true"></i>
                            <div class="flex-1 min-w-0">
                                <p class="font-medium truncate" title="{{ $currentBranch?->name }}">{{ $currentBranch?->name }}</p>
                                <div class="flex items-center min-w-0 gap-2 mt-0.5">
                                    <span class="px-2 py-0.5 text-[10px] font-semibold rounded-full shrink-0 {{ $planBadgeClasses }}">
                                        {{ $currentPlanName }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="px-6 pb-4">
                        <div class="px-4 py-3 text-sm text-center rounded-lg bg-yellow-50 dark:bg-yellow-900/20">
                            <p class="text-yellow-800 dark:text-yellow-200">
                                <i class="mr-2 fa-solid fa-exclamation-triangle"></i>
                                No branches set up
                            </p>
                            <p class="mt-1 text-xs text-yellow-700 dark:text-yellow-300">
                                You need to create at least one branch to use the system
                            </p>

                            @if (\Illuminate\Support\Facades\Route::has('setup.branches'))
                                <a href="{{ route('setup.branches') }}"
                                    class="inline-flex items-center justify-center w-full px-3 py-2 mt-2 text-xs font-medium text-white bg-yellow-600 rounded-lg hover:bg-yellow-700 dark:bg-yellow-700 dark:hover:bg-yellow-600">
                                    <i class="mr-1 fa-solid fa-plus"></i>
                                    Create First Branch
                                </a>
                            @endif
                        </div>
                    </div>
                @endif
            </div>

            <!-- Navigation -->
            <nav aria-label="Main" class="flex-1 px-4 py-4 overflow-y-auto">

                @if ($billingNotice)
                    <div class="px-4 py-3 mb-3 text-sm border rounded-lg border-amber-200 bg-amber-50 dark:border-amber-800 dark:bg-amber-900/20" role="status">
                        <p class="text-amber-800 dark:text-amber-200">
                            <i class="mr-2 fa-solid fa-hourglass-half" aria-hidden="true"></i>
                            {{ $billingNotice['text'] }}
                        </p>

                        <a href="{{ route('owner.subscription.index') }}"
                            class="inline-flex items-center justify-center w-full px-3 py-2 mt-2 text-xs font-medium text-white rounded-lg bg-[#8B7355] hover:bg-[#7A6348]">
                            {{ $billingNotice['action'] }}
                        </a>
                    </div>
                @endif

                {{-- Zones (answer P1 = A2). The branch zone is labelled with the branch it shows and
                     follows the switcher; the Whole spa zone does not react to it. --}}
                @php $branchZone = collect($nav['zones'])->firstWhere('key', 'branch'); @endphp
                <p class="px-4 pb-1 text-[11px] font-semibold tracking-wider text-gray-500 uppercase truncate dark:text-gray-400"
                    title="{{ $branchZone['label'] ?? $mobileContextLabel }}">
                    {{ $branchZone['label'] ?? $mobileContextLabel }}
                </p>

                @if ($branchLocked)
                    <div class="px-4 py-3 mt-1 mb-1 text-sm rounded-lg bg-yellow-50 dark:bg-yellow-900/20">
                        <p class="text-yellow-800 dark:text-yellow-200">
                            <i class="mr-2 fa-solid fa-lock" aria-hidden="true"></i>
                            {{ $branchLockMessage }}
                        </p>

                        @role('owner')
                            <a href="{{ $branchLockReason === 'plan_limit' ? route('owner.subscription.index') : route('owner.spa-profile.edit') }}"
                                class="inline-flex items-center justify-center w-full px-3 py-2 mt-2 text-xs font-medium text-white bg-yellow-600 rounded-lg hover:bg-yellow-700 dark:bg-yellow-700 dark:hover:bg-yellow-600">
                                {{ $branchLockReason === 'plan_limit' ? 'View Subscription' : 'Open Branch Documents' }}
                            </a>
                        @endrole
                    </div>
                @endif

                {{-- Dashboard, My Payslips, pinned links, then the zones. Shared with the admin sidebar. --}}
                @include('layouts.partials.sidebar-items', ['nav' => $nav, 'labelledZone' => 'branch'])

            </nav>

            <!-- ACCOUNT & LOGOUT -->
            <div class="flex-shrink-0 p-3 border-t dark:border-gray-700">
                {{-- Help & Guides (help guides Phase 1, Q1 → C): in the fixed footer, so it stays
                     visible however long the menu is, and doesn't push the pinned links down. --}}
                @if ($showHelpLink)
                    @php $helpActive = request()->routeIs('help.*'); @endphp
                    <a href="{{ route('help.index') }}"
                       @if ($helpActive) aria-current="page" @endif
                       class="flex items-center w-full min-h-[44px] gap-2 px-3 py-2 mb-2 font-medium transition-colors rounded-lg {{ $helpActive ? 'bg-gray-100 text-gray-900 dark:bg-gray-700 dark:text-white' : 'text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700' }}">
                        <i class="fa-solid fa-circle-question w-4 shrink-0 text-[#8B7355] dark:text-[#C4A97D]" aria-hidden="true"></i>
                        <span class="min-w-0">Help &amp; Guides</span>
                    </a>
                @endif
                @if ($appLinkLabel)
                    <a href="{{ route('mobile-app') }}" data-testid="sidebar-app-link"
                       class="flex items-center w-full min-h-[44px] gap-2 px-3 py-2 mb-2 font-medium transition-colors rounded-lg text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700">
                        <i class="fa-solid fa-mobile-screen w-4 shrink-0 text-[#8B7355] dark:text-[#C4A97D]" aria-hidden="true"></i>
                        <span class="min-w-0">{{ $appLinkLabel }}</span>
                    </a>
                @endif
                @auth
                    <div class="flex items-center justify-between gap-1">
                        {{-- Account. The identity block itself is the link to the profile page. --}}
                        <a href="{{ route('profile.edit') }}"
                           @if (request()->routeIs('profile.*')) aria-current="page" @endif
                           class="flex items-center flex-1 min-w-0 gap-3 px-2 py-1.5 -mx-1 transition-colors rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 {{ request()->routeIs('profile.*') ? 'bg-gray-100 dark:bg-gray-700' : '' }}">
                            {{-- Profile photo, or initials when there is none. --}}
                            <x-avatar :user="Auth::user()" size="sm" />
                            <span class="flex-1 min-w-0">
                                <span class="block text-sm font-medium text-gray-800 truncate dark:text-white">{{ Auth::user()->name }}</span>
                                <span class="block text-xs text-gray-500 truncate dark:text-gray-400">{{ Auth::user()->email }}</span>
                            </span>
                        </a>
                        <button @click="showLogoutModal = true"
                                class="flex items-center justify-center w-11 h-11 shrink-0 text-gray-600 transition-colors rounded-lg hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20"
                                title="Log out" aria-label="Log out">
                            <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i>
                        </button>
                    </div>
                @else
                    <div class="text-center">
                        <p class="text-sm text-gray-500 dark:text-gray-400">Not logged in</p>
                        <a href="{{ route('login') }}"
                        class="inline-flex items-center justify-center w-full px-4 py-2 mt-2 text-sm font-medium text-white transition-colors rounded-lg bg-[#8B7355] hover:bg-[#6F5430]">
                            <i class="mr-2 fa-solid fa-sign-in-alt"></i>
                            Login
                        </a>
                    </div>
                @endauth
            </div>

        </div>
    </aside>

    <!-- OVERLAY for Mobile -->
    <div x-show="open" x-cloak @click="open = false" aria-hidden="true"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-40 bg-black/40 md:hidden"></div>

    <!-- Logout Confirmation Modal -->
    <div x-show="showLogoutModal" x-cloak
        @keydown.escape.window="showLogoutModal = false"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 dark:bg-black/70">
        <div class="w-[80%] max-w-sm overflow-hidden bg-white dark:bg-gray-800 shadow-2xl rounded-3xl ring-1 ring-black/10 dark:ring-white/10"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 transform scale-95"
            x-transition:enter-end="opacity-100 transform scale-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 transform scale-100"
            x-transition:leave-end="opacity-0 transform scale-95">

            <div class="flex items-center justify-between px-6 py-4 border-b border-black/5 dark:border-white/10">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Confirm Logout</h3>
                <button @click="showLogoutModal = false"
                    class="flex items-center justify-center w-10 h-10 transition rounded-xl hover:bg-black/5 dark:hover:bg-white/10">
                    <i class="text-lg text-gray-700 dark:text-gray-300 fa-solid fa-xmark"></i>
                </button>
            </div>

            <div class="p-6">
                <div class="flex items-start gap-4">
                    <div class="flex items-center justify-center flex-shrink-0 w-12 h-12 bg-red-100 rounded-full dark:bg-red-900/30">
                        <i class="text-xl text-red-600 dark:text-red-400 fa-solid fa-right-from-bracket"></i>
                    </div>
                    <div>
                        <h4 class="font-semibold text-gray-900 dark:text-white">Are you sure?</h4>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            You will be logged out of your account and redirected to the home page.
                        </p>
                    </div>
                </div>
            </div>

            <div class="flex gap-2 px-6 pb-6">
                <button @click="showLogoutModal = false"
                    class="flex-1 py-2.5 rounded-xl text-sm font-semibold text-gray-700 dark:text-gray-200 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 transition">
                    Cancel
                </button>
                <form method="POST" action="{{ route('logout') }}" id="logoutForm"
                      x-on:submit="clearSidebarState()" class="flex-1">
                    @csrf
                    <button type="submit"
                        class="w-full py-2.5 rounded-xl text-sm font-semibold text-white bg-red-600 hover:bg-red-700 focus:ring-4 focus:ring-red-300 dark:focus:ring-red-900 transition">
                        Yes, Logout
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- MAIN CONTENT -->
    <main id="main-content" tabindex="-1" class="relative flex-1 h-screen overflow-y-auto md:ml-64">
        <div class="pt-14 md:pt-0">
            {{-- First-login banner (help guides Phase 1, D4): business users, until dismissed or Help is opened. --}}
            @include('layouts.partials.guides-banner')
            @yield('content')
        </div>
    </main>

</div>

@include('layouts.partials.sidebar-script')

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('sidebar', () => ({
        // Groups + drawer: layouts/partials/sidebar-script (shared with the admin sidebar).
        // routeSection comes from App\Services\Navigation\Sidebar — never re-derive it here.
        ...window.levictasSidebar({
            storageKey: 'levictas_sidebar_open_section_{{ auth()->id() ?? "guest" }}',
            routeSection: @json($activeSection),
            badgesUrl: @json(route('nav.badges')),
        }),

        mobileBranchesOpen: false,
        branchesDropdown: false,
        showSelfRequestModal: false,

        selectedBranch: {{ Js::from($currentBranch?->name ?? 'Select Branch') }},
        selectedBranchId: {{ $currentBranch?->id ?? 'null' }},
        isOnBranchEditPage: {{ Js::from(request()->routeIs('branches.edit')) }},
        branchEditUrlTemplate: {{ Js::from(route('branches.edit', ['branch' => '__BRANCH_ID__'])) }},

        switchBranch(branchId) {
            fetch('/branch/switch', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
                    'Accept': 'application/json',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ branch_id: branchId }),
            })

            .then(response => {
                if (!response.ok) throw new Error('Network response was not ok');
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    showSpaToast('Branch switched successfully', 'success');
                    setTimeout(() => {
                    if (this.isOnBranchEditPage) {
                        const url = new URL(this.branchEditUrlTemplate.replace('__BRANCH_ID__', branchId), window.location.origin);
                        // preserve ?tab=... if present
                        url.search = window.location.search;
                        window.location.href = url.toString();
                    } else {
                        window.location.reload();
                    }
                }, 1000);
                } else {
                    showSpaToast(data.message || 'Failed to switch branch', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showSpaToast('An error occurred. Please try again.', 'error');
            });
        }
    }));
});
</script>