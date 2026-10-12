<?php
// Email notification system for bookings
require_once 'database.php';

class EmailNotifications {
    private $pdo;
    
    public function __construct() {
        $this->pdo = get_db_connection();
    }
    
    /**
     * Send booking confirmation email to customer
     */
    public function sendBookingConfirmation($bookingId) {
        $booking = $this->getBookingDetails($bookingId);
        if (!$booking) {
            return false;
        }
        
        $subject = "Booking Confirmation - XpressPoint HR Training";
        $message = $this->generateConfirmationEmail($booking);
        
        return $this->sendEmail($booking['customer_email'], $subject, $message);
    }
    
    /**
     * Send booking status update email
     */
    public function sendStatusUpdate($bookingId, $newStatus) {
        $booking = $this->getBookingDetails($bookingId);
        if (!$booking) {
            return false;
        }
        
        $subject = "Booking Update - XpressPoint HR Training";
        $message = $this->generateStatusUpdateEmail($booking, $newStatus);
        
        return $this->sendEmail($booking['customer_email'], $subject, $message);
    }
    
    /**
     * Send booking reminder email
     */
    public function sendBookingReminder($bookingId) {
        $booking = $this->getBookingDetails($bookingId);
        if (!$booking) {
            return false;
        }
        
        $subject = "Booking Reminder - XpressPoint HR Training";
        $message = $this->generateReminderEmail($booking);
        
        return $this->sendEmail($booking['customer_email'], $subject, $message);
    }
    
    /**
     * Get booking details from database
     */
    private function getBookingDetails($bookingId) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT b.*, u.username as confirmed_by_name
                FROM bookings b
                LEFT JOIN users u ON b.confirmed_by = u.id
                WHERE b.id = ?
            ");
            $stmt->execute([$bookingId]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Error fetching booking details: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Generate confirmation email content
     */
    private function generateConfirmationEmail($booking) {
        $facilityNames = [
            'conference_room' => 'Conference Room',
            'training_room' => 'Training Room',
            'breakout_area' => 'Breakout Area'
        ];
        
        $facilityName = $facilityNames[$booking['facility_type']] ?? $booking['facility_type'];
        $bookingDate = date('l, F j, Y', strtotime($booking['booking_date']));
        $startTime = date('g:i A', strtotime($booking['start_time']));
        $endTime = date('g:i A', strtotime($booking['end_time']));
        
        $html = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px; }
                .booking-details { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #667eea; }
                .detail-row { display: flex; justify-content: space-between; margin: 10px 0; padding: 8px 0; border-bottom: 1px solid #eee; }
                .detail-label { font-weight: bold; color: #555; }
                .detail-value { color: #333; }
                .footer { text-align: center; margin-top: 30px; color: #666; font-size: 14px; }
                .btn { display: inline-block; background: #667eea; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; margin: 10px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Booking Confirmed!</h1>
                    <p>Thank you for choosing XpressPoint HR Training</p>
                </div>
                
                <div class='content'>
                    <h2>Dear {$booking['customer_name']},</h2>
                    
                    <p>We're pleased to confirm your facility booking with XpressPoint HR Training. Your reservation has been successfully processed and confirmed.</p>
                    
                    <div class='booking-details'>
                        <h3>Booking Details</h3>
                        <div class='detail-row'>
                            <span class='detail-label'>Booking ID:</span>
                            <span class='detail-value'>#{$booking['id']}</span>
                        </div>
                        <div class='detail-row'>
                            <span class='detail-label'>Facility:</span>
                            <span class='detail-value'>{$facilityName}</span>
                        </div>
                        <div class='detail-row'>
                            <span class='detail-label'>Date:</span>
                            <span class='detail-value'>{$bookingDate}</span>
                        </div>
                        <div class='detail-row'>
                            <span class='detail-label'>Time:</span>
                            <span class='detail-value'>{$startTime} - {$endTime}</span>
                        </div>
                        <div class='detail-row'>
                            <span class='detail-label'>Attendees:</span>
                            <span class='detail-value'>{$booking['attendees_count']} people</span>
                        </div>";
        
        if ($booking['company']) {
            $html .= "
                        <div class='detail-row'>
                            <span class='detail-label'>Company:</span>
                            <span class='detail-value'>{$booking['company']}</span>
                        </div>";
        }
        
        if ($booking['purpose']) {
            $html .= "
                        <div class='detail-row'>
                            <span class='detail-label'>Purpose:</span>
                            <span class='detail-value'>{$booking['purpose']}</span>
                        </div>";
        }
        
        $html .= "
                    </div>
                    
                    <h3>Important Information</h3>
                    <ul>
                        <li>Please arrive 15 minutes before your scheduled time</li>
                        <li>Bring a valid ID for verification</li>
                        <li>Contact us immediately if you need to make any changes</li>
                        <li>Cancellations must be made 24 hours in advance</li>
                    </ul>
                    
                    <h3>Contact Information</h3>
                    <p><strong>XpressPoint Management Services</strong><br>
                    No.6, Jalan Berkat 12, Taman Malim Jaya, 75250 Melaka<br>
                    Phone: 016 772 1113<br>
                    Email: hr@xpresspointservices.com</p>
                    
                    <div class='footer'>
                        <p>Thank you for choosing XpressPoint HR Training!</p>
                        <p>This is an automated message. Please do not reply to this email.</p>
                    </div>
                </div>
            </div>
        </body>
        </html>";
        
        return $html;
    }
    
    /**
     * Generate status update email content
     */
    private function generateStatusUpdateEmail($booking, $newStatus) {
        $statusMessages = [
            'confirmed' => 'Your booking has been confirmed!',
            'cancelled' => 'Your booking has been cancelled.',
            'completed' => 'Your booking has been marked as completed.'
        ];
        
        $message = $statusMessages[$newStatus] ?? 'Your booking status has been updated.';
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Booking Update</h1>
                </div>
                <div class='content'>
                    <h2>Dear {$booking['customer_name']},</h2>
                    <p>{$message}</p>
                    <p>Booking ID: #{$booking['id']}</p>
                    <p>If you have any questions, please contact us at hr@xpresspointservices.com</p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    /**
     * Generate reminder email content
     */
    private function generateReminderEmail($booking) {
        $facilityNames = [
            'conference_room' => 'Conference Room',
            'training_room' => 'Training Room',
            'breakout_area' => 'Breakout Area'
        ];
        
        $facilityName = $facilityNames[$booking['facility_type']] ?? $booking['facility_type'];
        $bookingDate = date('l, F j, Y', strtotime($booking['booking_date']));
        $startTime = date('g:i A', strtotime($booking['start_time']));
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Booking Reminder</h1>
                </div>
                <div class='content'>
                    <h2>Dear {$booking['customer_name']},</h2>
                    <p>This is a friendly reminder about your upcoming facility booking:</p>
                    <p><strong>Facility:</strong> {$facilityName}<br>
                    <strong>Date:</strong> {$bookingDate}<br>
                    <strong>Time:</strong> {$startTime}</p>
                    <p>Please arrive 15 minutes before your scheduled time. If you need to make any changes, please contact us immediately.</p>
                    <p>Contact: hr@xpresspointservices.com | 016 772 1113</p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    /**
     * Send email using PHP mail function
     */
    private function sendEmail($to, $subject, $message) {
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: XpressPoint HR Training <noreply@xpresspointservices.com>',
            'Reply-To: hr@xpresspointservices.com',
            'X-Mailer: PHP/' . phpversion()
        ];
        
        return mail($to, $subject, $message, implode("\r\n", $headers));
    }
    
    /**
     * Tell every admin that a trainer reserved days on the calendar.
     */
    public function sendTrainerDayBooking($trainerName, $typeLabel, $startDate, $startTime, $endDate, $endTime, $kind = 'booked') {
        if (!$this->pdo) {
            return false;
        }

        $stmt = $this->pdo->query("SELECT email FROM users WHERE role = 'admin' AND email IS NOT NULL AND email <> ''");
        $emails = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
        if (!$emails) {
            return false;
        }

        $trainerName = trim((string) $trainerName) ?: 'A trainer';
        $typeLabel = trim((string) $typeLabel) ?: 'Training Programme';
        $from = date('M j, Y', strtotime($startDate)) . ' at ' . date('g:i A', strtotime($startTime));
        $to = date('M j, Y', strtotime($endDate)) . ' at ' . date('g:i A', strtotime($endTime));
        $kind = $kind ?? 'booked';
        if ($kind === 'cancelled') {
            $subject = 'Trainer cancelled a day - ' . $trainerName;
            $lead = 'cancelled';
            $note = 'Those days are free again.';
        } elseif ($kind === 'updated') {
            $subject = 'Trainer changed a booking - ' . $trainerName;
            $lead = 'changed';
            $note = 'Those days stay reserved. Edit the programme and post it when it is ready.';
        } else {
            $subject = 'Trainer booked a day - ' . $trainerName;
            $lead = 'booked';
            $note = 'Those days are reserved. Edit the programme and post it when it is ready.';
        }
        $message = '<p><strong>' . htmlspecialchars($trainerName) . '</strong> ' . $lead . ' a '
            . htmlspecialchars($typeLabel) . '.</p>'
            . '<p>From ' . htmlspecialchars($from) . ' to ' . htmlspecialchars($to) . '.</p>'
            . '<p>' . htmlspecialchars($note) . '</p>';

        $sent = false;
        foreach ($emails as $email) {
            if ($this->sendEmail($email, $subject, $message)) {
                $sent = true;
            }
        }
        return $sent;
    }

    /**
     * Send daily booking reminders
     */
    public function sendDailyReminders() {
        try {
            // Get bookings for tomorrow
            $tomorrow = date('Y-m-d', strtotime('+1 day'));
            
            $stmt = $this->pdo->prepare("
                SELECT id FROM bookings 
                WHERE booking_date = ? 
                AND status IN ('pending', 'confirmed')
            ");
            $stmt->execute([$tomorrow]);
            $bookings = $stmt->fetchAll();
            
            $sent = 0;
            foreach ($bookings as $booking) {
                if ($this->sendBookingReminder($booking['id'])) {
                    $sent++;
                }
            }
            
            return $sent;
        } catch (PDOException $e) {
            error_log("Error sending daily reminders: " . $e->getMessage());
            return false;
        }
    }
}
?>
