<?php
session_start();
require_once '../includes/functions.php';

// Check if download file is set in session
if (!isset($_SESSION['download_file']) || !isset($_SESSION['download_name'])) {
    header('Location: ../index.php');
    exit;
}

$filePath = $_SESSION['download_file'];
$downloadName = $_SESSION['download_name'];

// Check if file exists
if (!file_exists($filePath)) {
    header('Location: ../index.php');
    exit;
}

// Determine the correct MIME type based on file extension
$fileExtension = strtolower(pathinfo($downloadName, PATHINFO_EXTENSION));
$contentType = 'application/octet-stream'; // default

switch ($fileExtension) {
    case 'pdf':
        $contentType = 'application/pdf';
        break;
    case 'jpg':
    case 'jpeg':
        $contentType = 'image/jpeg';
        break;
    case 'png':
        $contentType = 'image/png';
        break;
    case 'zip':
        $contentType = 'application/zip';
        break;
}

// Set headers for download
header('Content-Type: ' . $contentType);
header('Content-Disposition: attachment; filename="' . $downloadName . '"');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Output file
readfile($filePath);

// Clean up - remove file after download
@unlink($filePath);

// Clear session variables
unset($_SESSION['download_file']);
unset($_SESSION['download_name']);

exit;
?>