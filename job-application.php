<?php
require_once 'includes/auth.php';
auth_start();
$xpm_account = auth_user();
$xpm_guest = !$xpm_account || empty($xpm_account['id']);
$xpm_login_next = auth_login_next_from_request();

// Include shared database connection
require_once 'includes/database.php';
require_once 'includes/phone-field.php';
require_once 'includes/form-prefill.php';
require_once 'config/email_config.php';

$pdo = get_db_connection();
$job = null;
$error = '';
$success = '';

// Helper function to format job descriptions into readable HTML
function formatJobDescription($description) {
    if (empty($description)) return '';
    
    $description = htmlspecialchars($description);
    
    // Split by newlines, or by " -" / " •" patterns (for descriptions stored as single lines)
    // First try splitting by actual newlines
    $lines = preg_split('/\r?\n/', $description);
    
    // If we only got one line, try splitting by " -" pattern (common in single-line descriptions)
    if (count($lines) <= 1) {
        // Split on " -" that appears to start a new bullet point (preceded by period, space, or start)
        $lines = preg_split('/(?<=\.)\s*-(?=\s*[A-Z])/', $description);
        if (count($lines) <= 1) {
            // Also try splitting on " -" without period requirement
            $lines = preg_split('/\s+-(?=[A-Z])/', $description);
        }
    }
    
    if (count($lines) <= 1) {
        // No bullet structure detected — just return with paragraph formatting
        return '<div class="job-desc-formatted"><p>' . nl2br($description) . '</p></div>';
    }
    
    $html = '<div class="job-desc-formatted">';
    $bullet_items = [];
    $intro_text = '';
    
    foreach ($lines as $index => $line) {
        $line = trim($line);
        if (empty($line)) continue;
        
        // Check if line starts with a bullet marker
        $is_bullet = preg_match('/^[-•·*]\s*/', $line);
        
        if ($is_bullet) {
            // Remove the bullet marker
            $clean = preg_replace('/^[-•·*]\s*/', '', $line);
            if (!empty($clean)) {
                $bullet_items[] = $clean;
            }
        } else {
            // If we have accumulated bullet items, flush them first
            if (!empty($bullet_items)) {
                $html .= '<ul class="job-desc-list">';
                foreach ($bullet_items as $item) {
                    $html .= '<li>' . $item . '</li>';
                }
                $html .= '</ul>';
                $bullet_items = [];
            }
            // This is a regular paragraph / intro text
            $html .= '<p>' . $line . '</p>';
        }
    }
    
    // Flush any remaining bullet items
    if (!empty($bullet_items)) {
        $html .= '<ul class="job-desc-list">';
        foreach ($bullet_items as $item) {
            $html .= '<li>' . $item . '</li>';
        }
        $html .= '</ul>';
    }
    
    $html .= '</div>';
    return $html;
}

function job_application_choice_lists() {
    $yes_no = ['Yes' => 'Yes', 'No' => 'No'];
    return [
        'state' => [
            'Johor' => 'Johor',
            'Kedah' => 'Kedah',
            'Kelantan' => 'Kelantan',
            'Melaka' => 'Melaka',
            'Negeri Sembilan' => 'Negeri Sembilan',
            'Pahang' => 'Pahang',
            'Perak' => 'Perak',
            'Perlis' => 'Perlis',
            'Pulau Pinang' => 'Pulau Pinang',
            'Sabah' => 'Sabah',
            'Sarawak' => 'Sarawak',
            'Selangor' => 'Selangor',
            'Terengganu' => 'Terengganu',
            'Kuala Lumpur' => 'Kuala Lumpur',
            'Labuan' => 'Labuan',
            'Putrajaya' => 'Putrajaya',
        ],
        'gender' => ['Male' => 'Male', 'Female' => 'Female'],
        'race' => ['Malay' => 'Malay', 'Chinese' => 'Chinese', 'Indian' => 'Indian', 'Others' => 'Others'],
        'marital_status' => ['Single' => 'Single', 'Married' => 'Married', 'Divorced' => 'Divorced', 'Widowed' => 'Widowed'],
        'religion' => ['Islam' => 'Islam', 'Buddhism' => 'Buddhism', 'Christianity' => 'Christianity', 'Hinduism' => 'Hinduism', 'Others' => 'Others'],
        'smoker' => $yes_no,
        'convicted_of_crime' => $yes_no,
        'willing_to_relocate' => $yes_no,
        'own_transportation' => $yes_no,
        'having_relative_in_company' => $yes_no,
        'able_to_work_shift' => $yes_no,
    ];
}

function job_application_select_options($name, $placeholder) {
    $lists = job_application_choice_lists();
    $current = (string) ($_POST[$name] ?? '');
    $html = '<option value="">' . htmlspecialchars($placeholder) . '</option>';
    foreach ($lists[$name] as $value => $label) {
        $selected = $current === (string) $value ? ' selected' : '';
        $html .= '<option value="' . htmlspecialchars($value) . '"' . $selected . '>' . htmlspecialchars($label) . '</option>';
    }
    return $html;
}

function job_application_profile_from_request() {
    $keys = [
        'address', 'state', 'city', 'age', 'weight', 'height', 'gender', 'race', 'nationality',
        'marital_status', 'religion', 'smoker', 'convicted_of_crime', 'willing_to_relocate',
        'own_transportation', 'having_relative_in_company', 'able_to_work_shift', 'current_health_medication',
    ];
    $profile = [];
    foreach ($keys as $key) {
        $profile[$key] = trim((string) ($_POST[$key] ?? ''));
    }
    return $profile;
}

function job_application_text_length($value) {
    return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
}

function job_application_positive_measure($value, $max) {
    if (!preg_match('/^\d+(\.\d{1,2})?$/', (string) $value)) {
        return false;
    }
    $number = (float) $value;
    return $number > 0 && $number <= $max;
}

function job_application_storage_columns() {
    return [
        'address' => 'TEXT NULL',
        'state' => 'VARCHAR(100) NULL',
        'city' => 'VARCHAR(100) NULL',
        'age' => 'SMALLINT UNSIGNED NULL',
        'weight' => 'DECIMAL(5,1) NULL',
        'height' => 'DECIMAL(5,1) NULL',
        'gender' => 'VARCHAR(20) NULL',
        'race' => 'VARCHAR(50) NULL',
        'nationality' => 'VARCHAR(100) NULL',
        'marital_status' => 'VARCHAR(50) NULL',
        'religion' => 'VARCHAR(50) NULL',
        'smoker' => 'VARCHAR(10) NULL',
        'convicted_of_crime' => 'VARCHAR(10) NULL',
        'willing_to_relocate' => 'VARCHAR(10) NULL',
        'own_transportation' => 'VARCHAR(10) NULL',
        'having_relative_in_company' => 'VARCHAR(10) NULL',
        'able_to_work_shift' => 'VARCHAR(10) NULL',
        'current_health_medication' => 'TEXT NULL',
    ];
}

function job_application_ensure_storage(PDO $pdo) {
    foreach (job_application_storage_columns() as $column => $definition) {
        try {
            $stmt = $pdo->query('SHOW COLUMNS FROM applications WHERE Field = ' . $pdo->quote($column));
            if ($stmt && !$stmt->fetch()) {
                $pdo->exec("ALTER TABLE applications ADD COLUMN `$column` $definition");
            }
        } catch (Throwable $e) {
            error_log('Job application schema: ' . $e->getMessage());
        }
    }

    try {
        $stmt = $pdo->query('SHOW COLUMNS FROM applications WHERE Field = ' . $pdo->quote('education_level'));
        $row = $stmt ? $stmt->fetch() : false;
        $type = is_array($row) ? (string) ($row['Type'] ?? '') : '';
        if (is_array($row) && strtoupper((string) $row['Null']) === 'NO' && preg_match('/^[a-z0-9(),\' _-]+$/i', $type)) {
            $pdo->exec('ALTER TABLE applications MODIFY `education_level` ' . $type . ' NULL DEFAULT NULL');
        }
    } catch (Throwable $e) {
        error_log('Job application schema: ' . $e->getMessage());
    }

    $nullable = [
        'availability_date' => 'DATE NULL DEFAULT NULL',
        'interviewer_comment' => 'TEXT NULL DEFAULT NULL',
    ];
    foreach ($nullable as $column => $definition) {
        try {
            $stmt = $pdo->query('SHOW COLUMNS FROM applications WHERE Field = ' . $pdo->quote($column));
            $row = $stmt ? $stmt->fetch() : null;
            if ($row && strtoupper((string) $row['Null']) === 'NO') {
                $pdo->exec("ALTER TABLE applications MODIFY `$column` $definition");
            }
        } catch (Throwable $e) {
            error_log('Job application schema: ' . $e->getMessage());
        }
    }
}

function job_application_fallback_value(array $column) {
    $type = strtolower((string) ($column['Type'] ?? ''));
    if (strpos($type, 'timestamp') === 0 || strpos($type, 'datetime') === 0) {
        return date('Y-m-d H:i:s');
    }
    if (strpos($type, 'date') === 0) {
        return date('Y-m-d');
    }
    if (strpos($type, 'enum(') === 0 && preg_match("/^enum\\('((?:[^']|'')*)'/", $type, $match)) {
        return str_replace("''", "'", $match[1]);
    }
    if (preg_match('/int|decimal|float|double|bit|year/', $type)) {
        return 0;
    }
    return '';
}

function job_application_blank_is_text(array $column) {
    $type = strtolower((string) ($column['Type'] ?? ''));
    return strpos($type, 'char') !== false
        || strpos($type, 'text') !== false
        || strpos($type, 'blob') !== false
        || strpos($type, 'json') === 0;
}

function job_application_insert(PDO $pdo, array $values) {
    job_application_ensure_storage($pdo);

    $existing = [];
    foreach ($pdo->query('SHOW COLUMNS FROM applications') as $column) {
        $existing[$column['Field']] = $column;
    }

    $columns = [];
    $bound = [];
    foreach ($values as $column => $value) {
        if (!isset($existing[$column])) {
            error_log('Job application skipped missing column: ' . $column);
            continue;
        }
        $columns[] = $column;
        if ($value === '' && !job_application_blank_is_text($existing[$column])) {
            $nullable = strtoupper((string) ($existing[$column]['Null'] ?? '')) === 'YES';
            $value = $nullable ? null : job_application_fallback_value($existing[$column]);
        }
        $bound[] = $value;
    }

    foreach ($existing as $name => $column) {
        if (in_array($name, $columns, true) || stripos((string) $column['Extra'], 'auto_increment') !== false) {
            continue;
        }
        $extra = (string) $column['Extra'];
        $hasDefault = $column['Default'] !== null
            || stripos($extra, 'DEFAULT_GENERATED') !== false
            || stripos($extra, 'CURRENT_TIMESTAMP') !== false;
        if (strtoupper((string) $column['Null']) === 'NO' && !$hasDefault) {
            $columns[] = $name;
            $bound[] = job_application_fallback_value($column);
        }
    }

    $quoted = array_map(function ($column) {
        return '`' . str_replace('`', '', $column) . '`';
    }, $columns);
    $placeholders = implode(', ', array_fill(0, count($bound), '?'));
    $stmt = $pdo->prepare('INSERT INTO applications (' . implode(', ', $quoted) . ') VALUES (' . $placeholders . ')');
    $stmt->execute($bound);
}

function job_application_profile_error(array $profile) {
    foreach ($profile as $value) {
        if ($value === '') {
            return 'Please fill in all required fields.';
        }
    }

    $lists = job_application_choice_lists();
    foreach ($lists as $key => $options) {
        if (!isset($options[$profile[$key]])) {
            return 'Please choose a valid option for each personal detail.';
        }
    }

    if (!preg_match('/^\d+$/', $profile['age']) || (int) $profile['age'] < 1 || (int) $profile['age'] > 120) {
        return 'Please enter a valid age.';
    }
    if (!job_application_positive_measure($profile['weight'], 500)) {
        return 'Please enter a valid weight in kilograms.';
    }
    if (!job_application_positive_measure($profile['height'], 300)) {
        return 'Please enter a valid height in centimetres.';
    }
    if (job_application_text_length($profile['city']) > 100 || job_application_text_length($profile['nationality']) > 100) {
        return 'Please shorten the city or nationality.';
    }
    if (job_application_text_length($profile['address']) > 2000 || job_application_text_length($profile['current_health_medication']) > 2000) {
        return 'Please shorten the address or health details.';
    }

    return '';
}

// Handle success message from redirect
if (isset($_GET['success']) && $_GET['success'] == '1') {
    $success = "Your application has been submitted successfully! We'll review it and get back to you soon. A confirmation was sent to your email.";
    // Clear any existing error messages when showing success
    $error = '';
}

// Email notification function for job applications
function sendJobApplicationNotification($job_id, $name, $email, $phone, $ic_number, $resume_path, $cover_letter_path, $profile = []) {
    // Job application emails from configuration
    $job_emails = [JOB_APPLICATION_EMAIL, JOB_APPLICATION_EMAIL_2];
    
    // Get job details
    global $pdo;
    $stmt = $pdo->prepare("SELECT title, company_name, location, employment_type FROM jobs WHERE id = ?");
    $stmt->execute([$job_id]);
    $job = $stmt->fetch();
    
    $job_title = $job ? $job['title'] : 'Unknown Position';
    $company_name = $job ? $job['company_name'] : 'Unknown Company';
    $location = $job ? $job['location'] : 'N/A';
    $employment_type = $job ? $job['employment_type'] : 'N/A';
    
    $subject = "New Job Application - " . $job_title . " at " . $company_name;
    
    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .header { background-color: #2c3e50; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; }
            .section { margin-bottom: 20px; }
            .section h3 { color: #2c3e50; border-bottom: 2px solid #3498db; padding-bottom: 5px; }
            .field { margin-bottom: 10px; }
            .field strong { color: #2c3e50; }
            .footer { background-color: #f8f9fa; padding: 15px; text-align: center; font-size: 12px; color: #666; }
            .file-info { background-color: #e8f4fd; padding: 10px; border-left: 4px solid #3498db; margin: 10px 0; }
        </style>
    </head>
    <body>
        <div class='header'>
            <h1>New Job Application</h1>
            <p>Someone has applied for a position</p>
        </div>
        
        <div class='content'>
            <div class='section'>
                <h3>Position Details</h3>
                <div class='field'><strong>Job Title:</strong> " . htmlspecialchars($job_title) . "</div>
                <div class='field'><strong>Company:</strong> " . htmlspecialchars($company_name) . "</div>
                <div class='field'><strong>Location:</strong> " . htmlspecialchars($location) . "</div>
                <div class='field'><strong>Employment Type:</strong> " . htmlspecialchars($employment_type) . "</div>
            </div>
            
            <div class='section'>
                <h3>Applicant Information</h3>
                <div class='field'><strong>Name:</strong> " . htmlspecialchars($name) . "</div>
                <div class='field'><strong>Email:</strong> " . htmlspecialchars($email) . "</div>
                <div class='field'><strong>Contact Number:</strong> " . htmlspecialchars($phone) . "</div>
                <div class='field'><strong>IC Number:</strong> " . htmlspecialchars($ic_number) . "</div>
                <div class='field'><strong>Address:</strong> " . nl2br(htmlspecialchars($profile['address'] ?? '')) . "</div>
                <div class='field'><strong>State:</strong> " . htmlspecialchars($profile['state'] ?? '') . "</div>
                <div class='field'><strong>City:</strong> " . htmlspecialchars($profile['city'] ?? '') . "</div>
                <div class='field'><strong>Age:</strong> " . htmlspecialchars($profile['age'] ?? '') . "</div>
                <div class='field'><strong>Weight (kg):</strong> " . htmlspecialchars($profile['weight'] ?? '') . "</div>
                <div class='field'><strong>Height (cm):</strong> " . htmlspecialchars($profile['height'] ?? '') . "</div>
                <div class='field'><strong>Gender:</strong> " . htmlspecialchars($profile['gender'] ?? '') . "</div>
                <div class='field'><strong>Race:</strong> " . htmlspecialchars($profile['race'] ?? '') . "</div>
                <div class='field'><strong>Nationality:</strong> " . htmlspecialchars($profile['nationality'] ?? '') . "</div>
                <div class='field'><strong>Marital Status:</strong> " . htmlspecialchars($profile['marital_status'] ?? '') . "</div>
                <div class='field'><strong>Religion:</strong> " . htmlspecialchars($profile['religion'] ?? '') . "</div>
                <div class='field'><strong>Smoker:</strong> " . htmlspecialchars($profile['smoker'] ?? '') . "</div>
                <div class='field'><strong>Convicted of crime:</strong> " . htmlspecialchars($profile['convicted_of_crime'] ?? '') . "</div>
                <div class='field'><strong>Willing to relocate:</strong> " . htmlspecialchars($profile['willing_to_relocate'] ?? '') . "</div>
                <div class='field'><strong>Own transportation:</strong> " . htmlspecialchars($profile['own_transportation'] ?? '') . "</div>
                <div class='field'><strong>Having relative in company:</strong> " . htmlspecialchars($profile['having_relative_in_company'] ?? '') . "</div>
                <div class='field'><strong>Able to work shift:</strong> " . htmlspecialchars($profile['able_to_work_shift'] ?? '') . "</div>
                <div class='field'><strong>Current health / medication:</strong> " . nl2br(htmlspecialchars($profile['current_health_medication'] ?? '')) . "</div>
            </div>
            
            <div class='section'>
                <h3>Application Files</h3>
                <div class='file-info'>
                    <div class='field'><strong>Resume:</strong> " . htmlspecialchars(basename($resume_path)) . "</div>
                    " . (!empty($cover_letter_path) ? "<div class='field'><strong>Cover Letter:</strong> " . htmlspecialchars(basename($cover_letter_path)) . "</div>" : "<div class='field'><strong>Cover Letter:</strong> Not provided</div>") . "
                </div>
            </div>
        </div>
        
        <div class='footer'>
            <p>This is an automated notification from Xpress Point Management</p>
            <p>Application submitted on " . date('Y-m-d H:i:s') . "</p>
        </div>
    </body>
    </html>
    ";
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: " . FROM_EMAIL . "\r\n";
    $headers .= "Reply-To: " . $email . "\r\n";
    
    // Send email to both job application emails
    foreach ($job_emails as $email_address) {
        $previousTimeout = ini_get('default_socket_timeout');
        ini_set('default_socket_timeout', '5');
        @mail($email_address, $subject, $message, $headers);
        ini_set('default_socket_timeout', (string) $previousTimeout);
    }
}

function sendJobApplicationConfirmation($job_id, $name, $email) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT title, company_name, location FROM jobs WHERE id = ?");
    $stmt->execute([$job_id]);
    $job = $stmt->fetch();

    $job_title = $job ? $job['title'] : 'the position you applied for';
    $company_name = $job ? $job['company_name'] : 'Xpress Point Management';
    $location = $job && !empty($job['location']) ? $job['location'] : '';

    $subject = "Application received - " . $job_title;
    $safe_name = htmlspecialchars($name);
    $safe_title = htmlspecialchars($job_title);
    $safe_company = htmlspecialchars($company_name);
    $location_line = $location !== ''
        ? "<div class='field'><strong>Location:</strong> " . htmlspecialchars($location) . "</div>"
        : '';

    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .header { background-color: #2c3e50; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; }
            .section { margin-bottom: 20px; }
            .section h3 { color: #2c3e50; border-bottom: 2px solid #3498db; padding-bottom: 5px; }
            .field { margin-bottom: 10px; }
            .field strong { color: #2c3e50; }
            .footer { background-color: #f8f9fa; padding: 15px; text-align: center; font-size: 12px; color: #666; }
        </style>
    </head>
    <body>
        <div class='header'>
            <h1>Application received</h1>
            <p>Thank you for applying</p>
        </div>
        <div class='content'>
            <p>Dear " . $safe_name . ",</p>
            <p>We have received your application. Our team will review it and be in touch.</p>
            <div class='section'>
                <h3>Position</h3>
                <div class='field'><strong>Job title:</strong> " . $safe_title . "</div>
                <div class='field'><strong>Company:</strong> " . $safe_company . "</div>
                " . $location_line . "
            </div>
        </div>
        <div class='footer'>
            <p>This is an automated confirmation from " . htmlspecialchars(FROM_NAME) . "</p>
            <p>Submitted on " . date('Y-m-d H:i:s') . "</p>
        </div>
    </body>
    </html>
    ";

    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= 'From: "' . str_replace('"', '', FROM_NAME) . '" <' . FROM_EMAIL . ">\r\n";
    $headers .= "Reply-To: " . JOB_APPLICATION_EMAIL . "\r\n";

    $previousTimeout = ini_get('default_socket_timeout');
    ini_set('default_socket_timeout', '5');
    @mail($email, $subject, $message, $headers);
    ini_set('default_socket_timeout', (string) $previousTimeout);
}

// Get job details if job_id is provided
if (isset($_GET['job_id']) && is_numeric($_GET['job_id'])) {
    $job_id = (int)$_GET['job_id'];
    try {
        $stmt = $pdo->prepare("SELECT * FROM jobs WHERE id = ? AND is_active = 1");
        $stmt->execute([$job_id]);
        $job = $stmt->fetch();
    } catch (PDOException $e) {
        $error = "Error loading job details.";
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $xpm_guest) {
    $error = 'Log in or create an account before you apply.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $job_id = $_POST['job_id'] ?? null;
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = combine_phone($_POST['phone_code'] ?? '+60', $_POST['phone'] ?? '');
    $ic_number = trim($_POST['ic_number'] ?? '');
    $profile = job_application_profile_from_request();
    $application_type = $_POST['application_type'] ?? 'with_resume';
    $cover_letter_path = '';
    $resume_path = '';
    
    // Initialize detailed application fields
    $education_level = '';
    $education_details = '';
    $reference1_name = $reference1_position = $reference1_company = $reference1_phone = $reference1_email = '';
    $reference2_name = $reference2_position = $reference2_company = $reference2_phone = $reference2_email = '';
    $reference3_name = $reference3_position = $reference3_company = $reference3_phone = $reference3_email = '';
    $employment1_company = $employment1_position = $employment1_duration = $employment1_description = '';
    $employment2_company = $employment2_position = $employment2_duration = $employment2_description = '';
    $employment3_company = $employment3_position = $employment3_duration = $employment3_description = '';
    $technical_skills = $soft_skills = $certifications = $languages = '';
    $expected_salary = $availability_date = $additional_notes = '';
    
    // Validation
    $profile_error = job_application_profile_error($profile);
    if (empty($name) || empty($email) || empty($job_id) || empty($ic_number) || empty($phone)) {
        $error = "Please fill in all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif ($profile_error !== '') {
        $error = $profile_error;
    } else {
        $upload_dir = 'uploads/resumes/';
        if (!is_dir($upload_dir)) {
            if (!mkdir($upload_dir, 0755, true)) {
                $error = "Error creating upload directory.";
            }
        }

        $allowed_doc_extensions = ['pdf', 'doc', 'docx'];

        // Handle file upload (only if application type is with_resume)
        if (empty($error) && $application_type === 'with_resume' && isset($_FILES['resume'])) {
            $file = $_FILES['resume'];
            
            if ($file['error'] === UPLOAD_ERR_OK) {
                $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                
                if (in_array($file_extension, $allowed_doc_extensions, true)) {
                    $filename = uniqid() . '_' . time() . '.' . $file_extension;
                    $filepath = $upload_dir . $filename;
                    
                    if (move_uploaded_file($file['tmp_name'], $filepath)) {
                        $resume_path = $filepath;
                    } else {
                        $error = "Error uploading resume file. Please try again.";
                    }
                } else {
                    $error = "Please upload a PDF or Word document (.pdf, .doc, .docx).";
                }
            } elseif ($file['error'] === UPLOAD_ERR_NO_FILE) {
                $error = "Please select a resume file to upload.";
            } elseif ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
                $error = "The resume file is too large. Please upload a smaller file (max 5MB).";
            } else {
                $error = "Error uploading resume file. Please try again.";
            }
        } elseif (empty($error) && $application_type === 'with_resume') {
            $error = "Please select a resume file to upload.";
        }
        
        // Handle cover letter file upload (optional)
        if (empty($error) && isset($_FILES['cover_letter']) && $_FILES['cover_letter']['error'] === UPLOAD_ERR_OK) {
            $cover_file = $_FILES['cover_letter'];
            $cover_extension = strtolower(pathinfo($cover_file['name'], PATHINFO_EXTENSION));
            
            if (in_array($cover_extension, $allowed_doc_extensions, true)) {
                $cover_filename = uniqid() . '_cover_' . time() . '.' . $cover_extension;
                $cover_filepath = $upload_dir . $cover_filename;
                
                if (move_uploaded_file($cover_file['tmp_name'], $cover_filepath)) {
                    $cover_letter_path = $cover_filepath;
                } else {
                    $error = "Error uploading cover letter file. Please try again.";
                }
            } else {
                $error = "Please upload a PDF or Word document for the cover letter (.pdf, .doc, .docx).";
            }
        }
        
        // Collect detailed application information (always collect, but only validate when without resume)
        $education_level = trim($_POST['education_level'] ?? '');
        if ($education_level === '') {
            $education_level = null;
        }
        $education_details = trim($_POST['education_details'] ?? '');
        
        // References
        $reference1_name = trim($_POST['reference1_name'] ?? '');
        $reference1_position = trim($_POST['reference1_position'] ?? '');
        $reference1_company = trim($_POST['reference1_company'] ?? '');
        $reference1_phone = combine_phone($_POST['reference1_phone_code'] ?? '+60', $_POST['reference1_phone'] ?? '');
        $reference1_email = trim($_POST['reference1_email'] ?? '');
        
        $reference2_name = trim($_POST['reference2_name'] ?? '');
        $reference2_position = trim($_POST['reference2_position'] ?? '');
        $reference2_company = trim($_POST['reference2_company'] ?? '');
        $reference2_phone = combine_phone($_POST['reference2_phone_code'] ?? '+60', $_POST['reference2_phone'] ?? '');
        $reference2_email = trim($_POST['reference2_email'] ?? '');
        
        $reference3_name = trim($_POST['reference3_name'] ?? '');
        $reference3_position = trim($_POST['reference3_position'] ?? '');
        $reference3_company = trim($_POST['reference3_company'] ?? '');
        $reference3_phone = combine_phone($_POST['reference3_phone_code'] ?? '+60', $_POST['reference3_phone'] ?? '');
        $reference3_email = trim($_POST['reference3_email'] ?? '');
        
        // Employment history
        $employment1_company = trim($_POST['employment1_company'] ?? '');
        $employment1_position = trim($_POST['employment1_position'] ?? '');
        $employment1_duration = trim($_POST['employment1_duration'] ?? '');
        $employment1_description = trim($_POST['employment1_description'] ?? '');
        
        $employment2_company = trim($_POST['employment2_company'] ?? '');
        $employment2_position = trim($_POST['employment2_position'] ?? '');
        $employment2_duration = trim($_POST['employment2_duration'] ?? '');
        $employment2_description = trim($_POST['employment2_description'] ?? '');
        
        $employment3_company = trim($_POST['employment3_company'] ?? '');
        $employment3_position = trim($_POST['employment3_position'] ?? '');
        $employment3_duration = trim($_POST['employment3_duration'] ?? '');
        $employment3_description = trim($_POST['employment3_description'] ?? '');
        
        // Skills and qualifications
        $technical_skills = trim($_POST['technical_skills'] ?? '');
        $soft_skills = trim($_POST['soft_skills'] ?? '');
        $certifications = trim($_POST['certifications'] ?? '');
        $languages = trim($_POST['languages'] ?? '');
        
        // Additional information
        $expected_salary = trim($_POST['expected_salary'] ?? '');
        $availability_date = trim($_POST['availability_date'] ?? '');
        $additional_notes = trim($_POST['additional_notes'] ?? '');
        // Strict SQL mode rejects '' for DATE. The date sits in the optional detailed section, so resume applications submit it blank.
        if ($availability_date === '') {
            $availability_date = null;
        } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $availability_date)
            || !checkdate((int) substr($availability_date, 5, 2), (int) substr($availability_date, 8, 2), (int) substr($availability_date, 0, 4))) {
            $error = 'Please enter a valid availability date.';
        }
        
        // Validation for required fields when without resume
        if ($application_type === 'without_resume' && empty($error)) {
            $missing_detailed = [];
            if ($education_level === '') {
                $missing_detailed[] = 'education level';
            }
            if ($reference1_name === '') {
                $missing_detailed[] = 'reference name';
            }
            if ($reference1_phone === '') {
                $missing_detailed[] = 'reference phone';
            }
            if ($missing_detailed) {
                $error = 'Please fill in the required detailed application fields: ' . implode(', ', $missing_detailed) . '.';
            }
        }
        
        if (empty($error)) {
            try {
                // DECIMAL(5,1) rejects a second decimal place when strict mode is on.
                $profile['weight'] = number_format((float) $profile['weight'], 1, '.', '');
                $profile['height'] = number_format((float) $profile['height'], 1, '.', '');

                job_application_insert($pdo, [
                    'job_id' => $job_id,
                    'name' => $name,
                    'email' => $email,
                    'phone' => $phone,
                    'ic_number' => $ic_number,
                    'address' => $profile['address'],
                    'state' => $profile['state'],
                    'city' => $profile['city'],
                    'age' => (int) $profile['age'],
                    'weight' => $profile['weight'],
                    'height' => $profile['height'],
                    'gender' => $profile['gender'],
                    'race' => $profile['race'],
                    'nationality' => $profile['nationality'],
                    'marital_status' => $profile['marital_status'],
                    'religion' => $profile['religion'],
                    'smoker' => $profile['smoker'],
                    'convicted_of_crime' => $profile['convicted_of_crime'],
                    'willing_to_relocate' => $profile['willing_to_relocate'],
                    'own_transportation' => $profile['own_transportation'],
                    'having_relative_in_company' => $profile['having_relative_in_company'],
                    'able_to_work_shift' => $profile['able_to_work_shift'],
                    'current_health_medication' => $profile['current_health_medication'],
                    'application_type' => $application_type,
                    'resume_path' => $resume_path,
                    'cover_letter_path' => $cover_letter_path,
                    'education_level' => $education_level,
                    'education_details' => $education_details,
                    'reference1_name' => $reference1_name,
                    'reference1_position' => $reference1_position,
                    'reference1_company' => $reference1_company,
                    'reference1_phone' => $reference1_phone,
                    'reference1_email' => $reference1_email,
                    'reference2_name' => $reference2_name,
                    'reference2_position' => $reference2_position,
                    'reference2_company' => $reference2_company,
                    'reference2_phone' => $reference2_phone,
                    'reference2_email' => $reference2_email,
                    'reference3_name' => $reference3_name,
                    'reference3_position' => $reference3_position,
                    'reference3_company' => $reference3_company,
                    'reference3_phone' => $reference3_phone,
                    'reference3_email' => $reference3_email,
                    'employment1_company' => $employment1_company,
                    'employment1_position' => $employment1_position,
                    'employment1_duration' => $employment1_duration,
                    'employment1_description' => $employment1_description,
                    'employment2_company' => $employment2_company,
                    'employment2_position' => $employment2_position,
                    'employment2_duration' => $employment2_duration,
                    'employment2_description' => $employment2_description,
                    'employment3_company' => $employment3_company,
                    'employment3_position' => $employment3_position,
                    'employment3_duration' => $employment3_duration,
                    'employment3_description' => $employment3_description,
                    'technical_skills' => $technical_skills,
                    'soft_skills' => $soft_skills,
                    'certifications' => $certifications,
                    'languages' => $languages,
                    'expected_salary' => $expected_salary,
                    'availability_date' => $availability_date,
                    'additional_notes' => $additional_notes,
                    'status' => 'pending',
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
                
                // Try to send email notification (don't let email errors stop the process)
                try {
                    sendJobApplicationNotification($job_id, $name, $email, $phone, $ic_number, $resume_path, $cover_letter_path, $profile);
                    sendJobApplicationConfirmation($job_id, $name, $email);
                } catch (Exception $emailError) {
                    // Log email error but don't stop the process
                    error_log("Email notification failed: " . $emailError->getMessage());
                }
                
                // Redirect to prevent form resubmission and show success message
                $redirect_url = $_SERVER['PHP_SELF'] . "?success=1&job_id=" . $job_id;
                header("Location: " . $redirect_url);
                exit;
                
            } catch (PDOException $e) {
                error_log('Job application insert failed: ' . $e->getMessage());
                $error = "Error submitting application. Please try again.";
            }
        }
    }
}

// Get all active jobs for dropdown
$all_jobs = [];
if ($pdo) {
    try {
        $stmt = $pdo->query("SELECT id, title, company_name FROM jobs WHERE is_active = 1 ORDER BY created_at DESC");
        $all_jobs = $stmt->fetchAll();
    } catch (PDOException $e) {
        // Handle error silently
    }
}

$xpm_prefilled = $pdo ? xpm_apply_account_prefill($pdo, 'job') : false;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $ic_number = $_POST['ic_number'] ?? '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Apply for exciting career opportunities with leading companies. Submit your job application today.">
    <meta name="keywords" content="job application, career opportunities, employment, job apply">
    <meta name="author" content="Xpress Point Management">
    <title>Job Application - Xpress Point Management</title>
    <?php $xpm_page_styles = ['pages/forms.css', 'pages/job-application.css']; include 'partials/site-head.php'; ?>
</head>
<body>
    <?php $xpm_active = 'careers'; include 'partials/site-nav.php'; ?>

    <main id="main">
        <header class="page-hero">
            <div class="container">
                <nav class="breadcrumb" aria-label="Breadcrumb" data-reveal="fade">
                    <a href="index.html">Home</a><i class="fas fa-chevron-right"></i>
                    <a href="jobs.php">Careers</a><i class="fas fa-chevron-right"></i>
                    <span>Apply</span>
                </nav>
                <span class="eyebrow" data-reveal><i class="fas fa-briefcase"></i> Job application</span>
                <h1 data-split>Apply for your dream position</h1>
                <p class="lead" data-reveal>Submit your application and take the next step in your career journey.</p>
            </div>
        </header>

        <section id="job-application" class="section form-page">
            <div class="container">
                <?php if ($error): ?>
                    <div class="alert alert-error" data-reveal>
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?php echo htmlspecialchars($error); ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success" data-reveal>
                        <i class="fas fa-check-circle"></i>
                        <span><?php echo htmlspecialchars($success); ?></span>
                    </div>
                <?php endif; ?>

                <div class="form-layout<?php echo $job ? '' : ' form-layout-single'; ?>">
                    <?php if ($job): ?>
                    <aside class="form-aside">
                        <div class="summary-card" data-reveal="left">
                            <div class="summary-body">
                                <div class="summary-top">
                                    <span class="icon-box icon-box-sm"><i class="<?php echo htmlspecialchars($job['job_icon'] ?? 'fas fa-briefcase'); ?>"></i></span>
                                    <span class="badge"><?php echo htmlspecialchars($job['job_badge'] ?? $job['type']); ?></span>
                                </div>

                                <div class="summary-company">
                                    <?php if (!empty($job['poster_image'])): ?>
                                    <img src="<?php echo htmlspecialchars($job['poster_image']); ?>"
                                         alt="<?php echo htmlspecialchars($job['title']); ?> Logo"
                                         class="job-logo-img">
                                    <?php else: ?>
                                    <span class="summary-company-fallback"><i class="fas fa-building"></i></span>
                                    <?php endif; ?>
                                    <?php if (!empty($job['company_name'])): ?>
                                    <span class="summary-company-name"><?php echo htmlspecialchars($job['company_name']); ?></span>
                                    <?php endif; ?>
                                </div>

                                <h2 class="summary-title"><?php echo htmlspecialchars($job['title']); ?></h2>

                                <ul class="summary-list">
                                    <li><i class="fas fa-location-dot"></i><span><?php echo htmlspecialchars($job['location']); ?></span></li>
                                    <li><i class="fas fa-clock"></i><span><?php echo htmlspecialchars($job['type']); ?></span></li>
                                    <?php if (!empty($job['salary_range'])): ?>
                                    <li><i class="fas fa-money-bill-wave"></i><span><?php echo htmlspecialchars($job['salary_range']); ?></span></li>
                                    <?php endif; ?>
                                    <?php if (!empty($job['experience_level'])): ?>
                                    <li>
                                        <i class="fas fa-user-tie"></i>
                                        <span>
                                            <?php
                                            $level = htmlspecialchars($job['experience_level']);
                                            $predefined_levels = ['entry', 'mid', 'senior', 'executive'];
                                            if (in_array(strtolower($level), $predefined_levels)) {
                                                echo ucfirst($level) . ' Level';
                                            } else {
                                                echo $level;
                                            }
                                            ?>
                                        </span>
                                    </li>
                                    <?php endif; ?>
                                </ul>

                                <details class="summary-more">
                                    <summary>Job description <i class="fas fa-chevron-down"></i></summary>
                                    <div class="job-description job-desc-formatted"><?php echo formatJobDescription($job['description']); ?></div>
                                </details>

                                <a href="job-detail.php?id=<?php echo (int)$job['id']; ?>" class="link-arrow">View full listing <i class="fas fa-arrow-right"></i></a>
                            </div>
                        </div>
                    </aside>
                    <?php endif; ?>

                    <div class="form-card" data-reveal="right">
                        <div class="form-card-header">
                            <h2>Application form</h2>
                            <p>Please fill out the form below to apply for this position. Fields marked * are required.</p>
                        </div>

                        <?php if ($xpm_guest): ?>
                            <?php auth_guest_banner('You can look through this application, but the fields stay locked until you sign in. Then you can fill in the details and apply.', $xpm_login_next); ?>
                        <?php endif; ?>
                        <?php if (!empty($xpm_prefilled)): ?>
                            <div class="alert alert-success" role="status">
                                <i class="fas fa-circle-check"></i>
                                <span>We filled in details we already have for you. Change anything before you send it.</span>
                            </div>
                        <?php endif; ?>
                        <form method="POST" enctype="multipart/form-data" class="form application-form<?php echo $xpm_guest ? ' form-locked' : ''; ?>" id="job-application-form">
                            <?php if ($xpm_guest): ?><fieldset class="guest-fieldset" disabled><?php endif; ?>
                            <?php if ($job): ?>
                                <input type="hidden" name="job_id" value="<?php echo $job['id']; ?>">
                            <?php endif; ?>

                            <fieldset class="form-section">
                                <legend class="form-section-title"><span class="step-no">1</span> About you</legend>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="name">Full Name *</label>
                                        <div class="input-wrapper">
                                            <i class="fas fa-user"></i>
                                            <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($name ?? ''); ?>" required placeholder="Enter your full name">
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="email">Email Address *</label>
                                        <div class="input-wrapper">
                                            <i class="fas fa-envelope"></i>
                                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email ?? ''); ?>" required placeholder="Enter your email address">
                                        </div>
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="phone">Contact Number *</label>
                                        <?php render_phone_field('phone', 'phone', true, $_POST['phone_code'] ?? '+60', $_POST['phone'] ?? ''); ?>
                                    </div>

                                    <div class="form-group">
                                        <label for="ic_number">IC Number *</label>
                                        <div class="input-wrapper">
                                            <i class="fas fa-id-card"></i>
                                            <input type="text" id="ic_number" name="ic_number" value="<?php echo htmlspecialchars($ic_number ?? ''); ?>" required placeholder="Enter your IC number">
                                        </div>
                                    </div>
                                </div>

                                <div class="sub-section">
                                    <h4 class="sub-section-title"><i class="fas fa-id-card"></i> Personal details</h4>

                                    <div class="form-group">
                                        <label for="address">Address *</label>
                                        <div class="input-wrapper">
                                            <i class="fas fa-location-dot"></i>
                                            <textarea id="address" name="address" rows="3" required placeholder="Enter your full address"><?php echo xpm_h('address'); ?></textarea>
                                        </div>
                                    </div>

                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="state">State *</label>
                                            <div class="input-wrapper">
                                                <i class="fas fa-map"></i>
                                                <select id="state" name="state" required>
                                                    <?php echo job_application_select_options('state', 'Select your state'); ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label for="city">City *</label>
                                            <div class="input-wrapper">
                                                <i class="fas fa-city"></i>
                                                <input type="text" id="city" name="city" required maxlength="100" placeholder="Enter your city" value="<?php echo xpm_h('city'); ?>">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="age">Age *</label>
                                            <div class="input-wrapper">
                                                <i class="fas fa-cake-candles"></i>
                                                <input type="number" id="age" name="age" required min="1" max="120" step="1" inputmode="numeric" placeholder="Age" value="<?php echo xpm_h('age'); ?>">
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label for="weight">Weight (kg) *</label>
                                            <div class="input-wrapper">
                                                <i class="fas fa-weight-scale"></i>
                                                <input type="number" id="weight" name="weight" required min="1" max="500" step="0.1" inputmode="decimal" placeholder="Weight in kg" value="<?php echo xpm_h('weight'); ?>">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="height">Height (cm) *</label>
                                            <div class="input-wrapper">
                                                <i class="fas fa-ruler-vertical"></i>
                                                <input type="number" id="height" name="height" required min="1" max="300" step="0.1" inputmode="decimal" placeholder="Height in cm" value="<?php echo xpm_h('height'); ?>">
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label for="gender">Gender *</label>
                                            <div class="input-wrapper">
                                                <i class="fas fa-venus-mars"></i>
                                                <select id="gender" name="gender" required>
                                                    <?php echo job_application_select_options('gender', 'Select gender'); ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="race">Race *</label>
                                            <div class="input-wrapper">
                                                <i class="fas fa-users"></i>
                                                <select id="race" name="race" required>
                                                    <?php echo job_application_select_options('race', 'Select race'); ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label for="nationality">Nationality *</label>
                                            <div class="input-wrapper">
                                                <i class="fas fa-flag"></i>
                                                <input type="text" id="nationality" name="nationality" required maxlength="100" placeholder="Nationality" value="<?php echo htmlspecialchars($_POST['nationality'] ?? 'Malaysian'); ?>">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="marital_status">Marital Status *</label>
                                            <div class="input-wrapper">
                                                <i class="fas fa-ring"></i>
                                                <select id="marital_status" name="marital_status" required>
                                                    <?php echo job_application_select_options('marital_status', 'Select marital status'); ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label for="religion">Religion *</label>
                                            <div class="input-wrapper">
                                                <i class="fas fa-place-of-worship"></i>
                                                <select id="religion" name="religion" required>
                                                    <?php echo job_application_select_options('religion', 'Select religion'); ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="smoker">Smoker *</label>
                                            <div class="input-wrapper">
                                                <i class="fas fa-smoking"></i>
                                                <select id="smoker" name="smoker" required>
                                                    <?php echo job_application_select_options('smoker', 'Select an option'); ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label for="convicted_of_crime">Convicted of crime *</label>
                                            <div class="input-wrapper">
                                                <i class="fas fa-gavel"></i>
                                                <select id="convicted_of_crime" name="convicted_of_crime" required>
                                                    <?php echo job_application_select_options('convicted_of_crime', 'Select an option'); ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="willing_to_relocate">Willing to relocate *</label>
                                            <div class="input-wrapper">
                                                <i class="fas fa-plane"></i>
                                                <select id="willing_to_relocate" name="willing_to_relocate" required>
                                                    <?php echo job_application_select_options('willing_to_relocate', 'Select an option'); ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label for="own_transportation">Own transportation *</label>
                                            <div class="input-wrapper">
                                                <i class="fas fa-car"></i>
                                                <select id="own_transportation" name="own_transportation" required>
                                                    <?php echo job_application_select_options('own_transportation', 'Select an option'); ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="having_relative_in_company">Having relative in company *</label>
                                            <div class="input-wrapper">
                                                <i class="fas fa-people-group"></i>
                                                <select id="having_relative_in_company" name="having_relative_in_company" required>
                                                    <?php echo job_application_select_options('having_relative_in_company', 'Select an option'); ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label for="able_to_work_shift">Able to work shift *</label>
                                            <div class="input-wrapper">
                                                <i class="fas fa-clock"></i>
                                                <select id="able_to_work_shift" name="able_to_work_shift" required>
                                                    <?php echo job_application_select_options('able_to_work_shift', 'Select an option'); ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="current_health_medication">Current health / medication *</label>
                                        <div class="input-wrapper">
                                            <i class="fas fa-notes-medical"></i>
                                            <textarea id="current_health_medication" name="current_health_medication" rows="3" required placeholder="Describe current health conditions or medication. Enter None if this does not apply."><?php echo xpm_h('current_health_medication'); ?></textarea>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="job_select">Position Applied For *</label>
                                    <div class="input-wrapper">
                                        <i class="fas fa-briefcase"></i>
                                        <?php if ($job): ?>
                                            <input type="text" id="job_select" value="<?php echo htmlspecialchars($job['title']); ?> - <?php echo htmlspecialchars($job['company_name']); ?>" readonly>
                                        <?php else: ?>
                                            <select id="job_select" name="job_id" required>
                                                <option value="">Choose a position to apply for...</option>
                                                <?php foreach ($all_jobs as $job_option): ?>
                                                    <option value="<?php echo $job_option['id']; ?>">
                                                        <?php echo htmlspecialchars($job_option['title']); ?> - <?php echo htmlspecialchars($job_option['company_name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </fieldset>

                            <fieldset class="form-section">
                                <legend class="form-section-title"><span class="step-no">2</span> How would you like to apply?</legend>

                                <div class="form-group">
                                    <span class="form-label">Application Type *</span>
                                    <div class="application-type-selection">
                                        <label class="application-type-option">
                                            <input type="radio" name="application_type" value="with_resume" <?php echo ($_POST['application_type'] ?? 'with_resume') !== 'without_resume' ? 'checked' : ''; ?> onchange="toggleApplicationType()">
                                            <span class="type-icon"><i class="fas fa-file-pdf"></i></span>
                                            <span class="type-text">
                                                <strong>I have a Resume/CV</strong>
                                                <small>Upload your resume and cover letter</small>
                                            </span>
                                        </label>

                                        <label class="application-type-option">
                                            <input type="radio" name="application_type" value="without_resume" <?php echo ($_POST['application_type'] ?? '') === 'without_resume' ? 'checked' : ''; ?> onchange="toggleApplicationType()">
                                            <span class="type-icon"><i class="fas fa-pen-to-square"></i></span>
                                            <span class="type-text">
                                                <strong>I don't have a Resume/CV</strong>
                                                <small>Fill in detailed information instead</small>
                                            </span>
                                        </label>
                                    </div>
                                </div>

                                <!-- Resume Upload Section (for with_resume) -->
                                <div id="resume-section" class="resume-section">
                                    <div class="form-group">
                                        <label for="resume">Resume/CV *</label>
                                        <div class="file-upload-wrapper" id="fileUploadWrapper">
                                            <i class="fas fa-file-upload" id="uploadIcon"></i>
                                            <input type="file" id="resume" name="resume" accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document" required>
                                            <div class="file-upload-info" id="fileUploadInfo">
                                                <span>Upload your resume (PDF or Word)</span>
                                                <small>Drag &amp; drop or click to browse &middot; Maximum file size: 5MB</small>
                                            </div>
                                            <div class="file-selected-info" id="fileSelectedInfo" style="display: none;">
                                                <i class="fas fa-file-alt"></i>
                                                <span id="selectedFileName"></span>
                                                <small id="selectedFileSize"></small>
                                                <button type="button" id="removeFile" class="remove-file-btn" aria-label="Remove file">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="cover_letter">Cover Letter (Optional)</label>
                                        <div class="file-upload-wrapper" id="coverLetterUploadWrapper">
                                            <i class="fas fa-file-upload" id="coverLetterUploadIcon"></i>
                                            <input type="file" id="cover_letter" name="cover_letter" accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document">
                                            <div class="file-upload-info" id="coverLetterFileUploadInfo">
                                                <span>Upload your cover letter (PDF or Word, optional)</span>
                                                <small>Maximum file size: 5MB</small>
                                            </div>
                                            <div class="file-selected-info" id="coverLetterFileSelectedInfo" style="display: none;">
                                                <i class="fas fa-file-alt"></i>
                                                <span id="coverLetterSelectedFileName"></span>
                                                <small id="coverLetterSelectedFileSize"></small>
                                                <button type="button" id="removeCoverLetterFile" class="remove-file-btn" aria-label="Remove file">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Detailed Application Section (for without_resume) -->
                                <div id="detailed-section" class="detailed-section" style="display: none;">
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle"></i>
                                        <span>Please provide detailed information about your background, experience, and qualifications.</span>
                                    </div>

                                    <div class="sub-section">
                                        <h4 class="sub-section-title"><i class="fas fa-graduation-cap"></i> Education</h4>

                                        <div class="form-group">
                                            <label for="education_level">Education Level *</label>
                                            <div class="input-wrapper">
                                                <i class="fas fa-certificate"></i>
                                                <select id="education_level" name="education_level">
                                                    <option value="">Select your education level</option>
                                                    <option value="high_school" <?php echo ($_POST['education_level'] ?? '') === 'high_school' ? 'selected' : ''; ?>>High School</option>
                                                    <option value="diploma" <?php echo ($_POST['education_level'] ?? '') === 'diploma' ? 'selected' : ''; ?>>Diploma</option>
                                                    <option value="bachelor" <?php echo ($_POST['education_level'] ?? '') === 'bachelor' ? 'selected' : ''; ?>>Bachelor's Degree</option>
                                                    <option value="master" <?php echo ($_POST['education_level'] ?? '') === 'master' ? 'selected' : ''; ?>>Master's Degree</option>
                                                    <option value="phd" <?php echo ($_POST['education_level'] ?? '') === 'phd' ? 'selected' : ''; ?>>PhD</option>
                                                    <option value="other" <?php echo ($_POST['education_level'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label for="education_details">Education Details</label>
                                            <div class="input-wrapper">
                                                <i class="fas fa-university"></i>
                                                <textarea id="education_details" name="education_details" rows="3" placeholder="Please provide details about your education, including institution names, degrees, and graduation years"><?php echo xpm_h('education_details'); ?></textarea>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="sub-section">
                                        <h4 class="sub-section-title"><i class="fas fa-users"></i> References (at least 1 required)</h4>

                                        <div class="entry-card reference-section">
                                            <h5>Reference 1 *</h5>
                                            <div class="form-row">
                                                <div class="form-group">
                                                    <label for="reference1_name">Name *</label>
                                                    <div class="input-wrapper">
                                                        <i class="fas fa-user"></i>
                                                        <input type="text" id="reference1_name" name="reference1_name" placeholder="Full name" value="<?php echo xpm_h('reference1_name'); ?>">
                                                    </div>
                                                </div>
                                                <div class="form-group">
                                                    <label for="reference1_position">Position</label>
                                                    <div class="input-wrapper">
                                                        <i class="fas fa-briefcase"></i>
                                                        <input type="text" id="reference1_position" name="reference1_position" placeholder="Job title" value="<?php echo xpm_h('reference1_position'); ?>">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="form-row">
                                                <div class="form-group">
                                                    <label for="reference1_company">Company</label>
                                                    <div class="input-wrapper">
                                                        <i class="fas fa-building"></i>
                                                        <input type="text" id="reference1_company" name="reference1_company" placeholder="Company name" value="<?php echo xpm_h('reference1_company'); ?>">
                                                    </div>
                                                </div>
                                                <div class="form-group">
                                                    <label for="reference1_phone">Phone *</label>
                                                    <?php render_phone_field('reference1_phone', 'reference1_phone', false, $_POST['reference1_phone_code'] ?? '+60', $_POST['reference1_phone'] ?? ''); ?>
                                                </div>
                                            </div>
                                            <div class="form-group">
                                                <label for="reference1_email">Email</label>
                                                <div class="input-wrapper">
                                                    <i class="fas fa-envelope"></i>
                                                    <input type="email" id="reference1_email" name="reference1_email" placeholder="Email address" value="<?php echo xpm_h('reference1_email'); ?>">
                                                </div>
                                            </div>
                                        </div>

                                        <details class="entry-card reference-section">
                                            <summary><h5>Reference 2 (Optional)</h5><i class="fas fa-plus"></i></summary>
                                            <div class="form-row">
                                                <div class="form-group">
                                                    <label for="reference2_name">Name</label>
                                                    <div class="input-wrapper">
                                                        <i class="fas fa-user"></i>
                                                        <input type="text" id="reference2_name" name="reference2_name" placeholder="Full name" value="<?php echo xpm_h('reference2_name'); ?>">
                                                    </div>
                                                </div>
                                                <div class="form-group">
                                                    <label for="reference2_position">Position</label>
                                                    <div class="input-wrapper">
                                                        <i class="fas fa-briefcase"></i>
                                                        <input type="text" id="reference2_position" name="reference2_position" placeholder="Job title" value="<?php echo xpm_h('reference2_position'); ?>">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="form-row">
                                                <div class="form-group">
                                                    <label for="reference2_company">Company</label>
                                                    <div class="input-wrapper">
                                                        <i class="fas fa-building"></i>
                                                        <input type="text" id="reference2_company" name="reference2_company" placeholder="Company name" value="<?php echo xpm_h('reference2_company'); ?>">
                                                    </div>
                                                </div>
                                                <div class="form-group">
                                                    <label for="reference2_phone">Phone</label>
                                                    <?php render_phone_field('reference2_phone', 'reference2_phone', false, $_POST['reference2_phone_code'] ?? '+60', $_POST['reference2_phone'] ?? ''); ?>
                                                </div>
                                            </div>
                                            <div class="form-group">
                                                <label for="reference2_email">Email</label>
                                                <div class="input-wrapper">
                                                    <i class="fas fa-envelope"></i>
                                                    <input type="email" id="reference2_email" name="reference2_email" placeholder="Email address" value="<?php echo xpm_h('reference2_email'); ?>">
                                                </div>
                                            </div>
                                        </details>

                                        <details class="entry-card reference-section">
                                            <summary><h5>Reference 3 (Optional)</h5><i class="fas fa-plus"></i></summary>
                                            <div class="form-row">
                                                <div class="form-group">
                                                    <label for="reference3_name">Name</label>
                                                    <div class="input-wrapper">
                                                        <i class="fas fa-user"></i>
                                                        <input type="text" id="reference3_name" name="reference3_name" placeholder="Full name" value="<?php echo xpm_h('reference3_name'); ?>">
                                                    </div>
                                                </div>
                                                <div class="form-group">
                                                    <label for="reference3_position">Position</label>
                                                    <div class="input-wrapper">
                                                        <i class="fas fa-briefcase"></i>
                                                        <input type="text" id="reference3_position" name="reference3_position" placeholder="Job title" value="<?php echo xpm_h('reference3_position'); ?>">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="form-row">
                                                <div class="form-group">
                                                    <label for="reference3_company">Company</label>
                                                    <div class="input-wrapper">
                                                        <i class="fas fa-building"></i>
                                                        <input type="text" id="reference3_company" name="reference3_company" placeholder="Company name" value="<?php echo xpm_h('reference3_company'); ?>">
                                                    </div>
                                                </div>
                                                <div class="form-group">
                                                    <label for="reference3_phone">Phone</label>
                                                    <?php render_phone_field('reference3_phone', 'reference3_phone', false, $_POST['reference3_phone_code'] ?? '+60', $_POST['reference3_phone'] ?? ''); ?>
                                                </div>
                                            </div>
                                            <div class="form-group">
                                                <label for="reference3_email">Email</label>
                                                <div class="input-wrapper">
                                                    <i class="fas fa-envelope"></i>
                                                    <input type="email" id="reference3_email" name="reference3_email" placeholder="Email address" value="<?php echo xpm_h('reference3_email'); ?>">
                                                </div>
                                            </div>
                                        </details>
                                    </div>

                                    <div class="sub-section">
                                        <h4 class="sub-section-title"><i class="fas fa-briefcase"></i> Employment history</h4>

                                        <div class="entry-card employment-section">
                                            <h5>Most Recent Position</h5>
                                            <div class="form-row">
                                                <div class="form-group">
                                                    <label for="employment1_company">Company</label>
                                                    <div class="input-wrapper">
                                                        <i class="fas fa-building"></i>
                                                        <input type="text" id="employment1_company" name="employment1_company" placeholder="Company name" value="<?php echo xpm_h('employment1_company'); ?>">
                                                    </div>
                                                </div>
                                                <div class="form-group">
                                                    <label for="employment1_position">Position</label>
                                                    <div class="input-wrapper">
                                                        <i class="fas fa-briefcase"></i>
                                                        <input type="text" id="employment1_position" name="employment1_position" placeholder="Job title" value="<?php echo xpm_h('employment1_position'); ?>">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="form-group">
                                                <label for="employment1_duration">Duration</label>
                                                <div class="input-wrapper">
                                                    <i class="fas fa-calendar"></i>
                                                    <input type="text" id="employment1_duration" name="employment1_duration" placeholder="e.g., Jan 2020 - Present" value="<?php echo xpm_h('employment1_duration'); ?>">
                                                </div>
                                            </div>
                                            <div class="form-group">
                                                <label for="employment1_description">Job Description</label>
                                                <div class="input-wrapper">
                                                    <i class="fas fa-tasks"></i>
                                                    <textarea id="employment1_description" name="employment1_description" rows="3" placeholder="Describe your responsibilities and achievements"><?php echo xpm_h('employment1_description'); ?></textarea>
                                                </div>
                                            </div>
                                        </div>

                                        <details class="entry-card employment-section">
                                            <summary><h5>Previous Position (Optional)</h5><i class="fas fa-plus"></i></summary>
                                            <div class="form-row">
                                                <div class="form-group">
                                                    <label for="employment2_company">Company</label>
                                                    <div class="input-wrapper">
                                                        <i class="fas fa-building"></i>
                                                        <input type="text" id="employment2_company" name="employment2_company" placeholder="Company name" value="<?php echo xpm_h('employment2_company'); ?>">
                                                    </div>
                                                </div>
                                                <div class="form-group">
                                                    <label for="employment2_position">Position</label>
                                                    <div class="input-wrapper">
                                                        <i class="fas fa-briefcase"></i>
                                                        <input type="text" id="employment2_position" name="employment2_position" placeholder="Job title" value="<?php echo xpm_h('employment2_position'); ?>">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="form-group">
                                                <label for="employment2_duration">Duration</label>
                                                <div class="input-wrapper">
                                                    <i class="fas fa-calendar"></i>
                                                    <input type="text" id="employment2_duration" name="employment2_duration" placeholder="e.g., Jan 2018 - Dec 2019" value="<?php echo xpm_h('employment2_duration'); ?>">
                                                </div>
                                            </div>
                                            <div class="form-group">
                                                <label for="employment2_description">Job Description</label>
                                                <div class="input-wrapper">
                                                    <i class="fas fa-tasks"></i>
                                                    <textarea id="employment2_description" name="employment2_description" rows="3" placeholder="Describe your responsibilities and achievements"><?php echo xpm_h('employment2_description'); ?></textarea>
                                                </div>
                                            </div>
                                        </details>

                                        <details class="entry-card employment-section">
                                            <summary><h5>Previous Position (Optional)</h5><i class="fas fa-plus"></i></summary>
                                            <div class="form-row">
                                                <div class="form-group">
                                                    <label for="employment3_company">Company</label>
                                                    <div class="input-wrapper">
                                                        <i class="fas fa-building"></i>
                                                        <input type="text" id="employment3_company" name="employment3_company" placeholder="Company name" value="<?php echo xpm_h('employment3_company'); ?>">
                                                    </div>
                                                </div>
                                                <div class="form-group">
                                                    <label for="employment3_position">Position</label>
                                                    <div class="input-wrapper">
                                                        <i class="fas fa-briefcase"></i>
                                                        <input type="text" id="employment3_position" name="employment3_position" placeholder="Job title" value="<?php echo xpm_h('employment3_position'); ?>">
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="form-group">
                                                <label for="employment3_duration">Duration</label>
                                                <div class="input-wrapper">
                                                    <i class="fas fa-calendar"></i>
                                                    <input type="text" id="employment3_duration" name="employment3_duration" placeholder="e.g., Jan 2016 - Dec 2017" value="<?php echo xpm_h('employment3_duration'); ?>">
                                                </div>
                                            </div>
                                            <div class="form-group">
                                                <label for="employment3_description">Job Description</label>
                                                <div class="input-wrapper">
                                                    <i class="fas fa-tasks"></i>
                                                    <textarea id="employment3_description" name="employment3_description" rows="3" placeholder="Describe your responsibilities and achievements"><?php echo xpm_h('employment3_description'); ?></textarea>
                                                </div>
                                            </div>
                                        </details>
                                    </div>

                                    <div class="sub-section">
                                        <h4 class="sub-section-title"><i class="fas fa-gears"></i> Skills and qualifications</h4>

                                        <div class="form-row">
                                            <div class="form-group">
                                                <label for="technical_skills">Technical Skills</label>
                                                <div class="input-wrapper">
                                                    <i class="fas fa-laptop-code"></i>
                                                    <textarea id="technical_skills" name="technical_skills" rows="3" placeholder="List your technical skills, software proficiency, programming languages, etc."><?php echo xpm_h('technical_skills'); ?></textarea>
                                                </div>
                                            </div>
                                            <div class="form-group">
                                                <label for="soft_skills">Soft Skills</label>
                                                <div class="input-wrapper">
                                                    <i class="fas fa-handshake"></i>
                                                    <textarea id="soft_skills" name="soft_skills" rows="3" placeholder="List your soft skills like communication, leadership, teamwork, etc."><?php echo xpm_h('soft_skills'); ?></textarea>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="form-row">
                                            <div class="form-group">
                                                <label for="certifications">Certifications</label>
                                                <div class="input-wrapper">
                                                    <i class="fas fa-certificate"></i>
                                                    <textarea id="certifications" name="certifications" rows="3" placeholder="List any professional certifications, licenses, or training you have"><?php echo xpm_h('certifications'); ?></textarea>
                                                </div>
                                            </div>
                                            <div class="form-group">
                                                <label for="languages">Languages</label>
                                                <div class="input-wrapper">
                                                    <i class="fas fa-language"></i>
                                                    <textarea id="languages" name="languages" rows="3" placeholder="List languages you speak and your proficiency level"><?php echo xpm_h('languages'); ?></textarea>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="sub-section">
                                        <h4 class="sub-section-title"><i class="fas fa-circle-info"></i> Additional information</h4>

                                        <div class="form-row">
                                            <div class="form-group">
                                                <label for="expected_salary">Expected Salary</label>
                                                <div class="input-wrapper">
                                                    <i class="fas fa-money-bill-wave"></i>
                                                    <input type="text" id="expected_salary" name="expected_salary" placeholder="e.g., RM 3000 - RM 4000" value="<?php echo xpm_h('expected_salary'); ?>">
                                                </div>
                                            </div>
                                            <div class="form-group">
                                                <label for="availability_date">Availability Date</label>
                                                <div class="input-wrapper">
                                                    <i class="fas fa-calendar-alt"></i>
                                                    <input type="date" id="availability_date" name="availability_date" value="<?php echo xpm_h('availability_date'); ?>">
                                                </div>
                                            </div>
                                        </div>

                                        <div class="form-group">
                                            <label for="additional_notes">Additional Notes</label>
                                            <div class="input-wrapper">
                                                <i class="fas fa-sticky-note"></i>
                                                <textarea id="additional_notes" name="additional_notes" rows="4" placeholder="Any additional information you'd like to share about yourself, your goals, or why you're interested in this position"><?php echo xpm_h('additional_notes'); ?></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </fieldset>

                            <?php if ($xpm_guest): ?></fieldset><?php endif; ?>
                            <div class="form-actions">
                                <a href="jobs.php" class="btn btn-outline">
                                    <i class="fas fa-arrow-left"></i>
                                    <span>Back to Jobs</span>
                                </a>
                                <button type="submit" class="btn btn-primary btn-lg"<?php echo $xpm_guest ? ' disabled' : ''; ?>>
                                    <i class="fas fa-paper-plane"></i>
                                    <span>Submit Application</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <?php $xpm_load_main_js = false; include 'partials/site-footer.php'; ?>

    <script src="assets/js/leave-guard.js?v=20261006-3"></script>
    <script>
        // Toggle application type function - MUST be global
        function toggleApplicationType() {
            const applicationType = document.querySelector('input[name="application_type"]:checked').value;
            const resumeSection = document.getElementById('resume-section');
            const detailedSection = document.getElementById('detailed-section');
            const resumeInput = document.getElementById('resume');
            
            console.log('Application type changed to:', applicationType);
            
            // Define the required fields for detailed application
            const requiredFields = [
                'education_level',
                'reference1_name', 
                'reference1_phone'
            ];
            
            if (applicationType === 'with_resume') {
                console.log('Showing resume section, hiding detailed section');
                resumeSection.style.display = 'block';
                detailedSection.style.display = 'none';
                if (resumeInput) {
                    resumeInput.required = true;
                }
                
                // Remove required attribute from detailed section fields
                requiredFields.forEach(fieldName => {
                    const field = document.getElementById(fieldName);
                    if (field) {
                        field.required = false;
                    }
                });
            } else {
                console.log('Hiding resume section, showing detailed section');
                resumeSection.style.display = 'none';
                detailedSection.style.display = 'block';
                if (resumeInput) {
                    resumeInput.required = false;
                }
                
                // Add required attribute to detailed section fields
                requiredFields.forEach(fieldName => {
                    const field = document.getElementById(fieldName);
                    if (field) {
                        field.required = true;
                    }
                });
            }
        }
        
        // File upload handling
        document.addEventListener('DOMContentLoaded', function() {
            /** Browsers often omit or misreport MIME types; validate by extension. */
            function isAllowedApplicationDoc(file) {
                if (!file || !file.name) return false;
                const m = /\.([^.]+)$/i.exec(file.name.trim());
                if (!m) return false;
                const ext = m[1].toLowerCase();
                return ['pdf', 'doc', 'docx'].indexOf(ext) !== -1;
            }

            // Initialize application type on page load
            toggleApplicationType();
            
            // Add event listeners to radio buttons for better reliability
            const applicationTypeRadios = document.querySelectorAll('input[name="application_type"]');
            applicationTypeRadios.forEach(radio => {
                radio.addEventListener('change', toggleApplicationType);
            });
            
            // Auto-save functionality
            const applicationForm = document.querySelector('form');
            
            // Ensure proper validation before form submission
            if (applicationForm) {
                applicationForm.addEventListener('submit', function(e) {
                    // Re-run the toggle function to ensure proper required attributes
                    toggleApplicationType();
                });
            }
            const formId = 'job-application-form';
            let autoSaveInterval;
            let hasUnsavedChanges = false;
            function markUnsaved(dirty) {
                hasUnsavedChanges = dirty;
                window.xpmFormDirty = dirty;
            }
            
            // Auto-save form data
            function autoSaveForm() {
                const formData = new FormData(applicationForm);
                const data = {};
                
                // Collect all form data (excluding files)
                for (let [key, value] of formData.entries()) {
                    if (key !== 'resume' && key !== 'cover_letter') {
                        data[key] = value;
                    }
                }
                
                // Save file selection info
                const resumeFile = document.getElementById('resume').files[0];
                const coverLetterFile = document.getElementById('cover_letter').files[0];
                
                if (resumeFile) {
                    data.resumeFileName = resumeFile.name;
                    data.resumeFileSize = resumeFile.size;
                }
                
                if (coverLetterFile) {
                    data.coverLetterFileName = coverLetterFile.name;
                    data.coverLetterFileSize = coverLetterFile.size;
                }
                
                // Save to localStorage
                localStorage.setItem(`form_${formId}_draft`, JSON.stringify(data));
                markUnsaved(true);
                
                // Show auto-save indicator
                showAutoSaveIndicator();
            }
            
            // Restore form data from localStorage
            function restoreFormData() {
                const savedData = localStorage.getItem(`form_${formId}_draft`);
                if (savedData) {
                    try {
                        const data = JSON.parse(savedData);
                        
                        // Restore form fields. Radios must be checked by value;
                        // assigning .value rewrites the first option and makes both submit the same type.
                        Object.keys(data).forEach(key => {
                            if (key === 'resumeFileName' || key === 'resumeFileSize' ||
                                key === 'coverLetterFileName' || key === 'coverLetterFileSize') {
                                return;
                            }
                            const fields = applicationForm.querySelectorAll(`[name="${CSS.escape(key)}"]`);
                            if (!fields.length) {
                                return;
                            }
                            if (fields[0].type === 'radio') {
                                fields.forEach(radio => {
                                    radio.checked = radio.value === data[key];
                                });
                                return;
                            }
                            if (fields[0].type === 'checkbox') {
                                fields[0].checked = data[key] === 'on' || data[key] === fields[0].value;
                                return;
                            }
                            fields[0].value = data[key];
                        });
                        if (typeof toggleApplicationType === 'function') {
                            toggleApplicationType();
                        }
                        
                        // Show restore notification
                        showRestoreNotification();
                        markUnsaved(true);
                    } catch (e) {
                        console.error('Error restoring form data:', e);
                    }
                }
            }
            
            // Clear saved form data
            function clearSavedFormData() {
                localStorage.removeItem(`form_${formId}_draft`);
                markUnsaved(false);
            }
            
            // Show auto-save indicator
            function showAutoSaveIndicator() {
                let indicator = document.getElementById('auto-save-indicator');
                if (!indicator) {
                    indicator = document.createElement('div');
                    indicator.id = 'auto-save-indicator';
                    indicator.style.cssText = `
                        position: fixed;
                        top: 20px;
                        right: 20px;
                        background: #10b981;
                        color: white;
                        padding: 8px 16px;
                        border-radius: 6px;
                        font-size: 14px;
                        z-index: 1000;
                        opacity: 0;
                        transition: opacity 0.3s ease;
                    `;
                    document.body.appendChild(indicator);
                }
                
                indicator.textContent = 'Auto-saved';
                indicator.style.opacity = '1';
                
                setTimeout(() => {
                    indicator.style.opacity = '0';
                }, 2000);
            }
            
            // Show restore notification
            function showRestoreNotification() {
                let notification = document.getElementById('restore-notification');
                if (!notification) {
                    notification = document.createElement('div');
                    notification.id = 'restore-notification';
                    notification.style.cssText = `
                        position: fixed;
                        top: 20px;
                        left: 50%;
                        transform: translateX(-50%);
                        background: #3b82f6;
                        color: white;
                        padding: 12px 24px;
                        border-radius: 8px;
                        font-size: 14px;
                        z-index: 1000;
                        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                    `;
                    document.body.appendChild(notification);
                }
                
                notification.innerHTML = `
                    <i class="fas fa-info-circle" style="margin-right: 8px;"></i>
                    Form data restored from previous session
                    <button onclick="this.parentElement.remove()" style="background: none; border: none; color: white; margin-left: 12px; cursor: pointer;">
                        <i class="fas fa-times"></i>
                    </button>
                `;
                
                setTimeout(() => {
                    if (notification.parentElement) {
                        notification.remove();
                    }
                }, 5000);
            }
            
            // Warn before leaving with unsaved changes
            window.addEventListener('beforeunload', function(e) {
                if (window.xpmFormDirty) {
                    e.preventDefault();
                    e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
                    return e.returnValue;
                }
            });
            
            // Start auto-save when form is modified
            applicationForm.addEventListener('input', function() {
                markUnsaved(true);
                if (!autoSaveInterval) {
                    autoSaveInterval = setInterval(autoSaveForm, 2000);
                }
            });
            
            // Clear auto-save on successful form submission
            applicationForm.addEventListener('submit', function() {
                clearSavedFormData();
                if (autoSaveInterval) {
                    clearInterval(autoSaveInterval);
                    autoSaveInterval = null;
                }
            });
            
            // Restore form data on page load
            restoreFormData();
            
            const fileInput = document.getElementById('resume');
            const fileUploadWrapper = document.getElementById('fileUploadWrapper');
            const fileUploadInfo = document.getElementById('fileUploadInfo');
            const fileSelectedInfo = document.getElementById('fileSelectedInfo');
            const selectedFileName = document.getElementById('selectedFileName');
            const selectedFileSize = document.getElementById('selectedFileSize');
            const removeFileBtn = document.getElementById('removeFile');
            const uploadIcon = document.getElementById('uploadIcon');
            
            // Handle file selection
            if (fileInput) {
                fileInput.addEventListener('change', function(e) {
                    const file = e.target.files[0];
                    
                    if (file) {
                        // Show selected file info
                        if (selectedFileName) selectedFileName.textContent = file.name;
                        if (selectedFileSize) selectedFileSize.textContent = formatFileSize(file.size);
                        
                        // Update UI
                        if (fileUploadInfo) fileUploadInfo.style.display = 'none';
                        if (fileSelectedInfo) fileSelectedInfo.style.display = 'flex';
                        if (fileUploadWrapper) fileUploadWrapper.classList.add('has-file');
                        if (uploadIcon) {
                            uploadIcon.className = 'fas fa-file-alt';
                            uploadIcon.style.color = '#2563eb';
                        }
                    
                        // Validate type by extension (MIME is unreliable across OS/browsers)
                        if (!isAllowedApplicationDoc(file)) {
                            xpmAlert('Please select a PDF or Word file (.pdf, .doc, .docx).');
                            clearFileSelection();
                            return;
                        }
                        
                        // Validate file size (5MB)
                        const maxSize = 5 * 1024 * 1024; // 5MB in bytes
                        if (file.size > maxSize) {
                            xpmAlert('File size must be less than 5MB.');
                            clearFileSelection();
                            return;
                        }
                    } else {
                        clearFileSelection();
                    }
                });
            }
            
            // Handle remove file button
            if (removeFileBtn) {
                removeFileBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    clearFileSelection();
                });
            }
            
            // Prevent file selected info from triggering file input
            if (fileSelectedInfo) {
                fileSelectedInfo.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                });
            }
            
            // Handle click on upload area to trigger file input (only when no file selected)
            if (fileUploadWrapper) {
                fileUploadWrapper.addEventListener('click', function(e) {
                    // Don't trigger if clicking on remove button or file info
                    if (e.target.closest('#removeFile') || e.target.closest('#fileSelectedInfo')) {
                        e.preventDefault();
                        e.stopPropagation();
                        return;
                    }
                });
            }
            
            // Function to clear file selection
            function clearFileSelection() {
                if (fileInput) fileInput.value = '';
                if (fileUploadInfo) fileUploadInfo.style.display = 'block';
                if (fileSelectedInfo) fileSelectedInfo.style.display = 'none';
                if (fileUploadWrapper) fileUploadWrapper.classList.remove('has-file');
                if (uploadIcon) {
                    uploadIcon.className = 'fas fa-file-upload';
                    uploadIcon.style.color = '#6b7280';
                }
            }
            
            // Function to format file size
            function formatFileSize(bytes) {
                if (bytes === 0) return '0 Bytes';
                const k = 1024;
                const sizes = ['Bytes', 'KB', 'MB', 'GB'];
                const i = Math.floor(Math.log(bytes) / Math.log(k));
                return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
            }
            
            // Cover letter file upload handling
            const coverLetterInput = document.getElementById('cover_letter');
            const coverLetterUploadWrapper = document.getElementById('coverLetterUploadWrapper');
            const coverLetterFileUploadInfo = document.getElementById('coverLetterFileUploadInfo');
            const coverLetterFileSelectedInfo = document.getElementById('coverLetterFileSelectedInfo');
            const coverLetterSelectedFileName = document.getElementById('coverLetterSelectedFileName');
            const coverLetterSelectedFileSize = document.getElementById('coverLetterSelectedFileSize');
            const removeCoverLetterFileBtn = document.getElementById('removeCoverLetterFile');
            const coverLetterUploadIcon = document.getElementById('coverLetterUploadIcon');
            
            // Handle cover letter file selection
            coverLetterInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                
                if (file) {
                    // Show selected file info
                    coverLetterSelectedFileName.textContent = file.name;
                    coverLetterSelectedFileSize.textContent = formatFileSize(file.size);
                    
                    // Update UI
                    coverLetterFileUploadInfo.style.display = 'none';
                    coverLetterFileSelectedInfo.style.display = 'flex';
                    coverLetterUploadWrapper.classList.add('has-file');
                    coverLetterUploadIcon.className = 'fas fa-file-alt';
                    coverLetterUploadIcon.style.color = '#2563eb';
                    
                    if (!isAllowedApplicationDoc(file)) {
                        xpmAlert('Please select a PDF or Word file (.pdf, .doc, .docx).');
                        clearCoverLetterFileSelection();
                        return;
                    }
                    
                    // Validate file size (5MB)
                    const maxSize = 5 * 1024 * 1024; // 5MB in bytes
                    if (file.size > maxSize) {
                        xpmAlert('Cover letter file size must be less than 5MB.');
                        clearCoverLetterFileSelection();
                        return;
                    }
                } else {
                    clearCoverLetterFileSelection();
                }
            });
            
            // Handle remove cover letter file button
            removeCoverLetterFileBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                clearCoverLetterFileSelection();
            });
            
            // Prevent cover letter file selected info from triggering file input
            coverLetterFileSelectedInfo.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
            });
            
            // Handle click on cover letter upload area to trigger file input (only when no file selected)
            coverLetterUploadWrapper.addEventListener('click', function(e) {
                // Don't trigger if clicking on remove button or file info
                if (e.target.closest('#removeCoverLetterFile') || e.target.closest('#coverLetterFileSelectedInfo')) {
                    e.preventDefault();
                    e.stopPropagation();
                    return;
                }
            });
            
            // Function to clear cover letter file selection
            function clearCoverLetterFileSelection() {
                coverLetterInput.value = '';
                coverLetterFileUploadInfo.style.display = 'block';
                coverLetterFileSelectedInfo.style.display = 'none';
                coverLetterUploadWrapper.classList.remove('has-file');
                coverLetterUploadIcon.className = 'fas fa-file-upload';
                coverLetterUploadIcon.style.color = '#6b7280';
            }
            
            // Form validation - only require file for "with resume" applications
            const formElement = document.querySelector('.application-form');
            if (formElement) {
                formElement.addEventListener('submit', function(e) {
                    const applicationType = document.querySelector('input[name="application_type"]:checked').value;
                    
                    // Only validate file upload for "with resume" applications
                    if (applicationType === 'with_resume') {
                        const file = fileInput.files[0];
                        if (!file) {
                            e.preventDefault();
                            xpmAlert('Please select a resume file to upload.');
                            fileInput.focus();
                            return false;
                        }
                        if (!isAllowedApplicationDoc(file)) {
                            e.preventDefault();
                            xpmAlert('Please upload a resume as PDF or Word (.pdf, .doc, .docx).');
                            fileInput.focus();
                            return false;
                        }
                    }
                });
            }
        });
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('#job-application-form details.entry-card').forEach(function(card) {
                var hasValue = Array.prototype.some.call(card.querySelectorAll('input, textarea'), function(field) {
                    return field.value.trim() !== '';
                });
                if (hasValue) card.open = true;
            });
        });
    </script>
</body>
</html>
