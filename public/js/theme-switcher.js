// Theme switcher: toggles data-bs-theme and persists the choice.
(function () {
    'use strict';

    const root = document.documentElement;
    const toggle = document.getElementById('theme-toggle');
    const icon = toggle ? toggle.querySelector('i') : null;

    function apply(theme) {
        root.setAttribute('data-bs-theme', theme);
        localStorage.setItem('cv-theme', theme);
        if (icon) {
            icon.className = theme === 'dark' ? 'bi bi-sun' : 'bi bi-moon-stars';
        }
    }

    if (toggle) {
        toggle.addEventListener('click', function () {
            apply(root.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark');
        });
        // Sync the icon with the theme applied before render.
        apply(root.getAttribute('data-bs-theme'));
    }
})();
