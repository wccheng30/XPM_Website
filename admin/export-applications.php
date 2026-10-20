<?php

require_once '../includes/database.php';
require '../vendor/autoload.php';
session_start();


use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Border;

require_once '../includes/auth.php';
auth_require(['admin', 'manager'], '403');

// Include database connection

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
            $where_conditions[] = "(a.name LIKE ? OR a.email LIKE ? OR a.phone LIKE ? OR j.title LIKE ?)";
            $search_param = "%$search_term%";
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
        }
        
        // Status filter
        if (!empty($_GET['filter'])) {
            $where_conditions[] = "a.status = ?";
            $params[] = $_GET['filter'];
        }

        if (!empty($_GET['ids'])) {
            $ids = array_values(array_filter(array_map('intval', explode(',', (string) $_GET['ids']))));
            if ($ids) {
                $where_conditions[] = 'a.id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
                foreach ($ids as $id) {
                    $params[] = $id;
                }
            }
        }
        
        // Build the final query
        $sql = "SELECT a.*, j.title as job_title 
                FROM applications a 
                LEFT JOIN jobs j ON a.job_id = j.id";
        if (!empty($where_conditions)) {
            $sql .= " WHERE " . implode(" AND ", $where_conditions);
        }
        $sql .= " ORDER BY a.created_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $applications = $stmt->fetchAll();
        
        // Prepare data for export
        $export_data = [];
        foreach ($applications as $application) {
            $export_data[] = [
                'ID' => $application['id'],
                'Candidate Name' => $application['name'],
                'Email' => $application['email'],
                'Phone' => $application['phone'] ?? '',
                'IC Number' => $application['ic_number'] ?? '',
                'Address' => $application['address'] ?? '',
                'State' => $application['state'] ?? '',
                'City' => $application['city'] ?? '',
                'Age' => $application['age'] ?? '',
                'Weight (kg)' => $application['weight'] ?? '',
                'Height (cm)' => $application['height'] ?? '',
                'Gender' => $application['gender'] ?? '',
                'Race' => $application['race'] ?? '',
                'Nationality' => $application['nationality'] ?? '',
                'Marital Status' => $application['marital_status'] ?? '',
                'Religion' => $application['religion'] ?? '',
                'Smoker' => $application['smoker'] ?? '',
                'Convicted of crime' => $application['convicted_of_crime'] ?? '',
                'Willing to relocate' => $application['willing_to_relocate'] ?? '',
                'Own transportation' => $application['own_transportation'] ?? '',
                'Having relative in company' => $application['having_relative_in_company'] ?? '',
                'Able to work shift' => $application['able_to_work_shift'] ?? '',
                'Current health / medication' => $application['current_health_medication'] ?? '',
                'Job Title' => $application['job_title'] ?? 'N/A',
                'Status' => ucfirst($application['status']),
                'Applied Date' => "'" . date('Y-m-d', strtotime($application['created_at'])),
                'Resume Path' => $application['resume_path'] ?? '',
                'Cover Letter Path' => $application['cover_letter_path'] ?? '',
                'Interviewer Comment' => $application['interviewer_comment'] ?? ''
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
            case 'excel':
                exportXLSX($export_data);
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
    $filename = 'job_applications_' . date('Y-m-d_H-i-s') . '.csv';
    
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

function exportXLSX($data) {
    $filename = 'job_applications_' . date('Y-m-d_H-i-s') . '.xlsx';
 
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
 
    // Offset: start the table 3 rows down, 3 columns right (so A1 -> D4)
    $startRow = 4;
    $startColIndex = 4; // D
    $startCol = Coordinate::stringFromColumnIndex($startColIndex);
    $startCell = $startCol . $startRow;
 
    if (!empty($data)) {
        // Write headers
        $headers = array_keys($data[0]);
        $sheet->fromArray($headers, null, $startCell);
 
        // Write data (row after headers)
        $dataStartCell = $startCol . ($startRow + 1);
        $sheet->fromArray(array_values($data), null, $dataStartCell);
 
        // Figure out the table's full range
        $endColIndex = $startColIndex + count($headers) - 1;
        $endCol = Coordinate::stringFromColumnIndex($endColIndex);
        $endRow = $startRow + count($data);
        $headerRange = $startCol . $startRow . ':' . $endCol . $startRow;
        $fullRange = $startCol . $startRow . ':' . $endCol . $endRow;
 
        // Bold header row
        $sheet->getStyle($headerRange)->getFont()->setBold(true);
 
        // Border separating header from content
        $sheet->getStyle($headerRange)->getBorders()
              ->getBottom()->setBorderStyle(Border::BORDER_MEDIUM);
 
        // Border around the entire table
        $sheet->getStyle($fullRange)->getBorders()
              ->getOutline()->setBorderStyle(Border::BORDER_THIN);
 
        // Auto-size only the table's columns
        foreach (range($startCol, $endCol) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }
 
    // Headers for XLSX download
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
 
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}

function exportJSON($data) {
    $filename = 'job_applications_' . date('Y-m-d_H-i-s') . '.json';
    
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}



function exportSQL($data) {
    $filename = 'job_applications_' . date('Y-m-d_H-i-s') . '.sql';
    
    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    echo '-- Job Applications Export' . "\n";
    echo '-- Generated on: ' . date('Y-m-d H:i:s') . "\n";
    echo '-- Total Records: ' . count($data) . "\n\n";
    
    echo '-- Create applications table if it doesn\'t exist' . "\n";
    echo 'CREATE TABLE IF NOT EXISTS applications (' . "\n";
    echo '    id INT AUTO_INCREMENT PRIMARY KEY,' . "\n";
    echo '    job_id INT,' . "\n";
    echo '    name VARCHAR(255) NOT NULL,' . "\n";
    echo '    email VARCHAR(255) NOT NULL,' . "\n";
    echo '    phone VARCHAR(50),' . "\n";
    echo '    resume_path VARCHAR(500),' . "\n";
    echo '    cover_letter_path VARCHAR(500),' . "\n";
    echo '    status ENUM(\'pending\', \'reviewed\', \'shortlisted\', \'rejected\', \'waiting for interview\', \'hired\', \'blacklisted\') DEFAULT \'pending\',' . "\n";
    echo '    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,' . "\n";
    echo '    FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE SET NULL' . "\n";
    echo ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;' . "\n\n";
    
    if (!empty($data)) {
        echo '-- Insert application records' . "\n";
        echo 'INSERT INTO applications (name, email, phone, status, created_at) VALUES' . "\n";
        
        $values = [];
        foreach ($data as $index => $application) {
            $name = addslashes($application['Candidate Name']);
            $email = addslashes($application['Email']);
            $phone = addslashes($application['Phone']);
            $status = strtolower($application['Status']);
            $created_date = $application['Applied Date'];
            
            $value = "('$name', '$email', '$phone', '$status', '$created_date')";
            
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
