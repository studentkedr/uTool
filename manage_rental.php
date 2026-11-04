
<?php
include 'db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    die("Unauthorized access.");
}

$user_id = (int) $_SESSION['user_id'];

// Accept POST requests only
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: user.php");
    exit();
}

// Validate CSRF token
if (
    !isset($_POST['csrf_token'], $_SESSION['csrf_token']) ||
    !is_string($_POST['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    die("Invalid or expired request. Please refresh the page and try again.");
}

// Validate required fields
if (!isset($_POST['id'], $_POST['action'])) {
    die("Missing rental information.");
}

$rental_id = filter_var($_POST['id'], FILTER_VALIDATE_INT);
$action = $_POST['action'];

if (!$rental_id || !is_string($action)) {
    die("Invalid rental ID or action.");
}

// Determine the appropriate return page
$redirect = ($action === 'return')
    ? "user.php?section=my-rentals"
    : "user.php?section=lending";

mysqli_begin_transaction($conn);

try {

    // =========================================
    // FETCH AND LOCK RENTAL RECORD
    // =========================================

    $stmt = mysqli_prepare($conn, "
        SELECT *
        FROM rentals
        WHERE id = ?
        FOR UPDATE
    ");

    mysqli_stmt_bind_param($stmt, "i", $rental_id);
    mysqli_stmt_execute($stmt);

    $rental = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if (!$rental) {
        throw new Exception("Rental record not found.");
    }

    $tool_id = (int) $rental['tool_id'];
    $owner_id = (int) $rental['owner_id'];
    $renter_id = (int) $rental['renter_id'];

    // =========================================
    // ACTIONS
    // =========================================

    switch ($action) {

        // =========================================
        // 1. OWNER APPROVES RENTAL
        // =========================================

        case 'approve':

            if ($user_id !== $owner_id) {
                throw new Exception("Only the owner can approve this request.");
            }

            if ($rental['status'] !== 'Pending') {
                throw new Exception("This rental request is no longer pending.");
            }

            // Validate tool condition before rental
            $notes = $_POST['pre_condition'] ?? '';

            if (!is_string($notes)) {
                throw new Exception("Invalid tool condition.");
            }

            $notes = trim($notes);

            if ($notes === '') {
                throw new Exception("Please enter the tool condition before approving.");
            }

            if (strlen($notes) > 2000) {
                throw new Exception("Tool condition notes are too long.");
            }

            // Lock and check tool availability
            $stmt = mysqli_prepare($conn, "
                SELECT id, status
                FROM tools
                WHERE id = ?
                FOR UPDATE
            ");

            mysqli_stmt_bind_param($stmt, "i", $tool_id);
            mysqli_stmt_execute($stmt);

            $tool = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

            if (!$tool) {
                throw new Exception("Tool record not found.");
            }

            if ($tool['status'] !== 'Available') {
                throw new Exception("This tool is no longer available.");
            }

            // Approve rental
            $stmt = mysqli_prepare($conn, "
                UPDATE rentals
                SET status = 'Approved',
                    pre_condition_notes = ?
                WHERE id = ?
                  AND status = 'Pending'
            ");

            mysqli_stmt_bind_param($stmt, "si", $notes, $rental_id);
            mysqli_stmt_execute($stmt);

            if (mysqli_stmt_affected_rows($stmt) !== 1) {
                throw new Exception("Unable to approve this rental.");
            }

            // Automatically reserve the tool
            $stmt = mysqli_prepare($conn, "
                UPDATE tools
                SET status = 'Reserved'
                WHERE id = ?
                  AND status = 'Available'
            ");

            mysqli_stmt_bind_param($stmt, "i", $tool_id);
            mysqli_stmt_execute($stmt);

            if (mysqli_stmt_affected_rows($stmt) !== 1) {
                throw new Exception("Unable to reserve this tool.");
            }

            $redirect = "user.php?section=lending&msg=approved";

            break;


        // =========================================
        // 2. OWNER DECLINES RENTAL
        // =========================================

        case 'decline':

            if ($user_id !== $owner_id) {
                throw new Exception("Only the owner can decline this request.");
            }

            if ($rental['status'] !== 'Pending') {
                throw new Exception("This rental request cannot be declined.");
            }

            $stmt = mysqli_prepare($conn, "
                UPDATE rentals
                SET status = 'Declined'
                WHERE id = ?
                  AND status = 'Pending'
            ");

            mysqli_stmt_bind_param($stmt, "i", $rental_id);
            mysqli_stmt_execute($stmt);

            if (mysqli_stmt_affected_rows($stmt) !== 1) {
                throw new Exception("Unable to decline this rental.");
            }

            $redirect = "user.php?section=lending&msg=declined";

            break;


        // =========================================
        // 3. OWNER CONFIRMS CASH PAYMENT
        // =========================================

        case 'confirm_cash':

            if ($user_id !== $owner_id) {
                throw new Exception("Only the owner can confirm cash payment.");
            }

            if ($rental['status'] !== 'Approved') {
                throw new Exception("Only approved rentals can receive payment confirmation.");
            }

            // Verify rental fee
            if (
                $rental['rental_payment_method'] !== 'Cash' ||
                $rental['rental_payment_status'] !== 'Pending'
            ) {
                throw new Exception("Rental fee is not awaiting cash confirmation.");
            }

            // Fetch and lock deposit
            $stmt = mysqli_prepare($conn, "
                SELECT id, status, payment_method
                FROM deposits
                WHERE rental_id = ?
                FOR UPDATE
            ");

            mysqli_stmt_bind_param($stmt, "i", $rental_id);
            mysqli_stmt_execute($stmt);

            $deposit = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

            if (!$deposit) {
                throw new Exception("Security deposit record not found.");
            }

            // Verify deposit payment
            if (
                $deposit['payment_method'] !== 'Cash' ||
                $deposit['status'] !== 'Pending'
            ) {
                throw new Exception("Security deposit is not awaiting cash confirmation.");
            }

            $deposit_id = (int) $deposit['id'];

            // Mark rental fee as paid
            $stmt = mysqli_prepare($conn, "
                UPDATE rentals
                SET rental_payment_status = 'Paid'
                WHERE id = ?
            ");

            mysqli_stmt_bind_param($stmt, "i", $rental_id);
            mysqli_stmt_execute($stmt);

            // Hold security deposit
            $stmt = mysqli_prepare($conn, "
                UPDATE deposits
                SET status = 'Held'
                WHERE id = ?
                  AND status = 'Pending'
            ");

            mysqli_stmt_bind_param($stmt, "i", $deposit_id);
            mysqli_stmt_execute($stmt);

            if (mysqli_stmt_affected_rows($stmt) !== 1) {
                throw new Exception("Unable to confirm the security deposit.");
            }

            $redirect = "user.php?section=lending&msg=cash_confirmed";

            break;


        // =========================================
        // 4. OWNER CONFIRMS TOOL PICKUP
        // =========================================

        case 'pickup':

            if ($user_id !== $owner_id) {
                throw new Exception("Only the owner can confirm tool pickup.");
            }

            if ($rental['status'] !== 'Approved') {
                throw new Exception("Only approved rentals can proceed to pickup.");
            }

            // Verify rental fee
            if ($rental['rental_payment_status'] !== 'Paid') {
                throw new Exception("Rental fee must be paid before pickup.");
            }

            // Fetch and lock security deposit
            $stmt = mysqli_prepare($conn, "
                SELECT id, status, payment_method
                FROM deposits
                WHERE rental_id = ?
                FOR UPDATE
            ");

            mysqli_stmt_bind_param($stmt, "i", $rental_id);
            mysqli_stmt_execute($stmt);

            $deposit = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

            if (!$deposit) {
                throw new Exception("Security deposit record not found.");
            }

            if ($deposit['status'] !== 'Held') {
                throw new Exception("Security deposit must be held before pickup.");
            }

            // Verify payment methods
            if (
                empty($rental['rental_payment_method']) ||
                $rental['rental_payment_method'] !== $deposit['payment_method']
            ) {
                throw new Exception("Rental fee and deposit payment methods do not match.");
            }

            // Lock and verify reserved tool
            $stmt = mysqli_prepare($conn, "
                SELECT id, status
                FROM tools
                WHERE id = ?
                FOR UPDATE
            ");

            mysqli_stmt_bind_param($stmt, "i", $tool_id);
            mysqli_stmt_execute($stmt);

            $tool = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

            if (!$tool || $tool['status'] !== 'Reserved') {
                throw new Exception("This tool is not reserved for this rental.");
            }

            // Mark rental as picked up
            $stmt = mysqli_prepare($conn, "
                UPDATE rentals
                SET status = 'Picked Up'
                WHERE id = ?
                  AND status = 'Approved'
            ");

            mysqli_stmt_bind_param($stmt, "i", $rental_id);
            mysqli_stmt_execute($stmt);

            if (mysqli_stmt_affected_rows($stmt) !== 1) {
                throw new Exception("Unable to confirm tool pickup.");
            }

            // Change Reserved to Rented
            $stmt = mysqli_prepare($conn, "
                UPDATE tools
                SET status = 'Rented'
                WHERE id = ?
                  AND status = 'Reserved'
            ");

            mysqli_stmt_bind_param($stmt, "i", $tool_id);
            mysqli_stmt_execute($stmt);

            if (mysqli_stmt_affected_rows($stmt) !== 1) {
                throw new Exception("Unable to update tool availability.");
            }

            $redirect = "user.php?section=lending&msg=picked_up";

            break;


        // =========================================
        // 5. RENTER SUBMITS TOOL RETURN
        // =========================================

        case 'return':

            if ($user_id !== $renter_id) {
                throw new Exception("Only the renter can submit a return.");
            }

            if ($rental['status'] !== 'Picked Up') {
                throw new Exception("Only picked-up tools can be returned.");
            }

            // Validate condition after rental
            $notes = $_POST['post_condition'] ?? '';

            if (!is_string($notes)) {
                throw new Exception("Invalid tool condition.");
            }

            $notes = trim($notes);

            if ($notes === '') {
                throw new Exception("Please describe the tool condition after rental.");
            }

            if (strlen($notes) > 2000) {
                throw new Exception("Tool condition notes are too long.");
            }

            // Submit tool return
            $stmt = mysqli_prepare($conn, "
                UPDATE rentals
                SET status = 'Returned',
                    post_condition_notes = ?
                WHERE id = ?
                  AND status = 'Picked Up'
            ");

            mysqli_stmt_bind_param($stmt, "si", $notes, $rental_id);
            mysqli_stmt_execute($stmt);

            if (mysqli_stmt_affected_rows($stmt) !== 1) {
                throw new Exception("Unable to submit tool return.");
            }

            $redirect = "user.php?section=my-rentals&msg=submitted";

            break;


        // =========================================
        // 6. OWNER COMPLETES RENTAL
        // =========================================

        case 'complete':

            if ($user_id !== $owner_id) {
                throw new Exception("Only the owner can complete this rental.");
            }

            if ($rental['status'] !== 'Returned') {
                throw new Exception("The renter must return the tool first.");
            }

            // Verify rental payment
            if ($rental['rental_payment_status'] !== 'Paid') {
                throw new Exception("Rental fee must be paid before completing the rental.");
            }

            // Fetch and lock deposit
            $stmt = mysqli_prepare($conn, "
                SELECT id, status
                FROM deposits
                WHERE rental_id = ?
                FOR UPDATE
            ");

            mysqli_stmt_bind_param($stmt, "i", $rental_id);
            mysqli_stmt_execute($stmt);

            $deposit = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

            if (!$deposit) {
                throw new Exception("Security deposit record not found.");
            }

            if ($deposit['status'] !== 'Held') {
                throw new Exception("Security deposit cannot be released because it is not held.");
            }

            $deposit_id = (int) $deposit['id'];

            // Lock and verify rented tool
            $stmt = mysqli_prepare($conn, "
                SELECT id, status
                FROM tools
                WHERE id = ?
                FOR UPDATE
            ");

            mysqli_stmt_bind_param($stmt, "i", $tool_id);
            mysqli_stmt_execute($stmt);

            $tool = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

            if (!$tool || $tool['status'] !== 'Rented') {
                throw new Exception("This tool is not currently rented.");
            }

            // Mark rental as completed
            $stmt = mysqli_prepare($conn, "
                UPDATE rentals
                SET status = 'Completed'
                WHERE id = ?
                  AND status = 'Returned'
            ");

            mysqli_stmt_bind_param($stmt, "i", $rental_id);
            mysqli_stmt_execute($stmt);

            if (mysqli_stmt_affected_rows($stmt) !== 1) {
                throw new Exception("Unable to complete this rental.");
            }

            // Release security deposit
            $stmt = mysqli_prepare($conn, "
                UPDATE deposits
                SET status = 'Released'
                WHERE id = ?
                  AND status = 'Held'
            ");

            mysqli_stmt_bind_param($stmt, "i", $deposit_id);
            mysqli_stmt_execute($stmt);

            if (mysqli_stmt_affected_rows($stmt) !== 1) {
                throw new Exception("Unable to release the security deposit.");
            }

            // Make tool available again
            $stmt = mysqli_prepare($conn, "
                UPDATE tools
                SET status = 'Available'
                WHERE id = ?
                  AND status = 'Rented'
            ");

            mysqli_stmt_bind_param($stmt, "i", $tool_id);
            mysqli_stmt_execute($stmt);

            if (mysqli_stmt_affected_rows($stmt) !== 1) {
                throw new Exception("Unable to make the tool available.");
            }

            $redirect = "user.php?section=lending&msg=completed";

            break;


        default:

            throw new Exception("Invalid action.");
    }

    // =========================================
    // COMMIT TRANSACTION
    // =========================================

    mysqli_commit($conn);

    // Rotate CSRF token after successful action
    unset($_SESSION['csrf_token']);

    header("Location: " . $redirect);
    exit();

} catch (Throwable $e) {

    mysqli_rollback($conn);

    $_SESSION['error_msg'] = $e->getMessage();

    header("Location: " . $redirect);
    exit();
}
?>
