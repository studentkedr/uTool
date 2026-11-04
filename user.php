<?php
// 1. INITIALIZATION & CONNECTION
// 1. INITIALIZATION & CONNECTION
include 'db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$user_id = $_SESSION['user_id'];

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Fetch ALL necessary user data once at the start
$user_query = mysqli_query($conn, "SELECT name, email, phone, created_at FROM users WHERE id = '$user_id'");
$user_data = mysqli_fetch_assoc($user_query);
$user_display_name = $user_data['name'] ?? 'User';

// 2. HELPER FUNCTIONS
function mysqli_real_escape_with_like($conn, $str)
{
    return mysqli_real_escape_string($conn, str_replace(['%', '_'], ['\%', '\_'], $str));
}

// 3. SET SECTION
$section = isset($_GET['section']) ? $_GET['section'] : 'dashboard';

// 4. (DELETE YOUR OLD SECTION 4 COMPLETELY - It is redundant now)

// 5. DASHBOARD STATS

// Count Active Rentals (Approved only)
$res_active = mysqli_query($conn, "
    SELECT COUNT(*) as count
    FROM rentals
    WHERE renter_id = '$user_id'
    AND status = 'Approved'
");

$active_rentals = mysqli_fetch_assoc($res_active)['count'] ?? 0;


// Count Tools You Listed
$res_listed = mysqli_query($conn, "
    SELECT COUNT(*) as count
    FROM tools
    WHERE owner_id = '$user_id'
");
$total_listed = mysqli_fetch_assoc($res_listed)['count'] ?? 0;

// Held Deposits
$res_deposits = mysqli_query($conn, "
    SELECT SUM(t.security_deposit) as total
    FROM rentals r
    JOIN tools t ON r.tool_id = t.id
    WHERE r.renter_id = '$user_id'
    AND r.status != 'Returned'
");
$held_deposits = mysqli_fetch_assoc($res_deposits)['total'] ?? 0;


// 6. BROWSE / FILTER LOGIC
$search = $_GET['search'] ?? '';
$price_range = $_GET['price_range'] ?? 'all';
$avail_only = isset($_GET['avail_only']) && $_GET['avail_only'] == '1';

// Combined Query: Gets tools, owner name, and checks if currently rented
$sql = "SELECT t.*, u.name as owner_name,
        (SELECT COUNT(*) FROM rentals r
         WHERE r.tool_id = t.id
         AND r.status != 'Returned') as is_rented
        FROM tools t
        JOIN users u ON t.owner_id = u.id
        WHERE t.owner_id != '$user_id'";

// Apply Search Filter
if (!empty($search)) {
    $sql .= " AND t.tool_name LIKE '%" .
        mysqli_real_escape_with_like($conn, $search) . "%'";
}

// Apply Price Range Filters
if ($price_range == '500') {
    $sql .= " AND t.price_per_day < 500";
} elseif ($price_range == '1000') {
    $sql .= " AND t.price_per_day BETWEEN 500 AND 1000";
} elseif ($price_range == 'over') {
    $sql .= " AND t.price_per_day > 1000";
}

// Apply Available Only Filter
if ($avail_only) {
    $sql .= " AND t.status = 'Available'
              AND NOT EXISTS (
                  SELECT 1 FROM rentals r
                  WHERE r.tool_id = t.id
                  AND r.status != 'Returned'
              )";
}

// Execute Query AFTER applying all filters
$browse_result = mysqli_query($conn, $sql);


?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>uTool - User Dashboard</title>
    <link rel="icon" type="image/jpg" href="uT.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --primary: #fbc02d;
            --primary-light: #fff9c4;
            --dark: #2d3436;
            --grey: #636e72;
            --bg: #f4f7f6;
            --white: #ffffff;
            --shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background-color: var(--bg);
            color: var(--dark);
        }

        input[type="radio"] {
            display: none;
        }

        /* --- Sidebar --- */
        #sidebar {
            position: fixed;
            width: 260px;
            height: 100vh;
            background: var(--white);
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.03);
            padding: 10px 0;
            z-index: 100;
        }

        .logo-area {
            text-align: center;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
            margin-bottom: 10px;
        }

        .logo-area p {
            font-size: 28px;
            font-weight: 800;
            color: var(--dark);
        }

        .logo-area span {
            color: var(--primary);
        }

        .sidebar-menu {
            list-style: none;
        }

        .sidebar-menu li {
            width: 100%;
            margin-bottom: 3px;
        }

        .sidebar-menu label {
            display: flex;
            align-items: center;
            padding: 10px 20px;
            font-weight: 600;
            color: var(--grey);
            cursor: pointer;
            transition: 0.3s;
            border-left: 4px solid transparent;
        }

        .sidebar-menu label i {
            margin-right: 15px;
            width: 20px;
        }

        .sidebar-menu label:hover {
            background: var(--primary-light);
            color: var(--dark);
        }

        .menu-header {
            padding: 15px 20px 5px 20px;
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            color: #b2bec3;
            font-weight: 800;
        }

        /* Style for the current active page */
        /* .sidebar-menu a.active {
            background: var(--primary-light);
            color: var(--primary);
            border-left: 4px solid var(--primary);
        } */

        /* Active Tab Logic */
        #dashboard:checked~#sidebar label[for="dashboard"],
        #browse:checked~#sidebar label[for="browse"],
        #rentals:checked~#sidebar label[for="rentals"],
        #tools:checked~#sidebar label[for="tools"],
        #lending:checked~#sidebar label[for="lending"],
        #deposits:checked~#sidebar label[for="deposits"],
        #profile:checked~#sidebar label[for="profile"] {
            background: var(--primary-light);
            color: var(--dark);
            border-left-color: var(--primary);
        }

        .logout {
            /* position: absolute;
            bottom: 20px;
            width: 100%;
            padding: 0 40px; */
            margin-top: 1rem;
            margin-left: 30px;
            color: #d63031;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
        }



        /* --- Main Content --- */
        .content {
            margin-left: 260px;
            padding: 40px;
        }

        /* Hide all sections in the main content area */
        .section {
            display: none;
        }


        #dashboard:checked~.content #section-dashboard,
        #browse:checked~.content #section-browse,
        #rentals:checked~.content #section-rentals,
        #tools:checked~.content #section-tools,
        #lending:checked~.content #section-lending,
        #deposits:checked~.content #section-deposits,
        #profile:checked~.content #section-profile {
            display: block;
        }

        .header-title h1 {
            font-size: 24px;
            margin-bottom: 5px;
        }

        .header-title p {
            color: var(--grey);
            font-size: 0.9rem;
            margin-bottom: 30px;
        }

        /* --- Stats Grid --- */
        .stats-grid {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
            flex-grow: 1;
            align-content: center;
        }

        .stat-card {
            background: var(--white);
            padding: 25px;
            width: 250px;
            border-radius: 15px;
            box-shadow: var(--shadow);
            border: 1px solid rgba(0, 0, 0, 0.02);
            /* transition: 0.3s; */
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }

        /* .stat-card:hover { transform: translateY(-5px); } */
        .stat-card h3 {
            font-size: 28px;
            color: var(--dark);
        }

        .stat-card p {
            color: var(--grey);
            font-size: 0.85rem;
            font-weight: 600;
        }

        /* --- Tables --- */
        .table-container {
            background: var(--white);
            border-radius: 15px;
            padding: 20px;
            box-shadow: var(--shadow);
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            text-align: left;
            padding: 15px;
            border-bottom: 2px solid #f1f1f1;
            color: var(--grey);
            font-size: 0.85rem;
        }

        td {
            padding: 15px;
            border-bottom: 1px solid #f1f1f1;
            font-size: 0.9rem;
        }

        /* --- Browse Tools Grid --- */
        .filter-bar {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
            background: var(--white);
            padding: 20px;
            border-radius: 12px;
            box-shadow: var(--shadow);
            align-items: center;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .filter-group label {
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--grey);
            text-transform: uppercase;
        }

        .filter-input {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            outline: none;
            font-size: 0.9rem;
        }

        .tool-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 25px;
        }

        .tool-card {
            background: var(--white);
            border-radius: 15px;
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: transform 0.2s;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }

        .tool-card:hover {
            transform: translateY(-5px);
        }

        .tool-image {
            width: 100%;
            height: 180px;
            /* Adjust height to match your design */
            background-color: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .tool-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            /* This prevents the image from being stretched or squished */
        }

        .tool-info {
            padding: 20px;
        }

        .tool-info h4 {
            font-size: 1.1rem;
            margin-bottom: 5px;
        }

        .owner-tag {
            font-size: 0.8rem;
            color: var(--grey);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .price-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }

        .price-tag {
            font-weight: 700;
            color: var(--dark);
        }

        .price-tag span {
            font-size: 1.2rem;
            color: var(--primary);
        }

        .btn-rent {
            width: 100%;
            background: var(--primary);
            color: var(--dark);
            border: none;
            padding: 12px;
            border-radius: 10px;
            font-weight: 700;
            margin-top: 15px;
            cursor: pointer;
            transition: 0.2s;
        }

        .btn-rent:hover {
            background: #f1b000;
        }

        /* --- My Rentals Specific Styles --- */
        .action-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 14px;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 600;
            text-decoration: none;
            transition: 0.2s;
            cursor: pointer;
            border: none;
        }

        .btn-submit-return {
            background-color: var(--primary);
            color: var(--dark);
        }

        .btn-submit-return:hover {
            background-color: #f1b000;
        }

        .btn-view-report {
            background-color: #f1f3f5;
            color: var(--grey);
        }

        .btn-view-report:hover {
            background-color: #e9ecef;
            color: var(--dark);
        }

        /* Status Specific Colors */
        .status-ongoing {
            background-color: #e3f2fd;
            color: #1976d2;
        }

        .status-returned {
            background-color: #e8f5e9;
            color: #2e7d32;
        }

        .status-late {
            background-color: #ffebee;
            color: #c62828;
        }

        .badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        /* --- My Tools Management --- */
        .header-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .btn-add {
            background: var(--dark);
            color: var(--white);
            padding: 12px 20px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: 0.3s;
        }

        .btn-add:hover {
            background: #000;
            transform: translateY(-2px);
        }

        /* Status Colors for My Tools */
        .status-available {
            background-color: #e8f5e9;
            color: #2e7d32;
        }

        /* Green */
        .status-rented {
            background-color: #e3f2fd;
            color: #1976d2;
        }

        /* Blue */
        .status-repair {
            background-color: #fff3e0;
            color: #e67e22;
        }

        /* Orange */

        /* Toggle Switch Styling */
        .switch {
            position: relative;
            display: inline-block;
            width: 40px;
            height: 20px;
        }

        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 14px;
            width: 14px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked+.slider {
            background-color: #2196F3;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 14px;
            width: 14px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked+.slider:before {
            transform: translateX(20px);
        }

        /* --- Deposit Status Colors --- */
        .status-held {
            background-color: #fff9c4;
            color: #f57f17;
        }

        /* Yellow/Orange */
        .status-released {
            background-color: #e8f5e9;
            color: #2e7d32;
        }

        /* Green */
        .status-deducted {
            background-color: #ffebee;
            color: #c62828;
        }


        /* Red */

        /* --- Condition Report UI --- */
        .report-comparison {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 15px;
        }

        .report-card {
            border: 1px solid #edf2f7;
            border-radius: 10px;
            padding: 15px;
            background: #f8fafc;
        }

        .report-card h5 {
            font-size: 0.75rem;
            color: var(--grey);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
        }

        .photo-placeholder {
            width: 100%;
            height: 140px;
            background: #e2e8f0;
            border-radius: 8px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #94a3b8;
            margin-bottom: 10px;
            border: 2px dashed #cbd5e1;
        }

        .report-text {
            font-size: 0.85rem;
            line-height: 1.4;
            color: var(--dark);
            font-style: italic;
        }

        /* --- Messages UI --- */
        .chat-container {
            display: grid;
            grid-template-columns: 300px 1fr;
            background: var(--white);
            height: 600px;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: var(--shadow);
        }

        .chat-sidebar {
            border-right: 1px solid #eee;
            overflow-y: auto;
        }

        .chat-user-item {
            padding: 15px;
            border-bottom: 1px solid #f9f9f9;
            cursor: pointer;
            transition: 0.2s;
        }

        .chat-user-item:hover {
            background: var(--primary-light);
        }

        .chat-user-item.active {
            background: var(--primary-light);
            border-left: 4px solid var(--primary);
        }

        .chat-main {
            display: flex;
            flex-direction: column;
            background: #fff;
        }

        .chat-header {
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
            font-weight: 700;
        }

        .chat-messages {
            flex-grow: 1;
            padding: 20px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .message-bubble {
            max-width: 70%;
            padding: 10px 15px;
            border-radius: 15px;
            font-size: 0.9rem;
        }

        .message-sent {
            align-self: flex-end;
            background: var(--primary);
            color: var(--dark);
            border-bottom-right-radius: 2px;
        }

        .message-received {
            align-self: flex-start;
            background: #f1f3f5;
            color: var(--dark);
            border-bottom-left-radius: 2px;
        }

        .chat-input-area {
            padding: 15px;
            border-top: 1px solid #eee;
            display: flex;
            gap: 10px;
        }

        /* --- Profile UI --- */
        .profile-card {
            background: var(--white);
            border-radius: 15px;
            padding: 40px;
            text-align: center;
            box-shadow: var(--shadow);
            max-width: 600px;
            margin: 0 auto;
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            background: var(--primary-light);
            color: var(--primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            margin: 0 auto 20px;
            border: 4px solid var(--white);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .rating-stars {
            color: #f1c40f;
            margin: 10px 0;
            font-size: 1.2rem;
        }









        /* 


        .message-action-card {
            background: #fff9e6;
            border: 2px dashed #f1c40f;
            padding: 20px;
            border-radius: 12px;
            margin: 15px auto;
            width: 90%;
            text-align: center;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
        }

        .btn-release {
            background: #27ae60;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 6px;
            font-weight: bold;
            display: inline-block;
            transition: background 0.2s;
        }

        .btn-release:hover {
            background: #219150;
        }

        .btn-issue {
            background: #e74c3c;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 6px;
            font-weight: bold;
            display: inline-block;
        }





        .report-box {
            margin-top: 10px;
            padding: 10px;
            background: #f9f9f9;
            border-radius: 6px;
            border: 1px solid #ddd;
        }

        .report-input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 0.85rem;
            resize: vertical;
            margin-bottom: 5px;
        }

        .btn-action {
            border: none;
            background: none;
            cursor: pointer;
            font-weight: 600;
            font-family: inherit;
        } */
    </style>
</head>

<body>

    <input type="radio" name="nav" id="dashboard" <?php if ($section == 'dashboard') echo 'checked'; ?>>
    <input type="radio" name="nav" id="browse" <?php if ($section == 'browse') echo 'checked'; ?>>
    <input type="radio" name="nav" id="rentals" <?php if ($section == 'rentals') echo 'checked'; ?>>
    <input type="radio" name="nav" id="tools" <?php if ($section == 'tools') echo 'checked'; ?>>
    <input type="radio" name="nav" id="lending" <?php if ($section == 'lending') echo 'checked'; ?>>
    <input type="radio" name="nav" id="deposits" <?php if ($section == 'deposits') echo 'checked'; ?>>
    <input type="radio" name="nav" id="profile" <?php if ($section == 'profile') echo 'checked'; ?>>

    <aside id="sidebar">
        <div class="logo-area">
            <p>u<span>Tool</span></p>
        </div>
        <ul class="sidebar-menu">
            <li class="menu-header">General</li>
            <li><label for="dashboard"><i class="fa-solid fa-chart-pie"></i> Dashboard</label></li>
            <li><label for="browse"><i class="fa-solid fa-magnifying-glass"></i> Browse Tools</label></li>

            <li class="menu-header">Your Activity</li>
            <li><label for="rentals"><i class="fa-solid fa-handshake-angle"></i> My Rentals</label></li>
            <li><label for="lending"><i class="fa-solid fa-hand-holding-dollar"></i> Lending</label></li>
            <li><label for="tools"><i class="fa-solid fa-toolbox"></i> My Tools</label></li>
            <li><label for="deposits"><i class="fa-solid fa-wallet"></i> Deposits</label></li>

            <li class="menu-header">Account</li>
            <li><label for="profile"><i class="fa-solid fa-user-gear"></i> Profile</label></li>
        </ul>

        <a href="logout.php" class="logout" style="display: block; text-decoration: none;">
            <i class="fa-solid fa-right-from-bracket"></i> Logout
        </a>
    </aside>

    <main class="content">


        <div class="section" id="section-dashboard">
            <div class="header-title">
                <h1>Welcome Back, <?php echo htmlspecialchars($user_display_name); ?></h1>
                <p>Here is what's happening with your tools today.</p>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <h3><?php echo $active_rentals; ?></h3>
                    <p>Active Rentals</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo $total_listed; ?></h3>
                    <p>Tools You Listed</p>
                </div>
                <div class="stat-card">
                    <h3>₱<?php echo number_format($held_deposits); ?></h3>
                    <p>Held Deposits</p>
                </div>
            </div>

            <div class="table-container">
                <h2 style="margin-bottom: 15px; font-size: 18px;">Notifications</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Event</th>
                            <th>Status</th>
                            <th>Item</th>
                            <th>Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Combined Notification Logic (Renter & Owner perspectives)
                        $notif_query = mysqli_query($conn, "
                -- 1. AS OWNER: New Rental Request (Pending Approval)
                (SELECT 'Rental Request' as event, 'Action Required' as status, t.tool_name as item, r.start_date as time 
                 FROM rentals r JOIN tools t ON r.tool_id = t.id 
                 WHERE r.owner_id = '$user_id' AND r.status = 'Pending')
                
                UNION
                
                -- 2. AS RENTER: Your request was approved
                (SELECT 'Request Approved' as event, 'Success' as status, t.tool_name as item, r.start_date as time 
                 FROM rentals r JOIN tools t ON r.tool_id = t.id 
                 WHERE r.renter_id = '$user_id' AND r.status = 'Approved')

                UNION

                -- 3. AS RENTER: Tool was successfully returned
                (SELECT 'Tool Returned' as event, 'Success' as status, t.tool_name as item, r.return_date as time 
                 FROM rentals r JOIN tools t ON r.tool_id = t.id 
                 WHERE r.renter_id = '$user_id' AND r.status = 'Returned')
                
                UNION
                
                -- 4. AS RENTER/OWNER: Overdue Tools (Late Return)
                (SELECT 'Late Return' as event, 'Warning' as status, t.tool_name as item, r.return_date as time 
                 FROM rentals r JOIN tools t ON r.tool_id = t.id 
                 WHERE (r.renter_id = '$user_id' OR r.owner_id = '$user_id') 
                 AND r.status = 'Approved' AND r.return_date < CURDATE())

                ORDER BY time DESC LIMIT 5
            ");

                        if ($notif_query && mysqli_num_rows($notif_query) > 0) {
                            while ($notif = mysqli_fetch_assoc($notif_query)) {
                                // Dynamic color assignment based on status string
                                $badge_color = '#7f8c8d'; // Default gray
                                $bg_color = '#f1f2f6';

                                if ($notif['status'] == 'Success') {
                                    $badge_color = '#27ae60';
                                    $bg_color = '#e3fcef';
                                } elseif ($notif['status'] == 'Warning' || $notif['status'] == 'Action Required') {
                                    $badge_color = '#d63031';
                                    $bg_color = '#ffebee';
                                }

                                echo "<tr>
                            <td>{$notif['event']}</td>
                            <td><span class='badge' style='background-color: $bg_color; color: $badge_color; padding: 4px 8px; border-radius: 4px; font-weight: bold;'>{$notif['status']}</span></td>
                            <td>" . htmlspecialchars($notif['item']) . "</td>
                            <td>" . date('M d, H:i', strtotime($notif['time'])) . "</td>
                          </tr>";
                            }
                        } else {
                            echo "<tr><td colspan='4' style='text-align:center;'>No new notifications.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>




        <div class="section" id="section-browse">
            <div class="header-title">
                <h1>Browse Available Tools</h1>
                <p>Rent high-quality tools from trusted owners in your area.</p>
            </div>

            <form method="GET" action="user.php#section-browse" class="filter-bar">
                <input type="hidden" name="section" value="browse">

                <div class="filter-group">
                    <label>Search</label>
                    <input type="text" name="search" class="filter-input" placeholder="Search tools..." value="<?php echo htmlspecialchars($search); ?>">
                </div>

                <div class="filter-group">
                    <label>Price Range</label>
                    <select name="price_range" class="filter-input">
                        <option value="all" <?php if ($price_range == 'all') echo 'selected'; ?>>All Prices</option>
                        <option value="500" <?php if ($price_range == '500') echo 'selected'; ?>>Under ₱500</option>
                        <option value="1000" <?php if ($price_range == '1000') echo 'selected'; ?>>₱500 - ₱1000</option>
                        <option value="over" <?php if ($price_range == 'over') echo 'selected'; ?>>Over ₱1000</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Status</label>
                    <div style="display: flex; align-items: center; gap: 8px; height: 38px;">
                        <input type="checkbox" name="avail_only" id="avail-only" value="1"
                            <?php if (!empty($avail_only)) echo 'checked'; ?>>
                        <span style="font-size: 0.9rem;">Available Only</span>
                    </div>
                </div>

                <div class="filter-group" style="display: flex; align-items: center; gap: 12px;">

                    <button type="submit"
                        style="background: var(--dark); color: white; border: none; padding: 10px 20px; border-radius: 6px; font-weight: 500; cursor: pointer; height: 40px; display: flex; align-items: center; font-family: inherit;">
                        Apply Filters
                    </button>

                    <?php if (!empty($search) || $price_range != 'all' || !empty($avail_only)): ?>
                        <a href="user.php?section=browse#section-browse"
                            style="text-decoration: none; color: #7f8c8d; font-size: 0.9rem; height: 40px; display: flex; align-items: center; padding: 0 10px;">
                            <i class="fa-solid fa-arrow-rotate-left" style="margin-right: 6px;"></i> Reset
                        </a>
                    <?php endif; ?>

                </div>
            </form>

            <div class="tool-grid">
                <?php if (mysqli_num_rows($browse_result) > 0): ?>
                    <?php while ($tool = mysqli_fetch_assoc($browse_result)):
                        // 1. CALCULATE AVAILABILITY
                        // Tool is unavailable if is_rented > 0 OR if its own status is not 'Available'
                        $is_unavailable = ($tool['is_rented'] > 0 || $tool['status'] !== 'Available');
                    ?>

                        <div class="tool-card">
                            <div class="tool-image" style="overflow: hidden; display: flex; align-items: center; justify-content: center; background-color: #f8f9fa; height: 180px;">
                                <?php if (!empty($tool['tool_image']) && file_exists($tool['tool_image'])): ?>
                                    <img src="<?php echo htmlspecialchars($tool['tool_image']); ?>"
                                        alt="<?php echo htmlspecialchars($tool['tool_name']); ?>"
                                        style="width: 100%; height: 100%; object-fit: cover;">
                                <?php else: ?>
                                    <i class="fa-solid fa-screwdriver-wrench" style="font-size: 2rem; color: #ccc;"></i>
                                <?php endif; ?>
                            </div>

                            <div class="tool-info">
                                <span class="badge <?php echo $is_unavailable ? 'status-rented' : 'badge-active'; ?>">
                                    <?php echo $is_unavailable ? 'Currently Rented' : 'Available'; ?>
                                </span>

                                <h4 style="margin-bottom: 5px;"><?php echo htmlspecialchars($tool['tool_name']); ?></h4>

                                <p class="tool-description" style="font-size: 0.85rem; color: #555; margin-bottom: 10px; line-height: 1.4; height: 2.8em; overflow: hidden; text-overflow: ellipsis; display: block;">
                                    <?php
                                    $desc = trim($tool['description'] ?? 'No description provided.');
                                    echo htmlspecialchars(mb_strimwidth($desc, 0, 80, "..."));
                                    ?>
                                </p>

                                <p class="owner-tag" style="margin-bottom: 10px;"><i class="fa-solid fa-user-circle"></i> Owner: <?php echo htmlspecialchars($tool['owner_name']); ?></p>

                                <div style="font-size: 0.85rem; color: #7f8c8d; border-top: 1px solid #eee; padding-top: 10px;">
                                    <p>Security Deposit: ₱<?php echo number_format($tool['security_deposit']); ?></p>
                                </div>

                                <div class="price-row">
                                    <div class="price-tag">₱<span><?php echo number_format($tool['price_per_day']); ?></span> / day</div>
                                </div>

                                <div style="margin-top: 10px;">
                                    <?php if ($is_unavailable): ?>
                                        <button class="btn-rent" disabled
                                            style="display: block; width: 100%; text-align: center; background: #dfe6e9; color: #b2bec3; padding: 10px; border: none; border-radius: 5px; font-weight: bold; cursor: not-allowed;">
                                            <i class="fa-solid fa-lock"></i> Unavailable
                                        </button>
                                    <?php else: ?>
                                        <a href="rent_tool.php?id=<?php echo $tool['id']; ?>" class="btn-rent"
                                            style="display: block; text-align: center; text-decoration: none; background: #3498db; color: white; padding: 10px; border-radius: 5px; font-weight: bold;">
                                            Rent Now
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p style="grid-column: 1/-1; text-align: center; padding: 50px;">No tools found matching your criteria.</p>
                <?php endif; ?>
            </div>
        </div>






        <div class="section" id="section-rentals">

            <div class="header-title">
                <h1>My Rentals</h1>
                <p>Manage the tools you are currently borrowing.</p>
            </div>

            <!-- SUCCESS MESSAGE -->
            <?php if (isset($_GET['msg']) && $_GET['msg'] === 'submitted'): ?>

                <div style="background-color:#d4edda; color:#155724; padding:15px; border-radius:8px; margin-bottom:20px; border:1px solid #c3e6cb;">

                    <i class="fa-solid fa-circle-check"></i>
                    <strong>Success!</strong>
                    Your return request has been submitted to the owner.

                </div>

            <?php endif; ?>


            <div class="table-container">

                <table>

                    <thead>
                        <tr>
                            <th>Tool & Owner</th>
                            <th>Rental Dates</th>
                            <th>Status</th>
                            <th>Payment</th>
                            <th>Actions</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php

                        $user_id = (int) $_SESSION['user_id'];

                        $rentals_stmt = mysqli_prepare($conn, "
                    SELECT
                        r.*,
                        t.tool_name,
                        t.price_per_day,
                        t.security_deposit,
                        u.name AS owner_name,
                        d.amount AS deposit_amount,
                        d.status AS deposit_status,
                        d.payment_method AS deposit_method

                    FROM rentals r

                    JOIN tools t ON r.tool_id = t.id
                    JOIN users u ON r.owner_id = u.id
                    LEFT JOIN deposits d ON r.id = d.rental_id

                    WHERE r.renter_id = ?

                    ORDER BY r.id DESC
                ");

                        mysqli_stmt_bind_param($rentals_stmt, "i", $user_id);
                        mysqli_stmt_execute($rentals_stmt);

                        $rentals_query = mysqli_stmt_get_result($rentals_stmt);

                        if (mysqli_num_rows($rentals_query) > 0):

                            while ($row = mysqli_fetch_assoc($rentals_query)):

                                // RENTAL STATUS
                                $status = trim($row['status'] ?? 'Pending');

                                // PAYMENT STATUS
                                $rental_payment_status = $row['rental_payment_status'] ?? 'Pending';
                                $deposit_status = $row['deposit_status'] ?? 'Pending';

                                $payment_method = $row['rental_payment_method'] ?? '';

                                // CHECK IF BOTH PAYMENTS ARE CONFIRMED
                                // Rental fee = Paid
                                // Security deposit = Held

                                $both_paid = (
                                    $rental_payment_status === 'Paid' &&
                                    $deposit_status === 'Held'
                                );

                                // DATE CALCULATIONS
                                $today = new DateTime('today');
                                $start_date = new DateTime($row['start_date']);
                                $return_date = new DateTime($row['end_date']);

                                $is_overdue = (
                                    $status === 'Picked Up' &&
                                    $today > $return_date
                                );

                                // STATUS STYLING
                                switch ($status) {

                                    case 'Approved':
                                    case 'Picked Up':
                                        $status_class = 'status-ongoing';
                                        break;

                                    case 'Returned':
                                    case 'Completed':
                                        $status_class = 'status-returned';
                                        break;

                                    case 'Declined':
                                        $status_class = 'status-declined';
                                        break;

                                    default:
                                        $status_class = 'status-pending';
                                        break;
                                }

                                if ($is_overdue) {
                                    $status_class = 'status-late';
                                }

                        ?>

                                <tr style="border-bottom:1px solid #eee;">

                                    <!-- TOOL AND OWNER -->
                                    <td style="padding:15px;">

                                        <div style="font-weight:700;">
                                            <?= htmlspecialchars($row['tool_name']); ?>
                                        </div>

                                        <div style="font-size:0.8rem; color:#7f8c8d;">
                                            Owner:
                                            <?= htmlspecialchars($row['owner_name']); ?>
                                        </div>

                                        <!-- CONDITION BEFORE RENTAL -->
                                        <?php if (!empty($row['pre_condition_notes'])): ?>

                                            <div style="font-size:0.75rem; color:#3498db; margin-top:4px;">

                                                <strong>Owner's Note:</strong>

                                                <?= htmlspecialchars($row['pre_condition_notes']); ?>

                                            </div>

                                        <?php endif; ?>

                                        <!-- CONDITION AFTER RENTAL -->
                                        <?php if (!empty($row['post_condition_notes'])): ?>

                                            <div style="font-size:0.75rem; color:#27ae60; margin-top:4px;">

                                                <strong>Return Note:</strong>

                                                <?= htmlspecialchars($row['post_condition_notes']); ?>

                                            </div>

                                        <?php endif; ?>

                                    </td>


                                    <!-- RENTAL DATES -->
                                    <td>

                                        <div style="font-size:0.9rem; <?= $is_overdue ? 'color:#e74c3c;font-weight:bold;' : ''; ?>">

                                            <?= $start_date->format('M d'); ?>
                                            -
                                            <?= $return_date->format('M d'); ?>

                                        </div>

                                        <div style="font-size:0.75rem; color:#7f8c8d;">

                                            <?php

                                            if ($status === 'Completed') {

                                                echo "Transaction Closed";
                                            } elseif ($status === 'Returned') {

                                                echo "Pending Owner Inspection";
                                            } elseif ($status === 'Picked Up') {

                                                if ($is_overdue) {

                                                    echo "OVERDUE";
                                                } else {

                                                    $diff = $today->diff($return_date);
                                                    echo $diff->days . " days left";
                                                }
                                            } elseif ($status === 'Approved') {

                                                echo "Approved by Owner";
                                            } elseif ($status === 'Pending') {

                                                echo "Waiting for Approval";
                                            } elseif ($status === 'Declined') {

                                                echo "Request Declined";
                                            }

                                            ?>

                                        </div>

                                    </td>


                                    <!-- RENTAL STATUS -->
                                    <td>

                                        <span class="badge <?= htmlspecialchars($status_class); ?>">

                                            <?= $is_overdue ? 'Late Return' : htmlspecialchars($status); ?>

                                        </span>

                                    </td>


                                    <!-- PAYMENT STATUS -->
                                    <td>

                                        <?php if ($status === 'Declined'): ?>

                                            <span style="color:#888;">
                                                Not Applicable
                                            </span>

                                        <?php else: ?>

                                            <div style="font-size:0.8rem;">

                                                <strong>Rental Fee:</strong>

                                                <?= htmlspecialchars($rental_payment_status); ?>

                                            </div>

                                            <div style="font-size:0.8rem;">

                                                <strong>Security Deposit:</strong>

                                                <?= htmlspecialchars($deposit_status); ?>

                                            </div>

                                            <?php if ($both_paid): ?>

                                                <span style="color:#27ae60; font-size:0.75rem;">

                                                    <i class="fa-solid fa-circle-check"></i>
                                                    Payment Confirmed

                                                </span>

                                            <?php elseif ($payment_method === 'Cash' && $status === 'Approved'): ?>

                                                <span style="color:#b7791f; font-size:0.75rem;">

                                                    Waiting for owner's cash confirmation.

                                                </span>

                                            <?php endif; ?>

                                        <?php endif; ?>

                                    </td>


                                    <!-- ACTIONS -->
                                    <td>

                                        <div style="display:flex; flex-direction:column; gap:10px;">

                                            <?php if ($status === 'Pending'): ?>

                                                <!-- WAITING FOR APPROVAL -->
                                                <button disabled
                                                    style="background:#ccc; color:#555; cursor:not-allowed; border:none; padding:8px; border-radius:4px;">

                                                    Awaiting Approval

                                                </button>


                                            <?php elseif ($status === 'Approved' && !$both_paid): ?>

                                                <!-- PAYMENT -->
                                                <a href="payment.php?id=<?= (int)$row['id']; ?>"
                                                    style="display:block; text-align:center; background:#32a8a8; color:white; text-decoration:none; padding:9px; border-radius:4px; font-weight:bold;">

                                                    <i class="fa-solid fa-credit-card"></i>

                                                    <?php

                                                    echo (
                                                        $payment_method === 'Cash' &&
                                                        $rental_payment_status === 'Pending'
                                                    )
                                                        ? 'View / Change Payment'
                                                        : 'Pay Now';

                                                    ?>

                                                </a>

                                                <?php if ($payment_method === 'Cash'): ?>

                                                    <small style="color:#b7791f;">
                                                        Waiting for owner's cash confirmation.
                                                    </small>

                                                <?php endif; ?>


                                            <?php elseif ($status === 'Approved' && $both_paid): ?>

                                                <!-- PAYMENT CONFIRMED -->
                                                <button disabled
                                                    style="background:#d4edda; color:#155724; border:none; padding:8px; border-radius:4px;">

                                                    <i class="fa-solid fa-check"></i>

                                                    Paid - Awaiting Pickup

                                                </button>


                                            <?php elseif ($status === 'Picked Up'): ?>

                                                <!-- SUBMIT TOOL RETURN -->
                                                <form action="manage_rental.php" method="POST">

                                                    <input type="hidden" name="id"
                                                        value="<?= (int)$row['id']; ?>">

                                                    <input type="hidden" name="action"
                                                        value="return">

                                                    <input type="hidden" name="csrf_token"
                                                        value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">

                                                    <textarea name="post_condition"
                                                        placeholder="Describe the tool's condition upon return..."
                                                        required
                                                        maxlength="2000"
                                                        style="width:100%; margin-bottom:5px; padding:8px; box-sizing:border-box;"></textarea>

                                                    <button type="submit"
                                                        onclick="return confirm('Are you sure you want to submit the tool return?');"
                                                        style="width:100%; background:#ffc107; border:none; padding:8px; border-radius:4px; font-weight:bold; cursor:pointer;">

                                                        Submit Return

                                                    </button>

                                                </form>


                                            <?php elseif ($status === 'Returned'): ?>

                                                <!-- WAITING FOR OWNER INSPECTION -->
                                                <button disabled
                                                    style="background:#eee; color:#777; border:none; padding:8px; border-radius:4px;">

                                                    Waiting for Owner

                                                </button>


                                            <?php elseif ($status === 'Completed'): ?>

                                                <!-- TRANSACTION COMPLETED -->
                                                <button disabled
                                                    style="background:#eee; color:#777; border:none; padding:8px; border-radius:4px;">

                                                    <i class="fa-solid fa-check-circle"></i>
                                                    Transaction Closed

                                                </button>


                                            <?php elseif ($status === 'Declined'): ?>

                                                <!-- REQUEST DECLINED -->
                                                <button disabled
                                                    style="background:#f8d7da; color:#721c24; border:none; padding:8px; border-radius:4px;">

                                                    Request Declined

                                                </button>

                                            <?php endif; ?>

                                        </div>

                                    </td>

                                </tr>

                            <?php

                            endwhile;

                        else:

                            ?>

                            <!-- NO RENTAL RECORDS -->
                            <tr>

                                <td colspan="5"
                                    style="text-align:center; padding:30px; color:#95a5a6;">

                                    No rental history found.

                                </td>

                            </tr>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>




        <div class="section" id="section-tools">

            <?php if (isset($_GET['action']) && $_GET['action'] == 'add'): ?>

                <div class="header-title">
                    <h1>Add New Tool</h1>
                    <p><a href="user.php?section=tools">← Back to My Tools</a></p>
                </div>

                <form action="process_add_tool.php" method="POST" enctype="multipart/form-data" class="filter-bar" style="flex-direction: column; align-items: flex-start; gap: 15px;">

                    <div class="filter-group" style="width: 100%;">
                        <label>Tool Name</label>
                        <input type="text" name="tool_name" class="filter-input" placeholder="e.g. Heavy Duty Power Drill" required>
                    </div>

                    <div class="filter-group" style="width: 100%;">
                        <label>Description</label>
                        <textarea name="description" class="filter-input" style="min-height: 100px; padding: 10px; resize: vertical;" placeholder="Describe the tool's condition, features, and any specific rental terms..."></textarea>
                    </div>

                    <div style="display: flex; gap: 20px; width: 100%;">
                        <div class="filter-group" style="flex: 1;">
                            <label>Price Per Day (₱)</label>
                            <input type="number" name="price" class="filter-input" placeholder="0.00" step="0.01" required>
                        </div>

                        <div class="filter-group" style="flex: 1;">
                            <label>Security Deposit (₱)</label>
                            <input type="number" name="deposit" class="filter-input" placeholder="Held until return" step="0.01" required>
                            <small style="color: #95a5a6;">This amount is held to cover potential damages or late fees.</small>
                        </div>
                    </div>

                    <div class="filter-group" style="width: 100%;">
                        <label>Tool Image</label>
                        <input type="file" name="tool_image" class="filter-input" accept="image/*" style="padding: 8px;">
                        <small style="color: #7f8c8d;">Supported formats: JPG, PNG. Max size: 2MB.</small>
                    </div>

                    <button type="submit" class="btn-rent" style="background-color: var(--dark); color: white;">
                        List Tool Now
                    </button>

                </form>

            <?php else: ?>

                <div class="header-actions">
                    <div class="header-title">
                        <h1>My Tools</h1>
                        <p>Manage and track the tools you've listed for rent.</p>
                    </div>

                    <a href="user.php?section=tools&action=add" class="btn-add">
                        <i class="fa-solid fa-plus"></i> Add New Tool
                    </a>
                </div>

                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Tool Details</th>
                                <th>Price / Day</th>
                                <th>Deposit</th>
                                <th>Status</th>
                                <th>Availability</th>
                                <th style="text-align: right;">Actions</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php
                            $my_tools_query = mysqli_query(
                                $conn,
                                "SELECT * FROM tools WHERE owner_id = '$user_id' ORDER BY id DESC"
                            );

                            if (mysqli_num_rows($my_tools_query) > 0) {

                                while ($tool = mysqli_fetch_assoc($my_tools_query)) {

                                    $status = $tool['status'];

                                    // Map status to CSS classes
                                    switch ($status) {

                                        case 'Reserved':
                                            $badge_class = 'status-reserved';
                                            break;

                                        case 'Rented':
                                            $badge_class = 'status-rented';
                                            break;

                                        case 'Under Repair':
                                            $badge_class = 'status-repair';
                                            break;

                                        default:
                                            $badge_class = 'status-available';
                                    }

                                    // Determine availability description
                                    if ($status === 'Reserved') {
                                        $availability_text = 'Reserved for Renter';
                                    } elseif ($status === 'Rented') {
                                        $availability_text = 'Currently Rented';
                                    } elseif ($status === 'Under Repair') {
                                        $availability_text = 'Maintenance';
                                    } else {
                                        $availability_text = 'Ready to Rent';
                                    }

                                    // Disable toggle when tool cannot be made available
                                    $disable_toggle = in_array(
                                        $status,
                                        ['Reserved', 'Rented', 'Under Repair'],
                                        true
                                    );

                            ?>

                                    <tr>

                                        <td style="display: flex; gap: 15px; align-items: center;">

                                            <div class="tool-img-preview" style="width: 50px; height: 50px; border-radius: 6px; overflow: hidden; background: #eee;">

                                                <img
                                                    src="<?php echo !empty($tool['tool_image']) ? htmlspecialchars($tool['tool_image']) : 'uploads/default.png'; ?>"
                                                    alt="Tool"
                                                    style="width: 100%; height: 100%; object-fit: cover;">

                                            </div>

                                            <div>
                                                <strong><?php echo htmlspecialchars($tool['tool_name']); ?></strong><br>

                                                <small style="color: #7f8c8d;">
                                                    ID: #<?php echo $tool['id']; ?>
                                                </small>
                                            </div>

                                        </td>

                                        <td>
                                            ₱<?php echo number_format($tool['price_per_day'], 2); ?>
                                        </td>

                                        <td>
                                            ₱<?php echo number_format($tool['security_deposit'], 2); ?>
                                        </td>

                                        <td>
                                            <span class="badge <?php echo $badge_class; ?>">
                                                <?php echo htmlspecialchars($status); ?>
                                            </span>
                                        </td>

                                        <td>

                                            <form action="update_availability.php" method="POST" style="margin: 0;">

                                                <input
                                                    type="hidden"
                                                    name="tool_id"
                                                    value="<?php echo $tool['id']; ?>">

                                                <label
                                                    class="switch"
                                                    title="<?php
                                                            echo $disable_toggle
                                                                ? 'Cannot toggle while ' . strtolower($status)
                                                                : 'Toggle Repair Status';
                                                            ?>">

                                                    <input
                                                        type="checkbox"
                                                        name="status_toggle"
                                                        onchange="this.form.submit()"

                                                        <?php echo ($status === 'Available') ? 'checked' : ''; ?>

                                                        <?php echo $disable_toggle ? 'disabled' : ''; ?>>

                                                    <span class="slider"></span>

                                                </label>

                                                <div style="font-size: 0.65rem; color: #7f8c8d; margin-top: 4px;">
                                                    <?php echo htmlspecialchars($availability_text); ?>
                                                </div>

                                            </form>

                                        </td>

                                        <td style="text-align: right;">

                                            <div style="display: flex; gap: 15px; justify-content: flex-end;">

                                                <a href="view_tool.php?id=<?php echo $tool['id']; ?>" title="View Details" style="color: #3498db;">
                                                    <i class="fa-solid fa-eye"></i>
                                                </a>

                                                <a href="edit_tool.php?id=<?php echo $tool['id']; ?>" title="Edit" style="color: #f39c12;">
                                                    <i class="fa-solid fa-pen"></i>
                                                </a>

                                                <a href="delete_tool.php?id=<?php echo $tool['id']; ?>" onclick="return confirm('Delete this tool?')" title="Delete" style="color: #e74c3c;">
                                                    <i class="fa-solid fa-trash"></i>
                                                </a>

                                            </div>

                                        </td>

                                    </tr>

                            <?php
                                }
                            } else {

                                echo "<tr><td colspan='6' style='text-align:center; padding: 20px;'>No tools listed yet.</td></tr>";
                            }
                            ?>
                        </tbody>

                    </table>
                </div>

            <?php endif; ?>

        </div>


        <div class="section" id="section-lending">

            <div class="header-title">
                <h1>Lending</h1>
                <p>Manage people renting your tools.</p>
            </div>

            <div class="table-container">

                <table style="width: 100%; border-collapse: collapse;">

                    <thead>
                        <tr style="text-align: left; border-bottom: 2px solid #eee;">
                            <th style="padding: 12px;">Tool</th>
                            <th>Renter</th>
                            <th>Rental Period</th>
                            <th>Status</th>
                            <th>Payment</th>
                            <th>Actions</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php

                        $owner_id = (int) $_SESSION['user_id'];

                        $stmt = mysqli_prepare($conn, "
                    SELECT
                        r.*,
                        t.tool_name,
                        u.name AS renter_name,
                        d.amount AS deposit_amount,
                        d.status AS deposit_status,
                        d.payment_method AS deposit_method

                    FROM rentals r

                    JOIN tools t ON r.tool_id = t.id
                    JOIN users u ON r.renter_id = u.id
                    LEFT JOIN deposits d ON r.id = d.rental_id

                    WHERE r.owner_id = ?

                    ORDER BY r.id DESC
                ");

                        mysqli_stmt_bind_param($stmt, "i", $owner_id);
                        mysqli_stmt_execute($stmt);

                        $lending_query = mysqli_stmt_get_result($stmt);

                        while ($row = mysqli_fetch_assoc($lending_query)) {

                            $status = trim($row['status'] ?? '');

                            $rental_payment = $row['rental_payment_status'] ?? 'Pending';
                            $deposit_payment = $row['deposit_status'] ?? 'Pending';

                            $payment_method = $row['rental_payment_method'] ?? '';
                            $deposit_method = $row['deposit_method'] ?? '';

                            // CHECK IF BOTH PAYMENTS ARE READY
                            $payments_ready = (
                                $rental_payment === 'Paid' &&
                                $deposit_payment === 'Held'
                            );

                            // CHECK IF BOTH PAYMENTS ARE CASH AND AWAITING CONFIRMATION
                            $is_cash_pending = (
                                $payment_method === 'Cash' &&
                                $deposit_method === 'Cash' &&
                                $rental_payment === 'Pending' &&
                                $deposit_payment === 'Pending'
                            );

                        ?>

                            <tr>

                                <!-- TOOL -->
                                <td style="padding: 12px;">
                                    <strong>
                                        <?= htmlspecialchars($row['tool_name']); ?>
                                    </strong>
                                </td>

                                <!-- RENTER -->
                                <td>
                                    <?= htmlspecialchars($row['renter_name']); ?>
                                </td>

                                <!-- RENTAL PERIOD -->
                                <td>
                                    <?= htmlspecialchars($row['start_date']); ?>
                                    to
                                    <?= htmlspecialchars($row['end_date']); ?>
                                </td>

                                <!-- RENTAL STATUS -->
                                <td>
                                    <span class="badge <?= strtolower(str_replace(' ', '-', $status)); ?>">
                                        <?= htmlspecialchars($status); ?>
                                    </span>
                                </td>

                                <!-- PAYMENT DETAILS -->
                                <td>
                                    <small>

                                        Rental:
                                        <?= htmlspecialchars($rental_payment); ?>

                                        <br>

                                        Deposit:
                                        <?= htmlspecialchars($deposit_payment); ?>

                                        <br>

                                        Method:
                                        <?= htmlspecialchars($payment_method ?: 'Not selected'); ?>

                                    </small>
                                </td>

                                <!-- ACTIONS -->
                                <td>

                                    <div style="display:flex; gap:5px; flex-wrap:wrap;">

                                        <?php if ($status === 'Pending'): ?>

                                            <!-- RENTAL ACTIONS -->
                                            <div style="display:flex; flex-direction:column; gap:8px; width:170px;">

                                                <!-- APPROVE RENTAL -->
                                                <form action="manage_rental.php" method="POST"
                                                    style="width:100%; display:flex; flex-direction:column;">

                                                    <input type="hidden" name="id"
                                                        value="<?= (int)$row['id']; ?>">

                                                    <input type="hidden" name="action"
                                                        value="approve">

                                                    <input type="hidden" name="csrf_token"
                                                        value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">

                                                    <textarea name="pre_condition"
                                                        placeholder="Tool condition before rental"
                                                        required
                                                        style="width:100%; box-sizing:border-box; padding:6px; margin-bottom:5px;"></textarea>

                                                    <button type="submit"
                                                        style="width:100%; background:#27ae60; color:white; padding:7px 10px; border:0; border-radius:4px; cursor:pointer;">
                                                        Approve
                                                    </button>

                                                </form>

                                                <!-- DECLINE RENTAL -->
                                                <form action="manage_rental.php" method="POST"
                                                    onsubmit="return confirm('Are you sure you want to decline this request?');"
                                                    style="width:100%;">

                                                    <input type="hidden" name="id"
                                                        value="<?= (int)$row['id']; ?>">

                                                    <input type="hidden" name="action"
                                                        value="decline">

                                                    <input type="hidden" name="csrf_token"
                                                        value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">

                                                    <button type="submit"
                                                        style="width:100%; background:#e74c3c; color:white; padding:7px 10px; border:0; border-radius:4px; cursor:pointer;">
                                                        Decline
                                                    </button>

                                                </form>

                                            </div>


                                        <?php elseif ($status === 'Approved' && $is_cash_pending): ?>

                                            <!-- CONFIRM CASH PAYMENT -->
                                            <form action="manage_rental.php" method="POST"
                                                onsubmit="return confirm('Confirm that you received the rental fee and security deposit in cash?');">

                                                <input type="hidden" name="id"
                                                    value="<?= (int)$row['id']; ?>">

                                                <input type="hidden" name="action"
                                                    value="confirm_cash">

                                                <input type="hidden" name="csrf_token"
                                                    value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">

                                                <button type="submit"
                                                    style="background:#27ae60;color:white;padding:5px 10px;border:0;border-radius:4px;cursor:pointer;">
                                                    Confirm Cash Payment
                                                </button>

                                            </form>


                                        <?php elseif ($status === 'Approved' && $payments_ready): ?>

                                            <!-- CONFIRM TOOL PICKUP -->
                                            <form action="manage_rental.php" method="POST"
                                                onsubmit="return confirm('Confirm that the renter has physically picked up the tool?');">

                                                <input type="hidden" name="id"
                                                    value="<?= (int)$row['id']; ?>">

                                                <input type="hidden" name="action"
                                                    value="pickup">

                                                <input type="hidden" name="csrf_token"
                                                    value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">

                                                <button type="submit"
                                                    style="background:#2980b9;color:white;padding:5px 10px;border:0;border-radius:4px;cursor:pointer;">
                                                    Confirm Tool Pickup
                                                </button>

                                            </form>


                                        <?php elseif ($status === 'Approved'): ?>

                                            <span style="color:#7f8c8d;font-size:0.8rem;">
                                                Awaiting renter payment
                                            </span>


                                        <?php elseif ($status === 'Picked Up'): ?>

                                            <span style="color:#7f8c8d;font-size:0.8rem;">
                                                Tool currently with renter
                                            </span>


                                        <?php elseif ($status === 'Returned'): ?>

                                            <!-- COMPLETE RENTAL -->
                                            <form action="manage_rental.php" method="POST"
                                                onsubmit="return confirm('Confirm that the returned tool has been inspected and the rental is complete?');">

                                                <input type="hidden" name="id"
                                                    value="<?= (int)$row['id']; ?>">

                                                <input type="hidden" name="action"
                                                    value="complete">

                                                <input type="hidden" name="csrf_token"
                                                    value="<?= htmlspecialchars($_SESSION['csrf_token']); ?>">

                                                <button type="submit"
                                                    style="background:#2980b9;color:white;padding:5px 10px;border:0;border-radius:4px;cursor:pointer;">
                                                    Confirm & Finish
                                                </button>

                                            </form>


                                        <?php elseif ($status === 'Completed'): ?>

                                            <span style="color:#27ae60;font-size:0.8rem;">
                                                Transaction Completed
                                            </span>


                                        <?php elseif ($status === 'Declined'): ?>

                                            <span style="color:#e74c3c;font-size:0.8rem;">
                                                Request Declined
                                            </span>


                                        <?php else: ?>

                                            <span style="color:#bdc3c7;font-size:0.8rem;">
                                                No Actions
                                            </span>

                                        <?php endif; ?>

                                    </div>

                                </td>

                            </tr>

                        <?php } ?>

                        <?php if (mysqli_num_rows($lending_query) === 0): ?>

                            <tr>
                                <td colspan="6" style="text-align:center;padding:20px;">
                                    No rental requests yet.
                                </td>
                            </tr>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </div>




        <div class="section" id="section-deposits">
            <div class="header-title">
                <h1>Deposits & Wallet</h1>
                <p>Track your security deposits as both a renter and a tool owner.</p>
            </div>

            <div class="table-container" style="margin-bottom: 30px;">
                <h2 style="margin-bottom: 15px; font-size: 1.1rem; color: var(--dark);">Deposits You Paid (Renter)</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Tool</th>
                            <th>Owner</th>
                            <th>Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Query for deposits where the current user is the renter
                        $renter_dep_query = mysqli_query($conn, "
                    SELECT d.*, t.tool_name, u.name as owner_name 
                    FROM deposits d
                    JOIN tools t ON d.tool_id = t.id
                    JOIN users u ON t.owner_id = u.id
                    WHERE d.user_id = '$user_id'
                ");

                        if (mysqli_num_rows($renter_dep_query) > 0) {
                            while ($dep = mysqli_fetch_assoc($renter_dep_query)) {
                                $status = $dep['status'];
                                $badge_class = ($status == 'Held') ? 'status-held' : 'status-released';
                        ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($dep['tool_name']); ?></td>
                                    <td><?php echo htmlspecialchars($dep['owner_name']); ?></td>
                                    <td><b>₱<?php echo number_format($dep['amount'], 2); ?></b></td>
                                    <td><span class="badge <?php echo $badge_class; ?>">
                                            <?php echo ($status == 'Held') ? 'Held by System' : $status; ?>
                                        </span></td>
                                </tr>
                        <?php
                            }
                        } else {
                            echo "<tr><td colspan='4' style='text-align:center;'>No deposits paid yet.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>

            <div class="table-container">
                <h2 style="margin-bottom: 15px; font-size: 1.1rem; color: var(--dark);">Deposits for Your Tools (Owner)</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Renter</th>
                            <th>Tool</th>
                            <th>Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Query for deposits where the current user owns the tool being rented
                        $owner_dep_query = mysqli_query($conn, "
        SELECT d.*, t.tool_name, u.name as renter_name, r.status as rental_status
        FROM deposits d
        JOIN tools t ON d.tool_id = t.id
        JOIN users u ON d.user_id = u.id
        JOIN rentals r ON d.rental_id = r.id
        WHERE t.owner_id = '$user_id'
        ORDER BY d.id DESC
    ");

                        if (mysqli_num_rows($owner_dep_query) > 0):
                            while ($dep = mysqli_fetch_assoc($owner_dep_query)):
                                $status = $dep['status'];
                                $badge_class = ($status == 'Held') ? 'status-held' : 'status-released';
                        ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($dep['renter_name']); ?></td>
                                    <td><?php echo htmlspecialchars($dep['tool_name']); ?></td>
                                    <td><b>₱<?php echo number_format($dep['amount'], 2); ?></b></td>
                                    <td>
                                        <span class="badge <?php echo $badge_class; ?>">
                                            <?php echo ($status == 'Held') ? 'Held in Escrow' : $status; ?>
                                        </span>

                                        <?php if ($status == 'Held' && $dep['rental_status'] == 'Returned'): ?>
                                            <a href="review_report.php?rental_id=<?php echo $dep['rental_id']; ?>"
                                                style="display:block; font-size: 0.75rem; color: var(--primary); margin-top: 8px; font-weight: 700; text-decoration: none;">
                                                <i class="fa-solid fa-magnifying-glass"></i> Review & Release
                                            </a>
                                        <?php elseif ($status == 'Held'): ?>
                                            <p style="font-size: 0.7rem; color: var(--light-grey); margin-top: 5px;">
                                                <i class="fa-solid fa-clock"></i> Waiting for return
                                            </p>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php
                            endwhile;
                        else:
                            ?>
                            <tr>
                                <td colspan="4" style="text-align:center; padding: 20px; color: var(--light-grey);">
                                    No active deposits for your tools.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        </div>



        <div class="section" id="section-profile">
            <div class="header-title">
                <h1>My Profile</h1>
                <p>Manage your account settings.</p>
            </div>

            <div class="profile-card">
                <div class="profile-avatar">
                    <i class="fa-solid fa-user"></i>
                </div>

                <!-- NAME -->
                <h2 style="margin-bottom: 5px;">
                    <?php echo htmlspecialchars($user_data['name'] ?? 'User'); ?>
                </h2>

                <!-- EMAIL -->
                <p style="color: var(--grey); font-size: 0.9rem; margin-bottom: 20px;">
                    <?php echo htmlspecialchars($user_data['email'] ?? ''); ?>
                </p>

                <!-- UPDATE PASSWORD -->
                <form action="user.php?section=profile" method="POST"
                    style="text-align: left; border-top: 1px solid #eee; padding-top: 20px;">

                    <?php
                    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_password'])) {
                        $new_password = $_POST['password'];

                        if (!empty($new_password)) {
                            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

                            $update_sql = "UPDATE users SET password = '$hashed_password' WHERE id = '$user_id'";

                            if (mysqli_query($conn, $update_sql)) {
                                echo "<div style='background: #d4edda; color: #155724; padding: 10px; border-radius: 5px; margin-bottom: 15px; font-size: 0.85rem;'>
                        <i class='fa-solid fa-circle-check'></i> Password updated successfully!
                        </div>";
                            }
                        } else {
                            echo "<div style='background: #f8d7da; color: #721c24; padding: 10px; border-radius: 5px; margin-bottom: 15px; font-size: 0.85rem;'>
                    Please enter a new password.
                    </div>";
                        }
                    }
                    ?>

                    <div class="filter-group" style="margin-bottom: 20px;">
                        <label>New Password</label>
                        <input type="password" name="password" class="filter-input"
                            placeholder="Enter new password" style="border: 1px solid #ddd;">
                    </div>

                    <button type="submit" name="update_password" class="btn-rent"
                        style="width: 100%; cursor: pointer;">
                        Update Password
                    </button>
                </form>
            </div>
        </div>

    </main>
</body>

</html>