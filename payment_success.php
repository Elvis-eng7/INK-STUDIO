<?php
require_once "db.php";

$bookingId = (int)($_GET['booking_id'] ?? 0);

if ($bookingId <= 0) {
    exit("Invalid booking reference.");
}

$sql = "SELECT booking_id, customer_name, amount_paid,
               amount_paid_kes, payment_status
        FROM bookings
        WHERE booking_id = ?
        LIMIT 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $bookingId);
$stmt->execute();

$result = $stmt->get_result();
$booking = $result->fetch_assoc();

$stmt->close();
$conn->close();

if (!$booking || $booking['payment_status'] !== 'Deposit Paid') {
    exit("Payment has not been confirmed.");
}

$name = htmlspecialchars($booking['customer_name']);
$reference = (int)$booking['booking_id'];
$usd = number_format((float)$booking['amount_paid'], 2);
$kes = number_format((float)$booking['amount_paid_kes'], 2);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful | INK STUDIO</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }

        body {
            min-height: 100vh;
            background: #f4f8f5;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .success-card {
            background: white;
            width: 100%;
            max-width: 460px;
            padding: 40px 28px;
            border-radius: 18px;
            text-align: center;
            box-shadow: 0 10px 35px rgba(0,0,0,0.08);
        }

        .check {
            width: 85px;
            height: 85px;
            background: #e0f5e8;
            color: #159447;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            margin: 0 auto 22px;
        }

        h1 {
            color: #159447;
            margin-bottom: 12px;
        }

        .message {
            color: #666;
            line-height: 1.6;
            margin-bottom: 28px;
        }

        .details {
            background: #f5faf6;
            border-radius: 12px;
            padding: 20px;
            text-align: left;
            margin-bottom: 25px;
        }

        .details p {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            margin: 13px 0;
            color: #555;
            font-size: 14px;
        }

        .details strong {
            color: #222;
            text-align: right;
        }

        .paid {
            color: #159447 !important;
        }

        .home-btn {
            display: block;
            background: #159447;
            color: white;
            text-decoration: none;
            padding: 15px;
            border-radius: 8px;
            font-weight: bold;
        }

        .home-btn:hover {
            background: #107738;
        }

        .brand {
            margin-top: 25px;
            color: #888;
            font-size: 13px;
            letter-spacing: 2px;
        }
    </style>
</head>

<body>

<div class="success-card">

    <div class="check">✓</div>

    <h1>Payment Successful!</h1>

    <p class="message">
        Congratulations, <?= $name ?>! Your deposit has been
        received successfully. Thank you for choosing INK STUDIO.
    </p>

    <div class="details">

        <p>
            <span>Booking ID</span>
            <strong>#<?= $reference ?></strong>
        </p>

        <p>
            <span>Deposit (USD)</span>
            <strong>$<?= $usd ?></strong>
        </p>

        <p>
            <span>Paid (KES)</span>
            <strong>KSh <?= $kes ?></strong>
        </p>

        <p>
            <span>Payment status</span>
            <strong class="paid">Deposit Paid</strong>
        </p>

    </div>

    <a href="tattoo3.html" class="home-btn">
        Return to INK STUDIO
    </a>

    <div class="brand">INK STUDIO</div>

</div>

</body>
</html>