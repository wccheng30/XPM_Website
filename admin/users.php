<?php
require_once '../includes/auth.php';
auth_require(['admin']);

$pdo = get_db_connection();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo && ($_POST['action'] ?? '') === 'delete') {
    $targetId = (int) ($_POST['user_id'] ?? 0);
    $actor = auth_user();
    if (!auth_verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'The form expired. Please try again.';
    } elseif ($targetId <= 0 || $targetId === (int) ($actor['id'] ?? 0)) {
        $error = 'You cannot remove the account you are using.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$targetId]);
            $target = $stmt->fetch();
            if (!$target || !in_array($target['role'], ['user', 'trainer'], true) || $target['username'] === 'admin') {
                $error = 'That account cannot be removed.';
            } else {
                $pdo->prepare("UPDATE programmes SET owner_user_id = NULL WHERE owner_user_id = ?")->execute([$targetId]);
                $pdo->prepare("DELETE FROM trainers WHERE user_id = ?")->execute([$targetId]);
                if (!empty($target['avatar_path']) && strpos($target['avatar_path'], 'assets/images/avatars/') === 0) {
                    $avatar = dirname(__DIR__) . '/' . $target['avatar_path'];
                    if (is_file($avatar)) {
                        @unlink($avatar);
                    }
                }
                $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$targetId]);
                $success = 'Account removed.';
            }
        } catch (PDOException $e) {
            $error = 'Could not remove that account.';
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {
    $targetId = (int) ($_POST['user_id'] ?? 0);
    $nextRole = $_POST['role'] ?? '';

    if (!auth_verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'The form expired. Please try again.';
    } elseif (!in_array($nextRole, ['user', 'trainer'], true) || $targetId <= 0) {
        $error = 'Choose a member or trainer role.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$targetId]);
            $target = $stmt->fetch();

            if (!$target || !in_array($target['role'], ['user', 'trainer'], true)) {
                $error = 'That account cannot be changed here.';
            } elseif ($nextRole === 'trainer') {
                $pdo->prepare("UPDATE users SET role = 'trainer' WHERE id = ?")->execute([$targetId]);
                $linked = $pdo->prepare("SELECT id FROM trainers WHERE user_id = ?");
                $linked->execute([$targetId]);
                if (!$linked->fetch()) {
                    $name = trim((string) ($target['display_name'] ?: $target['email']));
                    $pdo->prepare("INSERT INTO trainers (user_id, name, title, is_active, display_order) VALUES (?, ?, '', 0, 0)")
                        ->execute([$targetId, $name !== '' ? $name : 'Trainer']);
                }
                $success = 'This account can now manage their trainer profile and their own programmes.';
            } else {
                $pdo->prepare("UPDATE users SET role = 'user' WHERE id = ?")->execute([$targetId]);
                $pdo->prepare("UPDATE trainers SET is_active = 0 WHERE user_id = ?")->execute([$targetId]);
                $success = 'Trainer access removed. Their programmes stay in the list for admin.';
            }
        } catch (PDOException $e) {
            $error = 'Could not update that account.';
        }
    }
}

$users = [];
if ($pdo) {
    $stmt = $pdo->query("SELECT u.id, u.username, u.email, u.display_name, u.role, u.created_at, u.last_login, t.is_active AS trainer_active
        FROM users u
        LEFT JOIN trainers t ON t.user_id = u.id
        ORDER BY FIELD(u.role, 'admin', 'manager', 'trainer', 'user'), u.created_at DESC");
    $users = $stmt->fetchAll();
}

$roleLabels = [
    'admin' => 'Admin',
    'manager' => 'Manager',
    'trainer' => 'Trainer',
    'user' => 'Member',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users - XpressPoint Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="admin-styles.css?v=20261005-5">
</head>
<body data-motion="admin">
    <?php include_once 'includes/header.php'; renderAdminHeader('Users', 'fas fa-user-shield'); ?>
    <div class="admin-container">
        <?php include 'includes/navigation.php'; renderAdminNavigation('users'); ?>
        <div class="page-header">
            <h2 class="page-title"><i class="fas fa-user-shield"></i> Accounts</h2>
            <p>New registrations stay members. Choose who becomes a trainer.</p>
        </div>
        <?php if ($error): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php
        $grouped = ['admin' => [], 'manager' => [], 'trainer' => [], 'user' => []];
        foreach ($users as $row) {
            $grouped[$row['role']][] = $row;
        }
        foreach ($grouped as $roleKey => $rows):
            if (!$rows) {
                continue;
            }
        ?>
            <h3 class="user-group-title"><?php echo htmlspecialchars($roleLabels[$roleKey]); ?></h3>
            <div class="user-stack">
                <?php foreach ($rows as $row): ?>
                    <article class="user-card">
                        <div class="user-card-main">
                            <h3><?php echo htmlspecialchars($row['display_name'] ?: $row['username']); ?></h3>
                            <p><?php echo htmlspecialchars($row['email']); ?></p>
                            <p>
                                <?php echo htmlspecialchars($roleLabels[$row['role']] ?? $row['role']); ?>
                                <?php if ($row['role'] === 'trainer'): ?>
                                    · <?php echo !empty($row['trainer_active']) ? 'Visible on the site' : 'Hidden until you publish the trainer card'; ?>
                                <?php endif; ?>
                                <?php if (!empty($row['last_login'])): ?>
                                    · Last login <?php echo date('M j, Y', strtotime($row['last_login'])); ?>
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="user-card-actions">
                            <?php if (in_array($row['role'], ['user', 'trainer'], true)): ?>
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(auth_csrf_token()); ?>">
                                    <input type="hidden" name="user_id" value="<?php echo (int) $row['id']; ?>">
                                    <select name="role" class="form-control">
                                        <option value="user" <?php echo $row['role'] === 'user' ? 'selected' : ''; ?>>Member</option>
                                        <option value="trainer" <?php echo $row['role'] === 'trainer' ? 'selected' : ''; ?>>Trainer</option>
                                    </select>
                                    <button type="submit" class="btn btn-sm btn-primary">Save</button>
                                </form>
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(auth_csrf_token()); ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="user_id" value="<?php echo (int) $row['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger" data-confirm-title="Remove this account?" data-confirm="This login will be deleted." data-confirm-ok="Remove" data-confirm-icon="fas fa-trash">Remove</button>
                                </form>
                            <?php else: ?>
                                <span>Fixed</span>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <script src="../assets/js/motion.js?v=20261002-10"></script>
</body>
</html>
