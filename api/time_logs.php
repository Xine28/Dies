<?php
include "../config/database.php";
date_default_timezone_set('Asia/Manila');

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'OPTIONS') {
    http_response_code(200);
    echo json_encode(["status" => "success"]);
    exit();
}

if ($method !== 'GET' && $method !== 'POST' && $method !== 'PUT') {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Invalid request method."]);
    exit();
}

function getRequestData()
{
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

function formatDurationFromMinutes($minutes)
{
    $mins = max(0, (int) $minutes);
    $hours = intdiv($mins, 60);
    $remain = $mins % 60;

    if ($hours > 0 && $remain > 0) {
        return $hours . "h " . $remain . "m";
    }
    if ($hours > 0) {
        return $hours . "h";
    }
    return $remain . "m";
}

function normalizeLogDate($dateValue)
{
    $dateValue = trim((string) $dateValue);
    if ($dateValue === '') {
        return null;
    }
    $dt = DateTime::createFromFormat('Y-m-d', $dateValue, new DateTimeZone('Asia/Manila'));
    if (!$dt || $dt->format('Y-m-d') !== $dateValue) {
        return null;
    }
    return $dateValue;
}

function normalizeLogDateTime($rawValue, $logDate)
{
    if ($rawValue === null) {
        return null;
    }

    $value = trim((string) $rawValue);
    if ($value === '' || strtolower($value) === 'null') {
        return null;
    }

    if (preg_match('/^\d{1,2}:\d{2}(:\d{2})?\s*(AM|PM)?$/i', $value)) {
        $parsed = strtotime($logDate . ' ' . $value);
    } else {
        $parsed = strtotime($value);
    }

    if ($parsed === false) {
        return false;
    }

    return date('Y-m-d H:i:s', $parsed);
}

try {
    $conn->exec("
        CREATE TABLE IF NOT EXISTS student_time_logs (
            id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            student_id INT(11) NOT NULL,
            log_date DATE NOT NULL,
            time_in DATETIME DEFAULT NULL,
            time_out DATETIME DEFAULT NULL,
            reminder_sent_at DATETIME DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_student_date (student_id, log_date),
            KEY idx_student_id (student_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
    $conn->exec("ALTER TABLE student_time_logs ADD COLUMN IF NOT EXISTS reminder_sent_at DATETIME NULL");
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Failed to prepare time_logs table.", "detail" => $e->getMessage()]);
    exit();
}

if ($method === 'GET') {
    try {
        $studentId = isset($_GET['student_id']) ? (int) $_GET['student_id'] : 0;
        $latest = isset($_GET['latest']) ? (int) $_GET['latest'] : 0;
        $summary = isset($_GET['summary']) ? (int) $_GET['summary'] : 0;

        if ($studentId <= 0) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "student_id is required."]);
            exit();
        }

        if ($summary === 1) {
            $sumStmt = $conn->prepare("
                SELECT COALESCE(SUM(TIMESTAMPDIFF(MINUTE, time_in, time_out)), 0) AS total_minutes
                FROM student_time_logs
                WHERE student_id = ? AND time_in IS NOT NULL AND time_out IS NOT NULL
            ");
            $sumStmt->execute([$studentId]);
            $totalMinutes = (int) $sumStmt->fetchColumn();

            echo json_encode([
                "status" => "success",
                "data" => [
                    "student_id" => $studentId,
                    "total_minutes" => $totalMinutes,
                    "total_duration" => formatDurationFromMinutes($totalMinutes)
                ]
            ]);
            exit();
        }

        if ($latest === 1) {
            $stmt = $conn->prepare("
                SELECT *,
                       CASE
                           WHEN time_in IS NOT NULL AND time_out IS NOT NULL
                           THEN GREATEST(TIMESTAMPDIFF(MINUTE, time_in, time_out), 0)
                           ELSE NULL
                       END AS duration_minutes
                FROM student_time_logs
                WHERE student_id = ?
                ORDER BY log_date DESC, id DESC
                LIMIT 1
            ");
            $stmt->execute([$studentId]);
            $row = $stmt->fetch();

            if (!$row) {
                http_response_code(404);
                echo json_encode(["status" => "error", "message" => "No time logs found."]);
                exit();
            }

            if ($row['duration_minutes'] !== null) {
                $row['duration_text'] = formatDurationFromMinutes((int) $row['duration_minutes']);
            } else {
                $row['duration_text'] = null;
            }

            echo json_encode(["status" => "success", "data" => $row]);
            exit();
        }

        $stmt = $conn->prepare("
            SELECT *,
                   CASE
                       WHEN time_in IS NOT NULL AND time_out IS NOT NULL
                       THEN GREATEST(TIMESTAMPDIFF(MINUTE, time_in, time_out), 0)
                       ELSE NULL
                   END AS duration_minutes
            FROM student_time_logs
            WHERE student_id = ?
            ORDER BY log_date DESC, id DESC
        ");
        $stmt->execute([$studentId]);
        $rows = $stmt->fetchAll();

        foreach ($rows as &$row) {
            $row['duration_text'] = $row['duration_minutes'] !== null
                ? formatDurationFromMinutes((int) $row['duration_minutes'])
                : null;
        }
        unset($row);

        echo json_encode(["status" => "success", "data" => $rows]);
        exit();
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Database error.", "detail" => $e->getMessage()]);
        exit();
    }
}

if ($method === 'PUT') {
    $data = getRequestData();
    $studentId = isset($data['student_id']) ? (int) $data['student_id'] : 0;
    $logDate = isset($data['log_date']) ? normalizeLogDate($data['log_date']) : date('Y-m-d');
    $hasTimeIn = array_key_exists('time_in', $data);
    $hasTimeOut = array_key_exists('time_out', $data);

    if ($studentId <= 0) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "student_id is required."]);
        exit();
    }

    if (!$logDate) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "log_date must be in YYYY-MM-DD format."]);
        exit();
    }

    if (!$hasTimeIn && !$hasTimeOut) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Provide time_in or time_out (or both)."]);
        exit();
    }

    try {
        $studentStmt = $conn->prepare("SELECT id FROM users WHERE id = ? AND role = 'student' LIMIT 1");
        $studentStmt->execute([$studentId]);
        if (!$studentStmt->fetch()) {
            http_response_code(404);
            echo json_encode(["status" => "error", "message" => "Student not found."]);
            exit();
        }

        $existingStmt = $conn->prepare("SELECT * FROM student_time_logs WHERE student_id = ? AND log_date = ? LIMIT 1");
        $existingStmt->execute([$studentId, $logDate]);
        $existing = $existingStmt->fetch();

        $newTimeIn = $existing['time_in'] ?? null;
        $newTimeOut = $existing['time_out'] ?? null;

        if ($hasTimeIn) {
            $parsedIn = normalizeLogDateTime($data['time_in'], $logDate);
            if ($parsedIn === false) {
                http_response_code(400);
                echo json_encode(["status" => "error", "message" => "Invalid time_in format."]);
                exit();
            }
            $newTimeIn = $parsedIn;
        }

        if ($hasTimeOut) {
            $parsedOut = normalizeLogDateTime($data['time_out'], $logDate);
            if ($parsedOut === false) {
                http_response_code(400);
                echo json_encode(["status" => "error", "message" => "Invalid time_out format."]);
                exit();
            }
            $newTimeOut = $parsedOut;
        }

        if ($newTimeOut !== null && $newTimeIn === null) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "time_out cannot exist without time_in."]);
            exit();
        }

        if ($newTimeIn !== null && $newTimeOut !== null && strtotime($newTimeOut) < strtotime($newTimeIn)) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "time_out must be later than time_in."]);
            exit();
        }

        if ($existing) {
            $updateStmt = $conn->prepare("
                UPDATE student_time_logs
                SET time_in = ?, time_out = ?
                WHERE id = ?
            ");
            $updateStmt->execute([$newTimeIn, $newTimeOut, $existing['id']]);
            $savedId = (int) $existing['id'];
        } else {
            $insertStmt = $conn->prepare("
                INSERT INTO student_time_logs (student_id, log_date, time_in, time_out)
                VALUES (?, ?, ?, ?)
            ");
            $insertStmt->execute([$studentId, $logDate, $newTimeIn, $newTimeOut]);
            $savedId = (int) $conn->lastInsertId();
        }

        $rowStmt = $conn->prepare("
            SELECT *,
                   CASE
                       WHEN time_in IS NOT NULL AND time_out IS NOT NULL
                       THEN GREATEST(TIMESTAMPDIFF(MINUTE, time_in, time_out), 0)
                       ELSE NULL
                   END AS duration_minutes
            FROM student_time_logs
            WHERE id = ?
            LIMIT 1
        ");
        $rowStmt->execute([$savedId]);
        $savedRow = $rowStmt->fetch();

        if (!$savedRow) {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Failed to load updated time log."]);
            exit();
        }

        $savedRow['duration_text'] = $savedRow['duration_minutes'] !== null
            ? formatDurationFromMinutes((int) $savedRow['duration_minutes'])
            : null;

        echo json_encode([
            "status" => "success",
            "message" => "Time log updated.",
            "data" => $savedRow
        ]);
        exit();
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Database error.", "detail" => $e->getMessage()]);
        exit();
    }
}

$data = getRequestData();
$studentId = isset($data['student_id']) ? (int) $data['student_id'] : 0;
$action = strtolower(trim((string) ($data['action'] ?? '')));

if ($studentId <= 0 || ($action !== 'in' && $action !== 'out')) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "student_id and action(in|out) are required."]);
    exit();
}

$now = new DateTime('now', new DateTimeZone('Asia/Manila'));
$today = $now->format('Y-m-d');
$nowString = $now->format('Y-m-d H:i:s');
$currentMinutes = ((int) $now->format('H') * 60) + (int) $now->format('i');

$timeInStart = 8 * 60;
$timeInEnd = (10 * 60) + 30;
$timeOutStart = 8 * 60;
$timeOutEnd = (16 * 60) + 58;

try {
    $studentStmt = $conn->prepare("SELECT id, department_id FROM users WHERE id = ? AND role = 'student' LIMIT 1");
    $studentStmt->execute([$studentId]);
    $student = $studentStmt->fetch();
    if (!$student) {
        http_response_code(404);
        echo json_encode(["status" => "error", "message" => "Student not found."]);
        exit();
    }

    if (empty($student['department_id'])) {
        http_response_code(403);
        echo json_encode(["status" => "error", "message" => "Student must be assigned to a department first."]);
        exit();
    }

    $logStmt = $conn->prepare("SELECT * FROM student_time_logs WHERE student_id = ? AND log_date = ? LIMIT 1");
    $logStmt->execute([$studentId, $today]);
    $todayLog = $logStmt->fetch();

    if ($action === 'in') {
        if ($currentMinutes < $timeInStart || $currentMinutes > $timeInEnd) {
            http_response_code(403);
            echo json_encode(["status" => "error", "message" => "Time In is only allowed from 8:00 AM to 10:30 AM (PH time)."]);
            exit();
        }

        if ($todayLog && !empty($todayLog['time_in'])) {
            http_response_code(409);
            echo json_encode(["status" => "error", "message" => "Time In already logged for today."]);
            exit();
        }

        if ($todayLog) {
            $update = $conn->prepare("UPDATE student_time_logs SET time_in = ? WHERE id = ?");
            $update->execute([$nowString, $todayLog['id']]);
        } else {
            $insert = $conn->prepare("INSERT INTO student_time_logs (student_id, log_date, time_in) VALUES (?, ?, ?)");
            $insert->execute([$studentId, $today, $nowString]);
        }

        $lateMinutes = max(0, $currentMinutes - $timeInStart);
        echo json_encode([
            "status" => "success",
            "message" => "Time In logged.",
            "data" => [
                "student_id" => $studentId,
                "log_date" => $today,
                "time_in" => $nowString,
                "late_minutes" => $lateMinutes,
                "late_text" => formatDurationFromMinutes($lateMinutes)
            ]
        ]);
        exit();
    }

    if ($currentMinutes < $timeOutStart || $currentMinutes > $timeOutEnd) {
        http_response_code(403);
        echo json_encode(["status" => "error", "message" => "Time Out is only allowed from 8:00 AM to 4:58 PM (PH time)."]);
        exit();
    }

    if (!$todayLog || empty($todayLog['time_in'])) {
        http_response_code(409);
        echo json_encode(["status" => "error", "message" => "Cannot Time Out without Time In today."]);
        exit();
    }

    if (!empty($todayLog['time_out'])) {
        http_response_code(409);
        echo json_encode(["status" => "error", "message" => "Time Out already logged for today."]);
        exit();
    }

    $update = $conn->prepare("UPDATE student_time_logs SET time_out = ? WHERE id = ?");
    $update->execute([$nowString, $todayLog['id']]);

    $durationMinutes = max(0, (int) floor((strtotime($nowString) - strtotime($todayLog['time_in'])) / 60));
    echo json_encode([
        "status" => "success",
        "message" => "Time Out logged.",
        "data" => [
            "student_id" => $studentId,
            "log_date" => $today,
            "time_in" => $todayLog['time_in'],
            "time_out" => $nowString,
            "duration_minutes" => $durationMinutes,
            "duration_text" => formatDurationFromMinutes($durationMinutes)
        ]
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database error.", "detail" => $e->getMessage()]);
}
exit();
?>
