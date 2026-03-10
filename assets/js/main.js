/* ==========================================
   DIES - Digital Internship Evaluation System
   Main JavaScript File
========================================== */

$(document).ready(function(){

    /* ===============================
       DELETE STUDENT (AJAX)
    =============================== */
    $(document).on("click", ".delete-btn", function(){

        let studentId = $(this).data("id");

        if(confirm("Are you sure you want to delete this student?")){

            $.ajax({
                url: "../api/users.php",
                type: "POST",
                data: {
                    delete_id: studentId
                },
                success: function(response){
                    alert("Student deleted successfully!");
                    location.reload();
                },
                error: function(){
                    alert("Error deleting student.");
                }
            });

        }

    });


    /* ===============================
       OPEN EDIT MODAL
    =============================== */
    $(document).on("click", ".edit-btn", function(){

        $("#edit_id").val($(this).data("id"));
        $("#edit_name").val($(this).data("name"));
        $("#edit_email").val($(this).data("email"));

        $("#editModal").fadeIn();
    });


    /* ===============================
       CLOSE EDIT MODAL
    =============================== */
    $(document).on("click", "#closeModal", function(){
        $("#editModal").fadeOut();
    });


    /* ===============================
       UPDATE STUDENT (AJAX)
    =============================== */
    $("#editForm").submit(function(e){

        e.preventDefault();

        let id = $("#edit_id").val();
        let name = $("#edit_name").val();
        let email = $("#edit_email").val();

        if(name === "" || email === ""){
            alert("All fields are required.");
            return;
        }

        $.ajax({
            url: "../api/users.php",
            type: "POST",
            data: {
                edit_id: id,
                name: name,
                email: email
            },
            success: function(response){
                alert("Student updated successfully!");
                $("#editModal").fadeOut();
                location.reload();
            },
            error: function(){
                alert("Error updating student.");
            }
        });

    });


    /* ===============================
       CONFIRM DEPARTMENT ASSIGNMENT
    =============================== */
    $(document).on("submit", "form", function(){

        if($(this).find("button[name='assign']").length){
            return confirm("Are you sure you want to assign this department?");
        }

    });


});