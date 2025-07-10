<?php
// Health check endpoint for Railway

header('Content-Type: application/json');

$checks = [
    'php' => phpversion(),
    'ghostscript' => shell_exec('gs --version') ? true : false,
    'imagemagick' => shell_exec('convert --version') ? true : false,
    'uploads_writable' => is_writable(__DIR__ . '/uploads'),
    'temp_writable' => is_writable(__DIR__ . '/temp'),
];

$healthy = !in_array(false, $checks);

http_response_code($healthy ? 200 : 503);

echo json_encode([
    'status' => $healthy ? 'healthy' : 'unhealthy',
    'checks' => $checks,
    'timestamp' => date('c')
]);