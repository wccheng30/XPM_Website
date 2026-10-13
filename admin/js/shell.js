/* Admin shell: sidebar, drawer, account menu, theme, back to top. No dependencies. */
(function () {
    'use strict';

    window.__xpmShell = true;

    window.xpmFocusTrap = function (root, options) {
        options = options || {};
        if (!root) return function () {};
        var last = options.returnTo || document.activeElement;
        var active = true;
        function items() {
            return Array.prototype.slice.call(root.querySelectorAll(
                'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
            )).filter(function (el) {
                return !el.hidden && el.getAttribute('aria-hidden') !== 'true';
            });
        }
        function onKey(e) {
            if (!active) return;
            if (e.key === 'Escape') {
                e.preventDefault();
                e.stopPropagation();
                release();
                if (typeof options.onClose === 'function') options.onClose();
                return;
            }
            if (e.key !== 'Tab') return;
            var list = items();
            if (!list.length) {
                e.preventDefault();
                return;
            }
            var first = list[0];
            var lastEl = list[list.length - 1];
            if (e.shiftKey && document.activeElement === first) {
                e.preventDefault();
                lastEl.focus();
            } else if (!e.shiftKey && document.activeElement === lastEl) {
                e.preventDefault();
                first.focus();
            }
        }
        function release(restore) {
            if (!active) return;
            active = false;
            root.removeEventListener('keydown', onKey);
            if (restore !== false && last && typeof last.focus === 'function') last.focus();
        }
        root.addEventListener('keydown', onKey);
        var list = items();
        var focusEl = options.focus || list[0];
        if (focusEl && typeof focusEl.focus === 'function') {
            setTimeout(function () { if (active) focusEl.focus(); }, 0);
        }
        return release;
    };

    var THEME_KEY = 'xpm-admin-theme';
    var SIDEBAR_KEY = 'xpm-admin-sidebar';
    var root = document.documentElement;
    var scheme = window.matchMedia('(prefers-color-scheme: dark)');
    var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function ready(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    }

    function applyTheme(mode) {
        if (mode !== 'light' && mode !== 'dark' && mode !== 'system') mode = 'system';
        var theme = (mode === 'dark' || (mode === 'system' && scheme.matches)) ? 'dark' : 'light';
        root.setAttribute('data-theme', theme);
        root.setAttribute('data-theme-mode', mode);
        try {
            localStorage.setItem(THEME_KEY, mode);
        } catch (e) {}
        document.querySelectorAll('[data-theme-choice]').forEach(function (btn) {
            btn.setAttribute('aria-checked', btn.getAttribute('data-theme-choice') === mode ? 'true' : 'false');
        });
    }

    function onSchemeChange() {
        if (root.getAttribute('data-theme-mode') === 'system') applyTheme('system');
    }

    if (scheme.addEventListener) scheme.addEventListener('change', onSchemeChange);
    else if (scheme.addListener) scheme.addListener(onSchemeChange);

    ready(function () {
        var sidebar = document.getElementById('adminSidebar');
        var toggle = document.getElementById('sidebarToggle');
        var collapse = document.getElementById('sidebarCollapse');
        var overlay = document.getElementById('sidebarOverlay');
        var tip = document.getElementById('sidebarTip');
        var userBtn = document.getElementById('userMenuBtn');
        var userMenu = document.getElementById('userMenu');
        var drawerReturn = null;
        var menuRelease = null;
        var main = document.querySelector('.admin-container');
        if (main && !main.id) {
            main.id = 'admin-main';
            main.setAttribute('tabindex', '-1');
            main.setAttribute('role', 'main');
        }

        applyTheme(root.getAttribute('data-theme-mode') || 'system');

        function isMobile() {
            return window.innerWidth < 768;
        }

        function isRail() {
            if (isMobile()) return false;
            if (window.innerWidth < 1024) return true;
            return root.getAttribute('data-sidebar') === 'collapsed';
        }

        function setInert(el, on) {
            if (!el) return;
            if ('inert' in el) {
                el.inert = on;
                return;
            }
            el.setAttribute('aria-hidden', on ? 'true' : 'false');
            el.querySelectorAll('a, button').forEach(function (node) {
                if (on) {
                    if (!node.hasAttribute('data-prev-tab')) {
                        node.setAttribute('data-prev-tab', node.getAttribute('tabindex') || '');
                    }
                    node.setAttribute('tabindex', '-1');
                } else if (node.hasAttribute('data-prev-tab')) {
                    var prev = node.getAttribute('data-prev-tab');
                    if (prev) node.setAttribute('tabindex', prev);
                    else node.removeAttribute('tabindex');
                    node.removeAttribute('data-prev-tab');
                }
            });
        }

        function syncSidebar() {
            var collapsed = root.getAttribute('data-sidebar') === 'collapsed';
            if (collapse) {
                collapse.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
                collapse.setAttribute('aria-label', collapsed ? 'Expand sidebar' : 'Collapse sidebar');
                var label = collapse.querySelector('.sidebar-collapse-label');
                if (label) label.textContent = collapsed ? 'Expand' : 'Collapse';
            }
            var drawerOpen = document.body.classList.contains('sidebar-open');
            setInert(sidebar, isMobile() && !drawerOpen);
            if (!isMobile() && drawerOpen) setDrawer(false);
            if (!isRail()) hideTip();
        }

        function setCollapsed(collapsed) {
            if (collapsed) root.setAttribute('data-sidebar', 'collapsed');
            else root.removeAttribute('data-sidebar');
            try {
                localStorage.setItem(SIDEBAR_KEY, collapsed ? 'collapsed' : 'expanded');
            } catch (e) {}
            syncSidebar();
        }

        function focusables() {
            var nodes = [];
            if (toggle && isMobile()) nodes.push(toggle);
            if (!sidebar) return nodes;
            sidebar.querySelectorAll('a[href], button:not([disabled])').forEach(function (el) {
                if (el.offsetParent !== null) nodes.push(el);
            });
            return nodes;
        }

        function setDrawer(open) {
            document.body.classList.toggle('sidebar-open', open);
            if (toggle) {
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                toggle.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
            }
            if (overlay) overlay.hidden = !open;
            setInert(sidebar, isMobile() && !open);
            if (open) {
                drawerReturn = document.activeElement;
                var items = focusables();
                var firstLink = items.filter(function (el) { return el !== toggle; })[0];
                if (firstLink) firstLink.focus();
            } else if (drawerReturn && typeof drawerReturn.focus === 'function') {
                drawerReturn.focus();
                drawerReturn = null;
            }
        }

        function showTip(el) {
            if (!tip || !isRail()) return;
            var label = el.querySelector('.sidebar-label, .sidebar-collapse-label');
            if (!label) return;
            tip.textContent = label.textContent;
            tip.hidden = false;
            var rect = el.getBoundingClientRect();
            tip.style.top = (rect.top + rect.height / 2) + 'px';
            tip.style.left = (rect.right + 10) + 'px';
        }

        function hideTip() {
            if (tip) tip.hidden = true;
        }

        function closeUserMenu() {
            if (!userMenu || userMenu.hidden) return;
            userMenu.hidden = true;
            if (userBtn) userBtn.setAttribute('aria-expanded', 'false');
            if (menuRelease) {
                var release = menuRelease;
                menuRelease = null;
                release(false);
            }
        }

        function openUserMenu() {
            if (!userMenu) return;
            userMenu.hidden = false;
            if (userBtn) userBtn.setAttribute('aria-expanded', 'true');
            if (typeof window.xpmFocusTrap === 'function') {
                menuRelease = window.xpmFocusTrap(userMenu, {
                    returnTo: userBtn,
                    onClose: closeUserMenu
                });
            }
        }

        if (toggle) {
            toggle.addEventListener('click', function () {
                if (!isMobile()) return;
                setDrawer(!document.body.classList.contains('sidebar-open'));
            });
        }

        if (overlay) {
            overlay.addEventListener('click', function () { setDrawer(false); });
        }

        if (collapse) {
            collapse.addEventListener('click', function () {
                setCollapsed(root.getAttribute('data-sidebar') !== 'collapsed');
            });
        }

        if (sidebar) {
            function fromTarget(e, selector) {
                var node = e.target && e.target.closest ? e.target : (e.target && e.target.parentElement);
                if (!node || !node.closest) return null;
                return node.closest(selector);
            }

            sidebar.addEventListener('click', function (e) {
                var link = fromTarget(e, 'a');
                if (link && isMobile()) setDrawer(false);
            });
            sidebar.addEventListener('mouseover', function (e) {
                var el = fromTarget(e, '.sidebar-link, .sidebar-collapse');
                if (el) showTip(el);
            });
            sidebar.addEventListener('mouseout', function (e) {
                var el = fromTarget(e, '.sidebar-link, .sidebar-collapse');
                if (!el) return;
                var next = e.relatedTarget && e.relatedTarget.closest
                    ? e.relatedTarget.closest('.sidebar-link, .sidebar-collapse')
                    : null;
                if (next !== el) hideTip();
            });
            sidebar.addEventListener('focusin', function (e) {
                var el = fromTarget(e, '.sidebar-link, .sidebar-collapse');
                if (el) showTip(el);
            });
            sidebar.addEventListener('focusout', function () {
                hideTip();
            });
        }

        if (userBtn && userMenu) {
            userBtn.addEventListener('click', function () {
                if (userMenu.hidden) openUserMenu();
                else closeUserMenu();
            });
            userMenu.addEventListener('click', function (e) {
                var choice = e.target.closest('[data-theme-choice]');
                if (!choice) return;
                applyTheme(choice.getAttribute('data-theme-choice'));
            });
        }

        document.addEventListener('click', function (e) {
            if (!userMenu || userMenu.hidden) return;
            if (userMenu.contains(e.target) || (userBtn && userBtn.contains(e.target))) return;
            closeUserMenu();
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                if (document.body.classList.contains('sidebar-open')) {
                    e.preventDefault();
                    setDrawer(false);
                    return;
                }
                if (userMenu && !userMenu.hidden) {
                    e.preventDefault();
                    closeUserMenu();
                    if (userBtn) userBtn.focus();
                }
                return;
            }

            if (e.key !== 'Tab' || !document.body.classList.contains('sidebar-open') || !isMobile()) return;
            var items = focusables();
            if (!items.length) return;
            var first = items[0];
            var last = items[items.length - 1];
            if (e.shiftKey && document.activeElement === first) {
                e.preventDefault();
                last.focus();
            } else if (!e.shiftKey && document.activeElement === last) {
                e.preventDefault();
                first.focus();
            }
        });

        window.addEventListener('resize', syncSidebar);

        var topBtn = document.getElementById('adminBackToTop');
        if (topBtn && topBtn.getAttribute('data-bound') !== '1') {
            topBtn.setAttribute('data-bound', '1');
            var toggleTop = function () {
                topBtn.hidden = window.scrollY < 400;
            };
            window.addEventListener('scroll', toggleTop, { passive: true });
            toggleTop();
            topBtn.addEventListener('click', function () {
                window.scrollTo({ top: 0, behavior: reduceMotion ? 'auto' : 'smooth' });
            });
        }

        document.querySelectorAll('.list-table td, .record-table td, .preview-table td').forEach(function (cell) {
            var text = (cell.textContent || '').replace(/\s+/g, ' ').trim();
            if (!text) return;
            if (!cell.getAttribute('title') && (cell.scrollWidth > cell.clientWidth || text.length > 36)) {
                cell.setAttribute('title', text);
            }
        });

        syncSidebar();
    });
})();
