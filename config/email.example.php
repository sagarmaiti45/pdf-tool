<?php
// Example email configuration file
// Copy this to email.php and update with your actual credentials

// SMTP Configuration
define('SMTP_HOST', 'smtp.example.com');
define('SMTP_PORT', 587);
define('SMTP_SECURE', 'tls');
define('SMTP_AUTH', true);
define('SMTP_USERNAME', 'your-email@example.com');
define('SMTP_PASSWORD', 'your-password');

// Email Settings
define('FROM_EMAIL', 'your-email@example.com');
define('FROM_NAME', 'Your Website Name');

// Recipients
define('RECIPIENT_EMAILS', [
    'recipient1@example.com',
    'recipient2@example.com'
]);

// Include the email functions
require_once __DIR__ . '/email-functions.php';
?>