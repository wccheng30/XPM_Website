<?php
/**
 * Admin Header Component
 * Markup only: brand, page title, theme toggle and account links.
 *
 * @param string     $title Page title shown in the header
 * @param string     $icon  Font Awesome classes for the title icon
 * @param array|null $back  Optional ['url' => ..., 'label' => ...] back link
 */
function renderAdminHeader($title, $icon = 'fas fa-tachometer-alt', $back = null) {
    $username = $_SESSION['admin_username'] ?? 'Admin';
    $initial = function_exists('mb_substr') ? mb_substr($username, 0, 1) : substr($username, 0, 1);
    ?>
    <header class="admin-header">
        <a href="dashboard.php" class="admin-brand" aria-label="Admin dashboard">
            <span class="admin-brand-mark">XP</span>
            <span class="admin-brand-text">
                <span class="admin-brand-kicker">XpressPoint Admin</span>
                <h1><i class="<?php echo htmlspecialchars($icon); ?>"></i><?php echo htmlspecialchars($title); ?></h1>
            </span>
        </a>
        <div class="user-info">
            <?php if (!empty($back['url'])): ?>
                <a href="<?php echo htmlspecialchars($back['url']); ?>" class="header-icon-btn">
                    <i class="fas fa-arrow-left"></i>
                    <span><?php echo htmlspecialchars($back['label'] ?? 'Back'); ?></span>
                </a>
            <?php endif; ?>
            <button type="button" class="theme-toggle" onclick="toggleTheme()" aria-label="Toggle dark mode">
                <i class="fas fa-moon" id="theme-icon"></i>
                <span id="theme-text">Dark Mode</span>
            </button>
            <span class="user-chip">
                <span class="user-avatar"><?php echo htmlspecialchars($initial); ?></span>
                <?php echo htmlspecialchars($username); ?>
            </span>
            <a href="change-password.php" class="logout-btn" title="Change password">
                <i class="fas fa-key"></i>
                <span>Password</span>
            </a>
            <a href="logout-confirm.php" class="logout-btn is-primary">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </header>
    <script>
        (function () {
            function applyTheme(dark) {
                var icon = document.getElementById('theme-icon');
                var text = document.getElementById('theme-text');
                if (dark) {
                    document.body.setAttribute('data-theme', 'dark');
                } else {
                    document.body.removeAttribute('data-theme');
                }
                if (icon) icon.className = dark ? 'fas fa-sun' : 'fas fa-moon';
                if (text) text.textContent = dark ? 'Light Mode' : 'Dark Mode';
            }
            try { applyTheme(localStorage.getItem('theme') === 'dark'); } catch (e) {}
            if (typeof window.toggleTheme !== 'function') {
                window.toggleTheme = function () {
                    var dark = document.body.getAttribute('data-theme') !== 'dark';
                    applyTheme(dark);
                    try { localStorage.setItem('theme', dark ? 'dark' : 'light'); } catch (e) {}
                };
            }
        })();
    </script>
    <?php
}
