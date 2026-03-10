<?php
/*
 * Role-aware session helper and auth checks.
 * This enables separate session names per role so a single browser
 * can maintain multiple simultaneous logins (one per role).
 */

/* List of known roles (order matters for detection) */
function knownRoles() {
    return ['boss', 'supervisor', 'student'];
}

/* Start session using a role-specific session name */
function startSessionForRole($role) {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $name = strtoupper($role) . '_SESSION';
    session_name($name);
    session_start();
}

/* Detect any existing role-specific session cookie and start it. */
function detectAndStartAnyRoleSession() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    foreach (knownRoles() as $role) {
        $cookieName = strtoupper($role) . '_SESSION';
        if (isset($_COOKIE[$cookieName])) {
            session_name($cookieName);
            session_start();
            return;
        }
    }

    // No role-specific cookie found — start default session
    session_start();
}

/* =========================================
   CHECK IF USER IS LOGGED IN
========================================= */
function checkLogin($loginPath) {
    detectAndStartAnyRoleSession();
    if (!isset($_SESSION['user'])) {
        header("Location: $loginPath");
        exit();
    }
}

/* =========================================
   CHECK USER ROLE
   Starts the session for the requested role name, then verifies it.
========================================= */
function checkRole($requiredRole, $loginPath = null) {
    // Ensure we're using the role-specific session (so multiple sessions can coexist)
    startSessionForRole($requiredRole);
    // Provide a sensible default login path if none supplied
    if ($loginPath === null) {
        $loginPath = "../" . $requiredRole . "/login.php";
    }

    if (!isset($_SESSION['user'])) {
        header("Location: $loginPath");
        exit();
    }

    if (!isset($_SESSION['user']['role']) || $_SESSION['user']['role'] !== $requiredRole) {
        header("Location: $loginPath");
        exit();
    }
}

?>