<?php
include "../includes/auth.php";
checkRole('student', '../student/login.php');

include "../config/database.php";
include "../includes/colors.php";
include "../includes/mailer.php";
date_default_timezone_set('Asia/Manila');

$sessionUserId = isset($_SESSION['user']['id']) ? (int) $_SESSION['user']['id'] : 0;
if ($sessionUserId <= 0) {
    header('Location: ../student/login.php');
    exit();
}

// Refresh user session data
$stmt = $conn->prepare("
    SELECT users.*, departments.department_name, departments.supervisor_name
    FROM users
    LEFT JOIN departments ON users.department_id = departments.id
    WHERE users.id = ?
");
$stmt->execute([$sessionUserId]);
$user = $stmt->fetch();

if (!$user || !is_array($user)) {
    $fallbackSessionUser = $_SESSION['user'] ?? [];
    $user = [
        'id' => $sessionUserId,
        'full_name' => $fallbackSessionUser['full_name'] ?? 'Student',
        'email' => $fallbackSessionUser['email'] ?? '',
        'department_id' => $fallbackSessionUser['department_id'] ?? null,
        'department_name' => $fallbackSessionUser['department_name'] ?? '',
        'supervisor_name' => '',
        'resume' => '',
    ];
}
$currentUserId = (int) ($user['id'] ?? $sessionUserId);

// Update session
$_SESSION['user'] = $user;

// Create attendance table for student daily time logs (8:00 AM to 5:00 PM window)
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
    // Non-blocking
}

// Track student dashboard access time for supervisor monitoring
try {
    $conn->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS last_student_dashboard_at DATETIME NULL");
    $trackStmt = $conn->prepare("UPDATE users SET last_student_dashboard_at = NOW() WHERE id = ? AND role = 'student'");
    $trackStmt->execute([$currentUserId]);
} catch (PDOException $e) {
    // Tracking should not block dashboard load.
}

$timeLogMessage = "";
$timeLogMessageClass = "warn";
$showTimeInPopup = false;
$timeInPopupText = "";
$timeInPopupClass = "ok";
$tz = new DateTimeZone('Asia/Manila');
$now = new DateTime('now', $tz);
$today = $now->format('Y-m-d');
$timeInStart = 8 * 60;          // 8:00 AM (official time in)
$timeInEnd = (10 * 60) + 30;    // 10:30 AM (latest time in)
$timeOutStart = 8 * 60;         // 8:00 AM
$timeOutEnd = (16 * 60) + 58;   // until 4:58 PM, closes by 4:59 PM
$currentMinutes = ((int) $now->format('H') * 60) + (int) $now->format('i');

// ================================
// CHECK IF SUPERVISOR SUBMITTED EVALUATION
// ================================
$evaluationStmt = $conn->prepare("
    SELECT id, score FROM evaluations
    WHERE intern_id = ?
    ORDER BY id DESC
    LIMIT 1
");
$evaluationStmt->execute([$currentUserId]);
$evaluation = $evaluationStmt->fetch();

$evaluationStatus = $evaluation ? "Submitted" : "Pending";
$evaluationScore = $evaluation ? (int) $evaluation['score'] : null;
$evaluationId = $evaluation ? (int) $evaluation['id'] : null;
$evaluationResult = null;
if ($evaluationScore !== null) {
    $evaluationResult = ($evaluationScore <= 50) ? "Failed" : "Passed";
}
$hasSubmittedGrade = ($evaluationStatus === "Submitted");
$isAssignedToDepartment = !empty($user['department_id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['time_log_action'])) {
    try {
        $logStmt = $conn->prepare("SELECT id, time_in, time_out, reminder_sent_at FROM student_time_logs WHERE student_id = ? AND log_date = ? LIMIT 1");
        $logStmt->execute([$currentUserId, $today]);
        $todayLog = $logStmt->fetch();
    } catch (PDOException $e) {
        $todayLog = null;
    }

    $isTimeInAction = (!$todayLog || empty($todayLog['time_in']));
    $isTimeOutAction = ($todayLog && !empty($todayLog['time_in']) && empty($todayLog['time_out']));
    $nowString = $now->format('Y-m-d H:i:s');

    if (!$isAssignedToDepartment) {
        $timeLogMessage = "Time logging is available only after department assignment.";
        $timeLogMessageClass = "danger";
    } elseif ($hasSubmittedGrade) {
        $timeLogMessage = "Time In - Time Out is already done because grade is submitted.";
        $timeLogMessageClass = "warn";
    } elseif ($isTimeInAction) {
        if ($currentMinutes < $timeInStart) {
            $timeLogMessage = "Time In opens at exactly 8:00 AM (PH time).";
            $timeLogMessageClass = "danger";
        } elseif ($currentMinutes > $timeInEnd) {
            $timeLogMessage = "Time In is only allowed until 10:30 AM (PH time).";
            $timeLogMessageClass = "danger";
        } else {
            try {
                if (!$todayLog) {
                    $insertStmt = $conn->prepare("INSERT INTO student_time_logs (student_id, log_date, time_in) VALUES (?, ?, ?)");
                    $insertStmt->execute([$currentUserId, $today, $nowString]);
                } else {
                    $updateInStmt = $conn->prepare("UPDATE student_time_logs SET time_in = ? WHERE id = ?");
                    $updateInStmt->execute([$nowString, $todayLog['id']]);
                }

                $lateMinutes = max(0, $currentMinutes - $timeInStart);
                if ($lateMinutes === 0) {
                    $timeLogMessage = "Time In logged at " . $now->format('h:i A') . ".";
                    $timeInPopupText = "Time In successful. You are on time.";
                    $timeInPopupClass = "ok";
                } else {
                    $lateHours = intdiv($lateMinutes, 60);
                    $lateRemain = $lateMinutes % 60;
                    $lateParts = [];
                    if ($lateHours > 0) {
                        $lateParts[] = $lateHours . " hr";
                    }
                    if ($lateRemain > 0) {
                        $lateParts[] = $lateRemain . " min";
                    }
                    $lateText = implode(' ', $lateParts);
                    $timeLogMessage = "Time In logged at " . $now->format('h:i A') . " (Late by " . $lateText . ").";
                    $timeInPopupText = "Late Time In: " . $lateText . " late.";
                    $timeInPopupClass = "danger";
                }

                $timeLogMessageClass = "ok";
                $showTimeInPopup = true;
            } catch (PDOException $e) {
                $timeLogMessage = "Unable to save your Time In right now.";
                $timeLogMessageClass = "danger";
            }
        }
    } elseif ($isTimeOutAction) {
        if ($currentMinutes < $timeOutStart || $currentMinutes > $timeOutEnd) {
            $timeLogMessage = "Time Out is only allowed between 8:00 AM and 4:58 PM (PH time).";
            $timeLogMessageClass = "danger";
        } else {
            try {
                $updateStmt = $conn->prepare("UPDATE student_time_logs SET time_out = ? WHERE id = ?");
                $updateStmt->execute([$nowString, $todayLog['id']]);
                $timeLogMessage = "Time Out logged at " . $now->format('h:i A') . ".";
                $timeLogMessageClass = "ok";
            } catch (PDOException $e) {
                $timeLogMessage = "Unable to save your Time Out right now.";
                $timeLogMessageClass = "danger";
            }
        }
    } else {
        $timeLogMessage = "You already completed today's time log.";
        $timeLogMessageClass = "warn";
    }
}

$latestTimeLog = null;
$todayTimeLog = null;
try {
    $latestStmt = $conn->prepare("SELECT log_date, time_in, time_out, reminder_sent_at FROM student_time_logs WHERE student_id = ? ORDER BY log_date DESC, id DESC LIMIT 1");
    $latestStmt->execute([$currentUserId]);
    $latestTimeLog = $latestStmt->fetch();

    $todayStmt = $conn->prepare("SELECT id, time_in, time_out, reminder_sent_at FROM student_time_logs WHERE student_id = ? AND log_date = ? LIMIT 1");
    $todayStmt->execute([$currentUserId, $today]);
    $todayTimeLog = $todayStmt->fetch();
} catch (PDOException $e) {
    $latestTimeLog = null;
    $todayTimeLog = null;
}

// Send one reminder email from 4:50 PM to 4:58 PM if not yet timed out.
$reminderStart = (16 * 60) + 50;
$reminderEnd = (16 * 60) + 58;
if (
    !$hasSubmittedGrade &&
    $todayTimeLog &&
    !empty($todayTimeLog['time_in']) &&
    empty($todayTimeLog['time_out']) &&
    empty($todayTimeLog['reminder_sent_at']) &&
    $currentMinutes >= $reminderStart &&
    $currentMinutes <= $reminderEnd
) {
    $reminderSent = sendTimeOutReminderEmail((string) ($user['email'] ?? ''), (string) ($user['full_name'] ?? 'Student'), '4:59 PM');
    if ($reminderSent) {
        try {
            $markReminderStmt = $conn->prepare("UPDATE student_time_logs SET reminder_sent_at = ? WHERE id = ?");
            $markReminderStmt->execute([$now->format('Y-m-d H:i:s'), $todayTimeLog['id']]);
            $todayTimeLog['reminder_sent_at'] = $now->format('Y-m-d H:i:s');
        } catch (PDOException $e) {
            // Non-blocking
        }
    }
}

$timeLogButtonLabel = 'Time In';
if ($todayTimeLog && !empty($todayTimeLog['time_in']) && empty($todayTimeLog['time_out'])) {
    $timeLogButtonLabel = 'Time Out';
}
if ($todayTimeLog && !empty($todayTimeLog['time_in']) && !empty($todayTimeLog['time_out'])) {
    $timeLogButtonLabel = 'Logged Today';
}
$isBeforeTimeIn = ($currentMinutes < $timeInStart);
$isAfterTimeIn = ($currentMinutes > $timeInEnd);
$isAfterTimeOut = ($currentMinutes > $timeOutEnd);

$canSubmitTimeLog = false;
if ($isAssignedToDepartment && !$hasSubmittedGrade && $timeLogButtonLabel !== 'Logged Today') {
    if ($timeLogButtonLabel === 'Time In') {
        $canSubmitTimeLog = ($currentMinutes >= $timeInStart && $currentMinutes <= $timeInEnd);
    } elseif ($timeLogButtonLabel === 'Time Out') {
        $canSubmitTimeLog = ($currentMinutes >= $timeOutStart && $currentMinutes <= $timeOutEnd);
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&family=Sora:wght@500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --ink: #0c1a24;
            --muted: #4f6472;
            --brand-a: #2563eb;
            --brand-b: #0ea5e9;
            --ok: #0f766e;
            --warn: #b45309;
            --danger: #b91c1c;
            --surface: rgba(255, 255, 255, 0.93);
            --line: rgba(20, 44, 69, 0.12);
            --shadow: 0 18px 44px rgba(4, 16, 26, 0.14);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            min-height: 100vh;
            font-family: "Manrope", "Segoe UI", sans-serif;
            color: var(--ink);
            background:
                linear-gradient(rgba(245, 250, 255, 0.86), rgba(245, 250, 255, 0.86)),
                url("../images/student.png") no-repeat center center / cover fixed;
            padding: 26px 0;
        }

        .container {
            width: min(1120px, 92%);
            margin: 0 auto;
            position: relative;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 18px;
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 12px 16px;
            box-shadow: var(--shadow);
        }

        .brand {
            font-family: "Sora", "Segoe UI", sans-serif;
            font-weight: 700;
            color: #0f3d7a;
            letter-spacing: 0.02em;
        }

        .menu-dropdown {
            position: relative;
        }

        .menu-toggle {
            border: 1px solid #cfe3ff;
            background: #eff6ff;
            color: #14426d;
            font-weight: 700;
            font-size: 0.9rem;
            border-radius: 999px;
            padding: 8px 12px;
            cursor: pointer;
        }

        .menu-list {
            display: none;
            position: absolute;
            right: 0;
            top: calc(100% + 8px);
            min-width: 170px;
            background: #ffffff;
            border: 1px solid #d9e7f7;
            border-radius: 10px;
            box-shadow: 0 10px 24px rgba(12, 26, 36, 0.14);
            overflow: hidden;
            z-index: 40;
        }

        .menu-dropdown.open .menu-list {
            display: block;
        }

        .menu-list a {
            display: block;
            text-decoration: none;
            color: #14426d;
            font-weight: 700;
            font-size: 0.9rem;
            padding: 10px 12px;
            border-bottom: 1px solid #eef4fc;
            background: #ffffff;
        }

        .menu-list a:last-child {
            border-bottom: none;
        }

        .menu-list a:hover {
            background: #eff6ff;
        }

        .hero {
            background: linear-gradient(138deg, #0f3d7a, #0a7cc2);
            color: #eef7ff;
            border-radius: 20px;
            padding: 22px;
            box-shadow: var(--shadow);
            margin-bottom: 16px;
        }

        .hero h1 {
            font-family: "Sora", "Segoe UI", sans-serif;
            font-size: clamp(1.35rem, 2.3vw, 2rem);
            margin-bottom: 6px;
        }

        .hero p {
            color: rgba(238, 247, 255, 0.9);
        }

        .cards {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
            margin-bottom: 14px;
        }

        .card {
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 14px;
            box-shadow: var(--shadow);
        }

        .card h3 {
            font-size: 0.88rem;
            color: var(--muted);
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        .value {
            font-weight: 800;
            font-size: 1.05rem;
            color: #102a44;
            word-break: break-word;
        }

        .grade-circle {
            --grade: 0;
            width: 96px;
            height: 96px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            background:
                radial-gradient(closest-side, #ffffff 72%, transparent 73% 100%),
                conic-gradient(#0f766e calc(var(--grade) * 1%), #dbe7f3 0);
            border: 1px solid rgba(20, 44, 69, 0.14);
        }

        .grade-circle.fail {
            background:
                radial-gradient(closest-side, #ffffff 72%, transparent 73% 100%),
                conic-gradient(#b91c1c calc(var(--grade) * 1%), #f1dde0 0);
        }

        .grade-circle-value {
            font-weight: 800;
            font-size: 1rem;
            color: #102a44;
        }

        .grade-summary {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 10px;
        }

        .pill {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 999px;
            font-weight: 700;
            font-size: 0.85rem;
        }

        .ok {
            color: var(--ok);
            background: rgba(15, 118, 110, 0.14);
            border: 1px solid rgba(15, 118, 110, 0.25);
        }

        .warn {
            color: var(--warn);
            background: rgba(180, 83, 9, 0.14);
            border: 1px solid rgba(180, 83, 9, 0.25);
        }

        .danger {
            color: var(--danger);
            background: rgba(185, 28, 28, 0.12);
            border: 1px solid rgba(185, 28, 28, 0.24);
        }

        .btn {
            display: inline-block;
            margin-top: 10px;
            text-decoration: none;
            font-weight: 700;
            color: #fff;
            background: linear-gradient(140deg, var(--brand-a), var(--brand-b));
            padding: 8px 12px;
            border-radius: 9px;
            transition: transform 0.18s ease, filter 0.18s ease;
        }

        .btn:hover:not([disabled]) {
            transform: translateY(-2px);
            filter: brightness(0.95);
        }

        .btn[disabled] {
            opacity: 0.65;
            cursor: not-allowed;
            pointer-events: none;
        }

        .meta {
            margin-top: 8px;
            font-size: 0.82rem;
            color: #365066;
            line-height: 1.45;
        }

        .time-log-card {
            text-align: center;
            grid-column: 2 / span 2;
        }

        .time-log-card .meta {
            text-align: center;
        }

        .time-log-card form {
            display: flex;
            justify-content: center;
        }

        .time-popup {
            position: fixed;
            right: 16px;
            top: 16px;
            z-index: 1000;
            min-width: 260px;
            max-width: 360px;
            border-radius: 12px;
            padding: 12px 14px;
            box-shadow: 0 14px 34px rgba(8, 20, 31, 0.2);
            font-size: 0.9rem;
            font-weight: 700;
        }

        .time-popup.ok {
            background: #e8f8ef;
            color: #0f766e;
            border: 1px solid rgba(15, 118, 110, 0.24);
        }

        .time-popup.danger {
            background: #feecef;
            color: #b91c1c;
            border: 1px solid rgba(185, 28, 28, 0.24);
        }

        .assignment {
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 16px;
            box-shadow: var(--shadow);
        }

        .assignment h2 {
            font-family: "Sora", "Segoe UI", sans-serif;
            font-size: 1.15rem;
            margin-bottom: 10px;
        }

        .assignment p {
            margin-bottom: 8px;
            color: #1e3c50;
        }

        @media (max-width: 980px) {
            .cards {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .time-log-card {
                grid-column: 1 / -1;
            }
        }

        @media (max-width: 620px) {
            .topbar {
                flex-direction: column;
                align-items: flex-start;
            }

            .cards {
                grid-template-columns: 1fr;
            }

            .grade-summary {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="topbar">
        <div class="brand">DIES Student Dashboard</div>
        <div class="menu-dropdown" id="menuDropdown">
            <button type="button" class="menu-toggle" id="menuToggle">Menu</button>
            <div class="menu-list" id="menuList">
                <a href="dashboard.php">Dashboard</a>
                <a href="profile.php">Profile</a>
                <a href="logout.php">Logout</a>
            </div>
        </div>
    </div>

    <section class="hero">
        <h1>Welcome, <?php echo htmlspecialchars($user['full_name']); ?></h1>
        <p>Track your assignment, documents, and latest evaluation status in one place.</p>
    </section>

    <section class="cards">
        <article class="card">
            <h3>Email</h3>
            <div class="value"><?php echo htmlspecialchars($user['email']); ?></div>
        </article>

        <article class="card">
            <h3>Resume</h3>
            <?php if ($user['resume']) { ?>
                <div class="value">Uploaded</div>
                <a class="btn" href="../assets/uploads/resumes/<?php echo htmlspecialchars($user['resume']); ?>" target="_blank">View Resume</a>
            <?php } else { ?>
                <span class="pill warn">No Resume Uploaded</span>
            <?php } ?>
        </article>

        <article class="card">
            <h3>Department Status</h3>
            <?php if ($user['department_id']) { ?>
                <span class="pill ok">Assigned</span>
            <?php } else { ?>
                <span class="pill warn">Pending Assignment</span>
            <?php } ?>
        </article>

        <article class="card">
            <h3>Your Grade</h3>
            <?php if ($evaluationStatus == "Submitted") { ?>
                <?php $gradePercent = max(0, min(100, (int) $evaluationScore)); ?>
                <div class="grade-summary">
                    <div class="grade-circle <?php echo ($evaluationScore <= 50) ? 'fail' : ''; ?>" style="--grade: <?php echo $gradePercent; ?>;">
                        <span class="grade-circle-value"><?php echo htmlspecialchars((string) $evaluationScore); ?>%</span>
                    </div>
                    <span class="pill <?php echo ($evaluationScore <= 50) ? 'danger' : 'ok'; ?>">
                        <?php echo htmlspecialchars($evaluationResult); ?>
                    </span>
                </div>
                <a class="btn" href="view_evaluation.php?id=<?php echo (int) $evaluationId; ?>">View Evaluation</a>
            <?php } else { ?>
                <span class="pill danger">Pending Evaluation</span>
            <?php } ?>
        </article>

        <article class="card time-log-card">
            <h3>Time Log (8AM-4:59PM)</h3>
            <div class="meta">
                PH Time: <?php echo htmlspecialchars($now->format('h:i A')); ?><br>
                ----------------------------------------------<br>
                Time In: 8:00 AM - 10:30 AM (official start: 8:00 AM)<br>
                ----------------------------------------------<br>
                Time Out: 8:00 AM - 4:58 PM<br>
                ----------------------------------------------<br>
                Reminder: 4:50 PM | Cut-off: 5:00 PM
            </div>
            <?php if ($hasSubmittedGrade) { ?>
                <span class="pill ok">Time In - Time Out Done</span>
            <?php } elseif (!$isAssignedToDepartment) { ?>
                <span class="pill danger">You must be assigned to a department before logging time.</span>
            <?php } elseif ($timeLogButtonLabel === 'Time In' && $isBeforeTimeIn) { ?>
                <span class="pill warn">Time In opens at exactly 8:00 AM.</span>
            <?php } elseif ($timeLogButtonLabel === 'Time In' && $isAfterTimeIn) { ?>
                <span class="pill danger">Time In closed. Latest Time In is 10:30 AM.</span>
            <?php } elseif ($timeLogButtonLabel === 'Time Out' && $isAfterTimeOut) { ?>
                <span class="pill danger">Time Out closed. Cut-off is 4:59 PM.</span>
            <?php } ?>
            <?php if (!empty($timeLogMessage)) { ?>
                <span class="pill <?php echo htmlspecialchars($timeLogMessageClass); ?>"><?php echo htmlspecialchars($timeLogMessage); ?></span>
            <?php } ?>
            <?php if (!$hasSubmittedGrade) { ?>
                <form method="POST">
                    <input type="hidden" name="time_log_action" value="1">
                    <button class="btn" type="submit" <?php echo $canSubmitTimeLog ? '' : 'disabled'; ?>>
                        <?php echo htmlspecialchars($timeLogButtonLabel); ?>
                    </button>
                </form>
            <?php } ?>
            <?php if ($latestTimeLog) { ?>
                <div class="meta">
                    Date: <?php echo htmlspecialchars(date('M d, Y', strtotime($latestTimeLog['log_date']))); ?><br>
                    Time In: <?php echo !empty($latestTimeLog['time_in']) ? htmlspecialchars(date('h:i A', strtotime($latestTimeLog['time_in']))) : '-'; ?><br>
                    Time Out: <?php echo !empty($latestTimeLog['time_out']) ? htmlspecialchars(date('h:i A', strtotime($latestTimeLog['time_out']))) : '-'; ?>
                </div>
            <?php } else { ?>
                <span class="pill warn">No time log yet</span>
            <?php } ?>
        </article>

    </section>

    <section class="assignment">
        <h2>Internship Assignment Details</h2>
        <?php if ($user['department_id']) { ?>
            <p><strong>Department:</strong> <?php echo htmlspecialchars($user['department_name']); ?></p>
            <?php $supColor = getDeptColor($user['department_name'] ?? ''); ?>
            <p>
                <strong>Supervisor:</strong>
                <span style="color:<?php echo htmlspecialchars($supColor); ?>;font-weight:700;">
                    <?php echo htmlspecialchars($user['supervisor_name']); ?>
                </span>
            </p>
        <?php } else { ?>
            <p class="pill warn">You are not yet assigned to a department. Please wait for assignment.</p>
        <?php } ?>
    </section>
</div>
<?php if ($showTimeInPopup): ?>
    <div id="timeInPopup" class="time-popup <?php echo htmlspecialchars($timeInPopupClass); ?>">
        <?php echo htmlspecialchars($timeInPopupText); ?>
    </div>
    <script>
        setTimeout(function () {
            var popup = document.getElementById('timeInPopup');
            if (popup) {
                popup.style.display = 'none';
            }
        }, 10000);
    </script>
<?php endif; ?>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="../assets/js/logout-popup.js"></script>
<script>
    (function () {
        var dropdown = document.getElementById('menuDropdown');
        var toggle = document.getElementById('menuToggle');
        if (!dropdown || !toggle) return;

        toggle.addEventListener('click', function () {
            dropdown.classList.toggle('open');
        });

        document.addEventListener('click', function (e) {
            if (!dropdown.contains(e.target)) {
                dropdown.classList.remove('open');
            }
        });
    })();
</script>

</body>
</html>
