<?php
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $rental_id = $_POST['rental_id'];
    $notes = mysqli_real_escape_string($conn, $_POST['post_notes']);
    $today = date('Y-m-d');

    // 1. Insert into reports
    $sql = "INSERT INTO reports (rental_id, post_notes, post_date, status) 
            VALUES ('$rental_id', '$notes', '$today', 'Pending Review')";
    
    if (mysqli_query($conn, $sql)) {
        // 2. Update the rental status
        mysqli_query($conn, "UPDATE rentals SET status = 'Returned' WHERE id = '$rental_id'");
        
        // 3. Redirect back to dashboard
        header("Location: user.php?section=rentals&msg=ReportFiled");
    }
}
?>