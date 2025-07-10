<?php
// Simple test to ensure PHP is working
if (isset($_GET['test'])) {
    die("PHP is working! Path: " . __DIR__);
}

// Original index.php content
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Triniva - Free Online PDF Converter & Editor | Compress, Merge, Convert PDFs</title>
    <meta name="description" content="Free online PDF tools to compress, merge, rotate, convert JPG to PDF, PDF to JPG, unlock and protect PDFs. No registration required. Fast, secure and easy to use.">
    <meta name="keywords" content="PDF tools, PDF converter, PDF compressor, merge PDF, PDF editor, JPG to PDF, PDF to JPG, unlock PDF, protect PDF, free PDF tools">
    <meta name="author" content="Triniva">
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://www.triniva.com/">
    <meta property="og:title" content="Triniva - Free Online PDF Converter & Editor">
    <meta property="og:description" content="Free online PDF tools to compress, merge, rotate, and convert PDFs. No registration required. Fast and secure.">
    <meta property="og:image" content="https://www.triniva.com/assets/images/og-image.png">
    
    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="https://www.triniva.com/">
    <meta property="twitter:title" content="Triniva - Free Online PDF Converter & Editor">
    <meta property="twitter:description" content="Free online PDF tools to compress, merge, rotate, and convert PDFs. No registration required.">
    <meta property="twitter:image" content="https://www.triniva.com/assets/images/twitter-image.png">
    
    <!-- Canonical URL -->
    <link rel="canonical" href="https://www.triniva.com/">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
    
    <!-- Styles -->
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Schema.org markup -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "WebApplication",
        "name": "Triniva",
        "url": "https://www.triniva.com",
        "description": "Free online PDF tools to compress, merge, rotate, convert JPG to PDF, PDF to JPG, unlock and protect PDFs",
        "applicationCategory": "UtilitiesApplication",
        "operatingSystem": "Any",
        "offers": {
            "@type": "Offer",
            "price": "0",
            "priceCurrency": "USD"
        }
    }
    </script>
</head>
<body>
    <header>
        <div class="container">
            <nav class="navbar">
                <div class="logo">
                    <i class="fas fa-file-pdf"></i>
                    <span>Triniva</span>
                </div>
                <ul class="nav-links" id="navLinks">
                    <li><a href="#home">Home</a></li>
                    <li><a href="#tools">All Tools</a></li>
                    <li><a href="about.php">About</a></li>
                    <li><a href="contact.php">Contact</a></li>
                </ul>
                <button class="mobile-menu-toggle" id="mobileMenuToggle">
                    <i class="fas fa-bars"></i>
                </button>
            </nav>
        </div>
    </header>

    <section class="hero">
        <div class="container">
            <h1>Professional PDF Tools Suite</h1>
            <p>All-in-one solution for your PDF needs. Fast, secure, and completely free.</p>
        </div>
    </section>

    <section id="tools" class="tools-section">
        <div class="container">
            <h2>Choose Your Tool</h2>
            <div class="tools-grid">
                <a href="tools/compress.php" class="tool-card">
                    <div class="tool-icon">
                        <i class="fas fa-compress"></i>
                    </div>
                    <h3>Compress PDF</h3>
                    <p>Reduce PDF file size while maintaining quality</p>
                    <span class="tool-action">Compress Now <i class="fas fa-arrow-right"></i></span>
                </a>

                <a href="tools/merge.php" class="tool-card">
                    <div class="tool-icon">
                        <i class="fas fa-object-group"></i>
                    </div>
                    <h3>Merge PDF</h3>
                    <p>Combine multiple PDFs into a single document</p>
                    <span class="tool-action">Merge Now <i class="fas fa-arrow-right"></i></span>
                </a>

                <a href="tools/rotate.php" class="tool-card">
                    <div class="tool-icon">
                        <i class="fas fa-sync-alt"></i>
                    </div>
                    <h3>Rotate PDF</h3>
                    <p>Rotate pages clockwise or counter-clockwise</p>
                    <span class="tool-action">Rotate Now <i class="fas fa-arrow-right"></i></span>
                </a>

                <a href="tools/jpg-to-pdf.php" class="tool-card">
                    <div class="tool-icon">
                        <i class="fas fa-image"></i>
                    </div>
                    <h3>JPG to PDF</h3>
                    <p>Convert JPG images to PDF documents</p>
                    <span class="tool-action">Convert Now <i class="fas fa-arrow-right"></i></span>
                </a>

                <a href="tools/pdf-to-jpg.php" class="tool-card">
                    <div class="tool-icon">
                        <i class="fas fa-file-image"></i>
                    </div>
                    <h3>PDF to JPG</h3>
                    <p>Convert PDF pages to JPG images</p>
                    <span class="tool-action">Convert Now <i class="fas fa-arrow-right"></i></span>
                </a>

                <a href="tools/unlock.php" class="tool-card">
                    <div class="tool-icon">
                        <i class="fas fa-unlock-alt"></i>
                    </div>
                    <h3>Unlock PDF</h3>
                    <p>Remove restrictions from PDF files</p>
                    <span class="tool-action">Unlock Now <i class="fas fa-arrow-right"></i></span>
                </a>

                <a href="tools/protect.php" class="tool-card">
                    <div class="tool-icon">
                        <i class="fas fa-lock"></i>
                    </div>
                    <h3>Protect PDF</h3>
                    <p>Add password protection to your PDFs</p>
                    <span class="tool-action">Protect Now <i class="fas fa-arrow-right"></i></span>
                </a>
            </div>
        </div>
    </section>

    <section class="features">
        <div class="container">
            <h2>Why Choose Our PDF Tools?</h2>
            <div class="features-grid">
                <div class="feature">
                    <i class="fas fa-shield-alt"></i>
                    <h3>Secure Processing</h3>
                    <p>Your files are automatically deleted after processing</p>
                </div>
                <div class="feature">
                    <i class="fas fa-bolt"></i>
                    <h3>Lightning Fast</h3>
                    <p>Process PDFs in seconds with our optimized algorithms</p>
                </div>
                <div class="feature">
                    <i class="fas fa-dollar-sign"></i>
                    <h3>100% Free</h3>
                    <p>All tools are completely free with no hidden charges</p>
                </div>
                <div class="feature">
                    <i class="fas fa-cloud"></i>
                    <h3>No Registration</h3>
                    <p>Use all tools without creating an account</p>
                </div>
            </div>
        </div>
    </section>

    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3>Triniva</h3>
                    <p>Professional PDF tools that are fast, secure, and completely free.</p>
                </div>
                <div class="footer-section">
                    <h4>Quick Links</h4>
                    <ul>
                        <li><a href="index.php">Home</a></li>
                        <li><a href="#tools">All Tools</a></li>
                        <li><a href="contact.php">Contact</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h4>Legal</h4>
                    <ul>
                        <li><a href="privacy.php">Privacy Policy</a></li>
                        <li><a href="terms.php">Terms & Conditions</a></li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2024 Triniva. All rights reserved. A <a href="https://freshyportal.com" target="_blank" style="color: #fff; text-decoration: underline;">FreshyPortal</a> Product.</p>
            </div>
        </div>
    </footer>

    <script src="assets/js/main.js"></script>
</body>
</html>