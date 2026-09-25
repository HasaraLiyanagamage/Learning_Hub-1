<?php
session_start();
require_once 'db_connect.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Input Sanitization & Empty Field Validation
    $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
    $password = trim($_POST['password']);

    if (empty($email) || empty($password)) {
        $error = "Please fill in all fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } else {
        // SQL Injection Prevention using Prepared Statements
        $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // 🔐 Password Verification (තාවකාලිකව කෙලින්ම චෙක් කිරීම)
            if ($password === "admin123") {
                // Session Handling initialization
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];

                header("Location: dashboard.php");
                exit();
            } else {
                $error = "Incorrect password.";
            }
        } else {
            $error = "No user found with that email.";
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orion College - Hostel Login</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="login-container">
        <h2>Orion College Hostel Management System</h2>
        <p>Sign in to manage your reservations</p>
        
        <?php if (!empty($error)): ?>
            <div class="error-msg"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form action="login.php" method="POST" id="loginForm">
            <div class="input-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required>
            </div>
            
            <div class="input-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <button type="submit" class="btn-submit">Login</button>
        </form>
    </div>

    <script src="script.js"></script>
</body>
</html>