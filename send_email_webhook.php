<?php
// Alternative email solution using webhook services
// This file provides multiple reliable methods to send emails

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

function logError($message) {
    $log_file = 'webhook_error_log.txt';
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] $message\n";
    @file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
}

function sendViaWebhook($data) {
    // Method 1: Using Formspree (free service)
    $formspree_url = "https://formspree.io/f/YOUR_FORMSPREE_ID"; // You need to sign up at formspree.io
    
    $formspree_data = [
        'name' => $data['name'],
        'email' => $data['email'],
        'phone' => $data['phone'],
        'company' => $data['company'],
        'service' => $data['service'],
        'budget' => $data['budget'],
        'timeline' => $data['timeline'],
        'message' => $data['message'],
        '_subject' => "New Project Inquiry from " . $data['name'] . " - Noventra"
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $formspree_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($formspree_data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded'
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200 || $http_code === 302) {
        return ['success' => true, 'method' => 'Formspree'];
    }
    
    return ['success' => false, 'method' => 'Formspree', 'error' => $response];
}

function sendViaEmailJS($data) {
    // Method 2: Using EmailJS (requires setup)
    // This is a JavaScript-based solution that can be called from the frontend
    
    $emailjs_data = [
        'service_id' => 'YOUR_EMAILJS_SERVICE_ID',
        'template_id' => 'YOUR_EMAILJS_TEMPLATE_ID',
        'user_id' => 'YOUR_EMAILJS_USER_ID',
        'template_params' => [
            'to_email' => 'noventrawebsolutions@gmail.com',
            'from_name' => $data['name'],
            'from_email' => $data['email'],
            'phone' => $data['phone'],
            'company' => $data['company'],
            'service' => $data['service'],
            'budget' => $data['budget'],
            'timeline' => $data['timeline'],
            'message' => $data['message']
        ]
    ];
    
    return ['success' => true, 'method' => 'EmailJS', 'data' => $emailjs_data];
}

function sendViaSMTP($data) {
    // Method 3: Using PHPMailer with SMTP (most reliable)
    // This requires PHPMailer library to be installed
    
    $smtp_config = [
        'host' => 'smtp.gmail.com',
        'port' => 587,
        'username' => 'your-email@gmail.com', // Your Gmail address
        'password' => 'your-app-password', // Gmail App Password
        'encryption' => 'tls'
    ];
    
    // This is a placeholder - you would need to implement PHPMailer
    return ['success' => false, 'method' => 'SMTP', 'note' => 'Requires PHPMailer setup'];
}

function saveToFile($data) {
    // Method 4: Save to file for manual processing
    $email_content = "
=== NEW CONTACT FORM SUBMISSION ===
Time: " . date('Y-m-d H:i:s') . "
Name: " . $data['name'] . "
Email: " . $data['email'] . "
Phone: " . $data['phone'] . "
Company: " . $data['company'] . "
Service: " . $data['service'] . "
Budget: " . $data['budget'] . "
Timeline: " . $data['timeline'] . "
Message: " . $data['message'] . "
=====================================

";
    
    $file = 'contact_submissions.txt';
    if (@file_put_contents($file, $email_content, FILE_APPEND | LOCK_EX)) {
        return ['success' => true, 'method' => 'File storage'];
    }
    
    return ['success' => false, 'method' => 'File storage'];
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
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
    
    // Sanitize data
    $data = [
        'name' => htmlspecialchars(trim($input['name'])),
        'email' => filter_var(trim($input['email']), FILTER_SANITIZE_EMAIL),
        'phone' => htmlspecialchars(trim($input['phone'] ?? '')),
        'company' => htmlspecialchars(trim($input['company'] ?? '')),
        'service' => htmlspecialchars(trim($input['service'])),
        'budget' => htmlspecialchars(trim($input['budget'] ?? '')),
        'timeline' => htmlspecialchars(trim($input['timeline'] ?? '')),
        'message' => htmlspecialchars(trim($input['message']))
    ];
    
    // Try multiple methods
    $methods_tried = [];
    $success = false;
    $success_method = '';
    
    // Method 1: Try webhook service
    $webhook_result = sendViaWebhook($data);
    $methods_tried[] = $webhook_result['method'];
    
    if ($webhook_result['success']) {
        $success = true;
        $success_method = $webhook_result['method'];
    }
    
    // Method 2: Try file storage
    if (!$success) {
        $file_result = saveToFile($data);
        $methods_tried[] = $file_result['method'];
        
        if ($file_result['success']) {
            $success = true;
            $success_method = $file_result['method'];
        }
    }
    
    // Log the attempt
    $log_entry = date('Y-m-d H:i:s') . " - Webhook attempt from " . $data['name'] . " (" . $data['email'] . ") - Methods: " . implode(', ', $methods_tried) . " - Success: " . ($success ? 'Yes' : 'No') . "\n";
    @file_put_contents('webhook_log.txt', $log_entry, FILE_APPEND | LOCK_EX);
    
    if ($success) {
        echo json_encode([
            'success' => true,
            'message' => 'Thank you! Your message has been sent successfully. We will get back to you within 2 hours.',
            'method' => $success_method
        ]);
    } else {
        throw new Exception('All delivery methods failed. Please contact us directly at noventrawebsolutions@gmail.com');
    }
    
} catch (Exception $e) {
    logError("Webhook error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?> 