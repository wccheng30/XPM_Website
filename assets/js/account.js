(function () {
    var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    var toast = document.querySelector('.account-toast');
    if (toast) {
        var dismiss = function () {
            if (reduceMotion) {
                toast.remove();
                return;
            }
            toast.classList.add('is-hiding');
            window.setTimeout(function () {
                toast.remove();
            }, 300);
        };
        var close = toast.querySelector('.account-toast-close');
        if (close) {
            close.addEventListener('click', dismiss);
        }
        var delay = parseInt(toast.getAttribute('data-autohide') || '0', 10);
        if (delay > 0) {
            window.setTimeout(dismiss, delay);
        }
    }

    var nameInput = document.getElementById('display_name');
    var saveName = document.getElementById('save-name');
    var nameForm = document.getElementById('name-form');
    if (nameInput && saveName) {
        var syncName = function () {
            var original = nameInput.getAttribute('data-original') || '';
            var unchanged = nameInput.value.trim() === original.trim();
            var profileError = document.querySelector('#profile .alert-error');
            saveName.disabled = unchanged && !profileError;
        };
        nameInput.addEventListener('input', syncName);
        syncName();
        if (nameForm) {
            nameForm.addEventListener('submit', function (event) {
                if (saveName.disabled) {
                    event.preventDefault();
                    event.stopImmediatePropagation();
                }
            }, true);
        }
    }

    var newPassword = document.getElementById('new_password');
    var passwordHint = document.getElementById('new-password-hint');
    if (newPassword && passwordHint) {
        var syncHint = function () {
            var length = newPassword.value.length;
            var remaining = 8 - length;
            if (length === 0) {
                passwordHint.textContent = 'At least 8 characters.';
                passwordHint.classList.remove('is-met');
            } else if (remaining > 0) {
                passwordHint.textContent = remaining === 1
                    ? '1 more character needed.'
                    : remaining + ' more characters needed.';
                passwordHint.classList.remove('is-met');
            } else {
                passwordHint.textContent = '8 characters or more.';
                passwordHint.classList.add('is-met');
            }
        };
        newPassword.addEventListener('input', syncHint);
        syncHint();
    }

    var avatarPick = document.getElementById('avatar-pick');
    var avatarInput = document.getElementById('avatar');
    var avatarFace = document.getElementById('avatar-face');
    var avatarActions = document.getElementById('avatar-actions');
    var avatarCancel = document.getElementById('avatar-cancel');
    var avatarFileName = document.getElementById('avatar-file-name');
    if (avatarPick && avatarInput && avatarFace && avatarActions) {
        var originalFace = avatarFace.innerHTML;
        var previewUrl = '';

        var revokePreview = function () {
            if (previewUrl) {
                URL.revokeObjectURL(previewUrl);
                previewUrl = '';
            }
        };

        var restoreFace = function () {
            revokePreview();
            avatarFace.innerHTML = originalFace;
            if (avatarFileName) {
                avatarFileName.textContent = '';
            }
            avatarActions.hidden = true;
        };

        avatarPick.addEventListener('click', function () {
            avatarInput.click();
        });

        avatarInput.addEventListener('change', function () {
            var chosen = avatarInput.files && avatarInput.files[0];
            if (!chosen) {
                restoreFace();
                return;
            }
            revokePreview();
            if (avatarFileName) {
                avatarFileName.textContent = chosen.name;
            }
            avatarActions.hidden = false;
            if (chosen.type.indexOf('image/') !== 0) {
                avatarFace.innerHTML = originalFace;
                return;
            }
            previewUrl = URL.createObjectURL(chosen);
            var img = document.createElement('img');
            img.alt = '';
            img.src = previewUrl;
            avatarFace.textContent = '';
            avatarFace.appendChild(img);
        });

        if (avatarCancel) {
            avatarCancel.addEventListener('click', function () {
                avatarInput.value = '';
                restoreFace();
            });
        }
    }

    var navLinks = Array.prototype.slice.call(document.querySelectorAll('.account-nav a'));
    var sections = navLinks.map(function (link) {
        var id = (link.getAttribute('href') || '').replace('#', '');
        return id ? document.getElementById(id) : null;
    }).filter(Boolean);

    var setCurrent = function (id) {
        navLinks.forEach(function (link) {
            if (link.getAttribute('href') === '#' + id) {
                link.setAttribute('aria-current', 'true');
            } else {
                link.removeAttribute('aria-current');
            }
        });
    };

    navLinks.forEach(function (link) {
        link.addEventListener('click', function () {
            setCurrent((link.getAttribute('href') || '').replace('#', ''));
        });
    });

    if (sections.length && 'IntersectionObserver' in window) {
        var visible = {};
        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                visible[entry.target.id] = entry.isIntersecting ? entry.intersectionRatio : 0;
            });
            var bestId = '';
            var bestRatio = 0;
            sections.forEach(function (section) {
                var ratio = visible[section.id] || 0;
                if (ratio > bestRatio) {
                    bestRatio = ratio;
                    bestId = section.id;
                }
            });
            if (bestId) {
                setCurrent(bestId);
            }
        }, { rootMargin: '-20% 0px -55% 0px', threshold: [0.15, 0.4, 0.75] });
        sections.forEach(function (section) {
            observer.observe(section);
        });
    }

    var errorAlert = document.querySelector('.account-page .alert-error');
    if (errorAlert) {
        var errorTarget = errorAlert.closest('.account-card, .account-hero-block') || errorAlert;
        errorTarget.scrollIntoView({ behavior: reduceMotion ? 'auto' : 'smooth', block: 'start' });
    }
})();
