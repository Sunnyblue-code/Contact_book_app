<?php
session_start();
require_once '../config/config.php';
header('Content-Type: application/json');

// Verify CSRF token if provided
if (isset($_SERVER['HTTP_X_CSRF_TOKEN']) && $_SERVER['HTTP_X_CSRF_TOKEN'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

try {
    // Remove remember me token if exists
    if (isset($_COOKIE['remember_token'])) {
        $token = $conn->real_escape_string($_COOKIE['remember_token']);
        $sql = "DELETE FROM user_tokens WHERE token = '$token'";
        $conn->query($sql);

        // Delete the cookie
        setcookie('remember_token', '', time() - 3600, '/', '', true, true);
    }

    // Clear all session data
    $_SESSION = array();

    // Destroy the session cookie
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }

    // Destroy the session
    session_destroy();

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    error_log('Logout error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Error during logout']);
} finally {
    $conn->close();
}
