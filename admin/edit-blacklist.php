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

// Get blacklist ID from URL
$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: blacklist.php');
    exit;
}

$pdo = get_db_connection();
if (!$pdo) {
    die("Database connection failed.");
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
        
        // Update blacklist record
        $stmt = $pdo->prepare("UPDATE blacklist SET name = ?, position_applied = ?, phone_number = ?, date = ?, reason = ? WHERE id = ?");
        $stmt->execute([
            $name,
            $position,
            $phone,
            $date,
            $reason,
            $id
        ]);
        
        $success = "Blacklist entry updated successfully!";
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get current data
try {
    $stmt = $pdo->prepare("SELECT * FROM blacklist WHERE id = ?");
    $stmt->execute([$id]);
    $blacklist_entry = $stmt->fetch();
    
    if (!$blacklist_entry) {
        header('Location: blacklist.php');
        exit;
    }
} catch (PDOException $e) {
    $error = "Error fetching data: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Blacklist Entry - XpressPoint Admin</title>
    <link rel="stylesheet" href="admin-styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="admin-container">
        <!-- Navigation -->
        <?php include 'includes/navigation.php'; renderAdminNavigation('blacklist'); ?>

        <!-- Header -->
        <header class="admin-header">
            <div class="header-content">
                <h1><i class="fas fa-edit"></i> Edit Blacklist Entry</h1>
                <div class="header-actions">
                    <a href="blacklist.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Blacklist
                    </a>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="admin-main">
            <div class="content-section">
                <div class="section-header">
                    <h2>Blacklist Information</h2>
                    <p>Update blacklist details</p>
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

                <!-- Form -->
                <form method="POST" class="form-container">
                    <div class="form-grid">
                        <!-- Basic Information -->
                        <div class="form-section">
                            <h3><i class="fas fa-user-slash"></i> Person Details</h3>
                            
                            <div class="form-group">
                                <label for="name">Name *</label>
                                <input type="text" id="name" name="name" required 
                                       value="<?php echo htmlspecialchars($_POST['name'] ?? $blacklist_entry['name'] ?? ''); ?>"
                                       placeholder="Enter full name">
                            </div>
                            
                            <div class="form-group">
                                <label for="position">Position Applied</label>
                                <input type="text" id="position" name="position"
                                       value="<?php echo htmlspecialchars($_POST['position'] ?? $blacklist_entry['position_applied'] ?? ''); ?>"
                                       placeholder="e.g., Senior Developer">
                            </div>
                            
                            <div class="form-group">
                                <label for="phone">Phone Number</label>
                                <input type="text" id="phone" name="phone"
                                       value="<?php echo htmlspecialchars($_POST['phone'] ?? $blacklist_entry['phone_number'] ?? ''); ?>"
                                       placeholder="e.g., +60123456789">
                            </div>
                            
                            <div class="form-group">
                                <label for="date">Date</label>
                                <input type="date" id="date" name="date"
                                       value="<?php echo htmlspecialchars($_POST['date'] ?? $blacklist_entry['date'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <!-- Additional Details -->
                        <div class="form-section">
                            <h3><i class="fas fa-info-circle"></i> Additional Info</h3>
                            
                            <div class="form-group">
                                <label for="reason">Reason for Blacklist</label>
                                <textarea id="reason" name="reason" rows="4" 
                                          placeholder="Describe the reason for blacklisting..."><?php echo htmlspecialchars($_POST['reason'] ?? $blacklist_entry['reason'] ?? ''); ?></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Form Actions -->
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Changes
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
</body>
</html>
