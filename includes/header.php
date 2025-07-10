<?php
// Ensure config is loaded
if (!defined('SITE_NAME')) {
    require_once __DIR__ . '/config.php';
}

// Get current page for active nav highlighting
$current_page = basename($_SERVER['PHP_SELF']);
$is_tool_page = strpos($_SERVER['REQUEST_URI'], '/tools/') !== false;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' . SITE_NAME : SITE_NAME . ' - Free Online PDF Tools'; ?></title>
    <meta name="description" content="<?php echo isset($page_description) ? $page_description : 'Free online PDF tools to compress, merge, rotate, and convert PDFs. No registration required. Fast and secure.'; ?>">
    <meta name="keywords" content="<?php echo isset($page_keywords) ? $page_keywords : 'PDF tools, PDF converter, PDF compressor, merge PDF, free PDF tools'; ?>">
    <meta name="author" content="<?php echo SITE_NAME; ?>">
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="<?php echo SITE_URL . $_SERVER['REQUEST_URI']; ?>">
    <meta property="og:title" content="<?php echo isset($page_title) ? $page_title . ' - ' . SITE_NAME : SITE_NAME . ' - Free Online PDF Tools'; ?>">
    <meta property="og:description" content="<?php echo isset($page_description) ? $page_description : 'Free online PDF tools to compress, merge, rotate, and convert PDFs. No registration required.'; ?>">
    <meta property="og:image" content="<?php echo SITE_URL; ?>/assets/images/og-image.png">
    
    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="<?php echo SITE_URL . $_SERVER['REQUEST_URI']; ?>">
    <meta property="twitter:title" content="<?php echo isset($page_title) ? $page_title . ' - ' . SITE_NAME : SITE_NAME . ' - Free Online PDF Tools'; ?>">
    <meta property="twitter:description" content="<?php echo isset($page_description) ? $page_description : 'Free online PDF tools to compress, merge, rotate, and convert PDFs.'; ?>">
    <meta property="twitter:image" content="<?php echo SITE_URL; ?>/assets/images/twitter-image.png">
    
    <!-- Canonical URL -->
    <link rel="canonical" href="<?php echo SITE_URL . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH); ?>">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="<?php echo $is_tool_page ? '../' : ''; ?>assets/images/file-pdf.ico">
    <link rel="apple-touch-icon" sizes="180x180" href="<?php echo $is_tool_page ? '../' : ''; ?>assets/images/file-pdf.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="<?php echo $is_tool_page ? '../' : ''; ?>assets/images/file-pdf.ico">
    <link rel="icon" type="image/png" sizes="16x16" href="<?php echo $is_tool_page ? '../' : ''; ?>assets/images/file-pdf.ico">
    
    <!-- Styles -->
    <link rel="stylesheet" href="<?php echo $is_tool_page ? '../' : ''; ?>assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <?php if (isset($additional_head)) echo $additional_head; ?>
</head>
<body>
    <header>
        <div class="container">
            <nav class="navbar">
                <div class="logo">
                    <a href="<?php echo $is_tool_page ? '../' : '/'; ?>" style="text-decoration: none; color: inherit;">
                        <i class="fas fa-file-pdf"></i>
                        <span><?php echo SITE_NAME; ?></span>
                    </a>
                </div>
                <ul class="nav-links" id="navLinks">
                    <li><a href="<?php echo $is_tool_page ? '../' : '/'; ?>" <?php echo $current_page == 'index.php' ? 'class="active"' : ''; ?>>Home</a></li>
                    <li><a href="<?php echo $is_tool_page ? '../' : '/'; ?>#tools" <?php echo $is_tool_page ? 'class="active"' : ''; ?>>Tools</a></li>
                    <li><a href="<?php echo $is_tool_page ? '../' : ''; ?>about.php" <?php echo $current_page == 'about.php' ? 'class="active"' : ''; ?>>About</a></li>
                    <li><a href="<?php echo $is_tool_page ? '../' : ''; ?>contact.php" <?php echo $current_page == 'contact.php' ? 'class="active"' : ''; ?>>Contact</a></li>
                </ul>
                <button class="mobile-menu-toggle" id="mobileMenuToggle">
                    <i class="fas fa-bars"></i>
                </button>
            </nav>
        </div>
    </header>