<?php
include 'db.php';
session_start();

// 1. Security Check: Is the user logged in?
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if (isset($_GET['id'])) {
    $tool_id = mysqli_real_escape_string($conn, $_GET['id']);
    $user_id = $_SESSION['user_id'];

    // 2. Fetch the image path first so we can delete the file from the folder
    $file_query = mysqli_query($conn, "SELECT tool_image FROM tools WHERE id = '$tool_id' AND owner_id = '$user_id'");
    $tool_data = mysqli_fetch_assoc($file_query);

    if ($tool_data) {
        // Delete the physical file if it's not the default image
        if (!empty($tool_data['tool_image']) && $tool_data['tool_image'] != 'uploads/default.png') {
            if (file_exists($tool_data['tool_image'])) {
                unlink($tool_data['tool_image']);
            }
        }

        // 3. Delete the record from the database
        $delete_query = "DELETE FROM tools WHERE id = '$tool_id' AND owner_id = '$user_id'";
        
        if (mysqli_query($conn, $delete_query)) {
            $_SESSION['success_msg'] = "Tool deleted successfully.";
        } else {
            $_SESSION['error_msg'] = "Failed to delete tool.";
        }
    } else {
        $_SESSION['error_msg'] = "Tool not found or unauthorized.";
    }
}

// 4. Redirect back to the tools section
header("Location: user.php?section=tools");
exit();
?>