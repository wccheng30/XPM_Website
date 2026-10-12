<?php
require_once '../includes/auth.php';
auth_require(['admin', 'manager']);

// Include database connection
require_once '../includes/database.php';

$error = '';
$success = '';
$trainer = null;

// Get trainer ID from URL
$trainer_id = $_GET['id'] ?? null;

if (!$trainer_id) {
    header('Location: trainers.php?error=Invalid trainer ID');
    exit;
}

$pdo = get_db_connection();

if (!$pdo) {
    header('Location: trainers.php?error=Database connection failed');
    exit;
}

// Get trainer data
try {
    $stmt = $pdo->prepare("SELECT * FROM trainers WHERE id = ?");
    $stmt->execute([$trainer_id]);
    $trainer = $stmt->fetch();
    
    if (!$trainer) {
        header('Location: trainers.php?error=Trainer not found');
        exit;
    }
} catch (PDOException $e) {
    header('Location: trainers.php?error=Error fetching trainer data');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
        $image_path = $trainer['image_path']; // Keep existing image by default
        
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
                // Delete old image if it exists and is not a default image
                if ($trainer['image_path'] && 
                    !strpos($trainer['image_path'], 'icol-chua.png') && 
                    !strpos($trainer['image_path'], 'HRDTrainer.png') && 
                    !strpos($trainer['image_path'], 'AccreditedTrainer.png') && 
                    !strpos($trainer['image_path'], 'PowerUpMentor.png')) {
                    $old_image_path = '../' . $trainer['image_path'];
                    if (file_exists($old_image_path)) {
                        unlink($old_image_path);
                    }
                }
                $image_path = 'assets/images/trainers/' . $filename;
            } else {
                throw new Exception('Failed to upload image.');
            }
        }
        
        // Handle image removal
        if (isset($_POST['remove_image']) && $_POST['remove_image'] === '1') {
            if ($trainer['image_path'] && 
                !strpos($trainer['image_path'], 'icol-chua.png') && 
                !strpos($trainer['image_path'], 'HRDTrainer.png') && 
                !strpos($trainer['image_path'], 'AccreditedTrainer.png') && 
                !strpos($trainer['image_path'], 'PowerUpMentor.png')) {
                $old_image_path = '../' . $trainer['image_path'];
                if (file_exists($old_image_path)) {
                    unlink($old_image_path);
                }
            }
            $image_path = null;
        }
        
        // Update trainer
        $stmt = $pdo->prepare("UPDATE trainers SET name = ?, title = ?, experience_years = ?, image_path = ?, description = ?, specialties = ?, credentials = ?, is_active = ?, display_order = ? WHERE id = ?");
        $stmt->execute([
            $name,
            $title,
            $experience_years,
            $image_path,
            $description,
            json_encode($specialties),
            json_encode($credentials),
            $is_active,
            $display_order,
            $trainer_id
        ]);
        
        $success = "Trainer updated successfully!";
        
        // Refresh trainer data
        $stmt = $pdo->prepare("SELECT * FROM trainers WHERE id = ?");
        $stmt->execute([$trainer_id]);
        $trainer = $stmt->fetch();
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Parse specialties and credentials for display
$specialties = [];
$credentials = [];
if ($trainer['specialties']) {
    $specialties = json_decode($trainer['specialties'], true) ?: [];
}
if ($trainer['credentials']) {
    $credentials = json_decode($trainer['credentials'], true) ?: [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Trainer - XpressPoint Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="admin-styles.css?v=20261005-5">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body data-motion="admin">
    <?php include_once 'includes/header.php'; renderAdminHeader('Edit Trainer', 'fas fa-edit', ['url' => 'trainers.php', 'label' => 'Trainers']); ?>
    <div class="admin-container">
        <!-- Navigation -->
        <?php include 'includes/navigation.php'; renderAdminNavigation('trainers'); ?>


        <!-- Main Content -->
        <main class="admin-main">
            <div class="content-section">
                <div class="section-header">
                    <h2>Edit Trainer Information</h2>
                    <p>Update trainer details and settings</p>
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
                                       value="<?php echo htmlspecialchars($trainer['name']); ?>"
                                       placeholder="Enter trainer's full name">
                            </div>
                            
                            <div class="form-group">
                                <label for="title">Professional Title *</label>
                                <input type="text" id="title" name="title" required 
                                       value="<?php echo htmlspecialchars($trainer['title']); ?>"
                                       placeholder="e.g., Senior HR Consultant">
                            </div>
                            
                            <div class="form-group">
                                <label for="experience_years">Years of Experience</label>
                                <input type="number" id="experience_years" name="experience_years" min="0" max="50"
                                       value="<?php echo htmlspecialchars($trainer['experience_years']); ?>"
                                       placeholder="e.g., 15">
                            </div>
                            
                            <div class="form-group">
                                <label for="image">Profile Image</label>
                                <?php if ($trainer['image_path']): ?>
                                    <div class="current-image">
                                        <img src="../<?php echo htmlspecialchars($trainer['image_path']); ?>" 
                                             alt="Current image" style="max-width: 150px; max-height: 150px; border-radius: 8px; margin-bottom: 10px;">
                                        <br>
                                        <label class="checkbox-label">
                                            <input type="checkbox" name="remove_image" value="1">
                                            <span class="checkmark"></span>
                                            Remove current image
                                        </label>
                                    </div>
                                <?php endif; ?>
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
                                          placeholder="Describe the trainer's background, expertise, and achievements..."><?php echo htmlspecialchars($trainer['description']); ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="specialties">Specialties</label>
                                <input type="text" id="specialties" name="specialties" 
                                       value="<?php echo htmlspecialchars(implode(', ', $specialties)); ?>"
                                       placeholder="Employee Relations, Training & Development, HR Compliance (comma-separated)">
                                <small class="form-help">Separate multiple specialties with commas</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="credentials">Credentials & Certifications</label>
                                <input type="text" id="credentials" name="credentials" 
                                       value="<?php echo htmlspecialchars(implode(', ', $credentials)); ?>"
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
                                       value="<?php echo htmlspecialchars($trainer['display_order']); ?>"
                                       placeholder="0 (appears first)">
                                <small class="form-help">Lower numbers appear first in the list</small>
                            </div>
                            
                            <div class="form-group">
                                <label class="checkbox-label">
                                    <input type="checkbox" name="is_active" value="1" 
                                           <?php echo $trainer['is_active'] ? 'checked' : ''; ?>>
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
                            <i class="fas fa-save"></i> Update Trainer
                        </button>
                        <a href="trainers.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                        <?php if (($_SESSION['admin_role'] ?? 'admin') !== 'manager'): ?>
                        <a href="trainers.php?action=delete&id=<?php echo $trainer['id']; ?>" 
                           class="btn btn-danger" 
                           data-confirm-title="Delete this trainer?" data-confirm="This action cannot be undone." data-confirm-ok="Delete" data-confirm-icon="fas fa-trash">
                            <i class="fas fa-trash"></i> Delete Trainer
                        </a>
                        <?php endif; ?>
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
