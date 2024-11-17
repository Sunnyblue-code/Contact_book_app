<?php
// Initial setup
ini_set('display_errors', 1);
error_reporting(E_ALL);
ob_start();

session_start();
require_once '../config/config.php';
require_once '../auth/protect.php';
require_once '../config/database.php';
require_once '../auth/RateLimiter.php';

// Clear buffer and set headers
ob_clean();
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

// Initialize rate limiter
$rateLimiter = new RateLimiter($pdo);
$ip = $_SERVER['REMOTE_ADDR'];

// Increase rate limit: Allow 300 requests per minute for the contacts API
if (!$rateLimiter->isAllowed($ip, 'contacts', 300, 60)) {
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'error' => 'Rate limit exceeded. Please try again later.'
    ]);
    exit;
}

// Function to sanitize input
function sanitizeInput($input)
{
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

// Function to validate CSRF token
function validateCsrfToken($token)
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Function to send JSON response and exit
function sendJsonResponse($data, $statusCode = 200)
{
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
}

// Check authentication
if (!isset($_SESSION['user_id'])) {
    sendJsonResponse(['success' => false, 'error' => 'Unauthorized'], 401);
}

try {
    // Get input data
    $input = file_get_contents('php://input');
    $data = json_decode($input, true) ?? $_POST;

    $action = isset($data['action']) ? $data['action'] : (isset($_GET['action']) ? $_GET['action'] : '');

    // Validate CSRF token
    $headers = getallheaders();
    $csrfToken = $headers['X-CSRF-TOKEN'] ?? '';

    if (!validateCsrfToken($csrfToken)) {
        throw new Exception('Invalid CSRF token');
    }

    $user_id = (int)$_SESSION['user_id'];

    switch ($action) {
        case 'add':
            if (empty($data['name']) || empty($data['phone'])) {
                throw new Exception('Name and phone are required');
            }

            $stmt = $pdo->prepare("
                INSERT INTO contacts (
                    name, phone, email, address, description, category, user_id
                ) VALUES (
                    :name, :phone, :email, :address, :description, :category, :user_id
                )
            ");

            $result = $stmt->execute([
                ':name' => $data['name'],
                ':phone' => $data['phone'],
                ':email' => $data['email'] ?? '',
                ':address' => $data['address'] ?? '',
                ':description' => $data['description'] ?? '',
                ':category' => $data['category'] ?? 'General',
                ':user_id' => $user_id
            ]);

            if (!$result) {
                throw new Exception('Failed to add contact');
            }

            sendJsonResponse([
                'success' => true,
                'id' => $pdo->lastInsertId(),
                'message' => 'Contact added successfully'
            ]);
            break;

        case 'get':
            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                $perPage = isset($_GET['perPage']) ? (int)$_GET['perPage'] : 10;
                $sort = isset($_GET['sort']) ? $_GET['sort'] : 'name';
                $order = isset($_GET['order']) ? $_GET['order'] : 'asc';

                // Validate sort field
                $allowedSortFields = ['name', 'category', 'created_at'];
                if (!in_array($sort, $allowedSortFields)) {
                    $sort = 'name';
                }

                // Validate order
                $order = strtolower($order) === 'desc' ? 'DESC' : 'ASC';

                $offset = ($page - 1) * $perPage;

                // Get total count
                $stmt = $pdo->query("SELECT COUNT(*) FROM contacts");
                $total = $stmt->fetchColumn();

                // Get contacts with sorting
                $stmt = $pdo->prepare("
                    SELECT * FROM contacts 
                    ORDER BY $sort $order 
                    LIMIT :limit OFFSET :offset
                ");

                $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
                $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
                $stmt->execute();

                $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode([
                    'success' => true,
                    'contacts' => $contacts,
                    'total' => $total
                ]);
                exit;
            }
            break;

        case 'edit':
            if (empty($data['id']) || empty($data['name']) || empty($data['phone'])) {
                throw new Exception('ID, Name and Phone are required');
            }

            $stmt = $conn->prepare("UPDATE contacts SET name=?, phone=?, email=?, address=?, description=?, category=? WHERE id=? AND user_id=?");
            if (!$stmt) {
                throw new Exception($conn->error);
            }

            $id = (int)$data['id'];
            $name = sanitizeInput($data['name']);
            $phone = sanitizeInput($data['phone']);
            $email = sanitizeInput($data['email'] ?? '');
            $address = sanitizeInput($data['address'] ?? '');
            $description = sanitizeInput($data['description'] ?? '');
            $category = sanitizeInput($data['category'] ?? 'General');

            $stmt->bind_param(
                "ssssssii",
                $name,
                $phone,
                $email,
                $address,
                $description,
                $category,
                $id,
                $user_id
            );

            if (!$stmt->execute()) {
                throw new Exception('Failed to update contact: ' . $stmt->error);
            }

            sendJsonResponse([
                'success' => true,
                'message' => 'Contact updated successfully'
            ]);
            break;

        case 'delete':
            $id = $conn->real_escape_string($_POST['id']);
            $sql = "DELETE FROM contacts WHERE id=$id AND user_id=$user_id";
            sendJsonResponse(['success' => $conn->query($sql)]);
            break;

        case 'import':
            if (!isset($_FILES['csvFile'])) {
                sendJsonResponse(['success' => false, 'error' => 'No file uploaded']);
                break;
            }

            $file = $_FILES['csvFile']['tmp_name'];
            $count = 0;

            if (($handle = fopen($file, "r")) !== FALSE) {
                // Skip header row
                fgetcsv($handle);

                while (($data = fgetcsv($handle)) !== FALSE) {
                    $name = $conn->real_escape_string($data[0]);
                    $phone = $conn->real_escape_string($data[1]);
                    $email = $conn->real_escape_string($data[2]);
                    $address = $conn->real_escape_string($data[3]);
                    $category = $conn->real_escape_string($data[4] ?? 'General');

                    $sql = "INSERT INTO contacts (name, phone, email, address, category, user_id) 
                            VALUES ('$name', '$phone', '$email', '$address', '$category', $user_id)";

                    if ($conn->query($sql)) {
                        $count++;
                    }
                }
                fclose($handle);
            }

            sendJsonResponse(['success' => true, 'count' => $count]);
            break;

        case 'toggle_favorite':
            try {
                // Validate input
                $data = json_decode(file_get_contents('php://input'), true);
                $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);

                if (!$id) {
                    throw new Exception('Invalid contact ID');
                }

                // First, get the current favorite status
                $stmt = $pdo->prepare("SELECT is_favorite FROM contacts WHERE id = ? AND user_id = ?");
                $stmt->execute([$id, $_SESSION['user_id']]);
                $contact = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$contact) {
                    throw new Exception('Contact not found');
                }

                // Toggle the favorite status
                $newStatus = $contact['is_favorite'] ? 0 : 1;

                // Update the database
                $stmt = $pdo->prepare("UPDATE contacts SET is_favorite = ? WHERE id = ? AND user_id = ?");
                $success = $stmt->execute([$newStatus, $id, $_SESSION['user_id']]);

                if (!$success) {
                    throw new Exception('Failed to update favorite status');
                }

                echo json_encode([
                    'success' => true,
                    'is_favorite' => $newStatus
                ]);
            } catch (Exception $e) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => $e->getMessage()
                ]);
            }
            exit;

        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    error_log('Contact API Error: ' . $e->getMessage());
    sendJsonResponse([
        'success' => false,
        'error' => $e->getMessage()
    ], 500);
} finally {
    if (isset($stmt) && $stmt instanceof mysqli_stmt) {
        $stmt->close();
    }
    if (isset($countStmt) && $countStmt instanceof mysqli_stmt) {
        $countStmt->close();
    }
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
}
