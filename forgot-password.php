<?php
require_once 'includes/auth.php';
require_once 'includes/email-notifications.php';

auth_start();
$existing = auth_user();
if ($existing) {
    header('Location: ' . auth_home_path($existing['role'], false));
    exit;
}

$confirmation = 'If an account exists for that email, we sent a reset link. It expires in one hour.';
$error = '';
$sent = false;
$emailValue = '';
$pdo = get_db_connection();

function reset_client_ip() {
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

function reset_attempt_name($email) {
    return substr(strtolower(trim((string) $email)), 0, 255);
}

function reset_ensure_attempts(PDO $pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS login_attempts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ip_address VARCHAR(45) NOT NULL,
        login_name VARCHAR(255) NOT NULL,
        attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_login_attempts_ip (ip_address, attempted_at),
        INDEX idx_login_attempts_login (login_name, attempted_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function reset_ensure_tokens(PDO $pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS password_resets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        token_hash CHAR(64) NOT NULL,
        expires_at DATETIME NOT NULL,
        used_at DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_password_resets_user (user_id, used_at),
        INDEX idx_password_resets_hash (token_hash),
        CONSTRAINT fk_password_resets_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function reset_recent_requests(PDO $pdo, $column, $value) {
    $columns = ['ip_address' => 'ip_address', 'login_name' => 'login_name'];
    $col = $columns[$column];
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE {$col} = ? AND attempted_at > (NOW() - INTERVAL 15 MINUTE)");
    $stmt->execute([$value]);
    return (int) $stmt->fetchColumn();
}

function reset_is_limited(PDO $pdo, $ip, $emailName) {
    return reset_recent_requests($pdo, 'ip_address', $ip) >= 5
        || reset_recent_requests($pdo, 'login_name', $emailName) >= 5;
}

function reset_record_request(PDO $pdo, $ip, $emailName) {
    $pdo->prepare("DELETE FROM login_attempts WHERE attempted_at <= (NOW() - INTERVAL 15 MINUTE)")->execute();
    $pdo->prepare("INSERT INTO login_attempts (ip_address, login_name) VALUES (?, ?)")->execute([$ip, $emailName]);
}

function reset_public_url($path) {
    $https = auth_request_https();
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    $prefix = ($dir === '/' || $dir === '.' || $dir === '') ? '' : rtrim($dir, '/');
    return ($https ? 'https://' : 'http://') . $host . $prefix . '/' . ltrim($path, '/');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $emailValue = strtolower(trim($_POST['email'] ?? ''));
    $emailValid = $emailValue !== '' && filter_var($emailValue, FILTER_VALIDATE_EMAIL);

    if (!auth_verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'The form expired. Please try again.';
    } elseif (!$emailValid) {
        $error = 'Enter a valid email address.';
    } elseif (!$pdo) {
        $error = 'Database connection failed.';
    } else {
        try {
            reset_ensure_attempts($pdo);
            reset_ensure_tokens($pdo);
            $ip = reset_client_ip();
            $emailName = reset_attempt_name($emailValue);
            if (reset_is_limited($pdo, $ip, $emailName)) {
                $error = 'Too many attempts. Try again in a few minutes.';
            } else {
                reset_record_request($pdo, $ip, $emailName);
                $stmt = $pdo->prepare("SELECT id, email FROM users WHERE email = ? LIMIT 1");
                $stmt->execute([$emailValue]);
                $user = $stmt->fetch();
                if ($user && !empty($user['email'])) {
                    $token = bin2hex(random_bytes(32));
                    $hash = hash('sha256', $token);
                    $pdo->prepare("UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL")->execute([(int) $user['id']]);
                    $pdo->prepare("INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))")->execute([(int) $user['id'], $hash]);
                    $link = reset_public_url('reset-password.php?token=' . rawurlencode($token));
                    $mailer = new EmailNotifications();
                    if (!$mailer->sendPasswordReset($user['email'], $link)) {
                        error_log('Password reset email could not be sent.');
                    }
                }
                $sent = true;
                $emailValue = '';
            }
        } catch (PDOException $e) {
            $error = 'Could not send the reset email. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot password - Xpress Point Management Services</title>
    <?php $xpm_page_styles = ['pages/account.css']; include 'partials/site-head.php'; ?>
</head>
<body>
    <?php include 'partials/site-nav.php'; ?>
    <main id="main" class="auth-main">
            <div class="card auth-card">
            <?php if ($error !== ''): ?>
                <div class="alert alert-error" role="alert">
                    <i class="fas fa-circle-exclamation" aria-hidden="true"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>
            <?php if ($sent): ?>
                <div class="alert alert-success" role="status">
                    <i class="fas fa-circle-check" aria-hidden="true"></i>
                    <span><?php echo htmlspecialchars($confirmation); ?></span>
                </div>
            <?php endif; ?>
            <h1>Reset your password</h1>
            <p class="lead">Enter the email on your account.</p>
            <form method="POST" class="form">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(auth_csrf_token()); ?>">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($emailValue); ?>" autocomplete="email" inputmode="email" required>
                </div>
                <button type="submit" class="btn btn-primary btn-lg btn-block">Email me a link</button>
            </form>
            <p class="auth-switch"><a href="login.php">Return to log in</a></p>
            </div>
    </main>
    <?php include 'partials/site-footer.php'; ?>
    <script src="assets/js/motion.js?v=20261002-10"></script>
    <script src="assets/js/auth-ui.js?v=20261015-8"></script>
</body>
</html>
