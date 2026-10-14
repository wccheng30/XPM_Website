<?php
require_once '../includes/auth.php';
auth_require(['admin', 'manager', 'trainer']);
csrf_guard();
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
            if (isset($_FILES['poster_image']) && ($_FILES['poster_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $saved = auth_save_image($_FILES['poster_image'], 'assets/images/programmes', 'programme');
                if (!$saved['ok']) {
                    $field_errors['poster_image'] = $saved['error'];
                } else {
                    $poster_image = $saved['path'];
                }
            }
            
            // Handle banner image upload (auto-sets featured)
            $banner_image = null;
            if (isset($_FILES['banner_image']) && ($_FILES['banner_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $saved = auth_save_image($_FILES['banner_image'], 'assets/images/programmes', 'banner');
                if (!$saved['ok']) {
                    $field_errors['banner_image'] = $saved['error'];
                } else {
                    $banner_image = $saved['path'];
                }
            }
            
            // Auto-set is_featured based on banner_image presence
            $is_featured = !empty($banner_image) ? 1 : 0;
            
            // Handle second poster image upload
            $poster_image_2 = null;
            if (isset($_FILES['poster_image_2']) && ($_FILES['poster_image_2']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $saved = auth_save_image($_FILES['poster_image_2'], 'assets/images/programmes', 'programme_2');
                if (!$saved['ok']) {
                    $field_errors['poster_image_2'] = $saved['error'];
                } else {
                    $poster_image_2 = $saved['path'];
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
                        $success = 'Training programme added successfully. The facility booking could not be created.';
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
            $error = 'The request could not be completed.';
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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <?php include_once __DIR__ . '/includes/theme-boot.php'; ?>
    <link rel="stylesheet" href="<?php echo admin_asset('admin.min.css'); ?>">
    <link rel="stylesheet" href="<?php echo admin_asset('css/forms.css'); ?>">
</head>
<body data-motion="admin">
    <?php include_once 'includes/header.php'; renderAdminHeader('Add Training Programme', 'fas fa-plus', ['url' => 'programmes.php', 'label' => 'Programmes']); ?>
    
    <div class="admin-container">
        <?php include 'includes/navigation.php'; renderAdminNavigation('programmes'); ?>
        <!-- Page Header -->
            <div class="page-header">
            <h2 class="page-title">Add New Training Programme</h2>
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
                $mode = 'add';
                $values = $_POST;
                $defaults = [
                    'programme_type' => 'training',
                    'max_participants' => '25',
                    'certification' => 'HRD Corp Certified',
                    'programme_icon' => 'fas fa-graduation-cap',
                    'tax_inclusive' => '0',
                    'is_active' => '1',
                    'auto_create_facility_booking' => '1',
                ];
                foreach ($defaults as $default_key => $default_value) {
                    if (!array_key_exists($default_key, $values)) {
                        $values[$default_key] = $default_value;
                    }
                }
                $questions = $question_templates;
                include __DIR__ . '/includes/forms/programme-form.php';
                ?>

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
        // Clear the file input and the Choose file label
        fileInput.value = '';
        fileInput.dispatchEvent(new Event('change', { bubbles: true }));
        
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
                            <i class="fas fa-times" aria-hidden="true"></i>
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
            if (window.xpmSetIcon) {
                window.xpmSetIcon(previewIcon, iconClass || 'fas fa-graduation-cap');
            }
        }
        
        programmeIcon.addEventListener('change', function() {
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
        
        if (programmeIcon.value === 'custom') {
            customIconInput.hidden = false;
            updateIconPreview((customIconClass && customIconClass.value) || 'fas fa-graduation-cap');
        } else if (programmeIcon.value) {
            customIconInput.hidden = true;
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
        <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}" aria-hidden="true"></i>
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
        <script defer src="<?php echo admin_asset('../assets/js/leave-guard.js'); ?>"></script>
    <script defer src="<?php echo admin_asset('js/forms.js'); ?>"></script>
</body>
</html> 