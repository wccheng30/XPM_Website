<?php
session_start();

// Destroy all session data
session_destroy();

// Check if user wants to go back to main site
if (isset($_GET['redirect']) && $_GET['redirect'] === 'main') {
    header('Location: ../index.html');
    exit;
}

// Redirect to login page by default
header('Location: index.php');
exit;
?> 