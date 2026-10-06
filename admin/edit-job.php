<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

// Include database connection
require_once '../includes/database.php';

$error = '';
$success = '';
$field_errors = [];
$job = null;

// Get job ID from URL
$job_id = $_GET['id'] ?? null;

if (!$job_id) {
    header('Location: jobs.php?error=Invalid job ID');
    exit;
}

$pdo = get_db_connection();

if (!$pdo) {
    header('Location: jobs.php?error=Database connection failed');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
        $company_name = trim($_POST['company_name'] ?? 'HR Training');
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
        $poster_image = $_POST['current_poster_image'] ?? null; // Keep existing image by default
        
        // Check if user wants to remove the image
        if (isset($_POST['remove_poster_image']) && $_POST['remove_poster_image'] == '1') {
            $poster_image = null;
            // Delete old image if it exists
            if (!empty($_POST['current_poster_image']) && file_exists('../' . $_POST['current_poster_image'])) {
                unlink('../' . $_POST['current_poster_image']);
            }
        } elseif (isset($_FILES['poster_image']) && $_FILES['poster_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../assets/images/logos/';
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
                $filename = 'logo_' . time() . '_' . uniqid() . '.' . $file_extension;
                $filepath = $upload_dir . $filename;
                
                if (move_uploaded_file($_FILES['poster_image']['tmp_name'], $filepath)) {
                    $poster_image = 'assets/images/logos/' . $filename;
                    
                    // Delete old image if exists
                    if (!empty($_POST['current_poster_image']) && file_exists('../' . $_POST['current_poster_image'])) {
                        unlink('../' . $_POST['current_poster_image']);
                    }
                } else {
                    $field_errors['poster_image'] = 'The poster could not be uploaded. Try again.';
                }
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
            
            // Update job in database
            $stmt = $pdo->prepare("
                UPDATE jobs 
                SET title = ?, location = ?, type = ?, description = ?, 
                    requirements = ?, company_name = ?, job_icon = ?, job_badge = ?, 
                    poster_image = ?, start_time = ?, end_time = ?, salary_range = ?, 
                    experience_level = ?, is_active = ?, updated_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([$title, $location, $type, $description, 
                          $requirements, $company_name, $job_icon, $job_badge, 
                          $poster_image, $final_start_time, $final_end_time, $salary_range, 
                          $experience_level, $is_active, $job_id]);
            
            $success = 'Job updated successfully!';
        } else {
            $error = 'Please check the highlighted fields.';
        }
        
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}

// Get job data
try {
    $stmt = $pdo->prepare("SELECT * FROM jobs WHERE id = ?");
    $stmt->execute([$job_id]);
    $job = $stmt->fetch();
    
    if (!$job) {
        header('Location: jobs.php?error=Job not found');
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($field_errors)) {
        foreach (['title', 'location', 'type', 'description', 'requirements', 'company_name', 'job_icon', 'job_badge', 'salary_range', 'experience_level', 'start_time', 'end_time'] as $field_key) {
            if (array_key_exists($field_key, $_POST)) {
                $job[$field_key] = $_POST[$field_key];
            }
        }
    }
} catch (PDOException $e) {
    header('Location: jobs.php?error=Database error');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Edit Job - XpressPoint Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="admin-styles.css?v=20261006-2">
</head>
<body data-motion="admin">
    <?php include_once 'includes/header.php'; renderAdminHeader('Edit Job', 'fas fa-edit', ['url' => 'jobs.php', 'label' => 'Jobs']); ?>

    <div class="admin-container">
        <!-- Page Header -->
        <div class="page-header">
            <h2 class="page-title">Edit Job Posting</h2>
            <div class="d-flex gap-2">
                <a href="jobs.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i>
                    Back to Jobs
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

        <!-- Job Form -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-briefcase"></i> Job Details</h3>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data" id="edit-job-form">
                    <p class="form-intro">Fields with a star are required. You can add images and extras after the first save.</p>
                    <section class="form-section">
                        <h4 class="form-section-title">What people see</h4>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="title">Job Title *</label>
                            <input type="text" id="title" name="title" class="form-control<?php echo isset($field_errors['title']) ? ' is-invalid' : ''; ?>" 
                                   value="<?php echo htmlspecialchars($job['title']); ?>" required>
                            <?php if (!empty($field_errors['title'])): ?><div class="invalid-feedback"><?php echo htmlspecialchars($field_errors['title']); ?></div><?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label for="location">Location *</label>
                            <input type="text" id="location" name="location" class="form-control<?php echo isset($field_errors['location']) ? ' is-invalid' : ''; ?>" 
                                   value="<?php echo htmlspecialchars($job['location']); ?>" required>
                            <small class="form-text text-muted">City or Remote.</small>
                            <?php if (!empty($field_errors['location'])): ?><div class="invalid-feedback"><?php echo htmlspecialchars($field_errors['location']); ?></div><?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="form-group">
                            <label for="type">Job Type *</label>
                            <select id="type" name="type" class="form-control<?php echo isset($field_errors['type']) ? ' is-invalid' : ''; ?>" required>
                                <option value="">Select job type</option>
                                <option value="Full-time" <?php echo $job['type'] === 'Full-time' ? 'selected' : ''; ?>>Full-time</option>
                                <option value="Part-time" <?php echo $job['type'] === 'Part-time' ? 'selected' : ''; ?>>Part-time</option>
                                <option value="Contract" <?php echo $job['type'] === 'Contract' ? 'selected' : ''; ?>>Contract</option>
                                <option value="Internship" <?php echo $job['type'] === 'Internship' ? 'selected' : ''; ?>>Internship</option>
                                <option value="Remote" <?php echo $job['type'] === 'Remote' ? 'selected' : ''; ?>>Remote</option>
                                <option value="On Site" <?php echo $job['type'] === 'On Site' ? 'selected' : ''; ?>>On Site</option>
                                <?php 
                                // Check if the job type is custom (not in predefined list)
                                $predefinedTypes = ['Full-time', 'Part-time', 'Contract', 'Internship', 'Remote', 'On Site'];
                                $isCustomType = !in_array($job['type'], $predefinedTypes);
                                ?>
                                <option value="custom" <?php echo $isCustomType ? 'selected' : ''; ?>>Custom Type</option>
                            </select>
                            
                            <div id="custom-type-input" style="display: <?php echo $isCustomType ? 'block' : 'none'; ?>; margin-top: 10px;">
                                <input type="text" id="custom_job_type" name="custom_job_type" class="form-control" 
                                       placeholder="e.g., Freelance, Temporary, Seasonal" value="<?php echo $isCustomType ? htmlspecialchars($job['type'] === 'custom' ? ($_POST['custom_job_type'] ?? '') : $job['type']) : ''; ?>">
                                <small class="form-text text-muted">Enter custom job type (e.g., Freelance, Temporary, Seasonal)</small>
                            </div>
                            <?php if (!empty($field_errors['type'])): ?><div class="invalid-feedback"><?php echo htmlspecialchars($field_errors['type']); ?></div><?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label for="description">Job Description *</label>
                            <textarea id="description" name="description" class="form-control<?php echo isset($field_errors['description']) ? ' is-invalid' : ''; ?>" rows="6" required
                                      placeholder="Describe the role, responsibilities, and what the job entails..."><?php echo htmlspecialchars($job['description']); ?></textarea>
                            <?php if (!empty($field_errors['description'])): ?><div class="invalid-feedback"><?php echo htmlspecialchars($field_errors['description']); ?></div><?php endif; ?>
                        </div>
                    </section>

                    <section class="form-section">
                        <h4 class="form-section-title">When and where</h4>
                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" id="hours_not_specified" name="hours_not_specified" 
                                       <?php echo (is_null($job['start_time']) && is_null($job['end_time'])) || isset($_POST['hours_not_specified']) ? 'checked' : ''; ?>>
                                <span class="checkmark"></span>
                                <div class="checkbox-text">
                                    <div class="checkbox-main-text">
                                        <i class="fas fa-question-circle text-warning"></i>
                                        Work hours not specified by company
                                    </div>
                                    <div class="checkbox-description">Check this if the company didn't provide specific work hours</div>
                                </div>
                            </label>
                        </div>
                        <div id="work-hours-section" class="form-grid">
                            <div class="form-group">
                                <label for="start_time"><i class="fas fa-clock"></i> Work Start Time</label>
                                <input type="time" id="start_time" name="start_time" class="form-control" 
                                       value="<?php echo htmlspecialchars($job['start_time'] ?? '09:00'); ?>">
                                <small class="form-text text-muted">Typical work start time</small>
                            </div>
                            <div class="form-group">
                                <label for="end_time"><i class="fas fa-clock"></i> Work End Time</label>
                                <input type="time" id="end_time" name="end_time" class="form-control" 
                                       value="<?php echo htmlspecialchars($job['end_time'] ?? '17:00'); ?>">
                                <small class="form-text text-muted">Typical work end time</small>
                            </div>
                        </div>
                    </section>

                    <section class="form-section">
                        <h4 class="form-section-title">Images</h4>
                        <div class="form-group">
                            <label for="poster_image">Company logo</label>
                            <?php if (!empty($job['poster_image'])): ?>
                            <div class="current-upload is-logo">
                                <img src="../<?php echo htmlspecialchars($job['poster_image']); ?>" alt="Current logo">
                                <button type="button" class="btn btn-danger btn-sm" onclick="removeImage('poster_image')">
                                    <i class="fas fa-trash"></i> Remove Image
                                </button>
                            </div>
                            <input type="hidden" name="current_poster_image" value="<?php echo htmlspecialchars($job['poster_image']); ?>">
                            <?php endif; ?>
                            <div class="file-upload-container">
                                <div class="file-upload logo-upload<?php echo isset($field_errors['poster_image']) ? ' is-invalid' : ''; ?>">
                                    <input type="file" id="poster_image" name="poster_image" accept="image/*" onchange="previewJobLogo(this)">
                                    <label for="poster_image" class="file-upload-label">
                                        <i class="fas fa-cloud-upload-alt"></i>
                                        <div>
                                            <strong>Click to upload</strong> or drag and drop<br>
                                            <span>Recommended: 200×200px · PNG, JPG, GIF, WebP</span>
                                        </div>
                                    </label>
                                </div>
                            </div>
                            <div id="poster_image_preview" class="logo-preview"></div>
                            <input type="hidden" id="remove_poster_image" name="remove_poster_image" value="0">
                            <small class="form-text text-muted">Shown with the job. A square PNG with a transparent background works best.</small>
                            <?php if (!empty($field_errors['poster_image'])): ?><div class="invalid-feedback"><?php echo htmlspecialchars($field_errors['poster_image']); ?></div><?php endif; ?>
                        </div>
                    </section>

                    <details class="form-extra">
                        <summary>Extra</summary>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="job_icon">Job Icon</label>
                            <select id="job_icon" name="job_icon" class="form-control">
                                <?php 
                                // Check if current icon is custom (not in predefined list)
                                $predefinedIcons = [
                                    'fas fa-briefcase', 'fas fa-user-tie', 'fas fa-chalkboard-teacher', 'fas fa-users', 'fas fa-handshake',
                                    'fas fa-pen-fancy', 'fas fa-palette', 'fas fa-camera', 'fas fa-video', 'fas fa-music',
                                    'fas fa-laptop-code', 'fas fa-code', 'fas fa-database', 'fas fa-mobile-alt', 'fas fa-network-wired',
                                    'fas fa-crown', 'fas fa-star', 'fas fa-trophy', 'fas fa-medal', 'fas fa-chess-king',
                                    'fas fa-tools', 'fas fa-wrench', 'fas fa-cog', 'fas fa-tasks', 'fas fa-clipboard-list'
                                ];
                                $currentIcon = $job['job_icon'] ?? '';
                                $isCustomIcon = $currentIcon && !in_array($currentIcon, $predefinedIcons);
                                ?>
                                <?php if ($isCustomIcon): ?>
                                    <option value="<?php echo htmlspecialchars($currentIcon); ?>" selected><?php echo htmlspecialchars($currentIcon); ?> (Custom)</option>
                                <?php endif; ?>
                                
                                <!-- Business & Professional -->
                                <optgroup label="💼 Business & Professional">
                                    <option value="fas fa-briefcase" <?php echo ($job['job_icon'] ?? 'fas fa-briefcase') === 'fas fa-briefcase' ? 'selected' : ''; ?>>💼 Briefcase</option>
                                    <option value="fas fa-user-tie" <?php echo ($job['job_icon'] ?? '') === 'fas fa-user-tie' ? 'selected' : ''; ?>>👔 Business Person</option>
                                    <option value="fas fa-chalkboard-teacher" <?php echo ($job['job_icon'] ?? '') === 'fas fa-chalkboard-teacher' ? 'selected' : ''; ?>>👨‍🏫 Teacher</option>
                                    <option value="fas fa-users" <?php echo ($job['job_icon'] ?? '') === 'fas fa-users' ? 'selected' : ''; ?>>👥 Team</option>
                                    <option value="fas fa-handshake" <?php echo ($job['job_icon'] ?? '') === 'fas fa-handshake' ? 'selected' : ''; ?>>🤝 Handshake</option>
                                </optgroup>
                                
                                <!-- Creative & Design -->
                                <optgroup label="🎨 Creative & Design">
                                    <option value="fas fa-pen-fancy" <?php echo ($job['job_icon'] ?? '') === 'fas fa-pen-fancy' ? 'selected' : ''; ?>>✒️ Pen Fancy</option>
                                    <option value="fas fa-palette" <?php echo ($job['job_icon'] ?? '') === 'fas fa-palette' ? 'selected' : ''; ?>>🎨 Palette</option>
                                    <option value="fas fa-camera" <?php echo ($job['job_icon'] ?? '') === 'fas fa-camera' ? 'selected' : ''; ?>>📷 Camera</option>
                                    <option value="fas fa-video" <?php echo ($job['job_icon'] ?? '') === 'fas fa-video' ? 'selected' : ''; ?>>📹 Video</option>
                                    <option value="fas fa-music" <?php echo ($job['job_icon'] ?? '') === 'fas fa-music' ? 'selected' : ''; ?>>🎵 Music</option>
                                </optgroup>
                                
                                <!-- Technology -->
                                <optgroup label="💻 Technology">
                                    <option value="fas fa-laptop-code" <?php echo ($job['job_icon'] ?? '') === 'fas fa-laptop-code' ? 'selected' : ''; ?>>💻 Laptop Code</option>
                                    <option value="fas fa-code" <?php echo ($job['job_icon'] ?? '') === 'fas fa-code' ? 'selected' : ''; ?>>💻 Code</option>
                                    <option value="fas fa-database" <?php echo ($job['job_icon'] ?? '') === 'fas fa-database' ? 'selected' : ''; ?>>🗄️ Database</option>
                                    <option value="fas fa-mobile-alt" <?php echo ($job['job_icon'] ?? '') === 'fas fa-mobile-alt' ? 'selected' : ''; ?>>📱 Mobile</option>
                                    <option value="fas fa-network-wired" <?php echo ($job['job_icon'] ?? '') === 'fas fa-network-wired' ? 'selected' : ''; ?>>🌐 Network</option>
                                </optgroup>
                                
                                <!-- Management & Leadership -->
                                <optgroup label="👑 Management & Leadership">
                                    <option value="fas fa-crown" <?php echo ($job['job_icon'] ?? '') === 'fas fa-crown' ? 'selected' : ''; ?>>👑 Crown</option>
                                    <option value="fas fa-star" <?php echo ($job['job_icon'] ?? '') === 'fas fa-star' ? 'selected' : ''; ?>>⭐ Star</option>
                                    <option value="fas fa-trophy" <?php echo ($job['job_icon'] ?? '') === 'fas fa-trophy' ? 'selected' : ''; ?>>🏆 Trophy</option>
                                    <option value="fas fa-medal" <?php echo ($job['job_icon'] ?? '') === 'fas fa-medal' ? 'selected' : ''; ?>>🥇 Medal</option>
                                    <option value="fas fa-chess-king" <?php echo ($job['job_icon'] ?? '') === 'fas fa-chess-king' ? 'selected' : ''; ?>>♔ Chess King</option>
                                </optgroup>
                                
                                <!-- Support & Service -->
                                <optgroup label="🛠️ Support & Service">
                                    <option value="fas fa-tools" <?php echo ($job['job_icon'] ?? '') === 'fas fa-tools' ? 'selected' : ''; ?>>🔧 Tools</option>
                                    <option value="fas fa-wrench" <?php echo ($job['job_icon'] ?? '') === 'fas fa-wrench' ? 'selected' : ''; ?>>🔧 Wrench</option>
                                    <option value="fas fa-cog" <?php echo ($job['job_icon'] ?? '') === 'fas fa-cog' ? 'selected' : ''; ?>>⚙️ Cog</option>
                                    <option value="fas fa-tasks" <?php echo ($job['job_icon'] ?? '') === 'fas fa-tasks' ? 'selected' : ''; ?>>✅ Tasks</option>
                                    <option value="fas fa-clipboard-list" <?php echo ($job['job_icon'] ?? '') === 'fas fa-clipboard-list' ? 'selected' : ''; ?>>📋 Clipboard</option>
                                </optgroup>
                                
                                <!-- Custom -->
                                <optgroup label="🔧 Custom">
                                    <option value="custom" <?php echo ($job['job_icon'] ?? '') === 'custom' ? 'selected' : ''; ?>>🔧 Custom Icon</option>
                                </optgroup>
                            </select>
                            
                            <div id="custom-icon-input" style="display: none; margin-top: 10px;">
                                <input type="text" id="custom_icon_class" name="custom_icon_class" class="form-control" 
                                       placeholder="e.g., fas fa-rocket" value="<?php echo htmlspecialchars($_POST['custom_icon_class'] ?? ''); ?>">
                                <small class="form-text text-muted">Enter Font Awesome icon class (e.g., fas fa-rocket)</small>
                            </div>
                            
                            <!-- Icon Preview -->
                            <div id="icon-preview" style="margin-top: 10px; padding: 15px; background: #f8f9fa; border-radius: 8px; text-align: center;">
                                <div style="font-size: 2rem; color: #111111; margin-bottom: 10px;">
                                    <i id="preview-icon" class="<?php echo htmlspecialchars($job['job_icon'] ?? 'fas fa-briefcase'); ?>"></i>
                                </div>
                                <small style="color: #64748b;">Icon Preview</small>
                            </div>
                            
                            <small class="form-text text-muted">Select an icon that best represents this job position.</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="job_badge">Job Badge</label>
                            <input type="text" id="job_badge" name="job_badge" class="form-control" 
                                   value="<?php echo htmlspecialchars($job['job_badge'] ?? ''); ?>" 
                                   placeholder="e.g., New, Senior, Urgent, Remote">
                        </div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                        <div class="form-group">
                            <label for="salary_range">Salary Range</label>
                            <input type="text" id="salary_range" name="salary_range" class="form-control" 
                                   value="<?php echo htmlspecialchars($job['salary_range'] ?? ''); ?>" 
                                   placeholder="e.g., RM 3,000 - RM 5,000">
                        </div>
                        
                        <div class="form-group">
                            <label for="experience_level">Experience Level</label>
                            <input type="text" id="experience_level" name="experience_level" class="form-control" 
                                   value="<?php echo htmlspecialchars($job['experience_level'] ?? ''); ?>" 
                                   placeholder="e.g., Entry Level, Mid Level, Senior Level, Executive Level">
                            <small class="form-text text-muted">Enter the experience level required for this position</small>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="company_name">Company Name</label>
                        <input type="text" id="company_name" name="company_name" class="form-control" 
                               value="<?php echo htmlspecialchars($job['company_name'] ?? ''); ?>" 
                               placeholder="Enter company name">
                    </div>

                    <div class="form-group">
                        <label for="requirements">Job Requirements</label>
                        <textarea id="requirements" name="requirements" class="form-control" rows="6"
                                  placeholder="List the qualifications, skills, and experience required..."><?php echo htmlspecialchars($job['requirements'] ?? ''); ?></textarea>
                        <small class="form-text text-muted">Optional. Visitors see this under the job description.</small>
                    </div>
                    
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="is_active" value="1"
                                   <?php echo $job['is_active'] ? 'checked' : ''; ?>>
                            <span class="checkmark"></span>
                            <div class="checkbox-text">
                                <div class="checkbox-main-text">Active job posting</div>
                                <div class="checkbox-description">Active jobs will be visible on the website</div>
                            </div>
                        </label>
                    </div>
                    </details>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i>
                            Update Job
                        </button>
                        <a href="jobs.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i>
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="../assets/js/leave-guard.js?v=20261006-3"></script>
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

        // Cache busting - force fresh JavaScript load
        console.log('Icon functionality loaded at:', new Date().toISOString());
        console.log('Version: 2.0 - Fixed icon detection');
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-save functionality
            const form = document.querySelector('form');
            const formId = 'edit-job-form';
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
            
            // Simple icon functionality
            const iconSelect = document.getElementById('job_icon');
            const customIconInput = document.getElementById('custom-icon-input');
            const customIconClass = document.getElementById('custom_icon_class');
            const previewIcon = document.getElementById('preview-icon');
            
            // Update icon preview
            function updatePreview(iconClass) {
                previewIcon.className = iconClass || 'fas fa-briefcase';
            }
            
            // Handle dropdown change
            iconSelect.addEventListener('change', function() {
                const value = this.value;
                
                if (value === 'custom') {
                    customIconInput.style.display = 'block';
                    customIconClass.focus();
                } else {
                    customIconInput.style.display = 'none';
                    updatePreview(value);
                }
            });
            
            // Handle custom icon input
            customIconClass.addEventListener('input', function() {
                if (iconSelect.value === 'custom') {
                    updatePreview(this.value);
                }
            });
            
            // Initialize with current icon
            updatePreview(iconSelect.value);

            
            // Custom job type functionality
            const jobTypeSelect = document.getElementById('type');
            const customTypeInput = document.getElementById('custom-type-input');
            
            function handleJobTypeChange() {
                if (jobTypeSelect.value === 'custom') {
                    customTypeInput.style.display = 'block';
                } else {
                    customTypeInput.style.display = 'none';
                }
            }
            
            // Initialize custom job type display based on current selection
            function initializeCustomJobType() {
                // The PHP already handles the initial state, so we just need to ensure
                // the display matches the current selection
                handleJobTypeChange();
            }
            
            jobTypeSelect.addEventListener('change', handleJobTypeChange);
            
            // Initialize job type
            handleJobTypeChange();
            initializeCustomJobType();
            
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
    <script src="../assets/js/motion.js?v=20261002-10"></script>
</body>
</html> 