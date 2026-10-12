<?php
/**
 * Shared login session for public accounts and the admin area.
 */

require_once __DIR__ . '/database.php';

function auth_start() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_secure', '0');
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
