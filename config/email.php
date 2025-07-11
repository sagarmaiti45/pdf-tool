<?php
// Email configuration

// SMTP Configuration - Using environment variables for security
define('SMTP_HOST', getenv('SMTP_HOST') ?: 'smtp.hostinger.com');
define('SMTP_PORT', getenv('SMTP_PORT') ?: 587);
define('SMTP_SECURE', getenv('SMTP_SECURE') ?: 'tls');
define('SMTP_AUTH', true);
define('SMTP_USERNAME', getenv('SMTP_USERNAME') ?: 'info@freshyportal.com');
define('SMTP_PASSWORD', getenv('SMTP_PASSWORD') ?: ''); // Must be set via environment variable in Railway

// Email Settings
define('FROM_EMAIL', getenv('FROM_EMAIL') ?: 'info@freshyportal.com');
define('FROM_NAME', getenv('FROM_NAME') ?: 'Triniva PDF Tools');

// Recipients - Can be set via environment variable (comma-separated) or use defaults
$recipients = getenv('RECIPIENT_EMAILS');
if ($recipients) {
    define('RECIPIENT_EMAILS', array_map('trim', explode(',', $recipients)));
} else {
    define('RECIPIENT_EMAILS', [
        'sagarmaiti488@gmail.com',
        'info@triniva.com'
    ]);
}

// Include the email functions
require_once __DIR__ . '/email-functions.php';
?>