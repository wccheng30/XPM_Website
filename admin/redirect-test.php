<?php
// Simple redirect test
echo "<h2>Redirect Test</h2>";
echo "<p>Testing PHP redirect functionality...</p>";

if (isset($_GET['test'])) {
    echo "<p>Attempting redirect in 3 seconds...</p>";
    echo "<p>If you see this message after 3 seconds, the redirect failed.</p>";
    
    // Test different redirect methods
    if ($_GET['test'] === 'header') {
        header('Location: dashboard.php');
        exit;
    } elseif ($_GET['test'] === 'meta') {
        echo '<meta http-equiv="refresh" content="3;url=dashboard.php">';
        echo '<p>Meta refresh redirect...</p>';
    } elseif ($_GET['test'] === 'js') {
        echo '<script>setTimeout(function(){ window.location.href = "dashboard.php"; }, 3000);</script>';
        echo '<p>JavaScript redirect...</p>';
    }
} else {
    echo "<p><a href='?test=header'>Test PHP Header Redirect</a></p>";
    echo "<p><a href='?test=meta'>Test Meta Refresh Redirect</a></p>";
    echo "<p><a href='?test=js'>Test JavaScript Redirect</a></p>";
    echo "<p><a href='dashboard.php'>Direct Link to Dashboard</a></p>";
}
?>
