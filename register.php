<?php
require 'config.php';
require 'vendor/autoload.php'; // Include Composer autoloader

$pdo = getDatabaseConnection(); // Use the new function

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$message = '';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $email = $_POST['email'];
    $token = bin2hex(random_bytes(16)); // Generate a unique token

    // Password recommendations
    if ($password !== $confirm_password) {
        $message = 'Passwords do not match.';
    } elseif (strlen($password) < 8) {
        $message = 'Password must be at least 8 characters long.';
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $message = 'Password must contain at least one uppercase letter.';
    } elseif (!preg_match('/[a-z]/', $password)) {
        $message = 'Password must contain at least one lowercase letter.';
    } elseif (!preg_match('/[0-9]/', $password)) {
        $message = 'Password must contain at least one number.';
    } elseif (!preg_match('/[\W]/', $password)) {
        $message = 'Password must contain at least one special character.';
    } else {
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);
        try {
            $stmt = $pdo->prepare('INSERT INTO users (username, password, email, token, is_verified, is_locked) VALUES (?, ?, ?, ?, 0, 0)');
            $stmt->execute([$username, $hashed_password, $email, $token]);

            // Send verification email using PHPMailer
            $mail = new PHPMailer(true);
            try {
                // Server settings
                $mail->isSMTP();
                $mail->Host = 'web28.alfahosting-server.de';
                $mail->SMTPAuth = true;
                $mail->Username = 'journal@3-21.net';
                $mail->Password = '@8zK@5o##Nysmnad';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                $mail->Port = 465;

                // Recipients
                $mail->setFrom('no-reply@journal.hopto.org', 'Trade Journal');
                $mail->addAddress($email);

                // Content
                $mail->isHTML(true);
                $mail->Subject = 'Email Verification';
                $verification_link = "http://journal.hopto.org/verify.php?token=$token";
                $mail->Body = "Please click the following link to verify your email: <a href='$verification_link'>$verification_link</a>";

                $mail->send();
                $message = "A verification email has been sent to $email";
            } catch (Exception $e) {
                $message = "Failed to send verification email. Mailer Error: {$mail->ErrorInfo}";
            }

            header('Location: http://journal.hopto.org/login.php'); // Updated URL
        } catch (PDOException $e) {
            $message = 'Error: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Trade Journal</title>
    <link rel="stylesheet" href="css/responsive.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background: url('images/image.jpg') no-repeat center center fixed;
            background-size: cover;
            margin: 0;
            padding: 0;
            color: #fff;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        .container {
            max-width: 400px;
            padding: 20px;
            background-color: rgba(0, 0, 0, 0.8);
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.5);
            border-radius: 8px;
            text-align: center;
        }
        h1 {
            color: #fff;
        }
        label {
            display: block;
            margin-bottom: 5px;
            color: #ddd;
        }
        input, button {
            width: 100%;
            padding: 10px;
            margin-bottom: 10px;
            border: 1px solid #555;
            border-radius: 4px;
            box-sizing: border-box;
            background-color: #333;
            color: #fff;
        }
        button {
            background-color: #28a745;
            color: #fff;
            border: none;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        button:hover {
            background-color: #218838;
        }
        a {
            color: #007bff;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
        .message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 10px;
            margin-bottom: 10px;
            border: 1px solid #f5c6cb;
            border-radius: 4px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Register</h1>
        <?php if ($message): ?>
            <div class="message"><?php echo $message; ?></div>
        <?php endif; ?>
        <form method="post" action="">
            <label for="username">Username:</label>
            <input type="text" id="username" name="username" required>
            <label for="password">Password:</label>
            <input type="password" id="password" name="password" required>
            <label for="confirm_password">Confirm Password:</label>
            <input type="password" id="confirm_password" name="confirm_password" required>
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" required>
            <button type="submit">Register</button>
        </form>
        <p>Already have an account? <a href="http://journal.hopto.org/login.php">Login here</a></p> <!-- Updated URL -->
    </div>
</body>
</html>
