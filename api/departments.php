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
            $stmt = $conn->prepare("SELECT * FROM departments WHERE id = ?");
            $stmt->execute([$_GET['id']]);
            $department = $stmt->fetch();

            if (!$department) {
                http_response_code(404);
                echo json_encode(["status" => "error", "message" => "Department not found."]);
                exit();
            }

            echo json_encode(["status" => "success", "data" => $department]);
            exit();
        }

        $stmt = $conn->query("SELECT * FROM departments");
        echo json_encode(["status" => "success", "data" => $stmt->fetchAll()]);
        exit();
    }

    $data = getRequestData();

    if ($method === 'POST') {
        if (empty($data['department_name']) || empty($data['supervisor_name'])) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Missing required fields."]);
            exit();
        }

        $stmt = $conn->prepare("
            INSERT INTO departments (department_name, supervisor_name)
            VALUES (?, ?)
        ");
        $stmt->execute([$data['department_name'], $data['supervisor_name']]);

        http_response_code(201);
        echo json_encode([
            "status" => "success",
            "message" => "Department created successfully.",
            "id" => (int)$conn->lastInsertId()
        ]);
        exit();
    }

    if ($method === 'PUT') {
        if (empty($data['id']) || empty($data['department_name']) || empty($data['supervisor_name'])) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Missing required fields."]);
            exit();
        }

        $stmt = $conn->prepare("
            UPDATE departments
            SET department_name = ?, supervisor_name = ?
            WHERE id = ?
        ");
        $stmt->execute([$data['department_name'], $data['supervisor_name'], $data['id']]);

        echo json_encode(["status" => "success", "message" => "Department updated successfully."]);
        exit();
    }

    if ($method === 'PATCH') {
        if (empty($data['id']) || !array_key_exists('department_name', $data) || !array_key_exists('supervisor_name', $data)) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Missing required fields."]);
            exit();
        }

        $stmt = $conn->prepare("
            UPDATE departments
            SET department_name = ?, supervisor_name = ?
            WHERE id = ?
        ");
        $stmt->execute([$data['department_name'], $data['supervisor_name'], $data['id']]);

        echo json_encode(["status" => "success", "message" => "Department patched successfully."]);
        exit();
    }

    if ($method === 'DELETE') {
        $id = $data['id'] ?? ($_GET['id'] ?? null);
        if (empty($id)) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Missing department ID."]);
            exit();
        }

        $stmt = $conn->prepare("DELETE FROM departments WHERE id = ?");
        $stmt->execute([$id]);

        echo json_encode(["status" => "success", "message" => "Department deleted successfully."]);
        exit();
    }

    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Invalid request method."]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database error.", "detail" => $e->getMessage()]);
}
exit();
?>
