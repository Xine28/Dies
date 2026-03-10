<?php
session_start();

// If user already logged in, redirect based on role
if (isset($_SESSION['user'])) {

    if ($_SESSION['user']['role'] === 'student') {
        header("Location: student/dashboard.php");
    } elseif ($_SESSION['user']['role'] === 'supervisor') {
        header("Location: supervisor/dashboard.php");
    } elseif ($_SESSION['user']['role'] === 'boss') {
        header("Location: boss/dashboard.php");
    }

    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DIES - Digital Internship Evaluation System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&family=Sora:wght@500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-deep: #081b29;
            --bg-mid: #0f2f42;
            --bg-soft: #e8f6ff;
            --ink-strong: #0c1a24;
            --ink-soft: #395261;
            --brand-a: #11a2de;
            --brand-b: #12c59c;
            --card: rgba(255, 255, 255, 0.9);
            --line: rgba(255, 255, 255, 0.38);
            --shadow: 0 24px 80px rgba(7, 23, 33, 0.28);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            min-height: 100vh;
            font-family: "Manrope", "Segoe UI", sans-serif;
            color: var(--ink-strong);
            background:
                radial-gradient(circle at 12% 18%, rgba(18, 197, 156, 0.22), transparent 34%),
                radial-gradient(circle at 88% 9%, rgba(17, 162, 222, 0.26), transparent 29%),
                linear-gradient(145deg, var(--bg-deep), var(--bg-mid) 46%, #14364b 100%);
            overflow-x: hidden;
        }

        body::before,
        body::after {
            content: "";
            position: fixed;
            pointer-events: none;
            z-index: 0;
            border-radius: 999px;
            filter: blur(2px);
        }

        body::before {
            width: 540px;
            height: 540px;
            left: -180px;
            top: -140px;
            background: radial-gradient(circle, rgba(18, 197, 156, 0.35), transparent 65%);
            animation: floatA 10s ease-in-out infinite;
        }

        body::after {
            width: 460px;
            height: 460px;
            right: -150px;
            bottom: -130px;
            background: radial-gradient(circle, rgba(17, 162, 222, 0.28), transparent 62%);
            animation: floatB 12s ease-in-out infinite;
        }

        .page {
            position: relative;
            z-index: 1;
            width: min(1100px, 92%);
            margin: 48px auto 60px;
            padding: 34px;
            border: 1px solid var(--line);
            border-radius: 26px;
            backdrop-filter: blur(8px);
            background: linear-gradient(145deg, rgba(255, 255, 255, 0.95), rgba(231, 248, 255, 0.83));
            box-shadow: var(--shadow);
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 18px;
            margin-bottom: 28px;
            animation: rise 0.7s ease both;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            font-family: "Sora", "Segoe UI", sans-serif;
            font-size: 1.15rem;
            font-weight: 700;
            letter-spacing: 0.03em;
        }

        .logo-badge {
            width: 42px;
            height: 42px;
            display: grid;
            place-items: center;
            border-radius: 13px;
            color: #fff;
            font-weight: 700;
            background: linear-gradient(140deg, var(--brand-a), var(--brand-b));
        }

        .quick-link {
            text-decoration: none;
            font-weight: 700;
            color: #fff;
            background: linear-gradient(140deg, #0e3f57, #116a8f);
            padding: 10px 16px;
            border-radius: 999px;
            transition: transform 0.2s ease, opacity 0.2s ease;
        }

        .quick-link:hover {
            transform: translateY(-2px);
            opacity: 0.92;
        }

        .hero {
            display: grid;
            grid-template-columns: 1.22fr 0.88fr;
            gap: 24px;
            margin-bottom: 24px;
        }

        .hero-left {
            background: var(--bg-soft);
            border: 1px solid rgba(17, 162, 222, 0.2);
            border-radius: 22px;
            padding: 34px;
            animation: rise 0.8s ease 0.1s both;
        }

        .eyebrow {
            display: inline-block;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            font-size: 0.76rem;
            color: #0d6a92;
            margin-bottom: 16px;
        }

        h1 {
            font-family: "Sora", "Segoe UI", sans-serif;
            line-height: 1.1;
            font-size: clamp(1.8rem, 3.1vw, 2.9rem);
            margin-bottom: 14px;
        }

        .hero-text {
            color: var(--ink-soft);
            line-height: 1.7;
            max-width: 62ch;
        }

        .actions {
            margin-top: 24px;
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }

        .btn {
            text-decoration: none;
            font-weight: 700;
            padding: 12px 18px;
            border-radius: 12px;
            transition: transform 0.2s ease, box-shadow 0.2s ease, opacity 0.2s ease;
        }

        .btn-primary {
            color: #fff;
            background: linear-gradient(140deg, var(--brand-a), var(--brand-b));
            box-shadow: 0 14px 26px rgba(17, 162, 222, 0.24);
        }

        .btn-secondary {
            color: #0d4b69;
            border: 1px solid rgba(17, 162, 222, 0.28);
            background: #fff;
        }

        .btn:hover {
            transform: translateY(-2px);
            opacity: 0.96;
        }

        .hero-right {
            background: linear-gradient(165deg, #0f2f42, #174a66);
            border-radius: 22px;
            padding: 24px;
            color: #e6f5ff;
            display: grid;
            gap: 14px;
            align-content: center;
            animation: rise 0.8s ease 0.18s both;
        }

        .stat {
            background: rgba(255, 255, 255, 0.09);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 14px;
            padding: 14px;
        }

        .stat strong {
            display: block;
            font-family: "Sora", "Segoe UI", sans-serif;
            font-size: 1.3rem;
            margin-bottom: 4px;
        }

        .roles {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 14px;
            animation: rise 0.85s ease 0.24s both;
        }

        .role-card {
            text-decoration: none;
            color: inherit;
            background: rgba(255, 255, 255, 0.9);
            border: 1px solid rgba(17, 162, 222, 0.18);
            border-radius: 16px;
            padding: 18px;
            display: grid;
            gap: 8px;
            transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
        }

        .role-card:hover {
            transform: translateY(-3px);
            border-color: rgba(17, 162, 222, 0.38);
            box-shadow: 0 14px 30px rgba(12, 51, 71, 0.16);
        }

        .role-title {
            font-family: "Sora", "Segoe UI", sans-serif;
            font-size: 1.04rem;
            font-weight: 700;
        }

        .role-card p {
            color: #4d6776;
            line-height: 1.5;
            font-size: 0.94rem;
        }

        footer {
            margin-top: 26px;
            text-align: center;
            color: #426170;
            font-size: 0.88rem;
            animation: rise 0.9s ease 0.35s both;
        }

        @keyframes rise {
            from {
                opacity: 0;
                transform: translateY(14px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes floatA {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(16px); }
        }

        @keyframes floatB {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-14px); }
        }

        @media (max-width: 940px) {
            .hero {
                grid-template-columns: 1fr;
            }

            .roles {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 620px) {
            .page {
                width: 94%;
                margin-top: 22px;
                padding: 18px;
            }

            .topbar {
                flex-direction: column;
                align-items: flex-start;
            }

            .hero-left,
            .hero-right {
                padding: 20px;
            }

            .roles {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <main class="page">
        <div class="topbar">
            <div class="logo">
                <span class="logo-badge">D</span>
                <span>DIES Platform</span>
            </div>
            <a class="quick-link" href="student/login.php">Open Login</a>
        </div>

        <section class="hero">
            <article class="hero-left">
                <span class="eyebrow">Internship Operations</span>
                <h1>Digital Internship Evaluation System</h1>
                <p class="hero-text">
                    Centralize intern onboarding, department assignment, and performance evaluation in one platform.
                    DIES helps students, supervisors, and coordinators track progress with cleaner workflows.
                </p>
                <div class="actions">
                    <a class="btn btn-primary" href="student/register.php">Register as Student</a>
                    <a class="btn btn-secondary" href="student/login.php">Student Login</a>
                </div>
            </article>

            <aside class="hero-right">
                <div class="stat">
                    <strong>1 Platform</strong>
                    <span>For students, supervisors, and management</span>
                </div>
                <div class="stat">
                    <strong>Fast Tracking</strong>
                    <span>Assignments, evaluations, and status in one view</span>
                </div>
                <div class="stat">
                    <strong>Structured Records</strong>
                    <span>SQL-backed data for reliable reporting</span>
                </div>
            </aside>
        </section>

        <section class="roles">
            <a class="role-card" href="student/login.php">
                <span class="role-title">Student Portal</span>
                <p>Access your internship dashboard, profile, resume, and evaluation history.</p>
            </a>

            <a class="role-card" href="supervisor/login.php">
                <span class="role-title">Supervisor Portal</span>
                <p>Review assigned interns, submit evaluations, and monitor performance metrics.</p>
            </a>

            <a class="role-card" href="boss/login.php">
                <span class="role-title">Boss Portal</span>
                <p>Manage departments, assign interns, and oversee the internship lifecycle.</p>
            </a>
        </section>

        <footer>
            DIES - Digital Internship Evaluation System
        </footer>
    </main>

</body>
</html>
