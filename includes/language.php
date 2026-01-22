<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set default language
if (!isset($_SESSION['lang'])) {
    $_SESSION['lang'] = 'en'; // Default to English
}

// Handle language change
if (isset($_GET['lang']) && in_array($_GET['lang'], ['en', 'si'])) {
    $_SESSION['lang'] = $_GET['lang'];

    // Redirect to remove lang parameter from URL
    $redirect_url = strtok($_SERVER['REQUEST_URI'], '?');
    if (!empty($_SERVER['QUERY_STRING'])) {
        parse_str($_SERVER['QUERY_STRING'], $params);
        unset($params['lang']);
        if (!empty($params)) {
            $redirect_url .= '?' . http_build_query($params);
        }
    }
    header("Location: " . $redirect_url);
    exit();
}

// Load language file
$lang_file = __DIR__ . '/languages/' . $_SESSION['lang'] . '.php';
if (file_exists($lang_file)) {
    require_once $lang_file;
} else {
    // Fallback to English
    require_once __DIR__ . '/languages/en.php';
}

/**
 * Translation function
 */
function __($key)
{
    global $translations;
    return $translations[$key] ?? $key;
}

/**
 * Get current language
 */
function getCurrentLanguage()
{
    return $_SESSION['lang'] ?? 'en';
}

/**
 * Get language name
 */
function getLanguageName($lang = null)
{
    $lang = $lang ?? getCurrentLanguage();
    $names = [
        'en' => 'English',
        'si' => 'සිංහල'
    ];
    return $names[$lang] ?? 'English';
}
