/* Shared list behaviour: bulk bar, kebab menus, confirmations. */
(function () {
    'use strict';

    function ready(fn) {
        if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fn);
        else fn();
    }

    function checks() {
        return Array.prototype.slice.call(document.querySelectorAll('input[name="ids[]"]'));
    }

    function selected() {
        return checks().filter(function (box) { return box.checked; });
    }

    function updateBulk() {
        var bar = document.getElementById('listBulk');
        var summary = document.getElementById('listBulkSummary');
        var all = document.getElementById('listSelectAll');
        var rows = selected();
        if (bar) bar.hidden = rows.length === 0;
        if (summary) {
            var parts = [];
            if (document.getElementById('bulkStatusBtn')) parts.push('Change status');
            if (document.getElementById('bulkExportBtn')) parts.push('Export');
            if (document.getElementById('bulkDeleteBtn')) parts.push('Delete');
            summary.textContent = rows.length + ' selected' + (parts.length ? ': ' + parts.join(', ') : '');
        }
        if (all) {
            var total = checks().length;
            all.checked = total > 0 && rows.length === total;
            all.indeterminate = rows.length > 0 && rows.length < total;
        }
    }

    var menuRelease = null;
    var menuTrigger = null;

    function closeMenus(except) {
        document.querySelectorAll('.row-menu-panel').forEach(function (panel) {
            if (panel === except) return;
            panel.hidden = true;
            var btn = panel.parentNode.querySelector('.row-menu-btn');
            if (btn) btn.setAttribute('aria-expanded', 'false');
        });
        if (menuRelease && (!except || except.hidden)) {
            var release = menuRelease;
            menuRelease = null;
            release(false);
            if (menuTrigger && typeof menuTrigger.focus === 'function' && !except) {
                menuTrigger.focus();
            }
            menuTrigger = null;
        }
    }

    ready(function () {
        document.addEventListener('change', function (event) {
            if (event.target.id === 'listSelectAll') {
                checks().forEach(function (box) { box.checked = event.target.checked; });
                updateBulk();
            } else if (event.target.classList && event.target.classList.contains('list-check')) {
                updateBulk();
            }
        });

        document.addEventListener('click', function (event) {
            var menuBtn = event.target.closest ? event.target.closest('.row-menu-btn') : null;
            if (menuBtn) {
                var panel = menuBtn.parentNode.querySelector('.row-menu-panel');
                var open = panel.hidden;
                closeMenus(open ? panel : null);
                panel.hidden = !open;
                menuBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
                if (open && typeof window.xpmFocusTrap === 'function') {
                    menuTrigger = menuBtn;
                    menuRelease = window.xpmFocusTrap(panel, {
                        returnTo: menuBtn,
                        onClose: function () { closeMenus(null); }
                    });
                }
                return;
            }
            if (!event.target.closest || !event.target.closest('.row-menu')) closeMenus(null);
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') closeMenus(null);
        });

        var statusBtn = document.getElementById('bulkStatusBtn');
        var deleteBtn = document.getElementById('bulkDeleteBtn');
        var exportBtn = document.getElementById('bulkExportBtn');
        var form = document.getElementById('listBulkForm');

        function ids() {
            return selected().map(function (box) { return box.value; });
        }

        if (statusBtn && form) {
            statusBtn.addEventListener('click', function () {
                var status = document.getElementById('bulkStatus');
                if (!status || !status.value) {
                    if (window.xpmAlert) window.xpmAlert('Choose a status first.');
                    return;
                }
                var count = ids().length;
                var run = function () {
                    document.getElementById('bulkAction').value = 'bulk_update_status';
                    form.submit();
                };
                if (window.xpmConfirm) {
                    window.xpmConfirm({
                        title: 'Change status?',
                        message: 'Update ' + count + ' selected row' + (count === 1 ? '' : 's') + ' to ' + status.options[status.selectedIndex].text + '.',
                        confirmText: 'Change status',
                        icon: 'fas fa-edit'
                    }).then(function (ok) { if (ok) run(); });
                } else {
                    run();
                }
            });
        }

        if (deleteBtn && form) {
            deleteBtn.addEventListener('click', function () {
                var count = ids().length;
                var run = function () {
                    document.getElementById('bulkAction').value = 'bulk_delete';
                    form.submit();
                };
                if (window.xpmConfirm) {
                    window.xpmConfirm({
                        title: 'Delete selected?',
                        message: 'Delete ' + count + ' selected row' + (count === 1 ? '' : 's') + '? This cannot be undone.',
                        confirmText: 'Delete',
                        icon: 'fas fa-trash'
                    }).then(function (ok) { if (ok) run(); });
                } else {
                    run();
                }
            });
        }

        if (exportBtn) {
            exportBtn.addEventListener('click', function () {
                if (typeof window.exportApplications === 'function') window.exportApplications(ids());
                else if (typeof window.exportCurrentList === 'function') window.exportCurrentList(ids());
            });
        }

        updateBulk();
    });
})();
