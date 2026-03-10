<?php
// configure cookie lifespan globally in header as a precaution
$cookieParams = session_get_cookie_params();
session_set_cookie_params([
    'lifetime' => 60 * 60 * 24 * 7,
    'path'     => $cookieParams['path'],
    'domain'   => $cookieParams['domain'],
    'secure'   => $cookieParams['secure'],
    'httponly' => true,
    'samesite' => 'Lax',
]);

session_start();
require_once "../config/database.php";
?>
<!DOCTYPE html>
<html>
<head>
    <title>DIES System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/logout-popup.js"></script>
</head>
<body>
<div class="layout">

    <aside class="sidebar">
        <div class="sidebar-brand">
            <img src="../images/logo.png" alt="DIES" class="sidebar-logo">
            <h3>DIES</h3>
        </div>

        <nav class="sidebar-nav">
            <a href="../boss/dashboard.php" class="nav-link">Boss Dashboard</a>
            <a href="../supervisor/dashboard.php" class="nav-link">Supervisor Dashboard</a>
            <a href="../student/dashboard.php" class="nav-link">Student Dashboard</a>
            <hr>
            <a href="../logout.php" class="nav-link logout">Logout</a>
        </nav>
    </aside>

    <main class="content">
