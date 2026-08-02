<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>[x-cloak] { display: none !important; }</style>

    <title>@yield('title', 'Levictas') | Levictas</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Alpine.js with Collapse Plugin -->
    <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/collapse@3.x.x/dist/cdn.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Toastify -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/js/welcome.js'])
</head>

<body class="font-sans antialiased">
    <div class="min-h-screen bg-gray-100 dark:bg-gray-900">
        @if(auth()->check() && auth()->user()->hasRole('admin'))
            @include('layouts.navigation-admin')
        @elseif(auth()->check() && auth()->user()->hasRole('hr'))
            @include('layouts.navigation')
        @elseif(auth()->check() && auth()->user()->hasRole('finance'))
            @include('layouts.navigation')
        @else
            @include('layouts.navigation')
        @endif

        <!-- Page Heading -->
        @isset($header)
            <header class="bg-white shadow dark:bg-gray-800">
                <div class="px-4 py-6 mx-auto max-w-7xl sm:px-6 lg:px-8">
                    {{ $header }}
                </div>
            </header>
        @endisset

        <x-toast />
    </div>

    {{-- ================= FORCE PASSWORD CHANGE MODAL ================= --}}
    @auth
        @if(auth()->user()->password_reset_required)
        <!-- Your password modal HTML here... -->
        @endif
    @endauth

    {{-- ================= ALPINE COMPONENTS ================= --}}
<script>
    document.addEventListener('alpine:init', () => {
        // Which accordion section(s) contain the page currently being
        // viewed. Used to force that section open on load, so navigating
        // into it never requires re-clicking the dropdown. A route can
        // appear in two lists (e.g. attendance lives under different
        // sections depending on business tier) — that's harmless, since
        // opening a section that isn't rendered for this account does
        // nothing.
        const routeIsOperations = {{ (request()->routeIs('booking') || request()->routeIs('appointments.*') || request()->routeIs('schedule.*') || request()->routeIs('therapist.performance') || request()->routeIs('attendance.*')) ? 'true' : 'false' }};
        const routeIsPeople     = {{ (request()->routeIs('hiring.*') || request()->routeIs('applications.*') || request()->routeIs('interviews.*') || request()->routeIs('staff.*') || request()->routeIs('deployment.*') || request()->routeIs('attendance.*') || request()->routeIs('payroll.*')) ? 'true' : 'false' }};
        const routeIsManagement = {{ (request()->routeIs('services.*') || request()->routeIs('staff.*') || request()->routeIs('branches.*')) ? 'true' : 'false' }};
        const routeIsFinance    = {{ (request()->routeIs('revenue.*') || request()->routeIs('billing.*')) ? 'true' : 'false' }};
        const routeIsInsights   = {{ (request()->routeIs('decision-support.*') || request()->routeIs('reports.*')) ? 'true' : 'false' }};
        const routeIsInventory  = {{ (request()->routeIs('inventory.*')) ? 'true' : 'false' }};
        const routeIsSettings   = {{ (request()->routeIs('profile.*') || request()->routeIs('owner.spa-profile.*') || request()->routeIs('owner.workforce-finance-suite.*') || request()->routeIs('owner.subscription.*') || request()->routeIs('owner.roles-permissions.*')) ? 'true' : 'false' }};

        const SIDEBAR_STORAGE_KEY = 'levictas_sidebar_state';
        let savedSidebarState = {};
        try {
            savedSidebarState = JSON.parse(localStorage.getItem(SIDEBAR_STORAGE_KEY)) || {};
        } catch (e) {
            savedSidebarState = {};
        }

        // Define the sidebar component
        Alpine.data('sidebar', () => ({
            open: false,
            showLogoutModal: false,
            operationsOpen: savedSidebarState.operationsOpen ?? routeIsOperations,
            peopleOpen: savedSidebarState.peopleOpen ?? routeIsPeople,
            managementOpen: savedSidebarState.managementOpen ?? routeIsManagement,
            financeOpen: savedSidebarState.financeOpen ?? routeIsFinance,
            insightsOpen: savedSidebarState.insightsOpen ?? routeIsInsights,
            branchesDropdown: false,
            mobileBranchesOpen: false,
            inventoryOpen: savedSidebarState.inventoryOpen ?? routeIsInventory,
            settingsOpen: savedSidebarState.settingsOpen ?? routeIsSettings,
            selectedBranch: @json($currentBranch?->name ?? ($firstBranch?->name ?? 'Select Branch')),
            selectedBranchId: @json($currentBranch?->id ?? ($firstBranch?->id ?? null)),

            init() {
                // The section containing the current page always wins, even
                // if it was previously closed — you shouldn't have to
                // re-open the section you're actively looking at.
                if (routeIsOperations) this.operationsOpen = true;
                if (routeIsPeople) this.peopleOpen = true;
                if (routeIsManagement) this.managementOpen = true;
                if (routeIsFinance) this.financeOpen = true;
                if (routeIsInsights) this.insightsOpen = true;
                if (routeIsInventory) this.inventoryOpen = true;
                if (routeIsSettings) this.settingsOpen = true;

                this.persistSidebarState();

                this.$watch('operationsOpen', () => this.persistSidebarState());
                this.$watch('peopleOpen', () => this.persistSidebarState());
                this.$watch('managementOpen', () => this.persistSidebarState());
                this.$watch('financeOpen', () => this.persistSidebarState());
                this.$watch('insightsOpen', () => this.persistSidebarState());
                this.$watch('inventoryOpen', () => this.persistSidebarState());
                this.$watch('settingsOpen', () => this.persistSidebarState());
            },

            persistSidebarState() {
                localStorage.setItem(SIDEBAR_STORAGE_KEY, JSON.stringify({
                    operationsOpen: this.operationsOpen,
                    peopleOpen: this.peopleOpen,
                    managementOpen: this.managementOpen,
                    financeOpen: this.financeOpen,
                    insightsOpen: this.insightsOpen,
                    inventoryOpen: this.inventoryOpen,
                    settingsOpen: this.settingsOpen,
                }));
            },
        }));

        // Define the switchBranch function globally
        window.switchBranch = function(branchId) {
            const button = event.target.closest('button');
            const originalContent = button.innerHTML;
            button.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
            button.disabled = true;

            fetch('/branch/switch', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                credentials: 'same-origin',
                body: JSON.stringify({ branch_id: branchId })
            })
            .then(response => {
                if (!response.ok) throw new Error('Network response was not ok');
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    showSpaToast('Branch switched successfully', 'success');
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    showSpaToast(data.message || 'Failed to switch branch', 'error');
                    button.innerHTML = originalContent;
                    button.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showSpaToast('An error occurred. Please try again.', 'error');
                button.innerHTML = originalContent;
                button.disabled = false;
            });
        };
    });
</script>

    {{-- Toast function (if not already defined elsewhere) --}}
    <script>
        // Single unified toast function used everywhere
        function showSpaToast(message, type = 'success') {
            const isSuccess = type === 'success';

            Toastify({
                text: `
                    <div style="display:flex; align-items:center; gap:12px; padding: 2px 0;">
                        <div style="
                            width: 36px;
                            height: 36px;
                            border-radius: 50%;
                            background: ${isSuccess ? '#f0fdf4' : '#fef2f2'};
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            flex-shrink: 0;
                        ">
                            <i class="${isSuccess ? 'fa-solid fa-spa' : 'fa-solid fa-circle-xmark'}"
                               style="color: ${isSuccess ? '#16a34a' : '#dc2626'}; font-size: 15px;">
                            </i>
                        </div>
                        <div style="display:flex; flex-direction:column; gap:2px;">
                            <span style="
                                font-size: 11px;
                                font-weight: 600;
                                letter-spacing: 0.08em;
                                text-transform: uppercase;
                                color: ${isSuccess ? '#15803d' : '#b91c1c'};
                            ">${isSuccess ? 'Success' : 'Error'}</span>
                            <span style="
                                font-size: 13px;
                                color: #374151;
                                font-weight: 400;
                                line-height: 1.4;
                            ">${message}</span>
                        </div>
                    </div>
                `,
                duration: 2000,
                gravity: "top",
                position: "right",
                close: false,
                escapeMarkup: false,
                style: {
                    background: "#ffffff",
                    border: isSuccess ? "1px solid #bbf7d0" : "1px solid #fecaca",
                    borderLeft: isSuccess ? "4px solid #16a34a" : "4px solid #dc2626",
                    borderRadius: "10px",
                    minWidth: "300px",
                    maxWidth: "360px",
                    padding: "14px 18px",
                    boxShadow: "0 10px 30px rgba(0,0,0,0.08), 0 2px 8px rgba(0,0,0,0.04)",
                }
            }).showToast();
        }

        // For sessionStorage-based toasts (e.g. after redirects)
        document.addEventListener('DOMContentLoaded', function () {
            const type = sessionStorage.getItem('toast_type');
            const message = sessionStorage.getItem('toast_message');
            if (type && message) {
                showSpaToast(message, type);
                sessionStorage.removeItem('toast_type');
                sessionStorage.removeItem('toast_message');
            }
        });
    </script>
</body>

</html>
