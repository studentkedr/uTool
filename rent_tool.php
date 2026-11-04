<?php
include 'db.php';
session_start();

// Require login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Validate tool ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: user.php?section=browse");
    exit();
}

$tool_id = (int) $_GET['id'];

// Fetch tool and owner details
$stmt = mysqli_prepare($conn, "
    SELECT t.*, u.name AS owner_name
    FROM tools t
    JOIN users u ON t.owner_id = u.id
    WHERE t.id = ?
");

mysqli_stmt_bind_param($stmt, "i", $tool_id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$tool = mysqli_fetch_assoc($result);

if (!$tool) {
    die("Tool not found.");
}

// Prevent users from renting their own tools
if ($tool['owner_id'] == $user_id) {
    die("You cannot rent your own tool.");
}

// Check tool availability
if ($tool['status'] != 'Available') {
    die("This tool is currently unavailable.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirm Rental - <?php echo htmlspecialchars($tool['tool_name']); ?></title>
    <link rel="icon" type="image/jpg" href="uT.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        :root {
            --primary: #2563eb;
            --success: #10b981;
            --dark: #1e293b;
            --light-bg: #f8fafc;
            --text-main: #334155;
            --text-muted: #64748b;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--light-bg);
            color: var(--text-main);
            margin: 0;
            padding: 40px 20px;
            display: flex;
            justify-content: center;
        }

        .confirm-container {
            max-width: 900px;
            width: 100%;
            background: white;
            display: grid;
            grid-template-columns: 1fr 1.2fr;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }

        /* Image Side */
        .image-section {
            background: #f1f5f9;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px;
            position: relative;
        }

        .image-section img {
            max-width: 100%;
            height: auto;
            border-radius: 12px;
            filter: drop-shadow(0 10px 15px rgba(0,0,0,0.1));
        }

        /* Content Side */
        .details-section {
            padding: 50px;
        }

        h2 {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--dark);
            margin: 0 0 8px 0;
        }

        .owner-info {
            font-size: 0.95rem;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 24px;
        }

        .description-box {
            background: #f8fafc;
            border-left: 4px solid var(--primary);
            padding: 20px;
            border-radius: 0 12px 12px 0;
            font-size: 0.95rem;
            line-height: 1.6;
            margin-bottom: 30px;
        }

        .price-info {
            display: flex;
            gap: 20px;
            margin-bottom: 30px;
        }

        .price-card {
            flex: 1;
            background: white;
            border: 1px solid #e2e8f0;
            padding: 15px;
            border-radius: 12px;
            text-align: center;
        }

        .price-card span {
            display: block;
            font-size: 0.8rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 5px;
        }

        .price-card strong {
            font-size: 1.2rem;
            color: var(--primary);
        }

        /* Form Styling */
        label {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 8px;
        }

        input[type="date"] {
            width: 100%;
            padding: 12px;
            border: 1.5px solid #e2e8f0;
            border-radius: 10px;
            font-family: inherit;
            margin-bottom: 20px;
            box-sizing: border-box;
            transition: border-color 0.2s;
        }

        input[type="date"]:focus {
            outline: none;
            border-color: var(--primary);
        }

        .btn-confirm {
            width: 100%;
            background: var(--primary);
            color: white;
            border: none;
            padding: 16px;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.2);
        }

        .btn-confirm:hover {
            background: #1d4ed8;
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(37, 99, 235, 0.3);
        }

        .btn-cancel {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.9rem;
            transition: color 0.2s;
        }

        .btn-cancel:hover {
            color: #ef4444;
        }

        @media (max-width: 768px) {
            .confirm-container { grid-template-columns: 1fr; }
            .details-section { padding: 30px; }
        }
    </style>
</head>
<body>

<div class="confirm-container">
    <div class="image-section">
        <img src="<?php echo $tool['tool_image']; ?>" alt="Tool Image">
    </div>

    <div class="details-section">
        <h2>Rent <?php echo htmlspecialchars($tool['tool_name']); ?></h2>
        <div class="owner-info">
            <i class="fa-solid fa-circle-user"></i>
            <span>Owned by <strong><?php echo htmlspecialchars($tool['owner_name']); ?></strong></span>
        </div>
        
        <div class="description-box">
            <?php echo nl2br(htmlspecialchars($tool['description'] ?? 'No additional details provided.')); ?>
        </div>

        <div class="price-info">
            <div class="price-card">
                <span>Daily Rate</span>
                <strong>₱<?php echo number_format($tool['price_per_day']); ?></strong>
            </div>
            <div class="price-card">
                <span>Security Deposit</span>
                <strong>₱<?php echo number_format($tool['security_deposit']); ?></strong>
            </div>
        </div>

        <form action="process_rental.php" method="POST">
            <input type="hidden" name="tool_id" value="<?php echo $tool['id']; ?>">
            <input type="hidden" name="owner_id" value="<?php echo $tool['owner_id']; ?>">

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div>
                    <label><i class="fa-regular fa-calendar-plus"></i> Pickup Date</label>
                    <input type="date" name="start_date" required min="<?php echo date('Y-m-d'); ?>">
                </div>
                <div>
                    <label><i class="fa-regular fa-calendar-check"></i> Return Date</label>
                    <input type="date" name="end_date" required min="<?php echo date('Y-m-d'); ?>">
                </div>
            </div>

            <button type="submit" name="confirm_rent_btn" class="btn-confirm">
                Confirm Rental Request
            </button>
            
            <a href="user.php?section=browse" class="btn-cancel">
                <i class="fa-solid fa-arrow-left"></i> Back to Browse
            </a>
        </form>
    </div>
</div>


</body>
</html>