<?php
include "../includes/auth.php";
checkRole('boss', '../boss/login.php');

include "../config/database.php";

// Search functionality
$search = "";
if (isset($_GET['search'])) {
    $search = $_GET['search'];
    $stmt = $conn->prepare("
        SELECT users.*, departments.department_name
        FROM users
        LEFT JOIN departments ON users.department_id = departments.id
        WHERE users.role = 'student'
        AND (users.full_name LIKE ? OR users.email LIKE ?)
    ");
    $stmt->execute(["%$search%", "%$search%"]);
} else {
    $stmt = $conn->query("
        SELECT users.*, departments.department_name
        FROM users
        LEFT JOIN departments ON users.department_id = departments.id
        WHERE users.role = 'student'
    ");
}

$students = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Students</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>

<h2>Manage Interns - Boss Panel</h2>

<form method="GET">
    <input type="text" name="search" placeholder="Search name or email" value="<?php echo $search; ?>">
    <button type="submit">Search</button>
</form>

<br>

<table border="1" cellpadding="10" cellspacing="0" width="100%">
    <tr style="background:#f2f2f2;">
        <th>Name</th>
        <th>Email</th>
        <th>Resume</th>
        <th>Department</th>
        <th>Status</th>
        <th>Action</th>
    </tr>

    <?php if (count($students) > 0): ?>
        <?php foreach ($students as $student): ?>
            <tr>
                <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                <td><?php echo htmlspecialchars($student['email']); ?></td>

                <td>
                    <?php if ($student['resume']) { ?>
                        <a href="../assets/uploads/resumes/<?php echo $student['resume']; ?>" target="_blank">
                            View Resume
                        </a>
                    <?php } else { ?>
                        No Resume
                    <?php } ?>
                </td>

                <td>
                    <?php echo $student['department_name'] ? $student['department_name'] : "Not Assigned"; ?>
                </td>

                <td>
                    <?php if ($student['department_id']) { ?>
                        <span style="color:green;">Assigned</span>
                    <?php } else { ?>
                        <span style="color:red;">Pending</span>
                    <?php } ?>
                </td>

                <td>
                    <a href="assign_department.php?id=<?php echo $student['id']; ?>">Assign</a>
                    |
                    <button class="edit-btn" 
                        data-id="<?php echo $student['id']; ?>"
                        data-name="<?php echo htmlspecialchars($student['full_name']); ?>"
                        data-email="<?php echo htmlspecialchars($student['email']); ?>">
                        Edit
                    </button>
                    |
                    <button class="delete-btn" data-id="<?php echo $student['id']; ?>">
                        Delete
                    </button>
                </td>
            </tr>
        <?php endforeach; ?>
    <?php else: ?>
        <tr>
            <td colspan="6" align="center">No students found.</td>
        </tr>
    <?php endif; ?>
</table>

<!-- Simple Edit Modal -->
<div id="editModal" style="display:none; background:#eee; padding:20px; margin-top:20px;">
    <h3>Edit Student</h3>
    <form id="editForm">
        <input type="hidden" id="edit_id">
        Name:<br>
        <input type="text" id="edit_name"><br><br>
        Email:<br>
        <input type="email" id="edit_email"><br><br>
        <button type="submit">Update</button>
        <button type="button" id="closeModal">Cancel</button>
    </form>
</div>

<script>

// Open Edit Modal
$(document).on("click", ".edit-btn", function(){
    $("#edit_id").val($(this).data("id"));
    $("#edit_name").val($(this).data("name"));
    $("#edit_email").val($(this).data("email"));
    $("#editModal").show();
});

// Close Modal
$("#closeModal").click(function(){
    $("#editModal").hide();
});

// Update Student (AJAX)
$("#editForm").submit(function(e){
    e.preventDefault();

    $.ajax({
        url: "../api/users.php",
        method: "POST",
        data: {
            edit_id: $("#edit_id").val(),
            name: $("#edit_name").val(),
            email: $("#edit_email").val()
        },
        success: function(response){
            alert("Student updated successfully!");
            location.reload();
        }
    });
});

// Delete Student
$(document).on("click", ".delete-btn", function(){

    let id = $(this).data("id");

    if(confirm("Are you sure you want to delete this student?")){
        $.ajax({
            url: "../api/users.php",
            method: "POST",
            data: { delete_id: id },
            success: function(response){
                alert("Student deleted successfully!");
                location.reload();
            }
        });
    }

});

</script>

</body>
</html>