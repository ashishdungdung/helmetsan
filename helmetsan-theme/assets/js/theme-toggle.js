/**
 * Light/dark theme toggle. Sets data-theme on <html> and persists to localStorage.
 */
(function () {
    var STORAGE_KEY = 'helmetsan_theme';
    var THEMES = ['light', 'dark'];

    function getStored() {
        try {
            var t = localStorage.getItem(STORAGE_KEY);
            if (t && THEMES.indexOf(t) !== -1) return t;
        } catch (e) {}
        return 'light';
    }

    function applyTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        try {
            localStorage.setItem(STORAGE_KEY, theme);
        } catch (e) {}
    }

    // Apply stored theme immediately to avoid flash
    applyTheme(getStored());

    document.addEventListener('DOMContentLoaded', function () {
        var toggle = document.getElementById('hs-theme-toggle');
        if (!toggle) return;

        toggle.addEventListener('click', function () {
            var next = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            applyTheme(next);
            toggle.setAttribute('aria-label', next === 'dark' ? 'Switch to dark mode' : 'Switch to light mode');
            toggle.querySelector('[data-theme-icon]') && toggle.querySelector('[data-theme-icon]').setAttribute('aria-hidden', 'true');
        });
    });
})();
