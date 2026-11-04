
<?php
include 'db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if (
    $_SERVER['REQUEST_METHOD'] !== 'POST' ||
    !isset($_POST['pay_btn'])
) {
    header("Location: user.php?section=my-rentals");
    exit();
}

// VALIDATE CSRF TOKEN
if (
    !isset($_POST['csrf_token'], $_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    die("Invalid request. Please refresh the page and try again.");
}

$renter_id = (int) $_SESSION['user_id'];
$rental_id = filter_input(INPUT_POST, 'rental_id', FILTER_VALIDATE_INT);
$payment_method = $_POST['payment_method'] ?? '';

$allowed_methods = ['Cash', 'GCash', 'Maya', 'Card'];

if (
    !$rental_id ||
    !is_string($payment_method) ||
    !in_array($payment_method, $allowed_methods, true)
) {
    die("Invalid payment details.");
}

mysqli_begin_transaction($conn);

try {

    // =========================================
    // FETCH AND LOCK RENTAL RECORD
    // =========================================

    $stmt = mysqli_prepare($conn, "
        SELECT id, status, rental_payment_status, rental_payment_method
        FROM rentals
        WHERE id = ? AND renter_id = ?
        FOR UPDATE
    ");

    mysqli_stmt_bind_param($stmt, "ii", $rental_id, $renter_id);
    mysqli_stmt_execute($stmt);

    $rental = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if (!$rental) {
        throw new Exception("Rental record not found.");
    }

    if ($rental['status'] !== 'Approved') {
        throw new Exception("Only approved rentals can be paid.");
    }

    if ($rental['rental_payment_status'] !== 'Pending') {
        throw new Exception("Rental fee has already been processed.");
    }


    // =========================================
    // FETCH AND LOCK SECURITY DEPOSIT
    // =========================================

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
        throw new Exception("Deposit record not found.");
    }

    // PREVENT DUPLICATE PAYMENT SUBMISSION
    if (
        !empty($rental['rental_payment_method']) ||
        !empty($deposit['payment_method'])
    ) {
        throw new Exception("Payment method has already been submitted.");
    }

    if ($deposit['status'] !== 'Pending') {
        throw new Exception("Security deposit has already been processed.");
    }
    $deposit_id = (int) $deposit['id'];


    // =========================================
    // DETERMINE PAYMENT STATUS
    // =========================================

    if ($payment_method === 'Cash') {

        // Cash requires owner confirmation.
        $rental_payment_status = 'Pending';
        $deposit_status = 'Pending';
    } else {

        // Simulated online payment.
        $rental_payment_status = 'Paid';

        // Security deposit is held until rental completion.
        $deposit_status = 'Held';
    }


    // =========================================
    // UPDATE RENTAL FEE PAYMENT
    // =========================================

    $stmt = mysqli_prepare($conn, "
        UPDATE rentals
        SET rental_payment_method = ?,
            rental_payment_status = ?
        WHERE id = ?
    ");

    mysqli_stmt_bind_param(
        $stmt,
        "ssi",
        $payment_method,
        $rental_payment_status,
        $rental_id
    );

    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Failed to update rental payment.");
    }


    // =========================================
    // UPDATE SECURITY DEPOSIT
    // =========================================

    $stmt = mysqli_prepare($conn, "
        UPDATE deposits
        SET payment_method = ?,
            status = ?
        WHERE id = ?
    ");

    mysqli_stmt_bind_param(
        $stmt,
        "ssi",
        $payment_method,
        $deposit_status,
        $deposit_id
    );

    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Failed to update security deposit.");
    }


    // =========================================
    // COMMIT TRANSACTION
    // =========================================

    mysqli_commit($conn);

    if ($payment_method === 'Cash') {

        $_SESSION['success_msg'] =
            "Cash payment selected. Please pay the owner and wait for payment confirmation before pickup.";
    } else {

        $_SESSION['success_msg'] =
            "Simulated payment successful! Your rental fee has been marked as Paid and your security deposit is now Held. No actual transaction occurred.";
    }

    unset($_SESSION['csrf_token']);

    header("Location: user.php?section=my-rentals");
    exit();
} catch (Exception $e) {

    mysqli_rollback($conn);

    $_SESSION['error_msg'] = $e->getMessage();

    header("Location: payment.php?id=" . $rental_id);
    exit();
}
?>
