<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

// Handle logout
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_logout'])) {
    // Clear all session data
    session_unset();
    session_destroy();
    
    // Redirect to login page
    header('Location: index.php?logout=success');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirm Logout - XpressPoint Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="admin-styles.css?v=20261005-5">
    <link rel="stylesheet" href="css/auth.css?v=20261005-1">
</head>
<body class="auth-center" data-motion="admin">
    <script>
        try { if (localStorage.getItem('theme') === 'dark') document.body.setAttribute('data-theme', 'dark'); } catch (e) {}
    </script>
    <div class="logout-container" role="dialog" aria-labelledby="logout-title">
        <div class="logout-icon">
            <i class="fas fa-sign-out-alt"></i>
        </div>

        <h1 class="logout-title" id="logout-title">Sign out?</h1>

        <p class="logout-message">
            Are you sure you want to log out of the XpressPoint Admin Panel?
            You will need to log in again to access the admin features.
        </p>

        <div class="logout-buttons">
            <a href="dashboard.php" class="btn btn-secondary btn-cancel">
                <i class="fas fa-arrow-left"></i>
                Cancel
            </a>

            <form method="POST">
                <button type="submit" name="confirm_logout" class="btn btn-primary btn-logout">
                    <i class="fas fa-sign-out-alt"></i>
                    Yes, Logout
                </button>
            </form>
        </div>
    </div>
    <script src="../assets/js/motion.js?v=20261002-10"></script>
</body>
</html>
