<?php
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');
$user = auth_user();
if (!$user) {
    echo json_encode(['loggedIn' => false]);
    exit;
}

$workspace = null;
if (in_array($user['role'], ['admin', 'manager', 'trainer'], true)) {
    $workspace = auth_home_path($user['role'], false);
}

echo json_encode([
    'loggedIn' => true,
    'name' => $user['name'],
    'initial' => auth_initial($user['name']),
    'avatar' => $user['avatar'],
    'workspace' => $workspace,
]);
