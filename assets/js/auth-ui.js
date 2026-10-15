(function () {
    document.querySelectorAll('.password-toggle').forEach(function (button) {
        var field = button.closest('.password-field');
        var input = field ? field.querySelector('input') : null;
        if (!input) {
            return;
        }

        button.addEventListener('click', function () {
            var showing = input.type === 'text';
            input.type = showing ? 'password' : 'text';
            button.setAttribute('aria-pressed', showing ? 'false' : 'true');
            button.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
            var icon = button.querySelector('i');
            if (icon) {
                icon.classList.toggle('fa-eye', showing);
                icon.classList.toggle('fa-eye-slash', !showing);
            }
        });
    });

    document.querySelectorAll('.auth-card form, .account-page form').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
                return;
            }

            var button = form.querySelector('[type="submit"]');
            if (!button) {
                return;
            }
            if (button.classList.contains('is-loading')) {
                event.preventDefault();
                return;
            }

            button.classList.add('is-loading');
            button.setAttribute('aria-busy', 'true');
            window.setTimeout(function () {
                button.disabled = true;
            }, 0);
        });
    });
})();
