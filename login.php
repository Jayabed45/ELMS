<?php
session_start();
require 'Includes/db.php'; // DB connection file

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($user = $result->fetch_assoc()) {
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['name'] = $user['name'];

            // Redirect based on role
            if ($user['role'] === 'admin') {
                header("Location: Admin/admin_dashboard.php");
            } elseif ($user['role'] === 'head_office') {
                header("Location: Head/head_office_dashboard.php");
            } else {
                header("Location: Employee/employee_dashboard.php");
            }
            exit();
        } else {
            $error = "Invalid email or password.";
        }
    } else {
        $error = "No user found with that email.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Leave Management System</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="bg-pattern"></div>
    
    <div class="login-container">
        <div class="system-logo">
            <h1>LMS</h1>
        </div>
        
        <h2>Sign In</h2>
        
        <?php if ($error): ?>
            <p class="error"><?= $error ?></p>
        <?php endif; ?>
        
        <form method="POST">
            <input type="email" name="email" placeholder="Email Address" required />
            <input type="password" name="password" placeholder="Password" required />
            <button type="submit">Sign In</button>
        </form>
        
        <p style="margin-top:20px;">Don't have an account? <a href="signup.php">Create Account</a></p>
    </div>
</body>
</html>