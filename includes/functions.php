<?php

/**
 * Common Functions
 * Utility functions used throughout the application
 */

/**
 * Sanitize input data
 * @param mixed $data Input data to sanitize
 * @return mixed Sanitized data
 */
function sanitize_input($data)
{
    if (is_array($data)) {
        return array_map('sanitize_input', $data);
    }
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Check if user is logged in
 * @return bool
 */
function is_logged_in()
{
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Require login - redirect if not logged in
 */
function require_login()
{
    if (!is_logged_in()) {
        header("Location: " . BASE_URL . "/login.php");
        exit();
    }

    // Check session timeout
    if (
        isset($_SESSION['last_activity']) &&
        (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)
    ) {
        session_unset();
        session_destroy();
        header("Location: " . BASE_URL . "/login.php?timeout=1");
        exit();
    }

    $_SESSION['last_activity'] = time();
}

/**
 * Check user permission
 * @param string $module Module name
 * @param string $action Action name
 * @return bool
 */
function has_permission($module, $action)
{
    if (!isset($_SESSION['permissions'])) {
        return false;
    }

    $permissions = json_decode($_SESSION['permissions'], true);

    // Administrator has all permissions
    if (in_array('all', $permissions['modules'])) {
        return true;
    }

    return in_array($module, $permissions['modules']) &&
        in_array($action, $permissions['permissions']);
}

/**
 * Require permission - show error if no permission
 * @param string $module Module name
 * @param string $action Action name
 */
function require_permission($module, $action)
{
    if (!has_permission($module, $action)) {
        http_response_code(403);
        die("Access Denied: You don't have permission to perform this action.");
    }
}

/**
 * Generate CSRF Token
 * @return string
 */
function generate_csrf_token()
{
    if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

/**
 * Verify CSRF Token
 * @param string $token Token to verify
 * @return bool
 */
function verify_csrf_token($token)
{
    return isset($_SESSION[CSRF_TOKEN_NAME]) &&
        hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

/**
 * Format currency
 * @param float $amount Amount to format
 * @param string $currency Currency symbol
 * @return string
 */
function format_currency($amount, $currency = CURRENCY_SYMBOL)
{
    $formatted = number_format($amount, 2);

    if (CURRENCY_POSITION === 'before') {
        return $currency . ' ' . $formatted;
    } else {
        return $formatted . ' ' . $currency;
    }
}

/**
 * Format date
 * @param string $date Date string
 * @param string $format Output format
 * @return string
 */
function format_date($date, $format = DISPLAY_DATE_FORMAT)
{
    if (empty($date) || $date == '0000-00-00') {
        return '-';
    }
    return date($format, strtotime($date));
}

/**
 * Format datetime
 * @param string $datetime Datetime string
 * @param string $format Output format
 * @return string
 */
function format_datetime($datetime, $format = DISPLAY_DATETIME_FORMAT)
{
    if (empty($datetime)) {
        return '-';
    }
    return date($format, strtotime($datetime));
}

/**
 * Generate random string
 * @param int $length Length of string
 * @return string
 */
function generate_random_string($length = 10)
{
    return bin2hex(random_bytes($length / 2));
}

/**
 * Log audit trail
 * @param int $user_id User ID
 * @param string $action_type Action type
 * @param string $table_name Table name
 * @param int $record_id Record ID
 * @param array $old_values Old values
 * @param array $new_values New values
 */
function log_audit(
    $user_id,
    $action_type,
    $table_name = null,
    $record_id = null,
    $old_values = null,
    $new_values = null
) {
    try {
        $database = new Database();
        $db = $database->getConnection();

        $query = "INSERT INTO audit_log 
                  (user_id, action_type, table_name, record_id, old_values, new_values, 
                   ip_address, user_agent) 
                  VALUES 
                  (:user_id, :action_type, :table_name, :record_id, :old_values, :new_values, 
                   :ip_address, :user_agent)";

        $stmt = $db->prepare($query);

        $stmt->execute([
            ':user_id' => $user_id,
            ':action_type' => $action_type,
            ':table_name' => $table_name,
            ':record_id' => $record_id,
            ':old_values' => $old_values ? json_encode($old_values) : null,
            ':new_values' => $new_values ? json_encode($new_values) : null,
            ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    } catch (Exception $e) {
        error_log("Audit Log Error: " . $e->getMessage());
    }
}

/**
 * Send JSON response
 * @param array $data Data to send
 * @param int $status_code HTTP status code
 */
function json_response($data, $status_code = 200)
{
    http_response_code($status_code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}

/**
 * Success response
 * @param string $message Success message
 * @param mixed $data Additional data
 */
function success_response($message, $data = null)
{
    json_response([
        'success' => true,
        'message' => $message,
        'data' => $data
    ]);
}

/**
 * Error response
 * @param string $message Error message
 * @param int $status_code HTTP status code
 */
function error_response($message, $status_code = 400)
{
    json_response([
        'success' => false,
        'message' => $message
    ], $status_code);
}

/**
 * Validate required fields
 * @param array $data Data to validate
 * @param array $fields Required fields
 * @return array Errors
 */
function validate_required($data, $fields)
{
    $errors = [];
    foreach ($fields as $field) {
        if (!isset($data[$field]) || empty(trim($data[$field]))) {
            $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
        }
    }
    return $errors;
}

/**
 * Validate email
 * @param string $email Email to validate
 * @return bool
 */
function validate_email($email)
{
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Validate phone number (Sri Lankan format)
 * @param string $phone Phone number
 * @return bool
 */
function validate_phone($phone)
{
    $phone = preg_replace('/[\s\-]/', '', $phone);
    return preg_match('/^(?:\+94|0)?[0-9]{9,10}$/', $phone);
}

/**
 * Get client IP address
 * @return string
 */
function get_client_ip()
{
    $ipaddress = '';
    if (isset($_SERVER['HTTP_CLIENT_IP']))
        $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
    else if (isset($_SERVER['HTTP_X_FORWARDED_FOR']))
        $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
    else if (isset($_SERVER['HTTP_X_FORWARDED']))
        $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
    else if (isset($_SERVER['HTTP_FORWARDED_FOR']))
        $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
    else if (isset($_SERVER['HTTP_FORWARDED']))
        $ipaddress = $_SERVER['HTTP_FORWARDED'];
    else if (isset($_SERVER['REMOTE_ADDR']))
        $ipaddress = $_SERVER['REMOTE_ADDR'];
    else
        $ipaddress = 'UNKNOWN';
    return $ipaddress;
}

/**
 * Calculate pagination
 * @param int $total_records Total records
 * @param int $current_page Current page
 * @param int $records_per_page Records per page
 * @return array Pagination data
 */
function calculate_pagination($total_records, $current_page = 1, $records_per_page = RECORDS_PER_PAGE)
{
    $total_pages = ceil($total_records / $records_per_page);
    $offset = ($current_page - 1) * $records_per_page;

    return [
        'total_records' => $total_records,
        'total_pages' => $total_pages,
        'current_page' => $current_page,
        'records_per_page' => $records_per_page,
        'offset' => $offset,
        'has_previous' => $current_page > 1,
        'has_next' => $current_page < $total_pages
    ];
}

/**
 * Render pagination links
 * @param array $pagination Pagination data
 * @param string $base_url Base URL
 * @return string HTML
 */
function render_pagination($pagination, $base_url)
{
    if ($pagination['total_pages'] <= 1) {
        return '';
    }

    $html = '<nav aria-label="Page navigation"><ul class="pagination">';

    // Previous
    if ($pagination['has_previous']) {
        $html .= '<li class="page-item">';
        $html .= '<a class="page-link" href="' . $base_url . '&page=' . ($pagination['current_page'] - 1) . '">Previous</a>';
        $html .= '</li>';
    }

    // Page numbers
    $start = max(1, $pagination['current_page'] - 2);
    $end = min($pagination['total_pages'], $pagination['current_page'] + 2);

    for ($i = $start; $i <= $end; $i++) {
        $active = ($i == $pagination['current_page']) ? 'active' : '';
        $html .= '<li class="page-item ' . $active . '">';
        $html .= '<a class="page-link" href="' . $base_url . '&page=' . $i . '">' . $i . '</a>';
        $html .= '</li>';
    }

    // Next
    if ($pagination['has_next']) {
        $html .= '<li class="page-item">';
        $html .= '<a class="page-link" href="' . $base_url . '&page=' . ($pagination['current_page'] + 1) . '">Next</a>';
        $html .= '</li>';
    }

    $html .= '</ul></nav>';

    return $html;
}

/**
 * Redirect with message
 * @param string $url URL to redirect to
 * @param string $message Message to display
 * @param string $type Message type (success, error, info, warning)
 */
function redirect_with_message($url, $message, $type = 'success')
{
    $_SESSION[$type] = $message;
    header("Location: $url");
    exit();
}

/**
 * Get flash message
 * @param string $type Message type
 * @return string|null
 */
function get_flash_message($type = 'success')
{
    if (isset($_SESSION[$type])) {
        $message = $_SESSION[$type];
        unset($_SESSION[$type]);
        return $message;
    }
    return null;
}

/**
 * Display flash message
 * @param string $type Message type
 * @return string HTML
 */
function display_flash_message($type = 'success')
{
    $message = get_flash_message($type);
    if ($message) {
        $class_map = [
            'success' => 'alert-success',
            'error' => 'alert-danger',
            'info' => 'alert-info',
            'warning' => 'alert-warning'
        ];
        $class = $class_map[$type] ?? 'alert-info';

        return '<div class="alert ' . $class . ' alert-dismissible fade show" role="alert">' .
            htmlspecialchars($message) .
            '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>' .
            '</div>';
    }
    return '';
}

/**
 * Upload file
 * @param array $file File from $_FILES
 * @param string $destination Destination directory
 * @param array $allowed_types Allowed MIME types
 * @return array Result
 */
function upload_file($file, $destination, $allowed_types = null)
{
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'File upload error'];
    }

    // Check file size
    if ($file['size'] > MAX_UPLOAD_SIZE) {
        return ['success' => false, 'message' => 'File too large'];
    }

    // Check file type
    if ($allowed_types && !in_array($file['type'], $allowed_types)) {
        return ['success' => false, 'message' => 'Invalid file type'];
    }

    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '.' . $extension;
    $filepath = $destination . $filename;

    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['success' => true, 'filename' => $filename, 'filepath' => $filepath];
    }

    return ['success' => false, 'message' => 'Failed to move uploaded file'];
}
