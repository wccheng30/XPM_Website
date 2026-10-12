<?php
/**
 * Admin Header Component
 * Markup only: brand, page title, and account links.
 *
 * @param string     $title Page title shown in the header
 * @param string     $icon  Font Awesome classes for the title icon
 * @param array|null $back  Optional ['url' => ..., 'label' => ...] back link
 */
function renderAdminHeader($title, $icon = 'fas fa-tachometer-alt', $back = null) {
    $username = $_SESSION['user_name'] ?? ($_SESSION['admin_username'] ?? 'Admin');
    $brandKicker = (($_SESSION['admin_role'] ?? '') === 'trainer') ? 'Trainer workspace' : 'XpressPoint Admin';
    $initial = function_exists('mb_substr') ? mb_substr($username, 0, 1) : substr($username, 0, 1);
    ?>
    <link rel="stylesheet" href="css/dense-list.css?v=20261008-3">
    <header class="admin-header">
        <a href="dashboard.php" class="admin-brand" aria-label="Admin dashboard">
            <span class="admin-brand-mark">XP</span>
            <span class="admin-brand-text">
                <span class="admin-brand-kicker"><?php echo htmlspecialchars($brandKicker); ?></span>
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
            <span class="user-chip">
                <span class="user-avatar"><?php echo htmlspecialchars($initial); ?></span>
                <?php echo htmlspecialchars($username); ?>
            </span>
            <a href="../index.html" class="logout-btn" title="Public site">
                <i class="fas fa-home"></i>
                <span>Main site</span>
            </a>
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
    <script src="../assets/js/xpm-dialog.js?v=20261006-5"></script>
    <?php
}
