<?php
require_once 'includes/functions.php';
require_once 'includes/config.php';

// Set page variables for header
$page_title = 'Contact Us';
$page_description = 'Get in touch with Triniva. We\'re here to help with any questions about our PDF conversion and editing tools.';
$page_keywords = 'contact, support, help, PDF tools, Triniva';

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

// Include header
require_once 'includes/header.php';
?>

    <main class="main-content">
        <div class="container">
            <div class="text-center mb-8">
                <h1 class="text-4xl font-bold mb-4">Contact Us</h1>
                <p class="text-gray-600">We're here to help with any questions or feedback</p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <div class="contact-info">
                    <h2 class="text-2xl font-semibold mb-4">Get in Touch</h2>
                    <p class="text-gray-600 mb-6">Have questions about our PDF tools? Need help with a specific feature? We're here to assist you.</p>
                    
                    <div class="space-y-6">
                        <div class="flex items-start gap-4">
                            <div class="flex-shrink-0">
                                <i class="fas fa-envelope text-primary text-xl"></i>
                            </div>
                            <div>
                                <h3 class="font-semibold mb-1">Email</h3>
                                <p class="text-gray-600">info@freshyportal.com</p>
                            </div>
                        </div>
                        
                        <div class="flex items-start gap-4">
                            <div class="flex-shrink-0">
                                <i class="fas fa-clock text-primary text-xl"></i>
                            </div>
                            <div>
                                <h3 class="font-semibold mb-1">Response Time</h3>
                                <p class="text-gray-600">24-48 hours (Mon-Fri)</p>
                            </div>
                        </div>
                        
                        <div class="flex items-start gap-4">
                            <div class="flex-shrink-0">
                                <i class="fas fa-map-marker-alt text-primary text-xl"></i>
                            </div>
                            <div>
                                <h3 class="font-semibold mb-1">Address</h3>
                                <p class="text-gray-600">East Medinipur, West Bengal<br>India - 721151</p>
                            </div>
                        </div>
                        
                        <div class="flex items-start gap-4">
                            <div class="flex-shrink-0">
                                <i class="fas fa-shield-alt text-primary text-xl"></i>
                            </div>
                            <div>
                                <h3 class="font-semibold mb-1">Privacy</h3>
                                <p class="text-gray-600">Your data is secure and never shared</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-8">
                        <h3 class="text-xl font-semibold mb-4">Frequently Asked Questions</h3>
                        <div class="space-y-4">
                            <div class="border-l-4 border-primary pl-4">
                                <h4 class="font-semibold mb-1">Is Triniva really free?</h4>
                                <p class="text-gray-600">Yes! All our tools are 100% free to use with no hidden charges.</p>
                            </div>
                            <div class="border-l-4 border-primary pl-4">
                                <h4 class="font-semibold mb-1">Are my files secure?</h4>
                                <p class="text-gray-600">Absolutely. Files are automatically deleted after processing and we never access your content.</p>
                            </div>
                            <div class="border-l-4 border-primary pl-4">
                                <h4 class="font-semibold mb-1">What's the file size limit?</h4>
                                <p class="text-gray-600">You can upload files up to 50MB. For larger files, try compressing them first.</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow-lg p-6">
                    <h2 class="text-2xl font-semibold mb-6">Send us a Message</h2>
                    
                    <?php if ($error): ?>
                        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-4">
                            <i class="fas fa-exclamation-circle mr-2"></i><?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-4">
                            <i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($success); ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" id="contactForm" class="space-y-4">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                        
                        <!-- Honeypot field for bot detection -->
                        <div style="position: absolute; left: -5000px;">
                            <input type="text" name="website" tabindex="-1" autocomplete="off">
                        </div>
                        
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Your Name *</label>
                            <input type="text" 
                                   name="name" 
                                   id="name" 
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent" 
                                   required 
                                   minlength="2"
                                   value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
                        </div>
                        
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email Address *</label>
                            <input type="email" 
                                   name="email" 
                                   id="email" 
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent" 
                                   required
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                        </div>
                        
                        <div>
                            <label for="subject" class="block text-sm font-medium text-gray-700 mb-1">Subject *</label>
                            <input type="text" 
                                   name="subject" 
                                   id="subject" 
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent" 
                                   required 
                                   minlength="5"
                                   value="<?php echo htmlspecialchars($_POST['subject'] ?? ''); ?>">
                        </div>
                        
                        <div>
                            <label for="message" class="block text-sm font-medium text-gray-700 mb-1">Message *</label>
                            <textarea name="message" 
                                      id="message" 
                                      class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent" 
                                      rows="6" 
                                      required 
                                      minlength="20"><?php echo htmlspecialchars($_POST['message'] ?? ''); ?></textarea>
                        </div>
                        
                        <button type="submit" class="w-full btn btn-primary">
                            <i class="fas fa-paper-plane mr-2"></i>Send Message
                        </button>
                        
                        <p class="text-sm text-gray-600 text-center">
                            By submitting this form, you agree to our 
                            <a href="privacy.php" class="text-primary hover:underline">Privacy Policy</a> and 
                            <a href="terms.php" class="text-primary hover:underline">Terms of Service</a>.
                        </p>
                    </form>
                </div>
            </div>
        </div>
    </main>

<?php
// Additional scripts for this page
$additional_scripts = <<<EOT
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
EOT;

// Include footer
require_once 'includes/footer.php';
?>