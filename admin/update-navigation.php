<?php
/**
 * Navigation Update Script
 * This script adds the Facility Calendar link to all admin pages
 */

$admin_files = [
    'dashboard.php',
    'contacts.php', 
    'jobs.php',
    'applications.php',
    'registrations.php',
    'programmes.php',
    'bookings.php',
    'programme-facility-management.php'
];

$facility_calendar_link = '                <a href="facility-calendar.php" class="nav-tab">
                    <i class="fas fa-calendar"></i>
                    Facility Calendar
                </a>';

$updated_files = [];
$errors = [];

foreach ($admin_files as $file) {
    $file_path = $file;
    
    if (!file_exists($file_path)) {
        $errors[] = "File not found: $file_path";
        continue;
    }
    
    $content = file_get_contents($file_path);
    
    // Check if the link already exists
    if (strpos($content, 'facility-calendar.php') !== false) {
        $updated_files[] = "$file_path - Already has Facility Calendar link";
        continue;
    }
    
    // Find the pattern to replace
    $pattern = '/(<a href="bookings\.php" class="nav-tab">\s*<i class="fas fa-calendar-alt"><\/i>\s*Bookings\s*<\/a>\s*)(<\/div>\s*<\/nav>)/';
    
    if (preg_match($pattern, $content)) {
        $new_content = preg_replace($pattern, '$1' . $facility_calendar_link . "\n            $2", $content);
        
        if (file_put_contents($file_path, $new_content)) {
            $updated_files[] = "$file_path - Updated successfully";
        } else {
            $errors[] = "Failed to update: $file_path";
        }
    } else {
        $errors[] = "Pattern not found in: $file_path";
    }
}

// Display results
echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Navigation Update - XpressPoint Admin</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f8fafc; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .success { color: #10b981; }
        .error { color: #ef4444; }
        .info { color: #3b82f6; }
        h1 { color: #1e293b; }
        ul { list-style: none; padding: 0; }
        li { padding: 5px 0; }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔧 Navigation Update Results</h1>
        
        <h2>✅ Successfully Updated Files:</h2>
        <ul>";

foreach ($updated_files as $file) {
    echo "<li class='success'>$file</li>";
}

echo "</ul>";

if (!empty($errors)) {
    echo "<h2>❌ Errors:</h2>
    <ul>";
    foreach ($errors as $error) {
        echo "<li class='error'>$error</li>";
    }
    echo "</ul>";
}

echo "<h2>📋 Summary:</h2>
        <p class='info'>Total files processed: " . count($admin_files) . "</p>
        <p class='success'>Successfully updated: " . count($updated_files) . "</p>
        <p class='error'>Errors: " . count($errors) . "</p>
        
        <h2>🎯 Next Steps:</h2>
        <p>The Facility Calendar link has been added to all admin pages. You can now access the calendar from any admin page navigation.</p>
        
        <a href='facility-calendar.php' style='display: inline-block; padding: 10px 20px; background: #3b82f6; color: white; text-decoration: none; border-radius: 6px; margin-top: 20px;'>
            🗓️ Go to Facility Calendar
        </a>
    </div>
</body>
</html>";
?>
