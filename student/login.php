<?php
// Use role-specific session name for student
session_name('STUDENT_SESSION');
session_start();
include "../config/database.php";

$error = "";

if (isset($_POST['login'])) {

    if (empty($_POST['email']) || empty($_POST['password'])) {
        $error = "Please fill in all fields.";
    } else {

        $email = $_POST['email'];
        $password = $_POST['password'];

        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {

            // Standardized session structure
            $_SESSION['user'] = [
                "id" => $user['id'],
                "role" => $user['role'],
                "full_name" => $user['full_name']
            ];

            session_regenerate_id(true);

            // Redirect through loading page first
            header("Location: ../loading.php?role=student&next=student/dashboard.php");
            exit();

        } else {
            $error = "Invalid email or password.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DIES - Student Login</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&family=Sora:wght@500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --ink: #0f1c2d;
            --muted: #4d647c;
            --brand-a: #2563eb;
            --brand-b: #0ea5e9;
            --danger: #b91c1c;
            --surface: rgba(255, 255, 255, 0.94);
            --line: rgba(27, 62, 102, 0.14);
            --shadow: 0 28px 70px rgba(5, 14, 28, 0.4);
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
                linear-gradient(124deg, rgba(10, 33, 66, 0.9), rgba(18, 72, 126, 0.8)),
                url('../images/student.png') no-repeat center center fixed;
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
            color: #eaf5ff;
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
            color: rgba(234, 245, 255, 0.88);
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
            border-color: #3a73ec;
            box-shadow: 0 0 0 3px rgba(58, 115, 236, 0.17);
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
            text-align: center;
            margin-top: 14px;
            font-size: 0.88rem;
            color: #4c6977;
        }

        .foot a {
            text-decoration: none;
            color: #0f63d8;
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
        <span class="badge">DIES Student</span>
        <h1>Student Access Portal</h1>
        <p>
            Sign in to manage your internship profile, submit your documents, and track your evaluation status in one place.
        </p>
    </section>

    <section class="form-wrap">
        <div class="head">
            <h2>Student Login</h2>
            <span>Enter your account credentials.</span>
        </div>

        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="field">
                <label for="email">Email</label>
                <input id="email" type="email" name="email" placeholder="student@email.com" required>
            </div>

            <div class="field">
                <label for="password">Password</label>
                <input id="password" type="password" name="password" placeholder="Enter your password" required>
            </div>

            <button type="submit" name="login">Sign In</button>
        </form>

        <div class="foot">
            Don't have an account? <a href="register.php">Register here</a><br>
            Back to <a href="../index.php">home page</a>
        </div>
    </section>
</main>

</body>
</html>
