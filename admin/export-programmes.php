<?php
session_start();

require_once '../includes/auth.php';
auth_require(['admin', 'manager'], '403');

// Include database connection
require_once '../includes/database.php';

// Helper function to safely format prices for export
function formatPriceForExport($price) {
    if (is_numeric($price)) {
        if ((float)$price == 0) {
            return 'Free';
        }
        return 'RM ' . number_format($price, 0);
    } else {
        return $price;
    }
}

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
            $where_conditions[] = "(title LIKE ? OR category LIKE ? OR duration LIKE ? OR description LIKE ?)";
            $search_param = "%$search_term%";
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
            } elseif ($_GET['filter'] === 'webinar') {
                $where_conditions[] = "programme_type = 'webinar'";
            } elseif ($_GET['filter'] === 'training') {
                $where_conditions[] = "programme_type = 'training'";
            }
        }
        if (!empty($_GET['ids'])) {
            $ids = array_values(array_filter(array_map('intval', explode(',', (string) $_GET['ids']))));
            if ($ids) {
                $where_conditions[] = 'id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
                foreach ($ids as $id) {
                    $params[] = $id;
                }
            }
        }
        
        // Build the final query
        $sql = "SELECT * FROM programmes";
        if (!empty($where_conditions)) {
            $sql .= " WHERE " . implode(" AND ", $where_conditions);
        }
        $sql .= " ORDER BY created_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $programmes = $stmt->fetchAll();
        
        // Prepare data for export
        $export_data = [];
        foreach ($programmes as $programme) {
            $export_data[] = [
                'ID' => $programme['id'],
                'Title' => $programme['title'],
                'Description' => $programme['description'],
                'Category' => $programme['category'],
                'Duration' => $programme['duration'],
                'Price' => $programme['price'] ? formatPriceForExport($programme['price']) : 'N/A',
                'Original Price' => $programme['original_price'] ? formatPriceForExport($programme['original_price']) : 'N/A',
                'Location' => $programme['location'] ?? 'N/A',
                'Certification' => $programme['certification'] ?? 'N/A',
                'Max Participants' => $programme['max_participants'] ?? 'N/A',
                'Programme Icon' => $programme['programme_icon'] ?? 'N/A',
                'Programme Badge' => $programme['programme_badge'] ?? 'N/A',
                'Modules' => $programme['modules'] ?? '',
                'Schedule Type' => $programme['schedule_type'] ?? 'N/A',
                'Start Date' => $programme['start_date'] ?? 'N/A',
                'End Date' => $programme['end_date'] ?? 'N/A',
                'Start Time' => $programme['start_time'] ?? 'N/A',
                'End Time' => $programme['end_time'] ?? 'N/A',
                'Poster Image' => $programme['poster_image'] ?? 'N/A',
                'Poster Image 2' => $programme['poster_image_2'] ?? 'N/A',
                'Status' => $programme['is_active'] ? 'Active' : 'Inactive',
                'Created Date' => "'" . date('Y-m-d', strtotime($programme['created_at'])),
                'Updated Date' => "'" . date('Y-m-d', strtotime($programme['updated_at']))
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
        exit('The request could not be completed.');
    }
} else {
    http_response_code(500);
    exit('Unable to connect to database');
}

function exportCSV($data) {
    $filename = 'training_programmes_' . date('Y-m-d_H-i-s') . '.csv';
    
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
    $filename = 'training_programmes_' . date('Y-m-d_H-i-s') . '.json';
    
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}



function exportSQL($data) {
    $filename = 'training_programmes_' . date('Y-m-d_H-i-s') . '.sql';
    
    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    echo '-- Training Programmes Export' . "\n";
    echo '-- Generated on: ' . date('Y-m-d H:i:s') . "\n";
    echo '-- Total Records: ' . count($data) . "\n\n";
    
    echo '-- Create programmes table if it doesn\'t exist' . "\n";
    echo 'CREATE TABLE IF NOT EXISTS programmes (' . "\n";
    echo '    id INT AUTO_INCREMENT PRIMARY KEY,' . "\n";
    echo '    title VARCHAR(255) NOT NULL,' . "\n";
    echo '    description TEXT,' . "\n";
    echo '    duration VARCHAR(100),' . "\n";
    echo '    price DECIMAL(10,2),' . "\n";
    echo '    original_price DECIMAL(10,2),' . "\n";
    echo '    category VARCHAR(100),' . "\n";
    echo '    max_participants INT DEFAULT 25,' . "\n";
    echo '    certification VARCHAR(255) DEFAULT \'HRD Corp Certified\',' . "\n";
    echo '    programme_icon VARCHAR(100) DEFAULT \'fas fa-graduation-cap\',' . "\n";
    echo '    programme_badge VARCHAR(100) DEFAULT \'Popular\',' . "\n";
    echo '    location VARCHAR(100) DEFAULT \'Online\',' . "\n";
    echo '    poster_image VARCHAR(500),' . "\n";
    echo '    modules TEXT,' . "\n";
    echo '    schedule_type VARCHAR(100) DEFAULT \'single_day\',' . "\n";
    echo '    start_date DATE,' . "\n";
    echo '    end_date DATE,' . "\n";
    echo '    start_time TIME,' . "\n";
    echo '    end_time TIME,' . "\n";
    echo '    is_active BOOLEAN DEFAULT TRUE,' . "\n";
    echo '    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,' . "\n";
    echo '    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP' . "\n";
    echo ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;' . "\n\n";
    
    if (!empty($data)) {
        echo '-- Insert programme records' . "\n";
        echo 'INSERT INTO programmes (title, description, duration, price, category, max_participants, certification, location, modules, is_active, created_at) VALUES' . "\n";
        
        $values = [];
        foreach ($data as $index => $programme) {
            $title = addslashes($programme['Title']);
            $description = addslashes($programme['Description']);
            $duration = addslashes($programme['Duration']);
            $price = is_numeric(str_replace(['RM ', ','], '', $programme['Price'])) ? str_replace(['RM ', ','], '', $programme['Price']) : 'NULL';
            $category = addslashes($programme['Category']);
            $max_participants = $programme['Max Participants'] !== 'N/A' ? $programme['Max Participants'] : 25;
            $certification = addslashes($programme['Certification']);
            $location = addslashes($programme['Location']);
            $modules = addslashes($programme['Modules']);
            $is_active = ($programme['Status'] === 'Active') ? 1 : 0;
            $created_date = $programme['Created Date'];
            
            $value = "('$title', '$description', '$duration', $price, '$category', $max_participants, '$certification', '$location', '$modules', $is_active, '$created_date')";
            
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
