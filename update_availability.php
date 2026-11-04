<?php
// update_availability.php
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $tool_id = mysqli_real_escape_string($conn, $_POST['tool_id']);

    // Determine the new status based on whether the checkbox was checked
    // If checked -> Available. If unchecked -> Under Repair.
    $new_status = isset($_POST['status_toggle']) ? 'Available' : 'Under Repair';

    // Safety check: Don't allow toggling if the tool is currently 'Rented'
    $check = mysqli_query($conn, "SELECT status FROM tools WHERE id = '$tool_id'");
    $row = mysqli_fetch_assoc($check);

    if (
        $row &&
        !in_array($row['status'], ['Rented', 'Reserved'])
    ) {
        $update = mysqli_query($conn, "
        UPDATE tools
        SET status = '$new_status'
        WHERE id = '$tool_id'
    ");
    }

    header("Location: user.php?section=tools&update=success");
    exit();
}
