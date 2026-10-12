<?php
require_once 'includes/auth.php';

auth_start();
$returnTo = auth_safe_next($_GET['next'] ?? $_POST['next'] ?? '');
$existing = auth_user();
if ($existing) {
    header('Location: ' . ($returnTo !== '' ? $returnTo : auth_home_path($existing['role'], false)));
    exit;
}

$error = '';
$pdo = get_db_connection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!auth_verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'The form expired. Please try again.';
    } elseif ($name === '' || $email === '' || $password === '' || $confirm === '') {
        $error = 'Fill in your name, email, and password.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/\A[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}\z/i', $email)) {
        $error = 'Register with a valid email address. That email is your login.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif (!$pdo) {
        $error = 'Database connection failed.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR username = ? LIMIT 1");
            $stmt->execute([$email, $email]);
            if ($stmt->fetch()) {
                $error = 'An account with that email already exists.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, password, email, display_name, role, created_at) VALUES (?, ?, ?, ?, 'user', NOW())");
                $stmt->execute([$email, $hash, $email, $name]);
                $loginNext = 'login.php?registered=1';
                if ($returnTo !== '') {
                    $loginNext .= '&next=' . rawurlencode($returnTo);
                }
                header('Location: ' . $loginNext);
                exit;
            }
        } catch (PDOException $e) {
            $error = 'Could not create the account. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create account - Xpress Point Management Services</title>
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
            <h1>Create an account</h1>
            <p class="lead">Use a real email address. It becomes the login for this account. New accounts start as a member.</p>
            <form method="POST" class="form">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(auth_csrf_token()); ?>">
                <?php if ($returnTo !== ''): ?>
                    <input type="hidden" name="next" value="<?php echo htmlspecialchars($returnTo); ?>">
                <?php endif; ?>
                <div class="form-group">
                    <label for="name">Full name</label>
                    <input type="text" id="name" name="name" class="form-control" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" autocomplete="name" required>
                </div>
                <div class="form-group">
                    <label for="email">Email address</label>
                    <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" autocomplete="email" inputmode="email" pattern="^[^\s@]+@[^\s@]+\.[^\s@]+$" title="Enter a valid email address" required>
                    <p class="form-hint">Use your email address. It becomes your login.</p>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-control" autocomplete="new-password" minlength="8" required>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm password</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" autocomplete="new-password" minlength="8" required>
                </div>
                <button type="submit" class="btn btn-primary btn-lg btn-block">Create account <i class="fas fa-arrow-right"></i></button>
            </form>
            <p class="auth-switch">Already have an account? <a href="login.php<?php echo $returnTo !== '' ? '?next=' . urlencode($returnTo) : ''; ?>">Log in</a></p>
        </div>
    </main>
    <?php include 'partials/site-footer.php'; ?>
    <script src="assets/js/motion.js?v=20261002-10"></script>
</body>
</html>
