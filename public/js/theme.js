(function () {
    var STORAGE_KEY = 'belanja-theme';

    function getStoredTheme() {
        try {
            return localStorage.getItem(STORAGE_KEY);
        } catch (e) {
            return null;
        }
    }

    function systemPrefersDark() {
        return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
    }

    function resolveTheme(stored) {
        if (stored === 'dark' || stored === 'light') {
            return stored;
        }

        return systemPrefersDark() ? 'dark' : 'light';
    }

    function applyTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);

        var meta = document.querySelector('meta[name="theme-color"]');
        if (meta) {
            meta.setAttribute('content', theme === 'dark' ? '#1a1f2e' : '#1f5c38');
        }
    }

    function initTheme() {
        applyTheme(resolveTheme(getStoredTheme()));
    }

    function toggleTheme() {
        var current = document.documentElement.getAttribute('data-theme') || 'light';
        var next = current === 'dark' ? 'light' : 'dark';

        try {
            localStorage.setItem(STORAGE_KEY, next);
        } catch (e) {
            // ignore
        }

        applyTheme(next);
    }

    initTheme();

    document.addEventListener('DOMContentLoaded', function () {
        ['themeToggle', 'themeToggleDesktop'].forEach(function (id) {
            var btn = document.getElementById(id);
            if (btn) {
                btn.addEventListener('click', toggleTheme);
            }
        });
    });

    if (window.matchMedia) {
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function () {
            if (!getStoredTheme()) {
                applyTheme(resolveTheme(null));
            }
        });
    }
})();
