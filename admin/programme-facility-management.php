<?php
require_once '../includes/auth.php';
auth_start();
$query = $_SERVER['QUERY_STRING'] ?? '';
header('Location: facilities.php' . ($query !== '' ? '?' . $query : ''));
exit;
