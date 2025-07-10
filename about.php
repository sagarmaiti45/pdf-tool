<?php
// Set page variables
$page_title = 'About Us';
$page_description = 'Learn about Triniva - your trusted partner for free online PDF conversion and editing. Discover our mission, values, and commitment to providing the best PDF tools.';
$page_keywords = 'about Triniva, PDF converter company, online PDF tools, free PDF service';

// Additional scripts for animation
$additional_scripts = <<<HTML
    <script>
        // Animate statistics on scroll
        const observerOptions = {
            threshold: 0.5,
            rootMargin: '0px'
        };

        const observer = new IntersectionObserver(function(entries) {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const statCards = entry.target.querySelectorAll('.stat-card h3');
                    statCards.forEach(stat => {
                        const target = stat.textContent;
                        const number = parseInt(target.replace(/[^0-9]/g, ''));
                        const suffix = target.replace(/[0-9]/g, '');
                        let current = 0;
                        const increment = number / 50;
                        
                        const timer = setInterval(() => {
                            current += increment;
                            if (current >= number) {
                                current = number;
                                clearInterval(timer);
                            }
                            stat.textContent = Math.floor(current) + suffix;
                        }, 30);
                    });
                    observer.unobserve(entry.target);
                }
            });
        }, observerOptions);

        const statsSection = document.querySelector('.stats-grid');
        if (statsSection) {
            observer.observe(statsSection);
        }
    </script>
HTML;

// Include header
require_once 'includes/header.php';
?>

    <div class="page-header">
        <div class="container">
            <h1>About Triniva</h1>
            <p>Your trusted partner for all PDF needs since 2024</p>
        </div>
    </div>

    <section class="about-section">
        <div class="container">
            <div class="about-intro">
                <div class="about-content">
                    <h2>Our Mission</h2>
                    <p class="lead">To provide the world's most reliable, fast, and user-friendly PDF tools - completely free and accessible to everyone.</p>
                    <p>At Triniva, we believe that everyone should have access to professional-grade PDF tools without barriers. Whether you're a student, professional, or business owner, our tools are designed to make your PDF tasks simple and efficient.</p>
                </div>
            </div>

            <div class="stats-section">
                <div class="stats-grid">
                    <div class="stat-card">
                        <i class="fas fa-users"></i>
                        <h3>1M+</h3>
                        <p>Happy Users</p>
                    </div>
                    <div class="stat-card">
                        <i class="fas fa-file-pdf"></i>
                        <h3>10M+</h3>
                        <p>PDFs Processed</p>
                    </div>
                    <div class="stat-card">
                        <i class="fas fa-globe"></i>
                        <h3>150+</h3>
                        <p>Countries Served</p>
                    </div>
                    <div class="stat-card">
                        <i class="fas fa-shield-alt"></i>
                        <h3>100%</h3>
                        <p>Secure & Private</p>
                    </div>
                </div>
            </div>

            <div class="values-section">
                <h2>Our Core Values</h2>
                <div class="values-grid">
                    <div class="value-card">
                        <div class="value-icon">
                            <i class="fas fa-lock"></i>
                        </div>
                        <h3>Privacy First</h3>
                        <p>Your files are automatically deleted after processing. We never access, store, or share your data. Your privacy is our top priority.</p>
                    </div>
                    <div class="value-card">
                        <div class="value-icon">
                            <i class="fas fa-rocket"></i>
                        </div>
                        <h3>Lightning Fast</h3>
                        <p>Powered by advanced algorithms and optimized servers, we ensure your PDFs are processed in seconds, not minutes.</p>
                    </div>
                    <div class="value-card">
                        <div class="value-icon">
                            <i class="fas fa-gift"></i>
                        </div>
                        <h3>Forever Free</h3>
                        <p>No hidden fees, no premium plans, no credit cards required. All our tools are 100% free and will always remain free.</p>
                    </div>
                    <div class="value-card">
                        <div class="value-icon">
                            <i class="fas fa-heart"></i>
                        </div>
                        <h3>User Focused</h3>
                        <p>Every feature is designed with you in mind. Simple interface, powerful tools, and no unnecessary complications.</p>
                    </div>
                </div>
            </div>

            <div class="story-section">
                <div class="story-content">
                    <h2>Our Story</h2>
                    <p>Triniva was born from a simple frustration - why should people pay expensive subscriptions for basic PDF operations? Our founders at FreshyPortal, having experienced the pain of dealing with overpriced PDF software, decided to create a solution that would be free for everyone.</p>
                    <p>Starting in 2024, we've grown from a simple PDF compressor to a comprehensive suite of PDF tools. Our commitment remains unchanged: provide the best PDF tools, keep them free, and respect user privacy above all.</p>
                    <p>Today, we're proud to serve millions of users worldwide, processing millions of documents every month while maintaining our core values of privacy, speed, and accessibility.</p>
                </div>
            </div>

            <div class="technology-section">
                <h2>Technology Stack</h2>
                <p class="tech-intro">We use industry-leading open-source technologies to ensure reliability, security, and performance.</p>
                <div class="tech-grid">
                    <div class="tech-item">
                        <i class="fab fa-php"></i>
                        <h4>PHP</h4>
                        <p>Robust backend processing</p>
                    </div>
                    <div class="tech-item">
                        <i class="fas fa-ghost"></i>
                        <h4>Ghostscript</h4>
                        <p>Professional PDF manipulation</p>
                    </div>
                    <div class="tech-item">
                        <i class="fas fa-magic"></i>
                        <h4>ImageMagick</h4>
                        <p>Advanced image processing</p>
                    </div>
                    <div class="tech-item">
                        <i class="fas fa-shield-alt"></i>
                        <h4>Security</h4>
                        <p>Enterprise-grade protection</p>
                    </div>
                </div>
            </div>

            <div class="comparison-section">
                <h2>Why Choose Triniva?</h2>
                <div class="comparison-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Feature</th>
                                <th>Triniva</th>
                                <th>Others</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Price</td>
                                <td><i class="fas fa-check text-success"></i> Free Forever</td>
                                <td><i class="fas fa-times text-danger"></i> $9-50/month</td>
                            </tr>
                            <tr>
                                <td>Registration</td>
                                <td><i class="fas fa-check text-success"></i> Not Required</td>
                                <td><i class="fas fa-times text-danger"></i> Email Required</td>
                            </tr>
                            <tr>
                                <td>File Size Limit</td>
                                <td><i class="fas fa-check text-success"></i> 50MB</td>
                                <td><i class="fas fa-times text-danger"></i> 5-25MB</td>
                            </tr>
                            <tr>
                                <td>Processing Speed</td>
                                <td><i class="fas fa-check text-success"></i> Instant</td>
                                <td><i class="fas fa-times text-danger"></i> Queue System</td>
                            </tr>
                            <tr>
                                <td>Data Privacy</td>
                                <td><i class="fas fa-check text-success"></i> Auto Delete</td>
                                <td><i class="fas fa-times text-danger"></i> Stored on Servers</td>
                            </tr>
                            <tr>
                                <td>Watermarks</td>
                                <td><i class="fas fa-check text-success"></i> None</td>
                                <td><i class="fas fa-times text-danger"></i> Free Plans Have</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="cta-section">
                <h2>Ready to Get Started?</h2>
                <p>Join millions of users who trust Triniva for their PDF needs.</p>
                <a href="/#tools" class="btn btn-primary btn-large">
                    <i class="fas fa-rocket"></i> Try Our Tools Now
                </a>
            </div>
        </div>
    </section>

<?php
// Include footer
require_once 'includes/footer.php';
?>