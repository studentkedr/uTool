
<?php
include 'db.php';
session_start();

// 1. Connection Safety Check
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

// 2. Check User Session
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// 3. Process Add Tool Form
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $user_id = $_SESSION['user_id'];

    $tool_name = mysqli_real_escape_string($conn, $_POST['tool_name']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $price = mysqli_real_escape_string($conn, $_POST['price']);
    $deposit = mysqli_real_escape_string($conn, $_POST['deposit']);

    $image_path = "uploads/default.png";

    // 4. Handle Image Upload
    if (isset($_FILES['tool_image']) && $_FILES['tool_image']['error'] == 0) {

        // Create uploads folder if it does not exist
        if (!is_dir('uploads')) {
            mkdir('uploads', 0777, true);
        }

        $file_name = time() . "_" . basename($_FILES["tool_image"]["name"]);
        $target_file = "uploads/" . $file_name;

        if (move_uploaded_file($_FILES["tool_image"]["tmp_name"], $target_file)) {
            $image_path = $target_file;
        }
    }

    // 5. Insert Tool into Database
    $query = "INSERT INTO tools
              (owner_id, tool_name, description, price_per_day,
               security_deposit, tool_image, status)
              VALUES
              ('$user_id', '$tool_name', '$description', '$price',
               '$deposit', '$image_path', 'Available')";

    $query_run = mysqli_query($conn, $query);

    // 6. Handle Result
    if ($query_run) {

        $_SESSION['success_msg'] = "Tool added successfully!";

        // Redirect to dashboard to display updated statistics
        header("Location: user.php?section=dashboard");
        exit();

    } else {

        $_SESSION['error_msg'] = "Database Error: " . mysqli_error($conn);

        header("Location: user.php?section=tools&action=add");
        exit();
    }

} else {

    // Prevent direct access without form submission
    header("Location: user.php?section=tools");
    exit();
}
?>
