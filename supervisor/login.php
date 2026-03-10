<?php
// Supervisor login (hard-coded accounts)
session_name('SUPERVISOR_SESSION');
session_start();

$error = "";

function verifyUserPassword($inputPassword, $storedPassword) {
    if (!is_string($storedPassword) || $storedPassword === '') {
        return false;
    }

    // Preferred: bcrypt/argon hash
    if (password_verify($inputPassword, $storedPassword)) {
        return true;
    }

    // Backward compatibility for old MD5-seeded accounts.
    if (strlen($storedPassword) === 32 && ctype_xdigit($storedPassword)) {
        return hash_equals(strtolower($storedPassword), md5($inputPassword));
    }

    return false;
}

function getHardcodedSupervisors() {
    return [
        'it@dies.com' => [
            'id' => 2,
            'role' => 'supervisor',
            'full_name' => 'Adrian Reyes',
            'email' => 'it@dies.com',
            'password' => 'e10adc3949ba59abbe56e057f20f883e', // 123456
            'department_id' => 1,
            'department_name' => 'IT Department'
        ],
        'marketing@dies.com' => [
            'id' => 3,
            'role' => 'supervisor',
            'full_name' => 'Yesha Malibong',
            'email' => 'marketing@dies.com',
            'password' => 'e10adc3949ba59abbe56e057f20f883e', // 123456
            'department_id' => 2,
            'department_name' => 'Marketing Department'
        ],
        'inventory@dies.com' => [
            'id' => 4,
            'role' => 'supervisor',
            'full_name' => 'Julianna Margallo',
            'email' => 'inventory@dies.com',
            'password' => 'e10adc3949ba59abbe56e057f20f883e', // 123456
            'department_id' => 3,
            'department_name' => 'Inventory Department'
        ]
    ];
}

/* ==========================
   LOGIN PROCESS
========================== */
if (isset($_POST['login'])) {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = "Please provide email and password.";
    } else {
        $accounts = getHardcodedSupervisors();
        $supervisor = $accounts[$email] ?? null;

        if ($supervisor && verifyUserPassword($password, $supervisor['password'])) {
            $_SESSION['user'] = [
                "id" => (int) $supervisor['id'],
                "role" => $supervisor['role'],
                "full_name" => $supervisor['full_name'],
                "email" => $supervisor['email'],
                "department_id" => isset($supervisor['department_id']) ? (int) $supervisor['department_id'] : null,
                "department_name" => $supervisor['department_name'] ?? ''
            ];

            session_regenerate_id(true);
            header("Location: ../loading.php?role=supervisor&next=supervisor/dashboard.php");
            exit();
        }

        $error = "Invalid credentials.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>DIES - Supervisor Login</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&family=Sora:wght@500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --ink: #10211a;
            --muted: #4a665b;
            --brand-a: #0f766e;
            --brand-b: #22c55e;
            --danger: #b91c1c;
            --surface: rgba(255, 255, 255, 0.94);
            --line: rgba(17, 61, 41, 0.14);
            --shadow: 0 28px 70px rgba(8, 24, 16, 0.4);
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
                linear-gradient(124deg, rgba(10, 40, 33, 0.88), rgba(18, 78, 58, 0.79)),
                url('../images/supervisor.png') no-repeat center center fixed;
            background-size: cover;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 18px;
        }

        .shell {
            width: min(960px, 96%);
            display: grid;
            grid-template-columns: 1.08fr 0.92fr;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: var(--shadow);
            border: 1px solid rgba(255, 255, 255, 0.22);
            animation: rise 0.6s ease both;
        }

        .panel {
            padding: 34px;
            background: linear-gradient(160deg, rgba(255, 255, 255, 0.2), rgba(255, 255, 255, 0.06));
            color: #ebffef;
            display: grid;
            align-content: center;
            gap: 14px;
        }

        .badge {
            display: inline-block;
            width: fit-content;
            font-size: 0.74rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            font-weight: 700;
            padding: 7px 10px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.14);
            border: 1px solid rgba(255, 255, 255, 0.26);
        }

        .panel h1 {
            font-family: "Sora", "Segoe UI", sans-serif;
            font-size: clamp(1.4rem, 2.2vw, 2rem);
            line-height: 1.2;
        }

        .panel p {
            color: rgba(235, 255, 239, 0.88);
            line-height: 1.7;
            max-width: 40ch;
        }

        .form-wrap {
            background: var(--surface);
            padding: 34px;
        }

        .head {
            margin-bottom: 18px;
        }

        .head h2 {
            font-family: "Sora", "Segoe UI", sans-serif;
            font-size: 1.5rem;
            margin-bottom: 6px;
        }

        .head span {
            color: var(--muted);
            font-size: 0.95rem;
        }

        .field {
            margin-bottom: 12px;
        }

        .field label {
            display: block;
            font-size: 0.88rem;
            font-weight: 700;
            color: #1f4636;
            margin-bottom: 6px;
        }

        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 12px 13px;
            border-radius: 11px;
            border: 1px solid var(--line);
            font: inherit;
            outline: none;
            background: #fff;
        }

        input[type="email"]:focus,
        input[type="password"]:focus {
            border-color: #1d9a6d;
            box-shadow: 0 0 0 3px rgba(29, 154, 109, 0.17);
        }

        button {
            width: 100%;
            padding: 12px 14px;
            background: linear-gradient(140deg, var(--brand-a), var(--brand-b));
            color: white;
            border: none;
            border-radius: 11px;
            cursor: pointer;
            font-weight: 700;
            margin-top: 4px;
            transition: transform 0.2s ease, opacity 0.2s ease;
        }

        button:hover {
            transform: translateY(-1px);
            opacity: 0.95;
        }

        .error {
            background: rgba(185, 28, 28, 0.08);
            color: var(--danger);
            border: 1px solid rgba(185, 28, 28, 0.24);
            border-radius: 10px;
            padding: 10px 12px;
            margin-bottom: 12px;
            font-size: 0.92rem;
        }

        .foot {
            margin-top: 14px;
            text-align: center;
            font-size: 0.88rem;
            color: #4c6f60;
        }

        .foot a {
            color: #0e7e52;
            text-decoration: none;
            font-weight: 700;
        }

        .foot a:hover {
            text-decoration: underline;
        }

        @keyframes rise {
            from {
                opacity: 0;
                transform: translateY(16px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 860px) {
            .shell {
                grid-template-columns: 1fr;
            }

            .panel {
                padding: 24px;
            }

            .form-wrap {
                padding: 24px;
            }
        }
    </style>
</head>
<body>

<main class="shell">
    <section class="panel">
        <span class="badge">DIES Supervisor</span>
        <h1>Supervisor Access Portal</h1>
        <p>
            Sign in to review assigned interns, submit evaluations, and track internship progress per department.
        </p>
    </section>

    <section class="form-wrap">
        <div class="head">
            <h2>Supervisor Login</h2>
            <span>Use your supervisor credentials.</span>
        </div>

        <?php if (!empty($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="field">
                <label for="email">Email</label>
                <input id="email" type="email" name="email" placeholder="supervisor@dies.com" required>
            </div>

            <div class="field">
                <label for="password">Password</label>
                <input id="password" type="password" name="password" placeholder="Enter your password" required>
            </div>

            <button type="submit" name="login">Sign In</button>
        </form>

        <div class="foot">
            Back to <a href="../index.php">home page</a>
        </div>
    </section>
</main>

</body>
</html>
