<?php
// Include shared database connection
require_once 'includes/database.php';
require_once 'config/email_config.php';

$pdo = get_db_connection();
$job = null;
$error = '';
$success = '';

// Handle success message from redirect
if (isset($_GET['success']) && $_GET['success'] == '1') {
    $success = "Your application has been submitted successfully! We'll review it and get back to you soon.";
    // Clear any existing error messages when showing success
    $error = '';
}

// Email notification function for job applications
function sendJobApplicationNotification($job_id, $name, $email, $phone, $ic_number, $resume_path, $cover_letter_path) {
    // Job application emails from configuration
    $job_emails = [JOB_APPLICATION_EMAIL, JOB_APPLICATION_EMAIL_2];
    
    // Get job details
    global $pdo;
    $stmt = $pdo->prepare("SELECT title, company_name, location, type FROM jobs WHERE id = ?");
    $stmt->execute([$job_id]);
    $job = $stmt->fetch();
    
    $job_title = $job ? $job['title'] : 'Unknown Position';
    $company_name = $job ? $job['company_name'] : 'Unknown Company';
    $location = $job ? $job['location'] : 'N/A';
    $employment_type = $job ? $job['type'] : 'N/A';
    
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
                <div class='field'><strong>Phone:</strong> " . htmlspecialchars($phone) . "</div>
                <div class='field'><strong>IC Number:</strong> " . htmlspecialchars($ic_number) . "</div>
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
        mail($email_address, $subject, $message, $headers);
    }
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
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $job_id = $_POST['job_id'] ?? null;
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $ic_number = trim($_POST['ic_number'] ?? '');
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
    if (empty($name) || empty($email) || empty($job_id) || empty($ic_number)) {
        $error = "Please fill in all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
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
        $education_details = trim($_POST['education_details'] ?? '');
        
        // References
        $reference1_name = trim($_POST['reference1_name'] ?? '');
        $reference1_position = trim($_POST['reference1_position'] ?? '');
        $reference1_company = trim($_POST['reference1_company'] ?? '');
        $reference1_phone = trim($_POST['reference1_phone'] ?? '');
        $reference1_email = trim($_POST['reference1_email'] ?? '');
        
        $reference2_name = trim($_POST['reference2_name'] ?? '');
        $reference2_position = trim($_POST['reference2_position'] ?? '');
        $reference2_company = trim($_POST['reference2_company'] ?? '');
        $reference2_phone = trim($_POST['reference2_phone'] ?? '');
        $reference2_email = trim($_POST['reference2_email'] ?? '');
        
        $reference3_name = trim($_POST['reference3_name'] ?? '');
        $reference3_position = trim($_POST['reference3_position'] ?? '');
        $reference3_company = trim($_POST['reference3_company'] ?? '');
        $reference3_phone = trim($_POST['reference3_phone'] ?? '');
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
        
        // Validation for required fields when without resume
        if ($application_type === 'without_resume' && empty($error)) {
            if (empty($education_level) || empty($reference1_name) || empty($reference1_phone)) {
                $error = "Please fill in all required fields for detailed application.";
            }
        }
        
        if (empty($error)) {
            try {
                // Strict SQL mode (common on live hosts) rejects '' for ENUM/DATE — use NULL instead
                $education_level = ($education_level !== '') ? $education_level : null;
                $availability_date = ($availability_date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $availability_date))
                    ? $availability_date
                    : null;
                $resume_path = ($resume_path !== '') ? $resume_path : null;
                $cover_letter_path = ($cover_letter_path !== '') ? $cover_letter_path : null;

                $stmt = $pdo->prepare("
                    INSERT INTO applications (job_id, name, email, phone, ic_number, application_type, resume_path, cover_letter_path, 
                    education_level, education_details, 
                    reference1_name, reference1_position, reference1_company, reference1_phone, reference1_email,
                    reference2_name, reference2_position, reference2_company, reference2_phone, reference2_email,
                    reference3_name, reference3_position, reference3_company, reference3_phone, reference3_email,
                    employment1_company, employment1_position, employment1_duration, employment1_description,
                    employment2_company, employment2_position, employment2_duration, employment2_description,
                    employment3_company, employment3_position, employment3_duration, employment3_description,
                    technical_skills, soft_skills, certifications, languages,
                    expected_salary, availability_date, additional_notes, interviewer_comment, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $job_id, $name, $email, $phone, $ic_number, $application_type, $resume_path, $cover_letter_path,
                    $education_level, $education_details,
                    $reference1_name, $reference1_position, $reference1_company, $reference1_phone, $reference1_email,
                    $reference2_name, $reference2_position, $reference2_company, $reference2_phone, $reference2_email,
                    $reference3_name, $reference3_position, $reference3_company, $reference3_phone, $reference3_email,
                    $employment1_company, $employment1_position, $employment1_duration, $employment1_description,
                    $employment2_company, $employment2_position, $employment2_duration, $employment2_description,
                    $employment3_company, $employment3_position, $employment3_duration, $employment3_description,
                    $technical_skills, $soft_skills, $certifications, $languages,
                    $expected_salary, $availability_date, $additional_notes, null, 'pending'
                ]);
                
                // Try to send email notification (don't let email errors stop the process)
                try {
                    sendJobApplicationNotification($job_id, $name, $email, $phone, $ic_number, $resume_path, $cover_letter_path);
                } catch (Exception $emailError) {
                    // Log email error but don't stop the process
                    error_log("Email notification failed: " . $emailError->getMessage());
                }
                
                // Redirect to prevent form resubmission and show success message
                $redirect_url = $_SERVER['PHP_SELF'] . "?success=1&job_id=" . $job_id;
                header("Location: " . $redirect_url);
                exit;
                
            } catch (PDOException $e) {
                error_log("Job application insert failed: " . $e->getMessage());
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
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="assets/images/favicon.ico">
    
    <style>
        /* Fix for the Back to Jobs button */
        .form-actions .btn-secondary {
            background: #6b7280 !important;
            color: white !important;
            border: 2px solid #6b7280 !important;
            opacity: 1 !important;
            visibility: visible !important;
        }
        
        .form-actions .btn-secondary:hover {
            background: #4b5563 !important;
            border-color: #4b5563 !important;
            transform: translateY(-2px) !important;
            box-shadow: 0 8px 25px rgba(107, 114, 128, 0.3) !important;
        }
        
        /* Ensure all form action buttons are visible */
        .form-actions {
            display: flex !important;
            gap: 12px !important;
            justify-content: flex-start !important;
            align-items: center !important;
            flex-wrap: wrap !important;
        }
        
        .form-actions .btn {
            position: relative !important;
            z-index: 10 !important;
            pointer-events: auto !important;
        }

        

    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar" id="navbar">
        <div class="nav-container">
            <div class="nav-logo">
                <a href="index.html">
                    <img src="assets/images/XPMLogo.png" alt="Xpress Point Management Services" class="logo-img">
                </a>
            </div>
            
            <div class="nav-menu" id="nav-menu">
                <a href="index.html#hero" class="nav-link">Home</a>
                <a href="index.html#about" class="nav-link">About</a>
                <a href="index.html#initiator" class="nav-link">Founder</a>
                <a href="index.html#services" class="nav-link">Services</a>
                <a href="index.html#facilities" class="nav-link">Facilities</a>
                <a href="index.html#testimonials" class="nav-link">Testimonials</a>
                <a href="programmes.php" class="nav-link">Programmes</a>
                <a href="jobs.php" class="nav-link active">Careers</a>
                <a href="index.html#portfolio" class="nav-link">Portfolio</a>
                <a href="index.html#contact" class="nav-link">Contact</a>
            </div>
            
            <div class="nav-toggle" id="nav-toggle">
                <span class="bar"></span>
                <span class="bar"></span>
                <span class="bar"></span>
            </div>
        </div>
    </nav>

    <!-- Job Application Section -->
    <section id="job-application" class="programme">
        <!-- Background Elements -->
        <div class="programme-background">
            <div class="programme-gradient-bg"></div>
            <div class="programme-grid-pattern"></div>
            <div class="programme-particles">
                <div class="programme-particle programme-particle-1"></div>
                <div class="programme-particle programme-particle-2"></div>
                <div class="programme-particle programme-particle-3"></div>
            </div>
            <div class="programme-shapes">
                <div class="programme-shape programme-shape-1"></div>
                <div class="programme-shape programme-shape-2"></div>
                <div class="programme-shape programme-shape-3"></div>
                <div class="programme-shape programme-shape-4"></div>
                <div class="programme-shape programme-shape-5"></div>
            </div>
        </div>
        
        <div class="container">
            <div class="section-header">
                <div class="section-badge">
                    <i class="fas fa-briefcase"></i>
                    <span>Job Application</span>
                </div>
                <h2 class="section-title">
                    Apply for Your Dream Position
                </h2>
                <p class="section-subtitle">
                    Submit your application and take the next step in your career journey
                </p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <span><?php echo htmlspecialchars($success); ?></span>
                </div>
            <?php endif; ?>

            <div class="application-container">
                <?php if ($job): ?>
                    <!-- Job Details Card -->
                    <div class="job-details-card">
                        <div class="job-header">
                            <div class="job-icon">
                                <i class="<?php echo htmlspecialchars($job['job_icon'] ?? 'fas fa-briefcase'); ?>"></i>
                            </div>
                            <div class="job-badge">
                                <span><?php echo htmlspecialchars($job['job_badge'] ?? $job['type']); ?></span>
                            </div>
                        </div>
                        
                        <div class="job-logo">
                            <?php if (!empty($job['poster_image'])): ?>
                            <img src="<?php echo htmlspecialchars($job['poster_image']); ?>" 
                                 alt="<?php echo htmlspecialchars($job['title']); ?> Logo"
                                 class="job-logo-img">
                            <?php else: ?>
                            <div class="default-logo">
                                <i class="fas fa-building"></i>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($job['company_name'])): ?>
                            <div class="company-name">
                                <span><?php echo htmlspecialchars($job['company_name']); ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="job-content">
                            <h3><?php echo htmlspecialchars($job['title']); ?></h3>
                            <p class="job-description"><?php echo htmlspecialchars($job['description']); ?></p>
                            
                            <div class="job-details">
                                <div class="detail-item">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <span><?php echo htmlspecialchars($job['location']); ?></span>
                                </div>

                                <div class="detail-item">
                                    <i class="fas fa-clock"></i>
                                    <span><?php echo htmlspecialchars($job['type']); ?></span>
                                </div>
                                <?php if (!empty($job['salary_range'])): ?>
                                <div class="detail-item">
                                    <i class="fas fa-dollar-sign"></i>
                                    <span><?php echo htmlspecialchars($job['salary_range']); ?></span>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($job['experience_level'])): ?>
                                <div class="detail-item">
                                    <i class="fas fa-user-tie"></i>
                                    <span>
                                        <?php 
                                        $level = htmlspecialchars($job['experience_level']);
                                        // Check if it's a predefined level that needs "Level" suffix
                                        $predefined_levels = ['entry', 'mid', 'senior', 'executive'];
                                        if (in_array(strtolower($level), $predefined_levels)) {
                                            echo ucfirst($level) . ' Level';
                                        } else {
                                            // For custom levels, display as is
                                            echo $level;
                                        }
                                        ?>
                                    </span>
                                </div>
                                <?php endif; ?>

                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Application Form -->
                <div class="application-form-card">
                    <div class="form-header">
                        <h3>Application Form</h3>
                        <p>Please fill out the form below to apply for this position</p>
                    </div>

                    <form method="POST" enctype="multipart/form-data" class="application-form" id="job-application-form">
                        <?php if ($job): ?>
                            <input type="hidden" name="job_id" value="<?php echo $job['id']; ?>">
                        <?php endif; ?>
                        
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
                                <label for="phone">Phone Number</label>
                                <div class="input-wrapper">
                                    <i class="fas fa-phone"></i>
                                    <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($phone ?? ''); ?>" placeholder="Enter your phone number">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="ic_number">IC Number *</label>
                                <div class="input-wrapper">
                                    <i class="fas fa-id-card"></i>
                                    <input type="text" id="ic_number" name="ic_number" value="<?php echo htmlspecialchars($ic_number ?? ''); ?>" required placeholder="Enter your IC number">
                                </div>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="<?php echo $job ? 'selected_job' : 'job_select'; ?>">Position Applied For *</label>
                                <div class="input-wrapper">
                                    <i class="fas fa-briefcase"></i>
                                    <?php if ($job): ?>
                                        <!-- Show selected job as read-only -->
                                        <input type="text" id="selected_job" value="<?php echo htmlspecialchars($job['title']); ?> - <?php echo htmlspecialchars($job['company_name']); ?>" readonly style="background-color: #f8f9fa; color: #6b7280;">
                                    <?php else: ?>
                                        <!-- Show job selection dropdown -->
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
                        </div>

                        <!-- Application Type Selection -->
                        <div class="form-group">
                            <label>Application Type *</label>
                            <div class="application-type-selection" style="display: flex; gap: 20px; margin-bottom: 20px;">
                                <label class="application-type-option" style="flex: 1; padding: 20px; border: 2px solid #e5e7eb; border-radius: 12px; cursor: pointer; transition: all 0.3s; text-align: center;" onmouseover="this.style.borderColor='#3b82f6'" onmouseout="if(!this.querySelector('input').checked) this.style.borderColor='#e5e7eb'">
                                    <input type="radio" name="application_type" value="with_resume" checked style="margin-right: 8px;" onchange="toggleApplicationType()">
                                    <div style="font-weight: 600; color: #1f2937; margin-bottom: 8px;">
                                        <i class="fas fa-file-pdf" style="color: #3b82f6; margin-right: 8px;"></i>
                                        I have a Resume/CV
                                    </div>
                                    <div style="font-size: 14px; color: #6b7280;">
                                        Upload your resume and cover letter
                                    </div>
                                </label>
                                
                                <label class="application-type-option" style="flex: 1; padding: 20px; border: 2px solid #e5e7eb; border-radius: 12px; cursor: pointer; transition: all 0.3s; text-align: center;" onmouseover="this.style.borderColor='#3b82f6'" onmouseout="if(!this.querySelector('input').checked) this.style.borderColor='#e5e7eb'">
                                    <input type="radio" name="application_type" value="without_resume" style="margin-right: 8px;" onchange="toggleApplicationType()">
                                    <div style="font-weight: 600; color: #1f2937; margin-bottom: 8px;">
                                        <i class="fas fa-edit" style="color: #10b981; margin-right: 8px;"></i>
                                        I don't have a Resume/CV
                                    </div>
                                    <div style="font-size: 14px; color: #6b7280;">
                                        Fill in detailed information instead
                                    </div>
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
                                    <small>Maximum file size: 5MB</small>
                                </div>
                                <div class="file-selected-info" id="fileSelectedInfo" style="display: none;">
                                    <i class="fas fa-file-alt"></i>
                                    <span id="selectedFileName"></span>
                                    <small id="selectedFileSize"></small>
                                    <button type="button" id="removeFile" class="remove-file-btn">
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
                                    <button type="button" id="removeCoverLetterFile" class="remove-file-btn">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        </div>

                        <!-- Detailed Application Section (for without_resume) -->
                        <div id="detailed-section" class="detailed-section" style="display: none;">
                            <div style="background: #f0f9ff; border: 1px solid #0ea5e9; border-radius: 12px; padding: 20px; margin-bottom: 24px;">
                                <h4 style="margin: 0 0 12px 0; color: #1f2937; font-size: 16px; font-weight: 600; display: flex; align-items: center; gap: 8px;">
                                    <i class="fas fa-info-circle" style="color: #0ea5e9;"></i>
                                    Detailed Application Information
                                </h4>
                                <p style="margin: 0; color: #6b7280; font-size: 14px;">
                                    Please provide detailed information about your background, experience, and qualifications.
                                </p>
                            </div>

                            <!-- Education Section -->
                            <div class="form-section" style="margin-bottom: 32px;">
                                <h4 style="margin: 0 0 20px 0; color: #1f2937; font-size: 18px; font-weight: 600; display: flex; align-items: center; gap: 8px;">
                                    <i class="fas fa-graduation-cap" style="color: #3b82f6;"></i>
                                    Education
                                </h4>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="education_level">Education Level *</label>
                                        <div class="input-wrapper">
                                            <i class="fas fa-certificate"></i>
                                            <select id="education_level" name="education_level">
                                                <option value="">Select your education level</option>
                                                <option value="high_school">High School</option>
                                                <option value="diploma">Diploma</option>
                                                <option value="bachelor">Bachelor's Degree</option>
                                                <option value="master">Master's Degree</option>
                                                <option value="phd">PhD</option>
                                                <option value="other">Other</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="education_details">Education Details</label>
                                    <div class="input-wrapper">
                                        <i class="fas fa-university"></i>
                                        <textarea id="education_details" name="education_details" rows="3" placeholder="Please provide details about your education, including institution names, degrees, and graduation years"></textarea>
                                    </div>
                                </div>
                            </div>

                            <!-- References Section -->
                            <div class="form-section" style="margin-bottom: 32px;">
                                <h4 style="margin: 0 0 20px 0; color: #1f2937; font-size: 18px; font-weight: 600; display: flex; align-items: center; gap: 8px;">
                                    <i class="fas fa-users" style="color: #10b981;"></i>
                                    References (at least 1 required)
                                </h4>
                                
                                <!-- Reference 1 -->
                                <div class="reference-section" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; margin-bottom: 16px;">
                                    <h5 style="margin: 0 0 16px 0; color: #374151; font-size: 16px; font-weight: 600;">Reference 1 *</h5>
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="reference1_name">Name *</label>
                                            <div class="input-wrapper">
                                                <i class="fas fa-user"></i>
                                                <input type="text" id="reference1_name" name="reference1_name" placeholder="Full name">
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label for="reference1_position">Position</label>
                                            <div class="input-wrapper">
                                                <i class="fas fa-briefcase"></i>
                                                <input type="text" id="reference1_position" name="reference1_position" placeholder="Job title">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="reference1_company">Company</label>
                                            <div class="input-wrapper">
                                                <i class="fas fa-building"></i>
                                                <input type="text" id="reference1_company" name="reference1_company" placeholder="Company name">
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label for="reference1_phone">Phone *</label>
                                            <div class="input-wrapper">
                                                <i class="fas fa-phone"></i>
                                                <input type="tel" id="reference1_phone" name="reference1_phone" placeholder="Phone number">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="reference1_email">Email</label>
                                        <div class="input-wrapper">
                                            <i class="fas fa-envelope"></i>
                                            <input type="email" id="reference1_email" name="reference1_email" placeholder="Email address">
                                        </div>
                                    </div>
                                </div>

                                <!-- Reference 2 -->
                                <div class="reference-section" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; margin-bottom: 16px;">
                                    <h5 style="margin: 0 0 16px 0; color: #374151; font-size: 16px; font-weight: 600;">Reference 2 (Optional)</h5>
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="reference2_name">Name</label>
                                            <div class="input-wrapper">
                                                <i class="fas fa-user"></i>
                                                <input type="text" id="reference2_name" name="reference2_name" placeholder="Full name">
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label for="reference2_position">Position</label>
                                            <div class="input-wrapper">
                                                <i class="fas fa-briefcase"></i>
                                                <input type="text" id="reference2_position" name="reference2_position" placeholder="Job title">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="reference2_company">Company</label>
                                            <div class="input-wrapper">
                                                <i class="fas fa-building"></i>
                                                <input type="text" id="reference2_company" name="reference2_company" placeholder="Company name">
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label for="reference2_phone">Phone</label>
                                            <div class="input-wrapper">
                                                <i class="fas fa-phone"></i>
                                                <input type="tel" id="reference2_phone" name="reference2_phone" placeholder="Phone number">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="reference2_email">Email</label>
                                        <div class="input-wrapper">
                                            <i class="fas fa-envelope"></i>
                                            <input type="email" id="reference2_email" name="reference2_email" placeholder="Email address">
                                        </div>
                                    </div>
                                </div>

                                <!-- Reference 3 -->
                                <div class="reference-section" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; margin-bottom: 16px;">
                                    <h5 style="margin: 0 0 16px 0; color: #374151; font-size: 16px; font-weight: 600;">Reference 3 (Optional)</h5>
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="reference3_name">Name</label>
                                            <div class="input-wrapper">
                                                <i class="fas fa-user"></i>
                                                <input type="text" id="reference3_name" name="reference3_name" placeholder="Full name">
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label for="reference3_position">Position</label>
                                            <div class="input-wrapper">
                                                <i class="fas fa-briefcase"></i>
                                                <input type="text" id="reference3_position" name="reference3_position" placeholder="Job title">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="reference3_company">Company</label>
                                            <div class="input-wrapper">
                                                <i class="fas fa-building"></i>
                                                <input type="text" id="reference3_company" name="reference3_company" placeholder="Company name">
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label for="reference3_phone">Phone</label>
                                            <div class="input-wrapper">
                                                <i class="fas fa-phone"></i>
                                                <input type="tel" id="reference3_phone" name="reference3_phone" placeholder="Phone number">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="reference3_email">Email</label>
                                        <div class="input-wrapper">
                                            <i class="fas fa-envelope"></i>
                                            <input type="email" id="reference3_email" name="reference3_email" placeholder="Email address">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Employment History Section -->
                            <div class="form-section" style="margin-bottom: 32px;">
                                <h4 style="margin: 0 0 20px 0; color: #1f2937; font-size: 18px; font-weight: 600; display: flex; align-items: center; gap: 8px;">
                                    <i class="fas fa-briefcase" style="color: #f59e0b;"></i>
                                    Employment History
                                </h4>
                                
                                <!-- Employment 1 -->
                                <div class="employment-section" style="background: #fefce8; border: 1px solid #f59e0b; border-radius: 8px; padding: 20px; margin-bottom: 16px;">
                                    <h5 style="margin: 0 0 16px 0; color: #374151; font-size: 16px; font-weight: 600;">Most Recent Position</h5>
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="employment1_company">Company</label>
                                            <div class="input-wrapper">
                                                <i class="fas fa-building"></i>
                                                <input type="text" id="employment1_company" name="employment1_company" placeholder="Company name">
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label for="employment1_position">Position</label>
                                            <div class="input-wrapper">
                                                <i class="fas fa-briefcase"></i>
                                                <input type="text" id="employment1_position" name="employment1_position" placeholder="Job title">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="employment1_duration">Duration</label>
                                            <div class="input-wrapper">
                                                <i class="fas fa-calendar"></i>
                                                <input type="text" id="employment1_duration" name="employment1_duration" placeholder="e.g., Jan 2020 - Present">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="employment1_description">Job Description</label>
                                        <div class="input-wrapper">
                                            <i class="fas fa-tasks"></i>
                                            <textarea id="employment1_description" name="employment1_description" rows="3" placeholder="Describe your responsibilities and achievements"></textarea>
                                        </div>
                                    </div>
                                </div>

                                <!-- Employment 2 -->
                                <div class="employment-section" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; margin-bottom: 16px;">
                                    <h5 style="margin: 0 0 16px 0; color: #374151; font-size: 16px; font-weight: 600;">Previous Position (Optional)</h5>
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="employment2_company">Company</label>
                                            <div class="input-wrapper">
                                                <i class="fas fa-building"></i>
                                                <input type="text" id="employment2_company" name="employment2_company" placeholder="Company name">
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label for="employment2_position">Position</label>
                                            <div class="input-wrapper">
                                                <i class="fas fa-briefcase"></i>
                                                <input type="text" id="employment2_position" name="employment2_position" placeholder="Job title">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="employment2_duration">Duration</label>
                                            <div class="input-wrapper">
                                                <i class="fas fa-calendar"></i>
                                                <input type="text" id="employment2_duration" name="employment2_duration" placeholder="e.g., Jan 2018 - Dec 2019">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="employment2_description">Job Description</label>
                                        <div class="input-wrapper">
                                            <i class="fas fa-tasks"></i>
                                            <textarea id="employment2_description" name="employment2_description" rows="3" placeholder="Describe your responsibilities and achievements"></textarea>
                                        </div>
                                    </div>
                                </div>

                                <!-- Employment 3 -->
                                <div class="employment-section" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; margin-bottom: 16px;">
                                    <h5 style="margin: 0 0 16px 0; color: #374151; font-size: 16px; font-weight: 600;">Previous Position (Optional)</h5>
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="employment3_company">Company</label>
                                            <div class="input-wrapper">
                                                <i class="fas fa-building"></i>
                                                <input type="text" id="employment3_company" name="employment3_company" placeholder="Company name">
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label for="employment3_position">Position</label>
                                            <div class="input-wrapper">
                                                <i class="fas fa-briefcase"></i>
                                                <input type="text" id="employment3_position" name="employment3_position" placeholder="Job title">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="employment3_duration">Duration</label>
                                            <div class="input-wrapper">
                                                <i class="fas fa-calendar"></i>
                                                <input type="text" id="employment3_duration" name="employment3_duration" placeholder="e.g., Jan 2016 - Dec 2017">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="employment3_description">Job Description</label>
                                        <div class="input-wrapper">
                                            <i class="fas fa-tasks"></i>
                                            <textarea id="employment3_description" name="employment3_description" rows="3" placeholder="Describe your responsibilities and achievements"></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Skills and Qualifications Section -->
                            <div class="form-section" style="margin-bottom: 32px;">
                                <h4 style="margin: 0 0 20px 0; color: #1f2937; font-size: 18px; font-weight: 600; display: flex; align-items: center; gap: 8px;">
                                    <i class="fas fa-cogs" style="color: #8b5cf6;"></i>
                                    Skills and Qualifications
                                </h4>
                                
                                <div class="form-group">
                                    <label for="technical_skills">Technical Skills</label>
                                    <div class="input-wrapper">
                                        <i class="fas fa-laptop-code"></i>
                                        <textarea id="technical_skills" name="technical_skills" rows="3" placeholder="List your technical skills, software proficiency, programming languages, etc."></textarea>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="soft_skills">Soft Skills</label>
                                    <div class="input-wrapper">
                                        <i class="fas fa-handshake"></i>
                                        <textarea id="soft_skills" name="soft_skills" rows="3" placeholder="List your soft skills like communication, leadership, teamwork, etc."></textarea>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="certifications">Certifications</label>
                                    <div class="input-wrapper">
                                        <i class="fas fa-certificate"></i>
                                        <textarea id="certifications" name="certifications" rows="3" placeholder="List any professional certifications, licenses, or training you have"></textarea>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="languages">Languages</label>
                                    <div class="input-wrapper">
                                        <i class="fas fa-language"></i>
                                        <textarea id="languages" name="languages" rows="2" placeholder="List languages you speak and your proficiency level"></textarea>
                                    </div>
                                </div>
                            </div>

                            <!-- Additional Information Section -->
                            <div class="form-section" style="margin-bottom: 32px;">
                                <h4 style="margin: 0 0 20px 0; color: #1f2937; font-size: 18px; font-weight: 600; display: flex; align-items: center; gap: 8px;">
                                    <i class="fas fa-info-circle" style="color: #06b6d4;"></i>
                                    Additional Information
                                </h4>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="expected_salary">Expected Salary</label>
                                        <div class="input-wrapper">
                                            <i class="fas fa-dollar-sign"></i>
                                            <input type="text" id="expected_salary" name="expected_salary" placeholder="e.g., RM 3000 - RM 4000">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="availability_date">Availability Date</label>
                                        <div class="input-wrapper">
                                            <i class="fas fa-calendar-alt"></i>
                                            <input type="date" id="availability_date" name="availability_date">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="additional_notes">Additional Notes</label>
                                    <div class="input-wrapper">
                                        <i class="fas fa-sticky-note"></i>
                                        <textarea id="additional_notes" name="additional_notes" rows="4" placeholder="Any additional information you'd like to share about yourself, your goals, or why you're interested in this position"></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary" style="background: #2563eb; color: white; padding: 1rem 2rem; border-radius: 12px; border: none; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 0.5rem;">
                                <i class="fas fa-paper-plane"></i>
                                <span>Submit Application</span>
                            </button>
                            <a href="jobs.php" class="btn btn-secondary" style="background: transparent; color: #6b7280; padding: 1rem 2rem; border-radius: 12px; border: 2px solid #d1d5db; font-weight: 600; text-decoration: none; display: flex; align-items: center; gap: 0.5rem;">
                                <i class="fas fa-arrow-left"></i>
                                <span>Back to Jobs</span>
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <div class="footer-logo">
                        <img src="assets/images/XPMLogo.png" alt="Xpress Point Management Services Logo">
                    </div>
                    <p>Empowering organizations with comprehensive HR services and training solutions since 2010.</p>
                    <div class="social-links">
                        <a href="https://www.linkedin.com/in/icol-chua-18248623/" target="_blank" rel="noopener noreferrer"><i class="fab fa-linkedin"></i></a>
                        <a href="https://www.facebook.com/XpressPointManagementServices" target="_blank" rel="noopener noreferrer"><i class="fab fa-facebook"></i></a>
                        <a href="https://www.instagram.com/xpresspoint_mlk/" target="_blank" rel="noopener noreferrer"><i class="fab fa-instagram"></i></a>
                        <a href="https://api.whatsapp.com/send?phone=601120816113&text&context=Afe5aPTzugSnNb11RVXu8gplTXAka-fQ3OSnoIHLoEqmW6cDcc2-nda4uIC8TFMPge3mZXDvEk9uqftJnydY7o4PQeFu4teMCQA9M_MxuKCiQ09tgidUXLf8Za7t5li-eJv1K9QAXOxfmXK4qCobgVak8Q&source&app=facebook" target="_blank" rel="noopener noreferrer"><i class="fab fa-whatsapp"></i></a>
                    </div>
                </div>
                
                <div class="footer-section">
                    <h4>Quick Links</h4>
                    <ul>
                        <li><a href="index.html#about">About Us</a></li>
                        <li><a href="index.html#services">Services</a></li>
                        <li><a href="programmes.php">Training Programmes</a></li>
                        <li><a href="jobs.php">Careers</a></li>
                        <li><a href="index.html#contact">Contact</a></li>
                    </ul>
                </div>
                
                <div class="footer-section">
                    <h4>Why Choose Us</h4>
                    <ul>
                        <li><a href="index.html#about">20+ Years Experience</a></li>
                        <li><a href="index.html#services">Certified Professionals</a></li>
                        <li><a href="index.html#services">Custom Solutions</a></li>
                        <li><a href="index.html#testimonials">Proven Results</a></li>
                        <li><a href="index.html#contact">Ongoing Support</a></li>
                    </ul>
                </div>
                
                <div class="footer-section">
                    <h4>Contact Info</h4>
                    <ul>
                        <li><i class="fas fa-map-marker-alt"></i> No.6, Jalan Berkat 12, Taman Malim Jaya, 75250 Melaka</li>
                        <li><i class="fas fa-phone"></i> 016 772 1113</li>
                        <li><i class="fas fa-envelope"></i> hr@xpresspointservices.com</li>
                    </ul>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; 2024 Xpress Point Management Services. All rights reserved.</p>
                <div class="footer-links">
                    <a href="#">Privacy Policy</a>
                    <a href="#">Terms of Service</a>
                    <a href="#">Cookie Policy</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- JavaScript -->
    
    <style>
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #dc2626;
        }
        
        .alert-success {
            background: rgba(34, 197, 94, 0.1);
            border: 1px solid rgba(34, 197, 94, 0.3);
            color: #16a34a;
        }
        
        .application-container {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 3rem;
            margin-top: 3rem;
        }
        
        .job-details-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(37, 99, 235, 0.1);
            border-radius: 16px;
            padding: 2rem;
            height: fit-content;
        }
        
        .application-form-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(37, 99, 235, 0.1);
            border-radius: 16px;
            padding: 2rem;
        }
        
        .form-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .form-header h3 {
            font-size: 1.75rem;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 0.5rem;
        }
        
        .form-header p {
            color: #6b7280;
        }
        
        .application-form {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .form-group label {
            font-weight: 600;
            color: #374151;
        }
        
        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }
        
        .input-wrapper i {
            position: absolute;
            left: 1rem;
            color: #6b7280;
            font-size: 1rem;
            z-index: 2;
        }
        
        .input-wrapper input,
        .input-wrapper select,
        .input-wrapper textarea {
            width: 100%;
            padding: 1rem 1rem 1rem 3rem;
            border: 2px solid rgba(37, 99, 235, 0.2);
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.8);
            font-size: 1rem;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }
        
        .input-wrapper textarea {
            padding-left: 3rem;
            resize: vertical;
            min-height: 120px;
        }
        
        .input-wrapper input:focus,
        .input-wrapper select:focus,
        .input-wrapper textarea:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
            transform: translateY(-2px);
        }
        
        .file-upload-wrapper {
            position: relative;
            border: 2px dashed rgba(37, 99, 235, 0.3);
            border-radius: 12px;
            padding: 2rem;
            text-align: center;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.8);
            cursor: pointer;
        }
        
        .file-upload-wrapper:hover {
            border-color: #2563eb;
            background: rgba(37, 99, 235, 0.05);
        }
        
        .file-upload-wrapper i {
            font-size: 2rem;
            color: #6b7280;
            margin-bottom: 1rem;
        }
        
        .file-upload-wrapper input[type="file"] {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
            z-index: 1;
        }
        
        .file-upload-wrapper.has-file input[type="file"] {
            z-index: 0;
            pointer-events: none;
        }
        
        .file-upload-info {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        
        .file-upload-info span {
            font-weight: 600;
            color: #374151;
        }
        
        .file-upload-info small {
            color: #6b7280;
        }
        
        .file-selected-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1rem;
            background: rgba(37, 99, 235, 0.1);
            border: 2px solid rgba(37, 99, 235, 0.3);
            border-radius: 8px;
            margin-top: 0.5rem;
        }
        
        .file-selected-info i {
            color: #2563eb;
            font-size: 1.25rem;
        }
        
        .file-selected-info span {
            font-weight: 600;
            color: #1f2937;
            flex: 1;
        }
        
        .file-selected-info small {
            color: #6b7280;
            font-size: 0.875rem;
        }
        
        .remove-file-btn {
            background: #ef4444;
            color: white;
            border: none;
            padding: 0.5rem;
            border-radius: 6px;
            cursor: pointer;
            transition: background-color 0.2s;
            font-size: 0.875rem;
        }
        
        .remove-file-btn:hover {
            background: #dc2626;
        }
        
        .file-upload-wrapper.has-file {
            border-color: #2563eb;
            background: rgba(37, 99, 235, 0.05);
        }
        
        .file-upload-wrapper.has-file i {
            color: #2563eb;
        }
        
        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .form-actions .btn {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 1rem 2rem;
            border-radius: 12px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }
        .form-actions .btn-primary {
            background: #2563eb;
            color: white;
            border: 2px solid #2563eb;
        }
        
        .form-actions .btn-primary:hover {
            background: #1d4ed8;
            border-color: #1d4ed8;
        }
        
        .form-actions .btn-secondary {
            background: transparent;
            color: #6b7280;
            border: 2px solid #d1d5db;
        }
        
        .form-actions .btn-secondary:hover {
            background: #f3f4f6;
            border-color: #9ca3af;
            color: #374151;
        }
        

        
        @media (max-width: 768px) {
            .application-container {
                grid-template-columns: 1fr;
                gap: 2rem;
                margin-top: 2rem;
            }
            
            .job-details-card,
            .application-form-card {
                padding: 1.5rem;
            }
            
            .form-row {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .form-actions {
                flex-direction: column;
                gap: 0.75rem;
            }
            
            .form-actions .btn {
                width: 100%;
                justify-content: center;
            }
            
            .section-title {
                font-size: 1.75rem;
            }
            
            .section-subtitle {
                font-size: 1rem;
            }
            
            /* Mobile input improvements */
            .input-wrapper input,
            .input-wrapper select,
            .input-wrapper textarea {
                padding: 1.25rem 1rem 1.25rem 3rem;
                font-size: 16px; /* Prevents zoom on iOS */
                border-radius: 10px;
            }
            
            .input-wrapper i {
                font-size: 1.1rem;
                left: 1.25rem;
            }
            
            .form-group label {
                font-size: 0.95rem;
                margin-bottom: 0.25rem;
            }
            
            .form-header h3 {
                font-size: 1.5rem;
            }
            
            .form-header p {
                font-size: 0.95rem;
            }
        }
        
        /* Tablet responsive adjustments */
        @media (max-width: 1024px) and (min-width: 769px) {
            .application-container {
                gap: 2rem;
            }
            
            .form-row {
                gap: 1rem;
            }
        }
    </style>
    
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
                hasUnsavedChanges = true;
                
                // Show auto-save indicator
                showAutoSaveIndicator();
            }
            
            // Restore form data from localStorage
            function restoreFormData() {
                const savedData = localStorage.getItem(`form_${formId}_draft`);
                if (savedData) {
                    try {
                        const data = JSON.parse(savedData);
                        
                        // Restore form fields
                        Object.keys(data).forEach(key => {
                            if (key !== 'resumeFileName' && key !== 'resumeFileSize' && 
                                key !== 'coverLetterFileName' && key !== 'coverLetterFileSize') {
                                const element = applicationForm.querySelector(`[name="${key}"]`);
                                if (element) {
                                    element.value = data[key];
                                }
                            }
                        });
                        
                        // Show restore notification
                        showRestoreNotification();
                        hasUnsavedChanges = true;
                    } catch (e) {
                        console.error('Error restoring form data:', e);
                    }
                }
            }
            
            // Clear saved form data
            function clearSavedFormData() {
                localStorage.removeItem(`form_${formId}_draft`);
                hasUnsavedChanges = false;
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
                if (hasUnsavedChanges) {
                    e.preventDefault();
                    e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
                    return e.returnValue;
                }
            });
            
            // Start auto-save when form is modified
            applicationForm.addEventListener('input', function() {
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
                            alert('Please select a PDF or Word file (.pdf, .doc, .docx).');
                            clearFileSelection();
                            return;
                        }
                        
                        // Validate file size (5MB)
                        const maxSize = 5 * 1024 * 1024; // 5MB in bytes
                        if (file.size > maxSize) {
                            alert('File size must be less than 5MB.');
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
                        alert('Please select a PDF or Word file (.pdf, .doc, .docx).');
                        clearCoverLetterFileSelection();
                        return;
                    }
                    
                    // Validate file size (5MB)
                    const maxSize = 5 * 1024 * 1024; // 5MB in bytes
                    if (file.size > maxSize) {
                        alert('Cover letter file size must be less than 5MB.');
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
                            alert('Please select a resume file to upload.');
                            fileInput.focus();
                            return false;
                        }
                        if (!isAllowedApplicationDoc(file)) {
                            e.preventDefault();
                            alert('Please upload a resume as PDF or Word (.pdf, .doc, .docx).');
                            fileInput.focus();
                            return false;
                        }
                    }
                });
            }
        });
    </script>
</body>
</html>
