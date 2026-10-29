<?php
require_once '../includes/auth.php';
auth_require(['admin', 'manager']);
csrf_guard();

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
            if (isset($_FILES['image']) && ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $saved = auth_save_image($_FILES['image'], 'assets/images/trainers', 'trainer');
                if (!$saved['ok']) {
                    throw new Exception($saved['error'] ?: 'Failed to upload image.');
                }
                $image_path = $saved['path'];
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
            $error = $e instanceof PDOException ? 'The trainer could not be saved.' : $e->getMessage();
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
    <?php include_once __DIR__ . '/includes/theme-boot.php'; ?>
    <link rel="stylesheet" href="<?php echo admin_asset('admin.min.css'); ?>">
    <link rel="stylesheet" href="<?php echo admin_asset('css/forms.css'); ?>">
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
                        <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle" aria-hidden="true"></i>
                        <?php echo htmlspecialchars($success); ?>
                        <a href="trainers.php" class="btn btn-sm btn-primary" style="margin-left: 10px;">
                            View Trainers
                        </a>
                    </div>
                <?php endif; ?>

                <!-- Form Tips -->
                <div class="form-tips">
                    <h4><i class="fas fa-lightbulb" aria-hidden="true"></i> Quick Tips</h4>
                    <ul>
                        <li>Use a professional headshot for the trainer image (300x300px recommended)</li>
                        <li>Separate specialties and credentials with commas</li>
                        <li>Featured trainers appear prominently on the website</li>
                        <li>Display order determines the sequence trainers appear (lower numbers first)</li>
                    </ul>
                </div>

                <!-- Form -->
                <?php
                $mode = 'add';
                $field_errors = [];
                if (!empty($error)) {
                    if (stripos($error, 'title') !== false) { $field_errors['title'] = $error; }
                    elseif (stripos($error, 'name') !== false) { $field_errors['name'] = $error; }
                    elseif (stripos($error, 'file') !== false || stripos($error, 'upload') !== false || stripos($error, 'image') !== false) { $field_errors['image'] = $error; }
                    else { $field_errors['_form'] = $error; }
                }
                $values = $_POST;
                if (!array_key_exists('display_order', $values)) { $values['display_order'] = '0'; }
                include __DIR__ . '/includes/forms/trainer-form.php';
                ?>

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
    </script>
        <script defer src="<?php echo admin_asset('../assets/js/leave-guard.js'); ?>"></script>
    <script defer src="<?php echo admin_asset('js/forms.js'); ?>"></script>
</body>
</html>
