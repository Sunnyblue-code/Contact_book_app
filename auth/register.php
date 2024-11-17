<?php
require_once '../config/config.php';
header('Content-Type: application/json');

try {
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);

    // Validate input
    if (empty($data['username']) || empty($data['email']) || empty($data['password'])) {
        throw new Exception('All fields are required');
    }

    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format');
    }

    $conn = getConnection();
    if (!$conn) {
        throw new Exception('Database connection failed');
    }

    // Check if username or email already exists
    $check = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $check->bind_param("ss", $data['username'], $data['email']);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        throw new Exception('Username or email already exists');
    }

    // Hash password and insert user
    $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $data['username'], $data['email'], $hashedPassword);

    if (!$stmt->execute()) {
        throw new Exception('Registration failed: ' . $stmt->error);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Registration successful'
    ]);
} catch (Exception $e) {
    error_log('Registration error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} finally {
    if (isset($stmt)) $stmt->close();
    if (isset($check)) $check->close();
    if (isset($conn)) $conn->close();
}
