<?php
// Configure session for hosting compatibility
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0);
ini_set('session.cookie_samesite', 'Lax');

session_start();

echo "<h2>Login Debug</h2>";
echo "<p>Session ID: " . session_id() . "</p>";
echo "<p>Current Session Data: " . print_r($_SESSION, true) . "</p>";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    echo "<h3>Login Attempt</h3>";
    echo "<p>Username: " . htmlspecialchars($username) . "</p>";
    echo "<p>Password provided: " . (empty($password) ? 'No' : 'Yes') . "</p>";
    
    if (empty($username) || empty($password)) {
        echo "<p style='color: red;'>Error: Please enter both username and password</p>";
    } else {
        // Test hardcoded credentials first
        if ($username === 'admin' && $password === 'xpresspoint2024') {
            echo "<p style='color: green;'>✓ Hardcoded credentials match!</p>";
            
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_username'] = $username;
            
            echo "<p>Session after setting:</p>";
            echo "<pre>" . print_r($_SESSION, true) . "</pre>";
            
            echo "<p>Checking session variables:</p>";
            echo "<p>admin_logged_in exists: " . (isset($_SESSION['admin_logged_in']) ? 'Yes' : 'No') . "</p>";
            echo "<p>admin_logged_in value: " . ($_SESSION['admin_logged_in'] ?? 'Not set') . "</p>";
            echo "<p>admin_logged_in === true: " . (($_SESSION['admin_logged_in'] ?? false) === true ? 'Yes' : 'No') . "</p>";
            
            if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
                echo "<p style='color: green;'>✓ Session set correctly! Redirecting...</p>";
                echo "<p><a href='dashboard.php'>Click here to go to dashboard</a></p>";
                // Uncomment the next line to test actual redirect
                // header('Location: dashboard.php'); exit;
            } else {
                echo "<p style='color: red;'>✗ Session not set properly</p>";
            }
        } else {
            echo "<p style='color: red;'>✗ Hardcoded credentials don't match</p>";
        }
    }
}
?>

<form method="POST">
    <h3>Test Login</h3>
    <p>Username: <input type="text" name="username" value="admin"></p>
    <p>Password: <input type="password" name="password" value="xpresspoint2024"></p>
    <p><input type="submit" value="Test Login"></p>
</form>

<p><a href="index.php">Back to normal login</a></p>
