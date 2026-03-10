<?php
include "../includes/auth.php";
checkRole('boss', '../boss/login.php');
include "../config/database.php";

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

$student_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($student_id <= 0) {
    header("Location: dashboard.php");
    exit();
}

$stmt = $conn->prepare("
    SELECT u.*, d.department_name
    FROM users u
    LEFT JOIN departments d ON u.department_id = d.id
    WHERE u.id = ? AND u.role = 'student'
");
$stmt->execute([$student_id]);
$student = $stmt->fetch();

if (!$student) {
    header("Location: dashboard.php");
    exit();
}

$avatarFile = $student['profile_pic'] ?? '';
$resumeFile = $student['resume'] ?? '';
$avatarExists = !empty($avatarFile) && file_exists(__DIR__ . "/../assets/uploads/avatars/" . $avatarFile);
$resumeExists = !empty($resumeFile) && file_exists(__DIR__ . "/../assets/uploads/resumes/" . $resumeFile);

// Ensure student time log table exists
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
} catch (PDOException $e) {
    // Non-blocking for profile view
}

$latestStudentLog = null;
$latestStudentLogDuration = null;
$totalLoggedDuration = "0m";
$studentTimeLogHistory = [];
try {
    $logStmt = $conn->prepare("SELECT log_date, time_in, time_out FROM student_time_logs WHERE student_id = ? ORDER BY log_date DESC, id DESC LIMIT 1");
    $logStmt->execute([$student_id]);
    $latestStudentLog = $logStmt->fetch();

    if ($latestStudentLog && !empty($latestStudentLog['time_in']) && !empty($latestStudentLog['time_out'])) {
        $latestMinutes = max(0, (int) floor((strtotime($latestStudentLog['time_out']) - strtotime($latestStudentLog['time_in'])) / 60));
        $latestStudentLogDuration = formatDurationFromMinutes($latestMinutes);
    }

    $sumStmt = $conn->prepare("
        SELECT COALESCE(SUM(TIMESTAMPDIFF(MINUTE, time_in, time_out)), 0) AS total_minutes
        FROM student_time_logs
        WHERE student_id = ? AND time_in IS NOT NULL AND time_out IS NOT NULL
    ");
    $sumStmt->execute([$student_id]);
    $totalMinutes = (int) $sumStmt->fetchColumn();
    $totalLoggedDuration = formatDurationFromMinutes($totalMinutes);

    $historyStmt = $conn->prepare("
        SELECT log_date, time_in, time_out
        FROM student_time_logs
        WHERE student_id = ?
        ORDER BY log_date DESC, id DESC
        LIMIT 20
    ");
    $historyStmt->execute([$student_id]);
    $studentTimeLogHistory = $historyStmt->fetchAll();
} catch (PDOException $e) {
    $latestStudentLog = null;
    $latestStudentLogDuration = null;
    $totalLoggedDuration = "0m";
    $studentTimeLogHistory = [];
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Intern Profile</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        :root {
            --bg: #f3f4f6;
            --panel: #ffffff;
            --text: #111827;
            --muted: #6b7280;
            --line: #e5e7eb;
            --primary: #0f766e;
            --primary-strong: #115e59;
            --nav-bg: #0b1320;
            --nav-link: #e2e8f0;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            color: var(--text);
            background: radial-gradient(circle at top right, #e6fffa, transparent 50%), var(--bg);
            min-height: 100vh;
            position: relative;
            overflow-x: hidden;
        }

        body::before {
            content: "";
            position: fixed;
            inset: 0;
            background:
                linear-gradient(rgba(243, 244, 246, 0.70), rgba(243, 244, 246, 0.70)),
                url("../images/boss.png") no-repeat center center / cover;
            opacity: 1;
            filter: brightness(1.08);
            pointer-events: none;
            z-index: 0;
        }

        .sidebar {
            background: var(--nav-bg);
            padding: 22px 16px;
            display: flex;
            flex-direction: column;
            align-items: stretch;
            gap: 8px;
            border-right: 1px solid #1f2937;
            width: 220px;
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            z-index: 2;
        }

        .sidebar .brand {
            font-family: "Sora", "Segoe UI", sans-serif;
            font-weight: 700;
            color: #f8fafc;
            margin-bottom: 6px;
            padding: 8px 10px;
        }

        .sidebar a {
            color: var(--nav-link);
            text-decoration: none;
            font-weight: 700;
            font-size: 0.92rem;
            padding: 9px 10px;
            border-radius: 9px;
            border: 1px solid transparent;
        }

        .sidebar a:hover {
            color: #ffffff;
            background: #1f2937;
            border-color: #334155;
        }

        .page {
            width: calc(100vw - 240px);
            margin: 0 0 0 220px;
            min-height: 100vh;
            padding: 24px 20px 40px;
            position: relative;
            z-index: 1;
        }

        .head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 14px;
            margin-bottom: 16px;
            flex-wrap: wrap;
        }

        h2 {
            margin: 0;
            font-size: 28px;
        }

        .subtitle {
            color: var(--muted);
            margin-top: 6px;
        }

        .back-btn {
            text-decoration: none;
            background: #fff;
            color: #0f172a;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 9px 12px;
            font-weight: 600;
        }

        .back-btn:hover {
            background: #f8fafc;
        }

        .profile-grid {
            display: grid;
            grid-template-columns: 260px 1fr;
            gap: 14px;
        }

        .card {
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 16px;
            box-shadow: 0 10px 28px rgba(15, 23, 42, 0.07);
        }

        .avatar-wrap {
            width: 140px;
            height: 140px;
            border-radius: 50%;
            overflow: hidden;
            margin: 6px auto 12px;
            border: 3px solid #e2e8f0;
            background: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .avatar-wrap img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .avatar-placeholder {
            color: #64748b;
            font-size: 13px;
            text-align: center;
            padding: 0 10px;
        }

        .name {
            margin: 0 0 4px;
            text-align: center;
        }

        .email {
            margin: 0;
            text-align: center;
            color: var(--muted);
            font-size: 14px;
            word-break: break-word;
        }

        .row {
            display: grid;
            grid-template-columns: 180px 1fr;
            gap: 10px;
            padding: 11px 0;
            border-bottom: 1px solid #f1f5f9;
            align-items: center;
        }

        .row:last-child {
            border-bottom: none;
        }

        .label {
            color: var(--muted);
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            font-weight: 600;
        }

        .value {
            font-weight: 600;
            color: #0f172a;
            word-break: break-word;
        }

        .resume-link {
            color: var(--primary);
            font-weight: 700;
            text-decoration: none;
        }

        .resume-link:hover {
            color: var(--primary-strong);
            text-decoration: underline;
        }

        .log-grid {
            display: grid;
            grid-template-columns: 180px 1fr;
            gap: 10px;
        }

        .log-box {
            background: #f8fafc;
            border: 1px solid #dbe7f5;
            border-radius: 10px;
            padding: 12px;
            margin-top: 10px;
        }

        .log-box h4 {
            margin: 0 0 10px 0;
            font-size: 15px;
            color: #0f172a;
        }

        .log-history {
            margin-top: 12px;
        }

        .log-history table {
            width: 100%;
            border-collapse: collapse;
        }

        .log-history th,
        .log-history td {
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
            padding: 8px 6px;
            font-size: 13px;
        }

        .log-history th {
            color: #64748b;
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 0.35px;
        }

        @media (max-width: 760px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: static;
                flex-direction: row;
                justify-content: flex-start;
                gap: 10px;
                padding: 12px;
                border-right: none;
                border-bottom: 1px solid #1f2937;
            }

            .sidebar .brand {
                width: 100%;
                margin-bottom: 0;
                padding: 4px 0 8px;
            }

            .page {
                width: 100%;
                margin: 0;
                padding: 14px 10px 24px;
            }

            h2 {
                font-size: 24px;
            }

            .profile-grid {
                grid-template-columns: 1fr;
            }

            .row {
                grid-template-columns: 1fr;
                gap: 6px;
            }
        }
    </style>
</head>
<body>
<div class="sidebar">
    <div class="brand">DIES Boss Dashboard</div>
    <a href="dashboard.php">Dashboard</a>
    <a href="../logout.php">Logout</a>
</div>

<div class="page">
    <div class="head">
        <div>
            <h2>Intern Profile</h2>
            <div class="subtitle">Read-only profile details for assignment review.</div>
        </div>
        <a href="dashboard.php" class="back-btn">Back to Dashboard</a>
    </div>

    <div class="profile-grid">
        <div class="card">
            <div class="avatar-wrap">
                <?php if ($avatarExists): ?>
                    <img src="../assets/uploads/avatars/<?php echo htmlspecialchars($avatarFile); ?>" alt="Avatar">
                <?php else: ?>
                    <div class="avatar-placeholder">No Profile Picture</div>
                <?php endif; ?>
            </div>
            <h3 class="name"><?php echo htmlspecialchars($student['full_name']); ?></h3>
            <p class="email"><?php echo htmlspecialchars($student['email']); ?></p>
        </div>

        <div class="card">
            <div class="row">
                <div class="label">Department</div>
                <div class="value"><?php echo !empty($student['department_name']) ? htmlspecialchars($student['department_name']) : "Not Assigned"; ?></div>
            </div>
            <div class="row">
                <div class="label">Resume</div>
                <div class="value">
                    <?php if ($resumeExists): ?>
                        <a class="resume-link" target="_blank" href="../assets/uploads/resumes/<?php echo htmlspecialchars($resumeFile); ?>">View Resume</a>
                    <?php else: ?>
                        Not Uploaded
                    <?php endif; ?>
                </div>
            </div>
            <div class="row">
                <div class="label">Profile Completed</div>
                <div class="value"><?php echo !empty($student['profile_completed']) ? "Yes" : "No"; ?></div>
            </div>
            <div class="row">
                <div class="label">Student ID</div>
                <div class="value"><?php echo (int) $student['id']; ?></div>
            </div>

            <div class="log-box">
                <h4>Time Log Summary</h4>
                <?php if ($latestStudentLog): ?>
                    <div class="log-grid">
                        <div class="label">Latest Date</div>
                        <div class="value"><?php echo htmlspecialchars(date('M d, Y', strtotime($latestStudentLog['log_date']))); ?></div>
                        <div class="label">Time In</div>
                        <div class="value"><?php echo !empty($latestStudentLog['time_in']) ? htmlspecialchars(date('h:i A', strtotime($latestStudentLog['time_in']))) : '-'; ?></div>
                        <div class="label">Time Out</div>
                        <div class="value"><?php echo !empty($latestStudentLog['time_out']) ? htmlspecialchars(date('h:i A', strtotime($latestStudentLog['time_out']))) : '-'; ?></div>
                        <div class="label">Total (Day)</div>
                        <div class="value"><?php echo htmlspecialchars($latestStudentLogDuration ?? '-'); ?></div>
                        <div class="label">Total (All Days)</div>
                        <div class="value"><?php echo htmlspecialchars($totalLoggedDuration); ?></div>
                    </div>
                <?php else: ?>
                    <div class="value">No time log yet</div>
                <?php endif; ?>
            </div>

            <div class="log-box log-history">
                <h4>Date & Time Log History</h4>
                <?php if (!empty($studentTimeLogHistory)): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Time In</th>
                                <th>Time Out</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($studentTimeLogHistory as $tl): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars(date('M d, Y', strtotime($tl['log_date']))); ?></td>
                                    <td><?php echo !empty($tl['time_in']) ? htmlspecialchars(date('h:i A', strtotime($tl['time_in']))) : '-'; ?></td>
                                    <td><?php echo !empty($tl['time_out']) ? htmlspecialchars(date('h:i A', strtotime($tl['time_out']))) : '-'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="value">No time log history yet</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="../assets/js/logout-popup.js"></script>
</body>
</html>
