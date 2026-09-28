<?php
/**
 * Shared Database Connection Configuration
 * XpressPoint Management Services
 *
 * Copy this file to database.php and fill in your credentials.
 */

$db_config = [
    'host' => 'localhost',
    'dbname' => 'xpresspo_db',
    'username' => 'your_db_user',
    'password' => 'your_db_password'
];

/**
 * Get database connection
 *
 * @param bool $create_db Whether to create database if it doesn't exist (for setup)
 * @return PDO|null Database connection or null on failure
 */
function get_db_connection($create_db = false) {
    global $db_config;

    try {
        if ($create_db) {
            $pdo = new PDO(
                "mysql:host={$db_config['host']};charset=utf8mb4",
                $db_config['username'],
                $db_config['password'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );
        } else {
            $pdo = new PDO(
                "mysql:host={$db_config['host']};dbname={$db_config['dbname']};charset=utf8mb4",
                $db_config['username'],
                $db_config['password'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );
        }

        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['admin_logged_in']) && !isset($_SESSION['admin_role'])) {
            $username = $_SESSION['admin_username'] ?? '';
            if ($username) {
                $stmt = $pdo->prepare("SELECT role FROM users WHERE username = ?");
                $stmt->execute([$username]);
                $user = $stmt->fetch();
                if ($user) {
                    $_SESSION['admin_role'] = $user['role'] ?? 'admin';
                } elseif ($username === 'admin') {
                    $_SESSION['admin_role'] = 'admin';
                }
            }
        }

        return $pdo;
    } catch (PDOException $e) {
        return null;
    }
}
