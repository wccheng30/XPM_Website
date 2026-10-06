<?php
// Configure session for hosting compatibility
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // Set to 1 if using HTTPS
session_start();



// Redirect if already logged in
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: dashboard.php');
    exit;
}

// Include shared database connection
require_once '../includes/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password';
    } else {
        try {
            $pdo = get_db_connection();
            
            if ($pdo) {
                // Check if admin user exists in database
                $stmt = $pdo->prepare("SELECT password, role FROM users WHERE username = ?");
                $stmt->execute([$username]);
                $user = $stmt->fetch();
                
                if ($user && password_verify($password, $user['password'])) {
                    // Valid password from database
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_username'] = $username;
                    $_SESSION['admin_role'] = $user['role'] ?? 'admin';
                    header('Location: dashboard.php');
                    exit;
                } elseif ($username === 'admin' && $password === 'xpresspoint2024') {
                    // Fallback to hardcoded password for existing installations
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_username'] = $username;
                    $_SESSION['admin_role'] = 'admin';

                    
                    header('Location: dashboard.php');
                    exit;
                } else {
                    $error = 'Invalid username or password';
                }
            } else {
                $error = 'Database connection failed';
            }
        } catch (PDOException $e) {
            $error = 'Login error. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - XpressPoint HR Training</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="admin-styles.css?v=20261005-5">
    <link rel="stylesheet" href="css/auth.css?v=20261005-1">
</head>
<body class="auth-body" data-motion="admin">
    <div class="auth-shell">
        <aside class="auth-aside">
            <a href="../index.html" class="auth-brand">
                <span class="auth-brand-mark">XP</span>
                XpressPoint
            </a>

            <div class="auth-pitch">
                <span class="auth-pitch-kicker"><span class="pulse-dot"></span> Admin workspace</span>
                <h2>Everything your team runs, in one quiet place.</h2>
                <p>Manage contacts, programmes, job applications and facility bookings for XpressPoint HR Training.</p>
                <ul class="auth-features">
                    <li><i class="fas fa-users"></i> Contacts, applications and registrations</li>
                    <li><i class="fas fa-calendar-alt"></i> Facility bookings and calendars</li>
                    <li><i class="fas fa-chalkboard-teacher"></i> Programmes, trainers and jobs</li>
                </ul>
            </div>

            <div class="auth-aside-foot">&copy; <?php echo date('Y'); ?> Xpress Point Management Services</div>
        </aside>

        <main class="auth-main">
            <div class="login-container auth-card">
                <div class="login-header auth-card-head">
                    <div class="logo auth-card-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <h1>Welcome back</h1>
                    <p>Sign in to your XpressPoint dashboard</p>
                </div>

                <?php if ($error): ?>
                    <div class="error-message" role="alert">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($_GET['logout']) && $_GET['logout'] === 'success'): ?>
                    <div class="auth-notice" role="status">
                        <i class="fas fa-check-circle"></i>
                        You have been successfully logged out.
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="form-group">
                        <label for="username">
                            <i class="fas fa-user"></i>
                            Username
                        </label>
                        <input type="text" id="username" name="username" class="form-control"
                               value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                               placeholder="Enter your username" autocomplete="username" required>
                    </div>

                    <div class="form-group">
                        <label for="password">
                            <i class="fas fa-lock"></i>
                            Password
                        </label>
                        <input type="password" id="password" name="password" class="form-control"
                               placeholder="Enter your password" autocomplete="current-password" required>
                    </div>

                    <button type="submit" class="login-btn btn btn-primary auth-submit">
                        Sign In
                        <i class="fas fa-arrow-right"></i>
                    </button>
                </form>

                <div class="form-footer">
                    <span>Secure admin access</span>
                    <span class="security-badge">
                        <i class="fas fa-shield-alt"></i>
                        SSL Secured
                    </span>
                </div>

                <a href="../index.html" class="back-link"><i class="fas fa-arrow-left"></i> Back to website</a>
            </div>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Add loading state to form submission
            const form = document.querySelector('form');
            form.addEventListener('submit', function() {
                const submitBtn = this.querySelector('.login-btn');
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Signing In...';
                submitBtn.style.pointerEvents = 'none';
            });
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && document.activeElement.tagName !== 'TEXTAREA') {
                document.querySelector('.login-btn').click();
            }
        });
    </script>
    <script src="../assets/js/motion.js?v=20261002-10"></script>
</body>
</html>
