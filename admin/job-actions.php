<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

// Include database connection
require_once '../includes/database.php';

// Handle job actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $job_id = $_POST['job_id'] ?? '';
    
    if (!$job_id || !$action) {
        header('Location: jobs.php');
        exit;
    }
    
    $pdo = get_db_connection();
    
    if ($pdo) {
        try {
            switch ($action) {
                case 'activate':
                    $stmt = $pdo->prepare("UPDATE jobs SET is_active = 1, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$job_id]);
                    $message = 'Job activated successfully!';
                    break;
                    
                case 'deactivate':
                    $stmt = $pdo->prepare("UPDATE jobs SET is_active = 0, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$job_id]);
                    $message = 'Job deactivated successfully!';
                    break;
                    
                case 'duplicate':
                    // Get the original job data
                    $stmt = $pdo->prepare("SELECT * FROM jobs WHERE id = ?");
                    $stmt->execute([$job_id]);
                    $original_job = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($original_job) {
                        // Create a duplicate with modified title and set as inactive
                        $duplicate_title = $original_job['title'] . ' (Copy)';
                        $duplicate_description = $original_job['description'];
                        $duplicate_location = $original_job['location'];
                        $duplicate_type = $original_job['type'];
                        $duplicate_requirements = $original_job['requirements'];
                        $duplicate_company_name = $original_job['company_name'];
                        $duplicate_job_icon = $original_job['job_icon'];
                        $duplicate_job_badge = $original_job['job_badge'];
                        $duplicate_poster_image = $original_job['poster_image'];
                        $duplicate_start_time = $original_job['start_time'];
                        $duplicate_end_time = $original_job['end_time'];
                        $duplicate_salary_range = $original_job['salary_range'];
                        $duplicate_experience_level = $original_job['experience_level'];
                        
                        // Insert the duplicate job
                        $insert_sql = "INSERT INTO jobs (title, description, location, type, requirements, company_name, job_icon, job_badge, poster_image, start_time, end_time, salary_range, experience_level, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NOW(), NOW())";
                        $insert_stmt = $pdo->prepare($insert_sql);
                        $insert_stmt->execute([
                            $duplicate_title,
                            $duplicate_description,
                            $duplicate_location,
                            $duplicate_type,
                            $duplicate_requirements,
                            $duplicate_company_name,
                            $duplicate_job_icon,
                            $duplicate_job_badge,
                            $duplicate_poster_image,
                            $duplicate_start_time,
                            $duplicate_end_time,
                            $duplicate_salary_range,
                            $duplicate_experience_level
                        ]);
                        
                        $message = 'Job duplicated successfully! The duplicate has been created as inactive.';
                    } else {
                        $message = 'Original job not found!';
                    }
                    break;
                    
                case 'delete':
                    $stmt = $pdo->prepare("DELETE FROM jobs WHERE id = ?");
                    $stmt->execute([$job_id]);
                    $message = 'Job deleted successfully!';
                    break;
                    
                default:
                    $message = 'Invalid action!';
                    break;
            }
            
            // Redirect back to jobs page with success message
            header("Location: jobs.php?message=" . urlencode($message) . "#job-" . $job_id);
            exit;
            
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
            header("Location: jobs.php?error=" . urlencode($error) . "#job-" . $job_id);
            exit;
        }
    } else {
        $error = "Unable to connect to database";
        header("Location: jobs.php?error=" . urlencode($error) . "#job-" . $job_id);
        exit;
    }
} else {
    header('Location: jobs.php');
    exit;
}
?> 