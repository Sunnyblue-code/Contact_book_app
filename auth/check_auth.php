<?php
require_once 'session.php';
header('Content-Type: application/json');

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    checkSessionTimeout();
    echo json_encode([
        'authenticated' => true,
        'user' => [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username']
        ]
    ]);
} else {
    checkRememberMe(); // Try to authenticate via remember me token
    echo json_encode([
        'authenticated' => isset($_SESSION['user_id']),
        'csrf_token' => generateCsrfToken()
    ]);
}
