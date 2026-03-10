<?php
include "../includes/auth.php";
checkRole('supervisor', 'login.php');

include "../config/database.php";

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    header('Location: view_students.php?error=invalid');
    exit;
}

// Load evaluation
try {
    $stmt = $conn->prepare("SELECT e.*, u.department_id AS intern_dept, u.full_name AS intern_name FROM evaluations e LEFT JOIN users u ON e.intern_id = u.id WHERE e.id = ? LIMIT 1");
    $stmt->execute([$id]);
    $ev = $stmt->fetch();
} catch (PDOException $e) {
    error_log('Edit eval fetch error: ' . $e->getMessage());
    $ev = false;
}

if (!$ev) {
    header('Location: view_students.php?error=notfound');
    exit;
}

// Ensure supervisor can edit: must be supervisor and belong to same department as intern
$dept_id = $_SESSION['user']['department_id'] ?? null;
if ($dept_id !== null && isset($ev['intern_dept']) && $ev['intern_dept'] != $dept_id) {
    header('Location: view_students.php?error=forbidden');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $comm = isset($_POST['comm']) ? (int) $_POST['comm'] : null;
    $problem = isset($_POST['problem']) ? (int) $_POST['problem'] : null;
    $teamwork = isset($_POST['teamwork']) ? (int) $_POST['teamwork'] : null;

    // Validate
    foreach (['comm'=>$comm,'problem'=>$problem,'teamwork'=>$teamwork] as $k=>$v) {
        if (!is_int($v) || $v < 1 || $v > 5) {
            header('Location: edit_evaluation.php?id=' . $id . '&error=invalid_score');
            exit;
        }
    }

    $avg = ($comm + $problem + $teamwork) / 3.0;
    $score = (int) round($avg * 20);

    try {
        $uStmt = $conn->prepare("UPDATE evaluations SET comm = ?, problem = ?, teamwork = ?, score = ? WHERE id = ?");
        $uStmt->execute([$comm, $problem, $teamwork, $score, $id]);
        header('Location: view_students.php?id=' . ($ev['intern_id'] ?? '') . '&success=updated');
        exit;
    } catch (PDOException $e) {
        error_log('Edit eval update error: ' . $e->getMessage());
        header('Location: edit_evaluation.php?id=' . $id . '&error=db');
        exit;
    }
}

?><!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Edit Evaluation</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .page { max-width:720px; margin:28px auto; }
        .card { background:#fff;padding:20px;border-radius:10px;box-shadow:0 6px 18px rgba(15,23,42,0.06); }
        label { display:block;margin-bottom:6px;font-weight:600;color:#334155; }
        select { width:100%;padding:10px;border-radius:8px;border:1px solid #e6eefc;margin-bottom:12px; }
        .actions { display:flex;gap:8px;justify-content:flex-end;margin-top:10px; }
    </style>
</head>
<body>
<div class="page">
    <a href="view_students.php?id=<?php echo htmlspecialchars($ev['intern_id']); ?>">← Back</a>
    <div class="card">
        <h2 style="margin-top:0;">Edit Evaluation for <?php echo htmlspecialchars($ev['intern_name'] ?? ''); ?></h2>

        <?php if (isset($_GET['error'])): ?>
            <div style="background:#fff1f2;border:1px solid #fecaca;color:#991b1b;padding:10px;border-radius:8px;margin-bottom:10px;">Error: <?php echo htmlspecialchars($_GET['error']); ?></div>
        <?php endif; ?>

        <form method="POST" action="edit_evaluation.php?id=<?php echo $id; ?>">
            <label>Communication</label>
            <select name="comm" required>
                <?php for ($i=5;$i>=1;$i--): ?>
                    <option value="<?php echo $i; ?>" <?php echo ((int)($ev['comm']??0) === $i) ? 'selected' : ''; ?>><?php echo str_repeat('★',$i); ?></option>
                <?php endfor; ?>
            </select>

            <label>Problem Solving</label>
            <select name="problem" required>
                <?php for ($i=5;$i>=1;$i--): ?>
                    <option value="<?php echo $i; ?>" <?php echo ((int)($ev['problem']??0) === $i) ? 'selected' : ''; ?>><?php echo str_repeat('★',$i); ?></option>
                <?php endfor; ?>
            </select>

            <label>Teamwork</label>
            <select name="teamwork" required>
                <?php for ($i=5;$i>=1;$i--): ?>
                    <option value="<?php echo $i; ?>" <?php echo ((int)($ev['teamwork']??0) === $i) ? 'selected' : ''; ?>><?php echo str_repeat('★',$i); ?></option>
                <?php endfor; ?>
            </select>

            <div class="actions">
                <a href="view_students.php?id=<?php echo htmlspecialchars($ev['intern_id']); ?>" class="btn">Cancel</a>
                <button type="submit" class="btn">Save Scores</button>
            </div>
        </form>
    </div>
</div>
</body>
</html>
