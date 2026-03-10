<?php
include "../includes/auth.php";
checkRole('student', '../student/login.php');

include "../config/database.php";

$user_id = $_SESSION['user']['id'];
$message = "";
$error = "";

// Fetch latest user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

/* ==============================
   HANDLE RESUME UPLOAD
============================== */
if (isset($_POST['upload_resume'])) {

    if (isset($_FILES['resume']) && $_FILES['resume']['error'] === 0) {

        $allowed = ['pdf', 'doc', 'docx'];
        $fileName = $_FILES['resume']['name'];
        $fileTmp = $_FILES['resume']['tmp_name'];
        $fileSize = $_FILES['resume']['size'];
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        // Validate file type
        if (!in_array($ext, $allowed)) {
            $error = "Only PDF, DOC, DOCX files are allowed.";
        }
        // Validate file size (max 5MB)
        elseif ($fileSize > 5 * 1024 * 1024) {
            $error = "File size must be less than 5MB.";
        }
        else {

            // Delete old resume if exists
            if ($user['resume']) {
                $oldPath = "../assets/uploads/resumes/" . $user['resume'];
                if (file_exists($oldPath)) {
                    unlink($oldPath);
                }
            }

            // Rename file to prevent duplication
            $safeFileName = time() . "_" . preg_replace("/[^a-zA-Z0-9.]/", "", $fileName);
            $uploadPath = "../assets/uploads/resumes/" . $safeFileName;

            if (move_uploaded_file($fileTmp, $uploadPath)) {

                // Update database
                $stmt = $conn->prepare("UPDATE users SET resume=? WHERE id=?");
                $stmt->execute([$safeFileName, $user_id]);

                // Update session
                $_SESSION['user']['resume'] = $safeFileName;

                $message = "Resume uploaded successfully!";
            } else {
                $error = "Upload failed. Please try again.";
            }
        }

    } else {
        $error = "Please select a file.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Upload Resume</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<div class="navbar">
    <a href="dashboard.php">Dashboard</a>
    <a href="profile.php">Profile</a>
    <a href="upload_resume.php">Upload Resume</a>
    <a href="logout.php">Logout</a>
</div>

<h2>Upload / Update Resume</h2>

<?php if ($message): ?>
    <p style="color:green;"><?php echo $message; ?></p>
<?php endif; ?>

<?php if ($error): ?>
    <p style="color:red;"><?php echo $error; ?></p>
<?php endif; ?>

<div class="card">

    <?php if ($user['resume']): ?>
        <p>
            Current Resume:
            <a href="../assets/uploads/resumes/<?php echo $user['resume']; ?>" target="_blank">
                View Resume
            </a>
        </p>
        <br>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">

        <label>Select Resume (PDF, DOC, DOCX | Max 5MB)</label>
        <input type="file" name="resume" required>

        <button type="submit" name="upload_resume">Upload Resume</button>

    </form>

</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="../assets/js/logout-popup.js"></script>
</body>
</html>
