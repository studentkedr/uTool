<?php
include 'db.php';
session_start();

if (isset($_POST['update_tool_btn'])) {
    $tool_id = mysqli_real_escape_string($conn, $_POST['tool_id']);
    $user_id = $_SESSION['user_id'];
    $tool_name = mysqli_real_escape_string($conn, $_POST['tool_name']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $price = mysqli_real_escape_string($conn, $_POST['price']);
    $deposit = mysqli_real_escape_string($conn, $_POST['deposit']);

    // Check if a new image is being uploaded
    if (isset($_FILES['tool_image']) && $_FILES['tool_image']['error'] == 0) {
        $file_name = time() . "_" . basename($_FILES["tool_image"]["name"]);
        $target_file = "uploads/" . $file_name;
        
        if (move_uploaded_file($_FILES["tool_image"]["tmp_name"], $target_file)) {
            // Update with new image
            $update_query = "UPDATE tools SET 
                tool_name = '$tool_name', 
                description = '$description', 
                price_per_day = '$price', 
                security_deposit = '$deposit', 
                tool_image = '$target_file' 
                WHERE id = '$tool_id' AND owner_id = '$user_id'";
        }
    } else {
        // Update without changing the image
        $update_query = "UPDATE tools SET 
            tool_name = '$tool_name', 
            description = '$description', 
            price_per_day = '$price', 
            security_deposit = '$deposit' 
            WHERE id = '$tool_id' AND owner_id = '$user_id'";
    }

    if (mysqli_query($conn, $update_query)) {
        $_SESSION['success_msg'] = "Tool updated successfully!";
        header("Location: user.php?section=tools");
    } else {
        $_SESSION['error_msg'] = "Update failed: " . mysqli_error($conn);
        header("Location: edit_tool.php?id=" . $tool_id);
    }
    exit();
}