(function () {
    function bindMenus(root) {
        (root || document).querySelectorAll('button.nav-avatar').forEach(function (button) {
            if (button.dataset.bound) return;
            var menu = button.parentElement ? button.parentElement.querySelector('.nav-account-menu') : null;
            if (!menu) return;
            button.dataset.bound = '1';
            button.addEventListener('click', function (event) {
                event.stopPropagation();
                var open = menu.hasAttribute('hidden');
                document.querySelectorAll('.nav-account-menu').forEach(function (other) {
                    other.setAttribute('hidden', '');
                });
                document.querySelectorAll('button.nav-avatar').forEach(function (other) {
                    other.setAttribute('aria-expanded', 'false');
                });
                if (open) {
                    menu.removeAttribute('hidden');
                    button.setAttribute('aria-expanded', 'true');
                }
            });
        });
    }

    document.addEventListener('click', function () {
        document.querySelectorAll('.nav-account-menu').forEach(function (menu) {
            menu.setAttribute('hidden', '');
        });
        document.querySelectorAll('button.nav-avatar').forEach(function (button) {
            button.setAttribute('aria-expanded', 'false');
        });
    });

    bindMenus(document);

    var login = document.querySelector('[data-nav-login]');
    if (!login) return;

    fetch('api/nav-user.php', { credentials: 'same-origin' })
        .then(function (response) { return response.json(); })
        .then(function (data) {
            if (!data || !data.loggedIn) return;
            function esc(value) {
                return String(value || '').replace(/[&<>"']/g, function (ch) {
                    return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch];
                });
            }
            var wrap = document.createElement('div');
            wrap.className = 'nav-account';
            var face = data.avatar
                ? '<img src="' + esc(data.avatar) + '" alt="">'
                : '<span>' + esc(data.initial || '?') + '</span>';
            var workspace = data.workspace
                ? '<a href="' + esc(data.workspace) + '">Workspace</a>'
                : '';
            var home = data.workspace ? '<a href="index.html">Main page</a>' + workspace : '';
            wrap.innerHTML = '<button type="button" class="nav-avatar" aria-expanded="false" aria-haspopup="true" aria-label="Account menu">' +
                face + '</button><div class="nav-account-menu" hidden>' + home +
                '<a href="account.php">Account</a><a href="logout.php">Log out</a></div>';
            login.replaceWith(wrap);
            bindMenus(wrap);
        })
        .catch(function () {});
})();
