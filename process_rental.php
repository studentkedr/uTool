
<?php
include 'db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if (
    $_SERVER['REQUEST_METHOD'] !== 'POST' ||
    !isset($_POST['confirm_rent_btn'])
) {
    header("Location: user.php?section=browse");
    exit();
}

$renter_id = (int) $_SESSION['user_id'];
$tool_id = filter_input(INPUT_POST, 'tool_id', FILTER_VALIDATE_INT);
$start_date = $_POST['start_date'] ?? '';
$end_date = $_POST['end_date'] ?? '';

if (!$tool_id || !$start_date || !$end_date) {
    $_SESSION['error_msg'] = "Please complete all rental details.";
    header("Location: user.php?section=browse");
    exit();
}

// Validate rental dates
$start = DateTime::createFromFormat('!Y-m-d', $start_date);
$end = DateTime::createFromFormat('!Y-m-d', $end_date);
$today = new DateTime('today');

if (
    !$start || $start->format('Y-m-d') !== $start_date ||
    !$end || $end->format('Y-m-d') !== $end_date ||
    $start < $today || $end <= $start
) {
    $_SESSION['error_msg'] =
        "Invalid rental dates. Please check your pickup and return dates.";

    header("Location: rent_tool.php?id=" . $tool_id);
    exit();
}

mysqli_begin_transaction($conn);

try {

    // Lock tool record and check availability
    $tool_stmt = mysqli_prepare($conn, "
        SELECT id, owner_id, security_deposit, status
        FROM tools
        WHERE id = ?
        FOR UPDATE
    ");

    mysqli_stmt_bind_param($tool_stmt, "i", $tool_id);
    mysqli_stmt_execute($tool_stmt);

    $tool_result = mysqli_stmt_get_result($tool_stmt);
    $tool = mysqli_fetch_assoc($tool_result);

    if (!$tool) {
        throw new Exception("Tool not found.");
    }

    $owner_id = (int) $tool['owner_id'];

    // Prevent renting your own tool
    if ($owner_id === $renter_id) {
        throw new Exception("You cannot rent your own tool.");
    }

    // Check availability
    if ($tool['status'] !== 'Available') {
        throw new Exception("This tool is currently unavailable.");
    }

    // Check for an existing unfinished rental
    $check_stmt = mysqli_prepare($conn, "
        SELECT id
        FROM rentals
        WHERE tool_id = ?
        AND status IN ('Pending', 'Approved', 'Picked Up', 'Returned')
        LIMIT 1
    ");

    mysqli_stmt_bind_param($check_stmt, "i", $tool_id);
    mysqli_stmt_execute($check_stmt);

    if (mysqli_num_rows(mysqli_stmt_get_result($check_stmt)) > 0) {
        throw new Exception(
            "This tool already has an ongoing rental request."
        );
    }

    // Create rental request
    $deposit_amount = (float) $tool['security_deposit'];

    $rental_stmt = mysqli_prepare($conn, "
        INSERT INTO rentals
        (tool_id, renter_id, owner_id, start_date, end_date,
         status, security_deposit)
        VALUES (?, ?, ?, ?, ?, 'Pending', ?)
    ");

    mysqli_stmt_bind_param(
        $rental_stmt,
        "iiissd",
        $tool_id,
        $renter_id,
        $owner_id,
        $start_date,
        $end_date,
        $deposit_amount
    );

    if (!mysqli_stmt_execute($rental_stmt)) {
        throw new Exception(mysqli_stmt_error($rental_stmt));
    }

    $rental_id = mysqli_insert_id($conn);

    // Create pending deposit record
    // Payment will only be recorded after owner approval
    $deposit_stmt = mysqli_prepare($conn, "
        INSERT INTO deposits
        (user_id, tool_id, rental_id, amount, status, payment_method)
        VALUES (?, ?, ?, ?, 'Pending', NULL)
    ");

    mysqli_stmt_bind_param(
        $deposit_stmt,
        "iiid",
        $renter_id,
        $tool_id,
        $rental_id,
        $deposit_amount
    );

    if (!mysqli_stmt_execute($deposit_stmt)) {
        throw new Exception(mysqli_stmt_error($deposit_stmt));
    }

    mysqli_commit($conn);

    $_SESSION['success_msg'] =
        "Rental request submitted! Please wait for the owner's approval before making payment.";

    header("Location: user.php?section=rentals");
    exit();

} catch (Exception $e) {

    mysqli_rollback($conn);

    $_SESSION['error_msg'] = $e->getMessage();

    header("Location: user.php?section=browse");
    exit();
}
?>
