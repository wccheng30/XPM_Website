<?php
require_once 'includes/auth.php';
auth_start();
$xpm_account = auth_user();
$xpm_guest = !$xpm_account || empty($xpm_account['id']);
$xpm_login_next = auth_login_next_from_request();

// Include shared database connection
require_once 'includes/database.php';
require_once 'config/email_config.php';
require_once 'includes/programme-question-config.php';
require_once 'includes/programme-pricing.php';
require_once 'includes/phone-field.php';
require_once 'includes/form-prefill.php';

// Helper function to safely display prices
function formatPrice($price) {
    if (is_numeric($price)) {           
        return 'RM ' . number_format($price, 0);
    } else {
        return htmlspecialchars($price);
    }
}

$pdo = get_db_connection();
$success_message = '';
$error_message = '';
$programme = null;
$programme_question_map = [];
$registration_block_message = '';

// Email notification function
function sendRegistrationNotification($programme_id, $name, $email, $phone, $ic_number, $company, $position, $billing_address, $billing_contact_no, $vehicle_type, $vehicle_type_other, $plate_number, $payment_method, $how_did_you_hear, $how_did_you_hear_other, $spicy_preference, $allergies, $allergies_other, $dietary_preference, $dietary_restrictions, $interested_programs, $interested_programs_other, $additional_notes) {
    // Programme registration email from configuration
    $programme_email = PROGRAMME_REGISTRATION_EMAIL;
    
    // Get programme details
    global $pdo;
    $stmt = $pdo->prepare("SELECT title, duration, price FROM programmes WHERE id = ?");
    $stmt->execute([$programme_id]);
    $programme = $stmt->fetch();
    
    $programme_title = $programme ? $programme['title'] : 'Unknown Programme';
    $programme_duration = $programme ? $programme['duration'] : 'N/A';
    $programme_price = $programme ? $programme['price'] : 'N/A';
    
    // Format values for display
    $vehicle_display = $vehicle_type === 'other' ? $vehicle_type_other : ucfirst($vehicle_type);
    $allergies_display = $allergies === 'other' ? $allergies_other : ucfirst($allergies);
    $dietary_display = $dietary_preference === 'other' ? $dietary_restrictions : ucfirst($dietary_preference);
    $hear_display = $how_did_you_hear === 'other' ? $how_did_you_hear_other : ucfirst(str_replace('_', ' ', $how_did_you_hear));
    $programs_display = $interested_programs === 'other' ? $interested_programs_other : ucfirst(str_replace('_', ' ', $interested_programs));
    
    $subject = "New Programme Registration - " . $programme_title;
    
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
            <h1>New Programme Registration</h1>
            <p>Someone has registered for a programme</p>
        </div>
        
        <div class='content'>
            <div class='section'>
                <h3>Programme Details</h3>
                <div class='field'><strong>Programme:</strong> " . htmlspecialchars($programme_title) . "</div>
                <div class='field'><strong>Duration:</strong> " . htmlspecialchars($programme_duration) . "</div>
                <div class='field'><strong>Price:</strong> " . htmlspecialchars($programme_price) . "</div>
            </div>
            
            <div class='section'>
                <h3>Personal Information</h3>
                <div class='field'><strong>Name:</strong> " . htmlspecialchars($name) . "</div>
                <div class='field'><strong>Email:</strong> " . htmlspecialchars($email) . "</div>
                <div class='field'><strong>Phone:</strong> " . htmlspecialchars($phone) . "</div>
                <div class='field'><strong>IC Number:</strong> " . htmlspecialchars($ic_number) . "</div>
                <div class='field'><strong>Company:</strong> " . htmlspecialchars($company) . "</div>
                <div class='field'><strong>Position:</strong> " . htmlspecialchars($position) . "</div>
            </div>
            
            <div class='section'>
                <h3>Billing Information</h3>
                <div class='field'><strong>Billing Address:</strong> " . htmlspecialchars($billing_address) . "</div>
                <div class='field'><strong>Billing Contact:</strong> " . htmlspecialchars($billing_contact_no) . "</div>
                <div class='field'><strong>Payment Method:</strong> " . htmlspecialchars(ucfirst(str_replace('_', ' ', $payment_method))) . "</div>
            </div>
            
            <div class='section'>
                <h3>Vehicle Information</h3>
                <div class='field'><strong>Vehicle Type:</strong> " . htmlspecialchars($vehicle_display) . "</div>
                <div class='field'><strong>Plate Number:</strong> " . htmlspecialchars($plate_number) . "</div>
            </div>
            
            <div class='section'>
                <h3>Preferences & Requirements</h3>
                <div class='field'><strong>Spicy Preference:</strong> " . htmlspecialchars(ucfirst(str_replace('_', ' ', $spicy_preference))) . "</div>
                <div class='field'><strong>Dietary Preference:</strong> " . htmlspecialchars($dietary_display) . "</div>
                <div class='field'><strong>Allergies:</strong> " . htmlspecialchars($allergies_display) . "</div>
            </div>
            
            <div class='section'>
                <h3>Marketing & Interests</h3>
                <div class='field'><strong>How did they hear:</strong> " . htmlspecialchars($hear_display) . "</div>
                <div class='field'><strong>Interested Programs:</strong> " . htmlspecialchars($programs_display) . "</div>
            </div>
            
            " . (!empty($additional_notes) ? "
            <div class='section'>
                <h3>Additional Notes</h3>
                <div class='field'>" . nl2br(htmlspecialchars($additional_notes)) . "</div>
            </div>
            " : "") . "
        </div>
        
        <div class='footer'>
            <p>This is an automated notification from Xpress Point Management</p>
            <p>Registration submitted on " . date('Y-m-d H:i:s') . "</p>
        </div>
    </body>
    </html>
    ";
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: " . FROM_EMAIL . "\r\n";
    $headers .= "Reply-To: " . $email . "\r\n";
    
    // Send email to programme registration email
    mail($programme_email, $subject, $message, $headers);
}

function sendRegistrationConfirmation($programme_id, $name, $email) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT title, duration, price FROM programmes WHERE id = ?");
    $stmt->execute([$programme_id]);
    $programme = $stmt->fetch();

    $programme_title = $programme ? $programme['title'] : 'the programme you registered for';
    $programme_duration = $programme && !empty($programme['duration']) ? $programme['duration'] : '';
    $programme_price = '';
    if ($programme && $programme['price'] !== null && $programme['price'] !== '') {
        $programme_price = is_numeric($programme['price'])
            ? 'RM ' . number_format($programme['price'], 0)
            : (string) $programme['price'];
    }

    $subject = "Registration received - " . $programme_title;
    $duration_line = $programme_duration !== ''
        ? "<div class='field'><strong>Duration:</strong> " . htmlspecialchars($programme_duration) . "</div>"
        : '';
    $price_line = $programme_price !== ''
        ? "<div class='field'><strong>Price:</strong> " . htmlspecialchars($programme_price) . "</div>"
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
            <h1>Registration received</h1>
            <p>Thank you for registering</p>
        </div>
        <div class='content'>
            <p>Dear " . htmlspecialchars($name) . ",</p>
            <p>We have received your registration. Our team will contact you with the programme details.</p>
            <div class='section'>
                <h3>Programme</h3>
                <div class='field'><strong>Programme:</strong> " . htmlspecialchars($programme_title) . "</div>
                " . $duration_line . "
                " . $price_line . "
            </div>
        </div>
        <div class='footer'>
            <p>This is an automated confirmation from " . htmlspecialchars(FROM_NAME) . "</p>
            <p>Submitted on " . date('Y-m-d H:i:s') . "</p>
        </div>
    </body>
    </html>
    ";

    return xpm_send_mail($email, $subject, $message, PROGRAMME_REGISTRATION_EMAIL);
}

// Get programme details if programme_id is provided
if (isset($_GET['programme_id'])) {
    $programme_id = (int)$_GET['programme_id'];
    try {
        $stmt = $pdo->prepare("SELECT * FROM programmes WHERE id = ?");
        $stmt->execute([$programme_id]);
        $requested_programme = $stmt->fetch();
        if ($requested_programme && programme_registration_is_open($requested_programme)) {
            $programme = $requested_programme;
        } elseif ($requested_programme) {
            $registration_block_message = programme_registration_block_message($requested_programme);
        }
    } catch (PDOException $e) {
        $error_message = "Error loading programme details.";
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted_programme_id = $_POST['programme_id'] ?? null;
    $target_programme = null;
    if ($pdo && $posted_programme_id !== null && $posted_programme_id !== '' && is_numeric($posted_programme_id)) {
        $status_stmt = $pdo->prepare("SELECT * FROM programmes WHERE id = ?");
        $status_stmt->execute([(int) $posted_programme_id]);
        $target_programme = $status_stmt->fetch() ?: null;
    }
    if (!$target_programme || !programme_registration_is_open($target_programme)) {
        $error_message = programme_registration_block_message($target_programme);
        $registration_block_message = '';
    } elseif ($xpm_guest) {
        $error_message = 'Log in or create an account before you register.';
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error_message === '') {
    $programme_id = $_POST['programme_id'] ?? null;
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = combine_phone($_POST['phone_code'] ?? '+60', $_POST['phone'] ?? '');
    $ic_number = trim($_POST['ic_number'] ?? '');
    $company = trim($_POST['company'] ?? '');
    $position = trim($_POST['position'] ?? '');
    $billing_address = trim($_POST['billing_address'] ?? '');
    $billing_contact_no = combine_phone($_POST['billing_contact_no_code'] ?? '+60', $_POST['billing_contact_no'] ?? '');
    $vehicle_type = trim($_POST['vehicle_type'] ?? '');
    $vehicle_type_other = trim($_POST['vehicle_type_other'] ?? '');
    $plate_number = trim($_POST['plate_number'] ?? '');
    $payment_method = trim($_POST['payment_method'] ?? '');
    $how_did_you_hear = trim($_POST['how_did_you_hear'] ?? '');
    $how_did_you_hear_other = trim($_POST['how_did_you_hear_other'] ?? '');
    $spicy_preference = trim($_POST['spicy_preference'] ?? '');
    $allergies = trim($_POST['allergies'] ?? '');
    $allergies_other = trim($_POST['allergies_other'] ?? '');
    $dietary_preference = trim($_POST['dietary_preference'] ?? '');
    $dietary_restrictions = trim($_POST['dietary_restrictions'] ?? '');
    $interested_programs = trim($_POST['interested_programs'] ?? '');
    $interested_programs_other = trim($_POST['interested_programs_other'] ?? '');
    $additional_notes = trim($_POST['additional_notes'] ?? '');

    // Disabled/hidden fields are omitted from POST; store NULL instead of ''
    // to avoid ENUM truncation warnings (SQLSTATE[01000] 1265).
    $nullable_fields = [
        'vehicle_type', 'vehicle_type_other', 'plate_number', 'payment_method',
        'how_did_you_hear', 'how_did_you_hear_other', 'spicy_preference',
        'allergies', 'allergies_other', 'dietary_preference', 'dietary_restrictions',
        'interested_programs', 'interested_programs_other', 'additional_notes',
        'ic_number', 'company', 'position', 'billing_address', 'billing_contact_no', 'phone'
    ];
    foreach ($nullable_fields as $field_name) {
        if ($$field_name === '') {
            $$field_name = null;
        }
    }

    $selected_question_configs = [];
    if (!empty($programme_id) && is_numeric($programme_id)) {
        $selected_question_configs = getProgrammeQuestionConfigs($pdo, (int)$programme_id, true);
    }

    $required_field_keys = [];
    foreach ($selected_question_configs as $question_config) {
        if (!empty($question_config['is_required'])) {
            $required_field_keys[] = $question_config['field_key'];
        }
    }

    $missing_required = false;
    foreach ($required_field_keys as $field_key) {
        $posted_value = trim((string)($_POST[$field_key] ?? ''));
        if ($posted_value === '') {
            $missing_required = true;
            break;
        }
    }

    // Validation
    if (empty($programme_id) || $missing_required) {
        $error_message = "Please fill in all required fields.";
    } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Please enter a valid email address.";
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO programme_registrations 
                (programme_id, name, email, phone, ic_number, company, position, billing_address, billing_contact_no, 
                 vehicle_type, vehicle_type_other, plate_number, payment_method, how_did_you_hear, how_did_you_hear_other, spicy_preference, 
                 allergies, allergies_other, dietary_preference, dietary_restrictions, interested_programs, interested_programs_other, additional_notes) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $programme_id,
                $name,
                $email,
                $phone,
                $ic_number,
                $company,
                $position,
                $billing_address,
                $billing_contact_no,
                $vehicle_type,
                $vehicle_type_other,
                $plate_number,
                $payment_method,
                $how_did_you_hear,
                $how_did_you_hear_other,
                $spicy_preference,
                $allergies,
                $allergies_other,
                $dietary_preference,
                $dietary_restrictions,
                $interested_programs,
                $interested_programs_other,
                $additional_notes
            ]);
            
            $confirmSent = false;
            // Send email notification to admin and a confirmation to the registrant
            try {
                sendRegistrationNotification($programme_id, $name, $email, $phone, $ic_number, $company, $position, $billing_address, $billing_contact_no, $vehicle_type, $vehicle_type_other, $plate_number, $payment_method, $how_did_you_hear, $how_did_you_hear_other, $spicy_preference, $allergies, $allergies_other, $dietary_preference, $dietary_restrictions, $interested_programs, $interested_programs_other, $additional_notes);
                $confirmSent = sendRegistrationConfirmation($programme_id, $name, $email);
            } catch (Exception $emailError) {
                error_log("Registration email failed: " . $emailError->getMessage());
            }

            $success_message = $confirmSent
                ? "Registration submitted successfully! We'll contact you soon with programme details. A confirmation was sent to your email."
                : "Registration submitted successfully! We'll contact you soon with programme details. The confirmation email could not be sent.";
            
            // Clear form data
            $_POST = [];
            
        } catch (PDOException $e) {
            $error_message = $e->getMessage();
        }
    }
}

// Get all active programmes for dropdown
$all_programmes = [];
if ($pdo) {
    try {
        $stmt = $pdo->query("SELECT id, title, duration, price, tax_inclusive, tax_amount FROM programmes WHERE is_active = 1 AND registration_status = 'open' ORDER BY created_at DESC");
        $all_programmes = $stmt->fetchAll();
        foreach ($all_programmes as $programme_option) {
            $programme_question_map[(int)$programme_option['id']] = getProgrammeQuestionConfigs($pdo, (int)$programme_option['id'], true);
        }
    } catch (PDOException $e) {
        // Handle error silently
    }
}

$xpm_prefilled = $pdo ? xpm_apply_account_prefill($pdo, 'programme') : false;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Register for comprehensive HR training programmes. Join our professional development courses today.">
    <meta name="keywords" content="HR training, programme registration, professional development, HR courses">
    <meta name="author" content="Xpress Point Management">
    <title>Programme Registration - Xpress Point Management</title>
    <?php $xpm_page_styles = ['pages/forms.css', 'pages/programmes.css']; include 'partials/site-head.php'; ?>
</head>
<body>
    <?php $xpm_active = 'programmes'; include 'partials/site-nav.php'; ?>

    <main id="main">
        <header class="page-hero">
            <div class="container">
                <nav class="breadcrumb" aria-label="Breadcrumb" data-reveal="fade">
                    <a href="index.html">Home</a><i class="fas fa-chevron-right"></i>
                    <a href="programmes.php">Programmes</a><i class="fas fa-chevron-right"></i>
                    <span>Registration</span>
                </nav>
                <span class="eyebrow" data-reveal><i class="fas fa-graduation-cap"></i> Programme registration</span>
                <h1 data-split>Register for professional development</h1>
                <p class="lead" data-reveal>Join our comprehensive HR training programmes and advance your career.</p>
            </div>
        </header>

        <section id="programme-registration" class="section form-page">
            <div class="container">
                <?php if ($registration_block_message && !$error_message && !$success_message): ?>
                    <div class="alert alert-error" data-reveal>
                        <i class="fas fa-lock"></i>
                        <span><?php echo htmlspecialchars($registration_block_message); ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                    <div class="alert alert-error" data-reveal>
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?php echo htmlspecialchars($error_message); ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($success_message): ?>
                    <div class="alert alert-success" data-reveal>
                        <i class="fas fa-check-circle"></i>
                        <span><?php echo htmlspecialchars($success_message); ?></span>
                    </div>
                <?php endif; ?>

                <div class="form-layout<?php echo $programme ? '' : ' form-layout-single'; ?>">
                    <?php if ($programme): ?>
                    <aside class="form-aside">
                        <div class="summary-card" data-reveal="left">
                            <?php if (!empty($programme['poster_image'])): ?>
                            <div class="summary-media">
                                <img src="<?php echo htmlspecialchars($programme['poster_image']); ?>"
                                     alt="<?php echo htmlspecialchars($programme['title']); ?> Poster"
                                     class="programme-poster-img"
                                     data-poster-src="<?php echo htmlspecialchars($programme['poster_image']); ?>"
                                     data-poster-secondary="<?php echo !empty($programme['poster_image_2']) ? htmlspecialchars($programme['poster_image_2']) : ''; ?>"
                                     data-poster-title="<?php echo htmlspecialchars($programme['title']); ?>">
                                <span class="listing-media-hint"><i class="fas fa-expand"></i> View poster</span>
                            </div>
                            <?php endif; ?>

                            <div class="summary-body">
                                <div class="summary-top">
                                    <span class="icon-box icon-box-sm"><i class="<?php echo htmlspecialchars($programme['programme_icon'] ?? 'fas fa-graduation-cap'); ?>"></i></span>
                                    <span class="badge"><?php echo htmlspecialchars($programme['duration']); ?></span>
                                </div>
                                <h2 class="summary-title"><?php echo htmlspecialchars($programme['title']); ?></h2>
                                <p class="summary-desc"><?php echo htmlspecialchars($programme['description']); ?></p>

                                <ul class="summary-list">
                                    <li><i class="fas fa-clock"></i><span><?php echo htmlspecialchars($programme['duration']); ?></span></li>
                                    <li><i class="fas fa-tag"></i><span><?php echo formatPrice($programme['price']); ?><?php $sst_note = programme_sst_note($programme); echo $sst_note !== '' ? ' (' . htmlspecialchars($sst_note) . ')' : ''; ?></span></li>
                                    <li><i class="fas fa-certificate"></i><span><?php echo htmlspecialchars($programme['certification'] ?? 'HRD Corp Certified'); ?></span></li>
                                    <li><i class="fas fa-location-dot"></i><span><?php echo htmlspecialchars($programme['location'] ?? 'Online'); ?></span></li>
                                    <?php if (!empty($programme['start_date']) && $programme['start_date'] !== '0000-00-00'): ?>
                                    <li>
                                        <i class="fas fa-calendar-alt"></i>
                                        <span>
                                            <?php
                                            try {
                                                $start_date = new DateTime($programme['start_date']);
                                                if (!empty($programme['end_date']) && $programme['end_date'] !== '0000-00-00' && $programme['end_date'] !== $programme['start_date']) {
                                                    $end_date = new DateTime($programme['end_date']);
                                                    echo $start_date->format('M j, Y') . ' - ' . $end_date->format('M j, Y');
                                                } else {
                                                    echo $start_date->format('M j, Y');
                                                }
                                            } catch (Exception $e) {
                                                echo 'Date TBA';
                                            }
                                            ?>
                                        </span>
                                    </li>
                                    <?php else: ?>
                                    <li><i class="fas fa-calendar-alt"></i><span>Dates TBA - Contact for Schedule</span></li>
                                    <?php endif; ?>

                                    <?php if (!empty($programme['start_time']) && !empty($programme['end_time'])): ?>
                                    <li>
                                        <i class="fas fa-clock"></i>
                                        <span>
                                            <?php
                                            $start_time = new DateTime($programme['start_time']);
                                            $end_time = new DateTime($programme['end_time']);
                                            echo $start_time->format('g:i A') . ' - ' . $end_time->format('g:i A');
                                            ?>
                                        </span>
                                    </li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </div>

                        <div class="help-card" data-reveal="left">
                            <h3>Need help?</h3>
                            <p>Questions about the programme or HRDF claims? Reach our team directly.</p>
                            <a href="tel:+60167721113"><i class="fas fa-phone"></i> 016 772 1113</a>
                            <a href="tel:+60167723555"><i class="fas fa-phone"></i> 016 772 3555</a>
                            <a href="mailto:hr@xpresspointservices.com"><i class="fas fa-envelope"></i> hr@xpresspointservices.com</a>
                        </div>
                    </aside>
                    <?php endif; ?>

                    <div class="form-card" data-reveal="right">
                        <div class="form-card-header">
                            <h2>Registration form</h2>
                            <p>Please fill out the form below to register for this programme. Fields marked * are required.</p>
                        </div>

                        <?php if ($xpm_guest): ?>
                            <?php auth_guest_banner('You can look through this registration, but the fields stay locked until you sign in. Then you can fill in the details and register.', $xpm_login_next); ?>
                        <?php endif; ?>
                        <?php if (!empty($xpm_prefilled)): ?>
                            <div class="alert alert-success" role="status">
                                <i class="fas fa-circle-check"></i>
                                <span>We filled in details we already have for you. Change anything before you send it.</span>
                            </div>
                        <?php endif; ?>
                        <form method="POST" class="form application-form<?php echo $xpm_guest ? ' form-locked' : ''; ?>" id="programme-registration-form">
                            <?php if ($xpm_guest): ?><fieldset class="guest-fieldset" disabled><?php endif; ?>
                            <input type="hidden" name="programme_id" value="<?php echo $programme ? $programme['id'] : ''; ?>">

                            <fieldset class="form-section">
                                <legend class="form-section-title"><span class="step-no">1</span> Personal details</legend>

                                <div class="form-group">
                                    <label for="name">Full Name *</label>
                                    <div class="input-wrapper">
                                        <i class="fas fa-user"></i>
                                        <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required placeholder="Enter your full name">
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="email">Email Address *</label>
                                    <div class="input-wrapper">
                                        <i class="fas fa-envelope"></i>
                                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required placeholder="Enter your email address">
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="phone">Phone Number *</label>
                                        <?php render_phone_field('phone', 'phone', true, $_POST['phone_code'] ?? '+60', $_POST['phone'] ?? ''); ?>
                                    </div>

                                    <div class="form-group">
                                        <label for="ic_number">IC Number *</label>
                                        <div class="input-wrapper">
                                            <i class="fas fa-id-card"></i>
                                            <input type="text" id="ic_number" name="ic_number" value="<?php echo htmlspecialchars($_POST['ic_number'] ?? ''); ?>" required placeholder="Enter your IC number">
                                        </div>
                                    </div>
                                </div>
                            </fieldset>

                            <fieldset class="form-section">
                                <legend class="form-section-title"><span class="step-no">2</span> Programme &amp; organisation</legend>

                                <div class="form-group">
                                    <label for="programme_select">Select Programme *</label>
                                    <div class="input-wrapper">
                                        <i class="fas fa-graduation-cap"></i>
                                        <select id="programme_select" name="programme_id" required <?php echo $programme ? 'disabled' : ''; ?>>
                                            <option value="">Choose a programme...</option>
                                            <?php foreach ($all_programmes as $programme_option): ?>
                                                <option value="<?php echo $programme_option['id']; ?>"
                                                    <?php echo ($programme && $programme['id'] == $programme_option['id']) ? 'selected' : ''; ?>>
                                                    <?php
                                                    echo htmlspecialchars($programme_option['title']);
                                                    echo ' - ' . formatPrice($programme_option['price']);
                                                    $option_sst = programme_sst_note($programme_option);
                                                    if ($option_sst !== '') {
                                                        echo ' (' . htmlspecialchars($option_sst) . ')';
                                                    }
                                                    ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="company">Company/Organization *</label>
                                        <div class="input-wrapper">
                                            <i class="fas fa-building"></i>
                                            <input type="text" id="company" name="company" value="<?php echo htmlspecialchars($_POST['company'] ?? ''); ?>" required placeholder="Enter your company name">
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="position">Position/Title *</label>
                                        <div class="input-wrapper">
                                            <i class="fas fa-briefcase"></i>
                                            <input type="text" id="position" name="position" value="<?php echo htmlspecialchars($_POST['position'] ?? ''); ?>" required placeholder="Enter your job title">
                                        </div>
                                    </div>
                                </div>
                            </fieldset>

                            <fieldset class="form-section">
                                <legend class="form-section-title"><span class="step-no">3</span> Billing</legend>

                                <div class="form-group">
                                    <label for="billing_address">Billing Address *</label>
                                    <div class="input-wrapper">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <textarea id="billing_address" name="billing_address" rows="3" required placeholder="Enter your complete billing address"><?php echo htmlspecialchars($_POST['billing_address'] ?? ''); ?></textarea>
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="billing_contact_no">Billing Contact Number *</label>
                                        <?php render_phone_field('billing_contact_no', 'billing_contact_no', true, $_POST['billing_contact_no_code'] ?? '+60', $_POST['billing_contact_no'] ?? ''); ?>
                                    </div>

                                    <div class="form-group">
                                        <label for="payment_method">Payment Method *</label>
                                        <div class="input-wrapper">
                                            <i class="fas fa-credit-card"></i>
                                            <select id="payment_method" name="payment_method" required>
                                                <option value="">Select payment method...</option>
                                                <option value="hrdf_claim" <?php echo ($_POST['payment_method'] ?? '') === 'hrdf_claim' ? 'selected' : ''; ?>>HRDF Claim</option>
                                                <option value="non_hrdf_claim" <?php echo ($_POST['payment_method'] ?? '') === 'non_hrdf_claim' ? 'selected' : ''; ?>>Non HRDF Claim</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </fieldset>

                            <fieldset class="form-section">
                                <legend class="form-section-title"><span class="step-no">4</span> Parking</legend>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="vehicle_type">What vehicle will you be using? (for parking reservation) *</label>
                                        <div class="input-wrapper">
                                            <i class="fas fa-car"></i>
                                            <select id="vehicle_type" name="vehicle_type" required>
                                                <option value="">Select vehicle type...</option>
                                                <option value="car" <?php echo ($_POST['vehicle_type'] ?? '') === 'car' ? 'selected' : ''; ?>>Car</option>
                                                <option value="motorcycle" <?php echo ($_POST['vehicle_type'] ?? '') === 'motorcycle' ? 'selected' : ''; ?>>Motorcycle</option>
                                                <option value="other" <?php echo ($_POST['vehicle_type'] ?? '') === 'other' ? 'selected' : ''; ?>>Other (please specify below)</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="form-group" id="vehicle_type_other_group" style="display: none;">
                                        <label for="vehicle_type_other">Specify Other Vehicle Type</label>
                                        <div class="input-wrapper">
                                            <i class="fas fa-car"></i>
                                            <input type="text" id="vehicle_type_other" name="vehicle_type_other" value="<?php echo htmlspecialchars($_POST['vehicle_type_other'] ?? ''); ?>" placeholder="Please specify your vehicle type...">
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="plate_number">Plate Number (for parking reservation) *</label>
                                        <div class="input-wrapper">
                                            <i class="fas fa-id-card"></i>
                                            <input type="text" id="plate_number" name="plate_number" value="<?php echo htmlspecialchars($_POST['plate_number'] ?? ''); ?>" required placeholder="Enter your vehicle plate number">
                                        </div>
                                    </div>
                                </div>
                            </fieldset>

                            <fieldset class="form-section">
                                <legend class="form-section-title"><span class="step-no">5</span> Meals &amp; allergies</legend>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="spicy_preference">Spicy Preference *</label>
                                        <div class="input-wrapper">
                                            <i class="fas fa-pepper-hot"></i>
                                            <select id="spicy_preference" name="spicy_preference" required>
                                                <option value="">Select spicy preference...</option>
                                                <option value="spicy" <?php echo ($_POST['spicy_preference'] ?? '') === 'spicy' ? 'selected' : ''; ?>>Spicy</option>
                                                <option value="no_spicy" <?php echo ($_POST['spicy_preference'] ?? '') === 'no_spicy' ? 'selected' : ''; ?>>No Spicy</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="dietary_preference">Dietary Preference *</label>
                                        <div class="input-wrapper">
                                            <i class="fas fa-leaf"></i>
                                            <select id="dietary_preference" name="dietary_preference" required>
                                                <option value="">Select dietary preference...</option>
                                                <option value="none" <?php echo ($_POST['dietary_preference'] ?? '') === 'none' ? 'selected' : ''; ?>>No dietary restrictions</option>
                                                <option value="vegetarian" <?php echo ($_POST['dietary_preference'] ?? '') === 'vegetarian' ? 'selected' : ''; ?>>Vegetarian</option>
                                                <option value="vegan" <?php echo ($_POST['dietary_preference'] ?? '') === 'vegan' ? 'selected' : ''; ?>>Vegan</option>
                                                <option value="halal" <?php echo ($_POST['dietary_preference'] ?? '') === 'halal' ? 'selected' : ''; ?>>Halal</option>
                                                <option value="other" <?php echo ($_POST['dietary_preference'] ?? '') === 'other' ? 'selected' : ''; ?>>Other (please specify below)</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group" id="dietary_restrictions_group" style="display: none;">
                                    <label for="dietary_restrictions">Specific Dietary Restrictions</label>
                                    <div class="input-wrapper">
                                        <i class="fas fa-utensils"></i>
                                        <textarea id="dietary_restrictions" name="dietary_restrictions" rows="3" placeholder="Please specify any specific foods you cannot eat or other dietary requirements..."><?php echo htmlspecialchars($_POST['dietary_restrictions'] ?? ''); ?></textarea>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="allergies">Any Allergies? *</label>
                                    <div class="input-wrapper">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        <select id="allergies" name="allergies" required>
                                            <option value="">Select allergy option...</option>
                                            <option value="none" <?php echo ($_POST['allergies'] ?? '') === 'none' ? 'selected' : ''; ?>>No allergies</option>
                                            <option value="nuts" <?php echo ($_POST['allergies'] ?? '') === 'nuts' ? 'selected' : ''; ?>>Nuts</option>
                                            <option value="dairy" <?php echo ($_POST['allergies'] ?? '') === 'dairy' ? 'selected' : ''; ?>>Dairy</option>
                                            <option value="gluten" <?php echo ($_POST['allergies'] ?? '') === 'gluten' ? 'selected' : ''; ?>>Gluten</option>
                                            <option value="seafood" <?php echo ($_POST['allergies'] ?? '') === 'seafood' ? 'selected' : ''; ?>>Seafood</option>
                                            <option value="eggs" <?php echo ($_POST['allergies'] ?? '') === 'eggs' ? 'selected' : ''; ?>>Eggs</option>
                                            <option value="other" <?php echo ($_POST['allergies'] ?? '') === 'other' ? 'selected' : ''; ?>>Other (please specify below)</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="form-group" id="allergies_other_group" style="display: none;">
                                    <label for="allergies_other">Specify Other Allergies</label>
                                    <div class="input-wrapper">
                                        <i class="fas fa-exclamation-triangle"></i>
                                        <textarea id="allergies_other" name="allergies_other" rows="3" placeholder="Please specify any other food allergies..."><?php echo htmlspecialchars($_POST['allergies_other'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                            </fieldset>

                            <fieldset class="form-section">
                                <legend class="form-section-title"><span class="step-no">6</span> A little more about you</legend>

                                <div class="form-group">
                                    <label for="how_did_you_hear">How did you hear about this class? *</label>
                                    <div class="input-wrapper">
                                        <i class="fas fa-bullhorn"></i>
                                        <select id="how_did_you_hear" name="how_did_you_hear" required>
                                            <option value="">Select how you heard about us...</option>
                                            <option value="website" <?php echo ($_POST['how_did_you_hear'] ?? '') === 'website' ? 'selected' : ''; ?>>Website</option>
                                            <option value="social_media" <?php echo ($_POST['how_did_you_hear'] ?? '') === 'social_media' ? 'selected' : ''; ?>>Social Media</option>
                                            <option value="referral" <?php echo ($_POST['how_did_you_hear'] ?? '') === 'referral' ? 'selected' : ''; ?>>Referral</option>
                                            <option value="email" <?php echo ($_POST['how_did_you_hear'] ?? '') === 'email' ? 'selected' : ''; ?>>Email</option>
                                            <option value="advertisement" <?php echo ($_POST['how_did_you_hear'] ?? '') === 'advertisement' ? 'selected' : ''; ?>>Advertisement</option>
                                            <option value="other" <?php echo ($_POST['how_did_you_hear'] ?? '') === 'other' ? 'selected' : ''; ?>>Other (please specify below)</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="form-group" id="how_did_you_hear_other_group" style="display: none;">
                                    <label for="how_did_you_hear_other">Specify Other Source</label>
                                    <div class="input-wrapper">
                                        <i class="fas fa-bullhorn"></i>
                                        <textarea id="how_did_you_hear_other" name="how_did_you_hear_other" rows="3" placeholder="Please specify how you heard about this class..."><?php echo htmlspecialchars($_POST['how_did_you_hear_other'] ?? ''); ?></textarea>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="interested_programs">What more programs would you like to hear about?</label>
                                    <div class="input-wrapper">
                                        <i class="fas fa-graduation-cap"></i>
                                        <select id="interested_programs" name="interested_programs">
                                            <option value="">Select program interest...</option>
                                            <option value="laws_compliance" <?php echo ($_POST['interested_programs'] ?? '') === 'laws_compliance' ? 'selected' : ''; ?>>Laws and Compliance Training</option>
                                            <option value="soft_skills" <?php echo ($_POST['interested_programs'] ?? '') === 'soft_skills' ? 'selected' : ''; ?>>Soft Skills Development</option>
                                            <option value="other" <?php echo ($_POST['interested_programs'] ?? '') === 'other' ? 'selected' : ''; ?>>Other (please specify below)</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="form-group" id="interested_programs_other_group" style="display: none;">
                                    <label for="interested_programs_other">Specify Other Programs</label>
                                    <div class="input-wrapper">
                                        <i class="fas fa-graduation-cap"></i>
                                        <textarea id="interested_programs_other" name="interested_programs_other" rows="4" placeholder="Please specify any other programs you're interested in..."><?php echo htmlspecialchars($_POST['interested_programs_other'] ?? ''); ?></textarea>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="additional_notes">Additional Notes</label>
                                    <div class="input-wrapper">
                                        <i class="fas fa-file-alt"></i>
                                        <textarea id="additional_notes" name="additional_notes" rows="5" placeholder="Tell us about your learning goals, specific requirements, or any questions you have..."><?php echo htmlspecialchars($_POST['additional_notes'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                            </fieldset>

                            <?php if ($xpm_guest): ?></fieldset><?php endif; ?>
                            <div class="form-actions">
                                <a href="programmes.php" class="btn btn-outline">
                                    <i class="fas fa-arrow-left"></i>
                                    <span>Back to Programmes</span>
                                </a>
                                <button type="submit" class="btn btn-primary btn-lg"<?php echo $xpm_guest ? ' disabled' : ''; ?>>
                                    <i class="fas fa-paper-plane"></i>
                                    <span>Submit Registration</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <?php include 'partials/site-footer.php'; ?>

    <!-- Poster Modal Lightbox (Google Drive-style) -->
    <div id="posterModal" class="poster-modal" aria-hidden="true">
        <div class="poster-modal-overlay"></div>
        <div class="poster-modal-content" role="dialog" aria-modal="true" aria-labelledby="posterModalTitle">
            <div class="poster-modal-header">
                <h3 id="posterModalTitle">Programme Poster</h3>
                <div class="poster-zoom-controls">
                    <button type="button" class="zoom-btn zoom-out" id="zoomOut" title="Zoom out" aria-label="Zoom out">
                        <i class="fas fa-minus"></i>
                    </button>
                    <span class="zoom-level" id="zoomLevel">100%</span>
                    <button type="button" class="zoom-btn zoom-in" id="zoomIn" title="Zoom in" aria-label="Zoom in">
                        <i class="fas fa-plus"></i>
                    </button>
                    <button type="button" class="zoom-btn zoom-reset" id="zoomReset" title="Fit to screen" aria-label="Fit to screen">
                        <i class="fas fa-compress-arrows-alt"></i>
                    </button>
                    <button type="button" class="zoom-btn zoom-actual" id="zoomActual" title="Actual size" aria-label="Actual size">
                        100%
                    </button>
                </div>
                <button type="button" class="poster-modal-close" id="posterModalClose" aria-label="Close">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="poster-modal-body">
                <div class="poster-zoom-container" id="posterViewport">
                    <div class="poster-canvas" id="posterCanvas">
                        <img id="posterModalImage" src="" alt="Programme Poster" class="poster-zoom-image" draggable="false">
                    </div>
                </div>
                <div class="poster-secondary-thumbs" id="posterThumbs" hidden></div>
            </div>
        </div>
    </div>

    
    <script src="assets/js/leave-guard.js?v=20261006-3"></script>
    <script>
        // Auto-save functionality for programme registration
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            const formId = 'programme-registration-form';
            let autoSaveInterval;
            let hasUnsavedChanges = false;
            function markUnsaved(dirty) {
                hasUnsavedChanges = dirty;
                window.xpmFormDirty = dirty;
            }
            
            // Auto-save form data
            function autoSaveForm() {
                const formData = new FormData(form);
                const data = {};
                
                // Collect all form data
                for (let [key, value] of formData.entries()) {
                    data[key] = value;
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
                        
                        // Restore form fields
                        Object.keys(data).forEach(key => {
                            const element = form.querySelector(`[name="${key}"]`);
                            if (element) {
                                if (element.type === 'checkbox') {
                                    element.checked = data[key] === '1' || data[key] === 'on';
                                } else if (element.type === 'radio') {
                                    if (element.value === data[key]) {
                                        element.checked = true;
                                    }
                                } else {
                                    element.value = data[key];
                                }
                            }
                        });
                        
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
            form.addEventListener('input', function() {
                markUnsaved(true);
                if (!autoSaveInterval) {
                    autoSaveInterval = setInterval(autoSaveForm, 2000);
                }
            });
            
            // Clear auto-save on successful form submission
            form.addEventListener('submit', function() {
                clearSavedFormData();
                if (autoSaveInterval) {
                    clearInterval(autoSaveInterval);
                    autoSaveInterval = null;
                }
            });
            
            // Restore form data on page load
            restoreFormData();
            
            // Handle dietary preference change
            const dietaryPreference = document.getElementById('dietary_preference');
            const dietaryRestrictionsGroup = document.getElementById('dietary_restrictions_group');
            
            function toggleDietaryRestrictions() {
                if (dietaryPreference.value === 'other') {
                    // Show with slide-down animation
                    dietaryRestrictionsGroup.style.display = 'block';
                    dietaryRestrictionsGroup.style.opacity = '0';
                    dietaryRestrictionsGroup.style.transform = 'translateY(-10px)';
                    
                    // Trigger animation
                    setTimeout(() => {
                        dietaryRestrictionsGroup.style.transition = 'all 0.3s ease-out';
                        dietaryRestrictionsGroup.style.opacity = '1';
                        dietaryRestrictionsGroup.style.transform = 'translateY(0)';
                    }, 10);
                } else {
                    // Hide with slide-up animation
                    dietaryRestrictionsGroup.style.transition = 'all 0.3s ease-in';
                    dietaryRestrictionsGroup.style.opacity = '0';
                    dietaryRestrictionsGroup.style.transform = 'translateY(-10px)';
                    
                    // Hide after animation completes
                    setTimeout(() => {
                        dietaryRestrictionsGroup.style.display = 'none';
                        // Clear the textarea when hidden
                        document.getElementById('dietary_restrictions').value = '';
                    }, 300);
                }
            }
            
            // Add event listener
            dietaryPreference.addEventListener('change', toggleDietaryRestrictions);
            
            // Check on page load if "other" is already selected
            toggleDietaryRestrictions();
            
            // Handle allergies change
            const allergies = document.getElementById('allergies');
            const allergiesOtherGroup = document.getElementById('allergies_other_group');
            
            function toggleAllergiesOther() {
                if (allergies.value === 'other') {
                    // Show with slide-down animation
                    allergiesOtherGroup.style.display = 'block';
                    allergiesOtherGroup.style.opacity = '0';
                    allergiesOtherGroup.style.transform = 'translateY(-10px)';
                    
                    // Trigger animation
                    setTimeout(() => {
                        allergiesOtherGroup.style.transition = 'all 0.3s ease-out';
                        allergiesOtherGroup.style.opacity = '1';
                        allergiesOtherGroup.style.transform = 'translateY(0)';
                    }, 10);
                } else {
                    // Hide with slide-up animation
                    allergiesOtherGroup.style.transition = 'all 0.3s ease-in';
                    allergiesOtherGroup.style.opacity = '0';
                    allergiesOtherGroup.style.transform = 'translateY(-10px)';
                    
                    // Hide after animation completes
                    setTimeout(() => {
                        allergiesOtherGroup.style.display = 'none';
                        // Clear the textarea when hidden
                        document.getElementById('allergies_other').value = '';
                    }, 300);
                }
            }
            
            // Add event listener
            allergies.addEventListener('change', toggleAllergiesOther);
            
            // Check on page load if "other" is already selected
            toggleAllergiesOther();
            
            // Handle interested programs change
            const interestedPrograms = document.getElementById('interested_programs');
            const interestedProgramsOtherGroup = document.getElementById('interested_programs_other_group');
            
            function toggleInterestedProgramsOther() {
                if (interestedPrograms.value === 'other') {
                    // Show with slide-down animation
                    interestedProgramsOtherGroup.style.display = 'block';
                    interestedProgramsOtherGroup.style.opacity = '0';
                    interestedProgramsOtherGroup.style.transform = 'translateY(-10px)';
                    
                    // Trigger animation
                    setTimeout(() => {
                        interestedProgramsOtherGroup.style.transition = 'all 0.3s ease-out';
                        interestedProgramsOtherGroup.style.opacity = '1';
                        interestedProgramsOtherGroup.style.transform = 'translateY(0)';
                    }, 10);
                } else {
                    // Hide with slide-up animation
                    interestedProgramsOtherGroup.style.transition = 'all 0.3s ease-in';
                    interestedProgramsOtherGroup.style.opacity = '0';
                    interestedProgramsOtherGroup.style.transform = 'translateY(-10px)';
                    
                    // Hide after animation completes
                    setTimeout(() => {
                        interestedProgramsOtherGroup.style.display = 'none';
                        // Clear the textarea when hidden
                        document.getElementById('interested_programs_other').value = '';
                    }, 300);
                }
            }
            
            // Add event listener
            interestedPrograms.addEventListener('change', toggleInterestedProgramsOther);
            
            // Check on page load if "other" is already selected
            toggleInterestedProgramsOther();
            
            // Handle how did you hear change
            const howDidYouHear = document.getElementById('how_did_you_hear');
            const howDidYouHearOtherGroup = document.getElementById('how_did_you_hear_other_group');
            
            function toggleHowDidYouHearOther() {
                if (howDidYouHear.value === 'other') {
                    // Show with slide-down animation
                    howDidYouHearOtherGroup.style.display = 'block';
                    howDidYouHearOtherGroup.style.opacity = '0';
                    howDidYouHearOtherGroup.style.transform = 'translateY(-10px)';
                    
                    // Trigger animation
                    setTimeout(() => {
                        howDidYouHearOtherGroup.style.transition = 'all 0.3s ease-out';
                        howDidYouHearOtherGroup.style.opacity = '1';
                        howDidYouHearOtherGroup.style.transform = 'translateY(0)';
                    }, 10);
                } else {
                    // Hide with slide-up animation
                    howDidYouHearOtherGroup.style.transition = 'all 0.3s ease-in';
                    howDidYouHearOtherGroup.style.opacity = '0';
                    howDidYouHearOtherGroup.style.transform = 'translateY(-10px)';
                    
                    // Hide after animation completes
                    setTimeout(() => {
                        howDidYouHearOtherGroup.style.display = 'none';
                        // Clear the textarea when hidden
                        document.getElementById('how_did_you_hear_other').value = '';
                    }, 300);
                }
            }
            
            // Add event listener
            howDidYouHear.addEventListener('change', toggleHowDidYouHearOther);
            
            // Check on page load if "other" is already selected
            toggleHowDidYouHearOther();
            
            // Handle vehicle type change
            const vehicleType = document.getElementById('vehicle_type');
            const vehicleTypeOtherGroup = document.getElementById('vehicle_type_other_group');
            
            function toggleVehicleTypeOther() {
                if (vehicleType.value === 'other') {
                    // Show with slide-down animation
                    vehicleTypeOtherGroup.style.display = 'block';
                    vehicleTypeOtherGroup.style.opacity = '0';
                    vehicleTypeOtherGroup.style.transform = 'translateY(-10px)';
                    
                    // Trigger animation
                    setTimeout(() => {
                        vehicleTypeOtherGroup.style.transition = 'all 0.3s ease-out';
                        vehicleTypeOtherGroup.style.opacity = '1';
                        vehicleTypeOtherGroup.style.transform = 'translateY(0)';
                    }, 10);
                } else {
                    // Hide with slide-up animation
                    vehicleTypeOtherGroup.style.transition = 'all 0.3s ease-in';
                    vehicleTypeOtherGroup.style.opacity = '0';
                    vehicleTypeOtherGroup.style.transform = 'translateY(-10px)';
                    
                    // Hide after animation completes
                    setTimeout(() => {
                        vehicleTypeOtherGroup.style.display = 'none';
                        // Clear the input when hidden
                        document.getElementById('vehicle_type_other').value = '';
                    }, 300);
                }
            }
            
            // Add event listener
            vehicleType.addEventListener('change', toggleVehicleTypeOther);
            
            // Check on page load if "other" is already selected
            toggleVehicleTypeOther();
        });
    </script>

    <script>
        const programmeQuestionMap = <?php echo json_encode($programme_question_map, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;

        function parseDropdownOptions(rawOptions) {
            if (!rawOptions) return [];
            return rawOptions
                .split('\n')
                .map(line => line.trim())
                .filter(Boolean)
                .map(line => {
                    const [value, label] = line.includes('|') ? line.split('|', 2) : [line, line];
                    return { value: value.trim(), label: (label || value).trim() };
                });
        }

        function setFieldVisibility(fieldName, isVisible) {
            const field = document.querySelector(`[name="${fieldName}"]`);
            if (!field) return;
            const group = field.closest('.form-group');
            if (!group) return;

            group.style.display = isVisible ? '' : 'none';
            field.disabled = !isVisible;
        }

        function applyQuestionConfiguration(programmeId) {
            const configs = programmeQuestionMap[programmeId] || [];
            const configByKey = {};
            configs.forEach(cfg => {
                configByKey[cfg.field_key] = cfg;
            });

            const allKeys = [
                'name', 'email', 'phone', 'ic_number', 'company', 'position',
                'billing_address', 'billing_contact_no', 'payment_method',
                'vehicle_type', 'plate_number', 'how_did_you_hear', 'spicy_preference',
                'dietary_preference', 'allergies', 'interested_programs', 'additional_notes'
            ];

            allKeys.forEach(fieldKey => {
                const field = document.querySelector(`[name="${fieldKey}"]`);
                if (!field) return;

                const cfg = configByKey[fieldKey];
                const isEnabled = !!cfg;
                const isRequired = isEnabled && Number(cfg.is_required) === 1;

                setFieldVisibility(fieldKey, isEnabled);
                field.required = isRequired;

                const label = document.querySelector(`label[for="${field.id}"]`);
                if (label && cfg && cfg.field_label) {
                    label.textContent = cfg.field_label + (isRequired ? ' *' : '');
                }

                if (cfg && cfg.input_type === 'select' && field.tagName.toLowerCase() === 'select') {
                    const options = parseDropdownOptions(cfg.field_options || '');
                    if (options.length > 0) {
                        const currentValue = field.value;
                        field.innerHTML = '<option value="">Select...</option>';
                        options.forEach(opt => {
                            const optionEl = document.createElement('option');
                            optionEl.value = opt.value;
                            optionEl.textContent = opt.label;
                            field.appendChild(optionEl);
                        });
                        if (currentValue && [...field.options].some(opt => opt.value === currentValue)) {
                            field.value = currentValue;
                        }
                    }
                }
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            const programmeSelect = document.getElementById('programme_select');
            if (!programmeSelect) return;

            applyQuestionConfiguration(programmeSelect.value);
            programmeSelect.addEventListener('change', function() {
                applyQuestionConfiguration(this.value);
            });
        });
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            function syncFormSections() {
                document.querySelectorAll('#programme-registration-form .form-section').forEach(function(section) {
                    var groups = section.querySelectorAll('.form-group');
                    var anyEnabled = Array.prototype.some.call(groups, function(group) {
                        var field = group.querySelector('input, select, textarea');
                        return field && !field.disabled;
                    });
                    section.hidden = groups.length > 0 && !anyEnabled;
                });
            }
            syncFormSections();
            var programmeSelect = document.getElementById('programme_select');
            if (programmeSelect) programmeSelect.addEventListener('change', syncFormSections);
        });
    </script>
</body>
</html>
