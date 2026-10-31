<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

require_once '../db.php';

function clean($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) {
    die("Invalid booking ID.");
}

$id = (int)$_GET['id'];

$stmt = $conn->prepare("SELECT * FROM bookings WHERE booking_id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();

$result = $stmt->get_result();
$booking = $result->fetch_assoc();

if (!$booking) {
    die("Booking not found.");
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$token = $_SESSION['csrf_token'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Booking Details | INK STUDIO</title>

<style>
* {
    box-sizing: border-box;
}

body {
    background: #101715;
    color: #f0f5f1;
    font-family: Arial, sans-serif;
    margin: 0;
    padding: 30px 15px;
}

.container {
    max-width: 850px;
    margin: auto;
    background: #1c2924;
    border: 1px solid #304239;
    padding: 30px;
    border-radius: 12px;
}

h1 {
    color: #278568;
}

.subtitle {
    color: #9bafa5;
}

.details {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
    margin: 30px 0;
}

.detail {
    background: #142019;
    padding: 15px;
    border-radius: 7px;
    overflow-wrap: anywhere;
}

.detail small {
    display: block;
    color: #9bafa5;
    margin-bottom: 8px;
}

.detail strong {
    font-size: 14px;
}

.description {
    background: #142019;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 25px;
    white-space: pre-wrap;
    overflow-wrap: anywhere;
}

.actions {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    margin-top: 25px;
}

button {
    padding: 13px 20px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-weight: bold;
}

.approve {
    background: #278568;
    color: white;
}

.reject {
    background: #a33f3f;
    color: white;
}

.delete {
    background: #552727;
    color: white;
}

.back {
    display: inline-block;
    color: #d0b47a;
    margin-bottom: 20px;
    text-decoration: none;
}

.status {
    color: #d0b47a;
    font-weight: bold;
}

@media(max-width: 600px) {
    .details {
        grid-template-columns: 1fr;
    }

    .container {
        padding: 20px;
    }
}
</style>
</head>

<body>

<div class="container">

<a href="admin.php" class="back">← Back to Dashboard</a>

<h1>Booking Details</h1>

<p class="subtitle">
Booking #<?php echo clean($booking['booking_id']); ?>
</p>

<p>
Appointment Status:
<strong class="status">
<?php echo clean($booking['appointment_status']); ?>
</strong>
</p>

<div class="details">

    <div class="detail">
        <small>Customer Name</small>
        <strong><?php echo clean($booking['customer_name']); ?></strong>
    </div>

    <div class="detail">
        <small>Email Address</small>
        <strong><?php echo clean($booking['email']); ?></strong>
    </div>

    <div class="detail">
        <small>Country</small>
        <strong><?php echo clean($booking['country']); ?></strong>
    </div>

    <div class="detail">
        <small>Phone Number</small>
        <strong><?php echo clean($booking['phone']); ?></strong>
    </div>

    <div class="detail">
        <small>Tattoo Type</small>
        <strong><?php echo clean($booking['tattoo_type']); ?></strong>
    </div>

    <div class="detail">
        <small>Tattoo Size</small>
        <strong><?php echo clean($booking['tattoo_size']); ?></strong>
    </div>

    <div class="detail">
        <small>Appointment Date</small>
        <strong><?php echo clean($booking['appointment_date']); ?></strong>
    </div>

    <div class="detail">
        <small>Estimated Price</small>
        <strong>$<?php echo number_format((float)$booking['estimated_price'], 2); ?></strong>
    </div>

    <div class="detail">
        <small>Amount Paid</small>
        <strong>$<?php echo number_format((float)$booking['amount_paid'], 2); ?></strong>
    </div>

    <div class="detail">
        <small>Payment Status</small>
        <strong><?php echo clean($booking['payment_status']); ?></strong>
    </div>

    <div class="detail">
        <small>Booking Created</small>
        <strong><?php echo clean($booking['created_at']); ?></strong>
    </div>

</div>

<h3>Tattoo Description / Customer Request</h3>

<div class="description"><?php
echo clean($booking['description'] ?: 'No description provided.');
?></div>

<h3>Manage Appointment</h3>

<div class="actions">

    <?php if ($booking['appointment_status'] !== 'Approved'): ?>
    <form method="POST" action="booking_action.php">
        <input type="hidden" name="csrf_token" value="<?php echo clean($token); ?>">
        <input type="hidden" name="booking_id" value="<?php echo $id; ?>">
        <input type="hidden" name="action" value="approve">
        <button class="approve" type="submit">Approve Booking</button>
    </form>
    <?php endif; ?>

    <?php if ($booking['appointment_status'] !== 'Rejected'): ?>
    <form method="POST" action="booking_action.php">
        <input type="hidden" name="csrf_token" value="<?php echo clean($token); ?>">
        <input type="hidden" name="booking_id" value="<?php echo $id; ?>">
        <input type="hidden" name="action" value="reject">
        <button class="reject" type="submit">Reject Booking</button>
    </form>
    <?php endif; ?>

    <form method="POST" action="booking_action.php"
          onsubmit="return confirm('Permanently delete this booking? This cannot be undone.');">
        <input type="hidden" name="csrf_token" value="<?php echo clean($token); ?>">
        <input type="hidden" name="booking_id" value="<?php echo $id; ?>">
        <input type="hidden" name="action" value="delete">
        <button class="delete" type="submit">Delete Booking</button>
    </form>

</div>

</div>

</body>
</html>