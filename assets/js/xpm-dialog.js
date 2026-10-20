(function () {
    if (window.xpmConfirm) return;
    var dialog = null;
    var titleEl;
    var messageEl;
    var fieldEl;
    var fieldLabelEl;
    var confirmBtn;
    var cancelBtn;
    var iconEl;
    var lastFocus = null;
    var pending = null;

    function paintDialogIcon(holder, className) {
        if (!holder) return;
        var sprite = window.XPM_ICON_SPRITE;
        if (!sprite) {
            holder.innerHTML = '<i class="' + className + '" aria-hidden="true"></i>';
            return;
        }
        var match = /fa-([a-z0-9-]+)/.exec(className || '');
        var name = match ? match[1] : 'circle-question';
        holder.innerHTML = '<svg class="icon" aria-hidden="true" focusable="false"><use href="' + sprite + '#' + name + '"></use></svg>';
    }

    function build() {
        if (dialog) return;

        var style = document.createElement('style');
        style.id = 'xpm-dialog-styles';
        style.textContent = [
            'body.xpm-dialog-open{overflow:hidden}',
            '.xpm-dialog{position:fixed;inset:0;z-index:10060;display:grid;place-items:center;padding:1.25rem}',
            '.xpm-dialog[hidden]{display:none}',
            '.xpm-dialog-backdrop{position:absolute;inset:0;background:rgba(30,35,40,.55)}',
            '.xpm-dialog-card{position:relative;width:min(100%,420px);padding:1.75rem 1.5rem 1.4rem;text-align:center;background:#fff;border:1px solid #ececec;border-radius:16px;box-shadow:0 30px 70px -20px rgba(0,0,0,.22);font-family:Inter,system-ui,sans-serif}',
            '.xpm-dialog-icon{display:grid;place-items:center;width:3rem;height:3rem;margin:0 auto 1rem;color:#F03000;background:#FDECE6;border-radius:999px;font-size:1.1rem}',
            '.xpm-dialog-card h2{margin:0 0 .45rem;font-family:Poppins,Inter,sans-serif;font-size:1.35rem;font-weight:600;line-height:1.25;color:#1E2328}',
            '.xpm-dialog-card p{margin:0 0 1.35rem;font-size:.95rem;line-height:1.55;color:#4E575E;white-space:pre-line}',
            '.xpm-dialog-card p[hidden],.xpm-dialog-field[hidden],.xpm-dialog-cancel[hidden]{display:none}',
            '.xpm-dialog-field-label{display:block;margin:0 0 .35rem;font-size:.85rem;font-weight:600;color:#1E2328;text-align:left}',
            '.xpm-dialog-field-label[hidden]{display:none}',
            '.xpm-dialog-field{display:block;width:100%;margin:0 0 1.2rem;min-height:44px;padding:.65rem .8rem;font:inherit;font-size:.95rem;color:#1E2328;background:#fff;border:1px solid #d9d9db;border-radius:10px}',
            '.xpm-dialog-field:focus{outline:none;border-color:#D42400;box-shadow:0 0 0 4px rgba(212,36,0,.16)}',
            '.xpm-dialog-actions{display:flex;flex-wrap:wrap;gap:.6rem;justify-content:center}',
            '.xpm-dialog-confirm,.xpm-dialog-cancel{min-height:44px;padding:.65rem 1.1rem;font:inherit;font-size:.95rem;font-weight:600;border-radius:10px;cursor:pointer}',
            '.xpm-dialog-confirm{color:#fff;background:#D42400;border:1px solid #D42400}',
            '.xpm-dialog-confirm:hover{background:#9C1301;border-color:#9C1301}',
            '.xpm-dialog-cancel{color:#1E2328;background:transparent;border:1px solid #d9d9db}',
            '.xpm-dialog-cancel:hover{border-color:#1E2328}',
            '.xpm-dialog-confirm:focus-visible,.xpm-dialog-cancel:focus-visible,.xpm-dialog-field:focus-visible{outline:2px solid #5E3F8F;outline-offset:2px;box-shadow:none}'
        ].join('');
        document.head.appendChild(style);

        dialog = document.createElement('div');
        dialog.className = 'xpm-dialog';
        dialog.hidden = true;
        dialog.innerHTML = ''
            + '<div class="xpm-dialog-backdrop" data-xpm-dialog="cancel"></div>'
            + '<div class="xpm-dialog-card" role="dialog" aria-modal="true" aria-labelledby="xpm-dialog-title">'
            + '<div class="xpm-dialog-icon" aria-hidden="true"></div>'
            + '<h2 id="xpm-dialog-title"></h2>'
            + '<p id="xpm-dialog-message"></p>'
            + '<label class="xpm-dialog-field-label" id="xpm-dialog-field-label" for="xpm-dialog-field" hidden>Your response</label>'
            + '<input class="xpm-dialog-field" id="xpm-dialog-field" type="text" hidden aria-label="Your response">'
            + '<div class="xpm-dialog-actions">'
            + '<button type="button" class="xpm-dialog-confirm" data-xpm-dialog="confirm"></button>'
            + '<button type="button" class="xpm-dialog-cancel" data-xpm-dialog="cancel"></button>'
            + '</div></div>';
        document.body.appendChild(dialog);

        titleEl = dialog.querySelector('#xpm-dialog-title');
        messageEl = dialog.querySelector('#xpm-dialog-message');
        fieldEl = dialog.querySelector('#xpm-dialog-field');
        fieldLabelEl = dialog.querySelector('#xpm-dialog-field-label');
        confirmBtn = dialog.querySelector('.xpm-dialog-confirm');
        cancelBtn = dialog.querySelector('.xpm-dialog-cancel');
        iconEl = dialog.querySelector('.xpm-dialog-icon');

        dialog.addEventListener('click', function (event) {
            var action = event.target.getAttribute && event.target.getAttribute('data-xpm-dialog');
            if (action === 'confirm') finish(true);
            else if (action === 'cancel') finish(false);
        });
        fieldEl.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                finish(true);
            }
        });
        dialog.addEventListener('keydown', function (event) {
            if (event.key !== 'Tab' || dialog.hidden) return;
            var nodes = dialog.querySelectorAll('button:not([hidden]), input:not([hidden])');
            var list = Array.prototype.filter.call(nodes, function (node) {
                return !node.hidden && node.getAttribute('aria-hidden') !== 'true';
            });
            if (!list.length) return;
            var first = list[0];
            var last = list[list.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        });
    }

    function finish(confirmed) {
        if (!pending) return;
        var current = pending;
        pending = null;
        dialog.hidden = true;
        document.body.classList.remove('xpm-dialog-open');
        var value = false;
        if (current.mode === 'alert') value = undefined;
        else if (current.mode === 'prompt') value = confirmed ? fieldEl.value : null;
        else value = !!confirmed;
        current.resolve(value);
        if (lastFocus && typeof lastFocus.focus === 'function') {
            lastFocus.focus();
        }
    }

    function openDialog(options) {
        build();
        if (pending) finish(false);

        return new Promise(function (resolve) {
            pending = { mode: options.mode, resolve: resolve };
            lastFocus = document.activeElement;
            titleEl.textContent = options.title || '';
            messageEl.textContent = options.message || '';
            messageEl.hidden = !options.message;
            paintDialogIcon(iconEl, options.icon || 'fas fa-circle-question');
            confirmBtn.textContent = options.confirmText || 'Continue';
            cancelBtn.textContent = options.cancelText || 'Cancel';
            cancelBtn.hidden = options.mode === 'alert';
            fieldEl.hidden = options.mode !== 'prompt';
            if (fieldLabelEl) fieldLabelEl.hidden = options.mode !== 'prompt';
            fieldEl.value = '';
            fieldEl.placeholder = options.placeholder || '';
            dialog.hidden = false;
            document.body.classList.add('xpm-dialog-open');
            if (options.mode === 'prompt') fieldEl.focus();
            else confirmBtn.focus();
        });
    }

    function asOptions(messageOrOptions, extra) {
        if (messageOrOptions && typeof messageOrOptions === 'object') return messageOrOptions;
        return Object.assign({ message: messageOrOptions == null ? '' : String(messageOrOptions) }, extra || {});
    }

    window.xpmConfirm = function (messageOrOptions, extra) {
        var options = asOptions(messageOrOptions, extra);
        return openDialog({
            mode: 'confirm',
            title: options.title || 'Are you sure?',
            message: options.message || '',
            confirmText: options.confirmText || 'Continue',
            cancelText: options.cancelText || 'Cancel',
            icon: options.icon || 'fas fa-circle-question'
        });
    };

    window.xpmAlert = function (messageOrOptions, extra) {
        var options = asOptions(messageOrOptions, extra);
        var title = options.title;
        var message = options.message || '';
        if (!title) {
            title = message || 'Please check this';
            message = '';
        }
        return openDialog({
            mode: 'alert',
            title: title,
            message: message,
            confirmText: options.confirmText || 'OK',
            icon: options.icon || 'fas fa-circle-exclamation'
        });
    };

    window.xpmPrompt = function (messageOrOptions, extra) {
        var options = asOptions(messageOrOptions, extra);
        return openDialog({
            mode: 'prompt',
            title: options.title || 'Enter a response',
            message: options.message || '',
            placeholder: options.placeholder || '',
            confirmText: options.confirmText || 'Continue',
            cancelText: options.cancelText || 'Cancel',
            icon: options.icon || 'fas fa-pen'
        });
    };

    function confirmOptionsFrom(el) {
        return {
            title: el.getAttribute('data-confirm-title') || 'Are you sure?',
            message: el.getAttribute('data-confirm') || '',
            confirmText: el.getAttribute('data-confirm-ok') || 'Continue',
            cancelText: el.getAttribute('data-confirm-cancel') || 'Cancel',
            icon: el.getAttribute('data-confirm-icon') || 'fas fa-circle-question'
        };
    }

    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!form || !form.getAttribute) return;
        var button = event.submitter;
        if (!button || !button.hasAttribute('data-confirm')) return;
        if (form.getAttribute('data-xpm-confirmed') === '1') {
            form.removeAttribute('data-xpm-confirmed');
            return;
        }
        event.preventDefault();
        event.stopPropagation();
        window.xpmConfirm(confirmOptionsFrom(button)).then(function (ok) {
            if (!ok) return;
            form.setAttribute('data-xpm-confirmed', '1');
            if (typeof form.requestSubmit === 'function') form.requestSubmit(button);
            else form.submit();
        });
    }, true);

    document.addEventListener('click', function (event) {
        var link = event.target && event.target.closest ? event.target.closest('a[data-confirm]') : null;
        if (!link) return;
        if (link.getAttribute('data-xpm-confirmed') === '1') {
            link.removeAttribute('data-xpm-confirmed');
            return;
        }
        event.preventDefault();
        event.stopPropagation();
        window.xpmConfirm(confirmOptionsFrom(link)).then(function (ok) {
            if (!ok) return;
            link.setAttribute('data-xpm-confirmed', '1');
            link.click();
        });
    }, true);

    document.addEventListener('keydown', function (event) {
        if (!dialog || dialog.hidden) return;
        if (event.key === 'Escape') {
            event.preventDefault();
            event.stopPropagation();
            finish(false);
        }
    }, true);
})();
