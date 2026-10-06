<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    exit('Unauthorized');
}

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
            $where_conditions[] = "(pr.name LIKE ? OR pr.email LIKE ? OR pr.phone LIKE ? OR pr.company LIKE ? OR pr.position LIKE ? OR p.title LIKE ?)";
            $search_param = "%$search_term%";
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
        }
        
        // Status filter
        if (!empty($_GET['filter'])) {
            $where_conditions[] = "pr.status = ?";
            $params[] = $_GET['filter'];
        }
        
        // Build the final query
        $sql = "SELECT pr.*, p.title as programme_title, p.duration, p.price, p.modules 
                FROM programme_registrations pr 
                LEFT JOIN programmes p ON pr.programme_id = p.id";
        if (!empty($where_conditions)) {
            $sql .= " WHERE " . implode(" AND ", $where_conditions);
        }
        $sql .= " ORDER BY pr.created_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $registrations = $stmt->fetchAll();
        
        // Prepare data for export
        $export_data = [];
        foreach ($registrations as $registration) {
            $export_data[] = [
                'ID' => $registration['id'],
                'Participant Name' => $registration['name'],
                'Email' => $registration['email'],
                'Phone' => $registration['phone'] ?? '',
                'IC Number' => $registration['ic_number'] ?? '',
                'Company' => $registration['company'] ?? '',
                'Position' => $registration['position'] ?? '',
                'Programme Title' => $registration['programme_title'] ?? 'N/A',
                'Duration' => $registration['duration'] ?? 'N/A',
                'Price' => $registration['price'] ? formatPriceForExport($registration['price']) : 'N/A',
                'Key Modules' => $registration['modules'] ?? 'N/A',
                'Status' => ucfirst($registration['status']),
                'Registered Date' => "'" . date('Y-m-d', strtotime($registration['created_at'])),
                'Additional Notes' => $registration['additional_notes'] ?? ''
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
    $filename = 'programme_registrations_' . date('Y-m-d_H-i-s') . '.csv';
    
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
    $filename = 'programme_registrations_' . date('Y-m-d_H-i-s') . '.json';
    
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}


function exportSQL($data) {
    $filename = 'programme_registrations_' . date('Y-m-d_H-i-s') . '.sql';
    
    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    echo '-- Programme Registrations Export' . "\n";
    echo '-- Generated on: ' . date('Y-m-d H:i:s') . "\n";
    echo '-- Total Records: ' . count($data) . "\n\n";
    
    echo '-- Create programme_registrations table if it doesn\'t exist' . "\n";
    echo 'CREATE TABLE IF NOT EXISTS programme_registrations (' . "\n";
    echo '    id INT AUTO_INCREMENT PRIMARY KEY,' . "\n";
    echo '    programme_id INT,' . "\n";
    echo '    name VARCHAR(255) NOT NULL,' . "\n";
    echo '    email VARCHAR(255) NOT NULL,' . "\n";
    echo '    phone VARCHAR(50),' . "\n";
    echo '    company VARCHAR(255),' . "\n";
    echo '    position VARCHAR(255),' . "\n";
    echo '    status ENUM(\'pending\', \'confirmed\', \'cancelled\', \'completed\') DEFAULT \'pending\',' . "\n";
    echo '    additional_notes TEXT,' . "\n";
    echo '    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,' . "\n";
    echo '    FOREIGN KEY (programme_id) REFERENCES programmes(id) ON DELETE SET NULL' . "\n";
    echo ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;' . "\n\n";
    
    if (!empty($data)) {
        echo '-- Insert registration records' . "\n";
        echo 'INSERT INTO programme_registrations (name, email, phone, company, position, status, additional_notes, created_at) VALUES' . "\n";
        
        $values = [];
        foreach ($data as $index => $registration) {
            $name = addslashes($registration['Participant Name']);
            $email = addslashes($registration['Email']);
            $phone = addslashes($registration['Phone']);
            $company = addslashes($registration['Company']);
            $position = addslashes($registration['Position']);
            $status = strtolower($registration['Status']);
            $notes = addslashes($registration['Additional Notes']);
            $created_date = $registration['Registered Date'];
            
            $value = "('$name', '$email', '$phone', '$company', '$position', '$status', '$notes', '$created_date')";
            
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
