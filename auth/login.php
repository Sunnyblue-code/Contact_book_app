<?php
session_start();
require_once '../config/config.php';

header('Content-Type: application/json');

try {
    // Get and decode input
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);

    // Basic validation
    if (empty($data['username']) || empty($data['password'])) {
        throw new Exception('Username and password are required');
    }

    $conn = getConnection();
    if (!$conn) {
        throw new Exception('Database connection failed');
    }

    // Query for user
    $stmt = $conn->prepare("SELECT id, username, password FROM users WHERE username = ? OR email = ?");
    $stmt->bind_param("ss", $data['username'], $data['username']);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if (!$user || !password_verify($data['password'], $user['password'])) {
        throw new Exception('Invalid username or password');
    }

    // Set session
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];

    echo json_encode([
        'success' => true,
        'message' => 'Login successful'
    ]);
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} finally {
    if (isset($stmt)) $stmt->close();
    if (isset($conn)) $conn->close();
}
