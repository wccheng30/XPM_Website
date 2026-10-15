<?php
require_once 'includes/auth.php';

auth_start();
$returnTo = auth_safe_next($_GET['next'] ?? $_POST['next'] ?? '');
$existing = auth_user();
if ($existing) {
    if ($returnTo !== '') {
        header('Location: ' . $returnTo);
        exit;
    }
    $next = auth_after_login_path($existing['role']);
    header('Location: ' . $next);
    exit;
}

$error = '';
$pdo = get_db_connection();

function login_client_ip() {
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

function login_attempt_name($login) {
    return substr(strtolower(trim((string) $login)), 0, 255);
}

function login_ensure_attempts(PDO $pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS login_attempts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ip_address VARCHAR(45) NOT NULL,
        login_name VARCHAR(255) NOT NULL,
        attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_login_attempts_ip (ip_address, attempted_at),
        INDEX idx_login_attempts_login (login_name, attempted_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function login_recent_failures(PDO $pdo, $column, $value) {
    $columns = ['ip_address' => 'ip_address', 'login_name' => 'login_name'];
    $col = $columns[$column];
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE {$col} = ? AND attempted_at > (NOW() - INTERVAL 15 MINUTE)");
    $stmt->execute([$value]);
    return (int) $stmt->fetchColumn();
}

function login_is_locked(PDO $pdo, $ip, $loginName) {
    return login_recent_failures($pdo, 'ip_address', $ip) >= 5
        || login_recent_failures($pdo, 'login_name', $loginName) >= 5;
}

function login_record_failure(PDO $pdo, $ip, $loginName) {
    $pdo->prepare("DELETE FROM login_attempts WHERE attempted_at <= (NOW() - INTERVAL 15 MINUTE)")->execute();
    $pdo->prepare("INSERT INTO login_attempts (ip_address, login_name) VALUES (?, ?)")->execute([$ip, $loginName]);
}

function login_clear_failures(PDO $pdo, $loginName) {
    $pdo->prepare("DELETE FROM login_attempts WHERE login_name = ?")->execute([$loginName]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!auth_verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'The form expired. Please try again.';
    } elseif ($login === '' || $password === '') {
        $error = 'Enter your email and password.';
    } elseif (!$pdo) {
        $error = 'Database connection failed.';
    } else {
        try {
            login_ensure_attempts($pdo);
            $ip = login_client_ip();
            $loginName = login_attempt_name($login);
            if (login_is_locked($pdo, $ip, $loginName)) {
                $error = 'Too many attempts. Try again in a few minutes.';
            } else {
                $user = auth_find_user($pdo, $login);
                $valid = $user && password_verify($password, $user['password']);

                if ($valid && $user) {
                    login_clear_failures($pdo, $loginName);
                    auth_establish_session($user);
                    auth_touch_login($pdo, $user['id']);
                    if ($returnTo !== '') {
                        header('Location: ' . $returnTo);
                        exit;
                    }
                    $role = $user['role'] ?? 'user';
                    $next = auth_after_login_path($role);
                    header('Location: ' . $next);
                    exit;
                }

                login_record_failure($pdo, $ip, $loginName);
                $error = login_is_locked($pdo, $ip, $loginName)
                    ? 'Too many attempts. Try again in a few minutes.'
                    : 'Invalid email or password.';
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
    <title>Log in - Xpress Point Management Services</title>
    <?php $xpm_page_styles = ['pages/account.css']; include 'partials/site-head.php'; ?>
</head>
<body>
    <?php include 'partials/site-nav.php'; ?>
    <main id="main" class="auth-main">
        <div class="card auth-card">
            <?php if ($error): ?>
                <div class="alert alert-error" role="alert">
                    <i class="fas fa-circle-exclamation"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>
            <?php if (isset($_GET['registered'])): ?>
                <div class="alert alert-success" role="status">
                    <i class="fas fa-circle-check"></i>
                    <span>Your account is ready. Log in to continue.</span>
                </div>
            <?php endif; ?>
            <h1>Log in</h1>
            <p class="lead"><?php echo $returnTo !== '' ? 'Log in to continue with the form you opened.' : 'Use the email and password from your XpressPoint account.'; ?></p>
            <form method="POST" class="form">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(auth_csrf_token()); ?>">
                <?php if ($returnTo !== ''): ?>
                    <input type="hidden" name="next" value="<?php echo htmlspecialchars($returnTo); ?>">
                <?php endif; ?>
                <div class="form-group">
                    <label for="login">Email</label>
                    <input type="email" id="login" name="login" class="form-control" value="<?php echo htmlspecialchars($_POST['login'] ?? ''); ?>" autocomplete="username" inputmode="email" autofocus required>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-control" autocomplete="current-password" required>
                </div>
                <button type="submit" class="btn btn-primary btn-lg btn-block">Log in <i class="fas fa-arrow-right"></i></button>
            </form>
            <p class="auth-switch">New here? <a href="register.php<?php echo $returnTo !== '' ? '?next=' . urlencode($returnTo) : ''; ?>">Create an account</a></p>
        </div>
    </main>
    <?php include 'partials/site-footer.php'; ?>
    <script src="assets/js/motion.js?v=20261002-10"></script>
</body>
</html>
