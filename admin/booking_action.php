<?php
session_start();
require_once "../db.php";

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: admin.php");
    exit;
}

if (
    !isset($_POST['csrf_token'], $_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    die("Security verification failed.");
}

$booking_id = filter_input(INPUT_POST, 'booking_id', FILTER_VALIDATE_INT);
$action = $_POST['action'] ?? '';

if (!$booking_id) {
    die("Invalid booking ID.");
}

if ($action === 'approve') {
    $stmt = $conn->prepare(
        "UPDATE bookings SET appointment_status = 'Approved' WHERE booking_id = ?"
    );
    $message = "Appointment approved successfully!";
} elseif ($action === 'reject') {
    $stmt = $conn->prepare(
        "UPDATE bookings SET appointment_status = 'Rejected' WHERE booking_id = ?"
    );
    $message = "Appointment rejected successfully!";
} elseif ($action === 'delete') {
    $stmt = $conn->prepare(
        "DELETE FROM bookings WHERE booking_id = ?"
    );
    $message = "Booking deleted successfully!";
} else {
    die("Invalid action.");
}

if (!$stmt) {
    die("Database error: " . $conn->error);
}

$stmt->bind_param("i", $booking_id);

if (!$stmt->execute()) {
    die("Action failed: " . $stmt->error);
}

$stmt->close();

$_SESSION['admin_flash'] = $message;

header("Location: admin.php");
exit;
?>