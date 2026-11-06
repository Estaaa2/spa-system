// =====================================================
// LANDING NAV — shared by the landing page and the public pages
// =====================================================
/*
  Behaviour for resources/views/partials/landing/nav.blade.php and
  logout-modal.blade.php (landing footer Phase 1). welcome.js imports this
  file, so / gets it from there; layouts/public.blade.php loads it on its own
  so the public pages don't pull in the whole landing script.

  Every lookup is null-safe: welcome.js (and so this file) also runs inside
  layouts/app.blade.php, where none of these elements exist.
*/

// Mobile menu toggle
const mobileMenuBtn = document.getElementById('mobile-menu-button');
const mobileMenu    = document.getElementById('mobile-menu');
mobileMenuBtn?.addEventListener('click', () => mobileMenu?.classList.toggle('hidden'));

// Glass navbar once the page scrolls
const topNav = document.getElementById('topNav');
window.addEventListener('scroll', () => {
    if (window.scrollY > 10) topNav?.classList.add('nav-scrolled');
    else topNav?.classList.remove('nav-scrolled');
});

// =====================================================
// PROFILE DROPDOWN (customers)
// =====================================================
const profileDropdownBtn  = document.getElementById('profileDropdownBtn');
const profileDropdownMenu = document.getElementById('profileDropdownMenu');
const profileChevron      = document.getElementById('profileChevron');

function closeProfileDropdown() {
    profileDropdownMenu?.classList.add('hidden');
    profileChevron?.classList.remove('rotate-180');
}

profileDropdownBtn?.addEventListener('click', function (e) {
    e.stopPropagation();
    const isHidden = profileDropdownMenu.classList.contains('hidden');
    if (isHidden) {
        profileDropdownMenu.classList.remove('hidden');
        profileChevron?.classList.add('rotate-180');
    } else {
        closeProfileDropdown();
    }
});

document.addEventListener('click', function (e) {
    const wrapper = document.getElementById('profileDropdownWrapper');
    if (wrapper && !wrapper.contains(e.target)) closeProfileDropdown();
});

// =====================================================
// LOGOUT CONFIRMATION
// =====================================================
function openLogoutModal() {
    document.getElementById('logoutModal')?.classList.remove('hidden');
    document.body.classList.add('overflow-hidden');
}

function closeLogoutModal() {
    document.getElementById('logoutModal')?.classList.add('hidden');
    document.body.classList.remove('overflow-hidden');
}

/*
  Escape closes the logout window. It is only opened from the navbar, which
  sits under every modal backdrop, so when it is open it is the top layer.
  This listener is registered before welcome.js's Escape handler (imported
  modules run first), so stopping here keeps one Escape = one layer on /.
*/
window.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape') return;
    const modal = document.getElementById('logoutModal');
    if (modal && !modal.classList.contains('hidden')) {
        closeLogoutModal();
        e.stopImmediatePropagation();
    }
});

window.closeProfileDropdown = closeProfileDropdown;
window.openLogoutModal      = openLogoutModal;
window.closeLogoutModal     = closeLogoutModal;
