<?php
require_once '../includes/auth.php';
auth_require(['admin', 'manager', 'trainer']);
csrf_guard();
if (auth_is_trainer()) {
    header('Location: facilities.php');
    exit;
}

// Include database connection
require_once '../includes/database.php';
require_once '../includes/facility-booking-service.php';
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

$ownerGuard = $pdo->prepare("SELECT id, owner_user_id FROM programmes WHERE id = ?");
$ownerGuard->execute([$programme_id]);
$ownerRow = $ownerGuard->fetch();
if (!$ownerRow) {
    header('Location: programmes.php?error=Programme not found');
    exit;
}
if (!auth_owns_programme($ownerRow)) {
    header('Location: programmes.php?error=' . urlencode('You can only change programmes you own.'));
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
        $programme_type = auth_normalize_programme_type($_POST['programme_type'] ?? 'training');
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
        if (!in_array($registration_status, ['open', 'closed', 'full'], true)) {
            $registration_status = 'closed';
        }
        if (!$is_active) {
            $registration_status = 'closed';
        }
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
            auth_remove_managed_image($_POST['current_poster_image'] ?? '');
        } elseif (isset($_FILES['poster_image']) && ($_FILES['poster_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $saved = auth_save_image($_FILES['poster_image'], 'assets/images/programmes', 'programme');
            if (!$saved['ok']) {
                $field_errors['poster_image'] = $saved['error'];
            } else {
                $poster_image = $saved['path'];
                auth_remove_managed_image($_POST['current_poster_image'] ?? '');
            }
        }
        
        // Handle second poster image upload
        $poster_image_2 = $_POST['current_poster_image_2'] ?? null; // Keep existing image by default
        
        // Check if user wants to remove the second image
        if (isset($_POST['remove_poster_image_2']) && $_POST['remove_poster_image_2'] == '1') {
            $poster_image_2 = null;
            // Delete old image if it exists
            auth_remove_managed_image($_POST['current_poster_image_2'] ?? '');
        } elseif (isset($_FILES['poster_image_2']) && ($_FILES['poster_image_2']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $saved = auth_save_image($_FILES['poster_image_2'], 'assets/images/programmes', 'programme_2');
            if (!$saved['ok']) {
                $field_errors['poster_image_2'] = $saved['error'];
            } else {
                $poster_image_2 = $saved['path'];
                auth_remove_managed_image($_POST['current_poster_image_2'] ?? '');
            }
        }
        
        // Handle banner image upload (auto-sets featured)
        $banner_image = $_POST['current_banner_image'] ?? null; // Keep existing image by default
        
        // Check if user wants to remove the banner image
        if (isset($_POST['remove_banner_image']) && $_POST['remove_banner_image'] == '1') {
            $banner_image = null;
            // Delete old image if it exists
            auth_remove_managed_image($_POST['current_banner_image'] ?? '');
        } elseif (isset($_FILES['banner_image']) && ($_FILES['banner_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $saved = auth_save_image($_FILES['banner_image'], 'assets/images/programmes', 'banner');
            if (!$saved['ok']) {
                $field_errors['banner_image'] = $saved['error'];
            } else {
                $banner_image = $saved['path'];
                auth_remove_managed_image($_POST['current_banner_image'] ?? '');
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

        if (empty($field_errors) && !empty($start_date)) {
            $existingDates = $pdo->prepare('SELECT start_date, end_date FROM programmes WHERE id = ?');
            $existingDates->execute([$programme_id]);
            $existing = $existingDates->fetch(PDO::FETCH_ASSOC) ?: [];
            $oldBounds = xpm_reservation_bounds($existing['start_date'] ?? '', $existing['end_date'] ?? '');
            $newBounds = xpm_reservation_bounds($start_date, $end_date ?: $start_date);
            $oldKey = (is_array($oldBounds) && empty($oldBounds['error'])) ? $oldBounds['start'] . '|' . $oldBounds['end'] : '';
            $newKey = (is_array($newBounds) && empty($newBounds['error'])) ? $newBounds['start'] . '|' . $newBounds['end'] : '';
            if ($newKey !== $oldKey) {
                $reserved = xpm_day_reservation_conflict($pdo, $start_date, $end_date ?: $start_date, (int) $programme_id);
                if ($reserved) {
                    $field_errors['start_date'] = $reserved;
                }
            }
        }
        
        if (empty($field_errors)) {
            // Update programme in database
            $stmt = $pdo->prepare("
                UPDATE programmes 
                SET title = ?, description = ?, duration = ?, price = ?, tax_inclusive = ?, tax_amount = ?, original_price = ?, category = ?, programme_type = ?,
                    max_participants = ?, certification = ?, programme_icon = ?, 
                    programme_badge = ?, location = ?, poster_image = ?, poster_image_2 = ?, banner_image = ?, modules = ?, schedule_type = ?, start_date = ?, 
                    end_date = ?, start_time = ?, end_time = ?, requires_facility = ?, facility_type = ?, auto_create_facility_booking = ?,
                    is_active = ?, registration_status = ?, is_featured = ?, owner_user_id = ?, updated_at = NOW()
                WHERE id = ?
            ");

            if (auth_is_trainer()) {
                $owner_user_id = (int) (auth_user()['id'] ?? 0);
            } else {
                $posted_owner = (int) ($_POST['owner_user_id'] ?? 0);
                $owner_user_id = $posted_owner > 0 ? $posted_owner : null;
            }
            
            $stmt->execute([$title, $description, $duration, $price, $tax_inclusive, $tax_amount, $original_price, $category, $programme_type,
                          $max_participants, $certification, $programme_icon, 
                          $programme_badge, $location, $poster_image, $poster_image_2, $banner_image, $modules, $schedule_type, $start_date, 
                          $end_date, $start_time, $end_time, $requires_facility, $facility_type, $auto_create_facility_booking,
                          $is_active, $registration_status, $is_featured, $owner_user_id, $programme_id]);
            saveProgrammeQuestionConfigs($pdo, (int)$programme_id, $_POST['question_configs'] ?? getDefaultProgrammeQuestions());
            
            $success = 'Training programme updated successfully!';
        } else {
            $error = 'Please check the highlighted fields.';
        }
        
    } catch (Throwable $e) {
        $error = 'The request could not be completed.';
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
    if (!auth_owns_programme($programme)) {
        header('Location: programmes.php?error=' . urlencode('You can only change programmes you own.'));
        exit;
    }
    $question_configs = getProgrammeQuestionConfigs($pdo, (int)$programme_id, false);
    if (!empty($field_errors) && $_SERVER['REQUEST_METHOD'] === 'POST') {
        foreach (['title', 'description', 'duration', 'location', 'category', 'programme_type', 'certification', 'programme_icon', 'programme_badge', 'modules', 'schedule_type', 'start_date', 'end_date', 'start_time', 'end_time', 'facility_type'] as $posted_key) {
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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <?php include_once __DIR__ . '/includes/theme-boot.php'; ?>
    <link rel="stylesheet" href="<?php echo admin_asset('admin.min.css'); ?>">
    <link rel="stylesheet" href="<?php echo admin_asset('css/forms.css'); ?>">
</head>
<body data-motion="admin">
    <?php include_once 'includes/header.php'; renderAdminHeader('Edit Training Programme', 'fas fa-edit', ['url' => 'programmes.php', 'label' => 'Programmes']); ?>

    <div class="admin-container">
        <?php include 'includes/navigation.php'; renderAdminNavigation('programmes'); ?>
        <!-- Page Header -->
        <div class="page-header">
            <h2 class="page-title">Edit Training Programme</h2>
            <div class="d-flex gap-2">
                <a href="programmes.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left" aria-hidden="true"></i>
                    Back to Programmes
                </a>
            </div>
        </div>

        <!-- Alerts -->
        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle" aria-hidden="true"></i>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle" aria-hidden="true"></i>
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <!-- Programme Form -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-graduation-cap" aria-hidden="true"></i> Programme Details</h3>
            </div>
            <div class="card-body">
                <?php
                $mode = 'edit';
                $record_id = $programme_id;
                $values = [
                    'title' => $programme['title'] ?? '',
                    'programme_type' => $programme['programme_type'] ?? 'training',
                    'owner_user_id' => $programme['owner_user_id'] ?? '',
                    'description' => $programme['description'] ?? '',
                    'location' => $programme['location'] ?? '',
                    'duration' => $programme['duration'] ?? '',
                    'programme_price' => $programme['price'] ?? '',
                    'programme_original_price' => $programme['original_price'] ?? '',
                    'schedule_type' => $programme['schedule_type'] ?? '',
                    'start_date' => $programme['start_date'] ?? '',
                    'end_date' => $programme['end_date'] ?? '',
                    'start_time' => $programme['start_time'] ?? '',
                    'end_time' => $programme['end_time'] ?? '',
                    'tax_inclusive' => $programme['tax_inclusive'] ?? '0',
                    'tax_amount' => $programme['tax_amount'] ?? '',
                    'category' => $programme['category'] ?? '',
                    'max_participants' => $programme['max_participants'] ?? '25',
                    'certification' => $programme['certification'] ?? 'HRD Corp Certified',
                    'programme_icon' => $programme['programme_icon'] ?? 'fas fa-graduation-cap',
                    'programme_badge' => $programme['programme_badge'] ?? '',
                    'modules' => $programme['modules'] ?? '',
                    'requires_facility' => !empty($programme['requires_facility']) ? '1' : '',
                    'facility_type' => $programme['facility_type'] ?? '',
                    'auto_create_facility_booking' => (!isset($programme['auto_create_facility_booking']) || $programme['auto_create_facility_booking']) ? '1' : '',
                    'is_active' => !empty($programme['is_active']) ? '1' : '',
                    'registration_status' => $programme['registration_status'] ?? 'open',
                    'banner_image' => $programme['banner_image'] ?? '',
                    'poster_image' => $programme['poster_image'] ?? '',
                    'poster_image_2' => $programme['poster_image_2'] ?? '',
                    'custom_icon_class' => $_POST['custom_icon_class'] ?? '',
                ];
                $questions = $question_configs;
                include __DIR__ . '/includes/forms/programme-form.php';
                ?>

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
        fileInput.dispatchEvent(new Event('change', { bubbles: true }));
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
        previewContainer.innerHTML = '<div class="file-preview"><div class="preview-image"><img class="media-thumb" src="' + e.target.result + '" alt="Preview"></div><div class="preview-info"><div class="file-name">' + file.name + '</div></div><button type="button" onclick="removeFilePreview(\'' + input.id + '\')" class="remove-file" aria-label="Remove"><i class="fas fa-times" aria-hidden="true"></i></button></div>';
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
                message.innerHTML = '<i class="fas fa-info-circle" aria-hidden="true"></i> Image will be removed when you save the form.';
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
                    if (window.xpmSetIcon) {
                        window.xpmSetIcon(previewIcon, iconClass || 'fas fa-graduation-cap');
                    }
                }
            
            // Handle dropdown change
            iconSelect.addEventListener('change', function() {
                const selectedValue = this.value;
                
                if (selectedValue === 'custom') {
                    // Show custom input
                    customIconInput.hidden = false;
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
                    customIconInput.hidden = true;
                        
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
                    customIconInput.hidden = false;
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
                if (!schedulePreview || !previewText || !startDate || !endDate || !startTime || !endTime) {
                    return;
                }
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
                    preview += `<i class="fas fa-clock" aria-hidden="true"></i> ${startTimeFormatted} - ${endTimeFormatted}`;
                    
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
                if (!scheduleType) {
                    return;
                }
                const type = scheduleType.value;
                
                if (type === 'single_day') {
                    if (endDateGroup) {
                        endDateGroup.style.display = 'none';
                    }
                    if (endDate && startDate) {
                        endDate.value = startDate.value;
                    }
                } else if (endDateGroup) {
                    endDateGroup.style.display = 'block';
                }
                
                updateSchedulePreview();
            }
            
            if (scheduleType) {
                scheduleType.addEventListener('change', handleScheduleTypeChange);
            }
            if (startDate) {
                startDate.addEventListener('change', function() {
                    if (scheduleType && scheduleType.value === 'single_day' && endDate) {
                        endDate.value = this.value;
                    }
                    updateSchedulePreview();
                });
            }
            if (endDate) {
                endDate.addEventListener('change', updateSchedulePreview);
            }
            if (startTime) {
                startTime.addEventListener('change', updateSchedulePreview);
            }
            if (endTime) {
                endTime.addEventListener('change', updateSchedulePreview);
            }
            
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
        <script defer src="<?php echo admin_asset('../assets/js/leave-guard.js'); ?>"></script>
    <script defer src="<?php echo admin_asset('js/forms.js'); ?>"></script>
</body>
</html> 