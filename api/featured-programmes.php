<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

require_once '../includes/database.php';

try {
    $pdo = get_db_connection();
    
    // Fetch active and featured programmes
    $stmt = $pdo->prepare("
        SELECT id, title, description, category, duration, price, 
               tax_inclusive, tax_amount, original_price, max_participants, 
               certification, location, start_date, end_date, 
               start_time, end_time, programme_icon, programme_badge, 
               schedule_type, modules, poster_image 
        FROM programmes 
        WHERE is_active = 1 AND is_featured = 1 
        ORDER BY updated_at DESC
    ");
    $stmt->execute();
    $programmes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Add display price handling for consistency
    foreach ($programmes as &$programme) {
        // If tax exclusive, price doesn't change
        // If tax inclusive, price doesn't change (the original display logic fixed it)
        $programme['display_price'] = $programme['price'];
        if (is_numeric($programme['price'])) {
            $programme['formatted_price'] = 'RM ' . number_format($programme['price'], 2);
        } else {
            $programme['formatted_price'] = $programme['price'];
        }
    }
    
    echo json_encode([
        'success' => true,
        'count' => count($programmes),
        'data' => $programmes
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'An error occurred while fetching featured programmes.'
    ]);
}
?>
