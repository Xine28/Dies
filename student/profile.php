<?php
include "../includes/auth.php";
checkRole('student', '../student/login.php');

include "../config/database.php";

$user_id = $_SESSION['user']['id'];
$message = "";
$error = "";

$stmt = $conn->prepare("SELECT u.*, d.department_name AS department_name FROM users u LEFT JOIN departments d ON u.department_id = d.id WHERE u.id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Ensure avatar upload directory exists
$avatarDir = __DIR__ . "/../assets/uploads/avatars/";
if (!is_dir($avatarDir)) {
    mkdir($avatarDir, 0755, true);
}

// Handle profile picture upload
if (isset($_POST['upload_profile_pic'])) {
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $fileName = $_FILES['profile_pic']['name'];
        $fileTmp = $_FILES['profile_pic']['tmp_name'];
        $fileSize = $_FILES['profile_pic']['size'];
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed)) {
            $error = "Only JPG, PNG or GIF images are allowed for profile picture.";
        } elseif ($fileSize > 2 * 1024 * 1024) {
            $error = "Profile picture must be less than 2MB.";
        } else {
            $safeFileName = $user_id . "_" . time() . "_" . preg_replace("/[^a-zA-Z0-9.]/", "", $fileName);
            $uploadPath = $avatarDir . $safeFileName;

            // Try to create a square thumbnail (200x200) using GD if available
            $processed = false;
            if (function_exists('getimagesize') && function_exists('imagecreatetruecolor')) {
                $info = getimagesize($fileTmp);
                if ($info !== false) {
                    $srcWidth = $info[0];
                    $srcHeight = $info[1];
                    $mime = $info['mime'];

                    switch ($mime) {
                        case 'image/jpeg':
                            $srcImg = imagecreatefromjpeg($fileTmp);
                            break;
                        case 'image/png':
                            $srcImg = imagecreatefrompng($fileTmp);
                            break;
                        case 'image/gif':
                            $srcImg = imagecreatefromgif($fileTmp);
                            break;
                        default:
                            $srcImg = false;
                    }

                    if ($srcImg) {
                        $minSide = min($srcWidth, $srcHeight);
                        $srcX = intval(($srcWidth - $minSide) / 2);
                        $srcY = intval(($srcHeight - $minSide) / 2);

                        $dstSize = 200;
                        $dstImg = imagecreatetruecolor($dstSize, $dstSize);

                        // Preserve transparency for PNG/GIF
                        if ($mime === 'image/png' || $mime === 'image/gif') {
                            imagecolortransparent($dstImg, imagecolorallocatealpha($dstImg, 0, 0, 0, 127));
                            imagealphablending($dstImg, false);
                            imagesavealpha($dstImg, true);
                        }

                        imagecopyresampled($dstImg, $srcImg, 0, 0, $srcX, $srcY, $dstSize, $dstSize, $minSide, $minSide);

                        // Save processed image
                        switch ($ext) {
                            case 'jpg':
                            case 'jpeg':
                                imagejpeg($dstImg, $uploadPath, 90);
                                break;
                            case 'png':
                                imagepng($dstImg, $uploadPath);
                                break;
                            case 'gif':
                                imagegif($dstImg, $uploadPath);
                                break;
                        }

                        imagedestroy($srcImg);
                        imagedestroy($dstImg);
                        $processed = true;
                    }
                }
            }

            if (!$processed) {
                // Fallback to simple move
                if (!move_uploaded_file($fileTmp, $uploadPath)) {
                    $error = "Upload failed. Please try again.";
                }
            }

            if (empty($error)) {
                try {
                    $update = $conn->prepare("UPDATE users SET profile_pic = ? WHERE id = ?");
                    $update->execute([$safeFileName, $user_id]);
                } catch (PDOException $e) {
                    // Try to add column then retry
                    try {
                        $conn->exec("ALTER TABLE users ADD COLUMN profile_pic VARCHAR(255) NULL");
                        $update = $conn->prepare("UPDATE users SET profile_pic = ? WHERE id = ?");
                        $update->execute([$safeFileName, $user_id]);
                    } catch (PDOException $e) {
                        $error = "Failed to save profile picture (DB).";
                    }
                }
            }

            if (empty($error)) {
                $_SESSION['user']['profile_pic'] = $safeFileName;
                $user['profile_pic'] = $safeFileName;
                $message = "Profile picture uploaded successfully.";
            }
        }
    } else {
        $error = "Please select an image file.";
    }
}

// Check whether avatar and resume files actually exist
$avatarPath = __DIR__ . "/../assets/uploads/avatars/" . ($user['profile_pic'] ?? '');
$avatarExists = !empty($user['profile_pic']) && file_exists($avatarPath);
$resumePath = __DIR__ . "/../assets/uploads/resumes/" . ($user['resume'] ?? '');
$resumeExists = !empty($user['resume']) && file_exists($resumePath);

$requireProfilePic = !$avatarExists;

/* ==============================
   UPDATE PROFILE INFO
============================== */
if (isset($_POST['update_profile'])) {

    $name = $_POST['full_name'];
    $email = $_POST['email'];

    if (empty($name) || empty($email)) {
        $error = "All fields are required.";
    } else {

        $stmt = $conn->prepare("UPDATE users SET full_name=?, email=? WHERE id=?");
        $stmt->execute([$name, $email, $user_id]);

        $_SESSION['user']['full_name'] = $name;
        $_SESSION['user']['email'] = $email;

        $message = "Profile updated successfully.";
    }
}

/* ==============================
   CHANGE PASSWORD
============================== */
if (isset($_POST['change_password'])) {

    $current = $_POST['current_password'];
    $new = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];

    if (!password_verify($current, $user['password'])) {
        $error = "Current password is incorrect.";
    } elseif ($new !== $confirm) {
        $error = "New passwords do not match.";
    } else {

        $hashed = password_hash($new, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("UPDATE users SET password=? WHERE id=?");
        $stmt->execute([$hashed, $user_id]);

        $message = "Password changed successfully.";
    }
}

/* ==============================
   UPLOAD / UPDATE RESUME
============================== */
if (isset($_POST['upload_resume'])) {

    if (isset($_FILES['resume']) && $_FILES['resume']['error'] === 0) {

        $allowed = ['pdf', 'doc', 'docx'];
        $fileName = $_FILES['resume']['name'];
        $fileTmp = $_FILES['resume']['tmp_name'];
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed)) {
            $error = "Only PDF, DOC, DOCX files allowed.";
        } else {

            // Delete old resume if exists
            if ($user['resume']) {
                $oldPath = "../assets/uploads/resumes/" . $user['resume'];
                if (file_exists($oldPath)) {
                    unlink($oldPath);
                }
            }

            $newFileName = time() . "_" . $fileName;
            $uploadPath = "../assets/uploads/resumes/" . $newFileName;

            move_uploaded_file($fileTmp, $uploadPath);

            $stmt = $conn->prepare("UPDATE users SET resume=? WHERE id=?");
            $stmt->execute([$newFileName, $user_id]);

            $_SESSION['user']['resume'] = $newFileName;

            $message = "Resume uploaded successfully.";
        }

    } else {
        $error = "Please select a file.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Profile</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&family=Sora:wght@500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --ink: #0c1a24;
            --muted: #506675;
            --brand-a: #2563eb;
            --brand-b: #0ea5e9;
            --surface: rgba(255, 255, 255, 0.94);
            --line: rgba(20, 44, 69, 0.12);
            --shadow: 0 18px 44px rgba(4, 16, 26, 0.14);
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            min-height: 100vh;
            font-family: "Manrope", "Segoe UI", sans-serif;
            color: var(--ink);
            background:
                linear-gradient(rgba(245, 250, 255, 0.88), rgba(245, 250, 255, 0.88)),
                url("../images/student.png") no-repeat center center / cover fixed;
            padding: 24px 0;
        }

        .container { width: min(1140px, 92%); margin: 0 auto; }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 12px 16px;
            box-shadow: var(--shadow);
        }

        .title h1 { font-family: "Sora", "Segoe UI", sans-serif; font-size: 1.35rem; margin-bottom: 4px; }
        .title p { color: var(--muted); font-size: 0.9rem; }

        .menu-dropdown {
            position: relative;
        }

        .menu-toggle {
            border: 1px solid #cfe3ff;
            background: #eff6ff;
            color: #14426d;
            font-weight: 700;
            font-size: 0.9rem;
            border-radius: 999px;
            padding: 8px 12px;
            cursor: pointer;
        }

        .menu-list {
            display: none;
            position: absolute;
            right: 0;
            top: calc(100% + 8px);
            min-width: 170px;
            background: #ffffff;
            border: 1px solid #d9e7f7;
            border-radius: 10px;
            box-shadow: 0 10px 24px rgba(12, 26, 36, 0.14);
            overflow: hidden;
            z-index: 40;
        }

        .menu-dropdown.open .menu-list {
            display: block;
        }

        .menu-list a {
            display: block;
            text-decoration: none;
            color: #14426d;
            font-weight: 700;
            font-size: 0.9rem;
            padding: 10px 12px;
            border-bottom: 1px solid #eef4fc;
            background: #ffffff;
        }

        .menu-list a:last-child {
            border-bottom: none;
        }

        .menu-list a:hover {
            background: #eff6ff;
        }

        .alert {
            border-radius: 12px;
            padding: 12px 14px;
            margin-bottom: 12px;
            border: 1px solid transparent;
        }
        .alert-ok { color: #065f46; background: #ecfdf5; border-color: #a7f3d0; }
        .alert-err { color: #991b1b; background: #fff1f2; border-color: #fecaca; }

        .grid {
            display: grid;
            grid-template-columns: 280px 1fr 260px;
            gap: 12px;
        }

        .card {
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 16px;
            box-shadow: var(--shadow);
        }

        .card h2, .card h3 { font-family: "Sora", "Segoe UI", sans-serif; margin-bottom: 10px; }

        .avatar-wrap {
            width: 140px;
            height: 140px;
            border-radius: 50%;
            overflow: hidden;
            margin: 0 auto 12px;
            border: 3px solid #dbeafe;
        }
        .avatar-wrap img { width: 100%; height: 100%; object-fit: cover; }

        .center { text-align: center; }
        .meta { color: var(--muted); font-size: 0.92rem; text-align: center; margin-bottom: 4px; }

        .warn {
            margin-top: 10px;
            font-weight: 700;
            color: #9a3412;
            background: #fff7ed;
            border: 1px solid #fed7aa;
            border-radius: 10px;
            padding: 8px;
            text-align: center;
        }

        .field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }

        label {
            display: block;
            font-size: 0.85rem;
            color: #1f3948;
            font-weight: 700;
            margin-bottom: 5px;
        }

        input[type="text"], input[type="email"], input[type="password"], input[type="file"] {
            width: 100%;
            border: 1px solid #cfe0ef;
            border-radius: 10px;
            padding: 10px 11px;
            font: inherit;
            background: #fff;
            margin-bottom: 10px;
        }

        .btn {
            display: inline-block;
            border: 0;
            cursor: pointer;
            text-decoration: none;
            font-weight: 700;
            font-size: 0.9rem;
            padding: 9px 12px;
            border-radius: 10px;
        }
        .primary { color: #fff; background: linear-gradient(140deg, var(--brand-a), var(--brand-b)); }
        .ghost { color: #14426d; background: #eff6ff; border: 1px solid #cfe3ff; }

        .actions { display: flex; justify-content: flex-end; gap: 8px; }

        .side-meta { color: #223f52; font-size: 0.93rem; margin-bottom: 8px; }
        .side-meta strong { color: #102a44; }

        .stack { display: grid; gap: 8px; margin-top: 10px; }

        @media (max-width: 1080px) {
            .grid { grid-template-columns: 1fr; }
            .field-row { grid-template-columns: 1fr; }
            .actions { justify-content: flex-start; }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="topbar">
        <div class="title">
            <h1>My Profile</h1>
            <p>Manage your account, avatar, password, and resume</p>
        </div>
        <div class="menu-dropdown" id="menuDropdown">
            <button type="button" class="menu-toggle" id="menuToggle">Menu</button>
            <div class="menu-list" id="menuList">
                <a href="dashboard.php">Dashboard</a>
                <a href="profile.php">Refresh</a>
                <a href="logout.php">Logout</a>
            </div>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-ok"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-err"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <div class="grid">
        <div class="card">
            <div class="avatar-wrap">
                <?php if ($avatarExists): ?>
                    <img src="../assets/uploads/avatars/<?php echo htmlspecialchars($user['profile_pic']); ?>" alt="Avatar">
                <?php else: ?>
                    <img src="../images/avatar-placeholder.svg" alt="No avatar">
                <?php endif; ?>
            </div>
            <h3 class="center"><?php echo htmlspecialchars($user['full_name'] ?? ''); ?></h3>
            <div class="meta"><?php echo htmlspecialchars($user['email'] ?? ''); ?></div>

            <?php if ($requireProfilePic): ?>
                <div class="warn">Please upload a profile picture</div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" style="margin-top:12px;">
                <label>Choose Image</label>
                <input type="file" name="profile_pic" accept="image/*">
                <div style="margin-top:8px;display:flex;gap:8px;justify-content:center;">
                    <button type="submit" name="upload_profile_pic" class="btn primary">Upload Photo</button>
                    <a href="profile.php" class="btn ghost">Cancel</a>
                </div>
            </form>
        </div>

        <div class="card">
            <h2>Update Profile</h2>
            <form method="POST">
                <div class="field-row">
                    <div>
                        <label>Full Name</label>
                        <input type="text" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                    </div>
                    <div>
                        <label>Email</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                    </div>
                </div>

                <div class="actions">
                    <button type="submit" name="update_profile" class="btn primary">Save Profile</button>
                </div>
            </form>

            <div style="margin-top:18px;">
                <h3>Change Password</h3>
                <form method="POST">
                    <label>Current Password</label>
                    <input type="password" name="current_password" required>

                    <div style="display:flex;gap:12px;margin-top:10px;">
                        <div style="flex:1;">
                            <label>New Password</label>
                            <input type="password" name="new_password" required>
                        </div>
                        <div style="flex:1;">
                            <label>Confirm New Password</label>
                            <input type="password" name="confirm_password" required>
                        </div>
                    </div>

                    <div class="actions">
                        <button type="submit" name="change_password" class="btn primary">Change Password</button>
                    </div>
                </form>
            </div>

            <div style="margin-top:18px;">
                <h3>Resume</h3>
                <?php if ($resumeExists): ?>
                    <p>Current Resume: <a href="../assets/uploads/resumes/<?php echo htmlspecialchars($user['resume']); ?>" target="_blank">View</a></p>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data">
                    <label>Upload New Resume (PDF, DOC, DOCX)</label>
                    <input type="file" name="resume">
                    <div class="actions">
                        <button type="submit" name="upload_resume" class="btn primary">Upload Resume</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <h3>Profile Summary</h3>
            <div class="side-meta">Department: <strong><?php echo htmlspecialchars($user['department_name'] ?? '-'); ?></strong></div>
            <div class="side-meta">Resume: <strong><?php echo $resumeExists ? 'Uploaded' : 'Not uploaded'; ?></strong></div>
            <div class="side-meta">Avatar: <strong><?php echo $avatarExists ? 'Uploaded' : 'Missing'; ?></strong></div>

            <div style="margin-top:14px;">
                <h3>Actions</h3>
                <div class="stack">
                    <a href="upload_resume.php" class="btn ghost">Upload Resume</a>
                    <a href="profile.php" class="btn primary">Refresh</a>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="../assets/js/logout-popup.js"></script>
<script>
    (function () {
        var dropdown = document.getElementById('menuDropdown');
        var toggle = document.getElementById('menuToggle');
        if (!dropdown || !toggle) return;

        toggle.addEventListener('click', function () {
            dropdown.classList.toggle('open');
        });

        document.addEventListener('click', function (e) {
            if (!dropdown.contains(e.target)) {
                dropdown.classList.remove('open');
            }
        });
    })();
</script>
</body>
</html>
