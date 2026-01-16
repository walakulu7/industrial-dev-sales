<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

require_login();

header('Content-Type: application/json');

$center_id = (int)($_GET['center_id'] ?? 0);

if (!$center_id) {
    echo json_encode([]);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $query = "SELECT line_id, line_code, line_name 
              FROM production_lines 
              WHERE production_center_id = :center_id AND status = 'active'
              ORDER BY line_name";

    $stmt = $db->prepare($query);
    $stmt->execute([':center_id' => $center_id]);
    $lines = $stmt->fetchAll();

    echo json_encode($lines);
} catch (Exception $e) {
    echo json_encode([]);
}
