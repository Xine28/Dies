<?php
include "../includes/auth.php";
checkRole('supervisor', '../supervisor/login.php');

include "../config/database.php";
include "../includes/colors.php";

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

// Auto-heal stale evaluation intern_id values:
// If intern_id no longer exists but intern_email matches an existing user,
// remap evaluation to the current user id.
try {
    $conn->exec("
        UPDATE evaluations e
        LEFT JOIN users old_u ON e.intern_id = old_u.id
        LEFT JOIN users new_u ON e.intern_email = new_u.email
        SET e.intern_id = new_u.id,
            e.intern_name = new_u.full_name
        WHERE old_u.id IS NULL
          AND new_u.id IS NOT NULL
          AND e.intern_email IS NOT NULL
          AND e.intern_email <> ''
    ");
} catch (PDOException $e) {
    // Ignore if evaluations.intern_email column is not yet present.
}

// Load intern evaluation history: include all assigned students,
// and attach their latest evaluation by this supervisor if available.
$historyRows = [];
try {
    $historyStmt = $conn->prepare(
        "SELECT
            u.id AS intern_id,
            u.full_name AS intern_name,
            e.id AS evaluation_id,
            e.eval_date,
            e.created_at,
            e.score
         FROM users u
         LEFT JOIN (
            SELECT e1.*
            FROM evaluations e1
            INNER JOIN (
                SELECT intern_id, MAX(id) AS max_id
                FROM evaluations
                WHERE supervisor_id = ?
                GROUP BY intern_id
            ) latest ON latest.max_id = e1.id
         ) e ON e.intern_id = u.id
         WHERE u.role = 'student' AND u.department_id = ?
         ORDER BY u.full_name ASC"
    );
    $historyStmt->execute([$_SESSION['user']['id'], $department_id]);
    $historyRows = $historyStmt->fetchAll();
} catch (PDOException $e) {
    $historyRows = [];
}

// Compute dashboard metrics based on evaluations
$completedCount = 0;
$pendingCount = 0;
$areasForImprovement = 0;
try {
    $cntStmt = $conn->prepare("SELECT COUNT(*) FROM evaluations WHERE supervisor_id = ?");
    $cntStmt->execute([$_SESSION['user']['id']]);
    $completedCount = (int) $cntStmt->fetchColumn();

    $areasStmt = $conn->prepare("SELECT COUNT(*) FROM evaluations WHERE supervisor_id = ? AND score < ?");
    $areasStmt->execute([$_SESSION['user']['id'], 75]);
    $areasForImprovement = (int) $areasStmt->fetchColumn();

    // Pending = number of assigned students in this department without an evaluation by this supervisor
    $pendingStmt = $conn->prepare(
        "SELECT COUNT(*) FROM users u
         LEFT JOIN evaluations e ON u.id = e.intern_id AND e.supervisor_id = ?
         WHERE u.role = 'student' AND u.department_id = ? AND e.id IS NULL"
    );
    $pendingStmt->execute([$_SESSION['user']['id'], $department_id]);
    $pendingCount = (int) $pendingStmt->fetchColumn();

} catch (PDOException $e) {
    $completedCount = 0;
    $pendingCount = count($students);
    $areasForImprovement = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supervisor Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&family=Sora:wght@500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --ink: #0f2018;
            --muted: #4a665b;
            --brand-a: #0f766e;
            --brand-b: #22c55e;
            --surface: rgba(255, 255, 255, 0.95);
            --line: rgba(17, 61, 41, 0.14);
            --shadow: 0 18px 44px rgba(8, 24, 16, 0.14);
            --danger: #b91c1c;
            --warn: #b45309;
            --ok: #166534;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            min-height: 100vh;
            background:
                linear-gradient(rgba(244, 251, 247, 0.86), rgba(244, 251, 247, 0.86)),
                url("../images/supervisor.png") no-repeat center center / cover fixed;
            font-family: "Manrope", "Segoe UI", sans-serif;
            color: var(--ink);
            padding: 24px 0;
        }

        .container {
            width: min(1180px, 92%);
            margin: 0 auto;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 12px 16px;
            box-shadow: var(--shadow);
            margin-bottom: 14px;
        }

        .brand {
            font-family: "Sora", "Segoe UI", sans-serif;
            font-weight: 700;
            color: #0d5f45;
        }

        .top-actions {
            display: grid;
            gap: 6px;
            text-align: right;
            color: #355344;
        }

        .logout-btn {
            display: inline-block;
            text-decoration: none;
            font-weight: 700;
            color: #fff;
            background: #dc2626;
            border-radius: 9px;
            padding: 8px 12px;
        }

        .layout {
            display: grid;
            grid-template-columns: 290px 1fr 340px;
            gap: 12px;
        }

        .card {
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 14px;
            padding: 14px;
            box-shadow: var(--shadow);
        }

        .card h3 {
            font-family: "Sora", "Segoe UI", sans-serif;
            font-size: 1rem;
            margin-bottom: 10px;
        }

        .alert {
            border-radius: 12px;
            padding: 12px 14px;
            margin-bottom: 12px;
            border: 1px solid transparent;
        }

        .alert-ok { color: #065f46; background: #ecfdf5; border-color: #a7f3d0; }
        .alert-err { color: #991b1b; background: #fff1f2; border-color: #fecaca; }

        .search-input,
        .eval-form select,
        .eval-form input[type="date"],
        .eval-form textarea {
            width: 100%;
            border: 1px solid #cfe0ef;
            border-radius: 10px;
            padding: 10px 11px;
            font: inherit;
            background: #fff;
            margin-bottom: 10px;
        }

        .mini-list {
            display: grid;
            gap: 8px;
            max-height: 450px;
            overflow: auto;
        }

        .mini-item {
            border: 1px solid #d5e8dd;
            border-radius: 10px;
            padding: 9px;
            background: #f8fefb;
        }

        .mini-item strong { display: block; margin-bottom: 4px; }
        .mini-item small { color: #4b6358; }

        .hero {
            background: linear-gradient(138deg, #0c5d4f, #129373);
            color: #ecfff7;
            border-radius: 18px;
            padding: 18px;
            margin-bottom: 10px;
        }

        .hero h1 {
            font-family: "Sora", "Segoe UI", sans-serif;
            font-size: clamp(1.25rem, 2vw, 1.75rem);
            margin-bottom: 4px;
        }

        .metrics {
            display: flex;
            gap: 12px;
            margin-top: 12px;
        }

        .metric {
            background: rgba(255,255,255,0.95);
            border: 1px solid rgba(255,255,255,0.6);
            border-radius: 12px;
            padding: 12px;
            flex: 1;
            color: #123527;
        }

        .metric .num {
            font-weight: 800;
            font-size: 1.2rem;
        }

        .metric .lbl {
            font-size: 0.8rem;
            color: #355344;
        }

        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { padding: 9px; border-bottom: 1px solid #e3ece7; text-align: left; }
        th { font-size: 0.8rem; letter-spacing: 0.05em; text-transform: uppercase; color: #4a665b; }

            .btn {
                display: inline-block;
                text-decoration: none;
                font-weight: 700;
                color: #fff;
                background: linear-gradient(140deg, var(--brand-a), var(--brand-b));
                border: none;
                border-radius: 9px;
                padding: 8px 11px;
                cursor: pointer;
                transition: transform 0.18s ease, filter 0.18s ease;
            }

            .btn[disabled] {
                opacity: 0.65;
                cursor: not-allowed;
            }

            .btn:hover:not([disabled]) {
                filter: brightness(0.92);
                transform: translateY(-2px);
            }

        .muted { color: #5a7468; }

        .confirm-modal {
            position: fixed;
            inset: 0;
            background: rgba(7, 20, 14, 0.45);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            padding: 14px;
        }

        .confirm-modal.show {
            display: flex;
        }

        .confirm-box {
            width: min(420px, 96%);
            background: #ffffff;
            border: 1px solid #dbe7df;
            border-radius: 14px;
            box-shadow: 0 18px 46px rgba(7, 20, 14, 0.22);
            padding: 16px;
        }

        .confirm-title {
            font-family: "Sora", "Segoe UI", sans-serif;
            font-size: 1rem;
            margin-bottom: 6px;
            color: #0f2018;
        }

        .confirm-text {
            color: #4a665b;
            margin-bottom: 14px;
            font-size: 0.92rem;
        }

        .confirm-actions {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
        }

        .btn-cancel {
            background: #e2e8f0;
            color: #1e293b;
        }

        @media (max-width: 1080px) {
            .layout { grid-template-columns: 1fr; }
            .top-actions { text-align: left; }
            .metrics { flex-direction: column; }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="topbar">
        <div class="brand">DIES Supervisor Dashboard</div>
        <div class="top-actions">
            <?php $supColor = getDeptColor($department_name ?? ''); ?>
            <div>
                Supervisor:
                <strong style="color:<?php echo htmlspecialchars($supColor); ?>;"><?php echo htmlspecialchars($supervisor_name); ?></strong>
            </div>
            <div><?php echo htmlspecialchars($department_name); ?></div>
            <a href="../logout.php" class="logout-btn">Logout</a>
        </div>
    </div>
    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-ok">Evaluation submitted successfully.</div>
    <?php elseif (isset($_GET['error'])): ?>
        <?php
            $err = $_GET['error'];
            $msg = 'There was an error submitting the evaluation.';
            if ($err === 'method') $msg = 'Invalid request method.';
            if ($err === 'missing_supervisor') $msg = 'You are not authenticated as a supervisor. Please log in again.';
            if ($err === 'missing_intern') $msg = 'Please select an intern before submitting the evaluation.';
            if ($err === 'invalid_intern') $msg = 'Selected intern is not valid or not assigned to your department.';
            if ($err === 'invalid_score') $msg = 'Invalid score selection. Scores must be between 1 and 5.';
            if ($err === 'db') $msg = 'Database error while saving evaluation. Check server logs.';
        ?>
        <div class="alert alert-err"><?php echo htmlspecialchars($msg); ?></div>
    <?php endif; ?>

    <div class="layout">
        <aside class="card">
            <h3>Assigned Interns</h3>
            <form method="GET">
                <input class="search-input" type="text" name="search" placeholder="Search intern..." value="<?php echo htmlspecialchars($search); ?>">
            </form>
            <div class="mini-list">
                <?php if (!empty($students)): ?>
                    <?php foreach ($students as $s): ?>
                        <div class="mini-item">
                            <strong><?php echo htmlspecialchars($s['full_name']); ?></strong>
                            <small><?php echo htmlspecialchars($s['email']); ?></small>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="muted">No interns assigned.</div>
                <?php endif; ?>
            </div>
        </aside>

        <section>
            <div class="hero">
                <h1>Welcome, <?php echo htmlspecialchars($supervisor_name); ?></h1>
                <p>Overview of your assigned interns and evaluation activity.</p>
                <div class="metrics">
                    <div class="metric">
                        <div class="num" style="color:var(--ok);"><?php echo (int)$completedCount; ?></div>
                        <div class="lbl">Completed Evaluations</div>
                    </div>
                    <div class="metric">
                        <div class="num" style="color:var(--warn);"><?php echo (int)$pendingCount; ?></div>
                        <div class="lbl">Pending Evaluations</div>
                    </div>
                    <div class="metric">
                        <div class="num" style="color:var(--danger);"><?php echo (int)$areasForImprovement; ?></div>
                        <div class="lbl">Areas for Improvement</div>
                    </div>
                </div>
            </div>

            <div class="card">
                <h3>Intern Evaluation History</h3>
                <table>
                    <tr>
                        <th>Date</th>
                        <th>Intern</th>
                        <th>Score</th>
                        <th>Action</th>
                    </tr>
                    <?php if (!empty($historyRows)): ?>
                    <?php foreach ($historyRows as $row): ?>
                        <tr>
                            <td>
                                <?php
                                    if (!empty($row['evaluation_id'])) {
                                        echo htmlspecialchars(date('M d, Y', strtotime($row['eval_date'] ?? $row['created_at'] ?? 'now')));
                                    } else {
                                        echo '-';
                                    }
                                ?>
                            </td>
                            <td><?php echo htmlspecialchars($row['intern_name'] ?? ('Intern #' . (int) ($row['intern_id'] ?? 0))); ?></td>
                            <td>
                                <?php if (!empty($row['evaluation_id'])): ?>
                                    <?php echo htmlspecialchars((string) ((int) $row['score'])) . '%'; ?>
                                <?php else: ?>
                                    <span class="muted">No score yet</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="view_students.php?id=<?php echo htmlspecialchars((string) ($row['intern_id'] ?? 0)); ?>" class="btn">View</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="4" class="muted">No interns assigned yet.</td></tr>
                    <?php endif; ?>
                </table>
            </div>
        </section>

        <aside class="card">
            <h3>Submit Evaluation</h3>
            <?php if (!empty($students)): ?>
            <form method="POST" action="submit_evaluation.php" class="eval-form">
                <label>Intern</label>
                <select name="intern_id" required>
                    <option value="">Select Intern</option>
                    <?php foreach ($students as $s): ?>
                        <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['full_name']); ?></option>
                    <?php endforeach; ?>
                </select>

                <label>Evaluation Date</label>
                <input type="date" name="eval_date" value="<?php echo date('Y-m-d'); ?>">

                <label>Communication Skills</label>
                <select name="comm" required>
                    <option value="5">★★★★★</option>
                    <option value="4">★★★★☆</option>
                    <option value="3">★★★☆☆</option>
                    <option value="2">★★☆☆☆</option>
                    <option value="1">★☆☆☆☆</option>
                </select>

                <label>Problem Skills</label>
                <select name="problem" required>
                    <option value="5">★★★★★</option>
                    <option value="4">★★★★☆</option>
                    <option value="3">★★★☆☆</option>
                    <option value="2">★★☆☆☆</option>
                    <option value="1">★☆☆☆☆</option>
                </select>

                <label>Teamwork</label>
                <select name="teamwork" required>
                    <option value="5">★★★★★</option>
                    <option value="4">★★★★☆</option>
                    <option value="3">★★★☆☆</option>
                    <option value="2">★★☆☆☆</option>
                    <option value="1">★☆☆☆☆</option>
                </select>

                <label>Comments & Feedback</label>
                <textarea name="comments" rows="4"></textarea>

                <button class="btn" type="submit" id="submitEvaluationBtn">Submit Evaluation</button>
            </form>
            <?php else: ?>
                <div class="muted">No interns assigned to your department yet.</div>
                <button type="button" class="btn" style="opacity:.6;margin-top:10px;" disabled>Submit Evaluation</button>
            <?php endif; ?>
        </aside>
    </div>
</div>
<div id="submitConfirmModal" class="confirm-modal" role="dialog" aria-modal="true" aria-labelledby="confirmTitle">
    <div class="confirm-box">
        <div id="confirmTitle" class="confirm-title">Submit Evaluation</div>
        <div class="confirm-text">Are you sure you want to submit this evaluation?</div>
        <div class="confirm-actions">
            <button type="button" class="btn btn-cancel" id="confirmCancelBtn">Cancel</button>
            <button type="button" class="btn" id="confirmSubmitBtn">Yes, Submit</button>
        </div>
    </div>
</div>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="../assets/js/logout-popup.js"></script>
<script>
    $(function () {
        var $modal = $('#submitConfirmModal');
        var $pendingForm = null;

        $('.eval-form').on('submit', function (e) {
            var $form = $(this);
            var $btn = $('#submitEvaluationBtn');

            if ($form.data('submitting') === true) {
                e.preventDefault();
                return;
            }

            if ($form.data('confirmed') === true) {
                $form.removeData('confirmed');
                $form.data('submitting', true);
                $btn.prop('disabled', true).text('Submitting...');
                return;
            }

            e.preventDefault();
            $pendingForm = $form;
            $modal.addClass('show');
        });

        $('#confirmCancelBtn').on('click', function () {
            $modal.removeClass('show');
            $pendingForm = null;
        });

        $('#confirmSubmitBtn').on('click', function () {
            if (!$pendingForm) return;
            $modal.removeClass('show');
            $pendingForm.data('confirmed', true);
            $pendingForm.trigger('submit');
        });

        $modal.on('click', function (e) {
            if (e.target === this) {
                $modal.removeClass('show');
                $pendingForm = null;
            }
        });
    });
</script>
</body>
</html>
