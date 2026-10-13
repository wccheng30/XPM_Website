<?php
require_once '../includes/auth.php';
auth_require(['admin', 'manager']);

// Include database connection
require_once '../includes/database.php';

$pdo = get_db_connection();

if (!$pdo) {
    die("Database connection failed.");
}

// Get export format
$format = $_GET['format'] ?? 'csv';

try {
    // Get trainers data
    $params = [];
    $sql = "SELECT * FROM trainers";
    if (!empty($_GET['ids'])) {
        $ids = array_values(array_filter(array_map('intval', explode(',', (string) $_GET['ids']))));
        if ($ids) {
            $sql .= ' WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
            $params = $ids;
        }
    }
    $sql .= " ORDER BY display_order ASC, name ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $trainers = $stmt->fetchAll();
    
    if ($format === 'csv') {
        // Export as CSV
        $filename = 'trainers_' . date('Y-m-d_H-i-s') . '.csv';
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // Add BOM for UTF-8
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // CSV headers
        fputcsv($output, [
            'ID',
            'Name',
            'Title',
            'Experience Years',
            'Image Path',
            'Description',
            'Specialties',
            'Credentials',
            'Is Active',
            'Display Order',
            'Created At',
            'Updated At'
        ]);
        
        // CSV data
        foreach ($trainers as $trainer) {
            $specialties = $trainer['specialties'] ? implode('; ', json_decode($trainer['specialties'], true) ?: []) : '';
            $credentials = $trainer['credentials'] ? implode('; ', json_decode($trainer['credentials'], true) ?: []) : '';
            
            fputcsv($output, [
                $trainer['id'],
                $trainer['name'],
                $trainer['title'],
                $trainer['experience_years'],
                $trainer['image_path'],
                $trainer['description'],
                $specialties,
                $credentials,
                $trainer['is_active'] ? 'Yes' : 'No',
                $trainer['display_order'],
                $trainer['created_at'],
                $trainer['updated_at']
            ]);
        }
        
        fclose($output);
        
    } elseif ($format === 'excel') {
        // Export as Excel (HTML table)
        $filename = 'trainers_' . date('Y-m-d_H-i-s') . '.xls';
        
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        echo '<html><head><meta charset="utf-8"></head><body>';
        echo '<table border="1">';
        echo '<tr>';
        echo '<th scope="col">ID</th>';
        echo '<th scope="col">Name</th>';
        echo '<th scope="col">Title</th>';
        echo '<th scope="col">Experience Years</th>';
        echo '<th scope="col">Image Path</th>';
        echo '<th scope="col">Description</th>';
        echo '<th scope="col">Specialties</th>';
        echo '<th scope="col">Credentials</th>';
        echo '<th scope="col">Is Featured</th>';
        echo '<th scope="col">Is Active</th>';
        echo '<th scope="col">Display Order</th>';
        echo '<th scope="col">Created At</th>';
        echo '<th scope="col">Updated At</th>';
        echo '</tr>';
        
        foreach ($trainers as $trainer) {
            $specialties = $trainer['specialties'] ? implode('; ', json_decode($trainer['specialties'], true) ?: []) : '';
            $credentials = $trainer['credentials'] ? implode('; ', json_decode($trainer['credentials'], true) ?: []) : '';
            
            echo '<tr>';
            echo '<td>' . htmlspecialchars($trainer['id']) . '</td>';
            echo '<td>' . htmlspecialchars($trainer['name']) . '</td>';
            echo '<td>' . htmlspecialchars($trainer['title']) . '</td>';
            echo '<td>' . htmlspecialchars($trainer['experience_years']) . '</td>';
            echo '<td>' . htmlspecialchars($trainer['image_path']) . '</td>';
            echo '<td>' . htmlspecialchars($trainer['description']) . '</td>';
            echo '<td>' . htmlspecialchars($specialties) . '</td>';
            echo '<td>' . htmlspecialchars($credentials) . '</td>';
            echo '<td>' . ($trainer['is_active'] ? 'Yes' : 'No') . '</td>';
            echo '<td>' . htmlspecialchars($trainer['display_order']) . '</td>';
            echo '<td>' . htmlspecialchars($trainer['created_at']) . '</td>';
            echo '<td>' . htmlspecialchars($trainer['updated_at']) . '</td>';
            echo '</tr>';
        }
        
        echo '</table>';
        echo '</body></html>';
        
    } elseif ($format === 'json') {
        // Export as JSON
        $filename = 'trainers_' . date('Y-m-d_H-i-s') . '.json';
        
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        // Process trainers data for JSON export
        $export_data = [];
        foreach ($trainers as $trainer) {
            $export_data[] = [
                'id' => $trainer['id'],
                'name' => $trainer['name'],
                'title' => $trainer['title'],
                'experience_years' => $trainer['experience_years'],
                'image_path' => $trainer['image_path'],
                'description' => $trainer['description'],
                'specialties' => $trainer['specialties'] ? json_decode($trainer['specialties'], true) : [],
                'credentials' => $trainer['credentials'] ? json_decode($trainer['credentials'], true) : [],
                'is_active' => (bool)$trainer['is_active'],
                'display_order' => $trainer['display_order'],
                'created_at' => $trainer['created_at'],
                'updated_at' => $trainer['updated_at']
            ];
        }
        
        echo json_encode($export_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
    } else {
        // Invalid format
        header('Location: trainers.php?error=Invalid export format');
        exit;
    }
    
} catch (PDOException $e) {
    header('Location: trainers.php?error=' . urlencode('Export failed.'));
    exit;
}
?>
