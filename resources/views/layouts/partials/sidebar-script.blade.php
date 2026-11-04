{{--
    Shared Alpine behaviour for both sidebars (sidebar & RBAC rework Phases 2–3):
    collapsible groups (one open at a time, remembered per user) and the phone
    drawer — inert while closed, Tab trapped and page scroll locked while open,
    Escape closes it and focus returns to the menu button.

    Each layout spreads it into its own component:
        Alpine.data('sidebar', () => ({ ...window.levictasSidebar({ storageKey, routeSection }), …extra }))
    Markup contract: <aside id="app-sidebar">, <main id="main-content">, a toggle
    button with x-ref="drawerToggle", and @keydown.tab="trapFocus($event)" on the aside.

    Badges (Phase 4): pass badgesUrl to refresh the counts every 60 s while the
    tab is visible (paused when hidden, caught up on return, stopped on pagehide —
    the dashboard's polling lifecycle). It only updates the slots that
    components/nav-badge rendered; it never builds markup.
--}}
<style>
    /* The badge carries a display utility (inline-flex), which would beat the
       [hidden] attribute; this keeps a zero-count badge hidden. */
    [data-nav-badge][hidden] { display: none !important; }
</style>
<script>
window.levictasSidebar = window.levictasSidebar || function ({ storageKey, routeSection, badgesUrl = null }) {
    const POLL_MS = 60000;

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

            this.startBadgeRefresh();
        },

        // ── Badges ────────────────────────────────────────────────────────
        // No fetch at startup: the server render is already fresh (design doc §9).
        startBadgeRefresh() {
            if (!badgesUrl) return;

            let timer = null;
            let lastFetch = Date.now();
            let lastSignature = null;

            const tick = async () => {
                lastFetch = Date.now();
                try {
                    const res = await fetch(badgesUrl, {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin',
                    });
                    if (res.status === 401 || res.status === 419) { stop(); return; }   // logged out
                    if (!res.ok) return;
                    const data = await res.json();
                    const signature = JSON.stringify(data.badges || {});
                    if (signature === lastSignature) return;
                    lastSignature = signature;
                    this.applyBadges(data.badges || {});
                } catch (e) { /* offline or navigating away: try again next tick */ }
            };
            const start = () => { if (!timer) timer = setInterval(tick, POLL_MS); };
            const stop = () => { if (timer) { clearInterval(timer); timer = null; } };

            document.addEventListener('visibilitychange', () => {
                if (document.hidden) { stop(); return; }
                if (Date.now() - lastFetch >= POLL_MS) tick();
                start();
            });
            window.addEventListener('pagehide', stop);

            if (!document.hidden) start();
        },

        applyBadges(badges) {
            document.querySelectorAll('[data-nav-badge]').forEach((el) => {
                const b = badges[el.dataset.navBadge] || null;
                const count = b ? Number(b.count) || 0 : 0;
                const urgent = !!(b && b.urgent);

                el.dataset.count = String(count);
                el.className = urgent ? el.dataset.classUrgent : el.dataset.classNormal;
                el.hidden = count <= 0;

                const num = el.querySelector('[data-nav-badge-count]');
                const bang = el.querySelector('[data-nav-badge-urgent]');
                const label = el.querySelector('[data-nav-badge-label]');
                if (num) num.textContent = count > 99 ? '99+' : String(count);
                if (bang) bang.hidden = !urgent;
                if (label) label.textContent = count > 0 ? ', ' + b.label : '';
            });

            // Amber dot on a collapsed group when anything inside has a count.
            document.querySelectorAll('[data-nav-group-dot]').forEach((dot) => {
                const section = document.getElementById('nav-section-' + dot.dataset.navGroupDot);
                const total = section
                    ? [...section.querySelectorAll('[data-nav-badge]')].reduce((sum, el) => sum + (Number(el.dataset.count) || 0), 0)
                    : 0;
                dot.hidden = total <= 0;
            });
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
