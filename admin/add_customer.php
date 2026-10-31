<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

require_once '../db.php';

$success = "";
$error = "";

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

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $fullName = trim($_POST['fullName'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $country = trim($_POST['country'] ?? '');
    $phoneCode = trim($_POST['phoneCode'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $tattooType = trim($_POST['tattooType'] ?? '');
    $tattooSize = trim($_POST['tattooSize'] ?? '');
    $appointmentDate = trim($_POST['appointmentDate'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if (
        $fullName === '' ||
        $email === '' ||
        $country === '' ||
        $phoneCode === '' ||
        $phone === '' ||
        $tattooType === '' ||
        $tattooSize === '' ||
        $appointmentDate === ''
    ) {
        $error = "Please fill in all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (!isset($prices[$tattooType][$tattooSize])) {
        $error = "Invalid tattoo type or size.";
    } else {

        $dateObject = DateTime::createFromFormat('Y-m-d', $appointmentDate);
        $today = new DateTime('today');

        if (
            !$dateObject ||
            $dateObject->format('Y-m-d') !== $appointmentDate ||
            $dateObject < $today
        ) {
            $error = "Please select a valid future appointment date.";
        } else {

            $estimatedPrice = $prices[$tattooType][$tattooSize];
            $fullPhone = $phoneCode . $phone;

            $sql = "INSERT INTO bookings
                (
                    customer_name,
                    email,
                    country,
                    tattoo_type,
                    tattoo_size,
                    description,
                    appointment_date,
                    estimated_price,
                    amount_paid,
                    payment_status,
                    appointment_status,
                    phone
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 'Unpaid', 'Pending', ?)";

        $stmt = $conn->prepare($sql);

if (!$stmt) {

    $error = "Database error: " . $conn->error;

} else {

    $stmt->bind_param(
        "sssssssss",
        $fullName,
        $email,
        $country,
        $tattooType,
        $tattooSize,
        $description,
        $appointmentDate,
        $estimatedPrice,
        $fullPhone
    );

    if ($stmt->execute()) {

        $success = "Customer added successfully! Redirecting to dashboard in 2 seconds...";
        $_POST = [];
        $redirect = true;

    } else {

        $error = "Could not add customer: " . $stmt->error;

    }

    $stmt->close();

}
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Customer | INK STUDIO</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background: #f4f7f4;
            color: #222;
            padding: 35px 15px;
        }

        .container {
            max-width: 750px;
            margin: auto;
            background: white;
            padding: 35px;
            border-radius: 14px;
            box-shadow: 0 5px 25px rgba(0,0,0,0.08);
            border-top: 5px solid #16834a;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        .header h1 {
            color: #16834a;
            margin-bottom: 8px;
            font-size: 27px;
        }

        .header p {
            color: #777;
            font-size: 14px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .full-width {
            grid-column: 1 / -1;
        }

        label {
            font-size: 13px;
            font-weight: bold;
            margin-bottom: 8px;
            color: #333;
        }

        input, select, textarea {
            width: 100%;
            padding: 13px;
            border: 1px solid #ddd;
            border-radius: 7px;
            font-size: 14px;
            outline: none;
            background: #fff;
        }

        input:focus, select:focus, textarea:focus {
            border-color: #16834a;
            box-shadow: 0 0 0 2px rgba(22,131,74,0.1);
        }

        textarea {
            resize: vertical;
            min-height: 100px;
        }

        .phone-fields {
            display: grid;
            grid-template-columns: 110px 1fr;
            gap: 10px;
        }

        .price-box {
            background: #eaf7ef;
            border: 1px solid #b8e2c7;
            border-radius: 8px;
            padding: 17px;
            text-align: center;
            margin-top: 23px;
        }

        .price-box p {
            color: #555;
            font-size: 13px;
            margin-bottom: 6px;
        }

        .price-box h2 {
            color: #16834a;
            font-size: 27px;
        }

        .status-note {
            background: #fff8e5;
            border: 1px solid #f1d58a;
            color: #795b13;
            padding: 13px;
            border-radius: 7px;
            font-size: 13px;
            margin-top: 20px;
        }

        .buttons {
            display: flex;
            gap: 12px;
            margin-top: 25px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 14px 20px;
            border-radius: 7px;
            text-decoration: none;
            border: none;
            font-weight: bold;
            font-size: 14px;
            cursor: pointer;
            flex: 1;
        }

        .submit-btn {
            background: #16834a;
            color: white;
        }

        .submit-btn:hover {
            background: #106b3b;
        }

        .back-btn {
            background: #eeeeee;
            color: #333;
        }

        .back-btn:hover {
            background: #dddddd;
        }

        .message {
            padding: 14px;
            border-radius: 7px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .success {
            background: #e5f7eb;
            color: #126b36;
            border: 1px solid #a8dfba;
        }

        .error {
            background: #fdeaea;
            color: #a32121;
            border: 1px solid #efb1b1;
        }

        @media (max-width: 600px) {
            .container {
                padding: 23px 18px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .full-width {
                grid-column: auto;
            }

            .buttons {
                flex-direction: column;
            }
        }
    </style>
</head>

<body>

<div class="container">

    <div class="header">
        <h1>INK STUDIO</h1>
        <p>Add a new customer to your booking records</p>
    </div>

    <?php if ($success): ?>
        <div class="message success">
            <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="message error">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="">

        <div class="form-grid">

            <div class="form-group">
                <label>Customer Full Name *</label>
                <input type="text" name="fullName"
                    value="<?= htmlspecialchars($_POST['fullName'] ?? '') ?>"
                    required>
            </div>

            <div class="form-group">
                <label>Email Address *</label>
                <input type="email" name="email"
                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                    required>
            </div>

            <div class="form-group">
                <label>Country *</label>
                <input type="text" name="country"
                    value="<?= htmlspecialchars($_POST['country'] ?? '') ?>"
                    required>
            </div>

            <div class="form-group">
                <label>Phone Number *</label>
                <div class="phone-fields">
                    <input type="text" name="phoneCode"
                        placeholder="+254"
                        value="<?= htmlspecialchars($_POST['phoneCode'] ?? '') ?>"
                        required>

                    <input type="tel" name="phone"
                        placeholder="712345678"
                        value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"
                        required>
                </div>
            </div>

            <div class="form-group">
                <label>Tattoo Type *</label>
                <select name="tattooType" id="tattooType" required>
                    <option value="">Select tattoo type</option>
                    <option value="fine-line"
                        <?= ($_POST['tattooType'] ?? '') === 'fine-line' ? 'selected' : '' ?>>
                        Fine-Line
                    </option>
                    <option value="realism"
                        <?= ($_POST['tattooType'] ?? '') === 'realism' ? 'selected' : '' ?>>
                        Realism
                    </option>
                    <option value="custom"
                        <?= ($_POST['tattooType'] ?? '') === 'custom' ? 'selected' : '' ?>>
                        Custom
                    </option>
                </select>
            </div>

            <div class="form-group">
                <label>Tattoo Size *</label>
                <select name="tattooSize" id="tattooSize" required>
                    <option value="">Select size</option>
                    <option value="small"
                        <?= ($_POST['tattooSize'] ?? '') === 'small' ? 'selected' : '' ?>>
                        Small
                    </option>
                    <option value="medium"
                        <?= ($_POST['tattooSize'] ?? '') === 'medium' ? 'selected' : '' ?>>
                        Medium
                    </option>
                    <option value="large"
                        <?= ($_POST['tattooSize'] ?? '') === 'large' ? 'selected' : '' ?>>
                        Large
                    </option>
                </select>
            </div>

            <div class="form-group full-width">
                <label>Appointment Date *</label>
                <input type="date" name="appointmentDate"
                    min="<?= date('Y-m-d') ?>"
                    value="<?= htmlspecialchars($_POST['appointmentDate'] ?? '') ?>"
                    required>
            </div>

            <div class="form-group full-width">
                <label>Tattoo Description</label>
                <textarea name="description"
                    placeholder="Describe the tattoo the customer wants..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
            </div>

        </div>

        <div class="price-box">
            <p>Estimated Tattoo Price</p>
            <h2 id="estimatedPrice">$0 USD</h2>
        </div>

        <div class="status-note">
            <strong>Booking status:</strong> Pending<br>
            <strong>Payment status:</strong> Unpaid<br>
            <strong>Amount paid:</strong> $0
        </div>

        <div class="buttons">
            <a href="admin.php" class="btn back-btn">
                Back to Dashboard
            </a>

            <button type="submit" class="btn submit-btn">
                + Add Customer
            </button>
        </div>

    </form>

</div>

<script>
    const prices = {
        "fine-line": {
            "small": 50,
            "medium": 100,
            "large": 180
        },
        "realism": {
            "small": 100,
            "medium": 200,
            "large": 350
        },
        "custom": {
            "small": 80,
            "medium": 150,
            "large": 250
        }
    };

    const tattooType = document.getElementById("tattooType");
    const tattooSize = document.getElementById("tattooSize");
    const estimatedPrice = document.getElementById("estimatedPrice");

    function calculatePrice() {
        const type = tattooType.value;
        const size = tattooSize.value;

        if (type && size && prices[type][size]) {
            estimatedPrice.textContent = "$" + prices[type][size] + " USD";
        } else {
            estimatedPrice.textContent = "$0 USD";
        }
    }

    tattooType.addEventListener("change", calculatePrice);
    tattooSize.addEventListener("change", calculatePrice);

    calculatePrice();
</script>
<?php if (!empty($redirect)): ?>
<script>
    setTimeout(function() {
        window.location.href = "admin.php";
    }, 2000);
</script>
<?php endif; ?>
</body>
</html>