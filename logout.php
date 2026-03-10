<?php
// Detect and start the appropriate role-specific session (if present)
$foundSession = false;
$roleMap = [
	'boss' => 'BOSS_SESSION',
	'supervisor' => 'SUPERVISOR_SESSION',
	'student' => 'STUDENT_SESSION'
];

foreach ($roleMap as $roleKey => $cookieName) {
	if (isset($_COOKIE[$cookieName])) {
		session_name($cookieName);
		session_start();
		$foundSession = true;
		break;
	}
}

if (!$foundSession) {
	// No role-specific session cookie found — start default session
	if (session_status() === PHP_SESSION_NONE) {
		session_start();
	}
}

/* =========================================
   DETECT ROLE BEFORE DESTROYING SESSION
========================================= */
$redirectPath = "index.php"; // default

if (isset($_SESSION['user']['role'])) {

	switch ($_SESSION['user']['role']) {
		case 'boss':
			$redirectPath = "boss/login.php";
			break;

		case 'supervisor':
			$redirectPath = "supervisor/login.php";
			break;

		case 'student':
			$redirectPath = "student/login.php";
			break;
	}
}

/* =========================================
   CLEAR SESSION DATA
========================================= */
$_SESSION = [];

/* Delete session cookie */
if (ini_get("session.use_cookies")) {
	$params = session_get_cookie_params();
	setcookie(
		session_name(),
		'',
		time() - 42000,
		$params["path"],
		$params["domain"],
		$params["secure"],
		$params["httponly"]
	);
}

/* Destroy session */
session_destroy();

/* Prevent browser cache (important) */
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

/* Redirect to correct login */
header("Location: $redirectPath");
exit();
?>