<?php
session_start();

require_once '../includes/auth.php';
auth_require(['admin', 'manager'], '403');

// Include database connection
require_once '../includes/database.php';

$pdo = get_db_connection();

if ($pdo) {
    try {
        // Get export format from URL parameter
        $format = $_GET['format'] ?? 'csv';
        
        // Build the query with filters
        $where_conditions = [];
        $params = [];
        
        // Search filter
        if (!empty($_GET['search'])) {
            $search_term = $_GET['search'];
            $where_conditions[] = "(title LIKE ? OR location LIKE ? OR type LIKE ? OR description LIKE ? OR requirements LIKE ?)";
            $search_param = "%$search_term%";
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
        }
        
        // Status filter
        if (!empty($_GET['filter'])) {
            if ($_GET['filter'] === 'active') {
                $where_conditions[] = "is_active = 1";
            } elseif ($_GET['filter'] === 'inactive') {
                $where_conditions[] = "is_active = 0";
            }
        }
        
        // Build the final query
        $sql = "SELECT * FROM jobs";
        if (!empty($where_conditions)) {
            $sql .= " WHERE " . implode(" AND ", $where_conditions);
        }
        $sql .= " ORDER BY created_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $jobs = $stmt->fetchAll();
        
        // Prepare data for export
        $export_data = [];
        foreach ($jobs as $job) {
            $export_data[] = [
                'ID' => $job['id'],
                'Title' => $job['title'],
                'Company Name' => $job['company_name'] ?? 'HR Training',
                'Location' => $job['location'],
                'Type' => $job['type'],
                'Description' => $job['description'],
                'Requirements' => $job['requirements'] ?? '',
                'Salary Range' => $job['salary_range'] ?? 'N/A',
                'Benefits' => $job['benefits'] ?? '',
                'Status' => $job['is_active'] ? 'Active' : 'Inactive',
                'Created Date' => date('Y-m-d H:i:s', strtotime($job['created_at']))
            ];
        }
        
        // Export based on format
        switch (strtolower($format)) {
            case 'csv':
                exportCSV($export_data);
                break;
            case 'json':
                exportJSON($export_data);
                break;
            case 'sql':
                exportSQL($export_data);
                break;
            default:
                exportCSV($export_data);
        }
        
    } catch (PDOException $e) {
        http_response_code(500);
        exit('Database error: ' . $e->getMessage());
    }
} else {
    http_response_code(500);
    exit('Unable to connect to database');
}

function exportCSV($data) {
    $filename = 'job_postings_' . date('Y-m-d_H-i-s') . '.csv';
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    if (!empty($data)) {
        // Write headers
        fputcsv($output, array_keys($data[0]));
        
        // Write data
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
    }
    
    fclose($output);
}

function exportJSON($data) {
    $filename = 'job_postings_' . date('Y-m-d_H-i-s') . '.json';
    
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}



function exportSQL($data) {
    $filename = 'job_postings_' . date('Y-m-d_H-i-s') . '.sql';
    
    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    echo '-- Job Postings Export' . "\n";
    echo '-- Generated on: ' . date('Y-m-d H:i:s') . "\n";
    echo '-- Total Records: ' . count($data) . "\n\n";
    
    echo '-- Create jobs table if it doesn\'t exist' . "\n";
    echo 'CREATE TABLE IF NOT EXISTS jobs (' . "\n";
    echo '    id INT AUTO_INCREMENT PRIMARY KEY,' . "\n";
    echo '    title VARCHAR(255) NOT NULL,' . "\n";
    echo '    location VARCHAR(255),' . "\n";
    echo '    type VARCHAR(100),' . "\n";
    echo '    description TEXT,' . "\n";
    echo '    requirements TEXT,' . "\n";
    echo '    company_name VARCHAR(255) DEFAULT \'HR Training\',' . "\n";
    echo '    job_icon VARCHAR(100) DEFAULT \'fas fa-briefcase\',' . "\n";
    echo '    job_badge VARCHAR(100) DEFAULT \'New\',' . "\n";
    echo '    poster_image VARCHAR(500),' . "\n";
    echo '    start_time TIME,' . "\n";
    echo '    end_time TIME,' . "\n";
    echo '    salary_range VARCHAR(100),' . "\n";
    echo '    experience_level VARCHAR(100),' . "\n";
    echo '    is_active BOOLEAN DEFAULT TRUE,' . "\n";
    echo '    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,' . "\n";
    echo '    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP' . "\n";
    echo ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;' . "\n\n";
    
    if (!empty($data)) {
        echo '-- Insert job records' . "\n";
        echo 'INSERT INTO jobs (title, location, type, description, requirements, company_name, salary_range, experience_level, is_active, created_at) VALUES' . "\n";
        
        $values = [];
        foreach ($data as $index => $job) {
            $title = addslashes($job['Title']);
            $location = addslashes($job['Location']);
            $type = addslashes($job['Type']);
            $description = addslashes($job['Description']);
            $requirements = addslashes($job['Requirements']);
            $company_name = addslashes($job['Company Name']);
            $salary_range = addslashes($job['Salary Range']);
            $experience_level = addslashes($job['Experience Level'] ?? '');
            $is_active = ($job['Status'] === 'Active') ? 1 : 0;
            $created_date = $job['Created Date'];
            
            $value = "('$title', '$location', '$type', '$description', '$requirements', '$company_name', '$salary_range', '$experience_level', $is_active, '$created_date')";
            
            if ($index === count($data) - 1) {
                $values[] = $value . ';';
            } else {
                $values[] = $value . ',';
            }
        }
        
        echo implode("\n", $values) . "\n\n";
        
        echo '-- Export completed successfully' . "\n";
        echo '-- You can now import this SQL file into any MySQL database' . "\n";
    }
}
?>
