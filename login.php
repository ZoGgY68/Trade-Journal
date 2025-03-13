<?php
session_start();
require 'config.php';

$pdo = getDatabaseConnection(); // Use the new function

$message = '';

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Rate limiting
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
}
if (!isset($_SESSION['last_login_attempt'])) {
    $_SESSION['last_login_attempt'] = time();
}

// Track IP-based login attempts
$client_ip = $_SERVER['REMOTE_ADDR'];
$ip_key = 'ip_' . str_replace('.', '_', $client_ip);
if (!isset($_SESSION[$ip_key])) {
    $_SESSION[$ip_key] = 0;
}
if (!isset($_SESSION[$ip_key . '_time'])) {
    $_SESSION[$ip_key . '_time'] = time();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $message = 'Invalid request. Please try again.';
    } else {
        // Sanitize input
        $username = filter_var(trim($_POST['username']), FILTER_SANITIZE_STRING);
        
        // Check for IP-based rate limiting first (stricter)
        $ip_attempts = $_SESSION[$ip_key];
        $ip_time_diff = time() - $_SESSION[$ip_key . '_time'];
        $cooldown_time = min(pow(2, $ip_attempts) * 30, 3600); // Exponential backoff, max 1 hour
        
        if ($ip_attempts >= 10 && $ip_time_diff < $cooldown_time) {
            $message = 'Too many login attempts from this IP. Please try again later.';
        } else {
            $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ?');
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && $user['is_locked'] && time() - strtotime($user['locked_at']) < 600) {
                $message = 'Your account is locked. Please try again after 10 minutes.';
            } else {
                if ($user && $user['is_locked']) {
                    $stmt = $pdo->prepare('UPDATE users SET is_locked = 0, locked_at = NULL WHERE username = ?');
                    $stmt->execute([$username]);
                }

                if ($_SESSION['login_attempts'] >= 5 && time() - $_SESSION['last_login_attempt'] < 300) {
                    $message = 'Too many login attempts. Please try again after 5 minutes.';
                } else {
                    $password = $_POST['password'];

                    if ($user && password_verify($password, $user['password'])) {
                        if (!$user['is_verified']) {
                            $message = 'Please verify your email address before logging in.';
                        } else {
                            $_SESSION['user_id'] = $user['id'];
                            $_SESSION['username'] = $user['username']; // Store username in session
                            $_SESSION['login_attempts'] = 0; // Reset login attempts on successful login
                            $_SESSION[$ip_key] = 0; // Reset IP-based attempts on successful login
                            
                            // Regenerate session ID to prevent session fixation
                            session_regenerate_id(true);
                            
                            header('Location: http://trading.3-21.eu/data_entry.php');
                            exit;
                        }
                    } else {
                        $_SESSION['login_attempts']++;
                        $_SESSION['last_login_attempt'] = time();
                        $_SESSION[$ip_key]++;
                        $_SESSION[$ip_key . '_time'] = time();
                        $message = 'Invalid username or password';

                        // Lock account after 10 failed attempts
                        if ($user && $_SESSION['login_attempts'] >= 10) {
                            $stmt = $pdo->prepare('UPDATE users SET is_locked = 1, locked_at = NOW() WHERE username = ?');
                            $stmt->execute([$username]);
                            $message = 'Your account has been locked due to too many failed login attempts. Please try again after 10 minutes.';
                        }
                    }
                }
            }
        }
    }
}

// Generate a nonce for inline scripts
$nonce = bin2hex(random_bytes(16));

// Security headers
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-$nonce'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com;");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>3-21 Trading Journal</title>
    <link rel="stylesheet" href="css/responsive.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', Arial, sans-serif;
            background: url('images/image.jpg') no-repeat center center fixed;
            background-size: cover;
            margin: 0;
            padding: 0;
            color: #fff;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        .header-description {
            text-align: center;
            padding: 20px;
            background-color: rgba(0, 0, 0, 0.7);
            margin-bottom: 10px;
        }
        .header-description h1 {
            font-weight: 600;
            font-size: 28px;
            margin-bottom: 10px;
            color: #4CAF50;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.7);
        }
        .header-description p {
            font-size: 16px;
            line-height: 1.6;
            max-width: 900px;
            margin: 0 auto;
            font-weight: 400;
            color: #f0f0f0;
        }
        .main-content {
            display: flex;
            flex: 1;
        }
        .left-container, .middle-container, .right-container {
            height: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .left-container, .right-container {
            width: 35%;
            position: relative;
        }
        .middle-container {
            width: 30%;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        .container {
            max-width: 380px;
            width: 380px;
            padding: 20px;
            background-color: rgba(0, 0, 0, 0.8);
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.5);
            border-radius: 8px;
            text-align: center;
            max-height: 80%;
            overflow-y: auto;
            margin: 0 auto; /* Center horizontally */
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
        
        /* Ensure page content doesn't overflow */
        body, html {
            overflow-x: hidden;
        }

        /* Fireworks CSS */
        .pyro {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            pointer-events: none;
        }
        .pyro > .before, .pyro > .after {
            position: absolute;
            width: 5px;
            height: 5px;
            border-radius: 50%;
            box-shadow: 0 0 #fff, 0 0 #fff, 0 0 #fff, 0 0 #fff, 0 0 #fff, 0 0 #fff;
            animation: 1s bang ease-out infinite backwards, 1s gravity ease-in infinite backwards, 5s position linear infinite backwards;
        }
        .pyro > .after {
            animation-delay: 1.25s, 1.25s, 1.25s;
            animation-duration: 1.25s, 1.25s, 6.25s;
        }
        @keyframes bang {
            to {
                box-shadow: -70px -115.67px #00ff84, 25px -82.67px #ff009d, -58px -13.67px #0026ff, 58px 57.33px #ff006e, -63px -91.67px #ff0059, -15px 43.33px #ff00d5, -85px 43.33px #ff6600, 57px -31.67px #88ff00, 69px -75.67px #ff00bf, 10px -60.67px #2bff00;
            }
        }
        @keyframes gravity {
            to {
                transform: translateY(200px);
                opacity: 0;
            }
        }
        @keyframes position {
            0%, 19.9% { margin-top: 10%; margin-left: 40%; }
            20%, 39.9% { margin-top: 40%; margin-left: 30%; }
            40%, 59.9% { margin-top: 20%; margin-left: 70%; }
            60%, 79.9% { margin-top: 30%; margin-left: 20%; }
            80%, 99.9% { margin-top: 30%; margin-left: 80%; }
        }
        
        /* Enhanced input fields with animations */
        .input-group {
            position: relative;
            margin-bottom: 20px;
        }
        
        .input-group input {
            width: 100%;
            padding: 10px;
            border: none;
            border-bottom: 2px solid #555;
            background-color: transparent;
            color: #fff;
            transition: all 0.3s ease;
            border-radius: 0;
        }
        
        .input-group input:focus {
            outline: none;
            border-bottom: 2px solid #28a745;
            box-shadow: 0 4px 6px rgba(40, 167, 69, 0.2);
        }
        
        .input-group label {
            position: absolute;
            top: 10px;
            left: 10px;
            color: #999;
            transition: all 0.3s ease;
            pointer-events: none;
        }
        
        .input-group input:focus + label,
        .input-group input:not(:placeholder-shown) + label {
            top: -20px;
            left: 5px;
            font-size: 12px;
            color: #28a745;
        }
        
        /* Pulse animation for login button */
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        .pulse-button {
            animation: pulse 2s infinite;
        }
        
        /* Loading spinner */
        .spinner {
            display: none;
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 0.8s ease infinite;
            margin: 0 auto;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Remember me checkbox */
        .checkbox-group {
            display: flex;
            align-items: center;
            justify-content: flex-start;
            margin-bottom: 15px;
        }
        
        .checkbox-group input {
            width: auto;
            margin-right: 10px;
        }
        
        /* Trading theme elements */
        .chart-line {
            height: 50px;
            width: 100%;
            margin: 20px 0;
            position: relative;
            overflow: hidden;
        }
        
        .chart-line svg {
            width: 100%;
            height: 100%;
        }
        
        .chart-line path {
            stroke: #28a745;
            stroke-width: 2;
            fill: none;
            stroke-dasharray: 1000;
            stroke-dashoffset: 1000;
            animation: dash 3s linear forwards;
        }
        
        @keyframes dash {
            to { stroke-dashoffset: 0; }
        }
        
        /* Time of day greeting */
        .greeting {
            font-size: 18px;
            margin-bottom: 20px;
            color: #28a745;
            font-weight: 600;
        }
        
        /* Enhanced fireworks */
        .pyro > .before, .pyro > .after {
            box-shadow: 0 0 #28a745, 0 0 #28a745, 0 0 #28a745, 0 0 #28a745, 0 0 #fff, 0 0 #fff;
        }
    </style>
    <!-- Force refresh with no cache -->
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
</head>
<body>
    <!-- Fireworks effect -->
    <div class="pyro">
        <div class="before"></div>
        <div class="after"></div>
    </div>

    <div class="header-description">
        <h1>3-21 Trading Journal</h1>
        <p>Trade Journal is a comprehensive tool designed to help traders keep track of their trades, analyze performance, and improve their trading strategies.</p>
    </div>
    
    <div class="main-content">
        <div class="left-container">
            <!-- Removed TradingView Container -->
        </div>
        
        <div class="middle-container">
            <div class="container">
                <div class="greeting" id="greeting"></div>
                <h1>Login</h1>
                
                <!-- Trading chart animation -->
                <div class="chart-line">
                    <svg viewBox="0 0 500 100">
                        <path d="M0,50 Q50,30 100,50 T200,50 T300,20 T400,50 T500,40"></path>
                    </svg>
                </div>
                
                <?php if ($message): ?>
                    <div class="message"><?php echo htmlspecialchars($message); ?></div>
                <?php endif; ?>
                
                <form action="login.php" method="POST" id="loginForm">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    
                    <div class="input-group">
                        <input type="text" id="username" name="username" placeholder=" " required>
                        <label for="username">Username</label>
                    </div>
                    
                    <div class="input-group">
                        <input type="password" id="password" name="password" placeholder=" " required>
                        <label for="password">Password</label>
                    </div>
                    
                    <div class="checkbox-group">
                        <input type="checkbox" id="remember" name="remember">
                        <label for="remember">Remember me</label>
                    </div>
                    
                    <button type="submit" class="pulse-button" id="loginBtn">Login</button>
                    <div class="spinner" id="loadingSpinner"></div>
                </form>
                
                <p>Don't have an account? <a href="http://trading.3-21.eu/register.php">Register here</a></p>
                <p><a href="http://trading.3-21.eu/forgot_password.php">Forgot Password?</a></p>
            </div>
        </div>
        
        <div class="right-container">
            <!-- Removed TradingView Container -->
        </div>
    </div>

    <script nonce="<?php echo $nonce; ?>">
        // Time-based greeting
        function getGreeting() {
            const hour = new Date().getHours();
            let greeting = "";
            if (hour < 12) greeting = "Good Morning";
            else if (hour < 18) greeting = "Good Afternoon";
            else greeting = "Good Evening";
            return greeting + ", Trader!";
        }
        
        document.getElementById('greeting').textContent = getGreeting();
        
        // Form submission with loading indicator
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            document.getElementById('loginBtn').style.display = 'none';
            document.getElementById('loadingSpinner').style.display = 'block';
        });
        
        // Enhanced fireworks effect
        document.addEventListener('DOMContentLoaded', function() {
            function createFirework() {
                const firework = document.createElement('div');
                firework.className = 'before';
                const x = Math.random() * window.innerWidth;
                const y = Math.random() * window.innerHeight;
                firework.style.left = x + 'px';
                firework.style.top = y + 'px';
                document.querySelector('.pyro').appendChild(firework);
                
                setTimeout(() => {
                    firework.remove();
                }, 1000);
            }
            
            setInterval(createFirework, 300);
        });
    </script>
</body>
</html>
