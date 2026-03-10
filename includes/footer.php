<?php
// Make sure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$year = date("Y");
$role = isset($_SESSION['user']['role']) ? ucfirst($_SESSION['user']['role']) : "Guest";
?>

    <footer style="
        margin-top:50px;
        padding:20px;
        background:#1f2937;
        color:white;
        text-align:center;
        font-size:14px;
    ">
        <div>
            <strong>DIES</strong> - Digital Internship Evaluation System
        </div>

        <div style="margin-top:5px;">
            Logged in as: <b><?php echo $role; ?></b>
        </div>

        <div style="margin-top:5px;">
            © <?php echo $year; ?> All Rights Reserved
        </div>
    </footer>

    <!-- Optional: Bootstrap (for better UI styling) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>