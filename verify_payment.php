<?php

session_start();

date_default_timezone_set('Africa/Nairobi');

require_once __DIR__ . "/db.php";
require_once __DIR__ . "/paystack_config.php";

error_reporting(E_ALL);
ini_set('display_errors', 0);

// --------------------------------------------------
// 1. GET AND VALIDATE PAYMENT DETAILS
// --------------------------------------------------

$reference = trim($_GET['reference'] ?? $_GET['trxref'] ?? '');

$sessionReference = $_SESSION['payment_reference'] ?? '';
$bookingId = (int)($_SESSION['payment_booking_id'] ?? 0);
$expectedAmount = (int)($_SESSION['payment_expected_amount'] ?? 0);

if (
    empty($reference) ||
    empty($sessionReference) ||
    $reference !== $sessionReference ||
    $bookingId <= 0 ||
    $expectedAmount <= 0
) {
    exit("Invalid payment details. Please contact INK STUDIO.");
}

// --------------------------------------------------
// 2. VERIFY PAYMENT DIRECTLY WITH PAYSTACK
// --------------------------------------------------

$url = "https://api.paystack.co/transaction/verify/" .
       rawurlencode($reference);

$ch = curl_init($url);

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer " . $paystack_secret_key,
    "Content-Type: application/json"
]);

curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);

if ($response === false) {
    $error = curl_error($ch);
    curl_close($ch);

    error_log("Paystack connection error: " . $error);

    exit("Payment verification connection failed. Please contact INK STUDIO.");
}

$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

curl_close($ch);

$result = json_decode($response, true);

if (
    $httpCode !== 200 ||
    empty($result['status']) ||
    ($result['data']['status'] ?? '') !== 'success'
) {
    exit("Payment could not be verified. Please contact INK STUDIO.");
}

$transaction = $result['data'];

if (
    ($transaction['reference'] ?? '') !== $reference ||
    ($transaction['currency'] ?? '') !== 'KES' ||
    (int)($transaction['amount'] ?? 0) !== $expectedAmount
) {
    exit("Payment details do not match the expected booking payment.");
}

$actualKES = $transaction['amount'] / 100;

$depositUSD = (float)($_SESSION['payment_deposit_usd'] ?? 0);
$exchangeRate = (float)($_SESSION['payment_exchange_rate'] ?? 0);

if ($depositUSD <= 0 || $exchangeRate <= 0) {
    exit("Payment information is incomplete.");
}

// --------------------------------------------------
// 3. PREPARE APPOINTMENT TIME SLOTS
// --------------------------------------------------

$timeSlots = [
    '08:00:00',
    '10:00:00',
    '12:00:00',
    '14:00:00',
    '16:00:00',
    '18:00:00',
    '20:00:00',
    '22:00:00'
];

try {

    // Start database transaction
    $conn->begin_transaction();

    // Lock this booking while processing payment
    $stmt = $conn->prepare(
        "SELECT appointment_date,
                preferred_appointment_date,
                appointment_time,
                payment_status,
                paystack_reference
         FROM bookings
         WHERE booking_id = ?
         FOR UPDATE"
    );

    $stmt->bind_param("i", $bookingId);
    $stmt->execute();

    $booking = $stmt->get_result()->fetch_assoc();

    $stmt->close();

    if (!$booking) {
        throw new Exception("Booking not found.");
    }

    // Prevent a different payment reference from
    // replacing an already verified payment.
    if (
        !empty($booking['paystack_reference']) &&
        $booking['paystack_reference'] !== $reference
    ) {
        throw new Exception("Booking already has a different payment reference.");
    }

    // --------------------------------------------------
    // 4. RECORD VERIFIED PAYMENT
    // --------------------------------------------------

    $sql = "UPDATE bookings
            SET amount_paid = ?,
                amount_paid_kes = ?,
                exchange_rate = ?,
                paystack_reference = ?,
                payment_status = 'Deposit Paid',
                appointment_status = 'Approved'
            WHERE booking_id = ?";

    $stmt = $conn->prepare($sql);

    $stmt->bind_param(
        "dddsi",
        $depositUSD,
        $actualKES,
        $exchangeRate,
        $reference,
        $bookingId
    );

    $stmt->execute();
    $stmt->close();

    // --------------------------------------------------
    // 5. AUTOMATIC APPOINTMENT ALLOCATION
    // --------------------------------------------------

    // Preserve the original requested date.
    $preferredDate = $booking['preferred_appointment_date']
        ?: $booking['appointment_date'];

    // If this booking already has a time, preserve it.
    if (empty($booking['appointment_time'])) {

        $allocated = false;

        // Search from the requested date forward.
        // Maximum search period: 365 days.

        $candidateDate = new DateTime($preferredDate);

        $slotStmt = $conn->prepare(
            "UPDATE bookings
             SET preferred_appointment_date = ?,
                 appointment_date = ?,
                 appointment_time = ?
             WHERE booking_id = ?
               AND appointment_time IS NULL"
        );

        for ($day = 0; $day < 365; $day++) {

            $date = $candidateDate->format('Y-m-d');

            foreach ($timeSlots as $time) {

                try {

                    $slotStmt->bind_param(
                        "sssi",
                        $preferredDate,
                        $date,
                        $time,
                        $bookingId
                    );

                    $slotStmt->execute();

                    if ($slotStmt->affected_rows === 1) {

                        $allocated = true;
                        break 2;
                    }

                } catch (mysqli_sql_exception $e) {

                    // Error 1062 means another booking
                    // already occupies this date and time.
                    if ((int)$e->getCode() === 1062) {
                        continue;
                    }

                    throw $e;
                }
            }

            // All slots occupied: check next day.
            $candidateDate->modify('+1 day');
        }

        $slotStmt->close();

        if (!$allocated) {
            throw new Exception("No appointment slot found within 365 days.");
        }

    } else {

        // Existing allocation: preserve the appointment.
        $stmt = $conn->prepare(
            "UPDATE bookings
             SET preferred_appointment_date = ?
             WHERE booking_id = ?
               AND preferred_appointment_date IS NULL"
        );

        $stmt->bind_param("si", $preferredDate, $bookingId);
        $stmt->execute();
        $stmt->close();
    }

    // Save payment and appointment together.
    $conn->commit();

} catch (Throwable $e) {

    $conn->rollback();

    error_log("INK STUDIO payment/allocation error: " . $e->getMessage());

    exit("Payment was verified, but appointment processing needs attention. Please contact INK STUDIO.");
}

// --------------------------------------------------
// 6. GET FINAL BOOKING DETAILS
// --------------------------------------------------

$stmt = $conn->prepare(
    "SELECT booking_id,
            customer_name,
            email,
            appointment_date,
            preferred_appointment_date,
            appointment_time,
            tattoo_type,
            tattoo_size,
            estimated_price,
            amount_paid,
            amount_paid_kes
     FROM bookings
     WHERE booking_id = ?"
);

$stmt->bind_param("i", $bookingId);
$stmt->execute();

$bookingDetails = $stmt->get_result()->fetch_assoc();

$stmt->close();

// --------------------------------------------------
// 7. PREVENT DUPLICATE CONFIRMATION EMAILS
// --------------------------------------------------

// 0 = not sent
// 1 = successfully sent
// 2 = currently being sent

$sendEmail = false;

$stmt = $conn->prepare(
    "UPDATE bookings
     SET confirmation_email_sent = 2
     WHERE booking_id = ?
       AND confirmation_email_sent = 0"
);

$stmt->bind_param("i", $bookingId);
$stmt->execute();

if ($stmt->affected_rows === 1) {
    $sendEmail = true;
}

$stmt->close();

// --------------------------------------------------
// 8. SEND APPOINTMENT CONFIRMATION EMAIL
// --------------------------------------------------

if ($sendEmail && $bookingDetails) {

    try {

        require_once __DIR__ .
            '/PHPMailer-master/PHPMailer-master/src/Exception.php';

        require_once __DIR__ .
            '/PHPMailer-master/PHPMailer-master/src/PHPMailer.php';

        require_once __DIR__ .
            '/PHPMailer-master/PHPMailer-master/src/SMTP.php';

        require_once __DIR__ . '/email_config.php';

        $mail = new PHPMailer\PHPMailer\PHPMailer(true);

        $mail->isSMTP();

        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;

        $mail->CharSet = 'UTF-8';

        $mail->setFrom(SENDER_EMAIL, SENDER_NAME);

        $mail->addAddress(
            $bookingDetails['email'],
            $bookingDetails['customer_name']
        );

        $mail->isHTML(true);

        $mail->Subject = "INK STUDIO - Your Tattoo Appointment is Confirmed";

        // Escape customer-provided information.
        $customerName = htmlspecialchars(
            $bookingDetails['customer_name'],
            ENT_QUOTES,
            'UTF-8'
        );

        $bookingNumber = (int)$bookingDetails['booking_id'];

        $tattooType = htmlspecialchars(
            $bookingDetails['tattoo_type'],
            ENT_QUOTES,
            'UTF-8'
        );

        $tattooSize = htmlspecialchars(
            $bookingDetails['tattoo_size'],
            ENT_QUOTES,
            'UTF-8'
        );

        $appointmentDate = date(
            'l, d F Y',
            strtotime($bookingDetails['appointment_date'])
        );

        $appointmentTime = date(
            'g:i A',
            strtotime($bookingDetails['appointment_time'])
        );

        $preferredDate = date(
            'd F Y',
            strtotime($bookingDetails['preferred_appointment_date'])
        );

        $actualDate = date(
            'd F Y',
            strtotime($bookingDetails['appointment_date'])
        );

        $rescheduledMessage = '';

        if (
            $bookingDetails['preferred_appointment_date'] !==
            $bookingDetails['appointment_date']
        ) {

            $rescheduledMessage = "
                <p>
                    Your originally requested date was
                    <strong>{$preferredDate}</strong>.
                    Unfortunately, all appointment slots were occupied.
                    We have therefore allocated your appointment to
                    <strong>{$actualDate}</strong>.
                </p>
            ";
        }

        $deposit = number_format(
            (float)$bookingDetails['amount_paid'],
            2
        );

        $paidKES = number_format(
            (float)$bookingDetails['amount_paid_kes'],
            2
        );

        $mail->Body = "
        <div style='font-family:Arial,sans-serif;
                    max-width:600px;
                    margin:auto;
                    padding:25px;
                    color:#333;
                    border:1px solid #ddd;'>

            <h1 style='color:#145c42;text-align:center;'>
                INK STUDIO
            </h1>

            <h2 style='text-align:center;'>
                Your Appointment is Confirmed!
            </h2>

            <p>Hello {$customerName},</p>

            <p>
                Congratulations! Your deposit has been
                successfully received and your tattoo
                appointment has been approved.
            </p>

            {$rescheduledMessage}

            <div style='background:#f2f7f4;
                        padding:20px;
                        border-radius:8px;'>

                <h3>YOUR APPOINTMENT DETAILS</h3>

                <p><strong>Booking ID:</strong> #{$bookingNumber}</p>

                <p><strong>Appointment Date:</strong> {$appointmentDate}</p>

                <p><strong>Appointment Time:</strong> {$appointmentTime}</p>

                <p><strong>Tattoo Type:</strong> {$tattooType}</p>

                <p><strong>Tattoo Size:</strong> {$tattooSize}</p>

                <p><strong>Deposit Paid:</strong> \${$deposit} USD</p>

                <p><strong>Payment Received:</strong> KSh {$paidKES}</p>

                <p><strong>Payment Status:</strong> Deposit Paid</p>

                <p><strong>Time Zone:</strong> East Africa Time (EAT)</p>

            </div>

            <p>
                Please keep this email for your records.
                We look forward to bringing your tattoo idea to life!
            </p>

            <p style='text-align:center;
                      color:#145c42;
                      font-weight:bold;'>
                INK STUDIO — ART THAT TELLS YOUR STORY
            </p>

        </div>
        ";

        $mail->AltBody =
            "Hello {$bookingDetails['customer_name']},\n\n" .
            "Your INK STUDIO appointment is confirmed.\n" .
            "Booking ID: #{$bookingNumber}\n" .
            "Date: {$appointmentDate}\n" .
            "Time: {$appointmentTime} EAT\n" .
            "Deposit paid: \${$deposit} USD\n";

        $mail->send();

        // Mark email as successfully sent.
        $stmt = $conn->prepare(
            "UPDATE bookings
             SET confirmation_email_sent = 1
             WHERE booking_id = ?"
        );

        $stmt->bind_param("i", $bookingId);
        $stmt->execute();
        $stmt->close();

    } catch (Throwable $e) {

        // Keep payment and appointment intact.
        // Allow a future retry if email sending failed.

        error_log("INK STUDIO email error: " . $e->getMessage());

        $stmt = $conn->prepare(
            "UPDATE bookings
             SET confirmation_email_sent = 0
             WHERE booking_id = ?
               AND confirmation_email_sent = 2"
        );

        $stmt->bind_param("i", $bookingId);
        $stmt->execute();
        $stmt->close();
    }
}

// --------------------------------------------------
// 9. REDIRECT TO SUCCESS PAGE
// --------------------------------------------------

$conn->close();

header(
    "Location: payment_success.php?booking_id=" .
    (int)$bookingId
);

exit;

?>