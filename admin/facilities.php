<?php
require_once '../includes/auth.php';
auth_require(['admin', 'manager', 'trainer']);
csrf_guard();

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

function facilities_room_label($type) {
    $map = [
        'conference_room' => 'Conference Room',
        'training_room' => 'Training Room',
        'breakout_area' => 'Breakout Area',
    ];
    return $map[$type] ?? (string) $type;
}

function facilities_event_button($class, $aria, $html, $event_id) {
    return '<button type="button" class="' . $class . '" aria-label="' . htmlspecialchars($aria) . '" title="' . htmlspecialchars($aria) . '" data-event="' . htmlspecialchars($event_id) . '">' . $html . '</button>';
}

function facilities_booking_payload(array $booking, array $actor) {
    $room = facilities_room_label($booking['facility_type'] ?? '');
    $time = '';
    if (!empty($booking['start_time'])) {
        $time = date('g:i A', strtotime($booking['start_time']));
        if (!empty($booking['end_time'])) {
            $time .= ' – ' . date('g:i A', strtotime($booking['end_time']));
        }
    }
    return [
        'kind' => 'booking',
        'id' => (int) ($booking['id'] ?? 0),
        'name' => (string) ($booking['customer_name'] ?? ''),
        'email' => (string) ($booking['customer_email'] ?? ''),
        'phone' => (string) ($booking['customer_phone'] ?? ''),
        'company' => (string) ($booking['company'] ?? ''),
        'room' => $room,
        'date' => (string) ($booking['booking_date'] ?? ''),
        'date_label' => !empty($booking['booking_date']) ? date('l, F j, Y', strtotime($booking['booking_date'])) : '',
        'time' => $time,
        'status' => (string) ($booking['status'] ?? ''),
        'purpose' => (string) ($booking['purpose'] ?? ''),
        'attendees' => (int) ($booking['attendees_count'] ?? 0),
        'programme_title' => (string) ($booking['programme_title'] ?? ''),
        'can_change' => auth_can_change_booking($actor, $booking['owner_user_id'] ?? null, $booking['programme_id'] ?? null) ? 1 : 0,
    ];
}

function facilities_calendar_programme(array $programme, array $actor, $date = '') {
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
    $aria = trim(($date ? date('F j, Y', strtotime($date)) . ', ' : '') . ($programme['title'] ?? '') . ', ' . $label);
    return facilities_event_button($class, $aria, $html, 'programme:' . $programme_id);
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
        $error_message = $e instanceof PDOException ? 'The booking could not be saved.' : $e->getMessage();
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
$calendar_view = (($_GET['view'] ?? 'month') === 'week') ? 'week' : 'month';
$month_start = date('Y-m-01', mktime(0, 0, 0, $current_month, 1, $current_year));
$month_end = date('Y-m-t', mktime(0, 0, 0, $current_month, 1, $current_year));
$week_param = $_GET['week'] ?? '';
$week_start = '';
$week_end = '';
if ($calendar_view === 'week') {
    $anchor = $week_param;
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $anchor)) {
        $in_this_month = ((int) date('n') === $current_month && (int) date('Y') === $current_year);
        $anchor = $in_this_month ? date('Y-m-d') : $month_start;
    }
    $dow = (int) date('w', strtotime($anchor . ' 12:00:00'));
    $week_start = date('Y-m-d', strtotime($anchor . ' 12:00:00 -' . $dow . ' days'));
    $week_end = date('Y-m-d', strtotime($week_start . ' 12:00:00 +6 days'));
    $start_date = $week_start;
    $end_date = $week_end;
} else {
    $start_date = $month_start;
    $end_date = $month_end;
}

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
$keep = 'month=' . $current_month . '&year=' . $current_year . '&view=' . $calendar_view;
if ($calendar_view === 'week' && $week_start !== '') {
    $keep .= '&week=' . rawurlencode($week_start);
}
$today_keep = 'view=' . $calendar_view . '&month=' . (int) date('n') . '&year=' . (int) date('Y');
if ($calendar_view === 'week') {
    $today_keep .= '&week=' . date('Y-m-d');
}
$month_toggle = 'view=month&month=' . $current_month . '&year=' . $current_year;
$week_toggle_anchor = $week_start !== '' ? $week_start : ((((int) date('n') === $current_month && (int) date('Y') === $current_year) ? date('Y-m-d') : $month_start));
$week_toggle = 'view=week&month=' . $current_month . '&year=' . $current_year . '&week=' . rawurlencode($week_toggle_anchor);
$nav_prev = $calendar_view === 'week'
    ? 'view=week&week=' . date('Y-m-d', strtotime($week_start . ' -7 days')) . '&month=' . (int) date('n', strtotime($week_start . ' -7 days')) . '&year=' . (int) date('Y', strtotime($week_start . ' -7 days'))
    : 'view=month&month=' . $prev_month . '&year=' . $prev_year;
$nav_next = $calendar_view === 'week'
    ? 'view=week&week=' . date('Y-m-d', strtotime($week_start . ' +7 days')) . '&month=' . (int) date('n', strtotime($week_start . ' +7 days')) . '&year=' . (int) date('Y', strtotime($week_start . ' +7 days'))
    : 'view=month&month=' . $next_month . '&year=' . $next_year;
$current_label = $calendar_view === 'week'
    ? date('M j', strtotime($week_start)) . ' – ' . date('M j, Y', strtotime($week_end))
    : $month_names[$current_month] . ' ' . $current_year;
$calendar_payloads = [];
$agenda_html = '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facilities - XpressPoint Admin</title>
    <link rel="icon" type="image/x-icon" href="../favicon.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <?php include_once __DIR__ . '/includes/theme-boot.php'; ?>
    <link rel="stylesheet" href="<?php echo admin_asset('admin.min.css'); ?>">
    <link rel="stylesheet" href="<?php echo admin_asset('css/facility-calendar.css'); ?>">
    <link rel="stylesheet" href="<?php echo admin_asset('css/bookings.css'); ?>">
    <link rel="stylesheet" href="<?php echo admin_asset('css/programme-facility-management.css'); ?>">
</head>
<body data-motion="admin">
    <?php include_once 'includes/header.php'; renderAdminHeader('Facilities', 'fas fa-building'); ?>
    <div class="admin-container">
        <?php include 'includes/navigation.php'; renderAdminNavigation('facilities'); ?>

        <?php if ($error_message): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-triangle" aria-hidden="true"></i> <?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
        <?php if ($success_message): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle" aria-hidden="true"></i> <?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>

        <div class="calendar-header">
            <h2 class="calendar-title"><i class="fas fa-calendar-alt" aria-hidden="true"></i> Calendar</h2>
            <div class="calendar-nav">
                <div class="view-toggle" role="group" aria-label="Calendar view">
                    <a href="?<?php echo htmlspecialchars($month_toggle); ?>"<?php echo $calendar_view === 'month' ? ' aria-current="true"' : ''; ?>>Month</a>
                    <a href="?<?php echo htmlspecialchars($week_toggle); ?>"<?php echo $calendar_view === 'week' ? ' aria-current="true"' : ''; ?>>Week</a>
                </div>
                <a href="?<?php echo htmlspecialchars($nav_prev); ?>" class="nav-btn"><i class="fas fa-chevron-left" aria-hidden="true"></i> Previous</a>
                <div class="current-month"><?php echo htmlspecialchars($current_label); ?></div>
                <a href="?<?php echo htmlspecialchars($nav_next); ?>" class="nav-btn">Next <i class="fas fa-chevron-right" aria-hidden="true"></i></a>
                <a href="?<?php echo htmlspecialchars($today_keep); ?>" class="today-btn">Today</a>
            </div>
        </div>
        <div class="calendar-legend" aria-label="Calendar legend">
            <span class="legend-swatch status-pending">Pending</span>
            <span class="legend-swatch status-confirmed">Confirmed</span>
            <span class="legend-swatch status-cancelled">Cancelled</span>
            <span class="legend-swatch status-completed">Completed</span>
            <span class="legend-swatch programme-webinar">Webinar</span>
            <span class="legend-swatch programme-training">Training</span>
            <span class="legend-swatch conference_room">Conference room</span>
            <span class="legend-swatch training_room">Training room</span>
            <span class="legend-swatch breakout_area">Breakout area</span>
            <span class="legend-note"><?php echo auth_is_trainer() ? 'Click an open day to book. Click your own booking to change or cancel it.' : 'Open a booking from the calendar. A reserved day cannot be double-booked.'; ?></span>
        </div>

        <div class="calendar-grid">
            <div class="calendar-weekdays">
                <div class="weekday">Sunday</div><div class="weekday">Monday</div><div class="weekday">Tuesday</div>
                <div class="weekday">Wednesday</div><div class="weekday">Thursday</div><div class="weekday">Friday</div><div class="weekday">Saturday</div>
            </div>
            <div class="calendar-days">
                <?php
                $calendar_payloads = [];
                $agenda_html = '';
                $render_day = function ($current_date, $number_label, $extra_class = '') use ($bookings_by_date, $programmes_by_date, $actor, &$calendar_payloads, &$agenda_html) {
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
                    $day_class = trim($extra_class . ' ' . ($current_date === date('Y-m-d') ? 'today' : '') . ($slot !== '' ? ' day-slot' : ''));
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
                    $events = '';
                    $shown_programmes = [];
                    if ($day_bookings || $day_programmes) {
                        $events .= '<div class="day-bookings">';
                        foreach ($day_bookings as $booking) {
                            if (!empty($booking['programme_id'])) {
                                $shown_programmes[(int) $booking['programme_id']] = true;
                                $events .= facilities_calendar_programme([
                                    'id' => $booking['programme_id'],
                                    'title' => $booking['programme_title'] ?: $booking['customer_name'],
                                    'programme_type' => $booking['programme_type'] ?? 'training',
                                    'owner_user_id' => $booking['owner_user_id'] ?? null,
                                    'start_time' => $booking['start_time'],
                                    'end_time' => $booking['end_time'],
                                ], $actor, $current_date);
                                $pid = (int) $booking['programme_id'];
                                $calendar_payloads['programme:' . $pid] = [
                                    'kind' => 'programme',
                                    'id' => $pid,
                                    'title' => (string) ($booking['programme_title'] ?: $booking['customer_name']),
                                    'type' => auth_programme_type_label(auth_normalize_programme_type($booking['programme_type'] ?? 'training')),
                                    'date' => $current_date,
                                    'time' => date('g:i A', strtotime($booking['start_time'])) . (!empty($booking['end_time']) ? ' – ' . date('g:i A', strtotime($booking['end_time'])) : ''),
                                    'can_edit' => (!auth_is_trainer() && auth_can_change_booking($actor, $booking['owner_user_id'] ?? null, $pid)) ? 1 : 0,
                                ];
                                continue;
                            }
                            $title = $booking['customer_name'];
                            $time_range = date('g:i A', strtotime($booking['start_time'])) . ' - ' . date('g:i A', strtotime($booking['end_time']));
                            $status = (string) ($booking['status'] ?? 'pending');
                            $room = facilities_room_label($booking['facility_type'] ?? '');
                            $aria = date('F j, Y', strtotime($current_date)) . ', ' . $title . ', ' . $room . ', ' . ucfirst($status);
                            $class = 'booking-item ' . htmlspecialchars($booking['facility_type']) . ' status-' . htmlspecialchars($status);
                            $html = '<span class="booking-name">' . htmlspecialchars($title) . '</span>';
                            $html .= '<span class="booking-status-text">' . htmlspecialchars(ucfirst($status)) . '</span>';
                            $html .= '<span class="booking-time">' . htmlspecialchars($time_range) . '</span>';
                            $events .= facilities_event_button($class, $aria, $html, 'booking:' . (int) $booking['id']);
                            $calendar_payloads['booking:' . (int) $booking['id']] = facilities_booking_payload($booking, $actor);
                        }
                        foreach ($day_programmes as $programme) {
                            if (isset($shown_programmes[(int) $programme['id']])) {
                                continue;
                            }
                            $events .= facilities_calendar_programme($programme, $actor, $current_date);
                            $pid = (int) $programme['id'];
                            $ptime = '';
                            if (!empty($programme['start_time'])) {
                                $ptime = date('g:i A', strtotime($programme['start_time']));
                                if (!empty($programme['end_time'])) {
                                    $ptime .= ' – ' . date('g:i A', strtotime($programme['end_time']));
                                }
                            }
                            $calendar_payloads['programme:' . $pid] = [
                                'kind' => 'programme',
                                'id' => $pid,
                                'title' => (string) ($programme['title'] ?? ''),
                                'type' => auth_programme_type_label(auth_normalize_programme_type($programme['programme_type'] ?? 'training')),
                                'date' => $current_date,
                                'time' => $ptime,
                                'can_edit' => (!auth_is_trainer() && auth_can_change_booking($actor, $programme['owner_user_id'] ?? null, $pid)) ? 1 : 0,
                            ];
                        }
                        $events .= '</div>';
                    }
                    if ($events !== '' || $current_date === date('Y-m-d')) {
                        $agenda_html .= '<article class="agenda-day' . ($current_date === date('Y-m-d') ? ' today' : '') . '"' . $slot_attrs . '>';
                        $agenda_html .= '<h3 class="agenda-day-label">' . htmlspecialchars(date('l, F j', strtotime($current_date))) . '</h3>';
                        $agenda_html .= $events !== '' ? $events : '<p class="legend-note">No bookings on this day.</p>';
                        $agenda_html .= '</article>';
                    }
                    echo '<div class="calendar-day ' . $day_class . '"' . $slot_attrs . '><div class="day-number">' . $number_label . '</div>' . $events . '</div>';
                };

                if ($calendar_view === 'week') {
                    $cursor = strtotime($week_start . ' 12:00:00');
                    for ($i = 0; $i < 7; $i++) {
                        $current_date = date('Y-m-d', strtotime('+' . $i . ' day', $cursor));
                        $in_month = ((int) date('n', strtotime($current_date)) === $current_month) ? '' : 'other-month';
                        $render_day($current_date, date('j', strtotime($current_date)), trim('is-week ' . $in_month));
                    }
                } else {
                    for ($i = 0; $i < $first_day_of_week; $i++) {
                        echo '<div class="calendar-day other-month"><div class="day-number"></div></div>';
                    }
                    for ($day = 1; $day <= $days_in_month; $day++) {
                        $current_date = sprintf('%04d-%02d-%02d', $current_year, $current_month, $day);
                        $render_day($current_date, (string) $day);
                    }
                }
                foreach ($booking_list as $booking) {
                    $calendar_payloads['booking:' . (int) $booking['id']] = facilities_booking_payload($booking, $actor);
                }
                ?>
            </div>
        </div>
        <div class="calendar-agenda" aria-label="Agenda"><?php echo $agenda_html; ?></div>

        <h2 class="section-title">Bookings</h2>
        <div class="filters">
            <form method="GET">
                <input type="hidden" name="month" value="<?php echo $current_month; ?>">
                <input type="hidden" name="year" value="<?php echo $current_year; ?>">
                <input type="hidden" name="view" value="<?php echo htmlspecialchars($calendar_view); ?>">
                <?php if ($week_start !== ''): ?><input type="hidden" name="week" value="<?php echo htmlspecialchars($week_start); ?>"><?php endif; ?>
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
                    <button type="submit" class="btn-filter"><i class="fas fa-search" aria-hidden="true"></i> Filter</button>
                    <a href="facilities.php?<?php echo $keep; ?>" class="btn-clear">Clear</a>
                </div>
            </form>
        </div>

        <div class="table-wrap">
            <table class="record-table">
                <thead>
                    <tr>
                        <th scope="col">Who</th>
                        <th scope="col">Room</th>
                        <th scope="col">When</th>
                        <th scope="col">Status</th>
                        <th scope="col">Actions</th>
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
                        <td data-label="Who" title="<?php echo htmlspecialchars($who . ($booking['customer_email'] ? ' · ' . $booking['customer_email'] : '')); ?>">
                            <strong><?php echo htmlspecialchars($who); ?></strong>
                            <div><?php echo htmlspecialchars($booking['customer_email'] ?: ''); ?></div>
                        </td>
                        <td data-label="Room"><?php echo htmlspecialchars($facility_types[$booking['facility_type']] ?? $booking['facility_type']); ?></td>
                        <td data-label="When">
                            <?php echo date('M j, Y', strtotime($booking['booking_date'])); ?><br>
                            <?php echo date('g:i A', strtotime($booking['start_time'])); ?> – <?php echo date('g:i A', strtotime($booking['end_time'])); ?>
                        </td>
                        <td data-label="Status"><span class="booking-status status-<?php echo htmlspecialchars($booking['status']); ?>"><?php echo htmlspecialchars(ucfirst($booking['status'])); ?></span></td>
                        <td class="record-actions" data-label="Actions">
                            <button type="button" class="btn btn-sm btn-secondary" data-event="booking:<?php echo (int) $booking['id']; ?>">View</button>
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
        <h2 class="section-title">Programme facilities</h2>
        <div class="table-wrap">
            <table class="record-table">
                <thead>
                    <tr>
                        <th scope="col">Programme</th>
                        <th scope="col">Room</th>
                        <th scope="col">When</th>
                        <th scope="col">Status</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($programme_facilities)): ?>
                    <tr><td colspan="5">No programmes currently require a facility.</td></tr>
                <?php else: foreach ($programme_facilities as $programme):
                    $canChange = auth_can_change_booking($actor, $programme['owner_user_id'] ?? null, $programme['programme_id']);
                ?>
                    <tr>
                        <td data-label="Programme" title="<?php echo htmlspecialchars($programme['title']); ?>"><strong><?php echo htmlspecialchars($programme['title']); ?></strong></td>
                        <td data-label="Room"><?php echo $programme['booking_id'] ? htmlspecialchars($facility_types[$programme['facility_type']] ?? $programme['facility_type']) : '—'; ?></td>
                        <td data-label="When">
                            <?php if ($programme['booking_id']): ?>
                                <?php echo date('M j, Y', strtotime($programme['booking_date'])); ?><br>
                                <?php echo date('g:i A', strtotime($programme['start_time'])); ?> – <?php echo date('g:i A', strtotime($programme['end_time'])); ?>
                            <?php else: ?>
                                Not booked
                            <?php endif; ?>
                        </td>
                        <td data-label="Status"><?php echo $programme['booking_id'] ? htmlspecialchars(ucfirst($programme['status'])) : 'Needs a room'; ?></td>
                        <td class="record-actions" data-label="Actions">
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

    <div id="cancelModal" class="modal" hidden>
        <div class="modal-content" role="dialog" aria-modal="true" aria-labelledby="cancelModalTitle">
            <div class="modal-header">
                <h3 id="cancelModalTitle">Cancel booking</h3>
                <button type="button" class="close" onclick="closeModal()" aria-label="Close cancel booking dialog">&times;</button>
            </div>
            <form method="POST">
                <?php echo csrf_field(); ?>
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

    <div id="editModal" class="modal" hidden>
        <div class="modal-content" role="dialog" aria-modal="true" aria-labelledby="editModalTitle">
            <div class="modal-header">
                <h3 id="editModalTitle">Edit facility booking</h3>
                <button class="close-modal" type="button" onclick="closeEditModal()" aria-label="Close edit booking dialog">&times;</button>
            </div>
            <form method="POST">
                <?php echo csrf_field(); ?>
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

    <aside id="bookingPanel" class="booking-panel" hidden>
        <div class="booking-panel-card" role="dialog" aria-modal="true" aria-labelledby="bookingPanelTitle">
            <button type="button" class="booking-panel-close" id="bookingPanelClose" aria-label="Close booking details">&times;</button>
            <p class="panel-kicker" id="bookingPanelKicker">Booking</p>
            <h2 id="bookingPanelTitle">Booking</h2>
            <div id="bookingPanelBody"></div>
        </div>
    </aside>
    <script type="application/json" id="calendar-events-data"><?php echo json_encode($calendar_payloads, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_UNESCAPED_UNICODE); ?></script>

    <?php if (auth_is_trainer()): ?>
    <div id="slotPanel" class="slot-panel" <?php echo $slot_open ? '' : 'hidden'; ?>>
        <div class="slot-card" role="dialog" aria-modal="true" aria-labelledby="slotDateLabel">
            <button type="button" class="slot-close" id="slotClose" aria-label="Close">&times;</button>
            <p class="slot-kicker" id="slotKicker"><?php echo $book_form['programme_id'] ? 'Your booking' : 'Open day'; ?></p>
            <h2 id="slotDateLabel"><?php echo $book_form['start_date'] !== '' ? date('l, F j, Y', strtotime($book_form['start_date'])) : 'Choose a day'; ?></h2>
            <form method="POST" id="slotForm" class="slot-form">
                <?php echo csrf_field(); ?>
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
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="cancel_trainer_booking">
                <input type="hidden" name="programme_id" id="slotCancelId" value="<?php echo (int) $book_form['programme_id']; ?>">
                <button type="submit" class="slot-cancel">Cancel booking</button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <script>
        (function () {
            var source = document.getElementById('calendar-events-data');
            var panel = document.getElementById('bookingPanel');
            var title = document.getElementById('bookingPanelTitle');
            var kicker = document.getElementById('bookingPanelKicker');
            var body = document.getElementById('bookingPanelBody');
            var panelRelease = null;
            var events = {};
            try { events = JSON.parse(source ? source.textContent : '{}') || {}; } catch (err) { events = {}; }

            function closePanel() {
                if (panelRelease) {
                    var release = panelRelease;
                    panelRelease = null;
                    release(false);
                }
                if (panel) panel.hidden = true;
            }

            function openEvent(id) {
                var item = events[id];
                if (!item || !panel) return;
                if (item.kind === 'programme') {
                    kicker.textContent = item.type || 'Programme';
                    title.textContent = item.title || 'Programme';
                    var html = '<div class="panel-kv">';
                    if (item.date) html += '<div><span class="kv-label">Date</span><span>' + item.date + '</span></div>';
                    if (item.time) html += '<div><span class="kv-label">Time</span><span>' + item.time + '</span></div>';
                    html += '</div><div class="panel-actions">';
                    if (item.can_edit) html += '<a class="btn btn-primary" href="edit-programme.php?id=' + item.id + '">Edit programme</a>';
                    html += '</div>';
                    body.innerHTML = html;
                } else {
                    kicker.textContent = (item.status || 'Booking');
                    title.textContent = item.programme_title || item.name || 'Booking';
                    var rows = [
                        ['Who', item.name],
                        ['Email', item.email],
                        ['Phone', item.phone],
                        ['Company', item.company],
                        ['Room', item.room],
                        ['Date', item.date_label || item.date],
                        ['Time', item.time],
                        ['Status', item.status],
                        ['Purpose', item.purpose]
                    ];
                    var html = '<div class="panel-kv">';
                    rows.forEach(function (row) {
                        if (!row[1]) return;
                        html += '<div><span class="kv-label">' + row[0] + '</span><span>' + String(row[1]).replace(/&/g, '&amp;').replace(/</g, '&lt;') + '</span></div>';
                    });
                    html += '</div><div class="panel-actions">';
                    if (item.can_change && item.status === 'pending') {
                        html += '<button type="button" class="btn btn-success" data-panel-action="confirm" data-id="' + item.id + '">Confirm</button>';
                        html += '<button type="button" class="btn btn-danger" data-panel-action="cancel" data-id="' + item.id + '">Cancel</button>';
                    } else if (item.can_change && item.status === 'confirmed') {
                        html += '<button type="button" class="btn btn-primary" data-panel-action="complete" data-id="' + item.id + '">Complete</button>';
                    }
                    html += '<a class="btn btn-secondary" href="booking-details.php?id=' + item.id + '">Open full details</a>';
                    html += '</div>';
                    body.innerHTML = html;
                }
                panel.hidden = false;
                if (panelRelease) panelRelease(false);
                if (typeof window.xpmFocusTrap === 'function') {
                    panelRelease = window.xpmFocusTrap(panel.querySelector('.booking-panel-card') || panel, {
                        onClose: closePanel
                    });
                }
            }

            document.addEventListener('click', function (event) {
                var actionBtn = event.target.closest('[data-panel-action]');
                if (actionBtn) {
                    var action = actionBtn.getAttribute('data-panel-action');
                    var id = parseInt(actionBtn.getAttribute('data-id'), 10);
                    if (action === 'confirm') confirmBooking(id);
                    else if (action === 'cancel') cancelBooking(id);
                    else if (action === 'complete') completeBooking(id);
                    return;
                }
                var trigger = event.target.closest('[data-event]');
                if (trigger) {
                    event.preventDefault();
                    event.stopPropagation();
                    openEvent(trigger.getAttribute('data-event'));
                }
            });
            if (document.getElementById('bookingPanelClose')) {
                document.getElementById('bookingPanelClose').addEventListener('click', closePanel);
            }
            if (panel) {
                panel.addEventListener('click', function (event) {
                    if (event.target === panel) closePanel();
                });
            }
            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && panel && !panel.hidden) closePanel();
            });
        })();

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
            var modal = document.getElementById('cancelModal');
            modal.hidden = false;
            modal.style.display = 'block';
            if (typeof window.xpmFocusTrap === 'function') {
                cancelRelease = window.xpmFocusTrap(modal.querySelector('.modal-content') || modal, {
                    onClose: closeModal
                });
            }
        }
        function completeBooking(id) {
            xpmConfirm({ title: 'Mark this booking completed?', message: 'The status will change to completed.', confirmText: 'Mark completed', icon: 'fas fa-check-double' }).then(function (ok) {
                if (ok) postAction({ action: 'complete', booking_id: id });
            });
        }
        var cancelRelease = null;
        var editRelease = null;
        function closeModal() {
            var modal = document.getElementById('cancelModal');
            if (cancelRelease) { var r = cancelRelease; cancelRelease = null; r(); }
            modal.hidden = true;
            modal.style.display = 'none';
        }
        function createFacilityBooking(id) {
            xpmConfirm({ title: 'Create facility booking?', message: 'A room booking will be created for this programme.', confirmText: 'Create', icon: 'fas fa-calendar-plus' }).then(function (ok) {
                if (ok) postAction({ action: 'create_facility_booking', programme_id: id });
            });
        }
        function editFacilityBooking(id) {
            document.getElementById('editProgrammeId').value = id;
            var modal = document.getElementById('editModal');
            modal.hidden = false;
            modal.style.display = 'block';
            if (typeof window.xpmFocusTrap === 'function') {
                editRelease = window.xpmFocusTrap(modal.querySelector('.modal-content') || modal, {
                    onClose: closeEditModal
                });
            }
        }
        function closeEditModal() {
            var modal = document.getElementById('editModal');
            if (editRelease) { var r = editRelease; editRelease = null; r(); }
            modal.hidden = true;
            modal.style.display = 'none';
        }
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
            var slotRelease = null;

            function closeSlot() {
                if (slotRelease) { var r = slotRelease; slotRelease = null; r(); }
                panel.hidden = true;
            }

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
                if (slotRelease) slotRelease(false);
                if (typeof window.xpmFocusTrap === 'function') {
                    slotRelease = window.xpmFocusTrap(panel.querySelector('.slot-card') || panel, {
                        onClose: closeSlot
                    });
                }
            }

            document.querySelectorAll('.calendar-day[data-slot], .agenda-day[data-slot]').forEach(function (day) {
                day.addEventListener('click', function (event) {
                    if (event.target.closest('a, button, [data-event]')) return;
                    openSlot(day);
                });
            });
            document.getElementById('slotClose').addEventListener('click', closeSlot);
            panel.addEventListener('click', function (event) {
                if (event.target === panel) closeSlot();
            });
            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && !panel.hidden) closeSlot();
            });
        })();
    </script>
</body>
</html>
