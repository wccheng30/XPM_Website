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
            $position = trim($_POST['position'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $date = trim($_POST['date'] ?? '');
            $reason = trim($_POST['reason'] ?? '');
            
            // Validate required fields
            if (empty($name)) {
                throw new Exception('Name is required.');
            }
            if (!empty($phone) && !preg_match('/^\+?[0-9\-\(\)\s]{7,20}$/', $phone)) {
                throw new Exception('Invalid phone number format.');
            }
            if (!empty($date) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                throw new Exception('Invalid date format. Expected YYYY-MM-DD.');
            }
            
            // Insert blacklist record
            $stmt = $pdo->prepare("INSERT INTO blacklist (name, position_applied, phone_number, date, reason) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                $name,
                $position,
                $phone,
                $date,
                $reason
            ]);
            
            $success = "Blacklist entry added successfully!";
            
            // Clear form data
            $_POST = [];
            
        } catch (Exception $e) {
            $error = $e instanceof PDOException ? 'The record could not be saved.' : $e->getMessage();
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
    <title>Add Blacklist Entry - XpressPoint Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <?php include_once __DIR__ . '/includes/theme-boot.php'; ?>
    <link rel="stylesheet" href="<?php echo admin_asset('admin.min.css'); ?>">
    <link rel="stylesheet" href="<?php echo admin_asset('css/forms.css'); ?>">
</head>
<body data-motion="admin">
    <?php include_once 'includes/header.php'; renderAdminHeader('Add Blacklist Entry', 'fas fa-plus', ['url' => 'blacklist.php', 'label' => 'Blacklist']); ?>
    <div class="admin-container">
        <!-- Navigation -->
        <?php include 'includes/navigation.php'; renderAdminNavigation('blacklist'); ?>


        <!-- Main Content -->
        <main class="admin-main">
            <div class="content-section">
                <div class="section-header">
                    <h2>Blacklist Information</h2>
                    <p>Add a person to the blacklist</p>
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
                        <a href="blacklist.php" class="btn btn-sm btn-primary" style="margin-left: 10px;">
                            View Blacklist
                        </a>
                    </div>
                <?php endif; ?>

                <!-- Form -->
                <?php
                $mode = 'add';
                $field_errors = [];
                if (!empty($error)) {
                    if (stripos($error, 'name') !== false) { $field_errors['name'] = $error; }
                    elseif (stripos($error, 'phone') !== false) { $field_errors['phone'] = $error; }
                    elseif (stripos($error, 'date') !== false) { $field_errors['date'] = $error; }
                    else { $field_errors['_form'] = $error; }
                }
                $values = $_POST;
                include __DIR__ . '/includes/forms/blacklist-form.php';
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
