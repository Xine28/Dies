<?php
include "../includes/auth.php";
checkRole('boss', '../boss/login.php');

include "../config/database.php";

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    header('Location: dashboard.php?error=invalid');
    exit;
}

$ev = false;
try {
    $stmt = $conn->prepare(
        "SELECT e.*, u.full_name AS intern_name, u.email AS intern_email,
                CASE WHEN s.full_name IS NULL OR s.full_name = '' THEN 'Supervisor' ELSE s.full_name END AS supervisor_name
         FROM evaluations e
         INNER JOIN users u ON e.intern_id = u.id
         LEFT JOIN users s ON e.supervisor_id = s.id
         WHERE e.id = ? LIMIT 1"
    );
    $stmt->execute([$id]);
    $ev = $stmt->fetch();
} catch (PDOException $e) {
    error_log('Boss view evaluation fetch error: ' . $e->getMessage());
    $ev = false;
}

if (!$ev) {
    ?><!doctype html>
    <html>
    <head>
        <meta charset="utf-8">
        <title>Evaluation Not Found</title>
        <link rel="stylesheet" href="../assets/css/style.css">
    </head>
    <body style="background:#f4f6f9;">
        <div style="max-width:900px;margin:40px auto;padding:20px;">
            <div class="card">
                <h3>Evaluation not found</h3>
                <p>The requested evaluation (ID <?php echo htmlspecialchars($id); ?>) could not be located.</p>
                <p><a href="dashboard.php" class="btn">Back to Dashboard</a></p>
            </div>
        </div>
    </body>
    </html><?php
    exit;
}
$internDisplayName = !empty($ev['intern_name']) ? $ev['intern_name'] : ('Intern #' . (int) ($ev['intern_id'] ?? 0));
?><!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>View Evaluation</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body { background:#f4f6f9; }
        .page { max-width:980px; margin:28px auto; }
        .card-inner { background:#fff; padding:26px 28px; border-radius:10px; box-shadow:0 8px 24px rgba(15,23,42,0.06); }
        .back-link { display:inline-block; margin-bottom:18px; color:#2563eb; }
        .details { display:grid; grid-template-columns:220px 1fr; gap:12px 22px; align-items:center; }
        .label { color:#475569; font-weight:600; }
        .value { background:#fff; border:1px solid #eef2ff; padding:12px 14px; border-radius:8px; color:#0f172a; }
        .value.score { color:#075985; font-weight:800; }
        .comments { min-height:60px; white-space:pre-wrap; }
        .close-link { float:right; color:#2563eb; text-decoration:none; font-weight:600; }
    </style>
</head>
<body>

<div class="page">
    <a href="dashboard.php" class="back-link">← Back to Dashboard</a>

    <div class="card card-inner">
        <h2 style="margin-top:0;margin-bottom:16px;">Evaluation Details</h2>

        <div class="details">
            <div class="label">Intern</div>
            <div class="value"><?php echo htmlspecialchars($internDisplayName); ?> — <?php echo htmlspecialchars($ev['intern_email'] ?? ''); ?></div>

            <div class="label">Supervisor</div>
            <div class="value"><?php echo htmlspecialchars($ev['supervisor_name'] ?? 'Supervisor'); ?></div>

            <div class="label">Evaluation Date</div>
            <div class="value"><?php echo htmlspecialchars(date('M d, Y', strtotime($ev['eval_date'] ?? $ev['created_at']))); ?></div>

            <div class="label">Communication</div>
            <div class="value"><?php echo htmlspecialchars($ev['comm'] ?? '-'); ?> / 5</div>

            <div class="label">Problem Solving</div>
            <div class="value"><?php echo htmlspecialchars($ev['problem'] ?? '-'); ?> / 5</div>

            <div class="label">Teamwork</div>
            <div class="value"><?php echo htmlspecialchars($ev['teamwork'] ?? '-'); ?> / 5</div>

            <div class="label">Overall Score</div>
            <div class="value score"><?php echo htmlspecialchars(isset($ev['score']) ? ($ev['score'] . '%') : '-'); ?></div>

            <div class="label">Comments</div>
            <div class="value comments"><?php echo nl2br(htmlspecialchars($ev['comments'] ?? '')); ?></div>
        </div>

        <a href="dashboard.php" class="close-link">Close</a>
    </div>

</div>

</body>
</html>

