<?php

session_start();
date_default_timezone_set('Africa/Nairobi');

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

require_once '../db.php';

// --------------------------------------------------
// HELPER FUNCTIONS
// --------------------------------------------------

function clean($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function updateSessionEmailStatus($conn, $bookingId, $status) {

    $allowedStatuses = ['Sent', 'Failed'];

    if (!in_array($status, $allowedStatuses, true)) {
        return false;
    }

    $stmt = $conn->prepare(
        "UPDATE bookings
         SET session_details_email_status = ?,
             session_details_email_sent_at =
                CASE
                    WHEN ? = 'Sent' THEN NOW()
                    ELSE NULL
                END
         WHERE booking_id = ?"
    );

    if (!$stmt) {
        error_log("Email status prepare error: " . $conn->error);
        return false;
    }

    $stmt->bind_param("ssi", $status, $status, $bookingId);

    $result = $stmt->execute();

    if (!$result) {
        error_log("Email status update error: " . $stmt->error);
    }

    $stmt->close();

    return $result;
}

// --------------------------------------------------
// APPOINTMENT SLOTS
// --------------------------------------------------

$slots = [
    '08:00:00' => '8:00 AM',
    '10:00:00' => '10:00 AM',
    '12:00:00' => '12:00 PM',
    '14:00:00' => '2:00 PM',
    '16:00:00' => '4:00 PM',
    '18:00:00' => '6:00 PM',
    '20:00:00' => '8:00 PM',
    '22:00:00' => '10:00 PM'
];

$totalSlots = count($slots);

$paidStatuses = [
    'Deposit Paid',
    'Partially Paid',
    'Fully Paid',
    'Paid'
];

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

// --------------------------------------------------
// SELECT CALENDAR DATE
// --------------------------------------------------

$today = date('Y-m-d');

$selectedDate = $_GET['date'] ?? $today;

$dateObject = DateTime::createFromFormat('!Y-m-d', $selectedDate);

if (
    !$dateObject ||
    $dateObject->format('Y-m-d') !== $selectedDate
) {
    $selectedDate = $today;
    $dateObject = new DateTime($today);
}

$previousDate = (clone $dateObject)
    ->modify('-1 day')
    ->format('Y-m-d');

$nextDate = (clone $dateObject)
    ->modify('+1 day')
    ->format('Y-m-d');

// --------------------------------------------------
// SEND APPOINTMENT CHANGE EMAIL
// --------------------------------------------------

function sendAppointmentChangeEmail($booking, $newDate, $newTime) {

    $configFile = __DIR__ . '/../email_config.php';

    $mailerPath = __DIR__ .
        '/../PHPMailer-master/PHPMailer-master/src/';

    if (
        !file_exists($configFile) ||
        !file_exists($mailerPath . 'PHPMailer.php')
    ) {
        return false;
    }

    require_once $configFile;
    require_once $mailerPath . 'Exception.php';
    require_once $mailerPath . 'PHPMailer.php';
    require_once $mailerPath . 'SMTP.php';

    try {

        $mail = new PHPMailer\PHPMailer\PHPMailer(true);

        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;

        $mail->SMTPSecure =
            PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;

        $mail->Port = SMTP_PORT;

        $mail->setFrom(SENDER_EMAIL, SENDER_NAME);

        $mail->addAddress(
            $booking['email'],
            $booking['customer_name']
        );

        $mail->isHTML(true);

        $mail->Subject =
            'Your INK STUDIO Appointment Has Been Updated';

        $formattedDate = date(
            'l, d F Y',
            strtotime($newDate)
        );

        $formattedTime = date(
            'g:i A',
            strtotime($newTime)
        );

        $customer = clean($booking['customer_name']);
        $bookingId = (int)$booking['booking_id'];

        $mail->Body = "
            <div style='font-family:Arial,sans-serif;
                        max-width:600px;margin:auto;
                        padding:25px;color:#26352d;'>

                <h2 style='color:#278568;'>INK STUDIO</h2>

                <h3>Your appointment has been updated</h3>

                <p>Hello {$customer},</p>

                <p>
                    Your appointment details have been changed
                    by our studio administrator.
                </p>

                <div style='background:#f1f5f2;
                            padding:18px;border-radius:8px;'>

                    <p><strong>Booking ID:</strong> #{$bookingId}</p>
                    <p><strong>New appointment date:</strong> {$formattedDate}</p>
                    <p><strong>New appointment time:</strong> {$formattedTime}</p>

                </div>

                <p>
                    Please contact INK STUDIO if you have any
                    questions about this change.
                </p>

                <p>Thank you for choosing INK STUDIO.</p>

            </div>
        ";

        $mail->AltBody =
            "Your INK STUDIO appointment has been updated. " .
            "New date: {$formattedDate}. " .
            "New time: {$formattedTime}. " .
            "Booking ID: #{$bookingId}.";

        $mail->send();

        return true;

    } catch (Throwable $e) {

        error_log(
            "Appointment change email error: " . $e->getMessage()
        );

        return false;
    }
}

// --------------------------------------------------
// SEND SESSION DETAILS EMAIL
// --------------------------------------------------

function sendSessionDetailsEmail($booking) {

    $configFile = __DIR__ . '/../email_config.php';

    $mailerPath = __DIR__ .
        '/../PHPMailer-master/PHPMailer-master/src/';

    if (
        !file_exists($configFile) ||
        !file_exists($mailerPath . 'PHPMailer.php')
    ) {
        return false;
    }

    require_once $configFile;
    require_once $mailerPath . 'Exception.php';
    require_once $mailerPath . 'PHPMailer.php';
    require_once $mailerPath . 'SMTP.php';

    try {

        $mail = new PHPMailer\PHPMailer\PHPMailer(true);

        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;

        $mail->SMTPSecure =
            PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;

        $mail->Port = SMTP_PORT;

        $mail->setFrom(SENDER_EMAIL, SENDER_NAME);

        $mail->addAddress(
            $booking['email'],
            $booking['customer_name']
        );

        $mail->isHTML(true);

        $mail->Subject =
            'Your Confirmed INK STUDIO Session Details';

        $customer = clean($booking['customer_name']);
        $bookingId = (int)$booking['booking_id'];

        $formattedDate = date(
            'l, d F Y',
            strtotime($booking['appointment_date'])
        );

        $formattedTime = date(
            'g:i A',
            strtotime($booking['appointment_time'])
        );

        $tattooType = clean($booking['tattoo_type']);
        $tattooSize = clean($booking['tattoo_size']);

        $estimatedPrice = (float)$booking['estimated_price'];
        $amountPaid = (float)$booking['amount_paid'];

        $remainingBalance = max(
            0,
            $estimatedPrice - $amountPaid
        );

        $fullyPaid = in_array(
            $booking['payment_status'],
            ['Fully Paid', 'Paid'],
            true
        ) || $remainingBalance <= 0;

        if ($fullyPaid) {

            $paymentMessage = "
                <div style='background:#e5f5e9;
                            padding:15px;border-radius:8px;
                            margin-top:20px;'>

                    <strong style='color:#278568;'>
                        Payment Completed
                    </strong>

                    <p>
                        Thank you! Your payment is complete.
                        No outstanding balance remains.
                    </p>

                </div>
            ";

            $paymentText =
                "Your payment is complete. No outstanding balance remains.";

        } else {

            $formattedBalance = number_format(
                $remainingBalance,
                2
            );

            $paymentMessage = "
                <div style='background:#fff4df;
                            padding:15px;border-radius:8px;
                            margin-top:20px;'>

                    <strong style='color:#9b6818;'>
                        Outstanding Payment
                    </strong>

                    <p>
                        Your deposit has been received, but your
                        payment is not yet complete.
                    </p>

                    <p>
                        <strong>Remaining Balance:</strong>
                        USD {$formattedBalance}
                    </p>

                    <p>
                        Kindly complete your remaining payment
                        before your scheduled appointment.
                    </p>

                    <p>
                        Please contact INK STUDIO for payment
                        instructions.
                    </p>

                </div>
            ";

            $paymentText =
                "Your remaining balance is USD {$formattedBalance}. " .
                "Please complete payment before your appointment. " .
                "Contact INK STUDIO for payment instructions.";
        }

        $mail->Body = "
            <div style='font-family:Arial,sans-serif;
                        max-width:600px;margin:auto;
                        padding:25px;color:#26352d;'>

                <h2 style='color:#278568;'>INK STUDIO</h2>

                <h3>Your Session Details</h3>

                <p>Hello {$customer},</p>

                <p>
                    Your tattoo session has been allocated.
                    Please keep the following appointment details
                    for your records.
                </p>

                <div style='background:#f1f5f2;
                            padding:20px;border-radius:8px;
                            line-height:1.8;'>

                    <p><strong>Booking ID:</strong> #{$bookingId}</p>
                    <p><strong>Appointment Date:</strong> {$formattedDate}</p>
                    <p><strong>Appointment Time:</strong> {$formattedTime}</p>
                    <p><strong>Tattoo Type:</strong> {$tattooType}</p>
                    <p><strong>Tattoo Size:</strong> {$tattooSize}</p>

                </div>

                {$paymentMessage}

                <h3 style='color:#278568;margin-top:25px;'>
                    Before Your Session
                </h3>

                <ul style='line-height:1.8;'>
                    <li>Please arrive 10 minutes early.</li>
                    <li>Keep your booking details available.</li>
                    <li>Contact us if you need assistance.</li>
                </ul>

                <p>
                    We look forward to welcoming you to INK STUDIO.
                </p>

                <p>Thank you for choosing us.</p>

                <p style='color:#278568;font-weight:bold;'>
                    INK STUDIO
                </p>

            </div>
        ";

        $mail->AltBody =
            "Hello {$booking['customer_name']},\n\n" .
            "Your INK STUDIO session has been allocated.\n\n" .
            "Booking ID: #{$bookingId}\n" .
            "Appointment Date: {$formattedDate}\n" .
            "Appointment Time: {$formattedTime}\n" .
            "Tattoo Type: {$booking['tattoo_type']}\n" .
            "Tattoo Size: {$booking['tattoo_size']}\n\n" .
            $paymentText . "\n\n" .
            "Please arrive 10 minutes early.\n" .
            "Thank you for choosing INK STUDIO.";

        $mail->send();

        return true;

    } catch (Throwable $e) {

        error_log(
            "Session details email error: " . $e->getMessage()
        );

        return false;
    }
}

// --------------------------------------------------
// HANDLE INDIVIDUAL AND BULK EMAIL ACTIONS
// --------------------------------------------------

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['email_action'])
) {

    $token = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['admin_csrf'], $token)) {
        exit("Invalid security token. Refresh the page.");
    }

    $emailDate = $_POST['email_date'] ?? '';

    $emailDateObject = DateTime::createFromFormat(
        '!Y-m-d',
        $emailDate
    );

    if (
        !$emailDateObject ||
        $emailDateObject->format('Y-m-d') !== $emailDate
    ) {

        $_SESSION['calendar_flash'] = [
            'type' => 'error',
            'message' => 'Invalid appointment date.'
        ];

        header(
            "Location: appointment_calendar.php?date=" .
            urlencode($selectedDate)
        );

        exit();
    }

    $emailAction = $_POST['email_action'];

    $sentCount = 0;
    $failedCount = 0;

    $validTimes = array_keys($slots);

    // ----------------------------------------------
    // INDIVIDUAL EMAIL
    // ----------------------------------------------

    if ($emailAction === 'send_individual_email') {

        $emailBookingId = filter_var(
            $_POST['email_booking_id'] ?? '',
            FILTER_VALIDATE_INT
        );

        if (!$emailBookingId) {

            $_SESSION['calendar_flash'] = [
                'type' => 'error',
                'message' => 'Invalid booking selected.'
            ];

        } else {

            $stmt = $conn->prepare(
                "SELECT booking_id, customer_name, email,
                        appointment_date, appointment_time,
                        tattoo_type, tattoo_size, payment_status,
                        estimated_price, amount_paid
                 FROM bookings
                 WHERE booking_id = ?
                 AND appointment_date = ?
                 AND appointment_time IS NOT NULL
                 AND payment_status IN
                 ('Deposit Paid', 'Partially Paid',
                  'Fully Paid', 'Paid')"
            );

            $stmt->bind_param(
                "is",
                $emailBookingId,
                $emailDate
            );

            $stmt->execute();

            $booking = $stmt->get_result()->fetch_assoc();

            $stmt->close();

            if (
                !$booking ||
                !in_array($booking['appointment_time'], $validTimes, true) ||
                !filter_var($booking['email'], FILTER_VALIDATE_EMAIL)
            ) {

                $_SESSION['calendar_flash'] = [
                    'type' => 'error',
                    'message' =>
                        'This booking is not a valid allocated, paid appointment.'
                ];

            } else {

                $emailSent = sendSessionDetailsEmail($booking);

                $statusSaved = updateSessionEmailStatus(
                    $conn,
                    $emailBookingId,
                    $emailSent ? 'Sent' : 'Failed'
                );

                if ($emailSent && $statusSaved) {

                    $_SESSION['calendar_flash'] = [
                        'type' => 'success',
                        'message' =>
                            'Session details email successfully submitted for Booking #' .
                            $emailBookingId . '.'
                    ];

                } elseif ($emailSent && !$statusSaved) {

                    $_SESSION['calendar_flash'] = [
                        'type' => 'warning',
                        'message' =>
                            'Email was submitted, but its status could not be saved. Check the database.'
                    ];

                } else {

                    $_SESSION['calendar_flash'] = [
                        'type' => 'error',
                        'message' =>
                            'Email could not be sent. Check your SMTP configuration and server logs.'
                    ];
                }
            }
        }
    }

    // ----------------------------------------------
    // BULK EMAIL
    // ----------------------------------------------

    elseif ($emailAction === 'send_bulk_email') {

        $stmt = $conn->prepare(
            "SELECT booking_id, customer_name, email,
                    appointment_date, appointment_time,
                    tattoo_type, tattoo_size, payment_status,
                    estimated_price, amount_paid
             FROM bookings
             WHERE appointment_date = ?
             AND appointment_time IN (
                '08:00:00',
                '10:00:00',
                '12:00:00',
                '14:00:00',
                '16:00:00',
                '18:00:00',
                '20:00:00',
                '22:00:00'
             )
             AND payment_status IN
             ('Deposit Paid', 'Partially Paid',
              'Fully Paid', 'Paid')
             ORDER BY appointment_time ASC"
        );

        $stmt->bind_param("s", $emailDate);

        $stmt->execute();

        $result = $stmt->get_result();

        while ($booking = $result->fetch_assoc()) {

            if (
                !filter_var(
                    $booking['email'],
                    FILTER_VALIDATE_EMAIL
                )
            ) {

                updateSessionEmailStatus(
                    $conn,
                    (int)$booking['booking_id'],
                    'Failed'
                );

                $failedCount++;
                continue;
            }

            $emailSent = sendSessionDetailsEmail($booking);

            $statusSaved = updateSessionEmailStatus(
                $conn,
                (int)$booking['booking_id'],
                $emailSent ? 'Sent' : 'Failed'
            );

            if ($emailSent && $statusSaved) {

                $sentCount++;

            } else {

                $failedCount++;
            }
        }

        $stmt->close();

        if ($sentCount > 0 && $failedCount === 0) {

            $_SESSION['calendar_flash'] = [
                'type' => 'success',
                'message' =>
                    $sentCount . ' session details email(s) successfully submitted and recorded.'
            ];

        } elseif ($sentCount > 0 && $failedCount > 0) {

            $_SESSION['calendar_flash'] = [
                'type' => 'warning',
                'message' =>
                    $sentCount . ' email(s) submitted successfully. ' .
                    $failedCount . ' email(s) failed or could not be recorded.'
            ];

        } elseif ($sentCount === 0 && $failedCount > 0) {

            $_SESSION['calendar_flash'] = [
                'type' => 'error',
                'message' =>
                    'All ' . $failedCount . ' email(s) failed or could not be recorded.'
            ];

        } else {

            $_SESSION['calendar_flash'] = [
                'type' => 'warning',
                'message' =>
                    'There are no allocated, paid customers to email on this date.'
            ];
        }
    }

    else {

        $_SESSION['calendar_flash'] = [
            'type' => 'error',
            'message' => 'Invalid email action.'
        ];
    }

    header(
        "Location: appointment_calendar.php?date=" .
        urlencode($emailDate)
    );

    exit();
}

// --------------------------------------------------
// HANDLE MANUAL ALLOCATION AND RESCHEDULING
// --------------------------------------------------

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['appointment_action'])
) {

    $token = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['admin_csrf'], $token)) {
        exit("Invalid security token. Refresh the page.");
    }

    $bookingId = filter_var(
        $_POST['booking_id'] ?? '',
        FILTER_VALIDATE_INT
    );

    $newDate = $_POST['appointment_date'] ?? '';
    $newTime = $_POST['appointment_time'] ?? '';

    $validDate = DateTime::createFromFormat('!Y-m-d', $newDate);

    if (
        !$bookingId ||
        !$validDate ||
        $validDate->format('Y-m-d') !== $newDate ||
        $newDate < $today ||
        !isset($slots[$newTime])
    ) {

        $_SESSION['calendar_flash'] = [
            'type' => 'error',
            'message' => 'Invalid booking, date or appointment time.'
        ];

        header(
            "Location: appointment_calendar.php?date=" .
            urlencode($selectedDate)
        );

        exit();
    }

    $booking = null;
    $processingError = '';

    try {

        $conn->begin_transaction();

        $stmt = $conn->prepare(
            "SELECT booking_id, customer_name, email,
                    payment_status, appointment_date,
                    appointment_time
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

        if (
            !in_array(
                $booking['payment_status'],
                $paidStatuses,
                true
            )
        ) {

            throw new Exception(
                "Only customers who have successfully paid a deposit can be allocated."
            );
        }

        $oldDate = $booking['appointment_date'];
        $oldTime = $booking['appointment_time'];

        $stmt = $conn->prepare(
            "SELECT booking_id
             FROM bookings
             WHERE appointment_date = ?
             AND appointment_time = ?
             FOR UPDATE"
        );

        $stmt->bind_param("ss", $newDate, $newTime);
        $stmt->execute();

        $occupied = $stmt->get_result()->fetch_assoc();

        $stmt->close();

        if (
            $occupied &&
            (int)$occupied['booking_id'] !== $bookingId
        ) {

            throw new Exception(
                "That appointment slot has already been allocated. Choose another slot."
            );
        }

        if (
            $oldDate === $newDate &&
            $oldTime === $newTime
        ) {

            throw new Exception(
                "This customer is already allocated to that appointment slot."
            );
        }

        $stmt = $conn->prepare(
            "UPDATE bookings
             SET appointment_date = ?,
                 appointment_time = ?,
                 appointment_status = 'Approved'
             WHERE booking_id = ?"
        );

        $stmt->bind_param(
            "ssi",
            $newDate,
            $newTime,
            $bookingId
        );

        $stmt->execute();

        $stmt->close();

        $conn->commit();

    } catch (Throwable $e) {

        try {
            $conn->rollback();
        } catch (Throwable $rollbackError) {
            error_log($rollbackError->getMessage());
        }

        if (
            $e instanceof mysqli_sql_exception &&
            (int)$e->getCode() === 1062
        ) {

            $processingError =
                "That appointment slot has just been allocated to another customer. Please choose another slot.";

        } else {

            $processingError = $e->getMessage();
        }
    }

    if ($processingError !== '') {

        $_SESSION['calendar_flash'] = [
            'type' => 'error',
            'message' => $processingError
        ];

    } else {

        $emailSent = sendAppointmentChangeEmail(
            $booking,
            $newDate,
            $newTime
        );

        if ($emailSent) {

            $_SESSION['calendar_flash'] = [
                'type' => 'success',
                'message' =>
                    'Appointment updated and notification email submitted successfully.'
            ];

        } else {

            $_SESSION['calendar_flash'] = [
                'type' => 'warning',
                'message' =>
                    'Appointment updated, but the notification email could not be sent. Check your email configuration and server logs.'
            ];
        }
    }

    header(
        "Location: appointment_calendar.php?date=" .
        urlencode($selectedDate)
    );

    exit();
}

// --------------------------------------------------
// FETCH BOOKINGS FOR SELECTED DATE
// --------------------------------------------------

$bookedSlots = [];

$stmt = $conn->prepare(
    "SELECT booking_id, customer_name, email,
            tattoo_type, tattoo_size, payment_status,
            appointment_time,
            session_details_email_status,
            session_details_email_sent_at
     FROM bookings
     WHERE appointment_date = ?
     AND appointment_time IS NOT NULL"
);

$stmt->bind_param("s", $selectedDate);

$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {

    $time = $row['appointment_time'];

    if (isset($slots[$time])) {
        $bookedSlots[$time] = $row;
    }
}

$stmt->close();

// --------------------------------------------------
// CALCULATE DAILY SUMMARY
// --------------------------------------------------

$bookedCount = count($bookedSlots);

$availableCount = max(
    0,
    $totalSlots - $bookedCount
);

// --------------------------------------------------
// FETCH PAID CUSTOMERS FOR ALLOCATION
// --------------------------------------------------

$paidCustomers = $conn->query(
    "SELECT booking_id, customer_name, email,
            appointment_date, appointment_time,
            payment_status
     FROM bookings
     WHERE payment_status IN
     ('Deposit Paid', 'Partially Paid', 'Fully Paid', 'Paid')
     ORDER BY booking_id DESC"
);

// --------------------------------------------------
// FETCH NEXT 14 DAYS' BOOKED SLOT COUNTS
// --------------------------------------------------

$overviewStart = $today;

$overviewEnd = (new DateTime($today))
    ->modify('+13 days')
    ->format('Y-m-d');

$dailyCounts = [];

$stmt = $conn->prepare(
    "SELECT appointment_date,
            COUNT(DISTINCT appointment_time) AS booked_count
     FROM bookings
     WHERE appointment_date BETWEEN ? AND ?
     AND appointment_time IN (
        '08:00:00',
        '10:00:00',
        '12:00:00',
        '14:00:00',
        '16:00:00',
        '18:00:00',
        '20:00:00',
        '22:00:00'
     )
     GROUP BY appointment_date"
);

$stmt->bind_param(
    "ss",
    $overviewStart,
    $overviewEnd
);

$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {

    $dailyCounts[$row['appointment_date']] =
        (int)$row['booked_count'];
}

$stmt->close();

$dailyOverview = [];

$overviewDate = new DateTime($overviewStart);

for ($i = 0; $i < 14; $i++) {

    $day = $overviewDate->format('Y-m-d');

    $booked = $dailyCounts[$day] ?? 0;

    $dailyOverview[] = [
        'date' => $day,
        'booked' => $booked,
        'available' => max(0, $totalSlots - $booked)
    ];

    $overviewDate->modify('+1 day');
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>INK STUDIO | Appointment Calendar</title>

<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">

<style>

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    background: #101715;
    color: #F0F5F1;
    font-family: 'DM Sans', sans-serif;
}

.container {
    max-width: 1250px;
    margin: auto;
    padding: 35px 25px;
}

.top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
    margin-bottom: 30px;
}

h1 {
    font-family: 'Playfair Display', serif;
    font-size: 29px;
    color: #55BD96;
}

.subtitle {
    color: #9BAFA5;
    margin-top: 8px;
    font-size: 13px;
}

.back {
    text-decoration: none;
    color: #D0B47A;
    border: 1px solid #665735;
    padding: 11px 16px;
    border-radius: 7px;
    font-size: 13px;
}

.date-navigation {
    background: #1C2924;
    border: 1px solid #304239;
    padding: 20px;
    border-radius: 12px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
    margin-bottom: 25px;
}

.date-navigation a {
    color: #55BD96;
    text-decoration: none;
    font-size: 13px;
    font-weight: 600;
}

.date-navigation form {
    display: flex;
    gap: 10px;
    align-items: center;
}

input, select {
    background: #142019;
    color: white;
    border: 1px solid #40584A;
    border-radius: 6px;
    padding: 11px;
    outline: none;
}

button {
    background: #278568;
    color: white;
    border: none;
    padding: 11px 16px;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
}

button:hover {
    background: #329F7D;
}

.today-link {
    background: #3B3425;
    color: #E8C879 !important;
    padding: 10px 13px;
    border-radius: 6px;
}

/* DAILY SUMMARY */

.summary {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
    margin-bottom: 30px;
}

.summary-card {
    background: #1C2924;
    border: 1px solid #304239;
    padding: 20px;
    border-radius: 10px;
}

.summary-card p {
    color: #9BAFA5;
    font-size: 12px;
    margin-bottom: 10px;
}

.summary-card strong {
    font-size: 25px;
}

/* 14-DAY OVERVIEW */

.daily-overview {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 10px;
    margin-bottom: 30px;
}

.day-card {
    background: #1C2924;
    border: 1px solid #304239;
    border-radius: 9px;
    padding: 14px 10px;
    text-align: center;
    transition: 0.2s;
}

.day-card:hover {
    border-color: #55BD96;
    transform: translateY(-2px);
}

.day-card a {
    color: #55BD96;
    text-decoration: none;
    font-size: 12px;
    font-weight: 700;
}

.day-card p {
    color: #9BAFA5;
    font-size: 11px;
    margin-top: 10px;
    line-height: 1.8;
}

.day-booked {
    color: #FF9999;
}

.day-available {
    color: #70D6A4;
}

.day-card.selected-day {
    border: 1px solid #55BD96;
    background: #243B30;
}

/* SLOT GRID */

.calendar {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
}

.slot {
    background: #1C2924;
    border: 1px solid #304239;
    border-radius: 10px;
    padding: 20px;
}

.slot.available {
    border-left: 4px solid #55BD96;
}

.slot.booked {
    border-left: 4px solid #E45C5C;
    background: #291D1D;
    border-top-color: #593333;
    border-right-color: #593333;
    border-bottom-color: #593333;
}

.slot-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    margin-bottom: 15px;
}

.slot-time {
    font-size: 19px;
    font-weight: 700;
}

.slot-status {
    font-size: 10px;
    padding: 6px 10px;
    border-radius: 20px;
    font-weight: 700;
}

.available .slot-status {
    background: #1D4031;
    color: #70D6A4;
}

.booked .slot-status {
    background: #512727;
    color: #FF9999;
}

.customer {
    color: #DCE6DF;
    font-size: 13px;
    line-height: 1.9;
}

.customer strong {
    color: white;
}

.email-status {
    display: inline-block;
    padding: 5px 9px;
    border-radius: 5px;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.4px;
}

.email-status.sent {
    background: #1D4031;
    color: #70D6A4;
    border: 1px solid #278568;
}

.email-status.failed {
    background: #512727;
    color: #FF9999;
    border: 1px solid #9A4444;
}

.email-status.not-sent {
    background: #3B3425;
    color: #E8C879;
    border: 1px solid #665735;
}

.email-timestamp {
    color: #9BAFA5;
    font-size: 11px;
}

.allocate {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #304239;
}

.allocate select {
    width: 100%;
    margin-bottom: 10px;
}

.allocate button {
    width: 100%;
}

.email-form {
    margin-top: 12px;
}

.email-form button {
    width: 100%;
    background: #3B3425;
    color: #E8C879;
    border: 1px solid #665735;
}

.email-form button:hover {
    background: #55482B;
}

.bulk-email {
    background: #1C2924;
    border: 1px solid #665735;
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 22px;
}

.bulk-email p {
    color: #9BAFA5;
    font-size: 12px;
    line-height: 1.7;
    margin: 8px 0 14px;
}

.bulk-email button {
    background: #8B713B;
    color: #fff;
}

.bulk-email button:hover {
    background: #A98A49;
}

.empty-note {
    color: #9BAFA5;
    font-size: 12px;
}

/* NOTIFICATIONS */

.flash {
    padding: 15px 18px;
    border-radius: 8px;
    margin-bottom: 20px;
    font-size: 13px;
}

.success {
    background: #1D4031;
    color: #70D6A4;
    border: 1px solid #278568;
}

.error {
    background: #402626;
    color: #FFAAAA;
    border: 1px solid #704040;
}

.warning {
    background: #3B3425;
    color: #E8C879;
    border: 1px solid #665735;
}

/* INSTRUCTIONS */

.instructions {
    background: #1C2924;
    border: 1px solid #304239;
    border-radius: 10px;
    padding: 18px;
    margin-top: 25px;
    color: #9BAFA5;
    font-size: 12px;
    line-height: 1.9;
}

.footer {
    text-align: center;
    color: #71877B;
    font-size: 11px;
    margin-top: 35px;
}

/* MOBILE RESPONSIVENESS */

@media(max-width: 900px) {

    .daily-overview {
        grid-template-columns: repeat(4, 1fr);
    }

}

@media(max-width: 700px) {

    .container {
        padding: 22px 14px;
    }

    .calendar {
        grid-template-columns: 1fr;
    }

    .summary {
        grid-template-columns: 1fr;
    }

    .daily-overview {
        grid-template-columns: repeat(2, 1fr);
    }

    .date-navigation {
        align-items: flex-start;
    }

    .date-navigation form {
        width: 100%;
    }

    .date-navigation input {
        min-width: 0;
        width: 100%;
    }

}

</style>

</head>

<body>

<div class="container">

    <div class="top">

        <div>
            <h1>Appointment Calendar</h1>

            <p class="subtitle">
                Manage daily appointments and available booking slots.
            </p>
        </div>

        <a href="admin.php" class="back">
            ← Back to Dashboard
        </a>

    </div>

    <?php if (!empty($_SESSION['calendar_flash'])): ?>

        <div class="flash <?php echo clean($_SESSION['calendar_flash']['type']); ?>">

            <?php
            echo clean($_SESSION['calendar_flash']['message']);
            unset($_SESSION['calendar_flash']);
            ?>

        </div>

    <?php endif; ?>

    <div class="date-navigation">

        <a href="?date=<?php echo clean($previousDate); ?>">
            ← Previous Day
        </a>

        <form method="GET">

            <input
                type="date"
                name="date"
                value="<?php echo clean($selectedDate); ?>"
                required
            >

            <button type="submit">
                View Date
            </button>

        </form>

        <a
            class="today-link"
            href="?date=<?php echo clean($today); ?>"
        >
            Today
        </a>

        <a href="?date=<?php echo clean($nextDate); ?>">
            Next Day →
        </a>

    </div>

    <h2 style="font-family:'Playfair Display',serif;
               margin-bottom:20px;">

        <?php
        echo date(
            'l, d F Y',
            strtotime($selectedDate)
        );
        ?>

    </h2>

    <!-- DAILY SUMMARY -->

    <div class="summary">

        <div class="summary-card">
            <p>TOTAL DAILY SLOTS</p>
            <strong><?php echo $totalSlots; ?></strong>
        </div>

        <div class="summary-card">
            <p>ALLOCATED SLOTS</p>
            <strong style="color:#FF9999;">
                <?php echo $bookedCount; ?>
            </strong>
        </div>

        <div class="summary-card">
            <p>AVAILABLE SLOTS</p>
            <strong style="color:#70D6A4;">
                <?php echo $availableCount; ?>
            </strong>
        </div>

    </div>

    <!-- 14-DAY OVERVIEW -->

    <h2 style="font-family:'Playfair Display',serif;
               margin-bottom:18px;">

        Next 14 Days Overview

    </h2>

    <div class="daily-overview">

        <?php foreach ($dailyOverview as $day): ?>

            <div class="day-card <?php
                echo $day['date'] === $selectedDate
                    ? 'selected-day'
                    : '';
            ?>">

                <a href="?date=<?php echo clean($day['date']); ?>">

                    <?php
                    echo date(
                        'D, d M',
                        strtotime($day['date'])
                    );
                    ?>

                </a>

                <p>

                    <span class="day-booked">
                        Booked: <?php echo $day['booked']; ?>
                    </span>

                    <br>

                    <span class="day-available">
                        Available: <?php echo $day['available']; ?>
                    </span>

                </p>

            </div>

        <?php endforeach; ?>

    </div>

    <!-- APPOINTMENT SLOTS -->

    <h2 style="font-family:'Playfair Display',serif;
               margin-bottom:10px;">

        Appointment Slots

    </h2>

    <!-- BULK EMAIL -->

    <div class="bulk-email">

        <h3 style="color:#D0B47A;">
            ✉ Send Session Details to All Customers
        </h3>

        <p>
            Send personalized appointment details and payment reminders
            to every allocated, paid customer on
            <?php echo clean(date('d F Y', strtotime($selectedDate))); ?>.
            The email status will be saved and displayed for each customer.
        </p>

        <form method="POST">

            <input
                type="hidden"
                name="csrf_token"
                value="<?php echo clean($_SESSION['admin_csrf']); ?>"
            >

            <input
                type="hidden"
                name="email_action"
                value="send_bulk_email"
            >

            <input
                type="hidden"
                name="email_date"
                value="<?php echo clean($selectedDate); ?>"
            >

            <button
                type="submit"
                onclick="return confirm('Send session details and payment reminders to ALL allocated customers on this date?');"
            >
                ✉ Send Emails to All Allocated Customers
            </button>

        </form>

    </div>

    <div class="calendar">

        <?php foreach ($slots as $time => $label): ?>

            <?php
            $booking = $bookedSlots[$time] ?? null;
            ?>

            <div class="slot <?php
                echo $booking ? 'booked' : 'available';
            ?>">

                <div class="slot-top">

                    <div class="slot-time">
                        <?php echo clean($label); ?>
                    </div>

                    <span class="slot-status">

                        <?php
                        echo $booking
                            ? 'ALLOCATED'
                            : 'AVAILABLE';
                        ?>

                    </span>

                </div>

                <?php if ($booking): ?>

                    <div class="customer">

                        <strong>
                            <?php echo clean($booking['customer_name']); ?>
                        </strong>

                        <br>

                        Booking ID:
                        #<?php echo (int)$booking['booking_id']; ?>

                        <br>

                        Email:
                        <?php echo clean($booking['email']); ?>

                        <br>

                        Tattoo:
                        <?php echo clean($booking['tattoo_type']); ?>

                        <br>

                        Size:
                        <?php echo clean($booking['tattoo_size']); ?>

                        <br>

                        Payment:
                        <?php echo clean($booking['payment_status']); ?>

                        <br>

                        Email Status:

                        <?php
                        $emailStatus = $booking['session_details_email_status'] ?? 'Not Sent';

                        $statusClass = 'not-sent';

                        if ($emailStatus === 'Sent') {
                            $statusClass = 'sent';
                        } elseif ($emailStatus === 'Failed') {
                            $statusClass = 'failed';
                        }
                        ?>

                        <span class="email-status <?php echo $statusClass; ?>">

                            <?php echo strtoupper(clean($emailStatus)); ?>

                        </span>

                        <?php if (
                            $emailStatus === 'Sent' &&
                            !empty($booking['session_details_email_sent_at'])
                        ): ?>

                            <br>

                            <span class="email-timestamp">
                                Last successful send:
                                <?php
                                echo date(
                                    'd M Y, g:i A',
                                    strtotime(
                                        $booking['session_details_email_sent_at']
                                    )
                                );
                                ?>
                            </span>

                        <?php endif; ?>

                    </div>

                    <!-- INDIVIDUAL EMAIL -->

                    <form method="POST" class="email-form">

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?php echo clean($_SESSION['admin_csrf']); ?>"
                        >

                        <input
                            type="hidden"
                            name="email_action"
                            value="send_individual_email"
                        >

                        <input
                            type="hidden"
                            name="email_booking_id"
                            value="<?php echo (int)$booking['booking_id']; ?>"
                        >

                        <input
                            type="hidden"
                            name="email_date"
                            value="<?php echo clean($selectedDate); ?>"
                        >

                        <button
                            type="submit"
                            onclick="return confirm('Send session details and payment reminder to this customer?');"
                        >
                            ✉ Send / Resend Session Details Email
                        </button>

                    </form>

                <?php else: ?>

                    <p class="empty-note">
                        This appointment slot is available.
                    </p>

                <?php endif; ?>

                <!-- ALLOCATION AND RESCHEDULING -->

                <?php
                if (
                    $selectedDate >= $today &&
                    $paidCustomers &&
                    $paidCustomers->num_rows > 0 &&
                    !$booking
                ):
                ?>

                    <form method="POST" class="allocate">

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?php echo clean($_SESSION['admin_csrf']); ?>"
                        >

                        <input
                            type="hidden"
                            name="appointment_action"
                            value="allocate"
                        >

                        <input
                            type="hidden"
                            name="appointment_date"
                            value="<?php echo clean($selectedDate); ?>"
                        >

                        <input
                            type="hidden"
                            name="appointment_time"
                            value="<?php echo clean($time); ?>"
                        >

                        <select name="booking_id" required>

                            <option value="">
                                Select a paid customer
                            </option>

                            <?php

                            $paidCustomers->data_seek(0);

                            while (
                                $customer = $paidCustomers->fetch_assoc()
                            ):

                            ?>

                                <option value="<?php
                                    echo (int)$customer['booking_id'];
                                ?>">

                                    #<?php
                                    echo (int)$customer['booking_id'];
                                    ?>

                                    -

                                    <?php
                                    echo clean($customer['customer_name']);
                                    ?>

                                    <?php if (
                                        !empty($customer['appointment_time'])
                                    ): ?>

                                        (Currently scheduled:

                                        <?php
                                        echo clean(
                                            $customer['appointment_date']
                                        );
                                        ?>

                                        <?php
                                        echo date(
                                            'g:i A',
                                            strtotime(
                                                $customer['appointment_time']
                                            )
                                        );
                                        ?>

                                        )

                                    <?php else: ?>

                                        (Not allocated)

                                    <?php endif; ?>

                                </option>

                            <?php endwhile; ?>

                        </select>

                        <button
                            type="submit"
                            onclick="return confirm('Allocate or reschedule this customer to this slot?');"
                        >
                            Allocate Customer
                        </button>

                    </form>

                <?php endif; ?>

            </div>

        <?php endforeach; ?>

    </div>

    <!-- INSTRUCTIONS -->

    <div class="instructions">

        <strong style="color:#D0B47A;">
            How Appointment Management Works
        </strong>

        <br>

        • Each day has eight appointment slots, two hours apart.

        <br>

        • Green slots are available.

        <br>

        • Red slots are allocated to customers.

        <br>

        • Allocated slots display the customer's booking details.

        <br>

        • Only customers with successful deposits or full payments can be allocated.

        <br>

        • Selecting a customer who already has an appointment moves them to the new slot.

        <br>

        • The system checks whether a slot is occupied before saving an allocation.

        <br>

        • The database also prevents two customers from occupying the same date and time.

        <br>

        • The customer's original preferred date is preserved.

        <br>

        • An appointment-change email is attempted after a successful update.

        <br>

        • Individual email sends session details to one allocated customer.

        <br>

        • Bulk email sends personalized messages to all allocated, paid customers on the selected date.

        <br>

        • Email status is saved as SENT, FAILED or NOT SENT.

        <br>

        • The email timestamp shows the last successful session-details email submission.

        <br>

        • Customers with an outstanding balance are reminded to complete payment before their session.

        <br>

        • Fully paid customers receive a payment-completed confirmation.

        <br>

        • The 14-day overview displays booked and available slots for each day.

        <br>

        <span style="color:#9BAFA5;">
            Note: SENT means the email was accepted by your SMTP service;
            it does not guarantee delivery to the customer's inbox.
        </span>

    </div>

    <div class="footer">

        © <?php echo date('Y'); ?> INK STUDIO. All rights reserved.

    </div>

</div>

</body>
</html>