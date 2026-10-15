<?php
require_once '../includes/auth.php';
auth_require(['admin']);
csrf_guard();
require_once __DIR__ . '/includes/ui.php';

$pdo = get_db_connection();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo && in_array($_POST['action'] ?? '', ['bulk_delete', 'bulk_update_status'], true)) {
    if (!auth_verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'The form expired. Please try again.';
    } else {
        $ids = array_values(array_filter(array_map('intval', (array) ($_POST['ids'] ?? []))));
        $actor = auth_user();
        $done = 0;
        foreach ($ids as $targetId) {
            if ($targetId <= 0 || $targetId === (int) ($actor['id'] ?? 0)) {
                continue;
            }
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$targetId]);
            $target = $stmt->fetch();
            if (!$target || !in_array($target['role'], ['user', 'trainer'], true) || $target['username'] === 'admin') {
                continue;
            }
            if (($_POST['action'] ?? '') === 'bulk_delete') {
                $pdo->prepare("UPDATE programmes SET owner_user_id = NULL WHERE owner_user_id = ?")->execute([$targetId]);
                $pdo->prepare("DELETE FROM trainers WHERE user_id = ?")->execute([$targetId]);
                $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$targetId]);
                $done++;
            } else {
                $nextRole = $_POST['status'] ?? '';
                if (!in_array($nextRole, ['user', 'trainer'], true)) {
                    continue;
                }
                $pdo->prepare("UPDATE users SET role = ? WHERE id = ?")->execute([$nextRole, $targetId]);
                if ($nextRole === 'trainer') {
                    $linked = $pdo->prepare("SELECT id FROM trainers WHERE user_id = ?");
                    $linked->execute([$targetId]);
                    if (!$linked->fetch()) {
                        $name = trim((string) ($target['display_name'] ?: $target['email']));
                        $pdo->prepare("INSERT INTO trainers (user_id, name, title, is_active, display_order) VALUES (?, ?, '', 0, 0)")
                            ->execute([$targetId, $name !== '' ? $name : 'Trainer']);
                    }
                } else {
                    $pdo->prepare("UPDATE trainers SET is_active = 0 WHERE user_id = ?")->execute([$targetId]);
                }
                $done++;
            }
        }
        $success = $done . ' account' . ($done === 1 ? '' : 's') . ' updated.';
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo && ($_POST['action'] ?? '') === 'delete') {
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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <?php include_once __DIR__ . '/includes/theme-boot.php'; ?>
    <link rel="stylesheet" href="<?php echo admin_asset('admin.min.css'); ?>">
    <link rel="stylesheet" href="<?php echo admin_asset('css/list.css'); ?>">
</head>
<body data-motion="admin">
    <?php include_once 'includes/header.php'; renderAdminHeader('Users', 'fas fa-user-shield'); ?>
    <div class="admin-container">
        <?php include 'includes/navigation.php'; renderAdminNavigation('users'); ?>
        <?php
        renderPageHead('Accounts', 'New registrations stay members. Choose who becomes a trainer.');
        if ($error) {
            echo '<div class="alert alert-error"><i class="fas fa-exclamation-circle" aria-hidden="true"></i> ' . ui_h($error) . '</div>';
        }
        if ($success) {
            echo '<div class="alert alert-success"><i class="fas fa-check-circle" aria-hidden="true"></i> ' . ui_h($success) . '</div>';
        }
        $role_filter = (string) ($_GET['filter'] ?? '');
        $user_search = strtolower(trim((string) ($_GET['search'] ?? '')));
        $role_counts = ['admin' => 0, 'manager' => 0, 'trainer' => 0, 'user' => 0];
        foreach ($users as $row) {
            if (isset($role_counts[$row['role']])) {
                $role_counts[$row['role']]++;
            }
        }
        $visible_users = array_values(array_filter($users, function ($row) use ($role_filter, $user_search) {
            if ($role_filter !== '' && $row['role'] !== $role_filter) {
                return false;
            }
            if ($user_search === '') {
                return true;
            }
            $haystack = strtolower($row['display_name'] . ' ' . $row['username'] . ' ' . $row['email']);
            return strpos($haystack, $user_search) !== false;
        }));
        renderStatusChips([
            ['value' => '', 'label' => 'All', 'count' => count($users)],
            ['value' => 'admin', 'label' => 'Admin', 'count' => $role_counts['admin']],
            ['value' => 'manager', 'label' => 'Staff', 'count' => $role_counts['manager']],
            ['value' => 'trainer', 'label' => 'Trainer', 'count' => $role_counts['trainer']],
            ['value' => 'user', 'label' => 'Member', 'count' => $role_counts['user']],
        ], $role_filter);
        $user_active = [];
        if ($user_search !== '') {
            $user_active[] = ['label' => 'Search: ' . $user_search, 'href' => ui_query(['search' => null], ['page'])];
        }
        if ($role_filter !== '') {
            $user_active[] = ['label' => 'Role: ' . ($roleLabels[$role_filter] ?? $role_filter), 'href' => ui_query(['filter' => null], ['page'])];
        }
        renderListToolbar('search', (string) ($_GET['search'] ?? ''), 'Search name or email', '', '', 'users.php', $user_active);
        $list_page = max(1, (int) ($_GET['page'] ?? 1));
        $list_per_page = ui_page_size(25);
        $list_total = count($visible_users);
        $list_pages = max(1, (int) ceil($list_total / $list_per_page));
        if ($list_page > $list_pages) {
            $list_page = $list_pages;
        }
        $page_users = array_slice($visible_users, ($list_page - 1) * $list_per_page, $list_per_page);
        ?>
        <?php if (!$page_users): ?>
            <?php renderEmptyState('fas fa-user-shield', 'No accounts found', 'Nothing matches the current search or role.', 'users.php', 'Clear filters'); ?>
        <?php else: ?>
            <form method="post" id="listBulkForm">
                <input type="hidden" name="csrf_token" value="<?php echo ui_h(auth_csrf_token()); ?>">
                <input type="hidden" name="action" id="bulkAction" value="">
            </form>
                <?php renderBulkBar(['user' => 'Member', 'trainer' => 'Trainer'], true, false); ?>
                <div class="list-table-wrap">
                    <table class="list-table">
                        <thead>
                            <tr>
                                <th scope="col"><input type="checkbox" id="listSelectAll" aria-label="Select all rows"></th>
                                <th scope="col">Name</th>
                                <th scope="col">Email</th>
                                <th scope="col">Role</th>
                                <th scope="col">Last login</th>
                                <th scope="col"><span class="u-clip">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($page_users as $row):
                                $editable = in_array($row['role'], ['user', 'trainer'], true);
                            ?>
                            <tr>
                                <td class="list-select" data-label="">
                                    <?php if ($editable): ?>
                                        <input type="checkbox" class="list-check" name="ids[]" value="<?php echo (int) $row['id']; ?>" form="listBulkForm" aria-label="Select <?php echo ui_h($row['display_name'] ?: $row['username']); ?>">
                                    <?php endif; ?>
                                </td>
                                <td data-label="Name"><?php echo ui_h($row['display_name'] ?: $row['username']); ?></td>
                                <td data-label="Email"><?php echo ui_h($row['email']); ?></td>
                                <td data-label="Role"><?php renderStatusBadge($row['role'], $roleLabels[$row['role']] ?? $row['role']); ?></td>
                                <td data-label="Last login"><?php echo !empty($row['last_login']) ? date('M j, Y', strtotime($row['last_login'])) : 'Never'; ?></td>
                                <td data-label="Actions">
                                    <?php if ($editable): ?>
                                        <form method="post" class="list-inline-form">
                                            <input type="hidden" name="csrf_token" value="<?php echo ui_h(auth_csrf_token()); ?>">
                                            <input type="hidden" name="user_id" value="<?php echo (int) $row['id']; ?>">
                                            <select name="role" aria-label="Role for <?php echo ui_h($row['display_name'] ?: $row['username']); ?>">
                                                <option value="user" <?php echo $row['role'] === 'user' ? 'selected' : ''; ?>>Member</option>
                                                <option value="trainer" <?php echo $row['role'] === 'trainer' ? 'selected' : ''; ?>>Trainer</option>
                                            </select>
                                            <button type="submit" class="btn btn-sm btn-secondary">Save</button>
                                        </form>
                                        <?php renderRowActions([
                                            ['label' => 'Delete', 'icon' => 'fas fa-trash', 'danger' => true, 'onclick' => 'removeAccount(' . (int) $row['id'] . ')'],
                                        ]); ?>
                                        <form method="post" id="userDelete<?php echo (int) $row['id']; ?>" hidden>
                                            <input type="hidden" name="csrf_token" value="<?php echo ui_h(auth_csrf_token()); ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="user_id" value="<?php echo (int) $row['id']; ?>">
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php renderPagination($list_page, $list_per_page, $list_total); ?>
        <?php endif; ?>
    </div>
    <script>
        function removeAccount(id) {
            var submitDelete = function () {
                document.getElementById('userDelete' + id).submit();
            };
            if (window.xpmConfirm) {
                window.xpmConfirm({
                    title: 'Remove this account?',
                    message: 'This login will be deleted.',
                    confirmText: 'Remove',
                    icon: 'fas fa-trash'
                }).then(function (ok) { if (ok) submitDelete(); });
            } else {
                submitDelete();
            }
        }
    </script>
    <script defer src="<?php echo admin_asset('js/list.js'); ?>"></script>
</body>
</html>
