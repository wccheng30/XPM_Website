<?php
require_once '../includes/auth.php';
auth_require(['admin', 'manager', 'trainer']);
csrf_guard();
if (auth_is_trainer()) {
    header('Location: facilities.php');
    exit;
}

// Include database connection and services
require_once '../includes/database.php';
require_once '../includes/facility-booking-service.php';

// Handle programme actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $programme_id = $_POST['programme_id'] ?? '';
    
    if (!$programme_id || !$action) {
        header('Location: programmes.php');
        exit;
    }
    
    $pdo = get_db_connection();
    $facilityService = new FacilityBookingService($pdo);
    
    if ($pdo) {
        try {
            $ownerStmt = $pdo->prepare("SELECT * FROM programmes WHERE id = ?");
            $ownerStmt->execute([$programme_id]);
            $ownedProgramme = $ownerStmt->fetch();
            if (!$ownedProgramme || !auth_owns_programme($ownedProgramme)) {
                header('Location: programmes.php?error=' . urlencode('You can only change programmes you own.'));
                exit;
            }

            switch ($action) {
                case 'activate':
                    $stmt = $pdo->prepare("UPDATE programmes SET is_active = 1, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$programme_id]);
                    
                    // Restore facility booking if programme requires facility
                    try {
                        $facilityService->restoreProgrammeFacilityBooking($programme_id);
                        $message = 'Programme activated and the facility booking was restored. Registration stays closed until you open it.';
                    } catch (Exception $e) {
                        $message = 'Programme activated. The facility booking could not be restored. Registration stays closed until you open it.';
                    }
                    break;
                    
                    
                case 'deactivate':
                    // Deactivate programme
                    $stmt = $pdo->prepare("UPDATE programmes SET is_active = 0, registration_status = 'closed', updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$programme_id]);
                    
                    // Cancel associated facility booking if it exists (mark as cancelled instead of deleting)
                    try {
                        $facilityService->cancelProgrammeFacilityBooking($programme_id);
                        $message = 'Programme deactivated and registration is closed. The facility booking has been cancelled.';
                    } catch (Exception $e) {
                        $message = 'Programme deactivated and registration is closed. The facility booking could not be cancelled.';
                    }
                    break;
                    
                case 'duplicate':
                    // Get the original programme data
                    $stmt = $pdo->prepare("SELECT * FROM programmes WHERE id = ?");
                    $stmt->execute([$programme_id]);
                    $original_programme = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($original_programme) {
                        // Create a duplicate with modified title and set as inactive
                        $duplicate_title = $original_programme['title'] . ' (Copy)';
                        $duplicate_description = $original_programme['description'];
                        $duplicate_duration = $original_programme['duration'];
                        $duplicate_price = $original_programme['price'];
                        $duplicate_original_price = $original_programme['original_price'];
                        $duplicate_category = $original_programme['category'];
                        $duplicate_max_participants = $original_programme['max_participants'];
                        $duplicate_certification = $original_programme['certification'];
                        $duplicate_programme_icon = $original_programme['programme_icon'];
                        $duplicate_programme_badge = $original_programme['programme_badge'];
                        $duplicate_location = $original_programme['location'];
                        $duplicate_modules = $original_programme['modules'];
                        $duplicate_schedule_type = $original_programme['schedule_type'];
                        $duplicate_poster_image = $original_programme['poster_image'];
                        
                        // Insert the duplicate programme
                        $insert_sql = "INSERT INTO programmes (title, description, duration, price, original_price, category, programme_type, owner_user_id, max_participants, certification, programme_icon, programme_badge, location, modules, schedule_type, poster_image, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NOW(), NOW())";
                        $insert_stmt = $pdo->prepare($insert_sql);
                        $insert_stmt->execute([
                            $duplicate_title,
                            $duplicate_description,
                            $duplicate_duration,
                            $duplicate_price,
                            $duplicate_original_price,
                            $duplicate_category,
                            $original_programme['programme_type'] ?? 'training',
                            $original_programme['owner_user_id'] ?? null,
                            $duplicate_max_participants,
                            $duplicate_certification,
                            $duplicate_programme_icon,
                            $duplicate_programme_badge,
                            $duplicate_location,
                            $duplicate_modules,
                            $duplicate_schedule_type,
                            $duplicate_poster_image
                        ]);
                        
                        $message = 'Programme duplicated successfully! The duplicate has been created as inactive.';
                    } else {
                        $message = 'Original programme not found!';
                    }
                    break;
                    
                case 'delete':
                    if (($_SESSION['admin_role'] ?? '') === 'manager') {
                        header('Location: programmes.php?error=' . urlencode('You do not have permission to delete programmes.'));
                        exit;
                    }
                    // Delete associated facility booking first
                    try {
                        $facilityService->deleteProgrammeFacilityBooking($programme_id);
                        $booking_deleted = true;
                    } catch (Exception $e) {
                        $booking_deleted = false;
                    }
                    
                    // Delete programme
                    $stmt = $pdo->prepare("DELETE FROM programmes WHERE id = ?");
                    $stmt->execute([$programme_id]);
                    
                    if ($booking_deleted) {
                        $message = 'Programme deleted successfully! Associated facility booking has been removed.';
                    } else {
                        $message = 'Programme deleted successfully! (Note: Associated facility booking may still exist)';
                    }
                    break;
                    
                default:
                    $message = 'Invalid action!';
                    break;
            }
            
            // Redirect back to programmes page with success message
            header("Location: programmes.php?message=" . urlencode($message) . "#programme-" . $programme_id);
            exit;
            
        } catch (PDOException $e) {
            $error = 'The programme could not be updated.';
            header("Location: programmes.php?error=" . urlencode($error) . "#programme-" . $programme_id);
            exit;
        }
    } else {
        $error = "Unable to connect to database";
        header("Location: programmes.php?error=" . urlencode($error) . "#programme-" . $programme_id);
        exit;
    }
} else {
    header('Location: programmes.php');
    exit;
}
?> 