<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

// Include database connection
require_once '../includes/database.php';
require_once '../includes/programme-question-config.php';

$error = '';
$success = '';
$field_errors = [];
$programme = null;
$question_configs = [];

// Get programme ID from URL
$programme_id = $_GET['id'] ?? null;

if (!$programme_id) {
    header('Location: programmes.php?error=Invalid programme ID');
    exit;
}

$pdo = get_db_connection();

if (!$pdo) {
    header('Location: programmes.php?error=Database connection failed');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
        
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $registration_status = trim($_POST['registration_status'] ?? 'open');
        $requires_facility = isset($_POST['requires_facility']) ? 1 : 0;
        $facility_type = !empty($_POST['facility_type']) ? $_POST['facility_type'] : null;
        $auto_create_facility_booking = isset($_POST['auto_create_facility_booking']) ? 1 : 0;
        
        // Initialize errors array
        $errors = [];
        
        // Handle poster image upload
        $poster_image = $_POST['current_poster_image'] ?? null; // Keep existing image by default
        
        // Check if user wants to remove the image
        if (isset($_POST['remove_poster_image']) && $_POST['remove_poster_image'] == '1') {
            $poster_image = null;
            // Delete old image if it exists
            if (!empty($_POST['current_poster_image']) && file_exists('../' . $_POST['current_poster_image'])) {
                unlink('../' . $_POST['current_poster_image']);
            }
        } elseif (isset($_FILES['poster_image']) && $_FILES['poster_image']['error'] === UPLOAD_ERR_OK) {
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
                    
                    // Delete old image if it exists
                    if (!empty($_POST['current_poster_image']) && file_exists('../' . $_POST['current_poster_image'])) {
                        unlink('../' . $_POST['current_poster_image']);
                    }
                } else {
                    $field_errors['poster_image'] = 'The poster could not be uploaded. Try again.';
                }
            }
        }
        
        // Handle second poster image upload
        $poster_image_2 = $_POST['current_poster_image_2'] ?? null; // Keep existing image by default
        
        // Check if user wants to remove the second image
        if (isset($_POST['remove_poster_image_2']) && $_POST['remove_poster_image_2'] == '1') {
            $poster_image_2 = null;
            // Delete old image if it exists
            if (!empty($_POST['current_poster_image_2']) && file_exists('../' . $_POST['current_poster_image_2'])) {
                unlink('../' . $_POST['current_poster_image_2']);
            }
        } elseif (isset($_FILES['poster_image_2']) && $_FILES['poster_image_2']['error'] === UPLOAD_ERR_OK) {
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
                    
                    // Delete old image if it exists
                    if (!empty($_POST['current_poster_image_2']) && file_exists('../' . $_POST['current_poster_image_2'])) {
                        unlink('../' . $_POST['current_poster_image_2']);
                    }
                } else {
                    $field_errors['poster_image_2'] = 'The second poster could not be uploaded. Try again.';
                }
            }
        }
        
        // Handle banner image upload (auto-sets featured)
        $banner_image = $_POST['current_banner_image'] ?? null; // Keep existing image by default
        
        // Check if user wants to remove the banner image
        if (isset($_POST['remove_banner_image']) && $_POST['remove_banner_image'] == '1') {
            $banner_image = null;
            // Delete old image if it exists
            if (!empty($_POST['current_banner_image']) && file_exists('../' . $_POST['current_banner_image'])) {
                unlink('../' . $_POST['current_banner_image']);
            }
        } elseif (isset($_FILES['banner_image']) && $_FILES['banner_image']['error'] === UPLOAD_ERR_OK) {
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
                    
                    // Delete old banner image if it exists
                    if (!empty($_POST['current_banner_image']) && file_exists('../' . $_POST['current_banner_image'])) {
                        unlink('../' . $_POST['current_banner_image']);
                    }
                } else {
                    $field_errors['banner_image'] = 'The banner could not be uploaded. Try again.';
                }
            }
        }
        
        // Auto-set is_featured based on banner_image presence
        $is_featured = !empty($banner_image) ? 1 : 0;
        
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
        
        if (empty($field_errors)) {
            // Update programme in database
            $stmt = $pdo->prepare("
                UPDATE programmes 
                SET title = ?, description = ?, duration = ?, price = ?, tax_inclusive = ?, tax_amount = ?, original_price = ?, category = ?, 
                    max_participants = ?, certification = ?, programme_icon = ?, 
                    programme_badge = ?, location = ?, poster_image = ?, poster_image_2 = ?, banner_image = ?, modules = ?, schedule_type = ?, start_date = ?, 
                    end_date = ?, start_time = ?, end_time = ?, requires_facility = ?, facility_type = ?, auto_create_facility_booking = ?,
                    is_active = ?, registration_status = ?, is_featured = ?, updated_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([$title, $description, $duration, $price, $tax_inclusive, $tax_amount, $original_price, $category, 
                          $max_participants, $certification, $programme_icon, 
                          $programme_badge, $location, $poster_image, $poster_image_2, $banner_image, $modules, $schedule_type, $start_date, 
                          $end_date, $start_time, $end_time, $requires_facility, $facility_type, $auto_create_facility_booking,
                          $is_active, $registration_status, $is_featured, $programme_id]);
            saveProgrammeQuestionConfigs($pdo, (int)$programme_id, $_POST['question_configs'] ?? getDefaultProgrammeQuestions());
            
            $success = 'Training programme updated successfully!';
        } else {
            $error = 'Please check the highlighted fields.';
        }
        
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}

// Get programme data
try {
    $stmt = $pdo->prepare("SELECT * FROM programmes WHERE id = ?");
    $stmt->execute([$programme_id]);
    $programme = $stmt->fetch();
    
    if (!$programme) {
        header('Location: programmes.php?error=Programme not found');
        exit;
    }
    $question_configs = getProgrammeQuestionConfigs($pdo, (int)$programme_id, false);
    if (!empty($field_errors) && $_SERVER['REQUEST_METHOD'] === 'POST') {
        foreach (['title', 'description', 'duration', 'location', 'category', 'certification', 'programme_icon', 'programme_badge', 'modules', 'schedule_type', 'start_date', 'end_date', 'start_time', 'end_time', 'facility_type'] as $posted_key) {
            if (array_key_exists($posted_key, $_POST)) {
                $programme[$posted_key] = $_POST[$posted_key];
            }
        }
        if (array_key_exists('programme_price', $_POST)) {
            $programme['price'] = $_POST['programme_price'];
        }
        if (array_key_exists('programme_original_price', $_POST)) {
            $programme['original_price'] = $_POST['programme_original_price'];
        }
        if (array_key_exists('tax_inclusive', $_POST)) {
            $programme['tax_inclusive'] = $_POST['tax_inclusive'];
        }
        if (array_key_exists('tax_amount', $_POST)) {
            $programme['tax_amount'] = $_POST['tax_amount'];
        }
        if (array_key_exists('max_participants', $_POST)) {
            $programme['max_participants'] = $_POST['max_participants'];
        }
    }
} catch (PDOException $e) {
    header('Location: programmes.php?error=Database error');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Programme - XpressPoint Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="admin-styles.css?v=20261005-5">
</head>
<body data-motion="admin">
    <?php include_once 'includes/header.php'; renderAdminHeader('Edit Training Programme', 'fas fa-edit', ['url' => 'programmes.php', 'label' => 'Programmes']); ?>

    <div class="admin-container">
        <!-- Page Header -->
        <div class="page-header">
            <h2 class="page-title">Edit Training Programme</h2>
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
                <form method="POST" enctype="multipart/form-data">
                    <p class="form-intro">Fields with a star are required. You can add images and extras after the first save.</p>
                    <section class="form-section">
                        <h4 class="form-section-title">What people see</h4>
                    <div class="form-group">
                        <label for="title">Programme Title *</label>
                        <input type="text" id="title" name="title" class="form-control<?php echo isset($field_errors['title']) ? ' is-invalid' : ''; ?>" 
                               value="<?php echo htmlspecialchars($programme['title']); ?>" required>
                        <?php if (!empty($field_errors['title'])): ?><div class="invalid-feedback"><?php echo htmlspecialchars($field_errors['title']); ?></div><?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Programme Description *</label>
                        <textarea id="description" name="description" class="form-control<?php echo isset($field_errors['description']) ? ' is-invalid' : ''; ?>" rows="4" required><?php echo htmlspecialchars($programme['description']); ?></textarea>
                        <?php if (!empty($field_errors['description'])): ?><div class="invalid-feedback"><?php echo htmlspecialchars($field_errors['description']); ?></div><?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="location">Location *</label>
                        <input type="text" id="location" name="location" class="form-control<?php echo isset($field_errors['location']) ? ' is-invalid' : ''; ?>" 
                               value="<?php echo htmlspecialchars($programme['location'] ?? ''); ?>" required>
                        <small class="form-text text-muted">City or Remote.</small>
                        <?php if (!empty($field_errors['location'])): ?><div class="invalid-feedback"><?php echo htmlspecialchars($field_errors['location']); ?></div><?php endif; ?>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="duration">Duration *</label>
                            <input type="text" id="duration" name="duration" class="form-control<?php echo isset($field_errors['duration']) ? ' is-invalid' : ''; ?>" 
                                   value="<?php echo htmlspecialchars($programme['duration']); ?>" placeholder="e.g., 2 days" required>
                            <small class="form-text text-muted">What visitors will read, for example 2 days.</small>
                            <?php if (!empty($field_errors['duration'])): ?><div class="invalid-feedback"><?php echo htmlspecialchars($field_errors['duration']); ?></div><?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label for="programme_price">Current Price (RM) *</label>
                            <input type="text" id="programme_price" name="programme_price" class="form-control<?php echo isset($field_errors['programme_price']) ? ' is-invalid' : ''; ?>" 
                                   value="<?php echo htmlspecialchars($programme['price']); ?>" 
                                   placeholder="e.g., 1500.00, Free, Contact for pricing" oninput="calculateTax()" required>
                            <small class="form-text text-muted">This is the price participants will pay.</small>
                            <?php if (!empty($field_errors['programme_price'])): ?><div class="invalid-feedback"><?php echo htmlspecialchars($field_errors['programme_price']); ?></div><?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="programme_original_price">Original Price (RM)</label>
                        <input type="text" id="programme_original_price" name="programme_original_price" class="form-control" 
                               value="<?php echo htmlspecialchars($programme['original_price'] ?? ''); ?>" 
                               placeholder="e.g., 2000.00 (leave empty if no discount)">
                        <small class="form-text text-muted">Original price before discount. Leave empty if there is no discount.</small>
                    </div>
                    </section>

                    <section class="form-section">
                        <h4 class="form-section-title">When and where</h4>
                        <?php $schedule_value = $programme['schedule_type'] ?? ''; ?>
                        <div class="form-group">
                            <label for="schedule_type">How people attend</label>
                            <select id="schedule_type" name="schedule_type" class="form-control">
                                <option value="">Choose one</option>
                                <option value="in_person" <?php echo $schedule_value === 'in_person' ? 'selected' : ''; ?>>In person</option>
                                <option value="online" <?php echo $schedule_value === 'online' ? 'selected' : ''; ?>>Online</option>
                                <option value="hybrid" <?php echo $schedule_value === 'hybrid' ? 'selected' : ''; ?>>Hybrid</option>
                                <?php if ($schedule_value !== '' && !in_array($schedule_value, ['in_person', 'online', 'hybrid'], true)): ?>
                                <option value="<?php echo htmlspecialchars($schedule_value); ?>" selected><?php echo htmlspecialchars($schedule_value); ?></option>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="start_date">Start Date</label>
                                <input type="date" id="start_date" name="start_date" class="form-control<?php echo isset($field_errors['start_date']) ? ' is-invalid' : ''; ?>" 
                                       value="<?php echo htmlspecialchars($programme['start_date'] ?? ''); ?>">
                                <?php if (!empty($field_errors['start_date'])): ?><div class="invalid-feedback"><?php echo htmlspecialchars($field_errors['start_date']); ?></div><?php endif; ?>
                            </div>
                            <div class="form-group" id="end_date_group">
                                <label for="end_date">End Date</label>
                                <input type="date" id="end_date" name="end_date" class="form-control" 
                                       value="<?php echo htmlspecialchars($programme['end_date'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="start_time">Start Time</label>
                                <input type="time" id="start_time" name="start_time" class="form-control<?php echo isset($field_errors['start_time']) ? ' is-invalid' : ''; ?>" 
                                       value="<?php echo htmlspecialchars($programme['start_time'] ?? ''); ?>">
                                <?php if (!empty($field_errors['start_time'])): ?><div class="invalid-feedback"><?php echo htmlspecialchars($field_errors['start_time']); ?></div><?php endif; ?>
                            </div>
                            <div class="form-group">
                                <label for="end_time">End Time</label>
                                <input type="time" id="end_time" name="end_time" class="form-control" 
                                       value="<?php echo htmlspecialchars($programme['end_time'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="form-group">
                            <div class="schedule-preview" id="schedule_preview" style="display: none;">
                                <label>Schedule Preview:</label>
                                <div class="preview-box" style="background: #f8f9fa; padding: 12px; border-radius: 8px; border-left: 4px solid #111111;">
                                    <div id="preview_text"></div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="form-section">
                        <h4 class="form-section-title">Images</h4>
                        <div class="form-group">
                            <label for="banner_image">Banner</label>
                            <?php if (!empty($programme['banner_image'])): ?>
                                <div class="current-upload" style="margin-bottom: 10px;">
                                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                                        <img src="../<?php echo htmlspecialchars($programme['banner_image']); ?>" alt="Current banner" style="max-width: 400px; height: auto; border-radius: 8px;">
                                        <button type="button" class="btn btn-danger btn-sm" onclick="removeImage('banner_image')"><i class="fas fa-trash"></i> Remove Banner</button>
                                    </div>
                                    <input type="hidden" name="current_banner_image" value="<?php echo htmlspecialchars($programme['banner_image']); ?>">
                                </div>
                            <?php endif; ?>
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
                            <input type="hidden" id="remove_banner_image" name="remove_banner_image" value="0">
                            <small class="form-text text-muted">A banner is what places the programme on the homepage slideshow.</small>
                            <?php if (!empty($field_errors['banner_image'])): ?><div class="invalid-feedback"><?php echo htmlspecialchars($field_errors['banner_image']); ?></div><?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label for="poster_image">Primary poster</label>
                            <?php if (!empty($programme['poster_image'])): ?>
                                <div class="current-upload" style="margin-bottom: 10px;">
                                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                                        <img src="../<?php echo htmlspecialchars($programme['poster_image']); ?>" alt="Current poster" style="max-width: 200px; height: auto; border-radius: 8px;">
                                        <button type="button" class="btn btn-danger btn-sm" onclick="removeImage('poster_image')"><i class="fas fa-trash"></i> Remove Image</button>
                                    </div>
                                    <input type="hidden" name="current_poster_image" value="<?php echo htmlspecialchars($programme['poster_image']); ?>">
                                </div>
                            <?php endif; ?>
                            <div class="file-upload-container">
                                <div class="file-upload<?php echo isset($field_errors['poster_image']) ? ' is-invalid' : ''; ?>" id="poster_image_upload">
                                    <input type="file" id="poster_image" name="poster_image" accept="image/*" onchange="handleFileUpload(this)">
                                    <label for="poster_image" class="file-upload-label">
                                        <i class="fas fa-cloud-upload-alt"></i>
                                        <div>
                                            <strong>Click to upload</strong> or drag and drop<br>
                                            <span style="font-size: 12px; opacity: 0.7;">PNG, JPG, GIF, WebP up to 5MB</span>
                                        </div>
                                    </label>
                                </div>
                                <div id="poster_image_preview" class="file-preview-container" style="display: none;"></div>
                            </div>
                            <input type="hidden" id="remove_poster_image" name="remove_poster_image" value="0">
                            <?php if (!empty($field_errors['poster_image'])): ?><div class="invalid-feedback"><?php echo htmlspecialchars($field_errors['poster_image']); ?></div><?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label for="poster_image_2">Secondary poster</label>
                            <?php if (!empty($programme['poster_image_2'])): ?>
                                <div class="current-upload" style="margin-bottom: 10px;">
                                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                                        <img src="../<?php echo htmlspecialchars($programme['poster_image_2']); ?>" alt="Current secondary poster" style="max-width: 200px; height: auto; border-radius: 8px;">
                                        <button type="button" class="btn btn-danger btn-sm" onclick="removeImage('poster_image_2')"><i class="fas fa-trash"></i> Remove Image</button>
                                    </div>
                                    <input type="hidden" name="current_poster_image_2" value="<?php echo htmlspecialchars($programme['poster_image_2']); ?>">
                                </div>
                            <?php endif; ?>
                            <div class="file-upload-container">
                                <div class="file-upload<?php echo isset($field_errors['poster_image_2']) ? ' is-invalid' : ''; ?>" id="poster_image_2_upload">
                                    <input type="file" id="poster_image_2" name="poster_image_2" accept="image/*" onchange="handleFileUpload(this)">
                                    <label for="poster_image_2" class="file-upload-label">
                                        <i class="fas fa-cloud-upload-alt"></i>
                                        <div>
                                            <strong>Click to upload</strong> or drag and drop<br>
                                            <span style="font-size: 12px; opacity: 0.7;">PNG, JPG, GIF, WebP up to 5MB (optional)</span>
                                        </div>
                                    </label>
                                </div>
                                <div id="poster_image_2_preview" class="file-preview-container" style="display: none;"></div>
                            </div>
                            <input type="hidden" id="remove_poster_image_2" name="remove_poster_image_2" value="0">
                            <?php if (!empty($field_errors['poster_image_2'])): ?><div class="invalid-feedback"><?php echo htmlspecialchars($field_errors['poster_image_2']); ?></div><?php endif; ?>
                        </div>
                    </section>

                    <details class="form-extra">
                        <summary>Extra</summary>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="tax_inclusive">Is the 8% SST already inside the price?</label>
                            <select id="tax_inclusive" name="tax_inclusive" class="form-control" onchange="calculateTax()">
                                <option value="0" <?php echo ($programme['tax_inclusive'] == '0') ? 'selected' : ''; ?>>No</option>
                                <option value="1" <?php echo ($programme['tax_inclusive'] == '1') ? 'selected' : ''; ?>>Yes</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="tax_amount">SST Amount (RM) <small style="color: #64748b; font-weight: normal;">(8% auto-calculated)</small></label>
                            <input type="number" step="0.01" id="tax_amount" name="tax_amount" class="form-control" 
                                   value="<?php echo htmlspecialchars($programme['tax_amount'] ?? ''); ?>" 
                                   placeholder="Auto-calculated" readonly style="background-color: #f1f5f9; cursor: not-allowed;">
                            <small class="form-text text-muted">SST amount is automatically calculated at 8%</small>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="category">Category</label>
                            <input type="text" id="category" name="category" class="form-control" 
                                   value="<?php echo htmlspecialchars($programme['category'] ?? ''); ?>">
                            <small class="form-text text-muted">Enter the category for this programme</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="max_participants">Max Participants</label>
                            <input type="number" id="max_participants" name="max_participants" min="1" class="form-control" 
                                   value="<?php echo htmlspecialchars($programme['max_participants'] ?? 25); ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="certification">Certification</label>
                            <input type="text" id="certification" name="certification" class="form-control" 
                                   value="<?php echo htmlspecialchars($programme['certification'] ?? 'HRD Corp Certified'); ?>" 
                                   placeholder="e.g., HRD Corp Certified">
                        </div>
                        
                        <div class="form-group">
                            <label for="programme_icon">Programme Icon</label>
                            <select id="programme_icon" name="programme_icon" class="form-control">
                                <option value="">Select an icon</option>
                                <?php 
                                // Check if current icon is custom (not in predefined list)
                                $predefinedIcons = [
                                    'fas fa-graduation-cap', 'fas fa-crown', 'fas fa-star', 'fas fa-trophy', 'fas fa-medal', 'fas fa-user-tie', 'fas fa-users',
                                    'fas fa-chart-line', 'fas fa-chart-bar', 'fas fa-chart-pie', 'fas fa-calculator', 'fas fa-database', 'fas fa-brain',
                                    'fas fa-bullhorn', 'fas fa-comments', 'fas fa-phone', 'fas fa-envelope', 'fas fa-video', 'fas fa-microphone',
                                    'fas fa-tasks', 'fas fa-project-diagram', 'fas fa-calendar-check', 'fas fa-clock', 'fas fa-check-circle', 'fas fa-list-check',
                                    'fas fa-user-plus', 'fas fa-user-edit', 'fas fa-handshake', 'fas fa-briefcase', 'fas fa-id-card', 'fas fa-user-graduate',
                                    'fas fa-laptop-code', 'fas fa-code', 'fas fa-cogs', 'fas fa-tools', 'fas fa-lightbulb', 'fas fa-rocket',
                                    'fas fa-dollar-sign', 'fas fa-chart-line', 'fas fa-building', 'fas fa-coins', 'fas fa-piggy-bank', 'fas fa-balance-scale'
                                ];
                                $currentIcon = $programme['programme_icon'] ?? '';
                                $isCustomIcon = $currentIcon && !in_array($currentIcon, $predefinedIcons);
                                ?>
                                <?php if ($isCustomIcon): ?>
                                    <option value="<?php echo htmlspecialchars($currentIcon); ?>" selected><?php echo htmlspecialchars($currentIcon); ?> (Custom)</option>
                                <?php endif; ?>
                                
                                <!-- Leadership & Management -->
                                <optgroup label="🎯 Leadership & Management">
                                    <option value="fas fa-graduation-cap" <?php echo ($programme['programme_icon'] ?? 'fas fa-graduation-cap') === 'fas fa-graduation-cap' ? 'selected' : ''; ?>>🎓 Graduation Cap</option>
                                    <option value="fas fa-crown" <?php echo ($programme['programme_icon'] ?? '') === 'fas fa-crown' ? 'selected' : ''; ?>>👑 Crown</option>
                                    <option value="fas fa-star" <?php echo ($programme['programme_icon'] ?? '') === 'fas fa-star' ? 'selected' : ''; ?>>⭐ Star</option>
                                    <option value="fas fa-trophy" <?php echo ($programme['programme_icon'] ?? '') === 'fas fa-trophy' ? 'selected' : ''; ?>>🏆 Trophy</option>
                                    <option value="fas fa-medal" <?php echo ($programme['programme_icon'] ?? '') === 'fas fa-medal' ? 'selected' : ''; ?>>🥇 Medal</option>
                                    <option value="fas fa-user-tie" <?php echo ($programme['programme_icon'] ?? '') === 'fas fa-user-tie' ? 'selected' : ''; ?>>👔 Business Person</option>
                                    <option value="fas fa-users" <?php echo ($programme['programme_icon'] ?? '') === 'fas fa-users' ? 'selected' : ''; ?>>👥 Team</option>
                                </optgroup>
                                
                                <!-- Analytics & Data -->
                                <optgroup label="📊 Analytics & Data">
                                    <option value="fas fa-chart-line" <?php echo ($programme['programme_icon'] ?? '') === 'fas fa-chart-line' ? 'selected' : ''; ?>>📈 Line Chart</option>
                                    <option value="fas fa-chart-bar" <?php echo ($programme['programme_icon'] ?? '') === 'fas fa-chart-bar' ? 'selected' : ''; ?>>📊 Bar Chart</option>
                                    <option value="fas fa-chart-pie" <?php echo ($programme['programme_icon'] ?? '') === 'fas fa-chart-pie' ? 'selected' : ''; ?>>🥧 Pie Chart</option>
                                    <option value="fas fa-calculator" <?php echo ($programme['programme_icon'] ?? '') === 'fas fa-calculator' ? 'selected' : ''; ?>>🧮 Calculator</option>
                                    <option value="fas fa-database" <?php echo ($programme['programme_icon'] ?? '') === 'fas fa-database' ? 'selected' : ''; ?>>🗄️ Database</option>
                                    <option value="fas fa-brain" <?php echo ($programme['programme_icon'] ?? '') === 'fas fa-brain' ? 'selected' : ''; ?>>🧠 Brain</option>
                                </optgroup>
                                
                                <!-- Communication & Marketing -->
                                <optgroup label="💬 Communication & Marketing">
                                    <option value="fas fa-bullhorn" <?php echo ($programme['programme_icon'] ?? '') === 'fas fa-bullhorn' ? 'selected' : ''; ?>>📢 Bullhorn</option>
                                    <option value="fas fa-comments" <?php echo ($programme['programme_icon'] ?? '') === 'fas fa-comments' ? 'selected' : ''; ?>>💬 Comments</option>
                                    <option value="fas fa-phone" <?php echo ($programme['programme_icon'] ?? '') === 'fas fa-phone' ? 'selected' : ''; ?>>📞 Phone</option>
                                    <option value="fas fa-envelope" <?php echo ($programme['programme_icon'] ?? '') === 'fas fa-envelope' ? 'selected' : ''; ?>>✉️ Envelope</option>
                                    <option value="fas fa-video" <?php echo ($programme['programme_icon'] ?? '') === 'fas fa-video' ? 'selected' : ''; ?>>📹 Video</option>
                                    <option value="fas fa-microphone" <?php echo ($programme['programme_icon'] ?? '') === 'fas fa-microphone' ? 'selected' : ''; ?>>🎤 Microphone</option>
                                </optgroup>
                                
                                <!-- Project & Task Management -->
                                <optgroup label="✅ Project & Task Management">
                                    <option value="fas fa-tasks" <?php echo ($programme['programme_icon'] ?? '') === 'fas fa-tasks' ? 'selected' : ''; ?>>✅ Tasks</option>
                                    <option value="fas fa-project-diagram" <?php echo ($programme['programme_icon'] ?? '') === 'fas fa-project-diagram' ? 'selected' : ''; ?>>📋 Project Diagram</option>
                                    <option value="fas fa-calendar-check" <?php echo ($programme['programme_icon'] ?? '') === 'fas fa-calendar-check' ? 'selected' : ''; ?>>📅 Calendar Check</option>
                                    <option value="fas fa-clock" <?php echo ($programme['programme_icon'] ?? '') === 'fas fa-clock' ? 'selected' : ''; ?>>⏰ Clock</option>
                                    <option value="fas fa-check-circle" <?php echo ($programme['programme_icon'] ?? '') === 'fas fa-check-circle' ? 'selected' : ''; ?>>✅ Check Circle</option>
                                    <option value="fas fa-list-check" <?php echo ($programme['programme_icon'] ?? '') === 'fas fa-list-check' ? 'selected' : ''; ?>>📝 Checklist</option>
                                </optgroup>
                                
                                <!-- HR & Recruitment -->
                                <optgroup label="👥 HR & Recruitment">
                                    <option value="fas fa-user-plus" <?php echo ($programme['programme_icon'] ?? '') === 'fas fa-user-plus' ? 'selected' : ''; ?>>➕ Add User</option>
                                    <option value="fas fa-user-edit" <?php echo ($programme['programme_icon'] ?? '') === 'fas fa-user-edit' ? 'selected' : ''; ?>>✏️ Edit User</option>
                                    <option value="fas fa-handshake" <?php echo ($programme['programme_icon'] ?? '') === 'fas fa-handshake' ? 'selected' : ''; ?>>🤝 Handshake</option>
                                    <option value="fas fa-briefcase" <?php echo ($programme['programme_icon'] ?? '') === 'fas fa-briefcase' ? 'selected' : ''; ?>>💼 Briefcase</option>
                                    <option value="fas fa-id-card" <?php echo ($programme['programme_icon'] ?? '') === 'fas fa-id-card' ? 'selected' : ''; ?>>🆔 ID Card</option>
                                    <option value="fas fa-user-graduate" <?php echo ($programme['programme_icon'] ?? '') === 'fas fa-user-graduate' ? 'selected' : ''; ?>>🎓 Graduate</option>
                                </optgroup>
                                
                                <!-- Technology & Skills -->
                                <optgroup label="💻 Technology & Skills">
                                    <option value="fas fa-laptop-code" <?php echo ($programme['programme_icon'] ?? '') === 'fas fa-laptop-code' ? 'selected' : ''; ?>>💻 Laptop Code</option>
                                    <option value="fas fa-code" <?php echo ($programme['programme_icon'] ?? '') === 'fas fa-code' ? 'selected' : ''; ?>>💻 Code</option>
                                    <option value="fas fa-cogs" <?php echo ($programme['programme_icon'] ?? '') === 'fas fa-cogs' ? 'selected' : ''; ?>>⚙️ Cogs</option>
                                    <option value="fas fa-tools" <?php echo ($programme['programme_icon'] ?? '') === 'fas fa-tools' ? 'selected' : ''; ?>>🔧 Tools</option>
                                    <option value="fas fa-lightbulb" <?php echo ($programme['programme_icon'] ?? '') === 'fas fa-lightbulb' ? 'selected' : ''; ?>>💡 Lightbulb</option>
                                    <option value="fas fa-rocket" <?php echo ($programme['programme_icon'] ?? '') === 'fas fa-rocket' ? 'selected' : ''; ?>>🚀 Rocket</option>
                                </optgroup>
                                
                                <!-- Finance & Business -->
                                <optgroup label="💰 Finance & Business">
                                    <option value="fas fa-dollar-sign" <?php echo ($programme['programme_icon'] ?? '') === 'fas fa-dollar-sign' ? 'selected' : ''; ?>>💰 Dollar Sign</option>
                                    <option value="fas fa-chart-line" <?php echo ($programme['programme_icon'] ?? '') === 'fas fa-chart-line' ? 'selected' : ''; ?>>📈 Growth Chart</option>
                                    <option value="fas fa-building" <?php echo ($programme['programme_icon'] ?? '') === 'fas fa-building' ? 'selected' : ''; ?>>🏢 Building</option>
                                    <option value="fas fa-coins" <?php echo ($programme['programme_icon'] ?? '') === 'fas fa-coins' ? 'selected' : ''; ?>>🪙 Coins</option>
                                    <option value="fas fa-piggy-bank" <?php echo ($programme['programme_icon'] ?? '') === 'fas fa-piggy-bank' ? 'selected' : ''; ?>>🐷 Piggy Bank</option>
                                    <option value="fas fa-balance-scale" <?php echo ($programme['programme_icon'] ?? '') === 'fas fa-balance-scale' ? 'selected' : ''; ?>>⚖️ Balance Scale</option>
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
                                    <i id="preview-icon" class="<?php echo htmlspecialchars($programme['programme_icon'] ?? 'fas fa-graduation-cap'); ?>"></i>
                                </div>
                                <small style="color: #64748b;">Icon Preview</small>
                        </div>
                            
                            <small class="form-text text-muted">Select an icon that best represents your programme. The icon will appear in programme cards and poster overlays.</small>
                    </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="programme_badge">Programme Badge</label>
                        <input type="text" id="programme_badge" name="programme_badge" class="form-control" 
                               value="<?php echo htmlspecialchars($programme['programme_badge'] ?? ''); ?>" 
                               placeholder="e.g., Popular, Advanced, Essential">
                    </div>
                    
                    <div class="form-group">
                        <label for="modules">Key Modules</label>
                        <textarea id="modules" name="modules" class="form-control" rows="4" 
                                  placeholder="Enter each key module on a new line:&#10;Strategic Planning&#10;Change Management&#10;Team Leadership&#10;Performance Management"><?php 
                                  // Convert comma-separated modules back to newline format for editing
                                  $modules_display = $programme['modules'] ?? '';
                                  if (!empty($modules_display)) {
                                      $modules_display = str_replace(', ', "\n", $modules_display);
                                  }
                                  echo htmlspecialchars($modules_display); 
                                  ?></textarea>
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
                                           <?php echo !empty($programme['requires_facility']) ? 'checked' : ''; ?>
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
                                        <option value="conference_room" <?php echo ($programme['facility_type'] ?? '') === 'conference_room' ? 'selected' : ''; ?>>Conference Room</option>
                                        <option value="training_room" <?php echo ($programme['facility_type'] ?? '') === 'training_room' ? 'selected' : ''; ?>>Training Room</option>
                                        <option value="breakout_area" <?php echo ($programme['facility_type'] ?? '') === 'breakout_area' ? 'selected' : ''; ?>>Breakout Area</option>
                                    </select>
                                    <?php if (!empty($field_errors['facility_type'])): ?><div class="invalid-feedback"><?php echo htmlspecialchars($field_errors['facility_type']); ?></div><?php endif; ?>
                                </div>
                                
                                <div class="form-group">
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="auto_create_facility_booking" value="1" 
                                               <?php echo !isset($programme['auto_create_facility_booking']) || $programme['auto_create_facility_booking'] ? 'checked' : ''; ?>>
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
                            <?php foreach ($question_configs as $index => $question): ?>
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
                                   <?php echo $programme['is_active'] ? 'checked' : ''; ?>>
                            <span class="checkmark"></span>
                            <div class="checkbox-text">
                                <div class="checkbox-main-text">Active programme</div>
                                <div class="checkbox-description">Active programmes will be visible on the website</div>
                            </div>
                        </label>
                    </div>
                    
                    <div class="form-group">
                        <label for="registration_status">Registration Status</label>
                        <select name="registration_status" id="registration_status" class="form-control">
                            <option value="open" <?php echo (isset($programme['registration_status']) && $programme['registration_status'] === 'open') ? 'selected' : ''; ?>>Open - Accepting registrations</option>
                            <option value="closed" <?php echo (isset($programme['registration_status']) && $programme['registration_status'] === 'closed') ? 'selected' : ''; ?>>Closed - No more registrations</option>
                            <option value="full" <?php echo (isset($programme['registration_status']) && $programme['registration_status'] === 'full') ? 'selected' : ''; ?>>Full - Maximum capacity reached</option>
                        </select>
                        <div class="form-help">Control whether users can register for this programme</div>
                    </div>
                    
                </details>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i>
                            Update Programme
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
    
    <!-- Enhanced Date/Time and Icon Selection JavaScript -->
    <script>
function removeFilePreview(inputId) {
    const previewContainer = document.getElementById(inputId + '_preview');
    const uploadContainer = document.getElementById(inputId + '_upload');
    const fileInput = document.getElementById(inputId);
    if (previewContainer && uploadContainer && fileInput) {
        fileInput.value = '';
        previewContainer.style.display = 'none';
        previewContainer.innerHTML = '';
        uploadContainer.style.display = 'block';
    }
}

function handleFileUpload(input) {
    const file = input.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = function(e) {
        const previewContainer = document.getElementById(input.id + '_preview');
        const uploadContainer = document.getElementById(input.id + '_upload');
        if (!previewContainer || !uploadContainer) return;
        uploadContainer.style.display = 'none';
        previewContainer.style.display = 'block';
        previewContainer.innerHTML = '<div class="file-preview"><div class="preview-image"><img src="' + e.target.result + '" alt="Preview" style="max-width: 200px; max-height: 150px; border-radius: 8px;"></div><div class="preview-info"><div class="file-name">' + file.name + '</div></div><button type="button" onclick="removeFilePreview(\'' + input.id + '\')" class="remove-file" aria-label="Remove"><i class="fas fa-times"></i></button></div>';
    };
    reader.readAsDataURL(file);
}

        // Function to remove image
        function removeImage(imageType) {
            xpmConfirm({
                title: 'Remove this image?',
                message: 'The image will be removed when you save the form.',
                confirmText: 'Remove',
                icon: 'fas fa-trash'
            }).then(function (ok) {
                if (!ok) return;
                // Set the remove flag
                document.getElementById('remove_' + imageType).value = '1';
                
                // Hide the image and remove button
                const imageContainer = document.querySelector(`input[name="${imageType}"]`).closest('.form-group');
                const imageDisplay = imageContainer.querySelector('.current-upload');
                if (imageDisplay) {
                    imageDisplay.style.display = 'none';
                }
                
                // Clear the file input
                document.getElementById(imageType).value = '';
                
                // Show a message
                const message = document.createElement('div');
                message.className = 'alert alert-info';
                message.innerHTML = '<i class="fas fa-info-circle"></i> Image will be removed when you save the form.';
                imageContainer.appendChild(message);
                
                // Remove the message after 3 seconds
                setTimeout(() => {
                    if (message.parentElement) {
                        message.remove();
                    }
                }, 3000);
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Icon selection functionality with proper null checks
            const iconSelect = document.getElementById('programme_icon');
            const customIconInput = document.getElementById('custom-icon-input');
            const customIconClass = document.getElementById('custom_icon_class');
            const previewIcon = document.getElementById('preview-icon');
            
            // Date/Time functionality
            const scheduleType = document.getElementById('schedule_type');
            const startDate = document.getElementById('start_date');
            const endDate = document.getElementById('end_date');
            const startTime = document.getElementById('start_time');
            const endTime = document.getElementById('end_time');
            const endDateGroup = document.getElementById('end_date_group');
            const schedulePreview = document.getElementById('schedule_preview');
            const previewText = document.getElementById('preview_text');
            
            // Icon functionality - only proceed if all required elements exist
            if (iconSelect && customIconInput && customIconClass && previewIcon) {
                let customIconListener = null; // Track custom icon listener to prevent duplicates
                
                // Update icon preview safely
                function updateIconPreview(iconClass) {
                    if (previewIcon && iconClass) {
                        previewIcon.className = iconClass;
                    } else if (previewIcon) {
                        previewIcon.className = 'fas fa-graduation-cap';
                    }
            }
            
            // Handle dropdown change
            iconSelect.addEventListener('change', function() {
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
                        if (iconSelect.value === 'custom' && customIconClass && customIconClass.value.trim()) {
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
            
            // Initialize preview with current icon
            if (iconSelect.value) {
                    updateIconPreview(iconSelect.value);
            }
            
            // Check if current icon is custom and initialize accordingly
            function initializeCustomIcon() {
                    if (!iconSelect || !customIconInput || !customIconClass || !previewIcon) {
                        return;
                    }
                    
                const currentIcon = iconSelect.value;
                    const currentOption = iconSelect.options[iconSelect.selectedIndex];
                
                    // Check if the current icon is custom (contains "(Custom)" in the option text)
                    const isCustomIcon = currentOption && currentOption.text.includes('(Custom)');
                
                if (isCustomIcon && currentIcon !== 'custom') {
                    // Set the select to 'custom' and show the custom input
                    iconSelect.value = 'custom';
                    customIconInput.style.display = 'block';
                    customIconClass.value = currentIcon;
                    
                    // Update preview
                        updateIconPreview(currentIcon);
                    }
            }
            
            // Initialize custom icon check
            initializeCustomIcon();
            } else {
                console.warn('Icon functionality: Some required elements not found');
            }
            
            // Date/Time functionality
            function updateSchedulePreview() {
                const startDateVal = startDate.value;
                const endDateVal = endDate.value;
                const startTimeVal = startTime.value;
                const endTimeVal = endTime.value;
                
                if (startDateVal && startTimeVal && endTimeVal) {
                    const startDateObj = new Date(startDateVal);
                    const endDateObj = endDateVal ? new Date(endDateVal) : null;
                    
                    let preview = '';
                    
                    // Format date
                    const startDateFormatted = startDateObj.toLocaleDateString('en-US', {
                        day: 'numeric',
                        month: 'long',
                        year: 'numeric'
                    });
                    
                    if (endDateObj && endDateVal !== startDateVal) {
                        const endDateFormatted = endDateObj.toLocaleDateString('en-US', {
                            day: 'numeric',
                            month: 'long',
                            year: 'numeric'
                        });
                        preview += `<strong>${startDateFormatted} - ${endDateFormatted}</strong><br>`;
                    } else {
                        preview += `<strong>${startDateFormatted}</strong><br>`;
                    }
                    
                    // Format time
                    const startTimeFormatted = formatTime(startTimeVal);
                    const endTimeFormatted = formatTime(endTimeVal);
                    preview += `<i class="fas fa-clock"></i> ${startTimeFormatted} - ${endTimeFormatted}`;
                    
                    previewText.innerHTML = preview;
                    schedulePreview.style.display = 'block';
                } else {
                    schedulePreview.style.display = 'none';
                }
            }
            
            function formatTime(timeString) {
                const [hours, minutes] = timeString.split(':');
                const hour = parseInt(hours);
                const ampm = hour >= 12 ? 'PM' : 'AM';
                const displayHour = hour % 12 || 12;
                return `${displayHour}:${minutes} ${ampm}`;
            }
            
            function handleScheduleTypeChange() {
                const type = scheduleType.value;
                
                if (type === 'single_day') {
                    endDateGroup.style.display = 'none';
                    endDate.value = startDate.value; // Auto-fill end date with start date
                } else {
                    endDateGroup.style.display = 'block';
                }
                
                updateSchedulePreview();
            }
            
            // Event listeners for date/time fields
            scheduleType.addEventListener('change', handleScheduleTypeChange);
            startDate.addEventListener('change', function() {
                if (scheduleType.value === 'single_day') {
                    endDate.value = this.value;
                }
                updateSchedulePreview();
            });
            endDate.addEventListener('change', updateSchedulePreview);
            startTime.addEventListener('change', updateSchedulePreview);
            endTime.addEventListener('change', updateSchedulePreview);
            
            // Initialize
            handleScheduleTypeChange();
            updateSchedulePreview();
        });

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

        function toggleFacilityOptions() {
            const requiresFacility = document.querySelector('input[name="requires_facility"]');
            const facilityOptions = document.getElementById('facility-options');
            
            if (requiresFacility && facilityOptions) {
                facilityOptions.style.display = requiresFacility.checked ? 'block' : 'none';
            }
        }

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