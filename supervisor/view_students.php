<?php
include "../includes/auth.php";
checkRole('supervisor', '../supervisor/login.php');

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

// Ensure dashboard activity column exists for student login/view tracking
try {
    $conn->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS last_student_dashboard_at DATETIME NULL");
} catch (PDOException $e) {
    // Non-blocking: page can still load even if schema update fails.
}

// Ensure student time log table exists
try {
    $conn->exec("
        CREATE TABLE IF NOT EXISTS student_time_logs (
            id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            student_id INT(11) NOT NULL,
            log_date DATE NOT NULL,
            time_in DATETIME DEFAULT NULL,
            time_out DATETIME DEFAULT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_student_date (student_id, log_date),
            KEY idx_student_id (student_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");
} catch (PDOException $e) {
    // Non-blocking: page can still load.
}

/* =========================================
   GET SUPERVISOR DATA FROM SESSION
========================================= */
$supervisor_name   = $_SESSION['user']['full_name'];
$department_id     = $_SESSION['user']['department_id'];
$department_name   = $_SESSION['user']['department_name'];

/* =========================================
   SEARCH INTERNS
========================================= */
$search = "";

if (isset($_GET['search']) && !empty($_GET['search'])) {

    $search = $_GET['search'];

    $stmt = $conn->prepare("
        SELECT * FROM users
        WHERE role = 'student'
        AND department_id = ?
        AND (full_name LIKE ? OR email LIKE ?)
    ");

    $stmt->execute([
        $department_id,
        "%$search%",
        "%$search%"
    ]);

} else {

    $stmt = $conn->prepare("
        SELECT * FROM users
        WHERE role = 'student'
        AND department_id = ?
    ");

    $stmt->execute([$department_id]);
}

$students = $stmt->fetchAll();

// Determine selected student (from GET id or first in list)
$selected_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$selected = null;
if ($selected_id > 0) {
    foreach ($students as $s) {
        if ($s['id'] == $selected_id) { $selected = $s; break; }
    }
}
if (!$selected && !empty($students)) {
    $selected = $students[0];
    $selected_id = $selected['id'];
}

// Load evaluations summary for selected student
$evalCount = 0;
$avgScore = null;
$latestStudentLog = null;
$latestStudentLogDuration = null;
$totalLoggedDuration = null;
$studentTimeLogHistory = [];
try {
    if ($selected) {
        $eStmt = $conn->prepare("SELECT COUNT(*) as cnt, AVG(score) as avg_score FROM evaluations WHERE intern_id = ?");
        $eStmt->execute([$selected['id']]);
        $row = $eStmt->fetch();
        $evalCount = $row['cnt'] ?? 0;
        $avgScore = $row['avg_score'] !== null ? round($row['avg_score'], 0) : null;

        $logStmt = $conn->prepare("SELECT log_date, time_in, time_out FROM student_time_logs WHERE student_id = ? ORDER BY log_date DESC, id DESC LIMIT 1");
        $logStmt->execute([$selected['id']]);
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
        $sumStmt->execute([$selected['id']]);
        $totalMinutes = (int) $sumStmt->fetchColumn();
        $totalLoggedDuration = formatDurationFromMinutes($totalMinutes);

        $historyStmt = $conn->prepare("
            SELECT log_date, time_in, time_out
            FROM student_time_logs
            WHERE student_id = ?
            ORDER BY log_date DESC, id DESC
            LIMIT 15
        ");
        $historyStmt->execute([$selected['id']]);
        $studentTimeLogHistory = $historyStmt->fetchAll();
    }
} catch (PDOException $e) {
    $evalCount = 0;
    $avgScore = null;
    $latestStudentLog = null;
    $latestStudentLogDuration = null;
    $totalLoggedDuration = null;
    $studentTimeLogHistory = [];
}

// Count how many interns in this department have at least one evaluation
$evaluatedCount = 0;
try {
    $cstmt = $conn->prepare("SELECT COUNT(DISTINCT e.intern_id) FROM evaluations e JOIN users u ON e.intern_id = u.id WHERE u.department_id = ?");
    $cstmt->execute([$department_id]);
    $evaluatedCount = (int) $cstmt->fetchColumn();
} catch (PDOException $e) {
    $evaluatedCount = 0;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Assigned Interns</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .page { max-width:1200px; margin:36px auto; padding:0 16px; }
        .top-row { display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:18px; }
        .page-title { font-size:22px; font-weight:700; }
        .grid { display:grid; grid-template-columns: 280px 1fr 320px; gap:20px; align-items:start; }
        .student-list { background:#fff; padding:14px; border-radius:12px; box-shadow:0 6px 18px rgba(15,23,42,0.06); max-height:72vh; overflow:auto; }
        .student-item { padding:12px; border-radius:8px; display:flex; gap:10px; align-items:center; cursor:pointer; }
        .student-item:hover { background:#f1f5f9; }
        .student-item.active { background:#eef2ff; border-left:4px solid #2563eb; box-shadow: inset 0 0 0 1px rgba(37,99,235,0.04); }
        .avatar { width:56px; height:56px; border-radius:8px; overflow:hidden; background:#f8fafc; display:flex; align-items:center; justify-content:center; border:1px solid #e6eefc; }
        .avatar img { width:100%; height:100%; object-fit:cover; }
        .student-meta { font-size:14px; }

        .detail-card { background:#fff; padding:22px; border-radius:12px; box-shadow:0 6px 18px rgba(15,23,42,0.06); }
        .detail-header { display:flex; gap:18px; align-items:center; }
        .detail-name { font-size:20px; font-weight:800; }
        .detail-sub { color:#64748b; }

        .metrics { display:flex; gap:12px; margin-top:14px; }
        .metric { flex:1; background:#fff; padding:12px; border-radius:8px; border:1px solid #eef2ff; text-align:center; }
        .metric .num { font-size:18px; font-weight:800; color:#0f172a; }
        .metric .label { font-size:12px; color:#64748b; }

        .right-cards { display:flex; flex-direction:column; gap:12px; }

        .btn { display:inline-block; padding:10px 12px; border-radius:8px; background:#2563eb; color:white; text-align:center; transition: transform 0.18s ease, filter 0.18s ease; }
        .btn.secondary { background:#10b981; }
        .btn:hover { filter: brightness(0.92); transform: translateY(-2px); }

        .time-log-box {
            margin-top: 8px;
            border: 1px solid #dbe7f5;
            border-radius: 10px;
            background: #f8fbff;
            padding: 10px;
        }

        .time-log-title {
            font-weight: 800;
            font-size: 13px;
            color: #1e3a5f;
            margin-bottom: 8px;
        }

        .time-log-grid {
            display: grid;
            grid-template-columns: 110px 1fr;
            gap: 6px 10px;
            font-size: 13px;
            color: #334155;
        }

        .time-log-grid .label {
            font-weight: 700;
            color: #475569;
        }

        .time-log-empty {
            font-size: 13px;
            color: #64748b;
        }

        .time-history-box {
            margin-top: 14px;
            border: 1px solid #dbe7f5;
            border-radius: 10px;
            background: #ffffff;
            padding: 10px;
        }

        .time-history-box h4 {
            margin: 0 0 8px 0;
            font-size: 14px;
            color: #1e3a5f;
        }

        .time-history-box table {
            margin-top: 0;
        }

        .time-history-box th,
        .time-history-box td {
            font-size: 12.5px;
            padding: 8px;
        }

        @media (max-width:1000px) { .grid { grid-template-columns: 1fr; } .right-cards { order:3; } }
    </style>
</head>
<body>

<div class="page">
    <div class="top-row">
        <div class="page-title">Assigned Interns — <?php echo htmlspecialchars($department_name); ?></div>
        <?php include "../includes/colors.php"; $supColor = getDeptColor($department_name ?? ''); ?>
        <div>Supervisor: <strong><span style="color:<?php echo htmlspecialchars($supColor); ?>;font-weight:700;"><?php echo htmlspecialchars($supervisor_name); ?></span></strong></div>
    </div>

    <div class="grid">

        <!-- LEFT: intern list -->
        <div class="student-list">
            <form method="GET" style="margin-bottom:12px;">
                <input type="text" name="search" placeholder="Search intern..." value="<?php echo htmlspecialchars($search); ?>" style="width:100%;padding:10px;border-radius:8px;border:1px solid #e6eefc;">
            </form>

            <?php if (!empty($students)): ?>
                <?php foreach ($students as $s): ?>
                    <?php $isActive = ($selected && $selected['id'] == $s['id']); ?>
                    <a href="view_students.php?id=<?php echo $s['id']; ?>" style="text-decoration:none;color:inherit;">
                    <div class="student-item <?php echo $isActive ? 'active' : ''; ?>">
                        <div class="avatar">
                            <?php if (!empty($s['profile_pic']) && file_exists(__DIR__ . '/../assets/uploads/avatars/' . $s['profile_pic'])): ?>
                                <img src="../assets/uploads/avatars/<?php echo htmlspecialchars($s['profile_pic']); ?>" alt="avatar">
                            <?php else: ?>
                                <img src="../images/avatar-placeholder.svg" alt="avatar">
                            <?php endif; ?>
                        </div>
                        <div class="student-meta">
                            <div style="font-weight:700;"><?php echo htmlspecialchars($s['full_name']); ?></div>
                            <div style="font-size:13px;color:#64748b;"><?php echo htmlspecialchars($s['email']); ?></div>
                        </div>
                    </div>
                    </a>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="color:#64748b;padding:12px;">No interns assigned.</div>
            <?php endif; ?>
        </div>

        <!-- CENTER: Selected student details -->
        <div>
            <div class="detail-card">
                <?php if ($selected): ?>
                    <div class="detail-header">
                        <div style="width:92px;height:92px;border-radius:12px;overflow:hidden;border:1px solid #eef2ff;">
                            <?php if (!empty($selected['profile_pic']) && file_exists(__DIR__ . '/../assets/uploads/avatars/' . $selected['profile_pic'])): ?>
                                <img src="../assets/uploads/avatars/<?php echo htmlspecialchars($selected['profile_pic']); ?>" style="width:92px;height:92px;object-fit:cover;" alt="avatar">
                            <?php else: ?>
                                <img src="../images/avatar-placeholder.svg" style="width:92px;height:92px;object-fit:cover;" alt="avatar">
                            <?php endif; ?>
                        </div>

	                        <div>
	                            <div class="detail-name"><?php echo htmlspecialchars($selected['full_name']); ?></div>
	                            <div class="detail-sub"><?php echo htmlspecialchars($selected['email']); ?> • <?php echo htmlspecialchars($selected['phone'] ?? ''); ?></div>
	                            <div style="margin-top:6px;font-size:13px;color:#475569;">
	                                <?php
	                                    $lastDashboardVisit = $selected['last_student_dashboard_at'] ?? null;
	                                    if (!empty($lastDashboardVisit) && strtotime($lastDashboardVisit) !== false) {
	                                        echo 'Student Last Dashboard Login: <strong>' . htmlspecialchars(date('M d, Y h:i A', strtotime($lastDashboardVisit))) . '</strong>';
	                                    } else {
	                                        echo 'Student Last Dashboard Login: <span style="color:#94a3b8;">No activity yet</span>';
	                                    }
	                                ?>
	                            </div>
                                <div class="time-log-box">
                                    <div class="time-log-title">Student Time Log</div>
                                    <?php if ($latestStudentLog) { ?>
                                        <div class="time-log-grid">
                                            <div class="label">Date</div>
                                            <div><?php echo htmlspecialchars(date('M d, Y', strtotime($latestStudentLog['log_date']))); ?></div>
                                            <div class="label">Time In</div>
                                            <div><?php echo !empty($latestStudentLog['time_in']) ? htmlspecialchars(date('h:i A', strtotime($latestStudentLog['time_in']))) : '-'; ?></div>
                                            <div class="label">Time Out</div>
                                            <div><?php echo !empty($latestStudentLog['time_out']) ? htmlspecialchars(date('h:i A', strtotime($latestStudentLog['time_out']))) : '-'; ?></div>
                                            <div class="label">Total (Day)</div>
                                            <div><?php echo htmlspecialchars($latestStudentLogDuration ?? '-'); ?></div>
                                            <div class="label">Total (All)</div>
                                            <div><?php echo htmlspecialchars($totalLoggedDuration ?? '0m'); ?></div>
                                        </div>
                                    <?php } else { ?>
                                        <div class="time-log-empty">No time log yet</div>
                                    <?php } ?>
                                </div>
	                            <div style="margin-top:8px;">
	                                <?php if (!empty($selected['resume'])): ?>
	                                    <a href="../assets/uploads/resumes/<?php echo htmlspecialchars($selected['resume']); ?>" class="btn" target="_blank">Download Resume</a>
                                <?php else: ?>
                                    <span style="color:#94a3b8;">No resume uploaded</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

	                    <div class="metrics">
	                        <div class="metric">
	                            <div class="num"><?php echo ($evalCount); ?></div>
	                            <div class="label">Evaluations</div>
                        </div>
                        <div class="metric">
                            <div class="num"><?php echo ($avgScore !== null ? $avgScore . '%' : '-'); ?></div>
                            <div class="label">Average Score</div>
                        </div>
                        <div class="metric">
                            <div class="num"><?php echo isset($selected['created_at']) ? (int) ((time() - strtotime($selected['created_at']))/86400) : '-'; ?></div>
	                            <div class="label">Days in Program</div>
	                        </div>
	                    </div>

                        <div class="time-history-box">
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
                                <div class="time-log-empty">No time log history yet</div>
                            <?php endif; ?>
                        </div>

	                    <div style="margin-top:16px;">
	                        <h3 style="margin-bottom:8px;">Recent Evaluations</h3>
                        <?php
                            try {
                                $rStmt = $conn->prepare("SELECT e.*, u.full_name as supervisor_name FROM evaluations e LEFT JOIN users u ON e.supervisor_id = u.id WHERE e.intern_id = ? ORDER BY e.created_at DESC LIMIT 5");
                                $rStmt->execute([$selected['id']]);
                                $recent = $rStmt->fetchAll();
                            } catch (PDOException $e) {
                                $recent = [];
                            }
                        ?>

                        <?php if (!empty($recent)): ?>
                            <table style="margin-top:10px;">
                                <thead>
                                    <tr><th>Date</th><th>Supervisor</th><th>Score</th><th></th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent as $rv): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars(date('M d, Y', strtotime($rv['eval_date'] ?? $rv['created_at']))); ?></td>
                                            <?php
                                                // If supervisor_name is missing (e.g., hard-coded supervisors with id=0),
                                                // fall back to the current logged-in supervisor's name.
                                                $supName = !empty($rv['supervisor_name']) ? $rv['supervisor_name'] : ($_SESSION['user']['full_name'] ?? '—');
                                            ?>
                                            <td><?php echo htmlspecialchars($supName); ?></td>
                                            <td><?php echo htmlspecialchars(isset($rv['score']) ? ($rv['score'] . '%') : '-'); ?></td>
                                            <td><a href="edit_evaluation.php?id=<?php echo htmlspecialchars($rv['id']); ?>" class="btn">Edit</a></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div style="color:#64748b;padding:10px;border-radius:6px;background:#f8fafc;border:1px solid #eef2ff;">No recent evaluations.</div>
                        <?php endif; ?>
                    </div>

                <?php else: ?>
                    <div style="padding:30px;text-align:center;color:#64748b;">Select an intern to see details.</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- RIGHT: small summary cards -->
        <div class="right-cards">
                <div class="card" style="padding:14px;">
                <h4 style="margin:0 0 8px 0;">Quick Summary</h4>
                <div style="color:#64748b;font-size:14px;">Department: <strong><?php echo htmlspecialchars($department_name); ?></strong></div>
                <div style="margin-top:8px;color:#64748b;font-size:14px;">Total Interns: <strong><?php echo count($students); ?></strong></div>
                <div style="margin-top:8px;color:#64748b;font-size:14px;">Students Evaluated: <strong><?php echo $evaluatedCount; ?></strong></div>
            </div>

            <div class="card" style="padding:14px;">
                <h4 style="margin:0 0 8px 0;">Actions</h4>
                <div style="display:flex;flex-direction:column;gap:8px;margin-top:8px;">
                    <a href="dashboard.php" class="btn">Back to Dashboard</a>
                    <?php if ($selected): ?><a href="view_evaluation.php?id=<?php echo $selected['id']; ?>" class="btn" style="background:#10b981;">View Latest Evaluation</a><?php endif; ?>
                </div>
            </div>
        </div>

    </div>
</div>

</body>
</html>
