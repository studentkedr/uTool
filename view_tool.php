<?php
include 'db.php';
session_start();

if (!isset($_GET['id'])) {
    header("Location: user.php?section=tools");
    exit();
}

$tool_id = mysqli_real_escape_string($conn, $_GET['id']);
$query = mysqli_query($conn, "SELECT * FROM tools WHERE id = '$tool_id'");
$tool = mysqli_fetch_assoc($query);

if (!$tool) { echo "Tool not found."; exit(); }
?>

<!DOCTYPE html>
<html>
<head>
    <title>View Tool - <?php echo $tool['tool_name']; ?></title>
    <link rel="icon" type="image/jpg" href="uT.png">
    <link rel="stylesheet" href="style.css"> </head>
<body style="padding: 50px; font-family: sans-serif; background: #f4f7f6;">
    <div style="max-width: 700px; margin: auto; background: white; padding: 30px; border-radius: 15px; box-shadow: 0 5px 15px rgba(0,0,0,0.1);">
        <a href="user.php?section=tools" style="text-decoration: none; color: #3498db;">← Back to My Tools</a>
        <hr style="margin: 20px 0; border: 0; border-top: 1px solid #eee;">
        
        <div style="display: flex; gap: 30px;">
            <img src="<?php echo $tool['tool_image']; ?>" style="width: 250px; height: 250px; object-fit: cover; border-radius: 10px;">
            <div>
                <h1 style="margin-top: 0;"><?php echo htmlspecialchars($tool['tool_name']); ?></h1>
                <span style="padding: 5px 10px; border-radius: 20px; background: #e3fcef; color: #27ae60; font-size: 0.8rem;"><?php echo $tool['status']; ?></span>
                
                <h2 style="color: #2c3e50; margin-top: 20px;">₱<?php echo number_format($tool['price_per_day']); ?> <small style="font-size: 0.9rem; color: #7f8c8d;">/ day</small></h2>
                <p><strong>Security Deposit:</strong> ₱<?php echo number_format($tool['security_deposit']); ?></p>
                <p style="color: #7f8c8d; line-height: 1.6;"><?php echo nl2br(htmlspecialchars($tool['description'])); ?></p>
            </div>
        </div>
    </div>
</body>
</html>