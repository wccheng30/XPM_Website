<?php
require_once '../includes/auth.php';
auth_require(['admin', 'manager', 'trainer']);

require_once '../includes/database.php';
require_once '../includes/facility-booking-service.php';
require_once '../includes/email-notifications.php';

$pdo = get_db_connection();
$facilityService = new FacilityBookingService($pdo);
$actor = auth_user();
$error_message = '';
$success_message = '';
$book_form = [
    'programme_id' => 0,
    'programme_type' => 'training',
    'start_date' => '',
    'start_time' => '',
    'end_date' => '',
    'end_time' => '',
];
$slot_open = false;

function facilities_clock($time) {
    $time = substr(trim((string) $time), 0, 5);
    return preg_match('/^\d{2}:\d{2}$/', $time) ? $time : '';
}

function facilities_mail_admin($trainerName, $typeLabel, $startDate, $startTime, $endDate, $endTime, $kind) {
    try {
        $notes = new EmailNotifications();
        return $notes->sendTrainerDayBooking($trainerName, $typeLabel, $startDate, $startTime, $endDate, $endTime, $kind);
    } catch (Throwable $e) {
        return false;
    }
}

function facilities_calendar_programme(array $programme, array $actor) {
    $type = auth_normalize_programme_type($programme['programme_type'] ?? 'training');
    $label = auth_programme_type_label($type);
    $time = '';
    if (!empty($programme['start_time'])) {
        $time = date('g:i A', strtotime($programme['start_time']));
        if (!empty($programme['end_time'])) {
            $time .= ' - ' . date('g:i A', strtotime($programme['end_time']));
        }
    }
    $class = 'booking-item programme programme-' . $type;
    $html = '<span class="booking-type">' . htmlspecialchars($label) . '</span>';
    $html .= '<span class="booking-name">' . htmlspecialchars($programme['title'] ?? '') . '</span>';
    if ($time !== '') {
        $html .= '<span class="booking-time">' . htmlspecialchars($time) . '</span>';
    }
    $programme_id = (int) ($programme['id'] ?? 0);
    if (auth_is_trainer()) {
        return '<div class="' . $class . '">' . $html . '</div>';
    }
    if ($programme_id && auth_can_change_booking($actor, $programme['owner_user_id'] ?? null, $programme_id)) {
        return '<a href="edit-programme.php?id=' . $programme_id . '" class="' . $class . '">' . $html . '</a>';
    }
    return '<div class="' . $class . '">' . $html . '</div>';
}

function facilities_programme_owner(PDO $pdo, $programmeId) {
    $stmt = $pdo->prepare("SELECT owner_user_id FROM programmes WHERE id = ?");
    $stmt->execute([(int) $programmeId]);
    $row = $stmt->fetch();
    return $row ? $row['owner_user_id'] : null;
}

function facilities_booking_access(PDO $pdo, $bookingId) {
    $stmt = $pdo->prepare("
        SELECT b.*, p.owner_user_id
        FROM bookings b
        LEFT JOIN programmes p ON b.programme_id = p.id
        WHERE b.id = ?
    ");
    $stmt->execute([(int) $bookingId]);
    return $stmt->fetch() ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if (auth_is_trainer() && !in_array($action, ['book_programme_day', 'cancel_trainer_booking'], true)) {
            throw new Exception('Book a day on the calendar. Programme editing stays with the admin.');
        }
        if ($action === 'book_programme_day') {
            if (!auth_is_trainer()) {
                throw new Exception('Only a trainer can reserve a day this way.');
            }
            $book_form = [
                'programme_id' => (int) ($_POST['programme_id'] ?? 0),
                'programme_type' => auth_normalize_programme_type($_POST['programme_type'] ?? 'training'),
                'start_date' => trim($_POST['start_date'] ?? ''),
                'start_time' => trim($_POST['start_time'] ?? ''),
                'end_date' => trim($_POST['end_date'] ?? ''),
                'end_time' => trim($_POST['end_time'] ?? ''),
            ];
            if ($book_form['end_date'] === '') {
                $book_form['end_date'] = $book_form['start_date'];
            }
            if ($book_form['start_date'] === '' || $book_form['start_time'] === '' || $book_form['end_time'] === '') {
                throw new Exception('Choose the type, the start date and time, and the end time.');
            }
            if ($book_form['programme_id'] === 0 && $book_form['start_date'] < date('Y-m-d')) {
                throw new Exception('Choose today or a later date.');
            }
            if ($book_form['start_date'] === $book_form['end_date'] && $book_form['end_time'] <= $book_form['start_time']) {
                throw new Exception('The end time has to be after the start time.');
            }
            $reserved = xpm_day_reservation_conflict(
                $pdo,
                $book_form['start_date'],
                $book_form['end_date'],
                $book_form['programme_id'] ?: null
            );
            if ($reserved) {
                throw new Exception($reserved);
            }
            $trainerName = trim((string) ($actor['name'] ?? ''));
            if ($trainerName === '') {
                $trainerName = trim((string) ($actor['email'] ?? '')) ?: 'Trainer';
            }
            $schedule = $book_form['end_date'] > $book_form['start_date'] ? 'date_range' : 'single_day';
            $kind = 'booked';
            if ($book_form['programme_id'] > 0) {
                $owned = $pdo->prepare('SELECT id FROM programmes WHERE id = ? AND owner_user_id = ?');
                $owned->execute([$book_form['programme_id'], (int) ($actor['id'] ?? 0)]);
                if (!$owned->fetch()) {
                    throw new Exception('You can only change a booking you made.');
                }
                $stmt = $pdo->prepare("
                    UPDATE programmes
                    SET programme_type = ?, schedule_type = ?, start_date = ?, end_date = ?, start_time = ?, end_time = ?
                    WHERE id = ? AND owner_user_id = ?
                ");
                $stmt->execute([
                    $book_form['programme_type'],
                    $schedule,
                    $book_form['start_date'],
                    $book_form['end_date'],
                    $book_form['start_time'],
                    $book_form['end_time'],
                    $book_form['programme_id'],
                    (int) ($actor['id'] ?? 0),
                ]);
                $kind = 'updated';
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO programmes
                        (title, description, programme_type, owner_user_id, schedule_type, start_date, end_date, start_time, end_time, is_active, requires_facility)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0)
                ");
                $stmt->execute([
                    $trainerName,
                    'Booked from the calendar.',
                    $book_form['programme_type'],
                    (int) ($actor['id'] ?? 0),
                    $schedule,
                    $book_form['start_date'],
                    $book_form['end_date'],
                    $book_form['start_time'],
                    $book_form['end_time'],
                ]);
            }
            $typeLabel = auth_programme_type_label($book_form['programme_type']);
            $mailed = facilities_mail_admin(
                $trainerName,
                $typeLabel,
                $book_form['start_date'],
                $book_form['start_time'],
                $book_form['end_date'],
                $book_form['end_time'],
                $kind
            );
            $success_message = $kind === 'updated'
                ? 'The booking is updated.'
                : $trainerName . ' reserved ' . $typeLabel
                    . ' from ' . date('M j, Y', strtotime($book_form['start_date']))
                    . ' to ' . date('M j, Y', strtotime($book_form['end_date'])) . '.';
            if ($mailed) {
                $success_message .= ' The admin has been notified.';
            }
            $book_form = [
                'programme_id' => 0,
                'programme_type' => 'training',
                'start_date' => '',
                'start_time' => '',
                'end_date' => '',
                'end_time' => '',
            ];
        } elseif ($action === 'cancel_trainer_booking') {
            if (!auth_is_trainer()) {
                throw new Exception('Only a trainer can cancel a calendar booking this way.');
            }
            $programmeId = (int) ($_POST['programme_id'] ?? 0);
            $owned = $pdo->prepare('SELECT id, title, programme_type, start_date, end_date, start_time, end_time FROM programmes WHERE id = ? AND owner_user_id = ?');
            $owned->execute([$programmeId, (int) ($actor['id'] ?? 0)]);
            $existing = $owned->fetch(PDO::FETCH_ASSOC);
            if (!$existing) {
                throw new Exception('You can only cancel a booking you made.');
            }
            $trainerName = trim((string) ($actor['name'] ?? ''));
            if ($trainerName === '') {
                $trainerName = trim((string) ($actor['email'] ?? '')) ?: 'Trainer';
            }
            $pdo->prepare('DELETE FROM bookings WHERE programme_id = ?')->execute([$programmeId]);
            $pdo->prepare('DELETE FROM programmes WHERE id = ? AND owner_user_id = ?')->execute([$programmeId, (int) ($actor['id'] ?? 0)]);
            $mailed = facilities_mail_admin(
                $trainerName,
                auth_programme_type_label($existing['programme_type'] ?? 'training'),
                $existing['start_date'],
                $existing['start_time'],
                $existing['end_date'] ?: $existing['start_date'],
                $existing['end_time'],
                'cancelled'
            );
            $success_message = 'The booking is cancelled and those days are free.';
            if ($mailed) {
                $success_message .= ' The admin has been notified.';
            }
        } elseif (in_array($action, ['confirm', 'cancel', 'complete'], true)) {
            $booking = facilities_booking_access($pdo, (int) ($_POST['booking_id'] ?? 0));
            if (!$booking || !auth_can_change_booking($actor, $booking['owner_user_id'] ?? null, $booking['programme_id'] ?? null)) {
                throw new Exception('You can only change bookings for programmes you own.');
            }
            $emailNotifications = new EmailNotifications();
            if ($action === 'confirm') {
                $stmt = $pdo->prepare("UPDATE bookings SET status = 'confirmed', confirmed_by = ?, confirmed_at = NOW() WHERE id = ?");
                $stmt->execute([(int) ($actor['id'] ?? 1), (int) $booking['id']]);
                $emailNotifications->sendStatusUpdate((int) $booking['id'], 'confirmed');
            } elseif ($action === 'cancel') {
                $stmt = $pdo->prepare("UPDATE bookings SET status = 'cancelled', cancellation_reason = ? WHERE id = ?");
                $stmt->execute([$_POST['cancellation_reason'] ?? '', (int) $booking['id']]);
                $emailNotifications->sendStatusUpdate((int) $booking['id'], 'cancelled');
            } else {
                $stmt = $pdo->prepare("UPDATE bookings SET status = 'completed' WHERE id = ?");
                $stmt->execute([(int) $booking['id']]);
                $emailNotifications->sendStatusUpdate((int) $booking['id'], 'completed');
            }
            $success_message = 'Booking updated. The customer has been notified by email.';
        } elseif (in_array($action, ['create_facility_booking', 'update_facility_booking', 'delete_facility_booking', 'toggle_facility_requirement'], true)) {
            $programmeId = (int) ($_POST['programme_id'] ?? 0);
            $ownerId = facilities_programme_owner($pdo, $programmeId);
            if (!auth_can_change_booking($actor, $ownerId, $programmeId)) {
                throw new Exception('You can only change facility bookings for programmes you own.');
            }
            if ($action === 'create_facility_booking') {
                $bookingId = $facilityService->createProgrammeFacilityBooking($programmeId);
                $success_message = "Facility booking created. Booking ID: #{$bookingId}";
            } elseif ($action === 'update_facility_booking') {
                $facilityService->updateProgrammeFacilityBooking(
                    $programmeId,
                    $_POST['facility_type'] ?? '',
                    $_POST['booking_date'] ?? '',
                    $_POST['start_time'] ?? '',
                    $_POST['end_time'] ?? ''
                );
                $success_message = 'Facility booking updated.';
            } elseif ($action === 'delete_facility_booking') {
                $facilityService->deleteProgrammeFacilityBooking($programmeId);
                $success_message = 'Facility booking deleted.';
            } else {
                $requires = filter_var($_POST['requires_facility'] ?? false, FILTER_VALIDATE_BOOLEAN);
                $stmt = $pdo->prepare("UPDATE programmes SET requires_facility = ? WHERE id = ?");
                $stmt->execute([$requires ? 1 : 0, $programmeId]);
                if (!$requires) {
                    $facilityService->deleteProgrammeFacilityBooking($programmeId);
                }
                $success_message = $requires
                    ? 'This programme now requires a facility booking.'
                    : 'This programme no longer requires a facility booking.';
            }
        }
    } catch (Exception $e) {
        $error_message = $e->getMessage();
        if (($action ?? '') === 'book_programme_day') {
            $slot_open = true;
        }
    }
}

$current_month = (int) ($_GET['month'] ?? date('n'));
$current_year = (int) ($_GET['year'] ?? date('Y'));
if ($current_month < 1 || $current_month > 12) {
    $current_month = (int) date('n');
}
$start_date = date('Y-m-01', mktime(0, 0, 0, $current_month, 1, $current_year));
$end_date = date('Y-m-t', mktime(0, 0, 0, $current_month, 1, $current_year));

$stmt = $pdo->prepare("
    SELECT b.*, p.title as programme_title, p.owner_user_id, p.programme_type
    FROM bookings b
    LEFT JOIN programmes p ON b.programme_id = p.id
    WHERE b.booking_date BETWEEN ? AND ?
    AND b.status IN ('pending', 'confirmed')
    ORDER BY b.booking_date, b.start_time
");
$stmt->execute([$start_date, $end_date]);
$calendar_bookings = $stmt->fetchAll();
$bookings_by_date = [];
foreach ($calendar_bookings as $booking) {
    $bookings_by_date[$booking['booking_date']][] = $booking;
}

$programme_stmt = $pdo->prepare("
    SELECT id, title, programme_type, owner_user_id, start_date, end_date, start_time, end_time
    FROM programmes
    WHERE start_date IS NOT NULL
      AND start_date <> '0000-00-00'
      AND start_date <= ?
      AND COALESCE(NULLIF(end_date, '0000-00-00'), start_date) >= ?
    ORDER BY start_time, title
");
$programme_stmt->execute([$end_date, $start_date]);
$programmes_by_date = [];
foreach ($programme_stmt->fetchAll() as $programme) {
    $range_end = $programme['end_date'];
    if (!$range_end || $range_end === '0000-00-00' || $range_end < $programme['start_date']) {
        $range_end = $programme['start_date'];
    }
    $cursor = max($programme['start_date'], $start_date);
    $last = min($range_end, $end_date);
    $day = strtotime($cursor);
    $last_ts = strtotime($last);
    while ($day !== false && $day <= $last_ts) {
        $programmes_by_date[date('Y-m-d', $day)][] = $programme;
        $day = strtotime('+1 day', $day);
    }
}

$prev_month = $current_month == 1 ? 12 : $current_month - 1;
$prev_year = $current_month == 1 ? $current_year - 1 : $current_year;
$next_month = $current_month == 12 ? 1 : $current_month + 1;
$next_year = $current_month == 12 ? $current_year + 1 : $current_year;
$first_day = mktime(0, 0, 0, $current_month, 1, $current_year);
$days_in_month = (int) date('t', $first_day);
$first_day_of_week = (int) date('w', $first_day);
$month_names = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
];

$status_filter = $_GET['status'] ?? '';
$facility_filter = $_GET['facility'] ?? '';
$search = $_GET['search'] ?? '';
$where = [];
$params = [];
if ($status_filter !== '') {
    $where[] = 'b.status = ?';
    $params[] = $status_filter;
}
if ($facility_filter !== '') {
    $where[] = 'b.facility_type = ?';
    $params[] = $facility_filter;
}
if ($search !== '') {
    $where[] = '(b.customer_name LIKE ? OR b.customer_email LIKE ? OR b.company LIKE ? OR p.title LIKE ?)';
    $like = '%' . $search . '%';
    $params = array_merge($params, [$like, $like, $like, $like]);
}
$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
$list_stmt = $pdo->prepare("
    SELECT b.*, p.title as programme_title, p.owner_user_id
    FROM bookings b
    LEFT JOIN programmes p ON b.programme_id = p.id
    $where_sql
    ORDER BY b.booking_date DESC, b.start_time ASC
");
$list_stmt->execute($params);
$booking_list = $list_stmt->fetchAll();

$programme_facilities = $facilityService->getAllProgrammeFacilityBookings();
$facility_types = [
    'conference_room' => 'Conference Room',
    'training_room' => 'Training Room',
    'breakout_area' => 'Breakout Area'
];
$status_options = [
    'pending' => 'Pending',
    'confirmed' => 'Confirmed',
    'cancelled' => 'Cancelled',
    'completed' => 'Completed'
];
$keep = 'month=' . $current_month . '&year=' . $current_year;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facilities - XpressPoint Admin</title>
    <link rel="icon" type="image/x-icon" href="../favicon.ico">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="admin-styles.css?v=20261008-1">
    <link rel="stylesheet" href="css/facility-calendar.css?v=20261012-2">
    <link rel="stylesheet" href="css/bookings.css?v=20261008-1">
    <link rel="stylesheet" href="css/programme-facility-management.css?v=20261008-1">
</head>
<body data-motion="admin">
    <?php include_once 'includes/header.php'; renderAdminHeader('Facilities', 'fas fa-building'); ?>
    <div class="admin-container">
        <?php include 'includes/navigation.php'; renderAdminNavigation('facilities'); ?>

        <?php if ($error_message): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
        <?php if ($success_message): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>

        <div class="calendar-header">
            <h2 class="calendar-title"><i class="fas fa-calendar-alt"></i> Calendar</h2>
            <div class="calendar-nav">
                <a href="?month=<?php echo $prev_month; ?>&year=<?php echo $prev_year; ?>" class="nav-btn"><i class="fas fa-chevron-left"></i> Previous</a>
                <div class="current-month"><?php echo $month_names[$current_month] . ' ' . $current_year; ?></div>
                <a href="?month=<?php echo $next_month; ?>&year=<?php echo $next_year; ?>" class="nav-btn">Next <i class="fas fa-chevron-right"></i></a>
            </div>
        </div>
        <div class="calendar-legend">
            <span class="legend-swatch programme-webinar">Webinar</span>
            <span class="legend-swatch programme-training">Training Programme</span>
            <span class="legend-note"><?php echo auth_is_trainer() ? 'Click an open day to book. Click your own booking to change or cancel it.' : 'A booked day is reserved. No other programme or room booking can use it.'; ?></span>
        </div>

        <div class="calendar-grid">
            <div class="calendar-weekdays">
                <div class="weekday">Sunday</div><div class="weekday">Monday</div><div class="weekday">Tuesday</div>
                <div class="weekday">Wednesday</div><div class="weekday">Thursday</div><div class="weekday">Friday</div><div class="weekday">Saturday</div>
            </div>
            <div class="calendar-days">
                <?php
                for ($i = 0; $i < $first_day_of_week; $i++) {
                    echo '<div class="calendar-day other-month"><div class="day-number"></div></div>';
                }
                for ($day = 1; $day <= $days_in_month; $day++) {
                    $current_date = sprintf('%04d-%02d-%02d', $current_year, $current_month, $day);
                    $day_bookings = $bookings_by_date[$current_date] ?? [];
                    $day_programmes = $programmes_by_date[$current_date] ?? [];
                    $own_programme = null;
                    $day_blocked = false;
                    foreach ($day_programmes as $programme) {
                        if (auth_is_trainer() && (int) ($programme['owner_user_id'] ?? 0) === (int) ($actor['id'] ?? 0)) {
                            $own_programme = $programme;
                        } else {
                            $day_blocked = true;
                        }
                    }
                    foreach ($day_bookings as $booking) {
                        $booking_owner = (int) ($booking['owner_user_id'] ?? 0);
                        $is_own_booking = auth_is_trainer() && !empty($booking['programme_id']) && $booking_owner === (int) ($actor['id'] ?? 0);
                        if (!$is_own_booking && (empty($booking['programme_id']) || $booking_owner !== (int) ($actor['id'] ?? 0))) {
                            $day_blocked = true;
                        }
                    }
                    $slot = '';
                    if (auth_is_trainer() && $own_programme) {
                        $slot = 'edit';
                    } elseif (auth_is_trainer() && !$day_blocked && $current_date >= date('Y-m-d')) {
                        $slot = 'book';
                    }
                    $day_class = trim(($current_date === date('Y-m-d') ? 'today' : '') . ($slot !== '' ? ' day-slot' : ''));
                    $slot_attrs = '';
                    if ($slot === 'book') {
                        $slot_attrs = ' data-slot="book" data-date="' . $current_date . '" data-start-date="' . $current_date . '" data-end-date="' . $current_date . '"';
                    } elseif ($slot === 'edit') {
                        $own_end = $own_programme['end_date'] ?? '';
                        if (!$own_end || $own_end === '0000-00-00' || $own_end < $own_programme['start_date']) {
                            $own_end = $own_programme['start_date'];
                        }
                        $slot_attrs = ' data-slot="edit"'
                            . ' data-date="' . htmlspecialchars($current_date) . '"'
                            . ' data-programme-id="' . (int) $own_programme['id'] . '"'
                            . ' data-type="' . htmlspecialchars(auth_normalize_programme_type($own_programme['programme_type'] ?? 'training')) . '"'
                            . ' data-start-date="' . htmlspecialchars(substr((string) $own_programme['start_date'], 0, 10)) . '"'
                            . ' data-end-date="' . htmlspecialchars(substr((string) $own_end, 0, 10)) . '"'
                            . ' data-start-time="' . htmlspecialchars(facilities_clock($own_programme['start_time'] ?? '')) . '"'
                            . ' data-end-time="' . htmlspecialchars(facilities_clock($own_programme['end_time'] ?? '')) . '"';
                    }
                    echo '<div class="calendar-day ' . $day_class . '"' . $slot_attrs . '><div class="day-number">' . $day . '</div>';
                    $shown_programmes = [];
                    if ($day_bookings || $day_programmes) {
                        echo '<div class="day-bookings">';
                        foreach ($day_bookings as $booking) {
                            if (!empty($booking['programme_id'])) {
                                $shown_programmes[(int) $booking['programme_id']] = true;
                                echo facilities_calendar_programme([
                                    'id' => $booking['programme_id'],
                                    'title' => $booking['programme_title'] ?: $booking['customer_name'],
                                    'programme_type' => $booking['programme_type'] ?? 'training',
                                    'owner_user_id' => $booking['owner_user_id'] ?? null,
                                    'start_time' => $booking['start_time'],
                                    'end_time' => $booking['end_time'],
                                ], $actor);
                                continue;
                            }
                            $title = $booking['customer_name'];
                            $time_range = date('g:i A', strtotime($booking['start_time'])) . ' - ' . date('g:i A', strtotime($booking['end_time']));
                            $facility_class = htmlspecialchars($booking['facility_type']);
                            echo '<a href="booking-details.php?id=' . (int) $booking['id'] . '" class="booking-item ' . $facility_class . '">';
                            echo '<span class="booking-name">' . htmlspecialchars($title) . '</span>';
                            echo '<span class="booking-time">' . htmlspecialchars($time_range) . '</span>';
                            echo '</a>';
                        }
                        foreach ($day_programmes as $programme) {
                            if (isset($shown_programmes[(int) $programme['id']])) {
                                continue;
                            }
                            echo facilities_calendar_programme($programme, $actor);
                        }
                        echo '</div>';
                    }
                    echo '</div>';
                }
                ?>
            </div>
        </div>

        <h2 style="margin: 28px 0 12px; font-size: 1.15rem;">Bookings</h2>
        <div class="filters">
            <form method="GET">
                <input type="hidden" name="month" value="<?php echo $current_month; ?>">
                <input type="hidden" name="year" value="<?php echo $current_year; ?>">
                <div class="filter-group">
                    <label for="search">Search</label>
                    <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Name, email, company, or programme">
                </div>
                <div class="filter-group">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="">All statuses</option>
                        <?php foreach ($status_options as $value => $label): ?>
                            <option value="<?php echo $value; ?>" <?php echo $status_filter === $value ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="facility">Facility</label>
                    <select id="facility" name="facility">
                        <option value="">All facilities</option>
                        <?php foreach ($facility_types as $value => $label): ?>
                            <option value="<?php echo $value; ?>" <?php echo $facility_filter === $value ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-actions">
                    <button type="submit" class="btn-filter"><i class="fas fa-search"></i> Filter</button>
                    <a href="facilities.php?<?php echo $keep; ?>" class="btn-clear">Clear</a>
                </div>
            </form>
        </div>

        <div style="overflow-x:auto;">
            <table class="record-table">
                <thead>
                    <tr>
                        <th>Who</th>
                        <th>Room</th>
                        <th>When</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($booking_list)): ?>
                    <tr><td colspan="5">No bookings match these filters.</td></tr>
                <?php else: foreach ($booking_list as $booking):
                    $canChange = auth_can_change_booking($actor, $booking['owner_user_id'] ?? null, $booking['programme_id'] ?? null);
                    $who = $booking['programme_title'] ?: $booking['customer_name'];
                ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($who); ?></strong>
                            <div><?php echo htmlspecialchars($booking['customer_email'] ?: ''); ?></div>
                        </td>
                        <td><?php echo htmlspecialchars($facility_types[$booking['facility_type']] ?? $booking['facility_type']); ?></td>
                        <td>
                            <?php echo date('M j, Y', strtotime($booking['booking_date'])); ?><br>
                            <?php echo date('g:i A', strtotime($booking['start_time'])); ?> – <?php echo date('g:i A', strtotime($booking['end_time'])); ?>
                        </td>
                        <td><span class="booking-status status-<?php echo htmlspecialchars($booking['status']); ?>"><?php echo htmlspecialchars(ucfirst($booking['status'])); ?></span></td>
                        <td class="record-actions">
                            <a href="booking-details.php?id=<?php echo (int) $booking['id']; ?>" class="btn btn-sm btn-secondary">View</a>
                            <?php if ($canChange && $booking['status'] === 'pending'): ?>
                                <button type="button" class="btn btn-sm btn-success" onclick="confirmBooking(<?php echo (int) $booking['id']; ?>)">Confirm</button>
                                <button type="button" class="btn btn-sm btn-danger" onclick="cancelBooking(<?php echo (int) $booking['id']; ?>)">Cancel</button>
                            <?php elseif ($canChange && $booking['status'] === 'confirmed'): ?>
                                <button type="button" class="btn btn-sm btn-primary" onclick="completeBooking(<?php echo (int) $booking['id']; ?>)">Complete</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <?php if (!auth_is_trainer()): ?>
        <h2 style="margin: 28px 0 12px; font-size: 1.15rem;">Programme facilities</h2>
        <div style="overflow-x:auto;">
            <table class="record-table">
                <thead>
                    <tr>
                        <th>Programme</th>
                        <th>Room</th>
                        <th>When</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($programme_facilities)): ?>
                    <tr><td colspan="5">No programmes currently require a facility.</td></tr>
                <?php else: foreach ($programme_facilities as $programme):
                    $canChange = auth_can_change_booking($actor, $programme['owner_user_id'] ?? null, $programme['programme_id']);
                ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($programme['title']); ?></strong></td>
                        <td><?php echo $programme['booking_id'] ? htmlspecialchars($facility_types[$programme['facility_type']] ?? $programme['facility_type']) : '—'; ?></td>
                        <td>
                            <?php if ($programme['booking_id']): ?>
                                <?php echo date('M j, Y', strtotime($programme['booking_date'])); ?><br>
                                <?php echo date('g:i A', strtotime($programme['start_time'])); ?> – <?php echo date('g:i A', strtotime($programme['end_time'])); ?>
                            <?php else: ?>
                                Not booked
                            <?php endif; ?>
                        </td>
                        <td><?php echo $programme['booking_id'] ? htmlspecialchars(ucfirst($programme['status'])) : 'Needs a room'; ?></td>
                        <td class="record-actions">
                            <?php if ($canChange && $programme['booking_id']): ?>
                                <button type="button" class="btn btn-sm btn-primary" onclick="editFacilityBooking(<?php echo (int) $programme['programme_id']; ?>)">Edit</button>
                                <button type="button" class="btn btn-sm btn-danger" onclick="deleteFacilityBooking(<?php echo (int) $programme['programme_id']; ?>)">Delete</button>
                            <?php elseif ($canChange): ?>
                                <button type="button" class="btn btn-sm btn-success" onclick="createFacilityBooking(<?php echo (int) $programme['programme_id']; ?>)">Create</button>
                            <?php endif; ?>
                            <?php if ($canChange): ?>
                                <button type="button" class="btn btn-sm btn-secondary" onclick="toggleFacilityRequirement(<?php echo (int) $programme['programme_id']; ?>, <?php echo $programme['requires_facility'] ? 'false' : 'true'; ?>)">
                                    <?php echo $programme['requires_facility'] ? 'Stop requiring' : 'Require room'; ?>
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

    <div id="cancelModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Cancel booking</h3>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="cancel">
                <input type="hidden" name="booking_id" id="cancelBookingId">
                <div class="form-group">
                    <label for="cancellation_reason">Cancellation reason</label>
                    <textarea name="cancellation_reason" id="cancellation_reason" rows="3"></textarea>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-danger">Cancel booking</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Close</button>
                </div>
            </form>
        </div>
    </div>

    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit facility booking</h3>
                <button class="close-modal" type="button" onclick="closeEditModal()">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="update_facility_booking">
                <input type="hidden" name="programme_id" id="editProgrammeId">
                <div class="form-group">
                    <label for="editFacilityType">Facility</label>
                    <select id="editFacilityType" name="facility_type" required>
                        <?php foreach ($facility_types as $value => $label): ?>
                            <option value="<?php echo $value; ?>"><?php echo htmlspecialchars($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="editBookingDate">Date</label>
                    <input type="date" id="editBookingDate" name="booking_date" required>
                </div>
                <div class="form-group">
                    <label for="editStartTime">Start</label>
                    <input type="time" id="editStartTime" name="start_time" required>
                </div>
                <div class="form-group">
                    <label for="editEndTime">End</label>
                    <input type="time" id="editEndTime" name="end_time" required>
                </div>
                <button type="submit" class="btn btn-primary">Update booking</button>
            </form>
        </div>
    </div>

    <?php if (auth_is_trainer()): ?>
    <div id="slotPanel" class="slot-panel" <?php echo $slot_open ? '' : 'hidden'; ?>>
        <div class="slot-card" role="dialog" aria-modal="true" aria-labelledby="slotDateLabel">
            <button type="button" class="slot-close" id="slotClose" aria-label="Close">&times;</button>
            <p class="slot-kicker" id="slotKicker"><?php echo $book_form['programme_id'] ? 'Your booking' : 'Open day'; ?></p>
            <h2 id="slotDateLabel"><?php echo $book_form['start_date'] !== '' ? date('l, F j, Y', strtotime($book_form['start_date'])) : 'Choose a day'; ?></h2>
            <form method="POST" id="slotForm" class="slot-form">
                <input type="hidden" name="action" value="book_programme_day">
                <input type="hidden" name="programme_id" id="slotProgrammeId" value="<?php echo (int) $book_form['programme_id']; ?>">
                <input type="hidden" name="start_date" id="slotStartDate" value="<?php echo htmlspecialchars($book_form['start_date']); ?>">
                <label>
                    Type
                    <select name="programme_type" id="slotType">
                        <option value="training" <?php echo $book_form['programme_type'] === 'training' ? 'selected' : ''; ?>>Training Programme</option>
                        <option value="webinar" <?php echo $book_form['programme_type'] === 'webinar' ? 'selected' : ''; ?>>Webinar</option>
                    </select>
                </label>
                <div class="slot-times">
                    <label>
                        Start time
                        <input type="time" name="start_time" id="slotStartTime" required value="<?php echo htmlspecialchars($book_form['start_time']); ?>">
                    </label>
                    <label>
                        End time
                        <input type="time" name="end_time" id="slotEndTime" required value="<?php echo htmlspecialchars($book_form['end_time']); ?>">
                    </label>
                </div>
                <label>
                    End date
                    <input type="date" name="end_date" id="slotEndDate" required value="<?php echo htmlspecialchars($book_form['end_date']); ?>">
                </label>
                <button type="submit" class="btn btn-primary" id="slotSave"><?php echo $book_form['programme_id'] ? 'Save changes' : 'Reserve this day'; ?></button>
            </form>
            <form method="POST" id="slotCancelForm" <?php echo $book_form['programme_id'] ? '' : 'hidden'; ?>>
                <input type="hidden" name="action" value="cancel_trainer_booking">
                <input type="hidden" name="programme_id" id="slotCancelId" value="<?php echo (int) $book_form['programme_id']; ?>">
                <button type="submit" class="slot-cancel">Cancel booking</button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <script>
        function postAction(fields) {
            const form = document.createElement('form');
            form.method = 'POST';
            Object.keys(fields).forEach(function (name) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = name;
                input.value = fields[name];
                form.appendChild(input);
            });
            document.body.appendChild(form);
            form.submit();
        }
        function confirmBooking(id) {
            xpmConfirm({ title: 'Confirm this booking?', message: 'The status will change to confirmed.', confirmText: 'Confirm', icon: 'fas fa-check' }).then(function (ok) {
                if (ok) postAction({ action: 'confirm', booking_id: id });
            });
        }
        function cancelBooking(id) {
            document.getElementById('cancelBookingId').value = id;
            document.getElementById('cancelModal').style.display = 'block';
        }
        function completeBooking(id) {
            xpmConfirm({ title: 'Mark this booking completed?', message: 'The status will change to completed.', confirmText: 'Mark completed', icon: 'fas fa-check-double' }).then(function (ok) {
                if (ok) postAction({ action: 'complete', booking_id: id });
            });
        }
        function closeModal() { document.getElementById('cancelModal').style.display = 'none'; }
        function createFacilityBooking(id) {
            xpmConfirm({ title: 'Create facility booking?', message: 'A room booking will be created for this programme.', confirmText: 'Create', icon: 'fas fa-calendar-plus' }).then(function (ok) {
                if (ok) postAction({ action: 'create_facility_booking', programme_id: id });
            });
        }
        function editFacilityBooking(id) {
            document.getElementById('editProgrammeId').value = id;
            document.getElementById('editModal').style.display = 'block';
        }
        function closeEditModal() { document.getElementById('editModal').style.display = 'none'; }
        function deleteFacilityBooking(id) {
            xpmConfirm({ title: 'Delete facility booking?', message: 'This cannot be undone.', confirmText: 'Delete', icon: 'fas fa-trash' }).then(function (ok) {
                if (ok) postAction({ action: 'delete_facility_booking', programme_id: id });
            });
        }
        function toggleFacilityRequirement(id, requires) {
            postAction({ action: 'toggle_facility_requirement', programme_id: id, requires_facility: requires });
        }
        (function () {
            const panel = document.getElementById('slotPanel');
            if (!panel) return;
            const programmeId = document.getElementById('slotProgrammeId');
            const cancelId = document.getElementById('slotCancelId');
            const startDate = document.getElementById('slotStartDate');
            const endDate = document.getElementById('slotEndDate');
            const startTime = document.getElementById('slotStartTime');
            const endTime = document.getElementById('slotEndTime');
            const typeField = document.getElementById('slotType');
            const dateLabel = document.getElementById('slotDateLabel');
            const kicker = document.getElementById('slotKicker');
            const saveButton = document.getElementById('slotSave');
            const cancelForm = document.getElementById('slotCancelForm');

            function prettyDate(iso) {
                const parts = (iso || '').split('-').map(Number);
                if (parts.length !== 3 || !parts[0]) return 'Choose a day';
                return new Date(parts[0], parts[1] - 1, parts[2]).toLocaleDateString('en-US', {
                    weekday: 'long', month: 'long', day: 'numeric', year: 'numeric'
                });
            }

            function openSlot(day) {
                const editing = day.dataset.slot === 'edit';
                programmeId.value = day.dataset.programmeId || '';
                cancelId.value = day.dataset.programmeId || '';
                startDate.value = day.dataset.startDate || day.dataset.date || '';
                endDate.value = day.dataset.endDate || day.dataset.date || '';
                startTime.value = day.dataset.startTime || '09:00';
                endTime.value = day.dataset.endTime || '17:00';
                typeField.value = day.dataset.type || 'training';
                dateLabel.textContent = prettyDate(startDate.value);
                kicker.textContent = editing ? 'Your booking' : 'Open day';
                saveButton.textContent = editing ? 'Save changes' : 'Reserve this day';
                cancelForm.hidden = !editing;
                panel.hidden = false;
            }

            document.querySelectorAll('.calendar-day[data-slot]').forEach(function (day) {
                day.addEventListener('click', function (event) {
                    if (event.target.closest('a')) return;
                    openSlot(day);
                });
            });
            document.getElementById('slotClose').addEventListener('click', function () {
                panel.hidden = true;
            });
            panel.addEventListener('click', function (event) {
                if (event.target === panel) panel.hidden = true;
            });
        })();
    </script>
    <script src="../assets/js/motion.js?v=20261002-10"></script>
</body>
</html>
