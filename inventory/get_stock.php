<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

require_login();

header('Content-Type: application/json');

$warehouse_id = (int)($_GET['warehouse_id'] ?? 0);
$product_id = (int)($_GET['product_id'] ?? 0);

if (!$warehouse_id || !$product_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $query = "SELECT quantity_available FROM inventory 
              WHERE warehouse_id = :warehouse_id AND product_id = :product_id";

    $stmt = $db->prepare($query);
    $stmt->execute([
        ':warehouse_id' => $warehouse_id,
        ':product_id' => $product_id
    ]);

    $result = $stmt->fetch();

    if ($result) {
        echo json_encode([
            'success' => true,
            'quantity' => $result['quantity_available']
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'quantity' => 0
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
