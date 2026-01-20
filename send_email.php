<?php
// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set headers for JSON response
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Function to log errors
function logError($message) {
    $log_file = 'error_log.txt';
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] $message\n";
    
    // Try to write to log file, but don't fail if we can't
    @file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
}

// Function to send email with multiple methods
function sendEmailWithMultipleMethods($to, $subject, $message, $headers) {
    $success = false;
    $methods_tried = [];
    
    // Method 1: Try PHP mail() function
    try {
        $mail_sent = @mail($to, $subject, $message, $headers);
        $methods_tried[] = "PHP mail() function";
        
        if ($mail_sent) {
            logError("Email sent successfully via PHP mail() function");
            return ['success' => true, 'method' => 'PHP mail()'];
        }
    } catch (Exception $e) {
        logError("PHP mail() failed: " . $e->getMessage());
    }
    
    // Method 2: Try with different headers
    try {
        $alt_headers = array(
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'From: Noventra <noreply@noventra.com>',
            'Reply-To: noreply@noventra.com',
            'X-Mailer: PHP/' . phpversion()
        );
        
        $mail_sent = @mail($to, $subject, $message, implode("\r\n", $alt_headers));
        $methods_tried[] = "PHP mail() with alt headers";
        
        if ($mail_sent) {
            logError("Email sent successfully via PHP mail() with alt headers");
            return ['success' => true, 'method' => 'PHP mail() alt headers'];
        }
    } catch (Exception $e) {
        logError("PHP mail() alt headers failed: " . $e->getMessage());
    }
    
    // Method 3: Try with simple text email
    try {
        $text_message = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $message));
        $text_headers = array(
            'From: Noventra <noreply@noventra.com>',
            'Reply-To: noreply@noventra.com',
            'X-Mailer: PHP/' . phpversion()
        );
        
        $mail_sent = @mail($to, $subject, $text_message, implode("\r\n", $text_headers));
        $methods_tried[] = "PHP mail() text only";
        
        if ($mail_sent) {
            logError("Email sent successfully via PHP mail() text only");
            return ['success' => true, 'method' => 'PHP mail() text only'];
        }
    } catch (Exception $e) {
        logError("PHP mail() text only failed: " . $e->getMessage());
    }
    
    // Method 4: Save to file for manual processing
    $email_data = [
        'to' => $to,
        'subject' => $subject,
        'message' => $message,
        'headers' => $headers,
        'timestamp' => date('Y-m-d H:i:s'),
        'methods_tried' => $methods_tried
    ];
    
    $email_file = 'pending_emails.txt';
    $email_entry = json_encode($email_data) . "\n";
    
    if (@file_put_contents($email_file, $email_entry, FILE_APPEND | LOCK_EX)) {
        logError("Email saved to pending_emails.txt for manual processing");
        return ['success' => true, 'method' => 'File storage', 'note' => 'Check pending_emails.txt'];
    }
    
    logError("All email methods failed. Methods tried: " . implode(', ', $methods_tried));
    return ['success' => false, 'methods_tried' => $methods_tried];
}

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Check if JSON decode failed
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON data received');
    }
    
    // Validate required fields
    $required_fields = ['name', 'email', 'service', 'message'];
    $missing_fields = [];
    
    foreach ($required_fields as $field) {
        if (empty($input[$field])) {
            $missing_fields[] = $field;
        }
    }
    
    if (!empty($missing_fields)) {
        throw new Exception('Missing required fields: ' . implode(', ', $missing_fields));
    }
    
    // Validate email format
    if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format');
    }
    
    // Sanitize input data
    $name = htmlspecialchars(trim($input['name']));
    $email = filter_var(trim($input['email']), FILTER_SANITIZE_EMAIL);
    $phone = htmlspecialchars(trim($input['phone'] ?? ''));
    $company = htmlspecialchars(trim($input['company'] ?? ''));
    $service = htmlspecialchars(trim($input['service']));
    $budget = htmlspecialchars(trim($input['budget'] ?? ''));
    $timeline = htmlspecialchars(trim($input['timeline'] ?? ''));
    $message = htmlspecialchars(trim($input['message']));
    
    // Your official email address - ALL contact form submissions will be sent here
    $to_email = 'noventrawebsolutions@gmail.com';
    
    // Email subject
    $subject = "URGENT: New Project Inquiry from $name - Noventra Contact Form";
    
    // Build email body
    $email_body = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .header { background: linear-gradient(135deg, #00d4ff 0%, #ff6b6b 100%); color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; }
            .field { margin-bottom: 15px; }
            .label { font-weight: bold; color: #00d4ff; }
            .value { margin-left: 10px; }
            .footer { background: #f5f5f5; padding: 15px; text-align: center; font-size: 12px; color: #666; }
            .urgent { background: #ffeb3b; color: #333; padding: 10px; border-radius: 5px; margin: 10px 0; }
        </style>
    </head>
    <body>
        <div class='header'>
            <h2>🚨 NEW PROJECT INQUIRY</h2>
            <p>Noventra Contact Form Submission</p>
        </div>
        
        <div class='urgent'>
            <strong>⚠️ URGENT: New inquiry received at " . date('F j, Y \a\t g:i A') . "</strong>
        </div>
        
        <div class='content'>
            <div class='field'>
                <span class='label'>Full Name:</span>
                <span class='value'>$name</span>
            </div>
            
            <div class='field'>
                <span class='label'>Email Address:</span>
                <span class='value'>$email</span>
            </div>
            
            <div class='field'>
                <span class='label'>Phone Number:</span>
                <span class='value'>" . ($phone ? $phone : 'Not provided') . "</span>
            </div>
            
            <div class='field'>
                <span class='label'>Company Name:</span>
                <span class='value'>" . ($company ? $company : 'Not provided') . "</span>
            </div>
            
            <div class='field'>
                <span class='label'>Service Required:</span>
                <span class='value'>$service</span>
            </div>
            
            <div class='field'>
                <span class='label'>Project Budget:</span>
                <span class='value'>" . ($budget ? $budget : 'Not specified') . "</span>
            </div>
            
            <div class='field'>
                <span class='label'>Project Timeline:</span>
                <span class='value'>" . ($timeline ? $timeline : 'Not specified') . "</span>
            </div>
            
            <div class='field'>
                <span class='label'>Project Details:</span>
                <div class='value' style='margin-top: 10px; padding: 15px; background: #f9f9f9; border-left: 4px solid #00d4ff;'>
                    " . nl2br($message) . "
                </div>
            </div>
        </div>
        
        <div class='footer'>
            <p>This message was sent from the Noventra website contact form.</p>
            <p>Submitted on: " . date('F j, Y \a\t g:i A') . "</p>
            <p><strong>Please respond within 2 hours!</strong></p>
        </div>
    </body>
    </html>
    ";
    
    // Email headers - optimized for better delivery
    $headers = array(
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: Noventra Contact Form <noreply@' . $_SERVER['HTTP_HOST'] . '>',
        'Reply-To: ' . $email,
        'X-Mailer: PHP/' . phpversion(),
        'X-Priority: 1',
        'Importance: High',
        'X-MSMail-Priority: High'
    );
    
    // Send email to you using multiple methods
    $email_result = sendEmailWithMultipleMethods($to_email, $subject, $email_body, implode("\r\n", $headers));
    
    if ($email_result['success']) {
        // Send confirmation email to customer
        $customer_subject = "Thank you for contacting Noventra - We'll get back to you soon!";
        
        $customer_email_body = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .header { background: linear-gradient(135deg, #00d4ff 0%, #ff6b6b 100%); color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; }
                .footer { background: #f5f5f5; padding: 15px; text-align: center; font-size: 12px; color: #666; }
            </style>
        </head>
        <body>
            <div class='header'>
                <h2>Thank You!</h2>
                <p>We've received your message</p>
            </div>
            
            <div class='content'>
                <p>Dear $name,</p>
                
                <p>Thank you for contacting <strong>Noventra</strong>. We have successfully received your project inquiry and will get back to you within 2 hours with a detailed proposal and timeline.</p>
                
                <p><strong>Your inquiry details:</strong></p>
                <ul>
                    <li><strong>Service:</strong> $service</li>
                    <li><strong>Submitted:</strong> " . date('F j, Y \a\t g:i A') . "</li>
                </ul>
                
                <p>In the meantime, if you have any urgent questions, feel free to:</p>
                <ul>
                    <li>Call us: <strong>+94 772 117 828</strong></li>
                    <li>Email us directly: <strong>noventrawebsolutions@gmail.com</strong></li>
                </ul>
                
                <p>Best regards,<br>
                <strong>Sahan Weerasinghe</strong><br>
                Founder & CEO, Noventra</p>
            </div>
            
            <div class='footer'>
                <p>Noventra - Transforming businesses through innovative technology solutions</p>
            </div>
        </body>
        </html>
        ";
        
        $customer_headers = array(
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'From: Noventra <noreply@' . $_SERVER['HTTP_HOST'] . '>',
            'X-Mailer: PHP/' . phpversion()
        );
        
        // Send confirmation email to customer
        sendEmailWithMultipleMethods($email, $customer_subject, $customer_email_body, implode("\r\n", $customer_headers));
        
        // Log the submission and create notification
        $log_entry = date('Y-m-d H:i:s') . " - New inquiry from $name ($email) - Service: $service - Method: " . $email_result['method'] . "\n";
        @file_put_contents('contact_log.txt', $log_entry, FILE_APPEND | LOCK_EX);
        
        // Create immediate notification file for monitoring
        $notification = [
            'timestamp' => date('Y-m-d H:i:s'),
            'name' => $name,
            'email' => $email,
            'service' => $service,
            'status' => 'new',
            'delivery_method' => $email_result['method']
        ];
        @file_put_contents('new_inquiry.json', json_encode($notification));
        
        $success_message = 'Thank you! Your message has been sent successfully. We will get back to you within 2 hours.';
        if (isset($email_result['note'])) {
            $success_message .= ' (' . $email_result['note'] . ')';
        }
        
        echo json_encode([
            'success' => true, 
            'message' => $success_message,
            'delivery_method' => $email_result['method']
        ]);
    } else {
        throw new Exception('Failed to send email. Please try again or contact us directly. Methods tried: ' . implode(', ', $email_result['methods_tried']));
    }
    
} catch (Exception $e) {
    logError("Contact form error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}
?> 