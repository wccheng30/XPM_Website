(function () {
    function described(el) {
        return (el.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean);
    }

    function setError(el, message) {
        var id = el.id || el.name;
        var errorId = id + '-error';
        var node = document.getElementById(errorId);
        if (!node) {
            node = document.createElement('p');
            node.className = 'form-error';
            node.id = errorId;
            el.closest('.form-field').appendChild(node);
        }
        node.textContent = message;
        el.classList.add('is-invalid');
        el.setAttribute('aria-invalid', 'true');
        var ids = described(el);
        if (ids.indexOf(errorId) === -1) {
            ids.push(errorId);
            el.setAttribute('aria-describedby', ids.join(' '));
        }
    }

    function clearError(el) {
        var errorId = (el.id || el.name) + '-error';
        var node = document.getElementById(errorId);
        if (node && node.dataset.client === '1') {
            node.remove();
        }
        el.classList.remove('is-invalid');
        el.removeAttribute('aria-invalid');
    }

    function labelText(el) {
        var label = el.id ? document.querySelector('label[for="' + el.id + '"]') : null;
        var text = label ? label.textContent.replace('*', '').trim() : 'This field';
        return text;
    }

    function validate(form) {
        var invalid = [];
        form.querySelectorAll('input, select, textarea').forEach(function (el) {
            if (el.type === 'hidden' || el.disabled) {
                return;
            }
            var message = '';
            if (el.required && !String(el.value || '').trim()) {
                message = labelText(el) + ' is required.';
            }
            if (message) {
                var existing = document.getElementById((el.id || el.name) + '-error');
                if (existing) {
                    existing.dataset.client = '1';
                }
                setError(el, message);
                var fresh = document.getElementById((el.id || el.name) + '-error');
                if (fresh) {
                    fresh.dataset.client = '1';
                }
                invalid.push(el);
            } else if (el.getAttribute('aria-invalid') === 'true' && document.getElementById((el.id || el.name) + '-error') && document.getElementById((el.id || el.name) + '-error').dataset.client === '1') {
                clearError(el);
            }
        });
        var old = form.parentElement && form.parentElement.querySelector('.form-summary[data-client]');
        if (!invalid.length) {
            if (old) {
                old.remove();
            }
            return true;
        }
        var summary = old || document.createElement('div');
        summary.className = 'form-summary';
        summary.dataset.client = '1';
        summary.setAttribute('role', 'alert');
        summary.tabIndex = -1;
        var items = invalid.map(function (el) {
            return '<li><a href="#' + el.id + '">' + labelText(el) + ' is required.</a></li>';
        }).join('');
        summary.innerHTML = '<p><strong>Check the highlighted fields.</strong></p><ul>' + items + '</ul>';
        form.parentElement.insertBefore(summary, form);
        summary.focus();
        invalid[0].focus();
        return false;
    }

    document.querySelectorAll('form[data-form-guard]').forEach(function (form) {
        form.addEventListener('input', function () {
            window.xpmFormDirty = true;
        });
        form.addEventListener('change', function (event) {
            window.xpmFormDirty = true;
            var input = event.target;
            if (input && input.type === 'file') {
                var name = input.parentElement && input.parentElement.querySelector('[data-file-name]');
                if (name) {
                    name.textContent = (input.files && input.files[0]) ? input.files[0].name : 'No file chosen';
                }
            }
        });
        form.addEventListener('submit', function (event) {
            if (!validate(form)) {
                event.preventDefault();
                event.stopImmediatePropagation();
                return;
            }
            window.xpmFormDirty = false;
        });
    });

    var links = document.querySelectorAll('.form-spy-links a');
    var sections = [];
    links.forEach(function (link) {
        var id = link.getAttribute('href').slice(1);
        var section = document.getElementById(id);
        if (section) {
            sections.push({ link: link, section: section });
        }
    });
    if (sections.length && 'IntersectionObserver' in window) {
        var active = sections[0].link;
        active.setAttribute('aria-current', 'true');
        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) {
                    return;
                }
                sections.forEach(function (item) {
                    item.link.removeAttribute('aria-current');
                    if (item.section === entry.target) {
                        item.link.setAttribute('aria-current', 'true');
                    }
                });
            });
        }, { rootMargin: '-20% 0px -60% 0px', threshold: 0.1 });
        sections.forEach(function (item) {
            observer.observe(item.section);
        });
    }

    document.querySelectorAll('.form-spy-toggle').forEach(function (button) {
        button.addEventListener('click', function () {
            var spy = button.closest('.form-spy');
            var open = button.getAttribute('aria-expanded') === 'true';
            button.setAttribute('aria-expanded', open ? 'false' : 'true');
            if (spy) {
                spy.classList.toggle('is-open', !open);
            }
        });
    });
})();
