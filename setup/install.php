<?php
// ===== XPRESSPOINT INSTALLATION SCRIPT =====
session_start();

$step = $_GET['step'] ?? 1;
$error = '';
$success = '';

// Database configuration
// Include shared database connection
require_once '../includes/database.php';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_database':
                $pdo = get_db_connection(true);
                if ($pdo) {
                    try {
                        $pdo->exec("CREATE DATABASE IF NOT EXISTS {$db_config['dbname']} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                        $success = "Database created successfully!";
                        $step = 2;
                    } catch (PDOException $e) {
                        $error = "Failed to create database: " . $e->getMessage();
                    }
                } else {
                    $error = "Cannot connect to MySQL. Please check your database configuration.";
                }
                break;
                
            case 'create_tables':
                $pdo = get_db_connection();
                if ($pdo) {
                    try {
                        // Read and execute the SQL file
                        $sql_file = file_get_contents('../setup/database.sql');
                        $statements = explode(';', $sql_file);
                        
                        foreach ($statements as $statement) {
                            $statement = trim($statement);
                            if (!empty($statement)) {
                                $pdo->exec($statement);
                            }
                        }
                        
                        $success = "Tables created successfully!";
                        $step = 3;
                    } catch (PDOException $e) {
                        $error = "Failed to create tables: " . $e->getMessage();
                    }
                } else {
                    $error = "Cannot connect to database.";
                }
                break;
                
            case 'test_system':
                $pdo = get_db_connection();
                if ($pdo) {
                    try {
                        // Test admin login
                        $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE username = 'admin'");
                        $admin_count = $stmt->fetch()['count'];
                        
                        if ($admin_count > 0) {
                            $success = "System is ready! Admin account created successfully.";
                            $step = 4;
                        } else {
                            $error = "Admin account not found. Please check the database setup.";
                        }
                    } catch (PDOException $e) {
                        $error = "Database test failed: " . $e->getMessage();
                    }
                } else {
                    $error = "Cannot connect to database.";
                }
                break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>XpressPoint Installation</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        
        .install-container {
            background: white;
            padding: 3rem;
            border-radius: 1rem;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 600px;
        }
        
        .install-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .install-header i {
            font-size: 3rem;
            color: #2563eb;
            margin-bottom: 1rem;
        }
        
        .install-header h1 {
            color: #1f2937;
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }
        
        .install-header p {
            color: #6b7280;
            font-size: 0.875rem;
        }
        
        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 2rem;
        }
        
        .step {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 0.5rem;
            font-weight: 600;
            font-size: 0.875rem;
        }
        
        .step.active {
            background: #2563eb;
            color: white;
        }
        
        .step.completed {
            background: #059669;
            color: white;
        }
        
        .step.pending {
            background: #e5e7eb;
            color: #6b7280;
        }
        
        .step-content {
            margin-bottom: 2rem;
        }
        
        .step-content h3 {
            color: #1f2937;
            margin-bottom: 1rem;
        }
        
        .step-content p {
            color: #6b7280;
            margin-bottom: 1rem;
            line-height: 1.6;
        }
        
        .btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            background: #2563eb;
            color: white;
            text-decoration: none;
            border-radius: 0.5rem;
            font-size: 1rem;
            font-weight: 500;
            border: none;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        
        .btn:hover {
            background: #1d4ed8;
        }
        
        .btn-success {
            background: #059669;
        }
        
        .btn-success:hover {
            background: #047857;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
        }
        
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        
        .system-info {
            background: #f9fafb;
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
        }
        
        .system-info h4 {
            color: #374151;
            margin-bottom: 0.5rem;
        }
        
        .system-info ul {
            color: #6b7280;
            font-size: 0.875rem;
        }
        
        .system-info li {
            margin-bottom: 0.25rem;
        }
    </style>
</head>
<body>
    <div class="install-container">
        <div class="install-header">
            <i class="fas fa-graduation-cap"></i>
            <h1>XpressPoint Installation</h1>
            <p>Setting up your HR Training Management System</p>
        </div>
        
        <div class="step-indicator">
            <div class="step <?php echo $step >= 1 ? 'active' : 'pending'; ?>">1</div>
            <div class="step <?php echo $step >= 2 ? 'completed' : ($step > 1 ? 'active' : 'pending'); ?>">2</div>
            <div class="step <?php echo $step >= 3 ? 'completed' : ($step > 2 ? 'active' : 'pending'); ?>">3</div>
            <div class="step <?php echo $step >= 4 ? 'completed' : ($step > 3 ? 'active' : 'pending'); ?>">4</div>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($step == 1): ?>
            <div class="step-content">
                <h3>Welcome to XpressPoint</h3>
                <p>This installation wizard will help you set up your HR Training Management System. We'll create the database and configure everything for you.</p>
                
                <div class="system-info">
                    <h4>System Requirements:</h4>
                    <ul>
                        <li>✅ PHP 7.4 or higher (Current: <?php echo phpversion(); ?>)</li>
                        <li>✅ MySQL/MariaDB database</li>
                        <li>✅ Web server (Apache/Nginx)</li>
                        <li>✅ PDO MySQL extension</li>
                    </ul>
                </div>
                
                <form method="POST">
                    <input type="hidden" name="action" value="create_database">
                    <button type="submit" class="btn">Start Installation</button>
                </form>
            </div>
            
        <?php elseif ($step == 2): ?>
            <div class="step-content">
                <h3>Database Created</h3>
                <p>The database has been created successfully. Now we'll set up the required tables and sample data.</p>
                
                <form method="POST">
                    <input type="hidden" name="action" value="create_tables">
                    <button type="submit" class="btn">Create Tables</button>
                </form>
            </div>
            
        <?php elseif ($step == 3): ?>
            <div class="step-content">
                <h3>Tables Created</h3>
                <p>All database tables have been created with sample data. Let's test the system to make sure everything is working.</p>
                
                <form method="POST">
                    <input type="hidden" name="action" value="test_system">
                    <button type="submit" class="btn">Test System</button>
                </form>
            </div>
            
        <?php elseif ($step == 4): ?>
            <div class="step-content">
                <h3>Installation Complete!</h3>
                <p>Your XpressPoint HR Training Management System is now ready to use.</p>
                
                <div class="system-info">
                    <h4>Access Information:</h4>
                    <ul>
                        <li><strong>Main Website:</strong> <a href="../index.html">View Website</a></li>
                        <li><strong>Admin Panel:</strong> <a href="../admin/">Access Admin</a></li>
                        <li><strong>Admin Login:</strong> admin / xpresspoint2024</li>
                        <li><strong>Keyboard Shortcut:</strong> Ctrl+Alt+Shift+X</li>
                    </ul>
                </div>
                
                <div class="system-info">
                    <h4>Next Steps:</h4>
                    <ul>
                        <li>Test the contact form on the main website</li>
                        <li>Log in to the admin panel</li>
                        <li>Try the keyboard shortcut for quick admin access</li>
                        <li>Configure email settings in api/contact.php</li>
                    </ul>
                </div>
                
                <a href="../index.html" class="btn btn-success">Go to Website</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html> 