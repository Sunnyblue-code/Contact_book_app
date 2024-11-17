<?php
require_once __DIR__ . '/session.php';

function checkAuth()
{
    // Check if user is logged in via session
    if (isset($_SESSION['user_id'])) {
        return true;
    }

    // Check if user has a valid session token cookie
    if (isset($_COOKIE['session_token'])) {
        if (validateSession($_COOKIE['session_token'])) {
            return true;
        }
        // Invalid or expired token, clear it
        setcookie('session_token', '', time() - 3600, '/');
    }

    return false;
}

function generateCsrfToken()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function protectApi()
{
    // Check authentication
    if (!isset($_SESSION['user_id'])) {
        header('HTTP/1.1 401 Unauthorized');
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }

    // Verify CSRF token for POST requests
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_SERVER['HTTP_X_CSRF_TOKEN']) || $_SERVER['HTTP_X_CSRF_TOKEN'] !== $_SESSION['csrf_token']) {
            header('HTTP/1.1 403 Forbidden');
            echo json_encode(['error' => 'Invalid CSRF token']);
            exit;
        }
    }

    // Update last activity
    $_SESSION['last_activity'] = time();
}

function protectPage()
{
    if (!checkAuth()) {
        header('Location: login.php');
        exit;
    }
    return generateCsrfToken();
}
