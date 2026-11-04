{{--
    Shared Alpine behaviour for both sidebars (sidebar & RBAC rework Phases 2–3):
    collapsible groups (one open at a time, remembered per user) and the phone
    drawer — inert while closed, Tab trapped and page scroll locked while open,
    Escape closes it and focus returns to the menu button.

    Each layout spreads it into its own component:
        Alpine.data('sidebar', () => ({ ...window.levictasSidebar({ storageKey, routeSection }), …extra }))
    Markup contract: <aside id="app-sidebar">, <main id="main-content">, a toggle
    button with x-ref="drawerToggle", and @keydown.tab="trapFocus($event)" on the aside.
--}}
<script>
window.levictasSidebar = window.levictasSidebar || function ({ storageKey, routeSection }) {
    let saved = null;
    try { saved = localStorage.getItem(storageKey) || null; } catch (e) { saved = null; }

    return {
        open: false,
        isMobile: false,
        showLogoutModal: false,
        openSection: routeSection || saved,

        init() {
            if (routeSection) {
                this.openSection = routeSection;
                this.persist();
            }

            // Drawer behaviour only applies below md (768px), where the sidebar is off-canvas.
            const mq = window.matchMedia('(max-width: 767.98px)');
            this.isMobile = mq.matches;
            mq.addEventListener('change', (e) => {
                this.isMobile = e.matches;
                if (!e.matches) this.open = false;
            });

            this.$watch('open', (isOpen) => isOpen ? this.drawerOpened() : this.drawerClosed());
        },

        // ── Groups ────────────────────────────────────────────────────────
        isOpen(name) {
            return this.openSection === name;
        },

        toggleSection(name) {
            this.openSection = (this.openSection === name) ? null : name;
            this.persist();
        },

        persist() {
            try {
                if (this.openSection) {
                    localStorage.setItem(storageKey, this.openSection);
                } else {
                    localStorage.removeItem(storageKey);
                }
            } catch (e) {}
        },

        clearSidebarState() {
            try { localStorage.removeItem(storageKey); } catch (e) {}
        },

        // ── Drawer ────────────────────────────────────────────────────────
        // Scroll lock: <main> is the page's scroll container, so lock it and the root.
        setPageScroll(locked) {
            const main = document.getElementById('main-content');
            document.documentElement.style.overflow = locked ? 'hidden' : '';
            if (main) main.style.overflow = locked ? 'hidden' : '';
        },

        drawerFocusables() {
            const aside = document.getElementById('app-sidebar');
            if (!aside) return [];
            return [...aside.querySelectorAll('a[href], button:not([disabled]), input, select, textarea, [tabindex]:not([tabindex="-1"])')]
                .filter((el) => el.offsetParent !== null && getComputedStyle(el).visibility !== 'hidden');
        },

        drawerOpened() {
            if (!this.isMobile) return;
            this.setPageScroll(true);
            this.$nextTick(() => this.drawerFocusables()[0]?.focus());
        },

        drawerClosed() {
            this.setPageScroll(false);
            const aside = document.getElementById('app-sidebar');
            if (this.isMobile && (!document.activeElement || document.activeElement === document.body || aside?.contains(document.activeElement))) {
                this.$refs.drawerToggle?.focus();
            }
        },

        trapFocus(e) {
            if (!this.isMobile || !this.open) return;
            const items = this.drawerFocusables();
            if (items.length === 0) return;
            const first = items[0];
            const last = items[items.length - 1];
            if (e.shiftKey && document.activeElement === first) {
                e.preventDefault();
                last.focus();
            } else if (!e.shiftKey && document.activeElement === last) {
                e.preventDefault();
                first.focus();
            }
        },
    };
};
</script>
