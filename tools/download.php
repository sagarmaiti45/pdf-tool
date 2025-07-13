<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

session_start();

// Check if file parameter is provided
if (!isset($_GET['file']) || empty($_GET['file'])) {
    header('Location: ../index.php');
    exit;
}

$fileName = basename($_GET['file']);
$filePath = UPLOAD_DIR . $fileName;

// Security check - ensure the file is in the uploads directory
$realPath = realpath($filePath);
$uploadsPath = realpath(UPLOAD_DIR);

if ($realPath === false || strpos($realPath, $uploadsPath) !== 0) {
    header('Location: ../index.php');
    exit;
}

// Check if file exists
if (!file_exists($filePath)) {
    header('Location: ../index.php');
    exit;
}

// Check if this file is in the session's temp files (security measure)
if (!isset($_SESSION['temp_files']) || !in_array($filePath, $_SESSION['temp_files'])) {
    header('Location: ../index.php');
    exit;
}

// Determine the correct MIME type based on file extension
$fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
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
    case 'doc':
    case 'docx':
        $contentType = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
        break;
    case 'odt':
        $contentType = 'application/vnd.oasis.opendocument.text';
        break;
    case 'txt':
        $contentType = 'text/plain';
        break;
    case 'rtf':
        $contentType = 'application/rtf';
        break;
}

// Set headers for download
header('Content-Type: ' . $contentType);
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Output file
readfile($filePath);

// Clean up - remove file after download
@unlink($filePath);

// Remove from session temp files
if (isset($_SESSION['temp_files'])) {
    $key = array_search($filePath, $_SESSION['temp_files']);
    if ($key !== false) {
        unset($_SESSION['temp_files'][$key]);
    }
}

exit;
?>