<?php
require_once 'includes/auth.php';

$account = auth_require_account();
if (!in_array($account['role'], ['admin', 'manager', 'trainer'], true)) {
    header('Location: account.php');
    exit;
}

$workspace = auth_home_path($account['role'], false);
$destinations = [
    'workspace' => $workspace,
    'site' => 'index.html',
];
$mustConfirm = $account['role'] === 'admin';
$error = '';
$selected = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $choice = (string) ($_POST['choice'] ?? '');
    $remember = !$mustConfirm && (($_POST['remember'] ?? '') === '1');
    if (!auth_verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'The form expired. Please try again.';
    } elseif (!isset($destinations[$choice])) {
        $error = 'Choose a destination.';
    } else {
        if ($remember) {
            auth_remember_staff_landing($choice);
        }
        header('Location: ' . $destinations[$choice], true, 303);
        exit;
    }
    if (isset($destinations[$choice])) {
        $selected = $choice;
    }
}

$displayName = trim((string) $account['name']);
if ($displayName === '') {
    $displayName = trim((string) ($account['username'] ?? ''));
}
$roleLabels = [
    'admin' => 'Admin',
    'manager' => 'Manager',
    'trainer' => 'Trainer',
];
$roleLabel = $roleLabels[$account['role']] ?? '';
$roleBadgeClass = 'badge';
if ($account['role'] === 'admin') {
    $roleBadgeClass = 'badge badge-dark';
} elseif ($account['role'] === 'trainer') {
    $roleBadgeClass = 'badge badge-success';
} elseif ($account['role'] === 'manager') {
    $roleBadgeClass = 'badge badge-warning';
}
$workspaceDetail = $account['role'] === 'trainer'
    ? 'Your programmes.'
    : 'Bookings, programmes and users.';
$avatar = str_replace('\\', '/', (string) ($account['avatar'] ?? ''));
$showAvatar = $avatar !== '' && preg_match('#^assets/images/avatars/[A-Za-z0-9._-]+$#', $avatar) === 1;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Where do you want to go? - Xpress Point Management Services</title>
    <?php $xpm_page_styles = ['pages/account.css']; include 'partials/site-head.php'; ?>
</head>
<body>
    <?php include 'partials/site-nav.php'; ?>
    <main id="main" class="auth-main continue-page">
        <div class="continue-panel">
            <div class="continue-greet">
                <span class="nav-avatar" aria-hidden="true">
                    <?php if ($showAvatar): ?>
                        <img src="<?php echo htmlspecialchars($avatar); ?>" alt="">
                    <?php else: ?>
                        <span><?php echo htmlspecialchars(auth_initial($displayName)); ?></span>
                    <?php endif; ?>
                </span>
                <div class="continue-greet-copy">
                    <p class="continue-welcome">Welcome back<?php if ($displayName !== ''): ?>, <?php echo htmlspecialchars($displayName); ?><?php endif; ?></p>
                    <?php if ($roleLabel !== ''): ?>
                        <span class="<?php echo $roleBadgeClass; ?>"><?php echo htmlspecialchars($roleLabel); ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <h1 id="continue-heading">Where do you want to go?</h1>
            <p class="lead">Open your workspace, or the public website.</p>
            <?php if ($error !== ''): ?>
                <div class="alert alert-error" role="alert">
                    <i class="fas fa-circle-exclamation" aria-hidden="true"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php endif; ?>
            <form method="POST" action="continue.php" class="continue-form">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(auth_csrf_token()); ?>">
                <div class="continue-cards" role="radiogroup" aria-labelledby="continue-heading">
                    <label class="continue-choice<?php echo $selected === 'workspace' ? ' is-selected' : ''; ?>">
                        <input type="radio" name="choice" value="workspace"<?php echo $selected === 'workspace' ? ' checked' : ''; ?> required>
                        <span class="icon-box" aria-hidden="true"><i class="fas fa-door-open"></i></span>
                        <span class="continue-choice-title">Your workspace</span>
                        <span class="continue-choice-desc"><?php echo htmlspecialchars($workspaceDetail); ?></span>
                    </label>
                    <label class="continue-choice<?php echo $selected === 'site' ? ' is-selected' : ''; ?>">
                        <input type="radio" name="choice" value="site"<?php echo $selected === 'site' ? ' checked' : ''; ?> required>
                        <span class="icon-box" aria-hidden="true"><i class="fas fa-globe"></i></span>
                        <span class="continue-choice-title">Public website</span>
                        <span class="continue-choice-desc">The pages visitors see.</span>
                    </label>
                </div>
                <?php if (!$mustConfirm): ?>
                    <label class="continue-remember" for="remember-choice">
                        <input type="checkbox" id="remember-choice" name="remember" value="1">
                        <span>Remember my choice</span>
                    </label>
                <?php endif; ?>
                <div class="continue-actions">
                    <button type="submit" class="btn btn-primary btn-lg">Confirm</button>
                </div>
            </form>
        </div>
    </main>
    <?php include 'partials/site-footer.php'; ?>
    <script src="assets/js/motion.js?v=20261030-4"></script>
</body>
</html>
