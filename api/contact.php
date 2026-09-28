<?php
// ===== CONFIGURATION =====
// Suppress warnings to prevent HTML output in JSON response
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Handle GET requests
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode([
        'success' => true,
        'message' => 'API is working correctly. Use POST method to submit contact form.',
        'method' => 'GET'
    ]);
    exit;
}

// ===== DATABASE CONFIGURATION =====
// Database connection is handled by includes/database.php

// ===== SECURITY FUNCTIONS =====
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validate_phone($phone) {
    // Remove all non-numeric characters
    $phone = preg_replace('/[^0-9]/', '', $phone);
    return strlen($phone) >= 10;
}

// Include shared database connection
require_once '../includes/database.php';

function create_tables($pdo) {
    try {
        // Create contacts table
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS contacts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                email VARCHAR(255) NOT NULL,
                company VARCHAR(255),
                phone VARCHAR(50),
                interest VARCHAR(100),
                message TEXT NOT NULL,
                status ENUM('new', 'contacted', 'qualified', 'blacklisted') DEFAULT 'new',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                ip_address VARCHAR(45),
                user_agent TEXT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Add status column if it doesn't exist (for existing installations)
        try {
            $pdo->exec("ALTER TABLE contacts ADD COLUMN status ENUM('new', 'contacted', 'qualified', 'blacklisted') DEFAULT 'new' AFTER message");
        } catch (PDOException $e) {
            // Column might already exist, ignore error
        }
        
        // Create index for status column if it doesn't exist
        try {
            $pdo->exec("CREATE INDEX idx_contacts_status ON contacts(status)");
        } catch (PDOException $e) {
            // Index might already exist, ignore error
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("Table creation failed: " . $e->getMessage());
        return false;
    }
}

function save_contact($data) {
    $pdo = get_db_connection();
    if (!$pdo) {
        return ['success' => false, 'message' => 'Database connection failed'];
    }
    
    // Create tables if they don't exist
    if (!create_tables($pdo)) {
        return ['success' => false, 'message' => 'Database setup failed'];
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO contacts (name, email, company, phone, interest, message, ip_address, user_agent)
            VALUES (:name, :email, :company, :phone, :interest, :message, :ip_address, :user_agent)
        ");
        
        $stmt->execute([
            ':name' => $data['name'],
            ':email' => $data['email'],
            ':company' => isset($data['company']) ? $data['company'] : '',
            ':phone' => isset($data['phone']) ? $data['phone'] : '',
            ':interest' => isset($data['interest']) ? $data['interest'] : '',
            ':message' => $data['message'],
            ':ip_address' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '',
            ':user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : ''
        ]);
        
        return ['success' => true, 'message' => 'Contact form submitted successfully'];
    } catch (PDOException $e) {
        error_log("Contact save failed: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to save contact information'];
    }
}

// ===== EMAIL FUNCTION =====
function send_notification_email($data) {
    // Suppress warnings to prevent HTML output
    error_reporting(0);
    
    $to = 'info@xpresspoint.com'; // Change to your email
    $subject = 'New Contact Form Submission - XpressPoint HR Training';

    $message = "
    New contact form submission received:

    Name: {$data['name']}
    Email: {$data['email']}
    Company: " . (isset($data['company']) ? $data['company'] : 'Not provided') . "
    Phone: " . (isset($data['phone']) ? $data['phone'] : 'Not provided') . "
    Service Interest: " . (isset($data['interest']) ? $data['interest'] : 'Not specified') . "

    Message:
    {$data['message']}

    Submitted on: " . date('Y-m-d H:i:s') . "
    IP Address: " . (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'Unknown') . "
    ";

    $headers = [
        'From' => 'noreply@xpresspoint.com',
        'Reply-To' => $data['email'],
        'X-Mailer' => 'PHP/' . phpversion()
    ];

    // Try to send email, but don't fail if it doesn't work
    try {
        return @mail($to, $subject, $message, $headers);
    } catch (Exception $e) {
        error_log("Email sending failed: " . $e->getMessage());
        return false;
    }
}

// ===== MAIN PROCESSING =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get JSON input
        $input = json_decode(file_get_contents('php://input'), true);
        
        // If JSON parsing failed, try POST data
        if (!$input) {
            $input = $_POST;
        }
        
        // Validate required fields
        $required_fields = ['name', 'email', 'message'];
        $errors = [];
        
        foreach ($required_fields as $field) {
            if (empty($input[$field])) {
                $errors[] = ucfirst($field) . ' is required';
            }
        }
        
        // Validate email
        if (!empty($input['email']) && !validate_email($input['email'])) {
            $errors[] = 'Please enter a valid email address';
        }
        
        // Validate phone if provided
        if (!empty($input['phone']) && !validate_phone($input['phone'])) {
            $errors[] = 'Please enter a valid phone number';
        }
        
        // Validate custom service details if custom service is selected
        if (isset($input['interest']) && $input['interest'] === 'custom') {
            if (empty($input['custom-service-details'])) {
                $errors[] = 'Custom service details are required when selecting Custom Service';
            } elseif (strlen(trim($input['custom-service-details'])) < 10) {
                $errors[] = 'Custom service details must be at least 10 characters long';
            }
        }
        
        // Check for spam/bot indicators
        if (!empty($input['honeypot']) || !empty($input['website'])) {
            $errors[] = 'Invalid form submission';
        }
        
        // Rate limiting disabled for production use
        // Uncomment and configure rate limiting as needed
        
        if (!empty($errors)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $errors
            ]);
            exit;
        }
        
        // Sanitize input
        $sanitized_data = [
            'name' => sanitize_input($input['name']),
            'email' => sanitize_input($input['email']),
            'company' => sanitize_input(isset($input['company']) ? $input['company'] : ''),
            'phone' => sanitize_input(isset($input['phone']) ? $input['phone'] : ''),
            'interest' => sanitize_input(isset($input['interest']) ? $input['interest'] : ''),
            'message' => sanitize_input($input['message'])
        ];
        
        // Handle custom service details
        if (isset($input['interest']) && $input['interest'] === 'custom' && !empty($input['custom-service-details'])) {
            $sanitized_data['message'] = "CUSTOM SERVICE REQUEST:\n\n" . sanitize_input($input['custom-service-details']) . "\n\n" . $sanitized_data['message'];
        }
        
        // Save to database
        $result = save_contact($sanitized_data);
        
        if ($result['success']) {
            // Send notification email
            send_notification_email($sanitized_data);
            
            // Rate limiting update (disabled)
            // file_put_contents($rate_limit_file, $current_time);
            
            echo json_encode([
                'success' => true,
                'message' => 'Thank you! Your message has been sent successfully. We will get back to you soon.'
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => $result['message']
            ]);
        }
        
    } catch (Exception $e) {
        error_log("Contact form error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'An unexpected error occurred. Please try again later.'
        ]);
    }
}
?> 