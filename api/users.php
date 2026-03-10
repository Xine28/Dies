<?php
include "../config/database.php";

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'OPTIONS') {
    http_response_code(200);
    echo json_encode(["status" => "success"]);
    exit();
}

function getRequestData() {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    $raw = file_get_contents("php://input");

    if (stripos($contentType, 'application/json') !== false) {
        $json = json_decode($raw, true);
        return is_array($json) ? $json : [];
    }

    if (!empty($_POST)) {
        return $_POST;
    }

    parse_str($raw, $data);
    return is_array($data) ? $data : [];
}

try {
    if ($method === 'GET') {
        if (isset($_GET['id'])) {
            $stmt = $conn->prepare("
                SELECT users.*, departments.department_name
                FROM users
                LEFT JOIN departments ON users.department_id = departments.id
                WHERE users.id = ?
            ");
            $stmt->execute([$_GET['id']]);
            $user = $stmt->fetch();

            if (!$user) {
                http_response_code(404);
                echo json_encode(["status" => "error", "message" => "User not found."]);
                exit();
            }

            echo json_encode(["status" => "success", "data" => $user]);
            exit();
        }

        $stmt = $conn->query("
            SELECT users.*, departments.department_name
            FROM users
            LEFT JOIN departments ON users.department_id = departments.id
            WHERE users.role = 'student'
        ");

        echo json_encode(["status" => "success", "data" => $stmt->fetchAll()]);
        exit();
    }

    $data = getRequestData();

    if ($method === 'POST') {
        if (empty($data['full_name']) || empty($data['email']) || empty($data['password'])) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Missing required fields."]);
            exit();
        }

        $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
        $stmt = $conn->prepare("
            INSERT INTO users (role, full_name, email, password, profile_completed)
            VALUES ('student', ?, ?, ?, 1)
        ");
        $stmt->execute([$data['full_name'], $data['email'], $hashedPassword]);

        http_response_code(201);
        echo json_encode([
            "status" => "success",
            "message" => "Student registered successfully.",
            "id" => (int)$conn->lastInsertId()
        ]);
        exit();
    }

    if ($method === 'PUT') {
        if (empty($data['id']) || empty($data['full_name']) || empty($data['email'])) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Missing required fields."]);
            exit();
        }

        $stmt = $conn->prepare("
            UPDATE users
            SET full_name = ?, email = ?
            WHERE id = ?
        ");
        $stmt->execute([$data['full_name'], $data['email'], $data['id']]);

        echo json_encode(["status" => "success", "message" => "User updated successfully."]);
        exit();
    }

    if ($method === 'PATCH') {
        if (empty($data['id']) || !array_key_exists('department_id', $data)) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Missing required fields."]);
            exit();
        }

        $stmt = $conn->prepare("
            UPDATE users
            SET department_id = ?
            WHERE id = ?
        ");
        $stmt->execute([$data['department_id'] ?: null, $data['id']]);

        echo json_encode(["status" => "success", "message" => "Department updated successfully."]);
        exit();
    }

    if ($method === 'DELETE') {
        $id = $data['id'] ?? ($_GET['id'] ?? null);
        if (empty($id)) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Missing user ID."]);
            exit();
        }

        // Prevent deleting interns that already have evaluation records.
        $evalCheck = $conn->prepare("SELECT COUNT(*) FROM evaluations WHERE intern_id = ?");
        $evalCheck->execute([$id]);
        $evaluationCount = (int) $evalCheck->fetchColumn();
        if ($evaluationCount > 0) {
            http_response_code(409);
            echo json_encode([
                "status" => "error",
                "message" => "Cannot delete user: evaluation records already exist."
            ]);
            exit();
        }

        $stmt = $conn->prepare("SELECT resume FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $user = $stmt->fetch();

        if ($user && !empty($user['resume'])) {
            $resumePath = "../assets/uploads/resumes/" . $user['resume'];
            if (file_exists($resumePath)) {
                unlink($resumePath);
            }
        }

        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);

        echo json_encode(["status" => "success", "message" => "User deleted successfully."]);
        exit();
    }

    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Invalid request method."]);
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        http_response_code(409);
        echo json_encode(["status" => "error", "message" => "Email already exists."]);
        exit();
    }

    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database error.", "detail" => $e->getMessage()]);
}
exit();
?>
