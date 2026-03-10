<?php
// Use role-specific session name for boss
session_name('BOSS_SESSION');
session_start();
include "../config/database.php";

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

/* =========================================
   LOGIN PROCESS
========================================= */

if (isset($_POST['login'])) {

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = "Please provide email and password.";
    } else {
        $stmt = $conn->prepare("SELECT id, role, full_name, email, password FROM users WHERE email = ? AND role = 'boss' LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && verifyUserPassword($password, $user['password'])) {
            $_SESSION['user'] = [
                "id" => (int) $user['id'],
                "role" => $user['role'],
                "full_name" => $user['full_name'],
                "email" => $user['email']
            ];

            session_regenerate_id(true);
            header("Location: dashboard.php");
            exit();
        }

        $error = "Invalid credentials.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DIES - Boss Login</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&family=Sora:wght@500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --ink: #0c1a24;
            --muted: #456371;
            --brand-a: #11a2de;
            --brand-b: #12c59c;
            --danger: #b91c1c;
            --surface: rgba(255, 255, 255, 0.93);
            --line: rgba(17, 45, 61, 0.12);
            --shadow: 0 28px 68px rgba(4, 17, 26, 0.38);
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
                linear-gradient(120deg, rgba(8, 27, 41, 0.92), rgba(15, 47, 66, 0.85)),
                url('../images/boss.png') no-repeat center center fixed;
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
            border: 1px solid rgba(255, 255, 255, 0.24);
            animation: rise 0.6s ease both;
        }

        .panel {
            padding: 34px;
            background: linear-gradient(160deg, rgba(255, 255, 255, 0.18), rgba(255, 255, 255, 0.06));
            color: #e6f4ff;
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
            color: rgba(230, 244, 255, 0.86);
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
            color: #1f3948;
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
            border-color: #2ca7d7;
            box-shadow: 0 0 0 3px rgba(44, 167, 215, 0.16);
        }

        button {
            width: 100%;
            padding: 12px 14px;
            background: linear-gradient(140deg, var(--brand-a), var(--brand-b));
            color: white;
            border: none;
            border-radius: 11px;
            cursor: pointer;
            font-weight: bold;
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
            color: #4c6977;
        }

        .foot a {
            color: #0e6e95;
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
        <span class="badge">DIES Admin</span>
        <h1>Boss and Admin Control Access</h1>
        <p>
            Sign in to manage departments, oversee internship assignments, and monitor organization-wide internship performance.
        </p>
    </section>

    <section class="form-wrap">
        <div class="head">
            <h2>Boss Login</h2>
            <span>Use your admin credentials to continue.</span>
        </div>

        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="field">
                <label for="email">Email</label>
                <input id="email" type="email" name="email" placeholder="boss@dies.com" required>
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
