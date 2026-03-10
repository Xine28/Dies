<?php
include "../config/database.php";
require_once "../includes/mailer.php";

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    echo json_encode(["status" => "success"]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        "status" => "error",
        "message" => "Invalid request method."
    ]);
    exit();
}

function getRequestData() {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    $raw = file_get_contents("php://input");

    if (stripos($contentType, 'application/json') !== false) {
        $json = json_decode($raw, true);
        return is_array($json) ? $json : [];
    }

    return !empty($_POST) ? $_POST : [];
}

$data = getRequestData();

if (empty($data['student_id']) || empty($data['department_id'])) {
    http_response_code(400);
    echo json_encode([
        "status" => "error",
        "message" => "Missing required fields."
    ]);
    exit();
}

$student_id = $data['student_id'];
$department_id = $data['department_id'];

try {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ? AND role = 'student'");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch();

    if (!$student) {
        http_response_code(404);
        echo json_encode([
            "status" => "error",
            "message" => "Student not found."
        ]);
        exit();
    }

    $deptStmt = $conn->prepare("SELECT * FROM departments WHERE id = ?");
    $deptStmt->execute([$department_id]);
    $department = $deptStmt->fetch();

    if (!$department) {
        http_response_code(404);
        echo json_encode([
            "status" => "error",
            "message" => "Invalid department."
        ]);
        exit();
    }

    $updateStmt = $conn->prepare("UPDATE users SET department_id = ? WHERE id = ?");
    $updateStmt->execute([$department_id, $student_id]);

    $emailSent = sendDepartmentAssignmentEmail(
        $student['email'],
        $student['full_name'],
        $department['department_name'],
        $department['supervisor_name']
    );

    echo json_encode([
        "status" => "success",
        "message" => "Department assigned successfully.",
        "email_sent" => $emailSent
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Database error.",
        "detail" => $e->getMessage()
    ]);
}
exit();
?>
