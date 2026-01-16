<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../classes/Production.php';

require_login();
require_permission('production', 'create');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_response('Invalid request method', 405);
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    error_response('Invalid CSRF token', 403);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    $production = new Production($db);

    $data = [
        'order_date' => $_POST['order_date'],
        'production_center_id' => (int)$_POST['production_center_id'],
        'production_line_id' => !empty($_POST['production_line_id']) ? (int)$_POST['production_line_id'] : null,
        'product_id' => (int)$_POST['product_id'],
        'planned_quantity' => (float)$_POST['planned_quantity'],
        'start_date' => $_POST['start_date'],
        'end_date' => $_POST['end_date'],
        'notes' => sanitize_input($_POST['notes'] ?? ''),
        'inputs' => $_POST['inputs'] ?? []
    ];

    $result = $production->createOrder($data);

    if ($result['success']) {
        log_audit($_SESSION['user_id'], 'create_production_order', 'production_orders', $result['order_id']);
        success_response('Production order created successfully', $result);
    } else {
        error_response($result['message'], 500);
    }
} catch (Exception $e) {
    error_log("Production Order Error: " . $e->getMessage());
    error_response($e->getMessage(), 500);
}
