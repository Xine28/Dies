    <?php
    include "../includes/auth.php";
    checkRole('boss', '../boss/login.php');

    include "../config/database.php";
    require_once "../includes/mailer.php";

    // Initialize message variable (prevents undefined variable warnings)
    $message = "";

    $boss_name = $_SESSION['user']['full_name'];

    /* =========================================
    HANDLE INTERN ASSIGNMENT (PRG PATTERN)
    ========================================= */
    if (isset($_POST['assign'])) {

        $student_id = isset($_POST['student_id']) ? (int) $_POST['student_id'] : 0;
        $department_id = isset($_POST['department_id']) ? (int) $_POST['department_id'] : 0;

        // Lock assignment once already assigned
        $lock_stmt = $conn->prepare("SELECT department_id, full_name, email FROM users WHERE id = ? AND role = 'student' LIMIT 1");
        $lock_stmt->execute([$student_id]);
        $locked_student = $lock_stmt->fetch();

        if (!$locked_student) {
            header("Location: dashboard.php?error=invalid_student");
            exit();
        }

        if (!empty($locked_student['department_id'])) {
            header("Location: dashboard.php?error=already_assigned");
            exit();
        }

        if ($department_id <= 0) {
            header("Location: dashboard.php?error=invalid_department");
            exit();
        }

        // Update department only for unassigned students
        $stmt = $conn->prepare("UPDATE users SET department_id = ? WHERE id = ? AND (department_id IS NULL OR department_id = 0)");
        $stmt->execute([$department_id, $student_id]);

        // Get student info
        $student_stmt = $conn->prepare("SELECT full_name, email FROM users WHERE id = ?");
        $student_stmt->execute([$student_id]);
        $student = $student_stmt->fetch();

        // Get department + supervisor
        $dept_stmt = $conn->prepare("SELECT department_name, supervisor_name FROM departments WHERE id = ?");
        $dept_stmt->execute([$department_id]);
        $department = $dept_stmt->fetch();

        // Send Email Notification
        if ($student && $department) {
            sendDepartmentAssignmentEmail(
                $student['email'],
                $student['full_name'],
                $department['department_name'],
                $department['supervisor_name']
            );
        }

        // ✅ REDIRECT AFTER POST (VERY IMPORTANT)
        header("Location: dashboard.php?success=1");
        exit();
    }

    /* =========================================
    HANDLE STUDENT DELETE
    ========================================= */
    if (isset($_POST['delete_student'])) {
        $student_id = isset($_POST['student_id']) ? (int) $_POST['student_id'] : 0;
        if ($student_id <= 0) {
            header("Location: dashboard.php?error=invalid_student");
            exit();
        }

        try {
            $check_stmt = $conn->prepare("SELECT id, role, resume, profile_pic FROM users WHERE id = ? LIMIT 1");
            $check_stmt->execute([$student_id]);
            $student_to_delete = $check_stmt->fetch();

            if (!$student_to_delete || ($student_to_delete['role'] ?? '') !== 'student') {
                header("Location: dashboard.php?error=invalid_student");
                exit();
            }

            // Prevent deleting interns with existing evaluations.
            $eval_stmt = $conn->prepare("SELECT COUNT(*) FROM evaluations WHERE intern_id = ?");
            $eval_stmt->execute([$student_id]);
            $evaluation_count = (int) $eval_stmt->fetchColumn();
            if ($evaluation_count > 0) {
                header("Location: dashboard.php?error=has_evaluations");
                exit();
            }

            $resume = $student_to_delete['resume'] ?? '';
            $profile_pic = $student_to_delete['profile_pic'] ?? '';

            $delete_stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'student'");
            $delete_stmt->execute([$student_id]);

            if ($delete_stmt->rowCount() > 0) {
                if (!empty($resume)) {
                    $resume_path = __DIR__ . "/../assets/uploads/resumes/" . $resume;
                    if (file_exists($resume_path)) {
                        @unlink($resume_path);
                    }
                }

                if (!empty($profile_pic)) {
                    $avatar_path = __DIR__ . "/../assets/uploads/avatars/" . $profile_pic;
                    if (file_exists($avatar_path)) {
                        @unlink($avatar_path);
                    }
                }
            }

            header("Location: dashboard.php?success=deleted");
            exit();
        } catch (PDOException $e) {
            header("Location: dashboard.php?error=db");
            exit();
        }
    }

    /* =========================================
    GET ALL STUDENTS WITH DEPARTMENT
    ========================================= */
    $stmt = $conn->prepare("
        SELECT users.*, departments.department_name,
            ev_latest.id AS latest_evaluation_id,
            ev_latest.score AS latest_evaluation_score
        FROM users
        LEFT JOIN departments ON users.department_id = departments.id
        LEFT JOIN (
            SELECT e1.id, e1.intern_id, e1.score
            FROM evaluations e1
            INNER JOIN (
                SELECT intern_id, MAX(id) AS max_id
                FROM evaluations
                GROUP BY intern_id
            ) e2 ON e1.id = e2.max_id
        ) ev_latest ON ev_latest.intern_id = users.id
        WHERE users.role = 'student'
    ");
    $stmt->execute();
    $students = $stmt->fetchAll();

    /* =========================================
    GET ALL DEPARTMENTS
    ========================================= */
    $dept_stmt = $conn->prepare("SELECT * FROM departments");
    $dept_stmt->execute();
    $departments = $dept_stmt->fetchAll();
    // Initialize message variable and handle success feedback
    $message = '';
    if (isset($_GET['success']) && $_GET['success'] == '1') {
        $message = 'Intern successfully assigned and email sent.';
    } elseif (isset($_GET['success']) && $_GET['success'] === 'deleted') {
        $message = 'Student deleted successfully.';
    }
    if (isset($_GET['error'])) {
        if ($_GET['error'] === 'already_assigned') {
            $message = 'This intern is already assigned to a department. Reassignment is locked.';
        } elseif ($_GET['error'] === 'invalid_student') {
            $message = 'Invalid intern selected.';
        } elseif ($_GET['error'] === 'invalid_department') {
            $message = 'Please select a valid department.';
        } elseif ($_GET['error'] === 'has_evaluations') {
            $message = 'Cannot delete this student because evaluation records already exist.';
        } elseif ($_GET['error'] === 'db') {
            $message = 'Database error while deleting student.';
        }
    }

    $total_interns = count($students);
    $assigned_interns = 0;
    foreach ($students as $s) {
        if (!empty($s['department_name'])) {
            $assigned_interns++;
        }
    }
    ?>

    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Boss Dashboard</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&family=Sora:wght@500;600;700&display=swap" rel="stylesheet">

        <style>
            :root {
                --ink: #0c1a24;
                --muted: #506675;
                --brand-a: #0f766e;
                --brand-b: #22c55e;
                --surface: rgba(255, 255, 255, 0.95);
                --line: rgba(20, 44, 69, 0.12);
                --shadow: 0 18px 44px rgba(4, 16, 26, 0.14);
                --danger: #b91c1c;
                --ok: #166534;
                --warn: #b45309;
            }

            * { box-sizing: border-box; margin: 0; padding: 0; }

            body {
                min-height: 100vh;
                background:
                    linear-gradient(rgba(247, 251, 249, 0.88), rgba(247, 251, 249, 0.88)),
                    url("../images/boss.png") no-repeat center center / cover fixed;
                font-family: "Manrope", "Segoe UI", sans-serif;
                color: var(--ink);
                padding: 24px 0;
            }

            .sidebar {
                background: #000f2b;
                width: 270px;
                height: 100vh;
                position: fixed;
                left: 0;
                top: 0;
                z-index: 20;
                padding: 58px 22px 22px 32px;
                display: flex;
                flex-direction: column;
                gap: 0;
                border-right: 1px solid rgba(255, 255, 255, 0.08);
            }

            .sidebar .brand {
                font-family: "Sora", "Segoe UI", sans-serif;
                font-weight: 700;
                color: #f8fafc;
                font-size: 18px;
                margin-bottom: 34px;
                padding: 0;
            }

            .sidebar a {
                text-decoration: none;
                font-weight: 700;
                font-size: 18px;
                color: #ffffff;
                border: none;
                border-radius: 0;
                padding: 0;
                line-height: 1.2;
            }

            .sidebar a + a {
                margin-top: 38px;
            }

            .sidebar a:hover {
                color: #ffffff;
                text-decoration: underline;
            }

            .container {
                width: min(1240px, calc(100% - 300px));
                margin: 0 0 0 290px;
            }

            .hero {
                background: linear-gradient(138deg, #0c5d4f, #129373);
                color: #ecfff7;
                border-radius: 18px;
                padding: 18px;
                margin-bottom: 12px;
                box-shadow: var(--shadow);
            }

            .hero h1 {
                font-family: "Sora", "Segoe UI", sans-serif;
                font-size: clamp(1.3rem, 2.1vw, 1.9rem);
                margin-bottom: 4px;
            }

            .hero p { color: rgba(236, 255, 247, 0.9); }

            .message {
                margin-bottom: 12px;
                padding: 12px 14px;
                border-radius: 11px;
                border: 1px solid;
                font-weight: 600;
            }

            .msg-ok {
                color: #065f46;
                background: #ecfdf5;
                border-color: #a7f3d0;
            }

            .msg-err {
                color: #991b1b;
                background: #fff1f2;
                border-color: #fecaca;
            }

            .stats {
                display: flex;
                gap: 12px;
                flex-wrap: wrap;
                margin-bottom: 12px;
            }

            .stat {
                background: var(--surface);
                border: 1px solid var(--line);
                border-radius: 12px;
                padding: 14px 16px;
                min-width: 150px;
                box-shadow: var(--shadow);
            }

            .stat-label {
                font-size: 12px;
                color: var(--muted);
                margin-bottom: 4px;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }

            .stat-value {
                font-size: 24px;
                font-weight: 700;
            }

            .table-card {
                background: var(--surface);
                border: 1px solid var(--line);
                border-radius: 16px;
                padding: 14px;
                box-shadow: var(--shadow);
            }

            .table-title {
                font-family: "Sora", "Segoe UI", sans-serif;
                margin-bottom: 10px;
            }

            table {
                width: 100%;
                border-collapse: collapse;
                overflow: auto;
            }

            th {
                color: #4a665b;
                padding: 10px 9px;
                text-align: left;
                border-bottom: 1px solid #e3ece7;
                font-size: 12px;
                text-transform: uppercase;
                letter-spacing: 0.45px;
            }

            td {
                padding: 10px 9px;
                border-bottom: 1px solid #edf3ef;
                vertical-align: middle;
            }

            tr:hover { background: #f9fdfa; }

            tr:last-child td {
                border-bottom: none;
            }

            .assign-form {
                display: flex;
                gap: 8px;
                flex-wrap: wrap;
            }

            select {
                padding: 9px 11px;
                border-radius: 10px;
                border: 1px solid #cfe0ef;
                min-width: 170px;
                background: #fff;
                color: var(--ink);
            }

            button {
                padding: 9px 13px;
                background: linear-gradient(140deg, var(--brand-a), var(--brand-b));
                color: #fff;
                border: none;
                border-radius: 10px;
                cursor: pointer;
                font-weight: 700;
                transition: transform 0.18s ease, filter 0.18s ease, background-color 0.18s ease;
            }

            .delete-btn {
                background: #dc2626;
            }

            .delete-btn:hover:not(:disabled) {
                background: #b91c1c;
                transform: translateY(-2px);
                filter: brightness(0.95);
            }

            .delete-btn:disabled {
                opacity: 0.55;
                cursor: not-allowed;
            }

            .profile-link {
                display: inline-block;
                padding: 8px 12px;
                border-radius: 9px;
                text-decoration: none;
                border: 1px solid #cfe3ff;
                color: #14426d;
                background: #eff6ff;
                font-weight: 700;
                font-size: 13px;
                transition: transform 0.18s ease, background-color 0.18s ease, border-color 0.18s ease, color 0.18s ease;
            }

            .profile-link:hover {
                background: #dbeafe;
                border-color: #93c5fd;
                color: #0f3b67;
                transform: translateY(-2px);
            }

            .not-assigned {
                color: var(--danger);
                font-weight: 700;
            }

            .section-title {
                margin: 0 0 14px;
                font-size: 18px;
                font-family: "Sora", "Segoe UI", sans-serif;
            }

            @media (max-width: 880px) {
                .sidebar {
                    width: 100%;
                    height: auto;
                    position: static;
                    border-bottom: 1px solid rgba(255, 255, 255, 0.12);
                    flex-direction: row;
                    align-items: center;
                    flex-wrap: wrap;
                    padding: 12px;
                }

                .sidebar .brand {
                    width: 100%;
                    margin-bottom: 0;
                    padding: 4px 0 8px;
                }

                .container {
                    width: min(1240px, 94%);
                    margin: 12px auto 0;
                }

                th, td { white-space: nowrap; }
            }
        </style>
    </head>
    <body>

    <div class="container">
        <div class="sidebar">
            <div class="brand">DIES Boss Dashboard</div>
            <a href="dashboard.php">Dashboard</a>
            <a href="../logout.php">Logout</a>
        </div>

        <div class="hero">
            <div>
                <h1>Boss Dashboard</h1>
                <p>Manage intern department assignments and monitor coverage.</p>
            </div>
        </div>

        <?php if ($message): ?>
            <?php
                $msgClass = 'msg-ok';
                if (isset($_GET['error'])) {
                    $msgClass = 'msg-err';
                }
            ?>
            <div class="message <?php echo $msgClass; ?>"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <div class="stats">
            <div class="stat">
                <div class="stat-label">Total Interns</div>
                <div class="stat-value"><?php echo $total_interns; ?></div>
            </div>
            <div class="stat">
                <div class="stat-label">Assigned</div>
                <div class="stat-value"><?php echo $assigned_interns; ?></div>
            </div>
            <div class="stat">
                <div class="stat-label">Unassigned</div>
                <div class="stat-value" style="color:<?php echo (($total_interns - $assigned_interns) > 0) ? 'var(--warn)' : 'var(--ok)'; ?>">
                    <?php echo $total_interns - $assigned_interns; ?>
                </div>
            </div>
        </div>

        <div class="table-card">
            <h3 class="table-title">Manage Intern Assignments</h3>
            <div style="overflow:auto;">
                <table>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Current Department</th>
                        <th>Evaluation</th>
                        <th>Profile</th>
                        <th>Delete</th>
                        <th>Assign Department</th>
                    </tr>

                    <?php if (!empty($students)): ?>
                        <?php foreach ($students as $student): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($student['email']); ?></td>

                                <td>
                                    <?php
                                        if ($student['department_name']) {
                                            echo htmlspecialchars($student['department_name']);
                                        } else {
                                            echo "<span class='not-assigned'>Not Assigned</span>";
                                        }
                                    ?>
                                </td>

                                <td>
                                    <?php if (!empty($student['latest_evaluation_id'])): ?>
                                        <span style="color:var(--ok);font-weight:700;">Submitted</span><br>
                                        <a class="profile-link" href="view_evaluation.php?id=<?php echo (int) $student['latest_evaluation_id']; ?>" style="margin-top:8px;">View</a>
                                    <?php else: ?>
                                        <span class="not-assigned">Pending</span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <a class="profile-link" href="view_profile.php?id=<?php echo (int) $student['id']; ?>">View Profile</a>
                                </td>

                                <td>
                                    <form method="POST" class="delete-student-form">
                                        <input type="hidden" name="student_id" value="<?php echo (int) $student['id']; ?>">
                                        <button
                                            type="submit"
                                            name="delete_student"
                                            class="delete-btn"
                                            <?php echo !empty($student['latest_evaluation_id']) ? 'disabled title="Cannot delete: evaluation exists"' : ''; ?>
                                        >
                                            Delete
                                        </button>
                                    </form>
                                </td>

                                <td>
                                    <?php if (!empty($student['department_id'])): ?>
                                        <span style="color:var(--ok);font-weight:700;">Have an supervisor</span>
                                    <?php else: ?>
                                        <form method="POST" class="assign-form">
                                            <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                                            <select name="department_id" required>
                                                <option value="">Select Department</option>
                                                <?php foreach ($departments as $dept): ?>
                                                    <option value="<?php echo $dept['id']; ?>">
                                                        <?php echo htmlspecialchars($dept['department_name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="submit" name="assign">Assign</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" align="center">No interns registered.</td>
                        </tr>
                    <?php endif; ?>

                </tr>
                </table>
            </div>
        </div>
    </div>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script>
        $(function () {
            $('.delete-student-form').on('submit', function (e) {
                var ok = window.confirm('Delete this student account?');
                if (!ok) {
                    e.preventDefault();
                }
            });
        });
    </script>
    <script src="../assets/js/logout-popup.js"></script>
    </body>
    </html>
