<?php
require_once 'includes/functions.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verifyCSRFToken($_POST['csrf_token'] ?? '');
        
        // Validate form inputs
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $message = trim($_POST['message'] ?? '');
        
        // Validation
        if (empty($name) || strlen($name) < 2) {
            throw new RuntimeException('Please enter a valid name (at least 2 characters).');
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Please enter a valid email address.');
        }
        
        if (empty($subject) || strlen($subject) < 5) {
            throw new RuntimeException('Please enter a subject (at least 5 characters).');
        }
        
        if (empty($message) || strlen($message) < 20) {
            throw new RuntimeException('Please enter a message (at least 20 characters).');
        }
        
        // Additional security checks
        $honeypot = $_POST['website'] ?? '';
        if (!empty($honeypot)) {
            // Bot detected
            throw new RuntimeException('Invalid submission detected.');
        }
        
        // Rate limiting check
        $ip = $_SERVER['REMOTE_ADDR'];
        $rateLimitKey = 'contact_' . $ip;
        $attempts = $_SESSION[$rateLimitKey] ?? 0;
        $lastAttempt = $_SESSION[$rateLimitKey . '_time'] ?? 0;
        
        if ($attempts >= 3 && (time() - $lastAttempt) < 3600) {
            throw new RuntimeException('Too many contact attempts. Please try again later.');
        }
        
        // Sanitize inputs
        $name = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $email = filter_var($email, FILTER_SANITIZE_EMAIL);
        $subject = htmlspecialchars($subject, ENT_QUOTES, 'UTF-8');
        $message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        
        // Prepare email content
        $to = 'support@pdftoolspro.com'; // Change this to your email
        $emailSubject = "[PDF Tools Contact] " . $subject;
        $emailBody = "Name: $name\n";
        $emailBody .= "Email: $email\n";
        $emailBody .= "Subject: $subject\n\n";
        $emailBody .= "Message:\n$message\n\n";
        $emailBody .= "IP Address: $ip\n";
        $emailBody .= "User Agent: " . $_SERVER['HTTP_USER_AGENT'] . "\n";
        $emailBody .= "Date: " . date('Y-m-d H:i:s');
        
        $headers = "From: noreply@pdftoolspro.com\r\n";
        $headers .= "Reply-To: $email\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();
        
        // Store contact form submission (for demo purposes, in production use database)
        $contactLog = dirname(__DIR__) . '/pdf/logs/contact_submissions.log';
        $logEntry = date('Y-m-d H:i:s') . " | $name | $email | $subject | " . substr($message, 0, 100) . "...\n";
        file_put_contents($contactLog, $logEntry, FILE_APPEND | LOCK_EX);
        
        // Update rate limiting
        $_SESSION[$rateLimitKey] = $attempts + 1;
        $_SESSION[$rateLimitKey . '_time'] = time();
        
        // For demo purposes, we'll just log the submission
        // In production, use mail() or a proper email service
        // if (!mail($to, $emailSubject, $emailBody, $headers)) {
        //     throw new RuntimeException('Failed to send email. Please try again later.');
        // }
        
        $success = 'Thank you for your message! We\'ll get back to you within 24-48 hours.';
        
        // Clear form data on success
        unset($_POST);
        
    } catch (RuntimeException $e) {
        $error = $e->getMessage();
    }
}

$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us - Triniva</title>
    <meta name="description" content="Get in touch with Triniva. We're here to help with any questions about our PDF conversion and editing tools.">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <header>
        <div class="container">
            <nav class="navbar">
                <div class="logo">
                    <a href="index.php" style="text-decoration: none; color: inherit;">
                        <i class="fas fa-file-pdf"></i>
                        <span>Triniva</span>
                    </a>
                </div>
                <ul class="nav-links" id="navLinks">
                    <li><a href="index.php">Home</a></li>
                    <li><a href="index.php#tools">All Tools</a></li>
                    <li><a href="index.php#about">About</a></li>
                    <li><a href="contact.php" class="active">Contact</a></li>
                </ul>
                <button class="mobile-menu-toggle" id="mobileMenuToggle">
                    <i class="fas fa-bars"></i>
                </button>
            </nav>
        </div>
    </header>

    <div class="page-header">
        <div class="container">
            <h1>Contact Us</h1>
            <p>We're here to help with any questions or feedback</p>
        </div>
    </div>

    <section class="contact-section">
        <div class="container">
            <div class="contact-grid">
                <div class="contact-info">
                    <h2>Get in Touch</h2>
                    <p>Have questions about our PDF tools? Need help with a specific feature? We're here to assist you.</p>
                    
                    <div class="contact-details">
                        <div class="contact-item">
                            <i class="fas fa-envelope"></i>
                            <div>
                                <h3>Email</h3>
                                <p>info@freshyportal.com</p>
                            </div>
                        </div>
                        
                        <div class="contact-item">
                            <i class="fas fa-clock"></i>
                            <div>
                                <h3>Response Time</h3>
                                <p>24-48 hours (Mon-Fri)</p>
                            </div>
                        </div>
                        
                        <div class="contact-item">
                            <i class="fas fa-phone"></i>
                            <div>
                                <h3>Phone</h3>
                                <p>+91 8101725801</p>
                            </div>
                        </div>
                        
                        <div class="contact-item">
                            <i class="fas fa-map-marker-alt"></i>
                            <div>
                                <h3>Address</h3>
                                <p>East Medinipur, West Bengal<br>India - 721151</p>
                            </div>
                        </div>
                        
                        <div class="contact-item">
                            <i class="fas fa-shield-alt"></i>
                            <div>
                                <h3>Privacy</h3>
                                <p>Your data is secure and never shared</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="faq-section">
                        <h3>Frequently Asked Questions</h3>
                        <div class="faq-item">
                            <h4>Is Triniva really free?</h4>
                            <p>Yes! All our tools are 100% free to use with no hidden charges.</p>
                        </div>
                        <div class="faq-item">
                            <h4>Are my files secure?</h4>
                            <p>Absolutely. Files are automatically deleted after processing and we never access your content.</p>
                        </div>
                        <div class="faq-item">
                            <h4>What's the file size limit?</h4>
                            <p>You can upload files up to 50MB. For larger files, try compressing them first.</p>
                        </div>
                    </div>
                </div>
                
                <div class="contact-form-container">
                    <h2>Send us a Message</h2>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" class="contact-form" id="contactForm">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                        
                        <!-- Honeypot field for bot detection -->
                        <div style="position: absolute; left: -5000px;">
                            <input type="text" name="website" tabindex="-1" autocomplete="off">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="name">Your Name *</label>
                            <input type="text" 
                                   name="name" 
                                   id="name" 
                                   class="form-control" 
                                   required 
                                   minlength="2"
                                   value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="email">Email Address *</label>
                            <input type="email" 
                                   name="email" 
                                   id="email" 
                                   class="form-control" 
                                   required
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="subject">Subject *</label>
                            <input type="text" 
                                   name="subject" 
                                   id="subject" 
                                   class="form-control" 
                                   required 
                                   minlength="5"
                                   value="<?php echo htmlspecialchars($_POST['subject'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label" for="message">Message *</label>
                            <textarea name="message" 
                                      id="message" 
                                      class="form-control" 
                                      rows="6" 
                                      required 
                                      minlength="20"><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary" style="width: 100%;">
                                <i class="fas fa-paper-plane"></i> Send Message
                            </button>
                        </div>
                        
                        <p style="font-size: 0.875rem; color: #757575; text-align: center;">
                            By submitting this form, you agree to our 
                            <a href="privacy.php" style="color: var(--primary-color);">Privacy Policy</a> and 
                            <a href="terms.php" style="color: var(--primary-color);">Terms of Service</a>.
                        </p>
                    </form>
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
                        <li><a href="index.php#tools">All Tools</a></li>
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
    <script>
        // Form validation
        document.getElementById('contactForm').addEventListener('submit', function(e) {
            const name = document.getElementById('name').value.trim();
            const email = document.getElementById('email').value.trim();
            const subject = document.getElementById('subject').value.trim();
            const message = document.getElementById('message').value.trim();
            
            if (name.length < 2) {
                e.preventDefault();
                alert('Please enter a valid name (at least 2 characters).');
                return;
            }
            
            if (!email.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) {
                e.preventDefault();
                alert('Please enter a valid email address.');
                return;
            }
            
            if (subject.length < 5) {
                e.preventDefault();
                alert('Please enter a subject (at least 5 characters).');
                return;
            }
            
            if (message.length < 20) {
                e.preventDefault();
                alert('Please enter a message (at least 20 characters).');
                return;
            }
        });
    </script>
</body>
</html>