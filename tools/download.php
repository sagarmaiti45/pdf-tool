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

header('Content-Type: application/pdf');
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