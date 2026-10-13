<?php
require_once '../includes/auth.php';
auth_require(['admin', 'manager']);
csrf_guard();

// Include database connection
require_once '../includes/database.php';

$error = '';
$success = '';
$field_errors = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo = get_db_connection();
    
    if ($pdo) {
        try {
            // Validate and sanitize input
            $title = trim($_POST['title'] ?? '');
            $location = trim($_POST['location'] ?? '');
            $type = trim($_POST['type'] ?? '');
            // Handle custom job type
            if ($type === 'custom' && !empty($_POST['custom_job_type'])) {
                $type = trim($_POST['custom_job_type']);
            }
            $description = trim($_POST['description'] ?? '');
            $requirements = trim($_POST['requirements'] ?? '');
            $company_name = trim($_POST['company_name'] ?? '');
            // Handle custom icon selection
            $job_icon = trim($_POST['job_icon'] ?? 'fas fa-briefcase');
            if ($job_icon === 'custom' && !empty($_POST['custom_icon_class'])) {
                $job_icon = trim($_POST['custom_icon_class']);
            }
            $job_badge = trim($_POST['job_badge'] ?? 'New');
            $salary_range = trim($_POST['salary_range'] ?? '');
            $experience_level = trim($_POST['experience_level'] ?? '');
            $start_time = !empty($_POST['start_time']) ? $_POST['start_time'] : null;
            $end_time = !empty($_POST['end_time']) ? $_POST['end_time'] : null;
            
            // Handle poster image upload
            $poster_image = null;
            if (isset($_FILES['poster_image']) && ($_FILES['poster_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $saved = auth_save_image($_FILES['poster_image'], 'assets/images/logos', 'logo');
                if (!$saved['ok']) {
                    $field_errors['poster_image'] = $saved['error'];
                } else {
                    $poster_image = $saved['path'];
                }
            }
            
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            if (empty($title)) {
                $field_errors['title'] = 'Add a job title.';
            }
            
            if (empty($location)) {
                $field_errors['location'] = 'Add a location.';
            }
            
            if (empty($type) || $type === 'custom') {
                $field_errors['type'] = 'Choose a job type.';
            }
            
            if (empty($description)) {
                $field_errors['description'] = 'Add a description.';
            }
            
            if (empty($field_errors)) {
                // Handle work hours - set to NULL if not specified
                $final_start_time = isset($_POST['hours_not_specified']) ? null : $start_time;
                $final_end_time = isset($_POST['hours_not_specified']) ? null : $end_time;
                
                // Insert job into database
                $stmt = $pdo->prepare("
                    INSERT INTO jobs (title, location, type, description, requirements, company_name, job_icon, job_badge, poster_image, start_time, end_time, salary_range, experience_level, is_active, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                
                $stmt->execute([$title, $location, $type, $description, $requirements, $company_name, $job_icon, $job_badge, $poster_image, $final_start_time, $final_end_time, $salary_range, $experience_level, $is_active]);
                
                $success = 'Job posted successfully!';
                
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
    <title>Add New Job - XpressPoint Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <?php include_once __DIR__ . '/includes/theme-boot.php'; ?>
    <link rel="stylesheet" href="<?php echo admin_asset('admin.min.css'); ?>">
    <link rel="stylesheet" href="<?php echo admin_asset('css/forms.css'); ?>">
</head>
<body data-motion="admin">
    <?php include_once 'includes/header.php'; renderAdminHeader('Add New Job', 'fas fa-plus', ['url' => 'jobs.php', 'label' => 'Jobs']); ?>

    <div class="admin-container">
        <?php include 'includes/navigation.php'; renderAdminNavigation('jobs'); ?>
        <!-- Page Header -->
        <div class="page-header">
            <h2 class="page-title">Post New Career Opportunity</h2>
            <div class="d-flex gap-2">
                <a href="jobs.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left" aria-hidden="true"></i>
                    Back to Jobs
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

        <!-- Job Form -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-briefcase" aria-hidden="true"></i> Job Details</h3>
            </div>
            <div class="card-body">
                <?php
                $mode = 'add';
                $values = $_POST;
                if (!array_key_exists('start_time', $values)) { $values['start_time'] = '09:00'; }
                if (!array_key_exists('end_time', $values)) { $values['end_time'] = '17:00'; }
                if (!array_key_exists('job_icon', $values)) { $values['job_icon'] = 'fas fa-briefcase'; }
                $values['is_active'] = '1';
                include __DIR__ . '/includes/forms/job-form.php';
                ?>

            </div>
        </div>
    </div>
    
    <script defer src="<?php echo admin_asset('../assets/js/leave-guard.js'); ?>"></script>
    <!-- Enhanced Date/Time and Icon Selection JavaScript -->
    <script>
        function previewJobLogo(input) {
            const preview = document.getElementById('poster_image_preview');
            if (!preview || !input.files || !input.files[0]) return;
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.style.display = 'block';
                preview.innerHTML = '<img src="' + e.target.result + '" alt="Logo preview">';
            };
            reader.readAsDataURL(input.files[0]);
        }

        document.addEventListener('DOMContentLoaded', function() {
            console.log('Add Job Form: DOM loaded, initializing...');
            // Auto-save functionality
            const form = document.querySelector('form');
            const formId = 'add-job-form';
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
                        background: #111111;
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
                    <i class="fas fa-info-circle" style="margin-right: 8px;" aria-hidden="true"></i>
                    Form data restored from previous session
                    <button type="button" aria-label="Dismiss" onclick="this.parentElement.remove()" style="background: none; border: none; color: white; margin-left: 12px; cursor: pointer;">
                        <i class="fas fa-times" aria-hidden="true"></i>
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
            
            // Icon selection functionality
            const iconSelect = document.getElementById('job_icon');
            const customIconInput = document.getElementById('custom-icon-input');
            const customIconClass = document.getElementById('custom_icon_class');
            const previewIcon = document.getElementById('preview-icon');
            
            console.log('Icon elements found:', {
                iconSelect: !!iconSelect,
                customIconInput: !!customIconInput,
                customIconClass: !!customIconClass,
                previewIcon: !!previewIcon
            });
            
            // Enhanced form interactions
            
            // Handle custom icon input changes (separate from dropdown change)
            customIconClass.addEventListener('input', function() {
                const previewIconElement = previewIcon.querySelector('i');
                previewIconElement.className = this.value || 'fas fa-briefcase';
            });
            
            // Handle dropdown change
            iconSelect.addEventListener('change', function() {
                const selectedValue = this.value;
                const previewIconElement = previewIcon.querySelector('i');
                
                console.log('Icon dropdown changed to:', selectedValue);
                
                if (selectedValue === 'custom') {
                    // Show custom input
                    customIconInput.style.display = 'block';
                    customIconClass.focus();
                    console.log('Custom icon input shown');
                    
                    // Set preview to current custom value if it exists
                    if (customIconClass.value) {
                        previewIconElement.className = customIconClass.value;
                    }
                } else {
                    // Hide custom input
                    customIconInput.style.display = 'none';
                    console.log('Custom icon input hidden');
                    
                    // Update preview with selected icon
                    if (selectedValue) {
                        previewIconElement.className = selectedValue;
                    } else {
                        previewIconElement.className = 'fas fa-briefcase';
                    }
                }
            });
            
            // Handle form submission for custom icons
            form.addEventListener('submit', function(e) {
                if (iconSelect.value === 'custom' && customIconClass.value.trim()) {
                    // Create a hidden input with the custom icon value
                    const hiddenInput = document.createElement('input');
                    hiddenInput.type = 'hidden';
                    hiddenInput.name = 'job_icon';
                    hiddenInput.value = customIconClass.value.trim();
                    this.appendChild(hiddenInput);
                }
            });
            
            // Initialize icon preview on page load
            const initialIconValue = iconSelect.value || 'fas fa-briefcase';
            const previewIconElement = previewIcon.querySelector('i');
            previewIconElement.className = initialIconValue;
            
            // Custom job type functionality
            const jobTypeSelect = document.getElementById('type');
            const customTypeInput = document.getElementById('custom-type-input');
            
            function handleJobTypeChange() {
                console.log('Job type changed to:', jobTypeSelect.value);
                if (jobTypeSelect.value === 'custom') {
                    customTypeInput.style.display = 'block';
                    console.log('Custom job type input shown');
                    // Focus on the custom input field
                    const customJobTypeInput = document.getElementById('custom_job_type');
                    if (customJobTypeInput) {
                        customJobTypeInput.focus();
                    }
                } else {
                    customTypeInput.style.display = 'none';
                    console.log('Custom job type input hidden');
                }
            }
            
            jobTypeSelect.addEventListener('change', handleJobTypeChange);
            
            // Initialize job type on page load
            handleJobTypeChange();
            
            // Initialize icon functionality on page load
            const initialIconSelectValue = iconSelect.value;
            if (initialIconSelectValue === 'custom') {
                customIconInput.style.display = 'block';
                if (customIconClass.value) {
                    previewIconElement.className = customIconClass.value;
                }
            } else {
                customIconInput.style.display = 'none';
                if (initialIconSelectValue) {
                    previewIconElement.className = initialIconSelectValue;
                }
            }
            
            // Handle work hours not specified checkbox
            const hoursNotSpecifiedCheckbox = document.getElementById('hours_not_specified');
            const workHoursSection = document.getElementById('work-hours-section');
            
            function toggleWorkHoursSection() {
                if (hoursNotSpecifiedCheckbox.checked) {
                    workHoursSection.style.display = 'none';
                    // Clear the time inputs when hidden
                    document.getElementById('start_time').value = '';
                    document.getElementById('end_time').value = '';
                } else {
                    workHoursSection.style.display = 'grid';
                    // Set default values when shown
                    if (!document.getElementById('start_time').value) {
                        document.getElementById('start_time').value = '09:00';
                    }
                    if (!document.getElementById('end_time').value) {
                        document.getElementById('end_time').value = '17:00';
                    }
                }
            }
            
            hoursNotSpecifiedCheckbox.addEventListener('change', toggleWorkHoursSection);
            
            // Initialize work hours section on page load
            toggleWorkHoursSection();
        });
    </script>
        <script defer src="<?php echo admin_asset('js/forms.js'); ?>"></script>
</body>
</html> 