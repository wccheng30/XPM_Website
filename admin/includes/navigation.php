<?php
/**
 * Admin sidebar.
 * Same signature and $current_page keys as the previous horizontal tabs.
 */

function renderAdminNavigation($current_page = '') {
    $role = $_SESSION['admin_role'] ?? ($_SESSION['user_role'] ?? 'admin');
    $isTrainer = ($role === 'trainer');
    $brandKicker = $isTrainer ? 'Trainer workspace' : 'XpressPoint Admin';

    if ($isTrainer) {
        $nav_items = [
            'dashboard' => ['url' => 'dashboard.php', 'icon' => 'fas fa-home', 'label' => 'Dashboard'],
            'facilities' => ['url' => 'facilities.php', 'icon' => 'fas fa-calendar-alt', 'label' => 'Calendar'],
            'my_profile' => ['url' => 'my-profile.php', 'icon' => 'fas fa-id-badge', 'label' => 'My Profile'],
        ];
        $groups = [
            'Overview' => ['dashboard'],
            'Training' => ['facilities'],
            'Account' => ['my_profile'],
        ];
    } else {
        $nav_items = [
            'dashboard' => ['url' => 'dashboard.php', 'icon' => 'fas fa-home', 'label' => 'Dashboard'],
            'jobs' => ['url' => 'jobs.php', 'icon' => 'fas fa-briefcase', 'label' => 'Jobs'],
            'applications' => ['url' => 'applications.php', 'icon' => 'fas fa-file-alt', 'label' => 'Job Applications'],
            'blacklist' => ['url' => 'blacklist.php', 'icon' => 'fas fa-user-slash', 'label' => 'Blacklist'],
            'programmes' => ['url' => 'programmes.php', 'icon' => 'fas fa-book-open', 'label' => 'Training Programmes'],
            'registrations' => ['url' => 'registrations.php', 'icon' => 'fas fa-graduation-cap', 'label' => 'Programme Registrations'],
            'trainers' => ['url' => 'trainers.php', 'icon' => 'fas fa-user-tie', 'label' => 'Trainers'],
            'facilities' => ['url' => 'facilities.php', 'icon' => 'fas fa-building', 'label' => 'Facilities'],
            'contacts' => ['url' => 'contacts.php', 'icon' => 'fas fa-users', 'label' => 'Contacts'],
        ];
        $groups = [
            'Overview' => ['dashboard'],
            'Recruitment' => ['jobs', 'applications', 'blacklist'],
            'Training' => ['programmes', 'registrations', 'trainers', 'facilities'],
            'Clients' => ['contacts'],
        ];
        if ($role === 'admin') {
            $nav_items['users'] = [
                'url' => 'users.php',
                'icon' => 'fas fa-user-shield',
                'label' => 'Users',
            ];
            $groups['System'] = ['users'];
        }
    }
    ?>
    <aside class="admin-sidebar" id="adminSidebar">
        <a class="sidebar-brand" href="dashboard.php" aria-label="XpressPoint Admin home">
            <span class="admin-brand-mark" aria-hidden="true">XP</span>
            <span class="sidebar-brand-text">
                <span class="admin-brand-kicker"><?php echo htmlspecialchars($brandKicker, ENT_QUOTES, 'UTF-8'); ?></span>
                <strong>XpressPoint</strong>
            </span>
        </a>
        <nav class="sidebar-nav" aria-label="Admin sections">
            <?php foreach ($groups as $heading => $keys): ?>
                <div class="sidebar-group">
                    <p class="sidebar-heading"><?php echo htmlspecialchars($heading, ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php foreach ($keys as $key):
                        if (!isset($nav_items[$key])) {
                            continue;
                        }
                        $item = $nav_items[$key];
                        $current = ($current_page === $key);
                        ?>
                        <a class="sidebar-link<?php echo $current ? ' is-current' : ''; ?>"
                           href="<?php echo htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8'); ?>"
                           <?php echo $current ? 'aria-current="page"' : ''; ?>>
                            <i class="<?php echo htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8'); ?>" aria-hidden="true"></i>
                            <span class="sidebar-label"><?php echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </nav>
        <button type="button" class="sidebar-collapse" id="sidebarCollapse" aria-expanded="true" aria-controls="adminSidebar">
            <i class="fas fa-angles-left" aria-hidden="true"></i>
            <span class="sidebar-collapse-label">Collapse</span>
        </button>
    </aside>
    <button type="button" class="sidebar-overlay" id="sidebarOverlay" hidden aria-label="Close menu"></button>
    <div class="sidebar-tip" id="sidebarTip" role="tooltip" hidden></div>
    <button type="button" class="back-to-top" id="adminBackToTop" hidden aria-label="Back to top">
        <i class="fas fa-arrow-up" aria-hidden="true"></i>
    </button>
    <?php
}
