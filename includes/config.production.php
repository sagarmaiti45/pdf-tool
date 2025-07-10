<?php
// Production configuration overrides

// Use Railway's PORT environment variable
if (getenv('RAILWAY_ENVIRONMENT') === 'production') {
    // Site configuration
    define('SITE_URL', 'https://' . $_SERVER['HTTP_HOST']);
    
    // Error reporting for production
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    
    // Use Railway's temporary directory
    define('TEMP_DIR', sys_get_temp_dir() . '/pdf_tools/');
    
    // Ensure directories exist
    if (!file_exists(TEMP_DIR)) {
        mkdir(TEMP_DIR, 0777, true);
    }
}