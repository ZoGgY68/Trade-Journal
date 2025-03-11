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
                            
                            header('Location: http://journal.hopto.org/data_entry.php');
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
header("Content-Security-Policy: default-src 'self'; script-src 'self' https://s3.tradingview.com 'nonce-$nonce'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; frame-src https://s.tradingview.com;");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Trade Journal</title>
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
        .tradingview-widget-wrapper {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100%; /* Use full height available */
            width: 100%;
            padding: 20px 0;
            position: relative; /* Added for positioning context */
        }
        .tradingview-widget-container {
            width: 95%;
            max-width: 650px;
            height: 700px !important; /* Increased and forced with !important */
            min-height: 700px !important; /* Added min-height to ensure it doesn't shrink */
            position: relative; /* Added for overlay positioning */
            z-index: 1; /* Ensure widget is above transparency layer */
        }
        /* Transparency overlay */
        .transparency-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.2); /* 20% black overlay */
            z-index: 2; /* Place above the widget */
            pointer-events: none; /* Allow clicks to pass through to widget */
        }
        @media screen and (max-height: 900px) {
            .tradingview-widget-container {
                height: 500px !important; /* Smaller height for smaller screens */
                min-height: 500px !important;
            }
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
        .left-container .tradingview-widget-container {
            height: 700px !important;
            min-height: 700px !important;
            max-height: 700px !important;
        }
        
        /* Ensure page content doesn't overflow */
        body, html {
            overflow-x: hidden;
        }
        
        /* Additional styles to fix left container */
        .left-widget {
            height: 700px !important;
            min-height: 700px !important;
            overflow: hidden !important;
        }
    </style>
    <!-- Force refresh with no cache -->
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
</head>
<body>
    <div class="header-description">
        <h1>Trading Journal</h1>
        <p>Trade Journal is a comprehensive tool designed to help traders keep track of their trades, analyze performance, and improve their trading strategies.</p>
    </div>
    
    <div class="main-content">
        <div class="left-container">
            <!-- TradingView Widget BEGIN -->
            <div class="tradingview-widget-wrapper">
                <div class="tradingview-widget-container left-widget" style="width: 95%; height: 700px !important; min-height: 700px !important; max-height: 700px !important;">
                    <div id="tradingview_12345" style="width: 100%; height: 100%;"></div>
                    <div class="transparency-overlay"></div>
                    <script type="text/javascript" src="https://s3.tradingview.com/tv.js" nonce="<?php echo $nonce; ?>"></script>
                    <script type="text/javascript" nonce="<?php echo $nonce; ?>">
                    new TradingView.widget({
                        "width": "100%",
                        "height": "100%",
                        "symbol": "OANDA:US30USD",
                        "interval": "D",
                        "timezone": "Etc/UTC",
                        "theme": "dark",
                        "style": "1",
                        "locale": "en",
                        "toolbar_bg": "#f1f3f6",
                        "enable_publishing": false,
                        "allow_symbol_change": true,
                        "container_id": "tradingview_12345"
                    });
                    </script>
                </div>
            </div>
            <!-- TradingView Widget END -->
        </div>
        
        <div class="middle-container">
            <div class="container">
                <h1>Login</h1>
                <?php if ($message): ?>
                    <div class="message"><?php echo htmlspecialchars($message); ?></div>
                <?php endif; ?>
                <form action="login.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <label for="username">Username:</label>
                    <input type="text" id="username" name="username" required>
                    <label for="password">Password:</label>
                    <input type="password" id="password" name="password" required>
                    <button type="submit">Login</button>
                </form>
                <p>Don't have an account? <a href="http://journal.hopto.org/register.php">Register here</a></p>
                <p><a href="http://journal.hopto.org/forgot_password.php">Forgot Password?</a></p>
            </div>
        </div>
        
        <div class="right-container">
            <!-- TradingView Widget BEGIN -->
            <div class="tradingview-widget-wrapper">
                <div class="tradingview-widget-container" style="width: 95%; height: 700px !important; min-height: 700px !important;">
                    <div id="tradingview_nas100" style="width: 100%; height: 100%;"></div>
                    <div class="transparency-overlay"></div>
                    <script type="text/javascript" src="https://s3.tradingview.com/tv.js" nonce="<?php echo $nonce; ?>"></script>
                    <script type="text/javascript" nonce="<?php echo $nonce; ?>">
                    new TradingView.widget({
                        "width": "100%",
                        "height": "100%",
                        "symbol": "OANDA:NAS100USD",
                        "interval": "D",
                        "timezone": "Etc/UTC",
                        "theme": "dark",
                        "style": "1",
                        "locale": "en",
                        "toolbar_bg": "#f1f3f6",
                        "enable_publishing": false,
                        "allow_symbol_change": true,
                        "container_id": "tradingview_nas100"
                    });
                    </script>
                </div>
            </div>
            <!-- TradingView Widget END -->
        </div>
    </div>
    
    <!-- Force widget height after page load -->
    <script nonce="<?php echo $nonce; ?>">
        window.addEventListener('load', function() {
            const leftWidget = document.querySelector('.left-container .tradingview-widget-container');
            if (leftWidget) {
                leftWidget.style.height = '700px';
                leftWidget.style.minHeight = '700px';
            }
        });
    </script>
</body>
</html>
