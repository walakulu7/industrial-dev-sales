<?php

/**
 * Application Configuration
 * Main configuration file for the system
 */

// Error Reporting (Set to 0 in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

// Timezone
date_default_timezone_set('Asia/Colombo');

// Session Configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // Set to 1 for HTTPS
session_start();

// Application Settings
define('APP_NAME', 'Textile Management System');
define('APP_VERSION', '1.0.0');
define('BASE_URL', 'http://localhost/textile_system');
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('MAX_UPLOAD_SIZE', 5242880); // 5MB

// Security
define('CSRF_TOKEN_NAME', 'csrf_token');
define('SESSION_TIMEOUT', 3600); // 1 hour

// Pagination
define('RECORDS_PER_PAGE', 25);

// Date Format
define('DATE_FORMAT', 'Y-m-d');
define('DATETIME_FORMAT', 'Y-m-d H:i:s');
define('DISPLAY_DATE_FORMAT', 'd/m/Y');
define('DISPLAY_DATETIME_FORMAT', 'd/m/Y h:i A');

// Currency
define('CURRENCY_SYMBOL', 'LKR');
define('CURRENCY_POSITION', 'before'); // before or after

// System Status
define('SYSTEM_MAINTENANCE', false);
define('ALLOW_REGISTRATION', false);

// File Upload Types
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif']);
define('ALLOWED_DOCUMENT_TYPES', ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']);

// Email Configuration (if needed)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', '');
define('SMTP_PASSWORD', '');
define('SMTP_FROM_EMAIL', 'noreply@textile.lk');
define('SMTP_FROM_NAME', 'Textile Management System');

// System Email
define('ADMIN_EMAIL', 'admin@textile.lk');
define('SUPPORT_EMAIL', 'support@textile.lk');
