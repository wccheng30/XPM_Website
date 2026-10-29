<?php
/**
 * Shared login session for public accounts and the admin area.
 */

require_once __DIR__ . '/database.php';

function auth_start() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443)
        || (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
    $path = ini_get('session.cookie_path') ?: '/';

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_secure', $https ? '1' : '0');
    ini_set('session.cookie_samesite', 'Lax');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => $path,
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function auth_user() {
    auth_start();
    $role = $_SESSION['user_role'] ?? ($_SESSION['admin_role'] ?? '');
    $logged_in = !empty($_SESSION['user_id']) || (!empty($_SESSION['admin_logged_in']) && $role !== '');
    if (!$logged_in) {
        return null;
    }

    $avatar = $_SESSION['user_avatar'] ?? '';
    if ($avatar === '' && !empty($_SESSION['user_id'])) {
        $pdo = get_db_connection();
        if ($pdo) {
            $stmt = $pdo->prepare("SELECT avatar_path FROM users WHERE id = ?");
            $stmt->execute([(int) $_SESSION['user_id']]);
            $avatar = (string) ($stmt->fetchColumn() ?: '');
            $_SESSION['user_avatar'] = $avatar;
        }
    }

    return [
        'id' => isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null,
        'role' => $role,
        'name' => $_SESSION['user_name'] ?? ($_SESSION['admin_username'] ?? ''),
        'email' => $_SESSION['user_email'] ?? '',
        'username' => $_SESSION['admin_username'] ?? '',
        'avatar' => $avatar,
    ];
}

function auth_initial($name) {
    $name = trim((string) $name);
    if ($name === '') {
        return '?';
    }
    $letter = function_exists('mb_substr') ? mb_substr($name, 0, 1) : substr($name, 0, 1);
    return function_exists('mb_strtoupper') ? mb_strtoupper($letter) : strtoupper($letter);
}

function auth_role() {
    $user = auth_user();
    return $user['role'] ?? '';
}

function auth_is_trainer() {
    return auth_role() === 'trainer';
}

function auth_can_change_booking(array $user, $ownerUserId, $programmeId) {
    if (in_array($user['role'] ?? '', ['admin', 'manager'], true)) {
        return true;
    }
    if (($user['role'] ?? '') !== 'trainer') {
        return false;
    }
    return $programmeId && (int) $ownerUserId === (int) ($user['id'] ?? 0);
}

function auth_establish_session(array $user) {
    auth_start();
    session_regenerate_id(true);

    $role = $user['role'] ?? 'user';
    $name = trim((string) ($user['display_name'] ?? ''));
    if ($name === '') {
        $name = $user['username'] ?? '';
    }

    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['user_role'] = $role;
    $_SESSION['user_email'] = $user['email'] ?? '';
    $_SESSION['user_name'] = $name;
    $_SESSION['user_avatar'] = $user['avatar_path'] ?? '';
    $_SESSION['admin_username'] = $user['username'] ?? '';
    $_SESSION['admin_role'] = $role;
    $_SESSION['admin_logged_in'] = in_array($role, ['admin', 'manager', 'trainer'], true);
}

function auth_touch_login(PDO $pdo, $userId) {
    if (!$userId) {
        return;
    }
    $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
    $stmt->execute([(int) $userId]);
}

function auth_find_user(PDO $pdo, $login) {
    $login = trim((string) $login);
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1");
    $stmt->execute([$login, $login]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function auth_home_path($role, $fromAdmin = false) {
    if ($role === 'trainer') {
        return $fromAdmin ? 'programmes.php' : 'admin/programmes.php';
    }
    if ($role === 'admin' || $role === 'manager') {
        return $fromAdmin ? 'dashboard.php' : 'admin/dashboard.php';
    }
    return $fromAdmin ? '../account.php' : 'account.php';
}

function auth_request_https() {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443)
        || (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https');
}

function auth_app_cookie_paths() {
    $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    $paths = ['/'];
    if ($dir !== '/' && $dir !== '.' && $dir !== '') {
        $paths[] = rtrim($dir, '/') . '/';
        $paths[] = rtrim($dir, '/');
    }
    return array_values(array_unique($paths));
}

function auth_staff_landing_value() {
    $value = (string) ($_COOKIE['xpm_staff_landing'] ?? '');
    return ($value === 'workspace' || $value === 'site') ? $value : '';
}

function auth_staff_landing_target($role) {
    $choice = auth_staff_landing_value();
    if ($choice === 'workspace') {
        return auth_home_path($role, false);
    }
    if ($choice === 'site') {
        return 'index.html';
    }
    return '';
}

function auth_after_login_path($role) {
    if ($role === 'admin') {
        return 'continue.php';
    }
    if ($role === 'manager' || $role === 'trainer') {
        $remembered = auth_staff_landing_target($role);
        return $remembered !== '' ? $remembered : 'continue.php';
    }
    return auth_home_path($role, false);
}

function auth_remember_staff_landing($choice) {
    if ($choice !== 'workspace' && $choice !== 'site') {
        return false;
    }
    $path = '/';
    foreach (auth_app_cookie_paths() as $candidate) {
        if ($candidate !== '/') {
            $path = rtrim($candidate, '/') . '/';
            break;
        }
    }
    setcookie('xpm_staff_landing', $choice, [
        'expires' => time() + 31536000,
        'path' => $path,
        'secure' => auth_request_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    $_COOKIE['xpm_staff_landing'] = $choice;
    return true;
}

function auth_clear_staff_landing() {
    $https = auth_request_https();
    foreach (auth_app_cookie_paths() as $path) {
        setcookie('xpm_staff_landing', '', [
            'expires' => time() - 42000,
            'path' => $path,
            'secure' => $https,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
    unset($_COOKIE['xpm_staff_landing']);
}

function auth_require(array $roles, $deny = 'redirect') {
    auth_start();
    $user = auth_user();
    if (!$user) {
        if ($deny === '403') {
            http_response_code(403);
            exit('Unauthorized');
        }
        header('Location: ../login.php');
        exit;
    }

    if (!in_array($user['role'], $roles, true)) {
        if ($deny === '403') {
            http_response_code(403);
            exit('Unauthorized');
        }
        header('Location: ' . auth_home_path($user['role'], true));
        exit;
    }
}

function auth_safe_next($next) {
    $next = str_replace(["\r", "\n", "\0"], '', trim((string) $next));
    if ($next === '' || preg_match('/^[a-z][a-z0-9+\-.]*:/i', $next) || strpos($next, '//') === 0 || strpos($next, '\\') !== false || strpos($next, '..') !== false) {
        return '';
    }
    if (!preg_match('/\A(?:index\.html#contact|job-application\.php\?job_id=\d+|programme-registration\.php\?programme_id=\d+)\z/', $next)) {
        return '';
    }
    return $next;
}

function auth_login_next_from_request() {
    $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
    $query = $_SERVER['QUERY_STRING'] ?? '';
    return auth_safe_next($script . ($query !== '' ? '?' . $query : ''));
}

function auth_guest_banner($detail, $next) {
    $next = auth_safe_next($next);
    $login = 'login.php' . ($next !== '' ? '?next=' . rawurlencode($next) : '');
    $register = 'register.php' . ($next !== '' ? '?next=' . rawurlencode($next) : '');
    echo '<div class="guest-gate" role="status">';
    echo '<div class="guest-gate-icon" aria-hidden="true"><i class="fas fa-lock"></i></div>';
    echo '<div>';
    echo '<strong>Log in or create an account first.</strong>';
    echo '<p>' . htmlspecialchars($detail) . '</p>';
    echo '<div class="guest-gate-actions">';
    echo '<a class="btn btn-primary" href="' . htmlspecialchars($login) . '">Log in</a>';
    echo '<a class="btn btn-outline" href="' . htmlspecialchars($register) . '">Create an account</a>';
    echo '</div></div></div>';
}

function auth_require_login() {
    auth_start();
    $user = auth_user();
    if ($user && !empty($user['id'])) {
        return $user;
    }
    $next = auth_login_next_from_request();
    $location = 'login.php';
    if ($next !== '') {
        $location .= '?next=' . rawurlencode($next);
    }
    header('Location: ' . $location);
    exit;
}

function auth_require_account() {
    auth_start();
    $user = auth_user();
    if (!$user || empty($user['id'])) {
        header('Location: login.php');
        exit;
    }
    return $user;
}

function auth_csrf_token() {
    auth_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function auth_verify_csrf($token) {
    auth_start();
    return is_string($token) && isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(auth_csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function csrf_verify($token = null) {
    if ($token === null) {
        $token = $_POST['csrf_token'] ?? '';
    }
    return auth_verify_csrf($token);
}

function csrf_guard() {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        return;
    }
    if (!csrf_verify()) {
        http_response_code(403);
        exit('Invalid request.');
    }
}

function auth_remove_managed_image($relative) {
    $relative = str_replace('\\', '/', (string) $relative);
    if (!preg_match('#^assets/images/(logos|trainers|programmes|testimonials|clients)/[A-Za-z0-9._-]+$#', $relative)) {
        return;
    }
    $full = dirname(__DIR__) . '/' . $relative;
    if (is_file($full)) {
        unlink($full);
    }
}

function auth_deny_script_execution($directory) {
    $htaccess = rtrim((string) $directory, "/\\") . DIRECTORY_SEPARATOR . '.htaccess';
    if (is_file($htaccess)) {
        return;
    }
    $rules = "<FilesMatch \"\\.(php|phtml|php3|php4|php5|phar|cgi|pl|asp|aspx)$\">\n    Require all denied\n</FilesMatch>\n";
    @file_put_contents($htaccess, $rules);
}

function auth_save_image(array $file, $relativeDir, $prefix = 'upload') {
    $maxBytes = 5 * 1024 * 1024;
    $allowed = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
    ];
    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_NO_FILE) {
        return ['ok' => false, 'error' => '', 'path' => ''];
    }
    if ($error !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'The file could not be uploaded.', 'path' => ''];
    }
    if ((int) ($file['size'] ?? 0) > $maxBytes) {
        return ['ok' => false, 'error' => 'Use an image under 5 MB.', 'path' => ''];
    }
    $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
    if (!isset($allowed[$extension])) {
        return ['ok' => false, 'error' => 'Use a JPG, PNG, GIF, or WebP image.', 'path' => ''];
    }
    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return ['ok' => false, 'error' => 'The file could not be uploaded.', 'path' => ''];
    }
    $imageTypes = [
        'jpg' => IMAGETYPE_JPEG,
        'jpeg' => IMAGETYPE_JPEG,
        'png' => IMAGETYPE_PNG,
        'gif' => IMAGETYPE_GIF,
        'webp' => defined('IMAGETYPE_WEBP') ? IMAGETYPE_WEBP : 18,
    ];
    $info = @getimagesize($tmp);
    $detected = is_array($info) ? (int) ($info[2] ?? 0) : 0;
    if ($detected !== ($imageTypes[$extension] ?? 0)) {
        return ['ok' => false, 'error' => 'The file contents do not match an allowed image type.', 'path' => ''];
    }
    $relativeDir = trim(str_replace('\\', '/', (string) $relativeDir), '/');
    if (!preg_match('#^assets/images/(logos|trainers|programmes|testimonials|clients)$#', $relativeDir)) {
        return ['ok' => false, 'error' => 'That upload folder is not allowed.', 'path' => ''];
    }
    $directory = dirname(__DIR__) . '/' . $relativeDir;
    if (!is_dir($directory) && !mkdir($directory, 0755, true)) {
        return ['ok' => false, 'error' => 'The upload folder could not be created.', 'path' => ''];
    }
    auth_deny_script_execution($directory);
    $filename = preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $prefix)) . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
    $destination = $directory . '/' . $filename;
    if (!move_uploaded_file($tmp, $destination)) {
        return ['ok' => false, 'error' => 'The file could not be saved.', 'path' => ''];
    }
    return ['ok' => true, 'error' => '', 'path' => $relativeDir . '/' . $filename];
}

function auth_remove_uploaded_image($relative, $prefix) {
    $relative = str_replace('\\', '/', (string) $relative);
    $prefix = preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $prefix));
    if ($prefix === '') {
        return;
    }
    $pattern = '#^assets/images/(testimonials|clients)/' . preg_quote($prefix, '#') . '_[a-f0-9]{16}\.(jpg|jpeg|png|gif|webp)$#';
    if (!preg_match($pattern, $relative)) {
        return;
    }
    auth_remove_managed_image($relative);
}

function auth_remove_stored_file($relative, $prefix) {
    $relative = str_replace('\\', '/', ltrim((string) $relative, '/'));
    $prefix = trim(str_replace('\\', '/', (string) $prefix), '/');
    if ($prefix === '' || $relative === '' || strpos($relative, '..') !== false || strpos($relative, $prefix . '/') !== 0) {
        return;
    }
    $root = realpath(dirname(__DIR__) . '/' . $prefix);
    $full = dirname(__DIR__) . '/' . $relative;
    if (!$root || !is_file($full)) {
        return;
    }
    $real = realpath($full);
    $rootPrefix = rtrim($root, '/\\') . DIRECTORY_SEPARATOR;
    if ($real && strpos($real, $rootPrefix) === 0) {
        unlink($real);
    }
}

function auth_import_file_error(array $file, array $extensions = ['csv', 'xlsx', 'xls']) {
    $maxBytes = 8 * 1024 * 1024;
    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_NO_FILE) {
        return 'Please select a file to upload.';
    }
    if ($error !== UPLOAD_ERR_OK) {
        return 'The file could not be uploaded.';
    }
    if ((int) ($file['size'] ?? 0) > $maxBytes) {
        return 'Use a spreadsheet under 8 MB.';
    }
    $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
    $extensions = array_map('strtolower', $extensions);
    if (!in_array($extension, $extensions, true)) {
        return 'Please upload a ' . implode(', ', $extensions) . ' file.';
    }
    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return 'The file could not be uploaded.';
    }
    $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    $blocked = ['text/html', 'application/x-httpd-php', 'application/x-php', 'text/x-php', 'application/javascript'];
    if (in_array($mime, $blocked, true)) {
        return 'That file type cannot be imported.';
    }
    return '';
}

function auth_owns_programme($programme) {
    if (!auth_is_trainer()) {
        return true;
    }
    $user = auth_user();
    return (int) ($programme['owner_user_id'] ?? 0) === (int) ($user['id'] ?? 0);
}

function auth_trainer_choices(PDO $pdo) {
    $stmt = $pdo->query("SELECT id, display_name, email, username FROM users WHERE role = 'trainer' ORDER BY display_name, email, username");
    return $stmt->fetchAll();
}

function auth_programme_type_label($type) {
    return $type === 'webinar' ? 'Webinar' : 'Training Programme';
}

function auth_normalize_programme_type($type) {
    return $type === 'webinar' ? 'webinar' : 'training';
}
