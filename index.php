<?php
// Set page-specific variables
$page_title = 'Free Online PDF Converter & Editor';
$page_description = 'Free online PDF tools to compress, merge, rotate, convert JPG to PDF, PDF to JPG, unlock and protect PDFs. No registration required. Fast, secure and easy to use.';
$page_keywords = 'PDF tools, PDF converter, PDF compressor, merge PDF, PDF editor, JPG to PDF, PDF to JPG, unlock PDF, protect PDF, free PDF tools';

// Include header
require_once 'includes/header.php';

// Simple test to ensure PHP is working
if (isset($_GET['test'])) {
    die("PHP is working! Path: " . __DIR__);
}
?>

    <section class="hero">
        <div class="container">
            <h1>Professional PDF Tools Suite</h1>
            <p>All-in-one solution for your PDF needs. Fast, secure, and completely free.</p>
        </div>
    </section>

    <section id="tools" class="section">
        <div class="container">
            <h2 class="text-center mb-8">Choose Your Tool</h2>
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

                <a href="tools/split.php" class="tool-card">
                    <div class="tool-icon">
                        <i class="fas fa-cut"></i>
                    </div>
                    <h3>Split PDF</h3>
                    <p>Split PDF files into multiple documents</p>
                    <span class="tool-action">Split Now <i class="fas fa-arrow-right"></i></span>
                </a>
            </div>
        </div>
    </section>

    <section class="section" style="background: var(--surface);">
        <div class="container">
            <h2 class="text-center" style="margin-bottom: 3rem;">Why Choose Our PDF Tools?</h2>
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

<?php
// Additional script for homepage
$additional_scripts = <<<HTML
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
HTML;

// Include footer
require_once 'includes/footer.php';
?>