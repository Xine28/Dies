<?php
include "../includes/auth.php";
checkRole('supervisor', 'login.php');

include "../config/database.php";
require_once "../includes/mailer.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php?error=method');
    exit;
}

$supervisor_id = $_SESSION['user']['id'] ?? null;
$department_id = $_SESSION['user']['department_id'] ?? null;
$intern_id = isset($_POST['intern_id']) ? (int) $_POST['intern_id'] : 0;
$eval_date = !empty($_POST['eval_date']) ? $_POST['eval_date'] : date('Y-m-d');
$comm = isset($_POST['comm']) ? (int) $_POST['comm'] : 0;
$problem = isset($_POST['problem']) ? (int) $_POST['problem'] : 0;
$teamwork = isset($_POST['teamwork']) ? (int) $_POST['teamwork'] : 0;
$comments = trim($_POST['comments'] ?? '');

// Basic validation
if (!isset($_SESSION['user']['id'])) {
    header('Location: dashboard.php?error=missing_supervisor');
    exit;
}

if ($intern_id <= 0) {
    header('Location: dashboard.php?error=missing_intern');
    exit;
}

// Ensure the selected intern exists and belongs to the supervisor's department
try {
    $checkStmt = $conn->prepare("SELECT id, department_id, role, full_name, email FROM users WHERE id = ? LIMIT 1");
    $checkStmt->execute([$intern_id]);
    $internRow = $checkStmt->fetch();
    if (!$internRow || $internRow['role'] !== 'student') {
        header('Location: dashboard.php?error=invalid_intern');
        exit;
    }
    if ($department_id !== null && $internRow['department_id'] != $department_id) {
        header('Location: dashboard.php?error=invalid_intern');
        exit;
    }
} catch (PDOException $e) {
    error_log('Intern check error: ' . $e->getMessage());
    header('Location: dashboard.php?error=db');
    exit;
}

foreach (['comm'=>$comm,'problem'=>$problem,'teamwork'=>$teamwork] as $k => $v) {
    if ($v < 1 || $v > 5) {
        header('Location: dashboard.php?error=invalid_score');
        exit;
    }
}

// Calculate an overall percentage score (average of the 3 criteria * 20)
$avg = ($comm + $problem + $teamwork) / 3.0;
$score = (int) round($avg * 20);

try {
    // Create table if it doesn't exist yet (safe migration)
    $createSql = "CREATE TABLE IF NOT EXISTS evaluations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        supervisor_id INT NOT NULL,
        intern_id INT NOT NULL,
        comm TINYINT,
        problem TINYINT,
        teamwork TINYINT,
        score SMALLINT,
        comments TEXT,
        eval_date DATE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $conn->exec($createSql);

    // Preserve intern details in evaluations so names remain visible
    // even if user records are later removed.
    try {
        $conn->exec("ALTER TABLE evaluations ADD COLUMN intern_name VARCHAR(100) NULL");
    } catch (PDOException $e) {
        // ignore if column already exists
    }
    try {
        $conn->exec("ALTER TABLE evaluations ADD COLUMN intern_email VARCHAR(100) NULL");
    } catch (PDOException $e) {
        // ignore if column already exists
    }

    // Upsert behavior: one evaluation record per supervisor+intern pair.
    // If duplicates already exist from previous inserts, keep the latest row
    // and delete older duplicates so history shows only one record.
    $existingStmt = $conn->prepare(
        "SELECT id
         FROM evaluations
         WHERE supervisor_id = ? AND intern_id = ?
         ORDER BY id DESC"
    );
    $existingStmt->execute([$supervisor_id, $intern_id]);
    $existingIds = $existingStmt->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($existingIds)) {
        $keepId = (int) $existingIds[0];

        $updateStmt = $conn->prepare(
            "UPDATE evaluations
             SET comm = ?, problem = ?, teamwork = ?, score = ?, comments = ?, eval_date = ?
             WHERE id = ?"
        );
        $updateStmt->execute([
            $comm,
            $problem,
            $teamwork,
            $score,
            $comments,
            $eval_date,
            $keepId
        ]);

        $snapshotUpdateStmt = $conn->prepare(
            "UPDATE evaluations
             SET intern_name = ?, intern_email = ?
             WHERE id = ?"
        );
        $snapshotUpdateStmt->execute([
            $internRow['full_name'] ?? null,
            $internRow['email'] ?? null,
            $keepId
        ]);

        // Cleanup older duplicates for this same supervisor/intern pair.
        if (count($existingIds) > 1) {
            $deleteDupStmt = $conn->prepare(
                "DELETE FROM evaluations
                 WHERE supervisor_id = ? AND intern_id = ? AND id <> ?"
            );
            $deleteDupStmt->execute([$supervisor_id, $intern_id, $keepId]);
        }
    } else {
        $stmt = $conn->prepare("INSERT INTO evaluations (supervisor_id, intern_id, comm, problem, teamwork, score, comments, eval_date, intern_name, intern_email) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $supervisor_id,
            $intern_id,
            $comm,
            $problem,
            $teamwork,
            $score,
            $comments,
            $eval_date,
            $internRow['full_name'] ?? null,
            $internRow['email'] ?? null
        ]);
    }

    $studentEmail = $internRow['email'] ?? '';
    $studentName = $internRow['full_name'] ?? 'Student';
    $supervisorName = $_SESSION['user']['full_name'] ?? 'Supervisor';

    // Email the student
    if (!empty($studentEmail)) {
        $mailSent = sendEvaluationSubmittedEmail(
            $studentEmail,
            $studentName,
            $supervisorName,
            $score,
            $eval_date
        );
        if (!$mailSent) {
            error_log('Evaluation email send failed for student_id=' . (int) $intern_id);
        }
    }

    // Email the boss after successful evaluation submit/update
    $bossEmail = 'xinefajardo1@gmail.com';
    $bossName = 'Xine Fajardo';
    $departmentName = $_SESSION['user']['department_name'] ?? '';
    $bossMailSent = sendBossEvaluationNotification(
        $bossEmail,
        $bossName,
        $studentName,
        $supervisorName,
        $score,
        $eval_date,
        $departmentName,
        $comments
    );
    if (!$bossMailSent) {
        error_log('Boss evaluation notification failed for intern_id=' . (int) $intern_id);
    }

    header('Location: dashboard.php?success=1');
    exit;

} catch (PDOException $e) {
    error_log('Evaluation submit error: ' . $e->getMessage());
    header('Location: dashboard.php?error=db');
    exit;
}

?>
