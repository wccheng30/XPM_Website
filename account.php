<?php
require_once 'includes/auth.php';

function xpm_square_avatar($path, $mime) {
    if (!function_exists('imagecreatetruecolor')) {
        return;
    }
    if ($mime === 'image/jpeg') {
        $src = @imagecreatefromjpeg($path);
    } elseif ($mime === 'image/png') {
        $src = @imagecreatefrompng($path);
    } elseif ($mime === 'image/gif') {
        $src = @imagecreatefromgif($path);
    } elseif ($mime === 'image/webp' && function_exists('imagecreatefromwebp')) {
        $src = @imagecreatefromwebp($path);
    } else {
        $src = false;
    }
    if (!$src) {
        return;
    }
    $width = imagesx($src);
    $height = imagesy($src);
    $side = min($width, $height);
    $cropX = (int) (($width - $side) / 2);
    $cropY = (int) (($height - $side) / 2);
    $dest = imagecreatetruecolor(256, 256);
    imagealphablending($dest, false);
    imagesavealpha($dest, true);
    imagecopyresampled($dest, $src, 0, 0, $cropX, $cropY, 256, 256, $side, $side);
    if ($mime === 'image/png') {
        imagepng($dest, $path);
    } elseif ($mime === 'image/gif') {
        imagegif($dest, $path);
    } elseif ($mime === 'image/webp' && function_exists('imagewebp')) {
        imagewebp($dest, $path, 85);
    } else {
        imagejpeg($dest, $path, 85);
    }
    imagedestroy($src);
    imagedestroy($dest);
}

function xpm_account_done($message) {
    $_SESSION['flash'] = ['type' => 'success', 'message' => $message];
    header('Location: account.php', true, 303);
    exit;
}


$account = auth_require_account();
$pdo = get_db_connection();
$cardError = [
    'avatar' => '',
    'profile' => '',
    'security' => '',
    'shortcuts' => '',
];
$nameValue = $account['name'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'avatar') {
    if (!auth_verify_csrf($_POST['csrf_token'] ?? '')) {
        $cardError['avatar'] = 'The form expired. Please try again.';
    } elseif (!$pdo) {
        $cardError['avatar'] = 'Database connection failed.';
    } elseif (empty($_FILES['avatar']['name'])) {
        $cardError['avatar'] = 'Choose a photo to upload.';
    } else {
        $file = $_FILES['avatar'];
        $allowed = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
        ];
        $type = '';
        if (is_uploaded_file($file['tmp_name'])) {
            $info = @getimagesize($file['tmp_name']);
            $type = $info['mime'] ?? '';
        }
        if ($file['error'] !== UPLOAD_ERR_OK || !isset($allowed[$type])) {
            $cardError['avatar'] = 'Upload a JPG, PNG, GIF, or WebP photo.';
        } elseif ($file['size'] > 5 * 1024 * 1024) {
            $cardError['avatar'] = 'Photo must be 5 MB or smaller.';
        } else {
            $dir = __DIR__ . '/assets/images/avatars';
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $filename = 'user-' . (int) $account['id'] . '-' . bin2hex(random_bytes(4)) . '.' . $allowed[$type];
            $dest = $dir . '/' . $filename;
            if (!move_uploaded_file($file['tmp_name'], $dest)) {
                $cardError['avatar'] = 'Could not save the photo. Please try again.';
            } else {
                xpm_square_avatar($dest, $type);
                $relative = 'assets/images/avatars/' . $filename;
                $stmt = $pdo->prepare("UPDATE users SET avatar_path = ? WHERE id = ?");
                $stmt->execute([$relative, (int) $account['id']]);
                if (!empty($account['avatar']) && strpos($account['avatar'], 'assets/images/avatars/') === 0) {
                    $old = __DIR__ . '/' . $account['avatar'];
                    if (is_file($old)) {
                        @unlink($old);
                    }
                }
                $_SESSION['user_avatar'] = $relative;
                $account['avatar'] = $relative;
                xpm_account_done('Profile photo updated.');
            }
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'name') {
    $name = trim($_POST['display_name'] ?? '');
    $nameValue = $name;
    if (!auth_verify_csrf($_POST['csrf_token'] ?? '')) {
        $cardError['profile'] = 'The form expired. Please try again.';
    } elseif ($name === '') {
        $cardError['profile'] = 'Enter your name.';
    } elseif (strlen($name) > 100) {
        $cardError['profile'] = 'Name must be 100 characters or fewer.';
    } elseif (!$pdo) {
        $cardError['profile'] = 'Database connection failed.';
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE users SET display_name = ? WHERE id = ?");
            $stmt->execute([$name, (int) $account['id']]);
            if ($account['role'] === 'trainer') {
                $pdo->prepare("UPDATE trainers SET name = ? WHERE user_id = ?")->execute([$name, (int) $account['id']]);
            }
            $_SESSION['user_name'] = $name;
            $account['name'] = $name;
            xpm_account_done('Name updated.');
        } catch (PDOException $e) {
            $cardError['profile'] = 'Could not update your name. Please try again.';
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'forget-landing') {
    if (!auth_verify_csrf($_POST['csrf_token'] ?? '')) {
        $cardError['shortcuts'] = 'The form expired. Please try again.';
    } elseif (!in_array($account['role'], ['admin', 'manager', 'trainer'], true)) {
        $cardError['shortcuts'] = 'That action is not available for this account.';
    } else {
        auth_clear_staff_landing();
        xpm_account_done('Landing choice forgotten.');
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!auth_verify_csrf($_POST['csrf_token'] ?? '')) {
        $cardError['security'] = 'The form expired. Please try again.';
    } elseif ($current === '' || $new === '' || $confirm === '') {
        $cardError['security'] = 'Fill in every password field.';
    } elseif ($new !== $confirm) {
        $cardError['security'] = 'New passwords do not match.';
    } elseif (strlen($new) < 8) {
        $cardError['security'] = 'New password must be at least 8 characters.';
    } elseif (!$pdo) {
        $cardError['security'] = 'Database connection failed.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([(int) $account['id']]);
            $row = $stmt->fetch();
            $matches = $row && password_verify($current, $row['password']);
            if (!$matches) {
                $cardError['security'] = 'Current password is incorrect.';
            } else {
                $hash = password_hash($new, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$hash, (int) $account['id']]);
                xpm_account_done('Password updated.');
            }
        } catch (PDOException $e) {
            $cardError['security'] = 'Could not update the password. Please try again.';
        }
    }
}

$flash = null;
if (!empty($_SESSION['flash']) && is_array($_SESSION['flash'])) {
    $flashType = (($_SESSION['flash']['type'] ?? '') === 'error') ? 'error' : 'success';
    $flashMessage = trim((string) ($_SESSION['flash']['message'] ?? ''));
    unset($_SESSION['flash']);
    if ($flashMessage !== '') {
        $flash = ['type' => $flashType, 'message' => $flashMessage];
    }
}

$memberSince = '';
if ($pdo) {
    try {
        $createdStmt = $pdo->prepare("SELECT created_at FROM users WHERE id = ?");
        $createdStmt->execute([(int) $account['id']]);
        $createdAt = $createdStmt->fetchColumn();
        $createdTime = $createdAt ? strtotime((string) $createdAt) : false;
        if ($createdTime) {
            $memberSince = date('F j, Y', $createdTime);
        }
    } catch (PDOException $e) {
        $memberSince = '';
    }
}

$isStaff = in_array($account['role'], ['admin', 'manager', 'trainer'], true);
$workspace = $isStaff ? auth_home_path($account['role'], false) : '';
$hasLandingChoice = $isStaff && auth_staff_landing_value() !== '';
$roleLabels = [
    'admin' => 'Admin',
    'manager' => 'Manager',
    'trainer' => 'Trainer',
    'user' => 'Member',
];
$roleLabel = $roleLabels[$account['role']] ?? ucfirst((string) $account['role']);
$roleBadgeClass = 'badge';
if ($account['role'] === 'admin') {
    $roleBadgeClass = 'badge badge-dark';
} elseif ($account['role'] === 'trainer') {
    $roleBadgeClass = 'badge badge-success';
} elseif ($account['role'] === 'manager') {
    $roleBadgeClass = 'badge badge-warning';
}
$displayName = $account['name'] !== '' ? $account['name'] : 'Your account';
$emailLabel = $account['email'] !== '' ? $account['email'] : '—';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your account - Xpress Point Management Services</title>
    <?php $xpm_page_styles = ['pages/account.css']; include 'partials/site-head.php'; ?>
</head>
<body>
    <?php include 'partials/site-nav.php'; ?>
    <main id="main" class="account-page">
        <div class="account-wrap">
            <div class="account-hero-block">
            <header class="account-hero">
                <div class="account-identity">
                    <form method="POST" enctype="multipart/form-data" class="account-avatar-form" id="avatar-form">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(auth_csrf_token()); ?>">
                        <input type="hidden" name="form" value="avatar">
                        <label class="sr-only" for="avatar">Profile photo</label>
                        <input type="file" id="avatar" name="avatar" class="account-file" accept="image/jpeg,image/png,image/gif,image/webp" tabindex="-1" required>
                        <button type="button" class="account-avatar" id="avatar-pick" aria-label="Change profile photo">
                            <span class="account-avatar-face" id="avatar-face">
                                <?php if (!empty($account['avatar'])): ?>
                                    <img src="<?php echo htmlspecialchars($account['avatar']); ?>" alt="">
                                <?php else: ?>
                                    <span><?php echo htmlspecialchars(auth_initial($account['name'])); ?></span>
                                <?php endif; ?>
                            </span>
                            <span class="account-avatar-camera" aria-hidden="true"><i class="fas fa-camera"></i></span>
                        </button>
                        <div class="account-avatar-actions" id="avatar-actions" hidden>
                            <p class="form-hint" id="avatar-file-name"></p>
                            <div class="account-avatar-buttons">
                                <button type="submit" class="btn btn-primary">Save photo</button>
                                <button type="button" class="btn btn-ghost" id="avatar-cancel">Cancel</button>
                            </div>
                        </div>
                    </form>
                    <div class="account-id-copy">
                        <h1><?php echo htmlspecialchars($displayName); ?></h1>
                        <p class="account-id-email"><?php echo htmlspecialchars($emailLabel); ?></p>
                        <p class="account-id-meta">
                            <span class="<?php echo $roleBadgeClass; ?>"><?php echo htmlspecialchars($roleLabel); ?></span>
                            <?php if ($memberSince !== ''): ?>
                                <span>Member since <?php echo htmlspecialchars($memberSince); ?></span>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
                <a class="btn btn-ghost account-logout" href="logout.php">Log out</a>
            </header>
            <?php if ($cardError['avatar'] !== ''): ?>
                <div class="alert alert-error" role="alert">
                    <i class="fas fa-circle-exclamation" aria-hidden="true"></i>
                    <span><?php echo htmlspecialchars($cardError['avatar']); ?></span>
                </div>
            <?php endif; ?>
            </div>

            <div class="account-layout">
                <nav class="account-nav" aria-label="Account sections">
                    <a href="#profile" aria-current="true">Profile</a>
                    <a href="#security">Security</a>
                    <a href="#shortcuts">Shortcuts</a>
                </nav>
                <div class="account-content">
                    <section id="profile" class="card account-card" tabindex="-1">
                        <h2>Profile</h2>
                        <?php if ($cardError['profile'] !== ''): ?>
                            <div class="alert alert-error" role="alert">
                                <i class="fas fa-circle-exclamation" aria-hidden="true"></i>
                                <span><?php echo htmlspecialchars($cardError['profile']); ?></span>
                            </div>
                        <?php endif; ?>
                        <form method="POST" class="form" id="name-form">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(auth_csrf_token()); ?>">
                            <input type="hidden" name="form" value="name">
                            <div class="account-name-line">
                                <div class="form-group">
                                    <label for="display_name">Name</label>
                                    <input type="text" id="display_name" name="display_name" class="form-control" value="<?php echo htmlspecialchars($nameValue); ?>" data-original="<?php echo htmlspecialchars($account['name']); ?>" autocomplete="name" maxlength="100" required>
                                </div>
                                <button type="submit" class="btn btn-primary" id="save-name" disabled>Save changes</button>
                            </div>
                        </form>
                        <div class="form-group account-email-block">
                            <label for="account-email">Email</label>
                            <input type="email" id="account-email" class="form-control" value="<?php echo htmlspecialchars($emailLabel); ?>" readonly aria-readonly="true">
                            <p class="form-hint">To change your email, <a href="index.html#contact">contact us</a>.</p>
                        </div>
                    </section>

                    <section id="security" class="card account-card" tabindex="-1">
                        <h2>Security</h2>
                        <?php if ($cardError['security'] !== ''): ?>
                            <div class="alert alert-error" role="alert">
                                <i class="fas fa-circle-exclamation" aria-hidden="true"></i>
                                <span><?php echo htmlspecialchars($cardError['security']); ?></span>
                            </div>
                        <?php endif; ?>
                        <form method="POST" class="form" id="password-form">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(auth_csrf_token()); ?>">
                            <input type="hidden" name="form" value="password">
                            <div class="form-group">
                                <label for="current_password">Current password</label>
                                <div class="password-field">
                                    <input type="password" id="current_password" name="current_password" class="form-control" autocomplete="current-password" required>
                                    <button type="button" class="password-toggle" aria-label="Show password" aria-pressed="false">
                                        <i class="fas fa-eye" aria-hidden="true"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="new_password">New password</label>
                                <div class="password-field">
                                    <input type="password" id="new_password" name="new_password" class="form-control" autocomplete="new-password" minlength="8" aria-describedby="new-password-hint" required>
                                    <button type="button" class="password-toggle" aria-label="Show password" aria-pressed="false">
                                        <i class="fas fa-eye" aria-hidden="true"></i>
                                    </button>
                                </div>
                                <p class="form-hint" id="new-password-hint">At least 8 characters.</p>
                            </div>
                            <div class="form-group">
                                <label for="confirm_password">Confirm new password</label>
                                <div class="password-field">
                                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" autocomplete="new-password" minlength="8" required>
                                    <button type="button" class="password-toggle" aria-label="Show password" aria-pressed="false">
                                        <i class="fas fa-eye" aria-hidden="true"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="account-actions">
                                <button type="submit" class="btn btn-primary">Update password</button>
                            </div>
                        </form>
                    </section>

                    <section id="shortcuts" class="card account-card" tabindex="-1">
                        <h2>Shortcuts</h2>
                        <?php if ($cardError['shortcuts'] !== ''): ?>
                            <div class="alert alert-error" role="alert">
                                <i class="fas fa-circle-exclamation" aria-hidden="true"></i>
                                <span><?php echo htmlspecialchars($cardError['shortcuts']); ?></span>
                            </div>
                        <?php endif; ?>
                        <ul class="account-links">
                            <li>
                                <a href="my-bookings.php">
                                    <span class="icon-box icon-box-sm" aria-hidden="true"><i class="fas fa-calendar-check"></i></span>
                                    <span>My bookings</span>
                                </a>
                            </li>
                            <li>
                                <a href="programmes.php">
                                    <span class="icon-box icon-box-sm" aria-hidden="true"><i class="fas fa-user-graduate"></i></span>
                                    <span>Programmes</span>
                                </a>
                            </li>
                            <li>
                                <a href="jobs.php">
                                    <span class="icon-box icon-box-sm" aria-hidden="true"><i class="fas fa-briefcase"></i></span>
                                    <span>Jobs</span>
                                </a>
                            </li>
                            <?php if ($workspace !== ''): ?>
                                <li>
                                    <a href="<?php echo htmlspecialchars($workspace); ?>">
                                        <span class="icon-box icon-box-sm" aria-hidden="true"><i class="fas fa-door-open"></i></span>
                                        <span>Open your workspace</span>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                        <?php if ($hasLandingChoice): ?>
                            <form method="POST" class="account-forget">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(auth_csrf_token()); ?>">
                                <input type="hidden" name="form" value="forget-landing">
                                <button type="submit" class="account-forget-link">Forget my choice</button>
                            </form>
                        <?php endif; ?>
                    </section>
                </div>
            </div>
        </div>
        <?php if ($flash): ?>
            <div class="account-toast<?php echo $flash['type'] === 'error' ? ' account-toast-error' : ''; ?>" role="status"<?php echo $flash['type'] === 'error' ? '' : ' data-autohide="5000"'; ?>>
                <i class="fas <?php echo $flash['type'] === 'error' ? 'fa-circle-exclamation' : 'fa-circle-check'; ?>" aria-hidden="true"></i>
                <span><?php echo htmlspecialchars($flash['message']); ?></span>
                <button type="button" class="account-toast-close" aria-label="Dismiss"><i class="fas fa-xmark" aria-hidden="true"></i></button>
            </div>
        <?php endif; ?>
    </main>
    <?php include 'partials/site-footer.php'; ?>
    <script src="assets/js/motion.js?v=20261030-4"></script>
    <script src="assets/js/auth-ui.js?v=20261015-3"></script>
    <script src="assets/js/account.js?v=20261015-3"></script>
</body>
</html>
