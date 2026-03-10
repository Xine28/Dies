<?php
session_start();

// Unset all session variables
$_SESSION = [];

// Destroy session
session_destroy();

// Prevent browser caching (so back button won't work)
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Redirect to login page
header("Location: login.php");
exit();
?>