<?php
/**
 * Shared public navigation.
 * Optional before include: $xpm_active = 'programmes' | 'careers' | ''
 * Keeps the #navbar / #nav-menu / #nav-toggle / .nav-link hooks used by main.js.
 */
require_once __DIR__ . '/../includes/auth.php';
auth_start();
$xpm_nav_user = auth_user();
$xpm_active = isset($xpm_active) ? $xpm_active : '';
$xpm_nav_links = [
    'home'         => ['index.html#hero', 'Home'],
    'about'        => ['index.html#about', 'About'],
    'founder'      => ['index.html#initiator', 'Founder'],
    'services'     => ['index.html#services', 'Services'],
    'facilities'   => ['index.html#facilities', 'Facilities'],
    'testimonials' => ['index.html#testimonials', 'Testimonials'],
    'programmes'   => ['programmes.php', 'Programmes'],
    'careers'      => ['jobs.php', 'Careers'],
    'portfolio'    => ['index.html#portfolio', 'Portfolio'],
    'contact'      => ['index.html#contact', 'Contact'],
];
?>
    <a class="skip-link" href="#main">Skip to content</a>
    <nav class="navbar" id="navbar" aria-label="Main">
        <div class="nav-container">
            <div class="nav-logo">
                <a href="index.html" aria-label="Xpress Point Management Services home">
                    <img src="assets/images/XPMLogo.png" alt="Xpress Point Management Services" class="logo-img">
                </a>
            </div>

            <div class="nav-menu" id="nav-menu">
<?php foreach ($xpm_nav_links as $xpm_key => $xpm_link): ?>
                <a href="<?php echo $xpm_link[0]; ?>" class="nav-link<?php echo $xpm_active === $xpm_key ? ' active' : ''; ?>"<?php echo $xpm_active === $xpm_key ? ' aria-current="page"' : ''; ?>><?php echo $xpm_link[1]; ?></a>
<?php endforeach; ?>
<?php if ($xpm_nav_user): ?>
                <div class="nav-account">
                    <button type="button" class="nav-avatar" aria-expanded="false" aria-haspopup="true" aria-label="Account menu for <?php echo htmlspecialchars($xpm_nav_user['name']); ?>">
<?php if (!empty($xpm_nav_user['avatar'])): ?>
                        <img src="<?php echo htmlspecialchars($xpm_nav_user['avatar']); ?>" alt="">
<?php else: ?>
                        <span><?php echo htmlspecialchars(auth_initial($xpm_nav_user['name'])); ?></span>
<?php endif; ?>
                    </button>
                    <div class="nav-account-menu" hidden>
<?php if (in_array($xpm_nav_user['role'], ['admin', 'manager', 'trainer'], true)): ?>
                        <a href="index.html">Main page</a>
                        <a href="<?php echo htmlspecialchars(auth_home_path($xpm_nav_user['role'], false)); ?>">Workspace</a>
<?php endif; ?>
                        <a href="account.php">Account</a>
                        <a href="logout.php">Log out</a>
                    </div>
                </div>
<?php else: ?>
                <a href="login.php" class="nav-link<?php echo $xpm_active === 'login' ? ' active' : ''; ?>" data-nav-login>Log in</a>
<?php endif; ?>
            </div>

            <div class="nav-toggle" id="nav-toggle" role="button" tabindex="0" aria-label="Toggle menu" aria-controls="nav-menu">
                <span class="bar"></span>
                <span class="bar"></span>
                <span class="bar"></span>
            </div>
        </div>
    </nav>
    <script src="assets/js/nav-account.js?v=20261008-3"></script>
