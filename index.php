<?php
$page_title = 'Triniva - Free Online PDF Tools';
$page_description = 'Free online PDF tools for everyone. Merge, split, compress, convert, rotate, unlock, and protect PDF files directly in your browser.';
$page_keywords = 'PDF tools, merge PDF, split PDF, compress PDF, convert PDF, rotate PDF, unlock PDF, protect PDF, online PDF editor';

// Include header
require_once 'includes/header.php';
?>

    <style>
        .maintenance-notice {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            text-align: center;
        }
        .maintenance-notice h3 {
            margin: 0 0 10px 0;
            font-size: 1.5rem;
        }
        .tool-card {
            opacity: 0.5;
            cursor: not-allowed;
            position: relative;
        }
        .tool-card::after {
            content: 'Coming Soon';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            font-weight: bold;
        }
        .tool-card:hover {
            transform: none;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
    </style>

    <div class="hero">
        <div class="container">
            <h1 class="hero-title">Free Online PDF Tools</h1>
            <p class="hero-subtitle">Simple, secure, and fast PDF tools for everyone</p>
            
            <div class="maintenance-notice">
                <h3>🚧 Under Maintenance</h3>
                <p>We're currently upgrading our PDF tools to provide you with a better experience.</p>
                <p>All tools will be available soon. Thank you for your patience!</p>
            </div>
        </div>
    </div>

    <div class="tools-section">
        <div class="container">
            <h2 class="section-title">Our PDF Tools</h2>
            <div class="tools-grid">
                
                <div class="tool-card">
                    <i class="fas fa-object-group tool-icon"></i>
                    <h3>Merge PDF</h3>
                    <p>Combine multiple PDF files into a single document</p>
                </div>
                
                <div class="tool-card">
                    <i class="fas fa-cut tool-icon"></i>
                    <h3>Split PDF</h3>
                    <p>Extract pages or split your PDF into multiple files</p>
                </div>
                
                <div class="tool-card">
                    <i class="fas fa-compress tool-icon"></i>
                    <h3>Compress PDF</h3>
                    <p>Reduce file size while maintaining quality</p>
                </div>
                
                <div class="tool-card">
                    <i class="fas fa-file-image tool-icon"></i>
                    <h3>PDF to JPG</h3>
                    <p>Convert PDF pages to high-quality JPG images</p>
                </div>
                
                <div class="tool-card">
                    <i class="fas fa-file-pdf tool-icon"></i>
                    <h3>JPG to PDF</h3>
                    <p>Convert images to PDF format</p>
                </div>
                
                <div class="tool-card">
                    <i class="fas fa-sync-alt tool-icon"></i>
                    <h3>Rotate PDF</h3>
                    <p>Rotate PDF pages to the correct orientation</p>
                </div>
                
                <div class="tool-card">
                    <i class="fas fa-lock tool-icon"></i>
                    <h3>Protect PDF</h3>
                    <p>Add password protection to your PDF files</p>
                </div>
                
                <div class="tool-card">
                    <i class="fas fa-unlock tool-icon"></i>
                    <h3>Unlock PDF</h3>
                    <p>Remove password and restrictions from PDFs</p>
                </div>
                
                <div class="tool-card">
                    <i class="fas fa-file-alt tool-icon"></i>
                    <h3>Extract Text</h3>
                    <p>Extract text content from PDF files</p>
                </div>
                
            </div>
        </div>
    </div>

    <div class="features-section">
        <div class="container">
            <h2 class="section-title">Why Choose Triniva?</h2>
            <div class="features-grid">
                <div class="feature-card">
                    <i class="fas fa-shield-alt feature-icon"></i>
                    <h3>Secure & Private</h3>
                    <p>Your files are automatically deleted after processing. We don't store or share your data.</p>
                </div>
                <div class="feature-card">
                    <i class="fas fa-bolt feature-icon"></i>
                    <h3>Fast Processing</h3>
                    <p>Our tools are optimized for speed. Process your PDFs in seconds, not minutes.</p>
                </div>
                <div class="feature-card">
                    <i class="fas fa-globe feature-icon"></i>
                    <h3>Works Everywhere</h3>
                    <p>No software installation needed. Works on any device with a web browser.</p>
                </div>
                <div class="feature-card">
                    <i class="fas fa-dollar-sign feature-icon"></i>
                    <h3>Completely Free</h3>
                    <p>All tools are 100% free to use. No hidden charges or subscriptions.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="cta-section">
        <div class="container">
            <h2>Ready When You Are</h2>
            <p>Check back soon to use our powerful PDF tools!</p>
        </div>
    </div>

<?php
// Include footer
require_once 'includes/footer.php';
?>