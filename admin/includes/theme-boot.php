<?php
/**
 * Apply the saved admin theme (and desktop sidebar width) before first paint.
 * Include from each admin <head>, before admin.min.css.
 */
require_once __DIR__ . '/assets.php';
admin_icons_begin();
?>
<script>
window.XPM_CSRF = <?php echo json_encode(auth_csrf_token()); ?>;
(function () {
    var nativeSubmit = HTMLFormElement.prototype.submit;
    HTMLFormElement.prototype.submit = function () {
        var method = (this.getAttribute('method') || this.method || 'get').toLowerCase();
        if (method === 'post' && window.XPM_CSRF && !this.querySelector('[name="csrf_token"]')) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'csrf_token';
            input.value = window.XPM_CSRF;
            this.appendChild(input);
        }
        return nativeSubmit.call(this);
    };
})();
(function () {
    var themeKey = 'xpm-admin-theme';
    var sideKey = 'xpm-admin-sidebar';
    try {
        var mode = localStorage.getItem(themeKey) || 'system';
        if (mode !== 'light' && mode !== 'dark' && mode !== 'system') mode = 'system';
        var dark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        var theme = (mode === 'dark' || (mode === 'system' && dark)) ? 'dark' : 'light';
        var root = document.documentElement;
        root.setAttribute('data-theme', theme);
        root.setAttribute('data-theme-mode', mode);
        if (localStorage.getItem(sideKey) === 'collapsed') {
            root.setAttribute('data-sidebar', 'collapsed');
        }
    } catch (e) {}
})();
</script>
