<?php
require_once 'db.php';

$username = "admin";
$password = "ChangeThis123!";

$hash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $conn->prepare(
    "INSERT INTO admins (username, password_hash)
     VALUES (?, ?)"
);

$stmt->bind_param("ss", $username, $hash);

if ($stmt->execute()) {
    echo "Admin account created successfully!";
} else {
    echo "Error: " . $stmt->error;
}
?>