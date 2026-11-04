<?php
include 'db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

if (isset($_GET['id'])) {
    $tool_id = mysqli_real_escape_string($conn, $_GET['id']);
    // Fetch tool and ensure it belongs to the logged-in user
    $query = mysqli_query($conn, "SELECT * FROM tools WHERE id = '$tool_id' AND owner_id = '$user_id'");
    $tool = mysqli_fetch_assoc($query);

    if (!$tool) {
        die("Tool not found or you don't have permission to edit it.");
    }
} else {
    header("Location: user.php?section=tools");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Edit Tool | uTool</title>
    <link rel="icon" type="image/jpg" href="uT.png">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #3498db;
            --dark-color: #2c3e50;
            --bg-color: #f4f7f6;
            --border-color: #e0e0e0;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--bg-color);
            color: var(--dark-color);
            margin: 0;
            padding: 40px 20px;
        }

        .edit-container {
            max-width: 650px;
            margin: auto;
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
        }

        .form-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .form-header h2 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 700;
        }

        .btn-back {
            text-decoration: none;
            color: #7f8c8d;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: color 0.3s;
        }

        .btn-back:hover {
            color: var(--primary-color);
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 8px;
            color: #555;
        }

        input[type="text"],
        input[type="number"],
        textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-family: inherit;
            font-size: 0.95rem;
            box-sizing: border-box;
            transition: border-color 0.3s;
        }

        input:focus,
        textarea:focus {
            outline: none;
            border-color: var(--primary-color);
        }

        .image-preview-section {
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
            margin-bottom: 25px;
        }

        .current-img {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 8px;
            border: 2px solid white;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .file-input-wrapper {
            flex: 1;
        }

        .btn-save {
            width: 100%;
            background: var(--dark-color);
            color: white;
            border: none;
            padding: 15px;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
        }

        .btn-save:hover {
            background: #34495e;
        }

        .price-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
    </style>
</head>

<body>

    <div class="edit-container">
        <div class="form-header">
            <h2>Edit Tool Details</h2>
            <a href="user.php?section=tools" class="btn-back">
                <i class="fa-solid fa-arrow-left"></i> Back
            </a>
        </div>

        <form action="process_edit_tool.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="tool_id" value="<?php echo $tool['id']; ?>">

            <div class="form-group">
                <label>Tool Name</label>
                <input type="text" name="tool_name" value="<?php echo htmlspecialchars($tool['tool_name']); ?>" required>
            </div>

            <div class="form-group">
                <label>Description</label>
                <textarea name="description" rows="4" required><?php echo htmlspecialchars($tool['description']); ?></textarea>
            </div>

            <div class="price-row">
                <div class="form-group">
                    <label>Price per Day (₱)</label>
                    <input type="number" name="price" value="<?php echo $tool['price_per_day']; ?>" required>
                </div>
                <div class="form-group">
                    <label>Security Deposit (₱)</label>
                    <input type="number" name="deposit" value="<?php echo $tool['security_deposit']; ?>" required>
                </div>
            </div>

            <label>Tool Image</label>
            <div class="image-preview-section">
                <?php if (!empty($tool['tool_image'])): ?>
                    <img src="<?php echo $tool['tool_image']; ?>" class="current-img" alt="Current">
                <?php else: ?>
                    <div class="current-img" style="display:flex; align-items:center; justify-content:center; background:#ddd;">
                        <i class="fa-solid fa-image" style="color:#aaa;"></i>
                    </div>
                <?php endif; ?>

                <div class="file-input-wrapper">
                    <span style="font-size: 0.75rem; color: #7f8c8d; display: block; margin-bottom: 5px;">Upload new to replace</span>
                    <input type="file" name="tool_image" accept="image/*" style="font-size: 0.85rem;">
                </div>
            </div>

            <button type="submit" name="update_tool_btn" class="btn-save">
                <i class="fa-solid fa-check"></i> Save Changes
            </button>
        </form>
    </div>

</body>

</html>