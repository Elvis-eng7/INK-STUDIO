<?php
session_start();

date_default_timezone_set('Africa/Nairobi');

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

require_once '../db.php';

// Generate CSRF protection token
if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

// Escape output safely
function clean($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// Format database dates
function formatDate($value) {
    if (empty($value)) {
        return '—';
    }

    $timestamp = strtotime($value);

    return $timestamp ? date('d M Y', $timestamp) : '—';
}

// Format date and time
function formatDateTime($value) {
    if (empty($value)) {
        return '—';
    }

    $timestamp = strtotime($value);

    return $timestamp ? date('d M Y, g:i A', $timestamp) : '—';
}

// --------------------------------------------------
// HANDLE BOOKING DELETION
// --------------------------------------------------

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['booking_action'])
) {

    $token = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['admin_csrf'], $token)) {
        exit("Invalid security token. Please refresh the page.");
    }

    $action = $_POST['booking_action'];

    if ($action === 'delete_all') {

        if (!$conn->query("DELETE FROM bookings")) {
            exit("Could not delete bookings: " . $conn->error);
        }

        if (!$conn->query("ALTER TABLE bookings AUTO_INCREMENT = 1")) {
            exit("Bookings deleted, but ID reset failed: " . $conn->error);
        }

        $_SESSION['admin_flash'] =
            "All bookings deleted successfully. Booking IDs reset to #1.";

    } elseif ($action === 'delete_unpaid') {

        $stmt = $conn->prepare(
            "DELETE FROM bookings WHERE payment_status = 'Unpaid'"
        );

        if (!$stmt || !$stmt->execute()) {
            exit("Could not delete unpaid bookings.");
        }

        $deleted = $stmt->affected_rows;
        $stmt->close();

        $_SESSION['admin_flash'] =
            $deleted . " unpaid booking(s) deleted. Paid bookings preserved.";
    }

    header("Location: admin.php");
    exit();
}

// --------------------------------------------------
// DASHBOARD STATISTICS
// --------------------------------------------------

$total = $conn->query(
    "SELECT COUNT(*) AS total FROM bookings"
)->fetch_assoc()['total'];

$pending = $conn->query(
    "SELECT COUNT(*) AS total FROM bookings
     WHERE appointment_status = 'Pending'"
)->fetch_assoc()['total'];

$approved = $conn->query(
    "SELECT COUNT(*) AS total FROM bookings
     WHERE appointment_status = 'Approved'"
)->fetch_assoc()['total'];

$rejected = $conn->query(
    "SELECT COUNT(*) AS total FROM bookings
     WHERE appointment_status = 'Rejected'"
)->fetch_assoc()['total'];

$revenue = $conn->query(
    "SELECT COALESCE(SUM(amount_paid), 0) AS revenue
     FROM bookings"
)->fetch_assoc()['revenue'];

// --------------------------------------------------
// AUTOMATIC BOOKING CONFIRMATION EMAIL STATISTICS
// --------------------------------------------------

$emailSent = $conn->query(
    "SELECT COUNT(*) AS total
     FROM bookings
     WHERE confirmation_email_sent = 1"
)->fetch_assoc()['total'];

$emailNotSent = $conn->query(
    "SELECT COUNT(*) AS total
     FROM bookings
     WHERE confirmation_email_sent = 0"
)->fetch_assoc()['total'];

// --------------------------------------------------
// SESSION DETAILS EMAIL STATISTICS
// --------------------------------------------------

$sessionEmailSent = $conn->query(
    "SELECT COUNT(*) AS total
     FROM bookings
     WHERE session_details_email_status = 'Sent'"
)->fetch_assoc()['total'];

$sessionEmailNotSent = $conn->query(
    "SELECT COUNT(*) AS total
     FROM bookings
     WHERE session_details_email_status IN ('Not Sent', 'Failed')
        OR session_details_email_status IS NULL"
)->fetch_assoc()['total'];

$sessionEmailFailed = $conn->query(
    "SELECT COUNT(*) AS total
     FROM bookings
     WHERE session_details_email_status = 'Failed'"
)->fetch_assoc()['total'];

// --------------------------------------------------
// SEARCH BOOKINGS
// --------------------------------------------------

$search = trim($_GET['search'] ?? '');

if ($search !== '') {

    $stmt = $conn->prepare(
        "SELECT * FROM bookings
         WHERE customer_name LIKE ?
         OR email LIKE ?
         OR tattoo_type LIKE ?
         OR CAST(booking_id AS CHAR) LIKE ?
         ORDER BY booking_id DESC
         LIMIT 100"
    );

    $term = "%$search%";

    $stmt->bind_param(
        "ssss",
        $term,
        $term,
        $term,
        $term
    );

    $stmt->execute();

    $bookings = $stmt->get_result();

} else {

    $bookings = $conn->query(
        "SELECT * FROM bookings
         ORDER BY booking_id DESC
         LIMIT 100"
    );
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>INK STUDIO | Admin Dashboard</title>

<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">

<style>

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

:root {
    --background: #101715;
    --sidebar: #131E19;
    --dark: #1C2924;
    --border: #304239;
    --green: #278568;
    --green-light: #55BD96;
    --gold: #D0B47A;
    --muted: #9BAFA5;
    --white: #F0F5F1;
}

body {
    background: var(--background);
    color: var(--white);
    font-family: 'DM Sans', sans-serif;
}

.layout {
    display: flex;
    min-height: 100vh;
}

/* SIDEBAR */

.sidebar {
    width: 245px;
    background: var(--sidebar);
    border-right: 1px solid var(--border);
    padding: 30px 20px;
    position: fixed;
    top: 0;
    bottom: 0;
    left: 0;
    overflow-y: auto;
}

.logo {
    font-family: 'Playfair Display', serif;
    font-size: 25px;
    color: var(--green-light);
    letter-spacing: 2px;
}

.logo-sub {
    color: var(--muted);
    font-size: 10px;
    letter-spacing: 3px;
    margin-top: 8px;
    margin-bottom: 50px;
}

.nav-title {
    color: #71877B;
    font-size: 10px;
    letter-spacing: 2px;
    margin-bottom: 15px;
}

.nav a {
    display: block;
    text-decoration: none;
    color: #A8B8AF;
    padding: 14px;
    margin-bottom: 8px;
    border-radius: 7px;
    font-size: 14px;
    transition: .3s;
}

.nav a:hover,
.nav a.active {
    background: #203B30;
    color: var(--green-light);
    border-left: 3px solid var(--green);
}

.logout-link {
    position: absolute;
    bottom: 30px;
    left: 20px;
    right: 20px;
}

/* MAIN */

.main {
    margin-left: 245px;
    width: calc(100% - 245px);
    padding: 35px 40px;
}

/* TOP BAR */

.topbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    margin-bottom: 40px;
}

.topbar h2 {
    font-family: 'Playfair Display', serif;
    font-size: 28px;
}

.topbar p {
    color: var(--muted);
    font-size: 13px;
    margin-top: 7px;
}

.admin-profile {
    display: flex;
    align-items: center;
    gap: 12px;
}

.avatar {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    background: #203B30;
    color: var(--green-light);
    display: grid;
    place-items: center;
    font-weight: bold;
}

/* HEADINGS */

.section-heading {
    font-size: 17px;
    margin-bottom: 20px;
}

/* STATISTICS */

.stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 18px;
    margin-bottom: 20px;
}

.stat-card {
    background: var(--dark);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 23px;
    transition: .3s;
}

.stat-card:hover {
    border-color: var(--green);
    transform: translateY(-3px);
}

.stat-label {
    color: var(--muted);
    font-size: 11px;
    margin-bottom: 15px;
    letter-spacing: 1px;
}

.stat-value {
    font-size: 27px;
    font-weight: 700;
}

.stat-note {
    font-size: 11px;
    color: var(--green-light);
    margin-top: 10px;
}

/* BOOKINGS SECTION */

.bookings-section {
    background: var(--dark);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 25px;
    margin-top: 25px;
}

.booking-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    margin-bottom: 25px;
    flex-wrap: wrap;
}

.booking-header h3 {
    font-size: 18px;
}

.management-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
    justify-content: flex-end;
    width: 100%;
}

.management-actions form {
    display: flex;
    margin: 0;
}

.add-customer-btn,
.delete-action {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 11px 15px;
    border-radius: 7px;
    text-decoration: none;
    font-size: 12px;
    font-weight: 600;
    border: 1px solid transparent;
    cursor: pointer;
    transition: .3s;
}

.add-customer-btn {
    background: #278568;
    color: white;
}

.add-customer-btn:hover {
    background: #329F7D;
}

.delete-all-btn {
    background: #472626;
    color: #FFAAAA;
    border-color: #704040;
}

.delete-unpaid-btn {
    background: #3B3425;
    color: #E8C879;
    border-color: #665735;
}

.delete-action:hover {
    filter: brightness(1.2);
    transform: translateY(-2px);
}

/* SEARCH */

.search-box {
    display: flex;
    gap: 8px;
}

.search-box input {
    background: #142019;
    border: 1px solid var(--border);
    color: white;
    padding: 11px;
    border-radius: 6px;
    outline: none;
    width: 220px;
}

.search-box input:focus {
    border-color: var(--green);
}

.search-box button {
    background: var(--green);
    color: white;
    border: none;
    padding: 10px 16px;
    border-radius: 6px;
    cursor: pointer;
}

.search-box button:hover {
    background: #329F7D;
}

.search-box a {
    color: var(--gold);
    text-decoration: none;
    padding: 10px;
}

/* TABLE */

.table-wrap {
    overflow-x: auto;
    width: 100%;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 1850px;
}

th {
    color: var(--muted);
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 1px;
    text-align: left;
    padding: 15px 12px;
    border-bottom: 1px solid var(--border);
    white-space: nowrap;
}

td {
    padding: 16px 12px;
    border-bottom: 1px solid #304239;
    font-size: 12px;
    color: #DCE6DF;
    white-space: nowrap;
}

tr:hover td {
    background: #23332B;
}

.customer-name {
    color: white;
    font-weight: 600;
}

.booking-id {
    color: var(--gold);
}

/* STATUS BADGES */

.status {
    display: inline-block;
    padding: 6px 10px;
    border-radius: 20px;
    font-size: 10px;
    white-space: nowrap;
}

.status-pending {
    color: #E8C879;
    background: #3B3425;
}

.status-approved {
    color: #70D6A4;
    background: #1D4031;
}

.status-rejected {
    color: #FF9292;
    background: #402626;
}

.status-unpaid {
    color: #FF9292;
    background: #402626;
}

.status-deposit-paid,
.status-partially-paid {
    color: #E8C879;
    background: #3B3425;
}

.status-paid,
.status-fully-paid {
    color: #70D6A4;
    background: #1D4031;
}

/* EMAIL STATUS */

.status-email-sent {
    color: #70D6A4;
    background: #1D4031;
}

.status-email-pending {
    color: #FFAAAA;
    background: #402626;
}

.status-email-failed {
    color: #FF9292;
    background: #542525;
}

.status-email-sending {
    color: #E8C879;
    background: #3B3425;
}

.email-time {
    display: block;
    color: var(--muted);
    font-size: 10px;
    margin-top: 7px;
}

/* ACTION BUTTON */

.view-btn {
    color: var(--green-light);
    text-decoration: none;
    font-weight: 600;
    white-space: nowrap;
}

.view-btn:hover {
    color: var(--gold);
}

.empty {
    text-align: center;
    padding: 40px;
    color: var(--muted);
}

/* POPUP */

.admin-popup {
    position: fixed;
    top: 25px;
    right: 25px;
    background: #1C2924;
    color: #55BD96;
    border: 1px solid #278568;
    padding: 16px 22px;
    border-radius: 10px;
    font-size: 14px;
    z-index: 9999;
    box-shadow: 0 8px 25px rgba(0,0,0,0.3);
    animation: popupIn 0.4s ease;
}

.admin-popup span {
    margin-right: 10px;
    font-weight: bold;
}

@keyframes popupIn {
    from {
        opacity: 0;
        transform: translateY(-15px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* FOOTER */

.footer {
    text-align: center;
    color: #71877B;
    font-size: 11px;
    margin-top: 35px;
}

/* RESPONSIVE */

@media(max-width: 1100px) {

    .stats {
        grid-template-columns: repeat(2, 1fr);
    }

    .main {
        padding: 30px 20px;
    }
}

@media(max-width: 900px) {

    .booking-header {
        align-items: flex-start;
    }

    .management-actions {
        justify-content: flex-start;
    }
}

@media(max-width: 700px) {

    .sidebar {
        position: relative;
        width: 100%;
        padding: 18px;
        border-right: none;
        border-bottom: 1px solid var(--border);
    }

    .layout {
        display: block;
    }

    .logo-sub {
        margin-bottom: 15px;
    }

    .nav-title {
        display: none;
    }

    .nav {
        display: flex;
        flex-wrap: wrap;
        gap: 5px;
    }

    .nav a {
        padding: 9px;
        margin: 0;
        font-size: 12px;
    }

    .logout-link {
        position: static;
        margin-top: 10px;
    }

    .main {
        margin-left: 0;
        width: 100%;
        padding: 22px 15px;
    }

    .topbar h2 {
        font-size: 21px;
    }

    .stats {
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
    }

    .stat-card {
        padding: 15px;
    }

    .stat-value {
        font-size: 21px;
    }

    .bookings-section {
        padding: 15px;
    }

    .management-actions {
        width: 100%;
    }

    .management-actions form,
    .management-actions button,
    .management-actions a {
        flex: 1;
    }

    .search-box {
        width: 100%;
    }

    .search-box input {
        width: 100%;
        min-width: 0;
    }

    .booking-header {
        align-items: flex-start;
    }
}

</style>

</head>

<body>

<?php if (isset($_SESSION['admin_flash'])): ?>

    <div id="adminPopup" class="admin-popup">
        <span>✓</span>
        <?php
        echo clean($_SESSION['admin_flash']);
        unset($_SESSION['admin_flash']);
        ?>
    </div>

<?php endif; ?>


<div class="layout">

<!-- SIDEBAR -->

<aside class="sidebar">

    <div class="logo">INK STUDIO</div>

    <div class="logo-sub">ADMINISTRATION</div>

    <div class="nav-title">MAIN MENU</div>

    <nav class="nav">

        <a href="#overview" class="active">
            ▦ &nbsp; Dashboard
        </a>

        <a href="#bookings">
            ▤ &nbsp; Bookings
        </a>

        <a href="appointment_calendar.php">
            ▦ &nbsp; Appointment Calendar
        </a>

        <a href="#payments">
            ◇ &nbsp; Payments
        </a>

        <a href="#email-status">
            ✉ &nbsp; Email Status
        </a>

        <a href="../tattoo3.html" target="_blank">
            ↗ &nbsp; Visit Website
        </a>

    </nav>

    <div class="logout-link">

        <nav class="nav">
            <a href="admin_logout.php">
                ↪ &nbsp; Logout
            </a>
        </nav>

    </div>

</aside>


<!-- MAIN CONTENT -->

<main class="main">


<!-- TOP BAR -->

<div class="topbar" id="overview">

    <div>

        <h2>Dashboard Overview</h2>

        <p>
            Welcome back. Here's what's happening at your studio.
        </p>

    </div>

    <div class="admin-profile">

        <div class="avatar">A</div>

        <div>

            <strong>
                <?php echo clean($_SESSION['admin_username'] ?? 'Admin'); ?>
            </strong>

            <p>Administrator</p>

        </div>

    </div>

</div>


<!-- BUSINESS SUMMARY -->

<h3 class="section-heading">
    Business Summary
</h3>

<div class="stats">

    <div class="stat-card">

        <div class="stat-label">TOTAL BOOKINGS</div>

        <div class="stat-value">
            <?php echo (int)$total; ?>
        </div>

        <div class="stat-note">
            All customer bookings
        </div>

    </div>


    <div class="stat-card">

        <div class="stat-label">PENDING APPOINTMENTS</div>

        <div class="stat-value">
            <?php echo (int)$pending; ?>
        </div>

        <div class="stat-note">
            Awaiting your decision
        </div>

    </div>


    <div class="stat-card">

        <div class="stat-label">APPROVED APPOINTMENTS</div>

        <div class="stat-value">
            <?php echo (int)$approved; ?>
        </div>

        <div class="stat-note">
            Confirmed appointments
        </div>

    </div>


    <div class="stat-card" id="payments">

        <div class="stat-label">TOTAL REVENUE RECEIVED</div>

        <div class="stat-value">
            $<?php echo number_format((float)$revenue, 2); ?>
        </div>

        <div class="stat-note">
            Recorded customer payments
        </div>

    </div>

</div>


<!-- EMAIL SUMMARY -->

<h3 class="section-heading" id="email-status">
    Email Communication Overview
</h3>

<div class="stats">

    <div class="stat-card">

        <div class="stat-label">BOOKING CONFIRMATIONS SENT</div>

        <div class="stat-value">
            <?php echo (int)$emailSent; ?>
        </div>

        <div class="stat-note">
            Automatic booking confirmation emails
        </div>

    </div>


    <div class="stat-card">

        <div class="stat-label">BOOKING CONFIRMATIONS NOT SENT</div>

        <div class="stat-value">
            <?php echo (int)$emailNotSent; ?>
        </div>

        <div class="stat-note">
            Automatic confirmation emails
        </div>

    </div>


    <div class="stat-card">

        <div class="stat-label">SESSION DETAILS EMAILS SENT</div>

        <div class="stat-value">
            <?php echo (int)$sessionEmailSent; ?>
        </div>

        <div class="stat-note">
            Sent through Appointment Calendar
        </div>

    </div>


    <div class="stat-card">

        <div class="stat-label">SESSION EMAILS NOT SENT / FAILED</div>

        <div class="stat-value">
            <?php echo (int)$sessionEmailNotSent; ?>
        </div>

        <div class="stat-note">
            <?php echo (int)$sessionEmailFailed; ?> failed email(s) recorded
        </div>

    </div>

</div>


<!-- CUSTOMER BOOKINGS -->

<section class="bookings-section" id="bookings">

    <div class="booking-header">

        <h3>Customer Bookings</h3>

        <div class="management-actions">

            <a href="add_customer.php" class="add-customer-btn">
                + Add Customer
            </a>


            <!-- DELETE ALL -->

            <form method="POST"
                onsubmit="return confirm('WARNING: This will permanently delete ALL bookings, including paid bookings. The next booking ID will reset to #1. Are you absolutely sure?');">

                <input type="hidden"
                    name="csrf_token"
                    value="<?php echo clean($_SESSION['admin_csrf']); ?>">

                <input type="hidden"
                    name="booking_action"
                    value="delete_all">

                <button type="submit"
                    class="delete-action delete-all-btn">

                    ✕ Delete All

                </button>

            </form>


            <!-- DELETE UNPAID -->

            <form method="POST"
                onsubmit="return confirm('Delete all unpaid bookings? Paid bookings will be preserved.');">

                <input type="hidden"
                    name="csrf_token"
                    value="<?php echo clean($_SESSION['admin_csrf']); ?>">

                <input type="hidden"
                    name="booking_action"
                    value="delete_unpaid">

                <button type="submit"
                    class="delete-action delete-unpaid-btn">

                    ◈ Delete Unpaid

                </button>

            </form>


            <!-- SEARCH -->

            <form method="GET" class="search-box">

                <input
                    type="text"
                    name="search"
                    placeholder="Search customers or booking ID..."
                    value="<?php echo clean($search); ?>"
                >

                <button type="submit">
                    Search
                </button>

                <?php if ($search !== ''): ?>

                    <a href="admin.php">
                        Clear
                    </a>

                <?php endif; ?>

            </form>

        </div>

    </div>


    <!-- BOOKINGS TABLE -->

    <div class="table-wrap">

        <table>

            <thead>

                <tr>

                    <th>Booking ID</th>

                    <th>Customer</th>

                    <th>Email</th>

                    <th>Phone</th>

                    <th>Tattoo</th>

                    <th>Size</th>

                    <th>Preferred Date</th>

                    <th>Allocated Date</th>

                    <th>Appointment Time</th>

                    <th>Price</th>

                    <th>Paid</th>

                    <th>Payment Status</th>

                    <th>Appointment Status</th>

                    <th>Booking Confirmation</th>

                    <th>Session Details Email</th>

                    <th>Action</th>

                </tr>

            </thead>


            <tbody>

            <?php if ($bookings && $bookings->num_rows > 0): ?>

                <?php while ($row = $bookings->fetch_assoc()): ?>

                    <?php

                    $paymentStatus =
                        $row['payment_status'] ?? 'Unpaid';

                    $appointmentStatus =
                        $row['appointment_status'] ?? 'Pending';

                    $paymentClass = strtolower(
                        str_replace(' ', '-', $paymentStatus)
                    );

                    $appointmentClass = strtolower(
                        $appointmentStatus
                    );


                    // --------------------------------
                    // AUTOMATIC BOOKING CONFIRMATION
                    // --------------------------------

                    $emailFlag = (int)(
                        $row['confirmation_email_sent'] ?? 0
                    );

                    if ($emailFlag === 1) {

                        $emailStatus = 'Sent';
                        $emailClass = 'email-sent';

                    } elseif ($emailFlag === 2) {

                        $emailStatus = 'Sending';
                        $emailClass = 'email-sending';

                    } else {

                        $emailStatus = 'Not Sent';
                        $emailClass = 'email-pending';

                    }


                    // --------------------------------
                    // SESSION DETAILS EMAIL
                    // --------------------------------

                    $sessionStatus =
                        $row['session_details_email_status'] ?? 'Not Sent';

                    if ($sessionStatus === 'Sent') {

                        $sessionEmailLabel = 'Sent';
                        $sessionEmailClass = 'email-sent';

                    } elseif ($sessionStatus === 'Failed') {

                        $sessionEmailLabel = 'Failed';
                        $sessionEmailClass = 'email-failed';

                    } else {

                        $sessionEmailLabel = 'Not Sent';
                        $sessionEmailClass = 'email-pending';

                    }

                    $sessionEmailDate =
                        $row['session_details_email_sent_at'] ?? null;

                    ?>


                    <tr>

                        <!-- BOOKING ID -->

                        <td class="booking-id">
                            #<?php echo (int)$row['booking_id']; ?>
                        </td>


                        <!-- CUSTOMER -->

                        <td class="customer-name">
                            <?php echo clean($row['customer_name']); ?>
                        </td>


                        <!-- EMAIL -->

                        <td>
                            <?php echo clean($row['email']); ?>
                        </td>


                        <!-- PHONE -->

                        <td>
                            <?php echo clean($row['phone']); ?>
                        </td>


                        <!-- TATTOO TYPE -->

                        <td>
                            <?php echo clean($row['tattoo_type']); ?>
                        </td>


                        <!-- TATTOO SIZE -->

                        <td>
                            <?php echo clean($row['tattoo_size']); ?>
                        </td>


                        <!-- PREFERRED DATE -->

                        <td>
                            <?php
                            echo formatDate(
                                $row['preferred_appointment_date'] ?? null
                            );
                            ?>
                        </td>


                        <!-- ACTUAL ALLOCATED DATE -->

                        <td>
                            <?php
                            echo formatDate(
                                $row['appointment_date'] ?? null
                            );
                            ?>
                        </td>


                        <!-- APPOINTMENT TIME -->

                        <td>

                            <?php

                            if (!empty($row['appointment_time'])) {

                                $timestamp = strtotime(
                                    $row['appointment_time']
                                );

                                echo $timestamp
                                    ? date('g:i A', $timestamp)
                                    : '—';

                            } else {

                                echo 'Not Allocated';

                            }

                            ?>

                        </td>


                        <!-- ESTIMATED PRICE -->

                        <td>
                            $<?php
                            echo number_format(
                                (float)$row['estimated_price'],
                                2
                            );
                            ?>
                        </td>


                        <!-- AMOUNT PAID -->

                        <td>
                            $<?php
                            echo number_format(
                                (float)$row['amount_paid'],
                                2
                            );
                            ?>
                        </td>


                        <!-- PAYMENT STATUS -->

                        <td>

                            <span class="status status-<?php echo clean($paymentClass); ?>">
                                <?php echo clean($paymentStatus); ?>
                            </span>

                        </td>


                        <!-- APPOINTMENT STATUS -->

                        <td>

                            <span class="status status-<?php echo clean($appointmentClass); ?>">
                                <?php echo clean($appointmentStatus); ?>
                            </span>

                        </td>


                        <!-- AUTOMATIC BOOKING CONFIRMATION EMAIL -->

                        <td>

                            <span class="status status-<?php echo clean($emailClass); ?>">
                                <?php echo clean($emailStatus); ?>
                            </span>

                        </td>


                        <!-- MANUAL SESSION DETAILS EMAIL -->

                        <td>

                            <span class="status status-<?php echo clean($sessionEmailClass); ?>">
                                <?php echo clean($sessionEmailLabel); ?>
                            </span>

                            <?php if ($sessionEmailLabel === 'Sent' && !empty($sessionEmailDate)): ?>

                                <span class="email-time">
                                    <?php echo clean(formatDateTime($sessionEmailDate)); ?>
                                </span>

                            <?php endif; ?>

                        </td>


                        <!-- VIEW DETAILS -->

                        <td>

                            <a class="view-btn"
                                href="booking_view.php?id=<?php echo (int)$row['booking_id']; ?>">

                                View Details →

                            </a>

                        </td>

                    </tr>


                <?php endwhile; ?>


            <?php else: ?>

                <tr>

                    <td colspan="16" class="empty">
                        No bookings found.
                    </td>

                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</section>


<!-- FOOTER -->

<div class="footer">

    © <?php echo date('Y'); ?> INK STUDIO. All rights reserved.

</div>


</main>

</div>


<!-- POPUP SCRIPT -->

<script>

const popup = document.getElementById("adminPopup");

if (popup) {

    setTimeout(() => {

        popup.style.transition = "opacity 0.5s";
        popup.style.opacity = "0";

        setTimeout(() => popup.remove(), 500);

    }, 3000);

}

</script>

</body>
</html>