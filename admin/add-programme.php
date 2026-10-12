<?php
require_once '../includes/auth.php';
auth_require(['admin', 'manager', 'trainer']);
if (auth_is_trainer()) {
    header('Location: facilities.php');
    exit;
}

// Include database connection and services
require_once '../includes/database.php';
require_once '../includes/facility-booking-service.php';
require_once '../includes/programme-question-config.php';

$error = '';
$success = '';
$field_errors = [];
$question_templates = getDefaultProgrammeQuestions();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo = get_db_connection();
    
    if ($pdo) {
        try {
            // Validate and sanitize input
            $title = trim($_POST['title'] ?? '');
            $description = trim(urldecode($_POST['description'] ?? ''));
            $duration = trim($_POST['duration'] ?? '');
            $price_raw = trim($_POST['programme_price'] ?? '');
            // Convert non-numeric prices (e.g., "Free", "Contact for pricing") to 0 for DECIMAL column
            $price = is_numeric($price_raw) ? $price_raw : 0;
            $tax_inclusive = isset($_POST['tax_inclusive']) ? intval($_POST['tax_inclusive']) : 0;
            $tax_amount = !empty($_POST['tax_amount']) ? trim($_POST['tax_amount']) : null;
            $original_price = !empty($_POST['programme_original_price']) ? trim($_POST['programme_original_price']) : null;
            $category = trim($_POST['category'] ?? '');
            $programme_type = auth_normalize_programme_type($_POST['programme_type'] ?? 'training');
            if (auth_is_trainer()) {
                $owner_user_id = (int) (auth_user()['id'] ?? 0);
            } else {
                $posted_owner = (int) ($_POST['owner_user_id'] ?? 0);
                $owner_user_id = $posted_owner > 0 ? $posted_owner : null;
            }
            $max_participants = intval($_POST['max_participants'] ?? 25);
            $certification = trim($_POST['certification'] ?? 'HRD Corp Certified');
            // Handle custom icon selection
            $programme_icon = trim($_POST['programme_icon'] ?? 'fas fa-graduation-cap');
            if ($programme_icon === 'custom') {
                if (!empty($_POST['custom_icon_class'])) {
                    $programme_icon = trim($_POST['custom_icon_class']);
                } else {
                    // If custom is selected but no custom class provided, keep default
                    $programme_icon = 'fas fa-graduation-cap';
                }
            }
            $programme_badge = trim($_POST['programme_badge'] ?? '');
            $location = trim($_POST['location'] ?? 'Online');
            $modules = trim($_POST['modules'] ?? '');
            // Convert newlines to comma-separated format for storage
            if (!empty($modules)) {
                // Handle both newline and comma-separated input
                if (strpos($modules, "\n") !== false) {
                    // Newline-separated input
                    $modules_array = array_filter(array_map('trim', explode("\n", $modules)));
                } else {
                    // Comma-separated input (fallback)
                    $modules_array = array_filter(array_map('trim', explode(',', $modules)));
                }
                $modules = implode(', ', $modules_array);
            }
            $schedule_type = trim($_POST['schedule_type'] ?? '');
            $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
            $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
            $start_time = !empty($_POST['start_time']) ? $_POST['start_time'] : null;
            $end_time = !empty($_POST['end_time']) ? $_POST['end_time'] : null;
            
            // Handle single day programmes
            if (!empty($schedule_type) && strtolower(trim($schedule_type)) === 'single_day' && $start_date) {
                $end_date = $start_date;
            }
            
            // Facility booking options
            $requires_facility = isset($_POST['requires_facility']) ? 1 : 0;
            $facility_type = !empty($_POST['facility_type']) ? $_POST['facility_type'] : null;
            $auto_create_facility_booking = isset($_POST['auto_create_facility_booking']) ? 1 : 0;
            
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            // Initialize errors array
            $field_errors = [];
            
            // Handle poster image upload
            $poster_image = null;
            if (isset($_FILES['poster_image']) && $_FILES['poster_image']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = '../assets/images/programmes/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $file_info = pathinfo($_FILES['poster_image']['name']);
                $file_extension = strtolower($file_info['extension']);
                
                // Validate file type
                $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                if (!in_array($file_extension, $allowed_types)) {
                    $field_errors['poster_image'] = 'Use a JPG, PNG, GIF, or WebP image.';
                } else {
                    // Generate unique filename
                    $filename = 'programme_' . time() . '_' . uniqid() . '.' . $file_extension;
                    $filepath = $upload_dir . $filename;
                    
                    if (move_uploaded_file($_FILES['poster_image']['tmp_name'], $filepath)) {
                        $poster_image = 'assets/images/programmes/' . $filename;
                    } else {
                        $field_errors['poster_image'] = 'The poster could not be uploaded. Try again.';
                    }
                }
            }
            
            // Handle banner image upload (auto-sets featured)
            $banner_image = null;
            if (isset($_FILES['banner_image']) && $_FILES['banner_image']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = '../assets/images/programmes/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $file_info = pathinfo($_FILES['banner_image']['name']);
                $file_extension = strtolower($file_info['extension']);
                
                // Validate file type
                $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                if (!in_array($file_extension, $allowed_types)) {
                    $field_errors['banner_image'] = 'Use a JPG, PNG, GIF, or WebP image.';
                } else {
                    // Generate unique filename
                    $filename = 'banner_' . time() . '_' . uniqid() . '.' . $file_extension;
                    $filepath = $upload_dir . $filename;
                    
                    if (move_uploaded_file($_FILES['banner_image']['tmp_name'], $filepath)) {
                        $banner_image = 'assets/images/programmes/' . $filename;
                    } else {
                        $field_errors['banner_image'] = 'The banner could not be uploaded. Try again.';
                    }
                }
            }
            
            // Auto-set is_featured based on banner_image presence
            $is_featured = !empty($banner_image) ? 1 : 0;
            
            // Handle second poster image upload
            $poster_image_2 = null;
            if (isset($_FILES['poster_image_2']) && $_FILES['poster_image_2']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = '../assets/images/programmes/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $file_info = pathinfo($_FILES['poster_image_2']['name']);
                $file_extension = strtolower($file_info['extension']);
                
                // Validate file type
                $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                if (!in_array($file_extension, $allowed_types)) {
                    $field_errors['poster_image_2'] = 'Use a JPG, PNG, GIF, or WebP image.';
                } else {
                    // Generate unique filename
                    $filename = 'programme_2_' . time() . '_' . uniqid() . '.' . $file_extension;
                    $filepath = $upload_dir . $filename;
                    
                    if (move_uploaded_file($_FILES['poster_image_2']['tmp_name'], $filepath)) {
                        $poster_image_2 = 'assets/images/programmes/' . $filename;
                    } else {
                        $field_errors['poster_image_2'] = 'The second poster could not be uploaded. Try again.';
                    }
                }
            }
            
            // Validation
            
            if ($max_participants <= 0) {
                $max_participants = 25;
            }

            if (empty($title)) {
                $field_errors['title'] = 'Add a programme title.';
            }
            
            if (empty($description)) {
                $field_errors['description'] = 'Add a description.';
            }
            
            if (empty($duration)) {
                $field_errors['duration'] = 'Add a duration.';
            }
            
            if (empty($price_raw)) {
                $field_errors['programme_price'] = 'Add a price.';
            }
            
            if (empty($location)) {
                $field_errors['location'] = 'Add a location.';
            }
            
            if ($requires_facility && empty($facility_type)) {
                $field_errors['facility_type'] = 'Choose a facility when booking is turned on.';
            }
            
            if ($requires_facility && empty($start_date)) {
                $field_errors['start_date'] = 'Add a start date for the facility booking.';
            }
            
            if ($requires_facility && (empty($start_time) || empty($end_time))) {
                $field_errors['start_time'] = 'Add a start and end time for the facility booking.';
            }

            if (empty($field_errors) && !empty($start_date)) {
                $reserved = xpm_day_reservation_conflict($pdo, $start_date, $end_date ?: $start_date);
                if ($reserved) {
                    $field_errors['start_date'] = $reserved;
                }
            }
            
            if (empty($field_errors)) {
                // Insert programme into database
                $stmt = $pdo->prepare("
                    INSERT INTO programmes (title, description, duration, price, tax_inclusive, tax_amount, original_price, category, programme_type, owner_user_id, max_participants, certification, programme_icon, programme_badge, location, poster_image, poster_image_2, banner_image, modules, schedule_type, start_date, end_date, start_time, end_time, requires_facility, facility_type, auto_create_facility_booking, is_active, is_featured, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                
                $stmt->execute([$title, $description, $duration, $price, $tax_inclusive, $tax_amount, $original_price, $category, $programme_type, $owner_user_id, $max_participants, $certification, $programme_icon, $programme_badge, $location, $poster_image, $poster_image_2, $banner_image, $modules, $schedule_type, $start_date, $end_date, $start_time, $end_time, $requires_facility, $facility_type, $auto_create_facility_booking, $is_active, $is_featured]);
                
                $programme_id = $pdo->lastInsertId();
                saveProgrammeQuestionConfigs($pdo, (int)$programme_id, $_POST['question_configs'] ?? $question_templates);
                
                // Create facility booking if required and auto-create is enabled
                if ($requires_facility && $auto_create_facility_booking) {
                    try {
                        $facilityService = new FacilityBookingService($pdo);
                        $booking_id = $facilityService->createProgrammeFacilityBooking($programme_id);
                        $success = 'Training programme added successfully! Facility booking #' . $booking_id . ' has been created automatically.';
                    } catch (Exception $e) {
                        $success = 'Training programme added successfully! However, facility booking creation failed: ' . $e->getMessage();
                    }
                } else {
                    $success = 'Training programme added successfully!';
                }
                
                // Clear form data
                $_POST = [];
            } else {
                $error = 'Please check the highlighted fields.';
            }
            
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    } else {
        $error = "Unable to connect to database";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Programme - XpressPoint Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="admin-styles.css?v=20261005-5">
</head>
<body data-motion="admin">
    <?php include_once 'includes/header.php'; renderAdminHeader('Add Training Programme', 'fas fa-plus', ['url' => 'programmes.php', 'label' => 'Programmes']); ?>
    
    <div class="admin-container">
        <!-- Page Header -->
            <div class="page-header">
            <h2 class="page-title">Add New Training Programme</h2>
            <div class="d-flex gap-2">
                <a href="programmes.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i>
                    Back to Programmes
                </a>
            </div>
            </div>
            
        <!-- Alerts -->
            <?php if ($error): ?>
                <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
        <!-- Programme Form -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-graduation-cap"></i> Programme Details</h3>
            </div>
            <div class="card-body">
            <form method="POST" action="" enctype="multipart/form-data">
                <p class="form-intro">Fields with a star are required. You can add images and extras after the first save.</p>
                <section class="form-section">
                    <h4 class="form-section-title">What people see</h4>
                <div class="form-row">
                <div class="form-group">
                    <label for="title">Programme Title *</label>
                        <input type="text" id="title" name="title" class="form-control<?php echo isset($field_errors['title']) ? ' is-invalid' : ''; ?>" 
                               value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>" required>
                        <?php if (!empty($field_errors['title'])): ?><div class="invalid-feedback"><?php echo htmlspecialchars($field_errors['title']); ?></div><?php endif; ?>
                </div>
                <div class="form-group">
                    <label for="programme_type">Programme type *</label>
                    <select id="programme_type" name="programme_type" class="form-control" required>
                        <option value="training" <?php echo ($_POST['programme_type'] ?? 'training') !== 'webinar' ? 'selected' : ''; ?>>Training Programme</option>
                        <option value="webinar" <?php echo ($_POST['programme_type'] ?? '') === 'webinar' ? 'selected' : ''; ?>>Webinar</option>
                    </select>
                </div>
                </div>
                <?php if (!auth_is_trainer()): ?>
                <?php
                    $owner_pdo = (isset($pdo) && $pdo) ? $pdo : get_db_connection();
                    $trainer_choices = $owner_pdo ? auth_trainer_choices($owner_pdo) : [];
                ?>
                <div class="form-group">
                    <label for="owner_user_id">Trainer owner</label>
                    <select id="owner_user_id" name="owner_user_id" class="form-control">
                        <option value="">Admin owned</option>
                        <?php foreach ($trainer_choices as $trainer_choice): ?>
                            <option value="<?php echo (int) $trainer_choice['id']; ?>" <?php echo (string) ($_POST['owner_user_id'] ?? '') === (string) $trainer_choice['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($trainer_choice['display_name'] ?: $trainer_choice['email']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="form-text text-muted">Leave this as admin owned, or assign the programme to a trainer.</small>
                </div>
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="description">Programme Description *</label>
                        <textarea id="description" name="description" class="form-control<?php echo isset($field_errors['description']) ? ' is-invalid' : ''; ?>" rows="4" required><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                        <?php if (!empty($field_errors['description'])): ?><div class="invalid-feedback"><?php echo htmlspecialchars($field_errors['description']); ?></div><?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="location">Location *</label>
                    <input type="text" id="location" name="location" class="form-control<?php echo isset($field_errors['location']) ? ' is-invalid' : ''; ?>" 
                           value="<?php echo htmlspecialchars($_POST['location'] ?? ''); ?>" required>
                    <small class="form-text text-muted">City or Remote.</small>
                    <?php if (!empty($field_errors['location'])): ?><div class="invalid-feedback"><?php echo htmlspecialchars($field_errors['location']); ?></div><?php endif; ?>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="duration">Duration *</label>
                            <input type="text" id="duration" name="duration" class="form-control<?php echo isset($field_errors['duration']) ? ' is-invalid' : ''; ?>" 
                                   value="<?php echo htmlspecialchars($_POST['duration'] ?? ''); ?>" placeholder="e.g., 2 days" required>
                        <small class="form-text text-muted">What visitors will read, for example 2 days.</small>
                        <?php if (!empty($field_errors['duration'])): ?><div class="invalid-feedback"><?php echo htmlspecialchars($field_errors['duration']); ?></div><?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label for="programme_price">Current Price (RM) *</label>
                            <input type="text" id="programme_price" name="programme_price" class="form-control<?php echo isset($field_errors['programme_price']) ? ' is-invalid' : ''; ?>" 
                                   value="<?php echo htmlspecialchars($_POST['programme_price'] ?? ''); ?>" 
                                   placeholder="e.g., 1500.00, Free, Contact for pricing" oninput="calculateTax()" required>
                        <small class="form-text text-muted">This is the price participants will pay.</small>
                        <?php if (!empty($field_errors['programme_price'])): ?><div class="invalid-feedback"><?php echo htmlspecialchars($field_errors['programme_price']); ?></div><?php endif; ?>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="programme_original_price">Original Price (RM)</label>
                        <input type="text" id="programme_original_price" name="programme_original_price" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['programme_original_price'] ?? ''); ?>" 
                               placeholder="e.g., 2000.00 (leave empty if no discount)">
                    <small class="form-text text-muted">Original price before discount. Leave empty if there is no discount.</small>
                </div>
                </section>

                <section class="form-section">
                    <h4 class="form-section-title">When and where</h4>
                    <div class="form-group">
                        <label for="schedule_type">How people attend</label>
                        <select id="schedule_type" name="schedule_type" class="form-control">
                            <option value="">Choose one</option>
                            <option value="in_person" <?php echo ($_POST['schedule_type'] ?? '') === 'in_person' ? 'selected' : ''; ?>>In person</option>
                            <option value="online" <?php echo ($_POST['schedule_type'] ?? '') === 'online' ? 'selected' : ''; ?>>Online</option>
                            <option value="hybrid" <?php echo ($_POST['schedule_type'] ?? '') === 'hybrid' ? 'selected' : ''; ?>>Hybrid</option>
                            <?php
                            $posted_schedule = $_POST['schedule_type'] ?? '';
                            if ($posted_schedule !== '' && !in_array($posted_schedule, ['in_person', 'online', 'hybrid'], true)):
                            ?>
                            <option value="<?php echo htmlspecialchars($posted_schedule); ?>" selected><?php echo htmlspecialchars($posted_schedule); ?></option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="start_date">Start Date</label>
                            <input type="date" id="start_date" name="start_date" class="form-control<?php echo isset($field_errors['start_date']) ? ' is-invalid' : ''; ?>" 
                                   value="<?php echo htmlspecialchars($_POST['start_date'] ?? ''); ?>">
                            <?php if (!empty($field_errors['start_date'])): ?><div class="invalid-feedback"><?php echo htmlspecialchars($field_errors['start_date']); ?></div><?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label for="end_date">End Date</label>
                            <input type="date" id="end_date" name="end_date" class="form-control" 
                                   value="<?php echo htmlspecialchars($_POST['end_date'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="start_time">Start Time</label>
                            <input type="time" id="start_time" name="start_time" class="form-control<?php echo isset($field_errors['start_time']) ? ' is-invalid' : ''; ?>" 
                                   value="<?php echo htmlspecialchars($_POST['start_time'] ?? ''); ?>">
                            <?php if (!empty($field_errors['start_time'])): ?><div class="invalid-feedback"><?php echo htmlspecialchars($field_errors['start_time']); ?></div><?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label for="end_time">End Time</label>
                            <input type="time" id="end_time" name="end_time" class="form-control" 
                                   value="<?php echo htmlspecialchars($_POST['end_time'] ?? ''); ?>">
                        </div>
                    </div>
                </section>

                <section class="form-section">
                    <h4 class="form-section-title">Images</h4>
                    <div class="form-group">
                        <label for="banner_image">Banner</label>
                        <div class="file-upload-container">
                            <div class="file-upload<?php echo isset($field_errors['banner_image']) ? ' is-invalid' : ''; ?>" id="banner_image_upload">
                                <input type="file" id="banner_image" name="banner_image" accept="image/*" onchange="handleFileUpload(this)">
                                <label for="banner_image" class="file-upload-label">
                                    <i class="fas fa-image"></i>
                                    <div>
                                        <strong>Click to upload banner</strong> or drag and drop<br>
                                        <span style="font-size: 12px; opacity: 0.7;">Recommended: 1920×520px · PNG, JPG, GIF, WebP up to 5MB</span>
                                    </div>
                                </label>
                            </div>
                            <div id="banner_image_preview" class="file-preview-container" style="display: none;"></div>
                        </div>
                        <small class="form-text text-muted">A banner is what places the programme on the homepage slideshow.</small>
                        <?php if (!empty($field_errors['banner_image'])): ?><div class="invalid-feedback"><?php echo htmlspecialchars($field_errors['banner_image']); ?></div><?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="poster_image">Primary poster</label>
                        <div class="file-upload-container">
                            <div class="file-upload<?php echo isset($field_errors['poster_image']) ? ' is-invalid' : ''; ?>" id="poster_image_upload">
                                <input type="file" id="poster_image" name="poster_image" accept="image/*" onchange="handleFileUpload(this)">
                                <label for="poster_image" class="file-upload-label">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <div>
                                        <strong>Click to upload</strong> or drag and drop<br>
                                        <span style="font-size: 12px; opacity: 0.7;">PNG, JPG, GIF up to 5MB</span>
                                    </div>
                                </label>
                            </div>
                            <div id="poster_image_preview" class="file-preview-container" style="display: none;"></div>
                        </div>
                        <?php if (!empty($field_errors['poster_image'])): ?><div class="invalid-feedback"><?php echo htmlspecialchars($field_errors['poster_image']); ?></div><?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="poster_image_2">Secondary poster</label>
                        <div class="file-upload-container">
                            <div class="file-upload<?php echo isset($field_errors['poster_image_2']) ? ' is-invalid' : ''; ?>" id="poster_image_2_upload">
                                <input type="file" id="poster_image_2" name="poster_image_2" accept="image/*" onchange="handleFileUpload(this)">
                                <label for="poster_image_2" class="file-upload-label">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <div>
                                        <strong>Click to upload</strong> or drag and drop<br>
                                        <span style="font-size: 12px; opacity: 0.7;">PNG, JPG, GIF up to 5MB (optional)</span>
                                    </div>
                                </label>
                            </div>
                            <div id="poster_image_2_preview" class="file-preview-container" style="display: none;"></div>
                        </div>
                        <?php if (!empty($field_errors['poster_image_2'])): ?><div class="invalid-feedback"><?php echo htmlspecialchars($field_errors['poster_image_2']); ?></div><?php endif; ?>
                    </div>
                </section>

                <details class="form-extra">
                    <summary>Extra</summary>
                <div class="form-row">
                    <div class="form-group">
                        <label for="tax_inclusive">Is the 8% SST already inside the price?</label>
                        <select id="tax_inclusive" name="tax_inclusive" class="form-control" onchange="calculateTax()">
                            <option value="0" <?php echo (isset($_POST['tax_inclusive']) && $_POST['tax_inclusive'] == '0') ? 'selected' : ''; ?>>No</option>
                            <option value="1" <?php echo (isset($_POST['tax_inclusive']) && $_POST['tax_inclusive'] == '1') ? 'selected' : ''; ?>>Yes</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="tax_amount">SST Amount (RM) <small style="color: #64748b; font-weight: normal;">(8% auto-calculated)</small></label>
                        <input type="number" step="0.01" id="tax_amount" name="tax_amount" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['tax_amount'] ?? ''); ?>" 
                               placeholder="Auto-calculated" readonly style="background-color: #f1f5f9; cursor: not-allowed;">
                        <small class="form-text text-muted">SST amount is automatically calculated at 8%</small>
                    </div>
                </div>
                
                    <div class="form-row">
                <div class="form-group">
                    <label for="category">Category</label>
                    <input type="text" id="category" name="category" class="form-control" 
                           value="<?php echo htmlspecialchars($_POST['category'] ?? ''); ?>">
                    <small class="form-text text-muted">Enter the category for this programme</small>
                </div>
                
                <div class="form-group">
                            <label for="max_participants">Max Participants</label>
                            <input type="number" id="max_participants" name="max_participants" min="1" class="form-control" 
                                   value="<?php echo htmlspecialchars($_POST['max_participants'] ?? '25'); ?>">
                    </div>
                </div>
                
                    <div class="form-row">
                        <div class="form-group">
                            <label for="certification">Certification</label>
                            <input type="text" id="certification" name="certification" class="form-control" 
                                   value="<?php echo htmlspecialchars($_POST['certification'] ?? 'HRD Corp Certified'); ?>" 
                                   placeholder="e.g., HRD Corp Certified">
                        </div>
                        
                        <div class="form-group">
                            <label for="programme_icon">Programme Icon</label>
                            <select id="programme_icon" name="programme_icon" class="form-control">
                                
                                <!-- Leadership & Management -->
                                <optgroup label="🎯 Leadership & Management">
                                    <option value="fas fa-graduation-cap" <?php echo ($_POST['programme_icon'] ?? 'fas fa-graduation-cap') === 'fas fa-graduation-cap' ? 'selected' : ''; ?>>🎓 Graduation Cap</option>
                                    <option value="fas fa-crown" <?php echo ($_POST['programme_icon'] ?? '') === 'fas fa-crown' ? 'selected' : ''; ?>>👑 Crown</option>
                                    <option value="fas fa-star" <?php echo ($_POST['programme_icon'] ?? '') === 'fas fa-star' ? 'selected' : ''; ?>>⭐ Star</option>
                                    <option value="fas fa-trophy" <?php echo ($_POST['programme_icon'] ?? '') === 'fas fa-trophy' ? 'selected' : ''; ?>>🏆 Trophy</option>
                                    <option value="fas fa-medal" <?php echo ($_POST['programme_icon'] ?? '') === 'fas fa-medal' ? 'selected' : ''; ?>>🥇 Medal</option>
                                    <option value="fas fa-user-tie" <?php echo ($_POST['programme_icon'] ?? '') === 'fas fa-user-tie' ? 'selected' : ''; ?>>👔 Business Person</option>
                                    <option value="fas fa-users" <?php echo ($_POST['programme_icon'] ?? '') === 'fas fa-users' ? 'selected' : ''; ?>>👥 Team</option>
                                </optgroup>
                                
                                <!-- Analytics & Data -->
                                <optgroup label="📊 Analytics & Data">
                                    <option value="fas fa-chart-line" <?php echo ($_POST['programme_icon'] ?? '') === 'fas fa-chart-line' ? 'selected' : ''; ?>>📈 Line Chart</option>
                                    <option value="fas fa-chart-bar" <?php echo ($_POST['programme_icon'] ?? '') === 'fas fa-chart-bar' ? 'selected' : ''; ?>>📊 Bar Chart</option>
                                    <option value="fas fa-chart-pie" <?php echo ($_POST['programme_icon'] ?? '') === 'fas fa-chart-pie' ? 'selected' : ''; ?>>🥧 Pie Chart</option>
                                    <option value="fas fa-calculator" <?php echo ($_POST['programme_icon'] ?? '') === 'fas fa-calculator' ? 'selected' : ''; ?>>🧮 Calculator</option>
                                    <option value="fas fa-database" <?php echo ($_POST['programme_icon'] ?? '') === 'fas fa-database' ? 'selected' : ''; ?>>🗄️ Database</option>
                                    <option value="fas fa-brain" <?php echo ($_POST['programme_icon'] ?? '') === 'fas fa-brain' ? 'selected' : ''; ?>>🧠 Brain</option>
                                </optgroup>
                                
                                <!-- Communication & Marketing -->
                                <optgroup label="💬 Communication & Marketing">
                                    <option value="fas fa-bullhorn" <?php echo ($_POST['programme_icon'] ?? '') === 'fas fa-bullhorn' ? 'selected' : ''; ?>>📢 Bullhorn</option>
                                    <option value="fas fa-comments" <?php echo ($_POST['programme_icon'] ?? '') === 'fas fa-comments' ? 'selected' : ''; ?>>💬 Comments</option>
                                    <option value="fas fa-phone" <?php echo ($_POST['programme_icon'] ?? '') === 'fas fa-phone' ? 'selected' : ''; ?>>📞 Phone</option>
                                    <option value="fas fa-envelope" <?php echo ($_POST['programme_icon'] ?? '') === 'fas fa-envelope' ? 'selected' : ''; ?>>✉️ Envelope</option>
                                    <option value="fas fa-video" <?php echo ($_POST['programme_icon'] ?? '') === 'fas fa-video' ? 'selected' : ''; ?>>📹 Video</option>
                                    <option value="fas fa-microphone" <?php echo ($_POST['programme_icon'] ?? '') === 'fas fa-microphone' ? 'selected' : ''; ?>>🎤 Microphone</option>
                                </optgroup>
                                
                                <!-- Project & Task Management -->
                                <optgroup label="✅ Project & Task Management">
                                    <option value="fas fa-tasks" <?php echo ($_POST['programme_icon'] ?? '') === 'fas fa-tasks' ? 'selected' : ''; ?>>✅ Tasks</option>
                                    <option value="fas fa-project-diagram" <?php echo ($_POST['programme_icon'] ?? '') === 'fas fa-project-diagram' ? 'selected' : ''; ?>>📋 Project Diagram</option>
                                    <option value="fas fa-calendar-check" <?php echo ($_POST['programme_icon'] ?? '') === 'fas fa-calendar-check' ? 'selected' : ''; ?>>📅 Calendar Check</option>
                                    <option value="fas fa-clock" <?php echo ($_POST['programme_icon'] ?? '') === 'fas fa-clock' ? 'selected' : ''; ?>>⏰ Clock</option>
                                    <option value="fas fa-check-circle" <?php echo ($_POST['programme_icon'] ?? '') === 'fas fa-check-circle' ? 'selected' : ''; ?>>✅ Check Circle</option>
                                    <option value="fas fa-list-check" <?php echo ($_POST['programme_icon'] ?? '') === 'fas fa-list-check' ? 'selected' : ''; ?>>📝 Checklist</option>
                                </optgroup>
                                
                                <!-- HR & Recruitment -->
                                <optgroup label="👥 HR & Recruitment">
                                    <option value="fas fa-user-plus" <?php echo ($_POST['programme_icon'] ?? '') === 'fas fa-user-plus' ? 'selected' : ''; ?>>➕ Add User</option>
                                    <option value="fas fa-user-edit" <?php echo ($_POST['programme_icon'] ?? '') === 'fas fa-user-edit' ? 'selected' : ''; ?>>✏️ Edit User</option>
                                    <option value="fas fa-handshake" <?php echo ($_POST['programme_icon'] ?? '') === 'fas fa-handshake' ? 'selected' : ''; ?>>🤝 Handshake</option>
                                    <option value="fas fa-briefcase" <?php echo ($_POST['programme_icon'] ?? '') === 'fas fa-briefcase' ? 'selected' : ''; ?>>💼 Briefcase</option>
                                    <option value="fas fa-id-card" <?php echo ($_POST['programme_icon'] ?? '') === 'fas fa-id-card' ? 'selected' : ''; ?>>🆔 ID Card</option>
                                    <option value="fas fa-user-graduate" <?php echo ($_POST['programme_icon'] ?? '') === 'fas fa-user-graduate' ? 'selected' : ''; ?>>🎓 Graduate</option>
                                </optgroup>
                                
                                <!-- Technology & Skills -->
                                <optgroup label="💻 Technology & Skills">
                                    <option value="fas fa-laptop-code" <?php echo ($_POST['programme_icon'] ?? '') === 'fas fa-laptop-code' ? 'selected' : ''; ?>>💻 Laptop Code</option>
                                    <option value="fas fa-code" <?php echo ($_POST['programme_icon'] ?? '') === 'fas fa-code' ? 'selected' : ''; ?>>💻 Code</option>
                                    <option value="fas fa-cogs" <?php echo ($_POST['programme_icon'] ?? '') === 'fas fa-cogs' ? 'selected' : ''; ?>>⚙️ Cogs</option>
                                    <option value="fas fa-tools" <?php echo ($_POST['programme_icon'] ?? '') === 'fas fa-tools' ? 'selected' : ''; ?>>🔧 Tools</option>
                                    <option value="fas fa-lightbulb" <?php echo ($_POST['programme_icon'] ?? '') === 'fas fa-lightbulb' ? 'selected' : ''; ?>>💡 Lightbulb</option>
                                    <option value="fas fa-rocket" <?php echo ($_POST['programme_icon'] ?? '') === 'fas fa-rocket' ? 'selected' : ''; ?>>🚀 Rocket</option>
                                </optgroup>
                                
                                <!-- Finance & Business -->
                                <optgroup label="💰 Finance & Business">
                                    <option value="fas fa-dollar-sign" <?php echo ($_POST['programme_icon'] ?? '') === 'fas fa-dollar-sign' ? 'selected' : ''; ?>>💰 Dollar Sign</option>
                                    <option value="fas fa-chart-line" <?php echo ($_POST['programme_icon'] ?? '') === 'fas fa-chart-line' ? 'selected' : ''; ?>>📈 Growth Chart</option>
                                    <option value="fas fa-building" <?php echo ($_POST['programme_icon'] ?? '') === 'fas fa-building' ? 'selected' : ''; ?>>🏢 Building</option>
                                    <option value="fas fa-coins" <?php echo ($_POST['programme_icon'] ?? '') === 'fas fa-coins' ? 'selected' : ''; ?>>🪙 Coins</option>
                                    <option value="fas fa-piggy-bank" <?php echo ($_POST['programme_icon'] ?? '') === 'fas fa-piggy-bank' ? 'selected' : ''; ?>>🐷 Piggy Bank</option>
                                    <option value="fas fa-balance-scale" <?php echo ($_POST['programme_icon'] ?? '') === 'fas fa-balance-scale' ? 'selected' : ''; ?>>⚖️ Balance Scale</option>
                                </optgroup>
                                
                                <!-- Custom Option -->
                                <optgroup label="🔧 Custom">
                                    <option value="custom">📝 Enter Custom Icon Class</option>
                                </optgroup>
                            </select>
                            
                            <!-- Custom icon input (hidden by default) -->
                            <div id="custom-icon-input" style="display: none; margin-top: 10px;">
                                <input type="text" id="custom_icon_class" name="custom_icon_class" class="form-control" 
                                       placeholder="e.g., fas fa-custom-icon">
                                <small class="form-text text-muted">Enter a Font Awesome icon class (e.g., fas fa-custom-icon)</small>
                            </div>
                            
                            <!-- Icon Preview -->
                            <div id="icon-preview" style="margin-top: 10px; padding: 15px; background: #f8f9fa; border-radius: 8px; text-align: center;">
                                <div style="font-size: 2rem; color: #111111; margin-bottom: 10px;">
                                    <i id="preview-icon" class="fas fa-graduation-cap"></i>
                                </div>
                                <small style="color: #64748b;">Icon Preview</small>
                        </div>
                            
                            <small class="form-text text-muted">Select an icon that best represents your programme. The icon will appear in programme cards and poster overlays.</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="programme_badge">Programme Badge</label>
                        <input type="text" id="programme_badge" name="programme_badge" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['programme_badge'] ?? ''); ?>" 
                               placeholder="e.g., Popular, Advanced, Essential">
                    </div>
                    
                    <div class="form-group">
                        <label for="modules">Key Modules</label>
                        <textarea id="modules" name="modules" class="form-control" rows="4" 
                                  placeholder="Enter each key module on a new line:&#10;Strategic Planning&#10;Change Management&#10;Team Leadership&#10;Performance Management"><?php echo htmlspecialchars($_POST['modules'] ?? ''); ?></textarea>
                        <small class="form-text text-muted">Enter each key module on a separate line (press Enter for new module)</small>
                    </div>
                    
                    <div style="grid-column: 1 / -1; width: 100%; display: flex; flex-direction: column; gap: 2rem;">
                    <!-- Facility Booking Section -->
                    <div class="card" style="margin-top: 0; width: 100%; display: block; clear: both;">
                        <div class="card-header">
                            <h3><i class="fas fa-building"></i> Facility Booking Options</h3>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <label class="checkbox-label">
                                    <input type="checkbox" name="requires_facility" value="1" 
                                           <?php echo isset($_POST['requires_facility']) ? 'checked' : ''; ?>
                                           onchange="toggleFacilityOptions()">
                                    <span class="checkmark"></span>
                                    <div class="checkbox-text">
                                        <div class="checkbox-main-text">Requires Facility Booking</div>
                                        <div class="checkbox-description">This programme needs a physical facility (conference room, training room, etc.)</div>
                                    </div>
                                </label>
                            </div>
                            
                            <div id="facility-options" style="display: none;">
                                <div class="form-group">
                                    <label for="facility_type">Facility Type</label>
                                    <select id="facility_type" name="facility_type" class="form-control<?php echo isset($field_errors['facility_type']) ? ' is-invalid' : ''; ?>">
                                        <option value="">Select Facility Type</option>
                                        <option value="conference_room" <?php echo ($_POST['facility_type'] ?? '') === 'conference_room' ? 'selected' : ''; ?>>Conference Room</option>
                                        <option value="training_room" <?php echo ($_POST['facility_type'] ?? '') === 'training_room' ? 'selected' : ''; ?>>Training Room</option>
                                        <option value="breakout_area" <?php echo ($_POST['facility_type'] ?? '') === 'breakout_area' ? 'selected' : ''; ?>>Breakout Area</option>
                                    </select>
                                    <?php if (!empty($field_errors['facility_type'])): ?><div class="invalid-feedback"><?php echo htmlspecialchars($field_errors['facility_type']); ?></div><?php endif; ?>
                                </div>
                                
                                <div class="form-group">
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="auto_create_facility_booking" value="1" 
                                               <?php echo isset($_POST['auto_create_facility_booking']) ? 'checked' : 'checked'; ?>>
                                        <span class="checkmark"></span>
                                        <div class="checkbox-text">
                                            <div class="checkbox-main-text">Auto-Create Facility Booking</div>
                                            <div class="checkbox-description">Automatically create a facility booking when this programme is saved</div>
                                        </div>
                                    </label>
                                </div>
                                
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i>
                                    <strong>Note:</strong> When facility booking is enabled, the programme's start date, start time, and end time will be used to create the facility booking. Make sure these fields are properly filled.
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card" style="margin-top: 0; width: 100%; display: block; clear: both;">
                        <div class="card-header">
                            <h3><i class="fas fa-list-check"></i> Programme Registration Questions</h3>
                        </div>
                        <div class="card-body">
                            <p class="form-text text-muted" style="margin-bottom: 16px;">
                                Tick which questions should appear in the programme registration form. You can also choose question type and whether each one is required.
                            </p>
                            <?php foreach ($question_templates as $index => $question): ?>
                                <?php
                                $postedConfig = $_POST['question_configs'][$index] ?? [];
                                $isEnabled = isset($postedConfig['is_enabled']) ? 1 : (int)$question['is_enabled'];
                                $isRequired = isset($postedConfig['is_required']) ? 1 : (int)$question['is_required'];
                                $inputType = $postedConfig['input_type'] ?? $question['input_type'];
                                $fieldLabel = $postedConfig['field_label'] ?? $question['field_label'];
                                $fieldOptions = $postedConfig['field_options'] ?? $question['field_options'];
                                ?>
                                <div style="border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px; margin-bottom: 12px; background: #f8fafc;">
                                    <input type="hidden" name="question_configs[<?php echo $index; ?>][field_key]" value="<?php echo htmlspecialchars($question['field_key']); ?>">
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label>Question Label</label>
                                            <input type="text" class="form-control" name="question_configs[<?php echo $index; ?>][field_label]" value="<?php echo htmlspecialchars($fieldLabel); ?>">
                                        </div>
                                        <div class="form-group">
                                            <label>Input Type</label>
                                            <select class="form-control" name="question_configs[<?php echo $index; ?>][input_type]">
                                                <?php foreach (['text' => 'Text Input', 'email' => 'Email Input', 'tel' => 'Phone Input', 'textarea' => 'Textarea', 'select' => 'Select Dropdown'] as $value => $label): ?>
                                                    <option value="<?php echo $value; ?>" <?php echo $inputType === $value ? 'selected' : ''; ?>>
                                                        <?php echo $label; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label>Dropdown Options (for Select type)</label>
                                        <textarea class="form-control" rows="2" name="question_configs[<?php echo $index; ?>][field_options]" placeholder="value|Label (one option per line)"><?php echo htmlspecialchars($fieldOptions); ?></textarea>
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label class="checkbox-label">
                                                <input type="checkbox" name="question_configs[<?php echo $index; ?>][is_enabled]" value="1" <?php echo $isEnabled ? 'checked' : ''; ?>>
                                                <span class="checkmark"></span>
                                                <div class="checkbox-text">
                                                    <div class="checkbox-main-text">Show in Registration Form</div>
                                                </div>
                                            </label>
                                        </div>
                                        <div class="form-group">
                                            <label class="checkbox-label">
                                                <input type="checkbox" name="question_configs[<?php echo $index; ?>][is_required]" value="1" <?php echo $isRequired ? 'checked' : ''; ?>>
                                                <span class="checkmark"></span>
                                                <div class="checkbox-text">
                                                    <div class="checkbox-main-text">Required Field</div>
                                                </div>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    </div>

                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="is_active" value="1" 
                                   <?php echo isset($_POST['is_active']) ? 'checked' : 'checked'; ?>>
                            <span class="checkmark"></span>
                            <div class="checkbox-text">
                                <div class="checkbox-main-text">Active Programme</div>
                                <div class="checkbox-description">Active programmes will be visible on the website</div>
                            </div>
                        </label>
                    </div>
                </details>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i>
                            Create Programme
                    </button>
                        <a href="programmes.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i>
                            Cancel
                        </a>
                </div>
            </form>
        </div>
        </div>
    </div>
    
    <script>
// Function to remove file preview and clear input
function removeFilePreview(inputId) {
    // Get the containers
    const previewContainer = document.getElementById(inputId + '_preview');
    const uploadContainer = document.getElementById(inputId + '_upload');
    const fileInput = document.getElementById(inputId);
    
    if (previewContainer && uploadContainer && fileInput) {
        // Clear the file input
        fileInput.value = '';
        
        // Hide the preview
        previewContainer.style.display = 'none';
        previewContainer.innerHTML = '';
        
        // Show the upload area again
        uploadContainer.style.display = 'block';
    }
}

// Enhanced file upload functionality
function handleFileUpload(input) {
    const file = input.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            // Get the preview container
            const previewContainer = document.getElementById(input.id + '_preview');
            const uploadContainer = document.getElementById(input.id + '_upload');
            
            if (previewContainer && uploadContainer) {
                // Hide the upload area
                uploadContainer.style.display = 'none';
                
                // Show the preview
                previewContainer.style.display = 'block';
                previewContainer.innerHTML = `
                    <div class="file-preview">
                        <div class="preview-image">
                            <img src="${e.target.result}" alt="Preview" style="max-width: 200px; max-height: 150px; border-radius: 8px;">
                        </div>
                        <div class="preview-info">
                            <div class="file-name">${file.name}</div>
                            <div class="file-size">(${(file.size / 1024 / 1024).toFixed(2)} MB)</div>
                        </div>
                        <button type="button" onclick="removeFilePreview('${input.id}')" class="remove-file" aria-label="Remove">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                `;
            }
        };
        reader.readAsDataURL(file);
    }
}

// Enhanced form interactions
document.addEventListener('DOMContentLoaded', function() {
    const formControls = document.querySelectorAll('.form-control');
    
    formControls.forEach(control => {
        control.addEventListener('focus', function() {
            this.parentElement.style.transform = 'translateY(-2px)';
        });
        
        control.addEventListener('blur', function() {
            this.parentElement.style.transform = 'translateY(0)';
        });
    });
    
    // Icon preview functionality with proper null checks
    const programmeIcon = document.getElementById('programme_icon');
    const customIconInput = document.getElementById('custom-icon-input');
    const customIconClass = document.getElementById('custom_icon_class');
    const previewIcon = document.getElementById('preview-icon');
    
    // Icon functionality - only proceed if all required elements exist
    if (programmeIcon && customIconInput && customIconClass && previewIcon) {
        let customIconListener = null; // Track custom icon listener to prevent duplicates
        
        function updateIconPreview(iconClass) {
            if (previewIcon && iconClass) {
                previewIcon.className = iconClass;
            } else if (previewIcon) {
                previewIcon.className = 'fas fa-graduation-cap';
            }
        }
        
        programmeIcon.addEventListener('change', function() {
            const selectedValue = this.value;
            
            if (selectedValue === 'custom') {
                // Show custom input
                customIconInput.style.display = 'block';
                if (customIconClass) {
                    customIconClass.focus();
                    
                    // Remove existing listener to prevent duplicates
                    if (customIconListener) {
                        customIconClass.removeEventListener('input', customIconListener);
                    }
                    
                    // Add new listener
                    customIconListener = function() {
                        updateIconPreview(this.value || 'fas fa-graduation-cap');
                    };
                    customIconClass.addEventListener('input', customIconListener);
                }
            } else {
                // Hide custom input
                customIconInput.style.display = 'none';
                
                // Remove custom icon listener
                if (customIconListener && customIconClass) {
                    customIconClass.removeEventListener('input', customIconListener);
                    customIconListener = null;
                }
                
                // Update preview with selected icon
                updateIconPreview(selectedValue);
            }
        });
        
        // Handle form submission for custom icons
        const form = document.querySelector('form');
        if (form) {
            form.addEventListener('submit', function(e) {
                if (programmeIcon.value === 'custom' && customIconClass && customIconClass.value.trim()) {
                    // Create a hidden input to send the custom icon value
                    const hiddenInput = document.createElement('input');
                    hiddenInput.type = 'hidden';
                    hiddenInput.name = 'programme_icon';
                    hiddenInput.value = customIconClass.value.trim();
                    
                    // Remove any existing hidden input with the same name
                    const existingHidden = form.querySelector('input[name="programme_icon"][type="hidden"]');
                    if (existingHidden) {
                        existingHidden.remove();
                    }
                    
                    // Add the new hidden input to the form
                    form.appendChild(hiddenInput);
                }
            });
        }
        
        // Initialize icon preview with default value
        if (programmeIcon.value) {
            updateIconPreview(programmeIcon.value);
        }
    } else {
        console.warn('Icon functionality: Some required elements not found');
    }
    
    // Schedule type change handler for text input
    const scheduleType = document.getElementById('schedule_type');
    const dateFields = document.querySelectorAll('#start_date, #end_date, #start_time, #end_time');
    
    if (scheduleType) {
        scheduleType.addEventListener('input', function() {
            // Auto-set end date to start date if single_day is typed
            const startDate = document.getElementById('start_date');
            const endDate = document.getElementById('end_date');
            
            if (this.value.toLowerCase().trim() === 'single_day' && startDate.value && !endDate.value) {
                endDate.value = startDate.value;
            }
        });
    }
    
    // Auto-save draft functionality
    const form = document.querySelector('form');
    const formData = new FormData(form);
    
    // Save form data to localStorage every 30 seconds
    setInterval(() => {
        const formElements = form.elements;
        const draftData = {};
        
        for (let element of formElements) {
            if (element.name && element.value) {
                draftData[element.name] = element.value;
            }
        }
        
        localStorage.setItem('programme_draft', JSON.stringify(draftData));
    }, 30000);
    
    // Load draft data on page load
    const savedDraft = localStorage.getItem('programme_draft');
    if (savedDraft) {
        const draftData = JSON.parse(savedDraft);
        Object.keys(draftData).forEach(key => {
            const element = form.querySelector(`[name="${key}"]`);
            if (element && !element.value) {
                element.value = draftData[key];
            }
        });
    }
    
    // Clear draft on successful submission
    form.addEventListener('submit', function() {
        localStorage.removeItem('programme_draft');
    });
});

// Enhanced validation
function validateForm() {
    const title = document.getElementById('title').value.trim();
    const description = document.getElementById('description').value.trim();
    const price = document.getElementById('programme_price').value;
    
    let isValid = true;
    let errorMessage = '';
    
    if (!title) {
        errorMessage = 'Programme title is required';
        isValid = false;
    } else if (!description) {
        errorMessage = 'Programme description is required';
        isValid = false;
    } else if (!price || price.trim() === '') {
        errorMessage = 'Please enter a price';
        isValid = false;
    }
    
    if (!isValid) {
        showToast(errorMessage, 'error');
        return false;
    }
    
    return true;
}

// Toast notification function
function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
        <span>${message}</span>
    `;
    
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.style.transform = 'translateX(0)';
        toast.style.opacity = '1';
    }, 100);
    
    setTimeout(() => {
        toast.style.transform = 'translateX(100%)';
        toast.style.opacity = '0';
        setTimeout(() => {
            document.body.removeChild(toast);
        }, 300);
    }, 3000);
}

// Calculate tax dynamically based on 8% SST
function calculateTax() {
    const priceInput = document.getElementById('programme_price');
    const taxTypeSelect = document.getElementById('tax_inclusive');
    const taxAmountInput = document.getElementById('tax_amount');
    
    if (!priceInput || !taxTypeSelect || !taxAmountInput) return;
    
    const price = parseFloat(priceInput.value);
    if (isNaN(price)) {
        // Don't auto-clear if they are typing 'Free' or text, just do nothing
        taxAmountInput.value = '';
        return;
    }
    
    const isInclusive = taxTypeSelect.value === '1';
    let taxAmount = 0;
    
    // 8% SST
    if (isInclusive) {
        // Tax = Price - (Price / 1.08)
        taxAmount = price - (price / 1.08);
    } else {
        // Tax = Price * 0.08
        taxAmount = price * 0.08;
    }
    
    taxAmountInput.value = taxAmount.toFixed(2);
}

// Toggle facility options visibility
function toggleFacilityOptions() {
    const requiresFacility = document.querySelector('input[name="requires_facility"]');
    const facilityOptions = document.getElementById('facility-options');
    
    if (requiresFacility.checked) {
        facilityOptions.style.display = 'block';
    } else {
        facilityOptions.style.display = 'none';
    }
}

// Initialize facility options on page load
document.addEventListener('DOMContentLoaded', function() {
    toggleFacilityOptions();
    calculateTax();
});

document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    form.addEventListener('submit', function(e) {
        const descriptionInput = document.getElementById('description');
        // Encode the text (including emojis) so the WAF doesn't block it
        descriptionInput.value = encodeURIComponent(descriptionInput.value);
    });
});
    </script>
    <script src="../assets/js/motion.js?v=20261002-10"></script>
</body>
</html> 