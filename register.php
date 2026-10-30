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
$fieldErrors = [
    'name' => '',
    'email' => '',
    'password' => '',
];
$pdo = get_db_connection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $emailValid = $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) && preg_match('/\A[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}\z/i', $email);

    if (!auth_verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'The form expired. Please try again.';
    } else {
        if ($name === '') {
            $fieldErrors['name'] = 'Enter your name.';
        }
        if (!$emailValid) {
            $fieldErrors['email'] = 'Register with a valid email address. That email is your login.';
        }
        if (strlen($password) < 8) {
            $fieldErrors['password'] = 'Password must be at least 8 characters.';
        }
        if ($error === '' && $fieldErrors['name'] === '' && $fieldErrors['email'] === '' && $fieldErrors['password'] === '') {
            if (!$pdo) {
                $error = 'Database connection failed.';
            } else {
                try {
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR username = ? LIMIT 1");
                    $stmt->execute([$email, $email]);
                    if ($stmt->fetch()) {
                        $fieldErrors['email'] = 'An account with that email already exists.';
                    } else {
                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("INSERT INTO users (username, password, email, display_name, role, created_at) VALUES (?, ?, ?, ?, 'user', NOW())");
                        $stmt->execute([$email, $hash, $email, $name]);
                        $userId = (int) $pdo->lastInsertId();
                        auth_establish_session([
                            'id' => $userId,
                            'role' => 'user',
                            'email' => $email,
                            'display_name' => $name,
                            'username' => $email,
                            'avatar_path' => '',
                        ]);
                        auth_touch_login($pdo, $userId);
                        header('Location: ' . ($returnTo !== '' ? $returnTo : 'account.php'));
                        exit;
                    }
                } catch (PDOException $e) {
                    $error = 'Could not create the account. Please try again.';
                }
            }
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
            <h1>Register</h1>
            <p class="lead">Your email is your login.</p>
            <form method="POST" class="form" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(auth_csrf_token()); ?>">
                <?php if ($returnTo !== ''): ?>
                    <input type="hidden" name="next" value="<?php echo htmlspecialchars($returnTo); ?>">
                <?php endif; ?>
                <div class="form-group">
                    <label for="name">Full name</label>
                    <input type="text" id="name" name="name" class="form-control" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" autocomplete="name" aria-describedby="name-error"<?php echo $fieldErrors['name'] !== '' ? ' aria-invalid="true"' : ''; ?> required>
                    <p class="field-error" id="name-error"<?php echo $fieldErrors['name'] === '' ? ' hidden' : ''; ?>><?php echo htmlspecialchars($fieldErrors['name']); ?></p>
                </div>
                <div class="form-group">
                    <label for="email">Email address</label>
                    <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" autocomplete="email" inputmode="email" aria-describedby="email-error"<?php echo $fieldErrors['email'] !== '' ? ' aria-invalid="true"' : ''; ?> required>
                    <p class="field-error" id="email-error"<?php echo $fieldErrors['email'] === '' ? ' hidden' : ''; ?>><?php echo htmlspecialchars($fieldErrors['email']); ?></p>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="password-field">
                        <input type="password" id="password" name="password" class="form-control" autocomplete="new-password" minlength="8" aria-describedby="password-hint password-error"<?php echo $fieldErrors['password'] !== '' ? ' aria-invalid="true"' : ''; ?> required>
                        <button type="button" class="password-toggle" aria-label="Show password" aria-pressed="false">
                            <i class="fas fa-eye" aria-hidden="true"></i>
                        </button>
                    </div>
                    <p class="form-hint password-length-hint" id="password-hint" data-for="password">At least 8 characters.</p>
                    <p class="field-error" id="password-error"<?php echo $fieldErrors['password'] === '' ? ' hidden' : ''; ?>><?php echo htmlspecialchars($fieldErrors['password']); ?></p>
                </div>
                <p class="auth-consent">By creating an account, you agree to our <a href="terms-of-service.php" target="_blank" rel="noopener">Terms of Service</a> and <a href="privacy-policy.php" target="_blank" rel="noopener">Privacy Policy</a>.</p>
                <button type="submit" class="btn btn-primary btn-lg btn-block">Register <i class="fas fa-arrow-right"></i></button>
            </form>
            <p class="auth-switch">Already registered? <a href="login.php<?php echo $returnTo !== '' ? '?next=' . urlencode($returnTo) : ''; ?>">Log in</a></p>
            </div>
    </main>
    <?php include 'partials/site-footer.php'; ?>
    <script src="assets/js/motion.js?v=20261030-4"></script>
    <script src="assets/js/auth-ui.js?v=20261015-8"></script>
</body>
</html>
