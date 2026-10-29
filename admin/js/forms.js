window.xpmSetIcon = function (root, className) {
    if (!root) {
        return;
    }
    var match = String(className || '').match(/\bfa-([a-z0-9-]+)/);
    if (!match) {
        return;
    }
    var use = root.querySelector('use');
    if (!use) {
        return;
    }
    var current = use.getAttribute('href') || use.getAttribute('xlink:href') || '';
    var hash = current.indexOf('#');
    var prefix = window.XPM_ICON_SPRITE || (hash >= 0 ? current.slice(0, hash) : '');
    if (!prefix) {
        return;
    }
    use.setAttribute('href', prefix + '#' + match[1]);
};

function isImageFileInput(input) {
    if (!input || input.type !== 'file') {
        return false;
    }
    return (input.getAttribute('accept') || '').toLowerCase().indexOf('image') !== -1;
}

function previewSlotFor(input) {
    var field = input.closest('.form-field, .form-group') || input.parentElement;
    var slot = field && field.querySelector('.file-preview-slot');
    if (slot) {
        return slot;
    }
    slot = document.createElement('div');
    slot.className = 'file-preview-slot';
    var anchor = input.closest('.file-control') || input;
    if (anchor.parentElement) {
        anchor.parentElement.insertBefore(slot, anchor.nextSibling);
    }
    return slot;
}

function resizedDataUrl(file, maxEdge) {
    return new Promise(function (resolve) {
        var url = URL.createObjectURL(file);
        var image = new Image();
        image.onload = function () {
            var longest = Math.max(image.width, image.height) || 1;
            var scale = Math.min(1, maxEdge / longest);
            var width = Math.max(1, Math.round(image.width * scale));
            var height = Math.max(1, Math.round(image.height * scale));
            var canvas = document.createElement('canvas');
            canvas.width = width;
            canvas.height = height;
            canvas.getContext('2d').drawImage(image, 0, 0, width, height);
            URL.revokeObjectURL(url);
            resolve(canvas.toDataURL('image/png'));
        };
        image.onerror = function () {
            URL.revokeObjectURL(url);
            resolve('');
        };
        image.src = url;
    });
}

function syncChosenFileName(input) {
    var name = input.parentElement && input.parentElement.querySelector('[data-file-name]');
    if (!name) {
        return;
    }
    var chosen = input.files ? input.files.length : 0;
    if (!chosen) {
        name.textContent = 'No file chosen';
    } else if (chosen === 1) {
        name.textContent = input.files[0].name;
    } else {
        name.textContent = chosen + ' files chosen';
    }
}

function previewRemoveButton(file) {
    var button = document.createElement('button');
    button.type = 'button';
    button.className = 'file-preview-remove';
    button.setAttribute('aria-label', 'Remove ' + (file.name || 'image'));
    var sprite = window.XPM_ICON_SPRITE || '';
    if (sprite) {
        var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        svg.setAttribute('class', 'icon');
        svg.setAttribute('aria-hidden', 'true');
        svg.setAttribute('focusable', 'false');
        var use = document.createElementNS('http://www.w3.org/2000/svg', 'use');
        use.setAttribute('href', sprite + '#times');
        svg.appendChild(use);
        button.appendChild(svg);
    } else {
        button.textContent = '\u00d7';
    }
    return button;
}

function removeChosenImage(input, file) {
    var kept = [];
    var list = input.files ? Array.prototype.slice.call(input.files) : [];
    list.forEach(function (item) {
        if (item !== file) {
            kept.push(item);
        }
    });
    if (kept.length) {
        try {
            var transfer = new DataTransfer();
            kept.forEach(function (item) {
                transfer.items.add(item);
            });
            input.files = transfer.files;
        } catch (error) {
            input.value = '';
        }
    } else {
        input.value = '';
    }
    syncChosenFileName(input);
    showImagePreviews(input);
}

function showImagePreviews(input) {
    var slot = previewSlotFor(input);
    var token = (input._previewToken || 0) + 1;
    input._previewToken = token;
    slot.replaceChildren();
    var files = [];
    if (input.files) {
        for (var i = 0; i < input.files.length; i++) {
            if ((input.files[i].type || '').indexOf('image/') === 0) {
                files.push(input.files[i]);
            }
        }
    }
    files.forEach(function (file) {
        resizedDataUrl(file, 320).then(function (url) {
            if (!url || input._previewToken !== token) {
                return;
            }
            var item = document.createElement('div');
            item.className = 'file-preview-item';
            var img = document.createElement('img');
            img.src = url;
            img.alt = '';
            var button = previewRemoveButton(file);
            button.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                removeChosenImage(input, file);
            });
            item.appendChild(img);
            item.appendChild(button);
            slot.appendChild(item);
        });
    });
}

document.addEventListener('change', function (event) {
    if (isImageFileInput(event.target)) {
        showImagePreviews(event.target);
    }
});

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
                syncChosenFileName(input);
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
    if (sections.length) {
        function spyOffset() {
            var raw = getComputedStyle(document.documentElement).getPropertyValue('--admin-header-h');
            var header = parseFloat(raw);
            return (isNaN(header) ? 72 : header) + 24;
        }

        function setCurrentSection() {
            var line = window.scrollY + spyOffset();
            var current = sections[0];
            sections.forEach(function (item) {
                var top = item.section.getBoundingClientRect().top + window.scrollY;
                if (top <= line) {
                    current = item;
                }
            });
            sections.forEach(function (item) {
                if (item === current) {
                    item.link.setAttribute('aria-current', 'true');
                } else {
                    item.link.removeAttribute('aria-current');
                }
            });
        }

        links.forEach(function (link) {
            link.addEventListener('click', function () {
                window.requestAnimationFrame(setCurrentSection);
            });
        });
        window.addEventListener('scroll', setCurrentSection, { passive: true });
        setCurrentSection();
    }

    document.querySelectorAll('.question-card').forEach(function (card) {
        var type = card.querySelector('select[name$="[input_type]"]');
        var options = card.querySelector('.question-options');
        if (!type || !options) {
            return;
        }
        function syncOptions() {
            options.hidden = type.value !== 'select';
        }
        type.addEventListener('change', syncOptions);
        syncOptions();
    });

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
