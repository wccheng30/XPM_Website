<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

// Include database connection
require_once '../includes/database.php';

$pdo = get_db_connection();

if (!$pdo) {
    header('Location: trainers.php?error=Database connection failed');
    exit;
}

// Handle different actions
$action = $_GET['action'] ?? '';
$trainer_id = $_GET['id'] ?? null;

if (!$trainer_id) {
    header('Location: trainers.php?error=Invalid trainer ID');
    exit;
}

try {
    switch ($action) {
        case 'delete':
            // Get trainer info before deletion
            $stmt = $pdo->prepare("SELECT * FROM trainers WHERE id = ?");
            $stmt->execute([$trainer_id]);
            $trainer = $stmt->fetch();
            
            if (!$trainer) {
                header('Location: trainers.php?error=Trainer not found');
                exit;
            }
            
            // Delete trainer image if it exists and is not a default image
            if ($trainer['image_path'] && 
                !strpos($trainer['image_path'], 'icol-chua.png') && 
                !strpos($trainer['image_path'], 'HRDTrainer.png') && 
                !strpos($trainer['image_path'], 'AccreditedTrainer.png') && 
                !strpos($trainer['image_path'], 'PowerUpMentor.png')) {
                $image_path = '../' . $trainer['image_path'];
                if (file_exists($image_path)) {
                    unlink($image_path);
                }
            }
            
            // Delete trainer from database
            $stmt = $pdo->prepare("DELETE FROM trainers WHERE id = ?");
            $stmt->execute([$trainer_id]);
            
            header('Location: trainers.php?success=Trainer deleted successfully#trainer-' . $trainer_id);
            exit;
            
        case 'toggle_active':
            $stmt = $pdo->prepare("UPDATE trainers SET is_active = NOT is_active WHERE id = ?");
            $stmt->execute([$trainer_id]);
            header('Location: trainers.php?success=Trainer active status updated#trainer-' . $trainer_id);
            exit;
            
        case 'duplicate':
            // Get trainer data
            $stmt = $pdo->prepare("SELECT * FROM trainers WHERE id = ?");
            $stmt->execute([$trainer_id]);
            $trainer = $stmt->fetch();
            
            if (!$trainer) {
                header('Location: trainers.php?error=Trainer not found');
                exit;
            }
            
            // Create duplicate with modified name
            $new_name = $trainer['name'] . ' (Copy)';
            $stmt = $pdo->prepare("INSERT INTO trainers (name, title, experience_years, image_path, description, specialties, credentials, is_active, display_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $new_name,
                $trainer['title'],
                $trainer['experience_years'],
                $trainer['image_path'],
                $trainer['description'],
                $trainer['specialties'],
                $trainer['credentials'],
                $trainer['is_active'],
                $trainer['display_order'] + 1
            ]);
            
            header('Location: trainers.php?success=Trainer duplicated successfully#trainer-' . $trainer_id);
            exit;
            
        default:
            header('Location: trainers.php?error=Invalid action');
            exit;
    }
    
} catch (PDOException $e) {
    header('Location: trainers.php?error=Database error: ' . urlencode($e->getMessage()));
    exit;
}
?>
