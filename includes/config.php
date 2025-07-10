<?php
/**
 * Configuration file for PDF Tools Pro
 * 
 * This file contains all the configuration settings for the application.
 * Update these values based on your production environment.
 */

// Environment setting (development, staging, production)
define('ENVIRONMENT', 'production');

// Error reporting based on environment
if (ENVIRONMENT === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('log_errors', 1);
} else {
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
}

// Site configuration
define('SITE_NAME', 'PDF Tools Pro');
define('SITE_URL', 'https://pdftoolspro.com');
define('SITE_EMAIL', 'support@pdftoolspro.com');
define('ADMIN_EMAIL', 'admin@pdftoolspro.com');

// File handling configuration
define('MAX_FILE_SIZE', 50 * 1024 * 1024); // 50MB
define('ALLOWED_FILE_TYPES', ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png']);
define('FILE_RETENTION_TIME', 3600); // 1 hour in seconds

// Directory configuration
define('BASE_PATH', dirname(__DIR__));
define('UPLOAD_DIR', BASE_PATH . '/uploads/');
define('TEMP_DIR', BASE_PATH . '/temp/');
define('LOG_DIR', BASE_PATH . '/logs/');

// Security configuration
define('CSRF_TOKEN_NAME', 'csrf_token');
define('SESSION_LIFETIME', 3600); // 1 hour
define('RATE_LIMIT_ATTEMPTS', 10); // Max attempts per hour
define('RATE_LIMIT_WINDOW', 3600); // 1 hour in seconds

// Ghostscript configuration
define('GS_PATH', '/usr/bin/gs'); // Docker path
define('GS_FALLBACK_PATH', 'gs'); // Fallback to system PATH

// ImageMagick configuration
define('MAGICK_PATH', '/usr/bin/convert'); // Docker path
define('MAGICK_FALLBACK_PATH', 'convert'); // Fallback to system PATH

// Email configuration
define('SMTP_ENABLED', false); // Set to true if using SMTP
define('SMTP_HOST', 'smtp.example.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your-smtp-username');
define('SMTP_PASSWORD', 'your-smtp-password');
define('SMTP_ENCRYPTION', 'tls');

// Analytics configuration (optional)
define('GOOGLE_ANALYTICS_ID', ''); // Add your GA tracking ID if needed

// Maintenance mode
define('MAINTENANCE_MODE', false);
define('MAINTENANCE_MESSAGE', 'We are currently performing maintenance. Please check back later.');

// Performance settings
define('ENABLE_CACHE', true);
define('CACHE_LIFETIME', 3600); // 1 hour

// Database configuration (if needed in future)
define('DB_ENABLED', false);
define('DB_HOST', 'localhost');
define('DB_NAME', 'pdftoolspro');
define('DB_USER', 'root');
define('DB_PASS', '');

// API Keys (if integrating with external services)
define('RECAPTCHA_ENABLED', false);
define('RECAPTCHA_SITE_KEY', '');
define('RECAPTCHA_SECRET_KEY', '');

// Timezone
date_default_timezone_set('UTC');

// Session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', ENVIRONMENT === 'production' ? 1 : 0);
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.gc_maxlifetime', SESSION_LIFETIME);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load functions
require_once BASE_PATH . '/includes/functions.php';