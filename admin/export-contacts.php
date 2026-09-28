<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    exit('Unauthorized');
}

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
            $where_conditions[] = "(name LIKE ? OR email LIKE ? OR company LIKE ?)";
            $search_param = "%$search_term%";
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
        }
        
        // Status filter
        if (!empty($_GET['filter'])) {
            if ($_GET['filter'] === 'custom') {
                $where_conditions[] = "interest = 'custom'";
            } elseif ($_GET['filter'] && $_GET['filter'] !== 'all') {
                $where_conditions[] = "status = ?";
                $params[] = $_GET['filter'];
            }
        }
        
        // Build the final query
        $sql = "SELECT * FROM contacts";
        if (!empty($where_conditions)) {
            $sql .= " WHERE " . implode(" AND ", $where_conditions);
        }
        $sql .= " ORDER BY created_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $contacts = $stmt->fetchAll();
        
        // Prepare data for export
        $export_data = [];
        foreach ($contacts as $contact) {
            $export_data[] = [
                'ID' => $contact['id'],
                'Name' => $contact['name'],
                'Email' => $contact['email'],
                'Company' => $contact['company'] ?? '',
                'Phone' => $contact['phone'] ?? '',
                'Interest' => $contact['interest'] ?? '',
                'Message' => $contact['message'],
                'Status' => ucfirst($contact['status']),
                'IP Address' => $contact['ip_address'] ?? '',
                'Created Date' => "'" . date('Y-m-d', strtotime($contact['created_at']))
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
    $filename = 'contacts_' . date('Y-m-d_H-i-s') . '.csv';
    
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
    $filename = 'contacts_' . date('Y-m-d_H-i-s') . '.json';
    
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}



function exportSQL($data) {
    $filename = 'contacts_' . date('Y-m-d_H-i-s') . '.sql';
    
    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    echo '-- Contacts Export' . "\n";
    echo '-- Generated on: ' . date('Y-m-d H:i:s') . "\n";
    echo '-- Total Records: ' . count($data) . "\n\n";
    
    echo '-- Create contacts table if it doesn\'t exist' . "\n";
    echo 'CREATE TABLE IF NOT EXISTS contacts (' . "\n";
    echo '    id INT AUTO_INCREMENT PRIMARY KEY,' . "\n";
    echo '    name VARCHAR(255) NOT NULL,' . "\n";
    echo '    email VARCHAR(255) NOT NULL,' . "\n";
    echo '    company VARCHAR(255),' . "\n";
    echo '    phone VARCHAR(50),' . "\n";
    echo '    interest VARCHAR(100),' . "\n";
    echo '    message TEXT NOT NULL,' . "\n";
    echo '    status ENUM(\'new\', \'contacted\', \'qualified\', \'blacklisted\') DEFAULT \'new\',' . "\n";
    echo '    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,' . "\n";
    echo '    ip_address VARCHAR(45),' . "\n";
    echo '    user_agent TEXT' . "\n";
    echo ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;' . "\n\n";
    
    if (!empty($data)) {
        echo '-- Insert contact records' . "\n";
        echo 'INSERT INTO contacts (name, email, company, phone, interest, message, status, ip_address, created_at) VALUES' . "\n";
        
        $values = [];
        foreach ($data as $index => $contact) {
            $name = addslashes($contact['Name']);
            $email = addslashes($contact['Email']);
            $company = addslashes($contact['Company']);
            $phone = addslashes($contact['Phone']);
            $interest = addslashes($contact['Interest']);
            $message = addslashes($contact['Message']);
            $status = strtolower($contact['Status']);
            $ip_address = addslashes($contact['IP Address']);
            $created_date = $contact['Created Date'];
            
            $value = "('$name', '$email', '$company', '$phone', '$interest', '$message', '$status', '$ip_address', '$created_date')";
            
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
