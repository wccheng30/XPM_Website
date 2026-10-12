<?php
require_once 'includes/auth.php';

$account = auth_require_account();
if (!in_array($account['role'], ['admin', 'manager', 'trainer'], true)) {
    header('Location: account.php');
    exit;
}

$workspace = auth_home_path($account['role'], false);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Where next - Xpress Point Management Services</title>
    <?php $xpm_page_styles = ['pages/account.css']; include 'partials/site-head.php'; ?>
</head>
<body>
    <?php include 'partials/site-nav.php'; ?>
    <main id="main" class="auth-main">
        <div class="card auth-card destination-choice">
            <h1>Where do you want to go?</h1>
            <p class="lead">Open the public site, or go to the workspace for your role.</p>
            <a class="btn btn-primary btn-lg btn-block" href="index.html">Main page</a>
            <a class="btn btn-secondary btn-lg btn-block" href="<?php echo htmlspecialchars($workspace); ?>">Your workspace</a>
        </div>
    </main>
    <?php include 'partials/site-footer.php'; ?>
    <script src="assets/js/motion.js?v=20261002-10"></script>
</body>
</html>
