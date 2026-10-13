<?php
/**
 * Admin header.
 * Hamburger (mobile), page title with optional back link, and one account menu.
 *
 * @param string     $title Page title shown in the header
 * @param string     $icon  Font Awesome classes for the title icon
 * @param array|null $back  Optional ['url' => ..., 'label' => ...] back link
 */
function renderAdminHeader($title, $icon = 'fas fa-tachometer-alt', $back = null) {
    $username = $_SESSION['user_name'] ?? ($_SESSION['admin_username'] ?? 'Admin');
    $initial = function_exists('mb_substr') ? mb_substr($username, 0, 1) : substr($username, 0, 1);
    ?>
    <link rel="stylesheet" href="<?php echo admin_asset('../assets/css/xpm-tokens.css'); ?>">
    <link rel="stylesheet" href="<?php echo admin_asset('../assets/css/xpm-motion.css'); ?>">
    <a class="skip-link" href="#admin-main">Skip to main content</a>
    <header class="admin-header">
        <div class="header-lead">
            <button type="button" class="sidebar-toggle" id="sidebarToggle" aria-expanded="false" aria-controls="adminSidebar" aria-label="Open menu">
                <span aria-hidden="true"></span>
                <span aria-hidden="true"></span>
                <span aria-hidden="true"></span>
            </button>
            <div class="header-heading">
                <?php if (!empty($back['url'])): ?>
                    <a class="header-back" href="<?php echo htmlspecialchars($back['url'], ENT_QUOTES, 'UTF-8'); ?>">
                        <i class="fas fa-arrow-left" aria-hidden="true"></i>
                        <span><?php echo htmlspecialchars($back['label'] ?? 'Back', ENT_QUOTES, 'UTF-8'); ?></span>
                    </a>
                    <span class="header-sep" aria-hidden="true">/</span>
                <?php endif; ?>
                <h1>
                    <i class="<?php echo htmlspecialchars($icon, ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></i>
                    <span><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></span>
                </h1>
            </div>
        </div>
        <div class="user-menu">
            <button type="button" class="user-menu-btn" id="userMenuBtn" aria-expanded="false" aria-haspopup="true" aria-controls="userMenu" aria-label="Account menu">
                <span class="user-avatar"><?php echo htmlspecialchars($initial, ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="user-menu-name"><?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?></span>
                <i class="fas fa-chevron-down" aria-hidden="true"></i>
            </button>
            <div class="user-menu-panel" id="userMenu" hidden>
                <a href="../index.html"><i class="fas fa-home" aria-hidden="true"></i> Main site</a>
                <a href="change-password.php"><i class="fas fa-key" aria-hidden="true"></i> Change password</a>
                <div class="theme-switch" role="radiogroup" aria-label="Theme">
                    <span class="theme-switch-label">Theme</span>
                    <div class="theme-switch-options">
                        <button type="button" role="radio" data-theme-choice="light" aria-checked="false">Light</button>
                        <button type="button" role="radio" data-theme-choice="dark" aria-checked="false">Dark</button>
                        <button type="button" role="radio" data-theme-choice="system" aria-checked="false">System</button>
                    </div>
                </div>
                <a class="user-menu-logout" href="logout-confirm.php"><i class="fas fa-sign-out-alt" aria-hidden="true"></i> Logout</a>
            </div>
        </div>
    </header>
    <script>window.XPM_ICON_SPRITE=<?php echo json_encode(admin_asset('assets/icons.svg', false)); ?>;</script>
    <script defer src="<?php echo admin_asset('../assets/js/xpm-dialog.js'); ?>"></script>
    <script defer src="<?php echo admin_asset('js/shell.js'); ?>"></script>
    <script defer src="<?php echo admin_asset('js/toast.js'); ?>"></script>
    <script defer src="<?php echo admin_asset('../assets/js/motion.js'); ?>"></script>
    <?php
}
