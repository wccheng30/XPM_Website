<?php
/**
 * Shared public navigation.
 * Optional before include: $xpm_active = 'programmes' | 'careers' | ''
 * Keeps the #navbar / #nav-menu / #nav-toggle / .nav-link hooks used by main.js.
 */
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
            </div>

            <div class="nav-toggle" id="nav-toggle" role="button" tabindex="0" aria-label="Toggle menu" aria-controls="nav-menu">
                <span class="bar"></span>
                <span class="bar"></span>
                <span class="bar"></span>
            </div>
        </div>
    </nav>
