<?php
require_once 'includes/auth.php';

auth_start();
$existing = auth_user();
if ($existing) {
    header('Location: ' . auth_home_path($existing['role'], false));
    exit;
}

$error = '';
$fieldError = '';
$token = (string) ($_POST['token'] ?? $_GET['token'] ?? '');
$token = strtolower(preg_replace('/[^a-f0-9]/', '', $token));
$valid = false;
$reset = null;
$pdo = get_db_connection();

function reset_form_ensure_tokens(PDO $pdo) {
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

if ($pdo && strlen($token) === 64) {
    try {
        reset_form_ensure_tokens($pdo);
        $stmt = $pdo->prepare("SELECT pr.id, pr.user_id FROM password_resets pr WHERE pr.token_hash = ? AND pr.used_at IS NULL AND pr.expires_at > NOW() LIMIT 1");
        $stmt->execute([hash('sha256', $token)]);
        $reset = $stmt->fetch() ?: null;
        $valid = (bool) $reset;
    } catch (PDOException $e) {
        $error = 'Could not check that reset link. Please try again.';
    }
}

if (!$valid && $error === '') {
    $error = 'This reset link is invalid or has expired.';
}

if ($valid && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    if (!auth_verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'The form expired. Please try again.';
    } elseif (strlen($password) < 8) {
        $fieldError = 'Password must be at least 8 characters.';
    } else {
        try {
            $pdo->beginTransaction();
            $mark = $pdo->prepare("UPDATE password_resets SET used_at = NOW() WHERE id = ? AND used_at IS NULL AND expires_at > NOW()");
            $mark->execute([(int) $reset['id']]);
            if ($mark->rowCount() !== 1) {
                $pdo->rollBack();
                $valid = false;
                $error = 'This reset link is invalid or has expired.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hash, (int) $reset['user_id']]);
                $pdo->prepare("UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL")->execute([(int) $reset['user_id']]);
                $pdo->commit();
                header('Location: login.php?reset=1');
                exit;
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'Could not update the password. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Choose a new password - Xpress Point Management Services</title>
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
            <h1>Choose a new password</h1>
            <?php if ($valid): ?>
                <p class="lead">Use at least 8 characters. This link works once.</p>
                <form method="POST" action="reset-password.php" class="form">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(auth_csrf_token()); ?>">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                    <div class="form-group">
                        <label for="password">New password</label>
                        <div class="password-field">
                            <input type="password" id="password" name="password" class="form-control" autocomplete="new-password" minlength="8" aria-describedby="password-hint password-error"<?php echo $fieldError !== '' ? ' aria-invalid="true"' : ''; ?> required>
                            <button type="button" class="password-toggle" aria-label="Show password" aria-pressed="false">
                                <i class="fas fa-eye" aria-hidden="true"></i>
                            </button>
                        </div>
                        <p class="form-hint password-length-hint" id="password-hint" data-for="password">At least 8 characters.</p>
                        <p class="field-error" id="password-error"<?php echo $fieldError === '' ? ' hidden' : ''; ?>><?php echo htmlspecialchars($fieldError); ?></p>
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg btn-block">Update password</button>
                </form>
            <?php else: ?>
                <p class="lead">Request a new link and we will email it if an account matches.</p>
            <?php endif; ?>
            <p class="auth-switch"><a href="forgot-password.php">Forgot password?</a></p>
            </div>
    </main>
    <?php include 'partials/site-footer.php'; ?>
    <script src="assets/js/motion.js?v=20261002-10"></script>
    <script src="assets/js/auth-ui.js?v=20261015-8"></script>
</body>
</html>
