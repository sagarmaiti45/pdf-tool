<?php
session_start();

if (!isset($_SESSION['download_file']) || !isset($_SESSION['download_name'])) {
    header('Location: ../index.php');
    exit;
}

$filePath = $_SESSION['download_file'];
$fileName = $_SESSION['download_name'];

if (!file_exists($filePath)) {
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
}

header('Content-Type: ' . $contentType);
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

readfile($filePath);

unlink($filePath);

unset($_SESSION['download_file']);
unset($_SESSION['download_name']);

exit;
?>