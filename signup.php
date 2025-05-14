<?php
require 'Includes/db.php';

$success = '';
$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $role = $_POST['role'];

    // Check if email already exists
    $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows > 0) {
        $error = "Email already registered.";
    } else {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $name, $email, $hashedPassword, $role);

        if ($stmt->execute()) {
            $success = "Account created successfully. You can now <a href='login.php'>log in</a>.";
        } else {
            $error = "Something went wrong. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - Leave Management System</title>
    <link rel="stylesheet" href="Assets/css/style.css">
</head>
<body>
    <div class="bg-pattern"></div>
    
    <div class="signup-container">
        <div class="system-logo">
            <h1>ELMS</h1>
        </div>
        
        <h2>Create Account</h2>
        
        <?php if ($error): ?>
            <p class="error"><?= $error ?></p>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <p class="success"><?= $success ?></p>
        <?php endif; ?>
        
        <form method="POST">
            <input type="text" name="name" placeholder="Full Name" required />
            <input type="email" name="email" placeholder="Email Address" required />
            <input type="password" name="password" placeholder="Password" required />
            <select name="role" required>
                <option value="">Select Your Role</option>
                <option value="admin">Admin</option>
                <option value="head_office">Head Office</option>
                <option value="employee">Employee</option>
            </select>
            <button type="submit">Create Account</button>
        </form>
        
        <p style="margin-top:20px;">Already have an account? <a href="login.php">Sign In</a></p>
    </div>
</body>
</html>