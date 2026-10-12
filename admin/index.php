<?php
require_once '../includes/auth.php';
auth_start();

$user = auth_user();
if ($user && in_array($user['role'], ['admin', 'manager', 'trainer'], true)) {
    header('Location: ' . auth_home_path($user['role'], true));
    exit;
}

header('Location: ../login.php');
exit;
