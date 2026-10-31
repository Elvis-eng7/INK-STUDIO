<?php
ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once "db.php";
require_once "paystack_config.php";

// Check booking ID
$bookingId = filter_input(INPUT_GET, "booking_id", FILTER_VALIDATE_INT);

if (!$bookingId) {
    exit("Invalid booking reference.");
}

// Retrieve booking from database
$sql = "SELECT booking_id, customer_name, email, estimated_price,
        amount_paid, payment_status
        FROM bookings WHERE booking_id = ? LIMIT 1";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    exit("Database error.");
}

$stmt->bind_param("i", $bookingId);
$stmt->execute();

$result = $stmt->get_result();
$booking = $result->fetch_assoc();

$stmt->close();

if (!$booking) {
    exit("Booking not found.");
}

if ($booking["payment_status"] === "Paid") {
    exit("This booking has already been paid.");
}

// Calculate deposit from database price
// Calculate 40% deposit in USD
$deposit = round((float)$booking["estimated_price"] * 0.40, 2);
// Display payment amounts
echo "<h3>INK STUDIO PAYMENT SUMMARY</h3>";

echo "Tattoo deposit: $" .
     number_format($deposit, 2) . " USD<br>";

echo "Amount payable: KSh " .
     number_format($depositKES, 2) . "<br>";

// Convert USD deposit to KES
$depositKES = round($deposit * $usd_to_kes_rate, 2);

// Convert KES to Paystack's smallest currency unit
$amountInKobo = (int) round($depositKES * 100);

// Generate a unique payment reference
$reference = "INK_" . $bookingId . "_" . bin2hex(random_bytes(6));

// Store reference in session
session_start();
$_SESSION["payment_reference"] = $reference;
$_SESSION["payment_booking_id"] = $bookingId;
$_SESSION["payment_expected_amount"] = $amountInKobo;
$_SESSION["payment_deposit_usd"] = $deposit;
$_SESSION["payment_exchange_rate"] = $usd_to_kes_rate;

// Initialize Paystack transaction
$data = [
    "email" => $booking["email"],
    "amount" => $amountInKobo,
    "currency" => "KES",
    "reference" => $reference,
    "callback_url" => "http://localhost/html/verify_payment.php"
];

$ch = curl_init("https://api.paystack.co/transaction/initialize");

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer " . $paystack_secret_key,
    "Content-Type: application/json"
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

$response = curl_exec($ch);

if ($response === false) {
    $error = curl_error($ch);
    curl_close($ch);
    exit("Payment connection failed: " . htmlspecialchars($error));
}

$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

curl_close($ch);

$responseData = json_decode($response, true);

// TEMPORARY DEBUGGING
if (
    empty($responseData["status"]) ||
    empty($responseData["data"]["authorization_url"])
) {
    echo "<h3>Paystack Debug Information</h3>";

    echo "HTTP Status: " . $httpCode . "<br>";

    echo "<pre>";
    print_r($responseData);
    echo "</pre>";

    exit;
}

// Redirect customer to Paystack
header("Location: " . $responseData["data"]["authorization_url"]);
exit;