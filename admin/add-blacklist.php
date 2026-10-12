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
    <title>Add Blacklist Entry - XpressPoint Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="admin-styles.css?v=20261005-5">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?php echo htmlspecialchars($success); ?>
                        <a href="blacklist.php" class="btn btn-sm btn-primary" style="margin-left: 10px;">
                            View Blacklist
                        </a>
                    </div>
                <?php endif; ?>

                <!-- Form -->
                <form method="POST" enctype="multipart/form-data" class="form-container">
                    <div class="form-grid">
                        <!-- Basic Information -->
                        <div class="form-section">
                            <h3><i class="fas fa-user-slash"></i> Person Details</h3>
                            
                            <div class="form-group">
                                <label for="name">Name *</label>
                                <input type="text" id="name" name="name" required 
                                       value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>"
                                       placeholder="Enter full name">
                            </div>
                            
                            <div class="form-group">
                                <label for="position">Position Applied</label>
                                <input type="text" id="position" name="position"
                                       value="<?php echo htmlspecialchars($_POST['position'] ?? ''); ?>"
                                       placeholder="e.g., Senior Developer">
                            </div>
                            
                            <div class="form-group">
                                <label for="phone">Phone Number</label>
                                <input type="text" id="phone" name="phone"
                                       value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>"
                                       placeholder="e.g., +60123456789">
                            </div>
                            
                            <div class="form-group">
                                <label for="date">Date</label>
                                <input type="date" id="date" name="date"
                                       value="<?php echo htmlspecialchars($_POST['date'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <!-- Additional Details -->
                        <div class="form-section">
                            <h3><i class="fas fa-info-circle"></i> Additional Info</h3>
                            
                            <div class="form-group">
                                <label for="reason">Reason for Blacklist</label>
                                <textarea id="reason" name="reason" rows="4" 
                                          placeholder="Describe the reason for blacklisting..."><?php echo htmlspecialchars($_POST['reason'] ?? ''); ?></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Form Actions -->
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Add to Blacklist
                        </button>
                        <a href="blacklist.php" class="btn btn-secondary">
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
    </script>
    <script src="../assets/js/motion.js?v=20261002-10"></script>
</body>
</html>
