<?php
session_start();

require '../vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Border;

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
            $where_conditions[] = "(name LIKE ? OR position_applied LIKE ? OR reason LIKE ?)";
            $search_param = "%$search_term%";
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
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
        $sql = "SELECT * FROM blacklist";
        if (!empty($where_conditions)) {
            $sql .= " WHERE " . implode(" AND ", $where_conditions);
        }
        $sql .= " ORDER BY date DESC, id DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $blacklist = $stmt->fetchAll();
        
        // Prepare data for export
        $export_data = [];
        foreach ($blacklist as $person) {
            $export_data[] = [
                'ID' => $person['id'],
                'Name' => $person['name'],
                'Position Applied' => $person['position_applied'],
                'Phone Number' => $person['phone_number'],
                'Date' => $person['date'],
                'Reason' => $person['reason']
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
    $filename = 'blacklist_' . date('Y-m-d_H-i-s') . '.csv';
    
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
    $filename = 'blacklist_' . date('Y-m-d_H-i-s') . '.json';
    
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

function exportSQL($data) {
    $filename = 'blacklist_' . date('Y-m-d_H-i-s') . '.sql';
    
    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    echo '-- Blacklist Export' . "\n";
    echo '-- Generated on: ' . date('Y-m-d H:i:s') . "\n";
    echo '-- Total Records: ' . count($data) . "\n\n";
    
    echo '-- Create blacklist table if it doesn\'t exist' . "\n";
    echo 'CREATE TABLE IF NOT EXISTS blacklist (' . "\n";
    echo '    id INT AUTO_INCREMENT PRIMARY KEY,' . "\n";
    echo '    name VARCHAR(255) NOT NULL DEFAULT \'\',' . "\n";
    echo '    position_applied VARCHAR(255) NOT NULL DEFAULT \'\',' . "\n";
    echo '    phone_number VARCHAR(255) NOT NULL DEFAULT \'\',' . "\n";
    echo '    date VARCHAR(255) NOT NULL DEFAULT \'\',' . "\n";
    echo '    reason TEXT NOT NULL' . "\n";
    echo ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;' . "\n\n";
    
    if (!empty($data)) {
        echo '-- Insert blacklist records' . "\n";
        echo 'INSERT INTO blacklist (id, name, position_applied, phone_number, date, reason) VALUES' . "\n";
        
        $values = [];
        foreach ($data as $index => $person) {
            $id = intval($person['ID']);
            $name = addslashes($person['Name']);
            $position = addslashes($person['Position Applied']);
            $phone = addslashes($person['Phone Number']);
            $date = addslashes($person['Date']);
            $reason = addslashes($person['Reason']);
            
            $value = "($id, '$name', '$position', '$phone', '$date', '$reason')";
            
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

function exportXLSX($data) {
    $filename = 'blacklist_' . date('Y-m-d_H-i-s') . '.xlsx';
 
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
?>
