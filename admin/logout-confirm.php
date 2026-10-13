<?php
require_once '../includes/auth.php';
auth_require(['admin', 'manager', 'trainer']);
csrf_guard();

// Handle logout
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_logout'])) {
    // Clear all session data
    session_unset();
    session_destroy();
    
    // Redirect to login page
    header('Location: ../login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirm Logout - XpressPoint Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <?php include_once __DIR__ . '/includes/theme-boot.php'; ?>
    <link rel="stylesheet" href="<?php echo admin_asset('../assets/css/xpm-tokens.css'); ?>">
    <link rel="stylesheet" href="<?php echo admin_asset('../assets/css/xpm-motion.css'); ?>">
    <link rel="stylesheet" href="<?php echo admin_asset('admin.min.css'); ?>">
    <link rel="stylesheet" href="<?php echo admin_asset('css/auth.css'); ?>">
</head>
<body class="auth-center" data-motion="admin">
    <div class="logout-container" role="dialog" aria-labelledby="logout-title">
        <div class="logout-icon">
            <i class="fas fa-sign-out-alt" aria-hidden="true"></i>
        </div>

        <h1 class="logout-title" id="logout-title">Sign out?</h1>

        <p class="logout-message">
            Are you sure you want to log out of the XpressPoint Admin Panel?
            You will need to log in again to access the admin features.
        </p>

        <div class="logout-buttons">
            <a href="dashboard.php" class="btn btn-secondary btn-cancel">
                <i class="fas fa-arrow-left" aria-hidden="true"></i>
                Cancel
            </a>

            <form method="POST">
                <?php echo csrf_field(); ?>
                <button type="submit" name="confirm_logout" class="btn btn-primary btn-logout">
                    <i class="fas fa-sign-out-alt" aria-hidden="true"></i>
                    Yes, Logout
                </button>
            </form>
        </div>
    </div>
    <script defer src="<?php echo admin_asset('../assets/js/motion.js'); ?>"></script>
</body>
</html>
