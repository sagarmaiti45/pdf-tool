<?php
// Email functions that work with the configuration

// Email configuration function using PHPMailer (if available) or native mail()
function sendContactEmail($name, $email, $subject, $message) {
    // Check if SMTP password is configured
    if (empty(SMTP_PASSWORD)) {
        error_log("Email configuration error: SMTP_PASSWORD not set. Please configure environment variables.");
        return false;
    }
    
    // Check if PHPMailer is available
    $phpmailerPath = dirname(__DIR__) . '/vendor/autoload.php';
    
    if (file_exists($phpmailerPath)) {
        // Use PHPMailer if available
        require_once $phpmailerPath;
        return sendEmailWithPHPMailer($name, $email, $subject, $message);
    } else {
        // Fallback to native mail() with proper headers
        return sendEmailNative($name, $email, $subject, $message);
    }
}

// PHPMailer implementation
function sendEmailWithPHPMailer($name, $email, $subject, $message) {
    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        // Server settings
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = SMTP_AUTH;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port = SMTP_PORT;
        
        // Recipients
        $mail->setFrom(FROM_EMAIL, FROM_NAME);
        foreach (RECIPIENT_EMAILS as $recipient) {
            $mail->addAddress($recipient);
        }
        $mail->addReplyTo($email, $name);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = "[Contact Form] " . $subject;
        
        // HTML body
        $htmlBody = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #4CAF50; color: white; padding: 20px; text-align: center; }
                .content { background: #f4f4f4; padding: 20px; margin: 20px 0; }
                .field { margin-bottom: 15px; }
                .label { font-weight: bold; color: #333; }
                .value { color: #666; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>New Contact Form Submission</h2>
                </div>
                <div class='content'>
                    <div class='field'>
                        <div class='label'>Name:</div>
                        <div class='value'>" . htmlspecialchars($name) . "</div>
                    </div>
                    <div class='field'>
                        <div class='label'>Email:</div>
                        <div class='value'>" . htmlspecialchars($email) . "</div>
                    </div>
                    <div class='field'>
                        <div class='label'>Subject:</div>
                        <div class='value'>" . htmlspecialchars($subject) . "</div>
                    </div>
                    <div class='field'>
                        <div class='label'>Message:</div>
                        <div class='value'>" . nl2br(htmlspecialchars($message)) . "</div>
                    </div>
                    <hr>
                    <p style='font-size: 12px; color: #999;'>
                        Submitted on: " . date('Y-m-d H:i:s') . "<br>
                        IP Address: " . $_SERVER['REMOTE_ADDR'] . "
                    </p>
                </div>
            </div>
        </body>
        </html>";
        
        $mail->Body = $htmlBody;
        $mail->AltBody = "Name: $name\nEmail: $email\n\nSubject: $subject\n\nMessage:\n$message";
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("PHPMailer Error: " . $mail->ErrorInfo);
        return false;
    }
}

// Native mail() implementation with SMTP authentication headers
function sendEmailNative($name, $email, $subject, $message) {
    $to = implode(', ', RECIPIENT_EMAILS);
    $emailSubject = "[Contact Form] " . $subject;
    
    // Create HTML message
    $htmlMessage = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; }
            .field { margin-bottom: 10px; }
            .label { font-weight: bold; }
        </style>
    </head>
    <body>
        <h3>New Contact Form Submission</h3>
        <div class='field'><span class='label'>Name:</span> " . htmlspecialchars($name) . "</div>
        <div class='field'><span class='label'>Email:</span> " . htmlspecialchars($email) . "</div>
        <div class='field'><span class='label'>Subject:</span> " . htmlspecialchars($subject) . "</div>
        <div class='field'><span class='label'>Message:</span><br>" . nl2br(htmlspecialchars($message)) . "</div>
        <hr>
        <p style='font-size: 12px; color: #666;'>
            Submitted on: " . date('Y-m-d H:i:s') . "<br>
            IP: " . $_SERVER['REMOTE_ADDR'] . "
        </p>
    </body>
    </html>";
    
    // Set headers for HTML email
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . FROM_NAME . " <" . FROM_EMAIL . ">\r\n";
    $headers .= "Reply-To: " . $name . " <" . $email . ">\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
    $headers .= "X-Priority: 3\r\n";
    $headers .= "Return-Path: " . FROM_EMAIL . "\r\n";
    
    // Send email
    return mail($to, $emailSubject, $htmlMessage, $headers);
}
?>