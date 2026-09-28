<?php
/**
 * Admin Navigation Component
 * This file contains the navigation structure for all admin pages
 */

function renderAdminNavigation($current_page = '') {
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
        'bookings' => [
            'url' => 'bookings.php',
            'icon' => 'fas fa-calendar-alt',
            'label' => 'Bookings'
        ],
        'facility_calendar' => [
            'url' => 'facility-calendar.php',
            'icon' => 'fas fa-calendar',
            'label' => 'Facility Calendar'
        ],
        'facility_management' => [
            'url' => 'programme-facility-management.php',
            'icon' => 'fas fa-building',
            'label' => 'Facility Management'
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
    
    echo '<nav class="admin-nav">';
    echo '<div class="nav-mobile-toggle" id="navToggle">';
    echo '<span></span>';
    echo '<span></span>';
    echo '<span></span>';
    echo '</div>';
    echo '<div class="nav-tabs" id="navTabs">';
    
    foreach ($nav_items as $key => $item) {
        $active_class = ($current_page === $key) ? 'active' : '';
        echo '<a href="' . $item['url'] . '" class="nav-tab ' . $active_class . '">';
        echo '<i class="' . $item['icon'] . '"></i>';
        echo '<span class="nav-label">' . $item['label'] . '</span>';
        echo '</a>';
    }
    
    echo '</div>';
    echo '</nav>';
}
?>
