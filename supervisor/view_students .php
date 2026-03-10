<?php
// Fix for accidental URL with a space (view_students%20.php)
// Redirect to the correct `view_students.php`, preserving query string.
$qs = isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== '' ? ('?' . $_SERVER['QUERY_STRING']) : '';
header('Location: view_students.php' . $qs, true, 301);
exit;
