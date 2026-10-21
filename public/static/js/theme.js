/* 主题切换：暗色 / 亮色，记忆在 localStorage */
(function () {
    var KEY = 'cs-theme';
    var root = document.documentElement;
    var SUN = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="12" r="4.2"></circle><path d="M12 2.5v2.2M12 19.3v2.2M2.5 12h2.2M19.3 12h2.2M5.2 5.2l1.6 1.6M17.2 17.2l1.6 1.6M18.8 5.2l-1.6 1.6M6.8 17.2l-1.6 1.6"></path></svg>';
    var MOON = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20.5 14.3A8.5 8.5 0 0 1 9.7 3.5a8.5 8.5 0 1 0 10.8 10.8z"></path></svg>';

    function current() {
        return root.getAttribute('data-theme') === 'light' ? 'light' : 'dark';
    }

    function fromUrl() {
        try {
            var q = new URLSearchParams(location.search).get('theme');
            return (q === 'light' || q === 'dark') ? q : '';
        } catch (e) { return ''; }
    }

    // URL 参数优先（便于分享/截图），其次跟随 <html data-theme> 的初始值
    var initial = fromUrl();
    if (initial) { root.setAttribute('data-theme', initial); }

    function apply(theme) {
        root.setAttribute('data-theme', theme);
        try { localStorage.setItem(KEY, theme); } catch (e) {}
        var btn = document.getElementById('themeToggle');
        if (btn) {
            btn.innerHTML = theme === 'light' ? MOON : SUN;
            btn.setAttribute('title', theme === 'light' ? '切换到暗色主题' : '切换到亮色主题');
            btn.setAttribute('aria-label', btn.getAttribute('title'));
        }
        document.dispatchEvent(new CustomEvent('themechange', { detail: { theme: theme } }));
    }

    function inject() {
        if (document.getElementById('themeToggle')) return;
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.id = 'themeToggle';
        btn.className = 'theme-toggle';
        btn.addEventListener('click', function () {
            apply(current() === 'light' ? 'dark' : 'light');
        });
        document.body.appendChild(btn);
        apply(current());
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', inject);
    } else {
        inject();
    }

    window.toggleTheme = function () {
        apply(current() === 'light' ? 'dark' : 'light');
    };
})();
