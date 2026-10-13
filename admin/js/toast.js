/* Shared admin toasts and export download helper. */
(function () {
    'use strict';

    var region = null;
    var timers = {};

    function ensureRegion() {
        if (region) return region;
        region = document.createElement('div');
        region.className = 'xpm-toasts';
        region.setAttribute('aria-live', 'polite');
        region.setAttribute('aria-relevant', 'additions');
        document.body.appendChild(region);
        return region;
    }

    function iconFor(type) {
        if (type === 'success') return 'fa-check-circle';
        if (type === 'error') return 'fa-exclamation-circle';
        return 'fa-info-circle';
    }

    function toastIcon(type) {
        var name = iconFor(type);
        var sprite = window.XPM_ICON_SPRITE;
        if (!sprite) {
            return '<i class="fas ' + name + ' xpm-toast-icon" aria-hidden="true"></i>';
        }
        return '<svg class="icon xpm-toast-icon" aria-hidden="true" focusable="false"><use href="' + sprite + '#' + name.replace(/^fa-/, '') + '"></use></svg>';
    }

    function dismiss(node) {
        if (!node || !node.parentNode) return;
        var key = node.getAttribute('data-toast-id');
        if (key && timers[key]) {
            clearTimeout(timers[key]);
            delete timers[key];
        }
        node.parentNode.removeChild(node);
    }

    function show(message, type, opts) {
        opts = opts || {};
        type = type || 'info';
        var host = ensureRegion();
        var id = opts.id || '';
        if (id) {
            var existing = host.querySelector('[data-toast-id="' + id + '"]');
            if (existing) dismiss(existing);
        }
        var toast = document.createElement('div');
        toast.className = 'xpm-toast is-' + type;
        toast.setAttribute('role', 'status');
        if (id) toast.setAttribute('data-toast-id', id);
        toast.innerHTML = toastIcon(type)
            + '<p class="xpm-toast-copy"></p>'
            + '<button type="button" class="xpm-toast-close" aria-label="Dismiss notification">&times;</button>';
        toast.querySelector('.xpm-toast-copy').textContent = message;
        toast.querySelector('.xpm-toast-close').addEventListener('click', function () {
            dismiss(toast);
        });
        host.appendChild(toast);
        var delay = opts.timeout === 0 ? 0 : (opts.timeout || 5000);
        if (delay) {
            var key = id || String(Date.now()) + Math.random();
            toast.setAttribute('data-toast-id', key);
            var start = function () {
                timers[key] = setTimeout(function () { dismiss(toast); }, delay);
            };
            var stop = function () {
                if (timers[key]) {
                    clearTimeout(timers[key]);
                    delete timers[key];
                }
            };
            toast.addEventListener('mouseenter', stop);
            toast.addEventListener('focusin', stop);
            toast.addEventListener('mouseleave', start);
            toast.addEventListener('focusout', start);
            start();
        }
        return toast;
    }

    function filenameFrom(header, fallback) {
        if (!header) return fallback;
        var star = /filename\*=UTF-8''([^;]+)/i.exec(header);
        if (star) {
            try { return decodeURIComponent(star[1].replace(/"/g, '')); } catch (e) { return star[1]; }
        }
        var plain = /filename="?([^"]+)"?/i.exec(header);
        return plain ? plain[1] : fallback;
    }

    function download(url, filename, trigger) {
        var btn = trigger && trigger.tagName === 'BUTTON' ? trigger : null;
        if (btn) {
            btn.disabled = true;
            btn.classList.add('is-loading');
        }
        show('Preparing export…', 'info', { id: 'export', timeout: 8000 });
        return fetch(url, { credentials: 'same-origin' }).then(function (res) {
            if (!res.ok) throw new Error('Export failed.');
            var type = (res.headers.get('Content-Type') || '').toLowerCase();
            if (type.indexOf('text/html') !== -1) throw new Error('Export failed.');
            var name = filenameFrom(res.headers.get('Content-Disposition'), filename || 'export');
            return res.blob().then(function (blob) {
                var objectUrl = URL.createObjectURL(blob);
                var link = document.createElement('a');
                link.href = objectUrl;
                link.download = name;
                document.body.appendChild(link);
                link.click();
                link.remove();
                setTimeout(function () { URL.revokeObjectURL(objectUrl); }, 1500);
                show('Export downloaded.', 'success', { id: 'export' });
            });
        }).catch(function (err) {
            show(err.message || 'Export failed.', 'error', { id: 'export' });
        }).finally(function () {
            if (btn) {
                btn.disabled = false;
                btn.classList.remove('is-loading');
            }
        });
    }

    window.xpmToast = show;
    window.xpmExportDownload = download;

    document.addEventListener('click', function (event) {
        if (event.defaultPrevented || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        var link = event.target.closest ? event.target.closest('a[href*="export-"]') : null;
        if (!link || link.target === '_blank') return;
        var href = link.getAttribute('href') || '';
        if (!/export-[a-z0-9-]+\.php/i.test(href)) return;
        event.preventDefault();
        download(href, '', link);
    });

    function promoteAlerts() {
        document.querySelectorAll('.alert-success, .alert-error').forEach(function (alert) {
            if (alert.querySelector('.btn, a')) return;
            var type = alert.classList.contains('alert-error') ? 'error' : 'success';
            var text = (alert.textContent || '').replace(/\s+/g, ' ').trim();
            if (!text) return;
            show(text, type);
            alert.hidden = true;
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', promoteAlerts);
    } else {
        promoteAlerts();
    }
})();
