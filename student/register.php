<?php
session_start();
include "../config/database.php";

$message = "";
$error = "";

if (isset($_POST['register'])) {

    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($full_name) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = "All fields are required.";
    }
    elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    }
    else {

        $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check->execute([$email]);

        if ($check->rowCount() > 0) {
            $error = "Email already registered.";
        } else {

            if (isset($_FILES['resume']) && $_FILES['resume']['error'] === 0) {

                $allowed = ['pdf', 'doc', 'docx'];
                $fileName = $_FILES['resume']['name'];
                $fileTmp = $_FILES['resume']['tmp_name'];
                $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

                if (!in_array($ext, $allowed)) {
                    $error = "Only PDF, DOC, DOCX files are allowed.";
                } else {

                    $newFileName = time() . "_" . preg_replace("/[^a-zA-Z0-9.]/", "", $fileName);
                    $uploadPath = "../assets/uploads/resumes/" . $newFileName;

                    if (move_uploaded_file($fileTmp, $uploadPath)) {

                        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                        $stmt = $conn->prepare("
                            INSERT INTO users (role, full_name, email, password, resume, profile_completed)
                            VALUES ('student', ?, ?, ?, ?, 1)
                        ");

                        $stmt->execute([
                            $full_name,
                            $email,
                            $hashedPassword,
                            $newFileName
                        ]);

                        $message = "Registration successful! You can now login.";
                    } else {
                        $error = "Resume upload failed.";
                    }
                }

            } else {
                $error = "Please upload your resume.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DIES - Student Registration</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&family=Sora:wght@500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --ink: #0f1f2d;
            --muted: #4f6372;
            --line: rgba(14, 45, 70, 0.18);
            --brand-a: #0b6d8c;
            --brand-b: #1198c8;
            --ok-bg: rgba(22, 163, 74, 0.13);
            --ok-text: #15803d;
            --err-bg: rgba(220, 38, 38, 0.1);
            --err-text: #b91c1c;
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
                linear-gradient(120deg, rgba(7, 31, 49, 0.82), rgba(9, 86, 124, 0.74)),
                url("../images/student.png") no-repeat center center / cover fixed;
            display: grid;
            place-items: center;
            padding: 20px;
        }

        .shell {
            width: min(980px, 96%);
            display: grid;
            grid-template-columns: 1.02fr 0.98fr;
            border-radius: 22px;
            overflow: hidden;
            box-shadow: 0 24px 58px rgba(3, 17, 29, 0.45);
            border: 1px solid rgba(255, 255, 255, 0.22);
        }

        .hero {
            background: linear-gradient(158deg, rgba(255, 255, 255, 0.17), rgba(255, 255, 255, 0.06));
            color: #ebf9ff;
            padding: 34px 30px;
            display: grid;
            align-content: center;
            gap: 14px;
        }

        .badge {
            width: fit-content;
            padding: 6px 10px;
            border-radius: 999px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.07em;
            text-transform: uppercase;
            background: rgba(255, 255, 255, 0.13);
        }

        .hero h1 {
            font-family: "Sora", "Segoe UI", sans-serif;
            font-size: clamp(1.35rem, 2.5vw, 2rem);
            line-height: 1.25;
        }

        .hero p {
            color: rgba(235, 249, 255, 0.9);
            line-height: 1.7;
            max-width: 40ch;
        }

        .tips {
            margin-top: 10px;
            display: grid;
            gap: 8px;
            font-size: 0.9rem;
            color: rgba(235, 249, 255, 0.92);
        }

        .panel {
            background: rgba(255, 255, 255, 0.95);
            padding: 30px;
        }

        .panel h2 {
            font-family: "Sora", "Segoe UI", sans-serif;
            font-size: 1.45rem;
            margin-bottom: 6px;
        }

        .panel-sub {
            color: var(--muted);
            font-size: 0.94rem;
            margin-bottom: 16px;
        }

        .message {
            margin-bottom: 12px;
            padding: 10px 12px;
            border-radius: 10px;
            font-size: 0.9rem;
            font-weight: 700;
        }

        .success {
            background: var(--ok-bg);
            color: var(--ok-text);
            border: 1px solid rgba(22, 163, 74, 0.3);
        }

        .error {
            background: var(--err-bg);
            color: var(--err-text);
            border: 1px solid rgba(220, 38, 38, 0.26);
        }

        .field {
            margin-bottom: 11px;
        }

        .field label {
            display: block;
            margin-bottom: 6px;
            font-size: 0.86rem;
            font-weight: 700;
            color: #1d3850;
        }

        .field input {
            width: 100%;
            border: 1px solid var(--line);
            border-radius: 10px;
            padding: 11px 12px;
            font: inherit;
            background: #fff;
            outline: none;
        }

        .field input:focus {
            border-color: #1694bf;
            box-shadow: 0 0 0 3px rgba(22, 148, 191, 0.15);
        }

        .help {
            margin-top: 4px;
            font-size: 0.78rem;
            color: #617789;
        }

        button {
            width: 100%;
            margin-top: 4px;
            border: none;
            border-radius: 10px;
            padding: 12px 14px;
            font: inherit;
            font-weight: 800;
            color: #fff;
            background: linear-gradient(140deg, var(--brand-a), var(--brand-b));
            cursor: pointer;
            transition: transform 0.2s ease, opacity 0.2s ease;
        }

        button:hover {
            transform: translateY(-1px);
            opacity: 0.94;
        }

        .login-link {
            margin-top: 12px;
            text-align: center;
            color: #466073;
            font-size: 0.9rem;
        }

        .login-link a {
            color: #0d78a3;
            text-decoration: none;
            font-weight: 700;
        }

        .login-link a:hover {
            text-decoration: underline;
        }

        @media (max-width: 860px) {
            .shell {
                grid-template-columns: 1fr;
            }

            .hero {
                padding: 24px;
            }

            .panel {
                padding: 24px;
            }
        }
    </style>
</head>
<body>
<main class="shell">
    <section class="hero">
        <span class="badge">DIES Student</span>
        <h1>Create your internship account</h1>
        <p>Register your student account, upload your resume, and get ready for department assignment and supervisor evaluation.</p>
        <div class="tips">
            <div>Resume formats: PDF, DOC, DOCX</div>
            <div>Use a valid email for updates</div>
            <div>Make sure passwords match before submit</div>
        </div>
    </section>

    <section class="panel">
        <h2>Student Registration</h2>
        <div class="panel-sub">Fill in your details to continue.</div>

        <?php if ($message): ?>
            <div class="message success"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="message error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">
            <div class="field">
                <label for="full_name">Full Name</label>
                <input id="full_name" type="text" name="full_name" placeholder="Juan Dela Cruz" required>
            </div>

            <div class="field">
                <label for="email">Email Address</label>
                <input id="email" type="email" name="email" placeholder="student@email.com" required>
            </div>

            <div class="field">
                <label for="password">Password</label>
                <input id="password" type="password" name="password" placeholder="Enter password" required>
            </div>

            <div class="field">
                <label for="confirm_password">Confirm Password</label>
                <input id="confirm_password" type="password" name="confirm_password" placeholder="Re-enter password" required>
            </div>

            <div class="field">
                <label for="resume">Upload Resume</label>
                <input id="resume" type="file" name="resume" required>
                <div class="help">Accepted files: PDF, DOC, DOCX</div>
            </div>

            <button type="submit" name="register">Create Account</button>
        </form>

        <div class="login-link">
            Already have an account? <a href="login.php">Login here</a>
        </div>
    </section>
</main>
</body>
</html>
