<?php
// Set timezone for consistent date handling
date_default_timezone_set('Asia/Kuala_Lumpur');

// Include shared database connection
require_once '../includes/database.php';

header('Content-Type: application/json');

$pdo = get_db_connection();

if (!$pdo) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// Get parameters
$facility_type = $_GET['facility'] ?? '';
$date = $_GET['date'] ?? '';
$month = $_GET['month'] ?? date('Y-m');

try {
    if ($facility_type && $date) {
        // Get availability for a specific date and facility
        $stmt = $pdo->prepare("
            SELECT start_time, end_time, status
            FROM bookings 
            WHERE facility_type = ? 
            AND booking_date = ? 
            AND status IN ('pending', 'confirmed')
            ORDER BY start_time
        ");
        
        $stmt->execute([$facility_type, $date]);
        $bookings = $stmt->fetchAll();
        
        // Generate available time slots (9 AM to 6 PM, 1-hour slots)
        $available_slots = [];
        $booked_slots = [];
        
        // Convert bookings to time ranges
        foreach ($bookings as $booking) {
            $booked_slots[] = [
                'start' => $booking['start_time'],
                'end' => $booking['end_time']
            ];
        }
        
        // Generate hourly slots from 9 AM to 6 PM
        for ($hour = 9; $hour < 18; $hour++) {
            $start_time = sprintf('%02d:00', $hour);
            $end_time = sprintf('%02d:00', $hour + 1);
            
            $is_available = true;
            foreach ($booked_slots as $booked) {
                if (($start_time >= $booked['start'] && $start_time < $booked['end']) ||
                    ($end_time > $booked['start'] && $end_time <= $booked['end']) ||
                    ($start_time <= $booked['start'] && $end_time >= $booked['end'])) {
                    $is_available = false;
                    break;
                }
            }
            
            $available_slots[] = [
                'start' => $start_time,
                'end' => $end_time,
                'available' => $is_available,
                'display' => date('g:i A', strtotime($start_time)) . ' - ' . date('g:i A', strtotime($end_time))
            ];
        }
        
        echo json_encode([
            'date' => $date,
            'facility' => $facility_type,
            'slots' => $available_slots,
            'bookings' => $bookings
        ]);
        
    } else {
        // Get availability for a month
        $start_date = $month . '-01';
        $end_date = date('Y-m-t', strtotime($start_date));
        
        $stmt = $pdo->prepare("
            SELECT booking_date, facility_type, COUNT(*) as booking_count
            FROM bookings 
            WHERE booking_date >= ? 
            AND booking_date <= ? 
            AND status IN ('pending', 'confirmed')
            GROUP BY booking_date, facility_type
        ");
        
        $stmt->execute([$start_date, $end_date]);
        $monthly_bookings = $stmt->fetchAll();
        
        // Create availability map
        $availability = [];
        foreach ($monthly_bookings as $booking) {
            $availability[$booking['booking_date']][$booking['facility_type']] = $booking['booking_count'];
        }
        
        echo json_encode([
            'month' => $month,
            'availability' => $availability
        ]);
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
