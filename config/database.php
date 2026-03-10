<?php
/*
|--------------------------------------------------------------------------
| DIES - Database Connection File
|--------------------------------------------------------------------------
| Edit the credentials below according to your XAMPP setup
|--------------------------------------------------------------------------
*/

$host = "localhost";
$dbname = "dies";
$username = "root";
$password = "";

try {

    $conn = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password
    );

    // Enable Exceptions
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Default Fetch Mode
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    die("Database Connection Failed: " . $e->getMessage());
}
?>