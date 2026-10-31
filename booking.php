<?php
ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once "db.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    exit("Please submit the booking form first.");
}

// 1. Collect customer details
$name = trim($_POST["fullName"] ?? "");
$email = trim($_POST["email"] ?? "");
$country = trim($_POST["country"] ?? "");
$type = trim($_POST["tattooType"] ?? "");
$size = trim($_POST["tattooSize"] ?? "");
$date = trim($_POST["appointmentDate"] ?? "");
$description = trim($_POST["description"] ?? "");
$phoneCode = trim($_POST["phoneCode"] ?? "");
$phoneNumber = trim($_POST["phone"] ?? "");

$validPhoneCodes = [
    "+254", "+255", "+256", "+27", "+1",
    "+44", "+91", "+971", "+86", "+82"
];

$phone = $phoneCode . " " . $phoneNumber;

$allowedTypes = ["fine-line", "realism", "custom"];
$allowedSizes = ["small", "medium", "large"];

// 2. Validate details
if (
    $name === "" ||
    !filter_var($email, FILTER_VALIDATE_EMAIL) ||
    $country === "" ||
    !in_array($type, $allowedTypes, true) ||
    !in_array($size, $allowedSizes, true) ||
    $date === "" ||
    !in_array($phoneCode, $validPhoneCodes, true) ||
    !preg_match('/^[0-9 ()-]{5,20}$/', $phoneNumber)
) {
    exit("Invalid booking details. Please check all fields and try again.");
}

// 3. Validate appointment date
$appointmentDate = DateTime::createFromFormat("!Y-m-d", $date);

if (
    !$appointmentDate ||
    $appointmentDate->format("Y-m-d") !== $date ||
    $date < date("Y-m-d")
) {
    exit("Please select a valid appointment date today or in the future.");
}

// 4. INK STUDIO pricing in USD
$prices = [
    "fine-line" => [
        "small" => 50,
        "medium" => 100,
        "large" => 180
    ],
    "realism" => [
        "small" => 100,
        "medium" => 200,
        "large" => 350
    ],
    "custom" => [
        "small" => 80,
        "medium" => 150,
        "large" => 250
    ]
];

$price = $prices[$type][$size];

// 5. Calculate deposit and remaining balance
$deposit = round($price * 0.40, 2);
$remainingBalance = round($price * 0.60, 2);

// 6. Save booking to MySQL
$sql = "INSERT INTO bookings
    (
        customer_name,
        email,
        country,
        phone,
        tattoo_type,
        tattoo_size,
        description,
        appointment_date,
        estimated_price,
        amount_paid,
        payment_status,
        appointment_status
    )
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 'Unpaid', 'Pending')";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    exit("Could not prepare booking: " . $conn->error);
}

$stmt->bind_param(
    "ssssssssd",
    $name,
    $email,
    $country,
    $phone,
    $type,
    $size,
    $description,
    $date,
    $price
);

if (!$stmt->execute()) {
    exit("Booking could not be saved: " . $stmt->error);
}

// Get the ID of the newly created booking
$bookingId = $stmt->insert_id;

$stmt->close();
$conn->close();

// 7. Prepare safe display values
$safeName = htmlspecialchars($name, ENT_QUOTES, "UTF-8");
$safePhone = htmlspecialchars($phone, ENT_QUOTES, "UTF-8");
$safeDate = htmlspecialchars($date, ENT_QUOTES, "UTF-8");
$safeSize = htmlspecialchars(ucfirst($size), ENT_QUOTES, "UTF-8");

$displayType = [
    "fine-line" => "Fine-Line",
    "realism" => "Realism",
    "custom" => "Custom Design"
][$type];

$safeDisplayType = htmlspecialchars($displayType, ENT_QUOTES, "UTF-8");

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Booking Received | INK STUDIO</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            background:
                radial-gradient(circle at top, #292319 0%, #111 45%, #080808 100%);
            color: #f5f0e8;
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 35px 15px;
        }

        .confirmation {
            width: 100%;
            max-width: 620px;
            background: #191919;
            border: 1px solid #a9894d;
            border-radius: 18px;
            padding: 35px;
            box-shadow: 0 15px 50px rgba(0,0,0,0.6);
            text-align: center;
        }

        .logo {
            font-size: 13px;
            font-weight: bold;
            letter-spacing: 5px;
            color: #d4af65;
            margin-bottom: 30px;
        }

        .success-icon {
            width: 75px;
            height: 75px;
            border: 2px solid #d4af65;
            color: #d4af65;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 38px;
            margin: 0 auto 22px;
        }

        .eyebrow {
            color: #d4af65;
            font-size: 11px;
            letter-spacing: 3px;
            font-weight: bold;
            margin-bottom: 12px;
        }

        h1 {
            color: #e5c47c;
            font-size: 30px;
            line-height: 1.3;
            margin-bottom: 12px;
        }

        .intro {
            color: #bbb;
            font-size: 14px;
            line-height: 1.7;
            margin-bottom: 28px;
        }

        .section-title {
            text-align: left;
            color: #d4af65;
            font-size: 12px;
            letter-spacing: 2px;
            margin-bottom: 16px;
        }

        .details {
            background: #242424;
            border: 1px solid #38332a;
            padding: 22px;
            border-radius: 12px;
            text-align: left;
            margin-bottom: 25px;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            margin: 13px 0;
            font-size: 13px;
        }

        .detail-row span {
            color: #aaa;
        }

        .detail-row strong {
            color: #f5f0e8;
            text-align: right;
        }

        .price-total {
            border-top: 1px solid #494235;
            padding-top: 16px;
            margin-top: 17px;
        }

        .price-total strong {
            color: #e5c47c;
            font-size: 22px;
        }

        .deposit-box {
            background: linear-gradient(145deg, #29251d, #1d1b17);
            border: 1px solid #a9894d;
            padding: 25px 20px;
            border-radius: 12px;
            margin-bottom: 22px;
        }

        .deposit-box h2 {
            color: #e5c47c;
            font-size: 21px;
            margin-bottom: 12px;
        }

        .deposit-description {
            color: #bbb;
            font-size: 13px;
            line-height: 1.7;
            margin-bottom: 20px;
        }

        .deposit-label {
            color: #ccc;
            font-size: 12px;
            letter-spacing: 1px;
        }

        .deposit-amount {
            color: #e5c47c;
            font-size: 38px;
            font-weight: bold;
            margin: 10px 0 18px;
        }

        .payment-breakdown {
            background: #171717;
            padding: 15px;
            border-radius: 8px;
        }

        .payment-row {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            color: #bbb;
            font-size: 13px;
            margin: 10px 0;
        }

        .payment-row strong {
            color: #eee;
        }

        .payment-notice {
            color: #d4af65;
            font-size: 12px;
            line-height: 1.7;
            margin: 18px 0;
        }

        .status {
            display: inline-block;
            background: #493b21;
            color: #f0d18b;
            border: 1px solid #a9894d;
            padding: 8px 17px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: bold;
            letter-spacing: 1px;
            margin-top: 12px;
        }

        .payment-btn {
            display: block;
            width: 100%;
            padding: 16px;
            margin-top: 20px;
            background: #d4af65;
            color: #111;
            text-decoration: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: bold;
            letter-spacing: 1px;
            transition: 0.3s;
        }

        .payment-btn:hover {
            background: #f0d18b;
            transform: translateY(-2px);
        }

        .return-btn {
            display: block;
            width: 100%;
            padding: 15px;
            margin-top: 13px;
            border: 1px solid #a9894d;
            border-radius: 8px;
            color: #e5c47c;
            text-decoration: none;
            font-size: 12px;
            font-weight: bold;
            letter-spacing: 1px;
            transition: 0.3s;
        }

        .return-btn:hover {
            background: #29251d;
        }

        .footer-note {
            color: #777;
            font-size: 11px;
            line-height: 1.7;
            margin-top: 25px;
        }

        @media (max-width: 500px) {
            .confirmation {
                padding: 27px 18px;
            }

            h1 {
                font-size: 25px;
            }

            .deposit-amount {
                font-size: 32px;
            }

            .detail-row {
                font-size: 12px;
            }
        }
    </style>
</head>

<body>

<main class="confirmation">

    <div class="logo">INK STUDIO</div>

    <div class="success-icon">&#10003;</div>

    <div class="eyebrow">BOOKING SUCCESSFULLY SUBMITTED</div>

    <h1>Congratulations, <?= $safeName ?>!</h1>

    <p class="intro">
        Your tattoo journey starts here.
        Thank you for choosing INK STUDIO.
        We've successfully received your booking request.
    </p>

    <!-- BOOKING SUMMARY -->
    <div class="details">

        <div class="section-title">YOUR BOOKING SUMMARY</div>

        <div class="detail-row">
            <span>Customer</span>
            <strong><?= $safeName ?></strong>
        </div>

        <div class="detail-row">
            <span>Phone</span>
            <strong><?= $safePhone ?></strong>
        </div>

        <div class="detail-row">
            <span>Tattoo style</span>
            <strong><?= $safeDisplayType ?></strong>
        </div>

        <div class="detail-row">
            <span>Tattoo size</span>
            <strong><?= $safeSize ?></strong>
        </div>

        <div class="detail-row">
            <span>Appointment date</span>
            <strong><?= $safeDate ?></strong>
        </div>

        <div class="detail-row price-total">
            <span>Estimated tattoo price</span>
            <strong>$<?= number_format($price, 2) ?> USD</strong>
        </div>

        <div style="text-align:center;">
            <span class="status">PENDING APPROVAL</span>
        </div>

    </div>

    <!-- DEPOSIT SECTION -->
    <div class="deposit-box">

        <h2>SECURE YOUR APPOINTMENT</h2>

        <p class="deposit-description">
            A 40% deposit is required to secure your appointment
            after our team reviews and approves your booking.
        </p>

        <div class="deposit-label">YOUR REQUIRED DEPOSIT (40%)</div>

        <div class="deposit-amount">
            $<?= number_format($deposit, 2) ?> USD
        </div>

        <div class="payment-breakdown">

            <div class="payment-row">
                <span>Estimated total</span>
                <strong>$<?= number_format($price, 2) ?></strong>
            </div>

            <div class="payment-row">
                <span>Deposit (40%)</span>
                <strong>$<?= number_format($deposit, 2) ?></strong>
            </div>

            <div class="payment-row">
                <span>Remaining balance (60%)</span>
                <strong>$<?= number_format($remainingBalance, 2) ?></strong>
            </div>

        </div>

        <p class="payment-notice">
            Your booking is currently pending.
         Proceed with the deposit for our team to
            approve <!--  -->your appointment.
        </p>

        <a
            href="payment.php?booking_id=<?= (int)$bookingId ?>"
            class="payment-btn"
        >
            PAY 40% DEPOSIT &nbsp; &#8594;
        </a>

    </div>

    <!-- RETURN BUTTON -->
    <a href="tattoo3.html" class="return-btn">
        &#8592; RETURN TO INK STUDIO
    </a>

    <p class="footer-note">
        INK STUDIO &copy; <?= date("Y") ?>.
        Art that tells your story.<br>
        Your appointment is not confirmed until approved by our team.
    </p>

</main>

</body>
</html>