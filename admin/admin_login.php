
<?php
session_start();
require_once '../db.php';

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $stmt = $conn->prepare(
        "SELECT id, username, password_hash
         FROM admins WHERE username = ?"
    );

    $stmt->bind_param("s", $username);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows === 1) {

        $admin = $result->fetch_assoc();

        if (password_verify($password, $admin['password_hash'])) {

            session_regenerate_id(true);

            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];

            header("Location: admin.php");
            exit();

        } else {
            $error = "Incorrect username or password.";
        }

    } else {
        $error = "Incorrect username or password.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>INK STUDIO Admin Login</title>
    <style>
        body {
            background: #111;
            color: white;
            font-family: Arial;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }

        .login-box {
            background: #222;
            padding: 35px;
            width: 320px;
            border-radius: 10px;
            text-align: center;
        }

        input {
            width: 100%;
            padding: 12px;
            margin: 10px 0;
            box-sizing: border-box;
            border: none;
            border-radius: 5px;
        }

        button {
            width: 100%;
            padding: 12px;
            background: #c49a55;
            border: none;
            cursor: pointer;
            font-weight: bold;
            border-radius: 5px;
        }

        .error {
            color: #ff7777;
        }
    </style>
</head>

<body>

<div class="login-box">

    <h2>INK STUDIO</h2>
    <p>Administrator Login</p>

    <?php if ($error): ?>
        <p class="error">
            <?php echo htmlspecialchars($error); ?>
        </p>
    <?php endif; ?>

    <form method="POST">
        <input type="text" name="username"
               placeholder="Username" required>

        <input type="password" name="password"
               placeholder="Password" required>

        <button type="submit">LOGIN</button>
    </form>

</div>

</body>
</html>