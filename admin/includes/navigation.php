<?php
/**
 * Admin Navigation Component
 * This file contains the navigation structure for all admin pages
 */

function renderAdminNavigation($current_page = '') {
    $role = $_SESSION['admin_role'] ?? ($_SESSION['user_role'] ?? 'admin');
    if ($role === 'trainer') {
        $nav_items = [
            'dashboard' => [
                'url' => 'dashboard.php',
                'icon' => 'fas fa-home',
                'label' => 'Dashboard'
            ],
            'facilities' => [
                'url' => 'facilities.php',
                'icon' => 'fas fa-calendar-alt',
                'label' => 'Calendar'
            ],
            'my_profile' => [
                'url' => 'my-profile.php',
                'icon' => 'fas fa-id-badge',
                'label' => 'My Profile'
            ],
        ];
    } else {
    $nav_items = [
        'dashboard' => [
            'url' => 'dashboard.php',
            'icon' => 'fas fa-home',
            'label' => 'Dashboard'
        ],
        'contacts' => [
            'url' => 'contacts.php',
            'icon' => 'fas fa-users',
            'label' => 'Contacts'
        ],
        'jobs' => [
            'url' => 'jobs.php',
            'icon' => 'fas fa-briefcase',
            'label' => 'Jobs'
        ],
        'applications' => [
            'url' => 'applications.php',
            'icon' => 'fas fa-file-alt',
            'label' => 'Job Applications'
        ],
        'registrations' => [
            'url' => 'registrations.php',
            'icon' => 'fas fa-graduation-cap',
            'label' => 'Programme Registrations'
        ],
        'programmes' => [
            'url' => 'programmes.php',
            'icon' => 'fas fa-chalkboard-teacher',
            'label' => 'Training Programmes'
        ],
        'facilities' => [
            'url' => 'facilities.php',
            'icon' => 'fas fa-building',
            'label' => 'Facilities'
        ],
        'trainers' => [
            'url' => 'trainers.php',
            'icon' => 'fas fa-chalkboard-teacher',
            'label' => 'Trainers'
        ],
        'blacklist' => [
            'url' => 'blacklist.php',
            'icon' => 'fas fa-user-slash',
            'label' => 'Blacklist'
        ]
    ];
        if ($role === 'admin') {
            $nav_items = array_merge(
                array_slice($nav_items, 0, 1, true),
                ['users' => [
                    'url' => 'users.php',
                    'icon' => 'fas fa-user-shield',
                    'label' => 'Users'
                ]],
                array_slice($nav_items, 1, null, true)
            );
        }
    }
    
    echo '<div class="admin-nav-sentinel" aria-hidden="true"></div>';
    echo '<nav class="admin-nav" aria-label="Admin sections">';
    echo '<div class="nav-mobile-toggle" id="navToggle" aria-expanded="false">';
    echo '<span></span>';
    echo '<span></span>';
    echo '<span></span>';
    echo '</div>';
    echo '<div class="nav-tabs" id="navTabs">';
    
    foreach ($nav_items as $key => $item) {
        $active_class = ($current_page === $key) ? 'active' : '';
        $label = htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8');
        echo '<a href="' . htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8') . '" class="nav-tab ' . $active_class . '" title="' . $label . '">';
        echo '<i class="' . $item['icon'] . '" aria-hidden="true"></i>';
        echo '<span class="nav-label">' . $label . '</span>';
        echo '</a>';
    }
    
    echo '</div>';
    echo '</nav>';
    echo '<button type="button" class="back-to-top" id="adminBackToTop" hidden aria-label="Back to top"><i class="fas fa-arrow-up" aria-hidden="true"></i></button>';
}
?>
