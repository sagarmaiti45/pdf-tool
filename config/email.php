<?php
/**
 * Email configuration for contact form
 */

function sendContactEmail($name, $email, $subject, $message) {
    // Simple email function using PHP mail()
    $to = 'info@freshyporta.com';
    $emailSubject = 'Contact Form: ' . $subject;
    
    $emailBody = "Name: $name\n";
    $emailBody .= "Email: $email\n";
    $emailBody .= "Subject: $subject\n\n";
    $emailBody .= "Message:\n$message\n";
    
    $headers = "From: $email\r\n";
    $headers .= "Reply-To: $email\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    
    // Log the submission regardless of mail() success
    $contactLog = __DIR__ . '/../logs/contact_submissions.log';
    $logEntry = date('Y-m-d H:i:s') . " | $name | $email | $subject | " . substr($message, 0, 100) . "...\n";
    @file_put_contents($contactLog, $logEntry, FILE_APPEND | LOCK_EX);
    
    // Try to send email
    return @mail($to, $emailSubject, $emailBody, $headers);
}