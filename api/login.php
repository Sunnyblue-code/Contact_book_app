<?php
require_once '../config/database.php';
require_once '../auth/RateLimiter.php';

$rateLimiter = new RateLimiter($pdo);
$ip = $_SERVER['REMOTE_ADDR'];

// Only allow 5 login attempts per minute
if (!$rateLimiter->isAllowed($ip, 'login', 5, 60)) {
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'error' => 'Too many login attempts. Please try again later.'
    ]);
    exit;
}

// ...existing login code...
