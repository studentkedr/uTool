<?php
include 'db.php'; 
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$rental_id = isset($_GET['rental_id']) ? mysqli_real_escape_string($conn, $_GET['rental_id']) : null;

if ($rental_id) {
    mysqli_begin_transaction($conn);

    try {
        // MODIFIED: Check if user is EITHER the owner OR the renter
        $check_q = mysqli_query($conn, "SELECT r.*, d.amount as deposit_amt 
                                       FROM rentals r 
                                       JOIN deposits d ON r.id = d.rental_id 
                                       WHERE r.id = '$rental_id' AND (r.owner_id = '$user_id' OR r.renter_id = '$user_id')");
        $rental_data = mysqli_fetch_assoc($check_q);

        if ($rental_data) {
            $renter_id = $rental_data['renter_id'];
            $deposit_to_refund = $rental_data['deposit_amt'];
            $tool_id = $rental_data['tool_id'];

            // ACTION A: Update Rental Status
            mysqli_query($conn, "UPDATE rentals SET status = 'Returned' WHERE id = '$rental_id'");

            // ACTION B: Update Deposit Status
            mysqli_query($conn, "UPDATE deposits SET status = 'Released' WHERE rental_id = '$rental_id'");

            // ACTION C: Record the Refund (Transaction)
            mysqli_query($conn, "INSERT INTO transactions (user_id, amount, type, description) 
                                 VALUES ('$renter_id', '$deposit_to_refund', 'Refund', 'Security Deposit Return')");

            // ACTION D: Make the Tool available (Updating your 'status' column)
            mysqli_query($conn, "UPDATE tools SET status = 'Available' WHERE id = '$tool_id'");

            mysqli_commit($conn);
            
            // REDIRECT: Go back to your rentals activity page
            header("Location: user.php?section=rentals&status=returned_success");
            exit();
        } else {
            throw new Exception("Unauthorized or Rental not found.");
        }

    } catch (Exception $e) {
        mysqli_rollback($conn);
        echo "Transaction Failed: " . $e->getMessage();
    }
} else {
    echo "No Rental ID provided.";
}
?>