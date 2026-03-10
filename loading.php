<?php
$role = strtolower(trim((string) ($_GET['role'] ?? '')));
$next = trim((string) ($_GET['next'] ?? 'index.php'));

$allowedTargets = [
    'student/dashboard.php',
    'supervisor/dashboard.php',
    'boss/dashboard.php',
    'index.php'
];

if (!in_array($next, $allowedTargets, true)) {
    $next = 'index.php';
}

$roleLabel = 'Loading';
if ($role === 'student') {
    $roleLabel = 'Student Loading';
} elseif ($role === 'supervisor') {
    $roleLabel = 'Supervisor Loading';
} elseif ($role === 'boss') {
    $roleLabel = 'Boss Loading';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DIES Loading</title>
    <style>
        :root {
            --ink: #0f1f2d;
            --muted: #4f6472;
            --brand-a: #0f766e;
            --brand-b: #22c55e;
            --panel: rgba(255, 255, 255, 0.94);
            --line: rgba(20, 44, 69, 0.12);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            min-height: 100vh;
            display: grid;
            place-items: center;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background:
                linear-gradient(rgba(241, 248, 255, 0.9), rgba(241, 248, 255, 0.9)),
                url("images/logo.png") no-repeat center center / 420px;
            color: var(--ink);
            padding: 16px;
        }

        .card {
            width: min(420px, 96%);
            background: var(--panel);
            border: 1px solid var(--line);
            border-radius: 16px;
            box-shadow: 0 18px 44px rgba(5, 18, 30, 0.14);
            padding: 24px;
            text-align: center;
        }

        .logo-wrap {
            width: 82px;
            height: 82px;
            border-radius: 50%;
            margin: 0 auto 12px;
            border: 1px solid #dbe7f5;
            background: #ffffff;
            display: grid;
            place-items: center;
        }

        .logo-wrap img {
            width: 66px;
            height: 66px;
            object-fit: contain;
        }

        h1 {
            font-size: 1.22rem;
            margin-bottom: 6px;
        }

        p {
            color: var(--muted);
            margin-bottom: 14px;
        }

        .bar {
            width: 100%;
            height: 8px;
            background: #e6edf6;
            border-radius: 999px;
            overflow: hidden;
        }

        .bar span {
            display: block;
            width: 42%;
            height: 100%;
            border-radius: 999px;
            background: linear-gradient(140deg, var(--brand-a), var(--brand-b));
            animation: slide 1.2s ease-in-out infinite;
        }

        @keyframes slide {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(260%); }
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="logo-wrap">
            <img src="images/logo.png" alt="DIES Logo">
        </div>
        <h1><?php echo htmlspecialchars($roleLabel); ?></h1>
        <p>Please wait while we prepare your dashboard...</p>
        <div class="bar"><span></span></div>
    </div>

    <script>
        setTimeout(function () {
            window.location.href = <?php echo json_encode($next); ?>;
        }, 1700);
    </script>
</body>
</html>
