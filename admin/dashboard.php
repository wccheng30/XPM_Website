<?php
require_once '../includes/auth.php';
auth_require(['admin', 'manager', 'trainer']);

// Include shared database connection
require_once '../includes/database.php';
require_once '../includes/blacklist-lookup.php';

// Get statistics
$pdo = get_db_connection();
$stats = [
    'contacts' => 0,
    'today_contacts' => 0,
    'recent_contacts' => 0,
    'custom_requests' => 0,
    'recent_custom' => 0,
    'jobs' => 0,
    'programmes' => 0,
    'applications' => 0,
    'pending_applications' => 0,
    'recent_applications' => 0,
    'registrations' => 0,
    'pending_registrations' => 0,
    'recent_registrations' => 0,
    'bookings' => 0,
    'pending_bookings' => 0,
    'today_bookings' => 0,
    'recent_bookings' => 0,
    'trainers' => 0,
    'active_trainers' => 0,
    'recent_jobs' => 0,
    'recent_programmes' => 0,
    'recent_trainers' => 0
];

$recent_contacts = [];
$recent_custom_requests = [];
$recent_applications = [];
$recent_registrations = [];
$upcoming_bookings = [];
$month_booking_days = [];
$interest_breakdown = [];
$blacklist_lookup = [];
$dash_loaded = false;

$is_trainer_view = auth_is_trainer();
$trainer_programme_count = 0;
if ($pdo && $is_trainer_view) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM programmes WHERE owner_user_id = ?");
        $stmt->execute([(int) (auth_user()['id'] ?? 0)]);
        $trainer_programme_count = (int) $stmt->fetchColumn();
        $dash_loaded = true;
    } catch (PDOException $e) {
        $trainer_programme_count = 0;
    }
} elseif ($pdo) {
    try {
        // Total contacts
        $stmt = $pdo->query("SELECT COUNT(*) FROM contacts");
        $stats['contacts'] = $stmt->fetchColumn();
        
        // Today's contacts
        $stmt = $pdo->query("SELECT COUNT(*) FROM contacts WHERE DATE(created_at) = CURDATE()");
        $stats['today_contacts'] = $stmt->fetchColumn();
        
        // Recent contacts (this week)
        $stmt = $pdo->query("SELECT COUNT(*) FROM contacts WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $stats['recent_contacts'] = $stmt->fetchColumn();
        
        // Custom requests
        $stmt = $pdo->query("SELECT COUNT(*) FROM contacts WHERE interest = 'custom'");
        $stats['custom_requests'] = $stmt->fetchColumn();
        
        // Recent custom requests
        $stmt = $pdo->query("SELECT COUNT(*) FROM contacts WHERE interest = 'custom' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $stats['recent_custom'] = $stmt->fetchColumn();
        
        // Active jobs
        $stmt = $pdo->query("SELECT COUNT(*) FROM jobs WHERE is_active = 1");
        $stats['jobs'] = $stmt->fetchColumn();
        
        // Training programmes
        $stmt = $pdo->query("SELECT COUNT(*) FROM programmes WHERE is_active = 1");
        $stats['programmes'] = $stmt->fetchColumn();
        
        // Job applications
        $stmt = $pdo->query("SELECT COUNT(*) FROM applications");
        $stats['applications'] = $stmt->fetchColumn();
        
        // Pending applications
        $stmt = $pdo->query("SELECT COUNT(*) FROM applications WHERE status = 'pending'");
        $stats['pending_applications'] = $stmt->fetchColumn();
        
        // Recent applications (this week)
        $stmt = $pdo->query("SELECT COUNT(*) FROM applications WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $stats['recent_applications'] = $stmt->fetchColumn();
        
        // Programme registrations
        $stmt = $pdo->query("SELECT COUNT(*) FROM programme_registrations");
        $stats['registrations'] = $stmt->fetchColumn();
        
        // Pending registrations
        $stmt = $pdo->query("SELECT COUNT(*) FROM programme_registrations WHERE status = 'pending'");
        $stats['pending_registrations'] = $stmt->fetchColumn();
        
        // Recent registrations (this week)
        $stmt = $pdo->query("SELECT COUNT(*) FROM programme_registrations WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $stats['recent_registrations'] = $stmt->fetchColumn();
        
        // Recent contacts
        $stmt = $pdo->query("SELECT * FROM contacts ORDER BY created_at DESC LIMIT 5");
        $recent_contacts = $stmt->fetchAll();
        
        // Recent custom requests
        $stmt = $pdo->query("SELECT * FROM contacts WHERE interest = 'custom' ORDER BY created_at DESC LIMIT 3");
        $recent_custom_requests = $stmt->fetchAll();
        
        // Interest breakdown
        $stmt = $pdo->query("SELECT interest, COUNT(*) as count FROM contacts GROUP BY interest ORDER BY count DESC");
        $interest_breakdown = $stmt->fetchAll();
        
        // Total bookings
        $stmt = $pdo->query("SELECT COUNT(*) FROM bookings");
        $stats['bookings'] = $stmt->fetchColumn();
        
        // Pending bookings
        $stmt = $pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'pending'");
        $stats['pending_bookings'] = $stmt->fetchColumn();
        
        // Today's bookings
        $stmt = $pdo->query("SELECT COUNT(*) FROM bookings WHERE booking_date = CURDATE()");
        $stats['today_bookings'] = $stmt->fetchColumn();
        
        // Recent bookings (this week)
        $stmt = $pdo->query("SELECT COUNT(*) FROM bookings WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $stats['recent_bookings'] = $stmt->fetchColumn();
        
        // Trainer statistics
        $stmt = $pdo->query("SELECT COUNT(*) FROM trainers");
        $stats['trainers'] = $stmt->fetchColumn();
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM trainers WHERE is_active = 1");
        $stats['active_trainers'] = $stmt->fetchColumn();
        
        $blacklist_lookup = load_blacklist_lookup($pdo);
        $dash_loaded = true;

        try {
            $stmt = $pdo->query("SELECT COUNT(*) FROM jobs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
            $stats['recent_jobs'] = $stmt->fetchColumn();

            $stmt = $pdo->query("SELECT COUNT(*) FROM programmes WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
            $stats['recent_programmes'] = $stmt->fetchColumn();

            $stmt = $pdo->query("SELECT COUNT(*) FROM trainers WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
            $stats['recent_trainers'] = $stmt->fetchColumn();

            $stmt = $pdo->query("SELECT a.id, a.name, a.phone, a.status, a.created_at, j.title AS subject FROM applications a LEFT JOIN jobs j ON a.job_id = j.id ORDER BY a.created_at DESC LIMIT 5");
            $recent_applications = $stmt->fetchAll();

            $stmt = $pdo->query("SELECT pr.id, pr.name, pr.phone, pr.status, pr.created_at, p.title AS subject FROM programme_registrations pr LEFT JOIN programmes p ON pr.programme_id = p.id ORDER BY pr.created_at DESC LIMIT 5");
            $recent_registrations = $stmt->fetchAll();

            $stmt = $pdo->query("SELECT b.id, b.customer_name, b.facility_type, b.booking_date, b.start_time, b.end_time, b.status, p.title AS programme_title FROM bookings b LEFT JOIN programmes p ON b.programme_id = p.id WHERE b.booking_date >= CURDATE() AND b.status IN ('pending', 'confirmed') ORDER BY b.booking_date ASC, b.start_time ASC LIMIT 5");
            $upcoming_bookings = $stmt->fetchAll();

            $month_start = date('Y-m-01');
            $month_next = date('Y-m-01', strtotime($month_start . ' +1 month'));
            $stmt = $pdo->prepare("SELECT booking_date, COUNT(*) AS day_count FROM bookings WHERE booking_date >= ? AND booking_date < ? AND status IN ('pending', 'confirmed') GROUP BY booking_date");
            $stmt->execute([$month_start, $month_next]);
            foreach ($stmt->fetchAll() as $day_row) {
                $month_booking_days[$day_row['booking_date']] = (int) $day_row['day_count'];
            }
        } catch (PDOException $e) {
            $recent_applications = [];
            $recent_registrations = [];
            $upcoming_bookings = [];
            $month_booking_days = [];
        }
    } catch (PDOException $e) {
        $dash_loaded = false;
    }
}

$interest_labels = [
    'hr-leadership' => 'HR Leadership',
    'employee-relations' => 'Employee Relations',
    'hr-analytics' => 'HR Analytics',
    'hr-compliance' => 'HR Compliance',
    'recruitment' => 'Recruitment',
    'compensation' => 'Compensation',
    'custom' => 'Custom Service'
];

$interest_total = 0;
foreach ($interest_breakdown as $interest_row) {
    $interest_total += (int) $interest_row['count'];
}

$dash_now = new DateTime('now', new DateTimeZone('Asia/Kuala_Lumpur'));
$dash_name = trim((string) ($_SESSION['user_name'] ?? ($_SESSION['admin_username'] ?? '')));
if ($dash_name === '') {
    $dash_name = 'there';
}
$dash_hour = (int) $dash_now->format('G');
if ($dash_hour < 12) {
    $dash_greeting = 'Good morning';
} elseif ($dash_hour < 17) {
    $dash_greeting = 'Good afternoon';
} else {
    $dash_greeting = 'Good evening';
}
$dash_date_label = $dash_now->format('l, j F Y');

$facility_labels = [
    'conference_room' => 'Conference Room',
    'training_room' => 'Training Room',
    'breakout_area' => 'Breakout Area',
];

function dash_relative_time($datetime) {
    $ts = strtotime((string) $datetime);
    if (!$ts) {
        return '';
    }
    $diff = time() - $ts;
    if ($diff < 60) {
        return 'Just now';
    }
    if ($diff < 3600) {
        $minutes = (int) floor($diff / 60);
        return $minutes . ' min ago';
    }
    if ($diff < 86400) {
        $hours = (int) floor($diff / 3600);
        return $hours === 1 ? '1 hr ago' : $hours . ' hr ago';
    }
    if ($diff < 604800) {
        $days = (int) floor($diff / 86400);
        return $days === 1 ? '1 day ago' : $days . ' days ago';
    }
    return date('j M', $ts);
}

function dash_delta_class($count) {
    return ((int) $count) > 0 ? 'is-up' : 'is-flat';
}

$dash_activity = [];
if ($dash_loaded && empty($is_trainer_view)) {
    foreach ($recent_contacts as $contact) {
        $bl_info = blacklist_match($blacklist_lookup, $contact['name'], $contact['phone'] ?? '');
        $dash_activity[] = [
            'type' => 'Contact',
            'icon' => 'fas fa-user',
            'title' => $contact['name'],
            'meta' => $interest_labels[$contact['interest']] ?? ($contact['email'] ?? 'Contact'),
            'time' => $contact['created_at'],
            'href' => 'contact-details.php?id=' . (int) $contact['id'],
            'blacklisted' => (($contact['status'] ?? '') === 'blacklisted') || $bl_info !== null,
        ];
    }
    foreach ($recent_applications as $row) {
        $bl_info = blacklist_match($blacklist_lookup, $row['name'], $row['phone'] ?? '');
        $dash_activity[] = [
            'type' => 'Application',
            'icon' => 'fas fa-file-alt',
            'title' => $row['name'],
            'meta' => $row['subject'] ?: 'Job application',
            'time' => $row['created_at'],
            'href' => 'applications.php?search=' . rawurlencode($row['name']),
            'blacklisted' => $bl_info !== null,
        ];
    }
    foreach ($recent_registrations as $row) {
        $bl_info = blacklist_match($blacklist_lookup, $row['name'], $row['phone'] ?? '');
        $dash_activity[] = [
            'type' => 'Registration',
            'icon' => 'fas fa-graduation-cap',
            'title' => $row['name'],
            'meta' => $row['subject'] ?: 'Programme registration',
            'time' => $row['created_at'],
            'href' => 'registrations.php?search=' . rawurlencode($row['name']),
            'blacklisted' => $bl_info !== null,
        ];
    }
    usort($dash_activity, function ($a, $b) {
        return strtotime((string) $b['time']) <=> strtotime((string) $a['time']);
    });
    $dash_activity = array_slice($dash_activity, 0, 8);
}

$attention_items = [
    [
        'count' => (int) $stats['pending_applications'],
        'label' => 'Pending applications',
        'href' => 'applications.php?filter=pending',
    ],
    [
        'count' => (int) $stats['pending_registrations'],
        'label' => 'Pending registrations',
        'href' => 'registrations.php?filter=pending',
    ],
    [
        'count' => (int) $stats['pending_bookings'],
        'label' => 'Pending facility bookings',
        'href' => 'facilities.php?status=pending',
    ],
];

$kpi_items = [
    ['count' => (int) $stats['contacts'], 'delta' => (int) $stats['recent_contacts'], 'label' => 'Total Contacts', 'icon' => 'fas fa-users', 'href' => 'contacts.php'],
    ['count' => (int) $stats['custom_requests'], 'delta' => (int) $stats['recent_custom'], 'label' => 'Custom Requests', 'icon' => 'fas fa-star', 'href' => 'contacts.php?filter=custom'],
    ['count' => (int) $stats['jobs'], 'delta' => (int) $stats['recent_jobs'], 'label' => 'Active Jobs', 'icon' => 'fas fa-briefcase', 'href' => 'jobs.php'],
    ['count' => (int) $stats['programmes'], 'delta' => (int) $stats['recent_programmes'], 'label' => 'Training Programmes', 'icon' => 'fas fa-book-open', 'href' => 'programmes.php'],
    ['count' => (int) $stats['trainers'], 'delta' => (int) $stats['recent_trainers'], 'label' => 'Trainers', 'icon' => 'fas fa-user-tie', 'href' => 'trainers.php'],
];

$cal_month_start = strtotime($dash_now->format('Y-m-01'));
$cal_days = (int) $dash_now->format('t');
$cal_pad = (int) date('w', $cal_month_start);
$cal_month_count = array_sum($month_booking_days);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - XpressPoint Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <?php include_once __DIR__ . '/includes/theme-boot.php'; ?>
    <link rel="stylesheet" href="<?php echo admin_asset('admin.min.css'); ?>">
    <link rel="stylesheet" href="<?php echo admin_asset('css/dashboard.css'); ?>">
</head>
<body data-motion="admin">
    <?php include_once 'includes/header.php'; renderAdminHeader('Dashboard', 'fas fa-tachometer-alt'); ?>
    
    <div class="admin-container">
        <!-- Navigation -->
        <?php include 'includes/navigation.php'; renderAdminNavigation('dashboard'); ?>

        <header class="dash-head">
            <div>
                <h2 class="dash-greeting"><?php echo htmlspecialchars($dash_greeting . ', ' . $dash_name); ?></h2>
                <p class="dash-date"><?php echo htmlspecialchars($dash_date_label); ?></p>
            </div>
            <?php if (empty($is_trainer_view)): ?>
            <details class="dash-create" id="dashCreate">
                <summary class="btn btn-primary">
                    <i class="fas fa-plus" aria-hidden="true"></i>
                    Create
                    <i class="fas fa-chevron-down dash-create-caret" aria-hidden="true"></i>
                </summary>
                <div class="dash-create-panel">
                    <a href="add-job.php"><i class="fas fa-briefcase" aria-hidden="true"></i> New job</a>
                    <a href="add-programme.php"><i class="fas fa-book-open" aria-hidden="true"></i> New programme</a>
                    <a href="add-trainer.php"><i class="fas fa-user-tie" aria-hidden="true"></i> New trainer</a>
                    <a href="add-blacklist.php"><i class="fas fa-user-slash" aria-hidden="true"></i> New blacklist entry</a>
                </div>
            </details>
            <?php endif; ?>
        </header>

        <?php if (!empty($is_trainer_view)): ?>
        <section class="card dash-trainer">
            <div class="card-body">
                <h2 class="section-title"><i class="fas fa-calendar-alt" aria-hidden="true"></i> Your calendar</h2>
                <p class="page-sub">Book a webinar or training programme on an open day. The first booking keeps that day.</p>
                <?php if (!$dash_loaded): ?>
                    <div class="dash-skeleton-stack" aria-busy="true" aria-label="Loading your programmes">
                        <span class="dash-skeleton"></span>
                        <span class="dash-skeleton dash-skeleton-short"></span>
                    </div>
                <?php elseif ($trainer_programme_count === 0): ?>
                    <div class="empty-state is-compact">
                        <i class="fas fa-calendar-plus" aria-hidden="true"></i>
                        <p>No programmes yet. Book an open day when you are ready.</p>
                    </div>
                <?php else: ?>
                    <p class="dash-trainer-count"><?php echo (int) $trainer_programme_count; ?> <?php echo $trainer_programme_count === 1 ? 'programme' : 'programmes'; ?></p>
                <?php endif; ?>
                <p class="cluster">
                    <a class="btn btn-primary" href="facilities.php">Book a day</a>
                    <a class="btn btn-secondary" href="my-profile.php">Edit trainer profile</a>
                </p>
            </div>
        </section>
        <?php else: ?>

        <section class="dash-attention-row" aria-label="Needs attention" <?php echo $dash_loaded ? '' : 'aria-busy="true"'; ?>>
            <?php if (!$dash_loaded): ?>
                <?php for ($skeleton_i = 0; $skeleton_i < 3; $skeleton_i++): ?>
                <div class="dash-attention dash-skeleton-card">
                    <span class="dash-skeleton dash-skeleton-short"></span>
                    <span class="dash-skeleton dash-skeleton-lg"></span>
                    <span class="dash-skeleton"></span>
                </div>
                <?php endfor; ?>
            <?php else: ?>
                <?php foreach ($attention_items as $item):
                    $is_clear = $item['count'] === 0;
                ?>
                <a class="dash-attention<?php echo $is_clear ? ' is-clear' : ''; ?>" href="<?php echo htmlspecialchars($item['href']); ?>">
                    <?php if ($is_clear): ?>
                        <span class="dash-attention-icon" aria-hidden="true"><i class="fas fa-check" aria-hidden="true"></i></span>
                    <?php endif; ?>
                    <span class="dash-attention-value"><?php echo number_format($item['count']); ?></span>
                    <span class="dash-attention-label"><?php echo htmlspecialchars($item['label']); ?></span>
                    <span class="dash-attention-state"><?php echo $is_clear ? 'Clear' : 'Review'; ?></span>
                </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>

        <section class="dash-kpis" aria-label="Key figures" <?php echo $dash_loaded ? '' : 'aria-busy="true"'; ?>>
            <?php if (!$dash_loaded): ?>
                <?php for ($skeleton_i = 0; $skeleton_i < 5; $skeleton_i++): ?>
                <div class="dash-kpi dash-skeleton-card">
                    <span class="dash-skeleton dash-skeleton-short"></span>
                    <span class="dash-skeleton dash-skeleton-lg"></span>
                    <span class="dash-skeleton"></span>
                </div>
                <?php endfor; ?>
            <?php else: ?>
                <?php foreach ($kpi_items as $item): ?>
                <a class="dash-kpi" href="<?php echo htmlspecialchars($item['href']); ?>">
                    <span class="dash-kpi-icon" aria-hidden="true"><i class="<?php echo htmlspecialchars($item['icon']); ?>" aria-hidden="true"></i></span>
                    <span class="dash-kpi-value"><?php echo number_format($item['count']); ?></span>
                    <span class="dash-kpi-label"><?php echo htmlspecialchars($item['label']); ?></span>
                    <span class="dash-delta <?php echo dash_delta_class($item['delta']); ?>">+<?php echo (int) $item['delta']; ?> this week</span>
                </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>

        <div class="dash-columns">
            <section class="card dash-panel">
                <div class="card-body">
                    <h2 class="section-title"><i class="fas fa-stream" aria-hidden="true"></i> Recent activity</h2>
                    <?php if (!$dash_loaded): ?>
                        <div class="dash-skeleton-stack" aria-busy="true" aria-label="Loading recent activity">
                            <span class="dash-skeleton"></span>
                            <span class="dash-skeleton"></span>
                            <span class="dash-skeleton"></span>
                            <span class="dash-skeleton dash-skeleton-short"></span>
                        </div>
                    <?php elseif (empty($dash_activity)): ?>
                        <div class="empty-state is-compact">
                            <i class="fas fa-inbox" aria-hidden="true"></i>
                            <p>No recent activity</p>
                        </div>
                    <?php else: ?>
                        <div class="dash-activity">
                            <?php foreach ($dash_activity as $event): ?>
                            <a class="dash-activity-item" href="<?php echo htmlspecialchars($event['href']); ?>">
                                <span class="dash-type-icon" aria-hidden="true"><i class="<?php echo htmlspecialchars($event['icon']); ?>" aria-hidden="true"></i></span>
                                <span class="dash-activity-copy">
                                    <span class="dash-activity-title">
                                        <?php echo htmlspecialchars($event['title']); ?>
                                        <?php if (!empty($event['blacklisted'])): ?>
                                            <span class="blacklist-flag">Blacklisted</span>
                                        <?php endif; ?>
                                    </span>
                                    <span class="dash-activity-meta"><?php echo htmlspecialchars($event['type'] . ' · ' . $event['meta']); ?></span>
                                </span>
                                <time class="dash-activity-time" datetime="<?php echo htmlspecialchars(date('c', strtotime((string) $event['time']))); ?>"><?php echo htmlspecialchars(dash_relative_time($event['time'])); ?></time>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <div class="dash-side">
                <section class="card dash-panel">
                    <div class="card-body">
                        <h2 class="section-title"><i class="fas fa-calendar-check" aria-hidden="true"></i> Upcoming bookings</h2>
                        <?php if (!$dash_loaded): ?>
                            <div class="dash-skeleton-stack" aria-busy="true" aria-label="Loading upcoming bookings">
                                <span class="dash-skeleton"></span>
                                <span class="dash-skeleton"></span>
                                <span class="dash-skeleton dash-skeleton-short"></span>
                            </div>
                        <?php elseif (empty($upcoming_bookings)): ?>
                            <div class="empty-state is-compact">
                                <i class="fas fa-calendar" aria-hidden="true"></i>
                                <p>No upcoming bookings</p>
                            </div>
                        <?php else: ?>
                            <div class="dash-bookings">
                                <?php foreach ($upcoming_bookings as $booking):
                                    $facility_name = $facility_labels[$booking['facility_type']] ?? $booking['facility_type'];
                                    $when = date('D, j M', strtotime($booking['booking_date']));
                                    $start_label = date('g:i A', strtotime($booking['start_time']));
                                ?>
                                <a class="dash-booking" href="booking-details.php?id=<?php echo (int) $booking['id']; ?>">
                                    <span class="dash-booking-date"><?php echo htmlspecialchars($when); ?></span>
                                    <span class="dash-booking-copy">
                                        <span class="dash-booking-title"><?php echo htmlspecialchars($booking['customer_name']); ?></span>
                                        <span class="dash-booking-meta"><?php echo htmlspecialchars($facility_name . ' · ' . $start_label); ?></span>
                                    </span>
                                </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>

                <section class="card dash-panel">
                    <div class="card-body">
                        <h2 class="section-title"><i class="fas fa-calendar-alt" aria-hidden="true"></i> <?php echo htmlspecialchars(date('F Y', $cal_month_start)); ?></h2>
                        <?php if (!$dash_loaded): ?>
                            <div class="dash-skeleton dash-skeleton-cal" aria-busy="true" aria-label="Loading calendar"></div>
                        <?php else: ?>
                        <div class="dash-cal" role="img" aria-label="<?php echo htmlspecialchars(date('F Y', $cal_month_start) . ', ' . $cal_month_count . ' bookings'); ?>">
                            <div class="dash-cal-grid">
                                <?php foreach (['S', 'M', 'T', 'W', 'T', 'F', 'S'] as $dow): ?>
                                    <span class="dash-cal-dow"><?php echo $dow; ?></span>
                                <?php endforeach; ?>
                                <?php for ($pad = 0; $pad < $cal_pad; $pad++): ?>
                                    <span class="dash-cal-day is-empty"></span>
                                <?php endfor; ?>
                                <?php for ($day = 1; $day <= $cal_days; $day++):
                                    $day_key = date('Y-m-', $cal_month_start) . str_pad((string) $day, 2, '0', STR_PAD_LEFT);
                                    $day_class = 'dash-cal-day';
                                    if ($day_key === $dash_now->format('Y-m-d')) {
                                        $day_class .= ' is-today';
                                    }
                                    if (!empty($month_booking_days[$day_key])) {
                                        $day_class .= ' has-booking';
                                    }
                                ?>
                                    <span class="<?php echo $day_class; ?>"><?php echo $day; ?></span>
                                <?php endfor; ?>
                            </div>
                            <p class="dash-cal-summary"><?php echo (int) $cal_month_count; ?> <?php echo $cal_month_count === 1 ? 'booking' : 'bookings'; ?> this month</p>
                            <a class="dash-cal-link" href="facilities.php?month=<?php echo (int) date('n', $cal_month_start); ?>&amp;year=<?php echo (int) date('Y', $cal_month_start); ?>">Open calendar</a>
                        </div>
                        <?php endif; ?>
                    </div>
                </section>
            </div>
        </div>
        <?php endif; ?>
    </div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var menu = document.getElementById('dashCreate');
    if (!menu) return;
    var release = null;
    var summary = menu.querySelector('summary');
    function closeMenu() {
        menu.open = false;
        if (release) { var r = release; release = null; r(false); }
        if (summary) summary.focus();
    }
    menu.addEventListener('toggle', function () {
        if (menu.open && typeof window.xpmFocusTrap === 'function') {
            release = window.xpmFocusTrap(menu, { returnTo: summary, onClose: closeMenu });
        } else if (release) {
            var r = release; release = null; r(false);
        }
    });
    document.addEventListener('click', function (event) {
        if (!menu.open || menu.contains(event.target)) return;
        closeMenu();
    });
    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape' || !menu.open) return;
        closeMenu();
    });
});
</script>

</body>
</html> 