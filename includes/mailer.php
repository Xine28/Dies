<?php

require __DIR__ . '/../PHPMailer-master/src/Exception.php';
require __DIR__ . '/../PHPMailer-master/src/PHPMailer.php';
require __DIR__ . '/../PHPMailer-master/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendDepartmentAssignmentEmail($toEmail, $studentName, $departmentName, $supervisorName)
{
    $mail = new PHPMailer(true);

    try {

        /* ================= SMTP SETTINGS ================= */
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'xinefajardo1@gmail.com';
        $mail->Password   = 'odorwpggdhtrjhpj'; // ⚠ Replace immediately
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('xinefajardo1@gmail.com', 'DIES - Internship System');
        $mail->addAddress($toEmail, $studentName);

        $mail->isHTML(true);
        $mail->Subject = "Internship Department Assignment - DIES";

        /* ================= EMBED LOGO ================= */
        $logoPath = realpath(__DIR__ . '/../images/logo.png');

        if ($logoPath) {
            $mail->addEmbeddedImage($logoPath, 'dieslogo', 'logo.png');
        }

        /* ================= DEPARTMENT COLOR ================= */
        $deptColor = "#333333"; // default

        switch (strtolower($departmentName)) {
            case "it department":
                $deptColor = "#16a34a"; // green
                break;

            case "marketing department":
                $deptColor = "#2563eb"; // blue
                break;

            case "inventory department":
                $deptColor = "#7c3aed"; // purple
                break;
        }

        /* ================= EMAIL BODY ================= */
        $mail->Body = '
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
</head>

<body style="margin:0;padding:0;background-color:#f3f4f6;font-family:Arial,Helvetica,sans-serif;">

<table width="100%" cellpadding="0" cellspacing="0" style="padding:40px 0;">
<tr>
<td align="center">

<table width="650" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:15px;overflow:hidden;box-shadow:0 5px 25px rgba(0,0,0,0.08);">

<!-- HEADER -->
<tr>
<td align="center" style="background:#2c3e8f;padding:45px 20px;">

    <table cellpadding="0" cellspacing="0" width="150" height="150" 
           style="background:#ffffff;border-radius:75px;border:6px solid #0f172a;">
        <tr>
            <td align="center" valign="middle">
                 <img src="cid:dieslogo" width="250"
                     style="display:block;margin:0 auto;">
            </td>
        </tr>
    </table>

    <h2 style="color:#ffffff;margin-top:25px;font-weight:600;">
        Digital Internship Evaluation System
    </h2>

</td>
</tr>

<!-- BODY -->
<tr>
<td style="padding:40px 45px;color:#333333;">

<p style="font-size:16px;margin:0 0 15px 0;">
Good day <strong>' . htmlspecialchars($studentName) . '</strong>,
</p>

<p style="font-size:15px;line-height:1.7;margin:0 0 25px 0;">
We are pleased to inform you that you have officially been assigned to the following department:
</p>

<table width="100%" cellpadding="0" cellspacing="0" style="background:#f2f3f5;border-radius:10px;margin:20px 0 30px 0;">
<tr>
<td style="padding:25px;font-size:15px;line-height:1.8;">
<strong>Department:</strong>
<span style="color:' . $deptColor . ';font-weight:bold;">
' . htmlspecialchars($departmentName) . '
</span><br><br>
<strong>Supervisor:</strong> ' . htmlspecialchars($supervisorName) . '
</td>
</tr>
</table>

<p style="font-size:15px;line-height:1.7;margin-bottom:25px;">
Please log in to your DIES dashboard to review further details and internship instructions.
</p>

<p style="margin-top:35px;font-size:15px;">
Best Regards,<br>
<strong>DIES Administration</strong>
</p>

</td>
</tr>

<!-- FOOTER -->
<tr>
<td align="center" style="background:#0f172a;color:#cbd5e1;padding:25px;font-size:12px;">
© ' . date("Y") . ' Digital Internship Evaluation System<br>
This is an automated email. Please do not reply.
</td>
</tr>

</table>

</td>
</tr>
</table>

</body>
</html>
';

        $mail->AltBody = "Hello $studentName,\n\nYou have been assigned to $departmentName under $supervisorName.\n\nPlease log in to your DIES dashboard for more details.";

        $mail->send();
        return true;

    } catch (Exception $e) {
        return false;
    }
}

function sendEvaluationSubmittedEmail($toEmail, $studentName, $supervisorName, $score, $evalDate)
{
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'xinefajardo1@gmail.com';
        $mail->Password   = 'odorwpggdhtrjhpj';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('xinefajardo1@gmail.com', 'DIES - Internship System');
        $mail->addAddress($toEmail, $studentName);

        $mail->isHTML(true);
        $mail->Subject = "Evaluation Submitted - DIES";

        $logoPath = realpath(__DIR__ . '/../images/logo.png');
        if ($logoPath) {
            $mail->addEmbeddedImage($logoPath, 'dieslogo', 'logo.png');
        }

        $result = ((int) $score <= 50) ? "Failed" : "Passed";
        $evalDateText = date("M d, Y", strtotime($evalDate));

        $mail->Body = '
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
</head>
<body style="margin:0;padding:0;background-color:#f3f4f6;font-family:Arial,Helvetica,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="padding:40px 0;">
<tr><td align="center">
<table width="650" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:15px;overflow:hidden;box-shadow:0 5px 25px rgba(0,0,0,0.08);">
<tr>
<td align="center" style="background:#2c3e8f;padding:35px 20px;">
    <img src="cid:dieslogo" width="90" style="display:block;margin:0 auto 14px auto;">
    <h2 style="color:#ffffff;margin:0;font-weight:600;">Digital Internship Evaluation System</h2>
</td>
</tr>
<tr>
<td style="padding:34px 40px;color:#333333;">
<p style="font-size:16px;margin:0 0 14px 0;">Good day <strong>' . htmlspecialchars($studentName) . '</strong>,</p>
<p style="font-size:15px;line-height:1.7;margin:0 0 20px 0;">Your supervisor has submitted your internship evaluation.</p>

<table width="100%" cellpadding="0" cellspacing="0" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;margin:8px 0 22px 0;">
<tr><td style="padding:18px;font-size:15px;line-height:1.9;">
<strong>Supervisor:</strong> ' . htmlspecialchars($supervisorName) . '<br>
<strong>Evaluation Date:</strong> ' . htmlspecialchars($evalDateText) . '<br>
<strong>Overall Score:</strong> ' . htmlspecialchars((string) $score) . '%<br>
<strong>Result:</strong> ' . htmlspecialchars($result) . '
</td></tr>
</table>

<p style="font-size:15px;line-height:1.7;margin:0 0 14px 0;">You can now log in to your student dashboard to view full evaluation details and download your PDF report.</p>

<p style="margin-top:26px;font-size:15px;">Best Regards,<br><strong>DIES Administration</strong></p>
</td>
</tr>
<tr>
<td align="center" style="background:#0f172a;color:#cbd5e1;padding:22px;font-size:12px;">
© ' . date("Y") . ' Digital Internship Evaluation System<br>
This is an automated email. Please do not reply.
</td>
</tr>
</table>
</td></tr>
</table>
</body>
</html>
';

        $mail->AltBody = "Hello $studentName,\n\nYour supervisor has submitted your evaluation.\nSupervisor: $supervisorName\nDate: $evalDateText\nScore: $score%\nResult: $result\n\nPlease log in to your student dashboard to view details.";

        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}

function sendBossEvaluationNotification($toEmail, $bossName, $studentName, $supervisorName, $score, $evalDate, $departmentName = '', $comments = '')
{
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'xinefajardo1@gmail.com';
        $mail->Password   = 'odorwpggdhtrjhpj';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('xinefajardo1@gmail.com', 'DIES - Internship System');
        $mail->addAddress($toEmail, $bossName);

        $mail->isHTML(true);
        $mail->Subject = "Supervisor Evaluation Submitted - DIES";

        $result = ((int) $score <= 50) ? "Failed" : "Passed";
        $evalDateText = date("M d, Y", strtotime($evalDate));
        $safeComments = trim($comments) === '' ? 'No comments provided.' : htmlspecialchars($comments);

        $mail->Body = '
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="padding:32px 0;">
<tr><td align="center">
<table width="620" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e5e7eb;">
<tr><td style="background:#0f172a;color:#ffffff;padding:18px 22px;">
<h2 style="margin:0;font-size:20px;">New Evaluation Submitted</h2>
</td></tr>
<tr><td style="padding:24px 22px;color:#111827;font-size:14px;line-height:1.7;">
<p style="margin:0 0 12px 0;">Hello <strong>' . htmlspecialchars($bossName) . '</strong>,</p>
<p style="margin:0 0 14px 0;">A supervisor submitted an intern evaluation with the following details:</p>
<table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e5e7eb;border-radius:8px;">
<tr><td style="padding:12px 14px;">
<strong>Intern:</strong> ' . htmlspecialchars($studentName) . '<br>
<strong>Supervisor:</strong> ' . htmlspecialchars($supervisorName) . '<br>
<strong>Department:</strong> ' . htmlspecialchars($departmentName !== '' ? $departmentName : 'N/A') . '<br>
<strong>Evaluation Date:</strong> ' . htmlspecialchars($evalDateText) . '<br>
<strong>Score:</strong> ' . htmlspecialchars((string) $score) . '% (' . htmlspecialchars($result) . ')<br>
<strong>Comments:</strong> ' . $safeComments . '
</td></tr>
</table>
<p style="margin:14px 0 0 0;">This is an automated message from DIES.</p>
</td></tr>
</table>
</td></tr>
</table>
</body>
</html>
';

        $mail->AltBody = "Hello $bossName,\n\nA supervisor submitted an intern evaluation.\nIntern: $studentName\nSupervisor: $supervisorName\nDepartment: " . ($departmentName !== '' ? $departmentName : 'N/A') . "\nDate: $evalDateText\nScore: $score% ($result)\nComments: " . (trim($comments) === '' ? 'No comments provided.' : $comments);

        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}

function sendTimeOutReminderEmail($toEmail, $studentName, $cutoffTime = '4:59 PM')
{
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'xinefajardo1@gmail.com';
        $mail->Password   = 'odorwpggdhtrjhpj';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('xinefajardo1@gmail.com', 'DIES - Internship System');
        $mail->addAddress($toEmail, $studentName);

        $mail->isHTML(true);
        $mail->Subject = "Reminder: Time Out Before $cutoffTime";

        $mail->Body = '
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="padding:30px 0;">
<tr><td align="center">
<table width="620" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e5e7eb;">
<tr><td style="background:#0f172a;color:#ffffff;padding:18px 22px;">
<h2 style="margin:0;font-size:20px;">Time Out Reminder</h2>
</td></tr>
<tr><td style="padding:22px;color:#111827;font-size:14px;line-height:1.7;">
<p style="margin:0 0 12px 0;">Hello <strong>' . htmlspecialchars($studentName) . '</strong>,</p>
<p style="margin:0 0 12px 0;">This is a reminder that your time out window is about to close.</p>
<p style="margin:0 0 12px 0;"><strong>Please submit your Time Out before ' . htmlspecialchars($cutoffTime) . ' (PH time).</strong></p>
<p style="margin:0;">This is an automated email from DIES.</p>
</td></tr>
</table>
</td></tr>
</table>
</body>
</html>
';

        $mail->AltBody = "Hello $studentName,\n\nYour time out window is about to close. Please submit your Time Out before $cutoffTime (PH time).";

        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}
?>
