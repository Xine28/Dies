<?php
include "../includes/auth.php";
checkRole('boss', '../boss/login.php');

include "../config/database.php";
require_once "../includes/mailer.php";

// Check if student ID exists
if (!isset($_GET['id'])) {
    header("Location: dashboard.php");
    exit();
}

$student_id = $_GET['id'];

// Get student info
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ? AND role = 'student'");
$stmt->execute([$student_id]);
$student = $stmt->fetch();

if (!$student) {
    die("Student not found.");
}

// Handle assignment
if (isset($_POST['assign'])) {

    $department_id = $_POST['department'];

    // Get department details
    $deptStmt = $conn->prepare("SELECT * FROM departments WHERE id = ?");
    $deptStmt->execute([$department_id]);
    $department = $deptStmt->fetch();

    if (!$department) {
        die("Invalid department selected.");
    }

    // Update student department
    $updateStmt = $conn->prepare("UPDATE users SET department_id = ? WHERE id = ?");
    $updateStmt->execute([$department_id, $student_id]);

    // Send email notification
    sendEmail(
        $student['email'],
        $student['full_name'],
        $department['department_name'],
        $department['supervisor_name']
    );

    $success = "Student successfully assigned to " . $department['department_name'];
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Assign Department</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<h2>Assign Department</h2>

<p><strong>Student Name:</strong> <?php echo $student['full_name']; ?></p>
<p><strong>Email:</strong> <?php echo $student['email']; ?></p>

<?php if (isset($success)) { ?>
    <p style="color:green;"><strong><?php echo $success; ?></strong></p>
<?php } ?>

<form method="POST">

    <label>Select Department:</label><br><br>

    <select name="department" required>
        <option value="">-- Choose Department --</option>
        <?php
        $departments = $conn->query("SELECT * FROM departments");
        while ($row = $departments->fetch()) {
            echo "<option value='".$row['id']."'>"
                .$row['department_name']." (Supervisor: ".$row['supervisor_name'].")"
                ."</option>";
        }
        ?>
    </select>

    <br><br>

    <button type="submit" name="assign">Assign Department</button>

</form>

<br>
<a href="dashboard.php">⬅ Back to Dashboard</a>

</body>
</html>