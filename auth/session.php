<?php
session_start();
require_once __DIR__ . '/../config/database.php';

function generateToken()
{
    return bin2hex(random_bytes(32));
}

function createSession($userId)
{
    global $pdo;
    $token = generateToken();
    $expiry = date('Y-m-d H:i:s', strtotime('+30 days'));

    try {
        $stmt = $pdo->prepare("INSERT INTO sessions (user_id, token, expiry) VALUES (?, ?, ?)");
        $stmt->execute([$userId, $token, $expiry]);

        setcookie('session_token', $token, strtotime('+30 days'), '/', '', true, true);
        $_SESSION['user_id'] = $userId;

        return true;
    } catch (PDOException $e) {
        error_log('Session creation error: ' . $e->getMessage());
        return false;
    }
}

function validateSession($token)
{
    global $pdo;

    try {
        $stmt = $pdo->prepare("
            SELECT s.*, u.username 
            FROM sessions s 
            JOIN users u ON s.user_id = u.id 
            WHERE s.token = ? AND s.expiry > NOW()
        ");
        $stmt->execute([$token]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($session) {
            $_SESSION['user_id'] = $session['user_id'];
            $_SESSION['username'] = $session['username'];
            return true;
        }

        return false;
    } catch (PDOException $e) {
        error_log('Session validation error: ' . $e->getMessage());
        return false;
    }
}

function destroySession()
{
    global $pdo;

    if (isset($_COOKIE['session_token'])) {
        try {
            $stmt = $pdo->prepare("DELETE FROM sessions WHERE token = ?");
            $stmt->execute([$_COOKIE['session_token']]);
        } catch (PDOException $e) {
            error_log('Session destruction error: ' . $e->getMessage());
        }

        setcookie('session_token', '', time() - 3600, '/');
    }

    session_destroy();
}

function cleanupTokens()
{
    global $pdo;

    try {
        $stmt = $pdo->prepare("DELETE FROM sessions WHERE expiry < NOW()");
        $stmt->execute();
    } catch (PDOException $e) {
        error_log('Token cleanup error: ' . $e->getMessage());
    }
}

// Clean up expired tokens periodically (1% chance on each request)
if (mt_rand(1, 100) === 1) {
    cleanupTokens();
}

// Validate session token if it exists
if (isset($_COOKIE['session_token']) && !isset($_SESSION['user_id'])) {
    validateSession($_COOKIE['session_token']);
}
