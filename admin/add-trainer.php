<?php
require_once '../includes/auth.php';
auth_require(['admin', 'manager']);

// Include database connection
require_once '../includes/database.php';

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pdo = get_db_connection();
    
    if ($pdo) {
        try {
            // Validate and sanitize input
            $name = trim($_POST['name'] ?? '');
            $title = trim($_POST['title'] ?? '');
            $experience_years = intval($_POST['experience_years'] ?? 0);
            $description = trim($_POST['description'] ?? '');
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            $display_order = intval($_POST['display_order'] ?? 0);
            
            // Handle specialties
            $specialties = [];
            if (!empty($_POST['specialties'])) {
                $specialties = array_filter(array_map('trim', explode(',', $_POST['specialties'])));
            }
            
            // Handle credentials
            $credentials = [];
            if (!empty($_POST['credentials'])) {
                $credentials = array_filter(array_map('trim', explode(',', $_POST['credentials'])));
            }
            
            // Validate required fields
            if (empty($name)) {
                throw new Exception('Trainer name is required.');
            }
            
            if (empty($title)) {
                throw new Exception('Trainer title is required.');
            }
            
            // Handle image upload
            $image_path = null;
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = '../assets/images/trainers/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $file_extension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                
                if (!in_array($file_extension, $allowed_extensions)) {
                    throw new Exception('Invalid file type. Only JPG, PNG, GIF, and WebP files are allowed.');
                }
                
                $filename = 'trainer_' . time() . '_' . uniqid() . '.' . $file_extension;
                $upload_path = $upload_dir . $filename;
                
                if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
                    $image_path = 'assets/images/trainers/' . $filename;
                } else {
                    throw new Exception('Failed to upload image.');
                }
            }
            
            // Insert trainer
            $stmt = $pdo->prepare("INSERT INTO trainers (name, title, experience_years, image_path, description, specialties, credentials, is_active, display_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $name,
                $title,
                $experience_years,
                $image_path,
                $description,
                json_encode($specialties),
                json_encode($credentials),
                $is_active,
                $display_order
            ]);
            
            $success = "Trainer added successfully!";
            
            // Clear form data
            $_POST = [];
            
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    } else {
        $error = "Database connection failed.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Trainer - XpressPoint Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="admin-styles.css?v=20261005-5">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body data-motion="admin">
    <?php include_once 'includes/header.php'; renderAdminHeader('Add Trainer', 'fas fa-plus', ['url' => 'trainers.php', 'label' => 'Trainers']); ?>
    <div class="admin-container">
        <!-- Navigation -->
        <?php include 'includes/navigation.php'; renderAdminNavigation('trainers'); ?>


        <!-- Main Content -->
        <main class="admin-main">
            <div class="content-section">
                <div class="section-header">
                    <h2>Trainer Information</h2>
                    <p>Add a new trainer to your team</p>
                </div>

                <!-- Messages -->
                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?php echo htmlspecialchars($success); ?>
                        <a href="trainers.php" class="btn btn-sm btn-primary" style="margin-left: 10px;">
                            View Trainers
                        </a>
                    </div>
                <?php endif; ?>

                <!-- Form Tips -->
                <div class="form-tips">
                    <h4><i class="fas fa-lightbulb"></i> Quick Tips</h4>
                    <ul>
                        <li>Use a professional headshot for the trainer image (300x300px recommended)</li>
                        <li>Separate specialties and credentials with commas</li>
                        <li>Featured trainers appear prominently on the website</li>
                        <li>Display order determines the sequence trainers appear (lower numbers first)</li>
                    </ul>
                </div>

                <!-- Form -->
                <form method="POST" enctype="multipart/form-data" class="form-container">
                    <div class="form-grid">
                        <!-- Basic Information -->
                        <div class="form-section">
                            <h3><i class="fas fa-user"></i> Basic Information</h3>
                            
                            <div class="form-group">
                                <label for="name">Trainer Name *</label>
                                <input type="text" id="name" name="name" required 
                                       value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>"
                                       placeholder="Enter trainer's full name">
                            </div>
                            
                            <div class="form-group">
                                <label for="title">Professional Title *</label>
                                <input type="text" id="title" name="title" required 
                                       value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>"
                                       placeholder="e.g., Senior HR Consultant">
                            </div>
                            
                            <div class="form-group">
                                <label for="experience_years">Years of Experience</label>
                                <input type="number" id="experience_years" name="experience_years" min="0" max="50"
                                       value="<?php echo htmlspecialchars($_POST['experience_years'] ?? ''); ?>"
                                       placeholder="e.g., 15">
                            </div>
                            
                            <div class="form-group">
                                <label for="image">Profile Image</label>
                                <input type="file" id="image" name="image" accept="image/*">
                                <small class="form-help">Recommended size: 300x300px. Supported formats: JPG, PNG, GIF, WebP</small>
                            </div>
                        </div>
                        
                        <!-- Professional Details -->
                        <div class="form-section">
                            <h3><i class="fas fa-briefcase"></i> Professional Details</h3>
                            
                            <div class="form-group">
                                <label for="description">Professional Description</label>
                                <textarea id="description" name="description" rows="4" 
                                          placeholder="Describe the trainer's background, expertise, and achievements..."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="specialties">Specialties</label>
                                <input type="text" id="specialties" name="specialties" 
                                       value="<?php echo htmlspecialchars($_POST['specialties'] ?? ''); ?>"
                                       placeholder="Employee Relations, Training & Development, HR Compliance (comma-separated)">
                                <small class="form-help">Separate multiple specialties with commas</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="credentials">Credentials & Certifications</label>
                                <input type="text" id="credentials" name="credentials" 
                                       value="<?php echo htmlspecialchars($_POST['credentials'] ?? ''); ?>"
                                       placeholder="SPHR Certified, Master Trainer, HRD Corp Registered (comma-separated)">
                                <small class="form-help">Separate multiple credentials with commas</small>
                            </div>
                        </div>
                        
                        <!-- Display Settings -->
                        <div class="form-section">
                            <h3><i class="fas fa-cog"></i> Display Settings</h3>
                            
                            <div class="form-group">
                                <label for="display_order">Display Order</label>
                                <input type="number" id="display_order" name="display_order" min="0" max="999"
                                       value="<?php echo htmlspecialchars($_POST['display_order'] ?? '0'); ?>"
                                       placeholder="0 (appears first)">
                                <small class="form-help">Lower numbers appear first in the list</small>
                            </div>
                            
                            <div class="form-group">
                                <label class="checkbox-label">
                                    <input type="checkbox" name="is_active" value="1" 
                                           <?php echo !isset($_POST['is_active']) || $_POST['is_active'] ? 'checked' : ''; ?>>
                                    <span class="checkmark"></span>
                                    Active Trainer
                                </label>
                                <small class="form-help">Only active trainers are displayed on the website</small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Form Actions -->
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Add Trainer
                        </button>
                        <a href="trainers.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script>
        // Auto-hide success messages after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                if (alert.classList.contains('alert-success')) {
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 300);
                }
            });
        }, 5000);
        
        // Image preview
        document.getElementById('image').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    // Create preview if it doesn't exist
                    let preview = document.getElementById('image-preview');
                    if (!preview) {
                        preview = document.createElement('div');
                        preview.id = 'image-preview';
                        preview.style.cssText = 'margin-top: 10px; text-align: center;';
                        e.target.parentNode.appendChild(preview);
                    }
                    preview.innerHTML = '<img src="' + e.target.result + '" style="max-width: 200px; max-height: 200px; border-radius: 8px; border: 2px solid #e5e7eb;">';
                };
                reader.readAsDataURL(file);
            }
        });
    </script>
    <script src="../assets/js/motion.js?v=20261002-10"></script>
</body>
</html>
