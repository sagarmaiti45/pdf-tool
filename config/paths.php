<?php
// Configuration for binary paths
// This file handles different environments (local Mac, Railway/Docker)

function getImageMagickPath() {
    // Try different possible paths
    $paths = [
        '/usr/bin/convert',      // Docker/Linux standard path
        '/usr/bin/magick',       // Newer ImageMagick installations
        '/opt/homebrew/bin/magick', // Mac homebrew
        'convert',               // System PATH
        'magick'                 // System PATH
    ];
    
    foreach ($paths as $path) {
        if (@file_exists($path) || @is_executable($path)) {
            return $path;
        }
        // Also check if command exists in PATH
        $output = [];
        $returnCode = 0;
        @exec("which $path 2>/dev/null", $output, $returnCode);
        if ($returnCode === 0 && !empty($output[0])) {
            return $output[0];
        }
    }
    
    // Default to 'convert' and let it fail with proper error message
    return 'convert';
}

function getGhostscriptPath() {
    // Try different possible paths
    $paths = [
        '/usr/bin/gs',           // Docker/Linux standard path
        '/opt/homebrew/bin/gs',  // Mac homebrew
        'gs'                     // System PATH
    ];
    
    foreach ($paths as $path) {
        if (@file_exists($path) || @is_executable($path)) {
            return $path;
        }
        // Also check if command exists in PATH
        $output = [];
        $returnCode = 0;
        @exec("which $path 2>/dev/null", $output, $returnCode);
        if ($returnCode === 0 && !empty($output[0])) {
            return $output[0];
        }
    }
    
    // Default to 'gs'
    return 'gs';
}

// Check if we're in a Docker/Railway environment
function isDockerEnvironment() {
    return file_exists('/.dockerenv') || getenv('RAILWAY_ENVIRONMENT') !== false;
}

// Get the appropriate ImageMagick command
function getImageMagickCommand() {
    if (isDockerEnvironment()) {
        // In Docker, use 'convert' command directly
        return 'convert';
    }
    return getImageMagickPath();
}

// Constants
define('MAGICK_PATH', getImageMagickCommand());
define('GS_PATH', getGhostscriptPath());
?>