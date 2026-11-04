<?php
include 'db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$renter_id = (int) $_SESSION['user_id'];
$rental_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$rental_id) {
    header("Location: user.php?section=my-rentals");
    exit();
}

// FETCH RENTAL AND DEPOSIT DETAILS
$stmt = mysqli_prepare($conn, "
    SELECT r.*,
           t.price_per_day,
           d.amount AS deposit_amount,
           d.status AS deposit_status,
           d.payment_method AS deposit_method
    FROM rentals r
    INNER JOIN tools t ON t.id = r.tool_id
    INNER JOIN deposits d ON d.rental_id = r.id
    WHERE r.id = ?
    AND r.renter_id = ?
    LIMIT 1
");

mysqli_stmt_bind_param($stmt, "ii", $rental_id, $renter_id);
mysqli_stmt_execute($stmt);

$rental = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$rental) {
    die("Rental record not found.");
}

// CHECK RENTAL STATUS
if ($rental['status'] !== 'Approved') {
    die("Payment is available only for approved rental requests.");
}

// CHECK IF PAYMENT HAS ALREADY BEEN PROCESSED
if (
    $rental['rental_payment_status'] !== 'Pending' ||
    $rental['deposit_status'] !== 'Pending' ||
    !empty($rental['rental_payment_method']) ||
    !empty($rental['deposit_method'])
) {
    die("
        Payment has already been submitted or processed.
        Please return to My Rentals to check your payment status.
        <br><br>
        <a href='user.php?section=my-rentals'>Back to My Rentals</a>
    ");
}

// COMPUTE RENTAL DURATION
$start_date = new DateTime($rental['start_date']);
$end_date = new DateTime($rental['end_date']);

$rental_days = (int) $start_date->diff($end_date)->days;

// GET RENTAL PRICE
$price_per_day = (float) $rental['price_per_day'];

// COMPUTE RENTAL FEE
$rental_fee = $price_per_day * $rental_days;

// GET SECURITY DEPOSIT
$deposit = (float) $rental['deposit_amount'];

// COMPUTE TOTAL
$total = $rental_fee + $deposit;


// GENERATE CSRF TOKEN
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/jpg" href="uT.png">
    <title>Rental Payment</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f5f5f5;
            margin: 0;
            padding: 30px 15px;
        }

        .payment-container {
            max-width: 550px;
            margin: auto;
            background: white;
            padding: 25px;
            border-radius: 12px;
        }

        h2 {
            margin-top: 0;
        }

        .summary {
            background: #f7f7f7;
            padding: 18px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .summary p {
            display: flex;
            justify-content: space-between;
            gap: 10px;
        }

        .total {
            border-top: 1px solid #ddd;
            padding-top: 12px;
            font-weight: bold;
            font-size: 19px;
        }

        .method {
            display: block;
            border: 1px solid #ddd;
            padding: 14px;
            border-radius: 8px;
            margin: 10px 0;
            cursor: pointer;
        }

        .method:hover {
            border-color: #32a8a8;
            background: #f8ffff;
        }

        .method input {
            margin-right: 10px;
        }

        button {
            width: 100%;
            padding: 14px;
            border: none;
            background: #32a8a8;
            color: white;
            font-size: 16px;
            border-radius: 8px;
            cursor: pointer;
            margin-top: 15px;
        }

        button:hover {
            background: #278888;
        }

        .note {
            color: #666;
            font-size: 13px;
            line-height: 1.5;
        }

        .back {
            display: block;
            text-align: center;
            margin-top: 18px;
            color: #32a8a8;
            text-decoration: none;
        }
    </style>
</head>

<body>

    <div class="payment-container">

        <h2>Rental Payment</h2>

        <p>
            Rental Reference: #<?php echo (int) $rental_id; ?>
        </p>

        <div class="summary">

            <h3>Payment Summary</h3>

            <p>
                <span>
                    Rental Fee
                    <br>
                    <small>
                        ₱<?= number_format($price_per_day, 2); ?>
                        × <?= $rental_days; ?> days
                    </small>
                </span>

                <strong>
                    ₱<?= number_format($rental_fee, 2); ?>
                </strong>
            </p>

            <p>
                <span>Security Deposit (Refundable)</span>
                <strong>
                    ₱<?php echo number_format($deposit, 2); ?>
                </strong>
            </p>

            <p class="total">
                <span>Total Amount</span>
                <span>
                    ₱<?php echo number_format($total, 2); ?>
                </span>
            </p>

        </div>

        <form action="process_payment.php" method="POST">

            <input
                type="hidden"
                name="rental_id"
                value="<?php echo (int) $rental_id; ?>">

            <input
                type="hidden"
                name="csrf_token"
                value="<?php echo htmlspecialchars(
                            $_SESSION['csrf_token'],
                            ENT_QUOTES,
                            'UTF-8'
                        ); ?>">

            <h3>Select Payment Method</h3>

            <label class="method">
                <input
                    type="radio"
                    name="payment_method"
                    value="Cash"
                    required>
                Cash
            </label>

            <label class="method">
                <input
                    type="radio"
                    name="payment_method"
                    value="GCash">
                GCash
            </label>

            <label class="method">
                <input
                    type="radio"
                    name="payment_method"
                    value="Maya">
                Maya
            </label>

            <label class="method">
                <input
                    type="radio"
                    name="payment_method"
                    value="Card">
                Credit / Debit Card
            </label>

            <p class="note">
                Online payment options are simulated for this project.
                No actual financial transaction will occur.
                Cash payments require confirmation from the tool owner.
            </p>

            <button type="submit" name="pay_btn">
                Confirm Payment Method
            </button>

        </form>

        <a href="user.php?section=my-rentals" class="back">
            Back to My Rentals
        </a>

    </div>

</body>

</html>