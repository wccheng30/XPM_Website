(function () {
    var pendingHref = '';
    var pendingReload = false;
    var dialog = null;
    var lastFocus = null;

    function buildDialog() {
        dialog = document.createElement('div');
        dialog.className = 'leave-dialog';
        dialog.hidden = true;
        dialog.innerHTML =
            '<div class="leave-dialog-backdrop" data-leave="stay"></div>' +
            '<div class="leave-dialog-card" role="dialog" aria-modal="true" aria-labelledby="leave-dialog-title">' +
                '<div class="leave-dialog-icon" aria-hidden="true"></div>' +
                '<h2 id="leave-dialog-title">Leave this page?</h2>' +
                '<p id="leave-dialog-text">You have unsaved changes. If you leave now, this form will not be submitted.</p>' +
                '<div class="leave-dialog-actions">' +
                    '<button type="button" class="leave-dialog-stay" data-leave="stay">Stay on this page</button>' +
                    '<button type="button" class="leave-dialog-go" data-leave="go">Leave anyway</button>' +
                '</div>' +
            '</div>';
        document.body.appendChild(dialog);
        var iconHolder = dialog.querySelector('.leave-dialog-icon');
        if (iconHolder) {
            var sprite = window.XPM_ICON_SPRITE;
            iconHolder.innerHTML = sprite
                ? '<svg class="icon" aria-hidden="true" focusable="false"><use href="' + sprite + '#right-from-bracket"></use></svg>'
                : '<i class="fas fa-right-from-bracket" aria-hidden="true"></i>';
        }

        dialog.addEventListener('click', function (event) {
            var action = event.target.closest('[data-leave]');
            if (!action) {
                return;
            }
            if (action.getAttribute('data-leave') === 'go') {
                confirmLeave();
            } else {
                closeDialog();
            }
        });
    }

    function openDialog(href, reload) {
        if (!dialog) {
            buildDialog();
        }
        pendingHref = href || '';
        pendingReload = !!reload;

        var title = dialog.querySelector('#leave-dialog-title');
        var text = dialog.querySelector('#leave-dialog-text');
        var go = dialog.querySelector('.leave-dialog-go');
        var icon = dialog.querySelector('.leave-dialog-icon i');
        if (pendingReload) {
            title.textContent = 'Reload this page?';
            text.textContent = 'You have unsaved changes. If you reload now, this form will not be submitted.';
            go.textContent = 'Reload anyway';
            icon.className = 'fas fa-rotate-right';
        } else {
            title.textContent = 'Leave this page?';
            text.textContent = 'You have unsaved changes. If you leave now, this form will not be submitted.';
            go.textContent = 'Leave anyway';
            icon.className = 'fas fa-right-from-bracket';
        }

        if (dialog.hidden) {
            lastFocus = document.activeElement;
            dialog.hidden = false;
            document.body.classList.add('leave-dialog-open');
        }
        dialog.querySelector('.leave-dialog-stay').focus();
    }

    function closeDialog() {
        if (!dialog) {
            return;
        }
        dialog.hidden = true;
        pendingHref = '';
        pendingReload = false;
        document.body.classList.remove('leave-dialog-open');
        if (lastFocus && typeof lastFocus.focus === 'function') {
            lastFocus.focus();
        }
    }

    function confirmLeave() {
        var href = pendingHref;
        var reload = pendingReload;
        window.xpmFormDirty = false;
        closeDialog();
        if (reload) {
            window.location.reload();
            return;
        }
        window.location.href = href;
    }

    function isReloadShortcut(event) {
        if (event.key === 'F5') {
            return true;
        }
        return (event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'r';
    }

    document.addEventListener('keydown', function (event) {
        if (dialog && !dialog.hidden && event.key === 'Escape') {
            event.preventDefault();
            closeDialog();
            return;
        }
        if (!window.xpmFormDirty || !isReloadShortcut(event)) {
            return;
        }
        event.preventDefault();
        event.stopPropagation();
        openDialog('', true);
    }, true);

    if (window.navigation && typeof navigation.addEventListener === 'function') {
        navigation.addEventListener('navigate', function (event) {
            if (!window.xpmFormDirty || event.navigationType !== 'reload' || !event.cancelable) {
                return;
            }
            event.preventDefault();
            openDialog('', true);
        });
    }

    document.addEventListener('click', function (event) {
        if (!window.xpmFormDirty || event.defaultPrevented) {
            return;
        }
        if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return;
        }

        var link = event.target.closest('a[href]');
        if (!link || link.closest('.leave-dialog') || link.hasAttribute('download')) {
            return;
        }
        if (link.target && link.target !== '_self') {
            return;
        }

        var href = link.getAttribute('href');
        if (!href || href.charAt(0) === '#' || href.indexOf('javascript:') === 0) {
            return;
        }

        var url;
        try {
            url = new URL(link.href, window.location.href);
        } catch (error) {
            return;
        }

        if (url.origin === window.location.origin &&
            url.pathname === window.location.pathname &&
            url.search === window.location.search) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        openDialog(link.href, false);
    }, true);
})();
