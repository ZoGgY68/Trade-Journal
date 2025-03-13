<?php
session_start();
require 'config.php';

// Security checks
if (!isset($_SESSION['user_id'])) {
    header('Location: http://trading.3-21.eu/login.php');
    exit;
}

// Regenerate session ID periodically for security
if (!isset($_SESSION['last_session_regen']) || time() - $_SESSION['last_session_regen'] > 1800) {
    session_regenerate_id(true);
    $_SESSION['last_session_regen'] = time();
}

// Generate CSRF token if not exists
if (!isset($_SESSION['entry_csrf_token'])) {
    $_SESSION['entry_csrf_token'] = bin2hex(random_bytes(32));
}

$pdo = getDatabaseConnection();

// Security headers
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline';");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");

// Function to remove trailing zeros but keep one zero after the last non-zero digit
function removeTrailingZeros($number) {
    if ($number === null) {
        return null;
    }
    
    // Convert to string if it's not already
    $number = (string)$number;
    
    // If there's no decimal point, return as is
    if (strpos($number, '.') === false) {
        return $number;
    }
    
    // Split number into integer and decimal parts
    list($integer, $decimal) = explode('.', $number);
    
    // Find the last non-zero character position
    $lastNonZero = -1;
    for ($i = strlen($decimal) - 1; $i >= 0; $i--) {
        if ($decimal[$i] !== '0') {
            $lastNonZero = $i;
            break;
        }
    }
    
    // If all zeros, return just the integer part
    if ($lastNonZero === -1) {
        return $integer;
    }
    
    // Keep one zero after the last non-zero digit if possible
    $keepLength = min($lastNonZero + 2, strlen($decimal));
    $decimal = substr($decimal, 0, $keepLength);
    
    return $integer . '.' . $decimal;
}

$profit_loss = null;
$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['entry_csrf_token']) {
        $message = 'Invalid form submission. Please try again.';
    } else {
        // Sanitize and validate inputs
        $trade_date = filter_var($_POST['trade_date'], FILTER_SANITIZE_STRING);
        $trade_direction = filter_var($_POST['trade_direction'], FILTER_SANITIZE_STRING);
        $symbol = filter_var($_POST['symbol'], FILTER_SANITIZE_STRING);
        $quantity = filter_var($_POST['quantity'], FILTER_VALIDATE_FLOAT) ? $_POST['quantity'] : 0;
        $price = filter_var($_POST['price'], FILTER_VALIDATE_FLOAT) ? $_POST['price'] : 0;
        
        $exit_date = !empty($_POST['exit_date']) ? filter_var($_POST['exit_date'], FILTER_SANITIZE_STRING) : null;
        $exit_price = !empty($_POST['exit_price']) ? filter_var($_POST['exit_price'], FILTER_VALIDATE_FLOAT) ? $_POST['exit_price'] : null : null;
        $stop_loss = !empty($_POST['stop_loss']) ? filter_var($_POST['stop_loss'], FILTER_VALIDATE_FLOAT) ? $_POST['stop_loss'] : null : null;
        $take_profit = !empty($_POST['take_profit']) ? filter_var($_POST['take_profit'], FILTER_VALIDATE_FLOAT) ? $_POST['take_profit'] : null : null;
        $strategy = !empty($_POST['strategy']) ? filter_var($_POST['strategy'], FILTER_SANITIZE_STRING) : '';
        $comment = !empty($_POST['comment']) ? filter_var($_POST['comment'], FILTER_SANITIZE_STRING) : '';
        $profit_loss = filter_var($_POST['profit_loss'], FILTER_VALIDATE_FLOAT) ? $_POST['profit_loss'] : 0;

        // Remove trailing zeros
        $quantity = removeTrailingZeros($quantity);
        $price = removeTrailingZeros($price);
        $exit_price = $exit_price !== null ? removeTrailingZeros($exit_price) : null;
        $stop_loss = $stop_loss !== null ? removeTrailingZeros($stop_loss) : null;
        $take_profit = $take_profit !== null ? removeTrailingZeros($take_profit) : null;
        $profit_loss = removeTrailingZeros($profit_loss);

        try {
            $stmt = $pdo->prepare('INSERT INTO trades (user_id, trade_date, trade_direction, symbol, quantity, price, exit_date, exit_price, stop_loss, take_profit, profit_loss, strategy, comment) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$_SESSION['user_id'], $trade_date, $trade_direction, $symbol, $quantity, $price, $exit_date, $exit_price, $stop_loss, $take_profit, $profit_loss, $strategy, $comment]);
            
            $message = 'Trade recorded successfully!';
            
            // Set success message in session to display after redirect
            $_SESSION['success_message'] = 'Trade recorded successfully!';
            
            // Clear all fields and prevent form resubmission
            header('Location: http://trading.3-21.eu/data_entry.php');
            exit;
        } catch (PDOException $e) {
            $message = 'Error: ' . $e->getMessage();
        }
    }
}

// Check for success message from previous submission
if (isset($_SESSION['success_message'])) {
    $message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

$stmt = $pdo->prepare('SELECT * FROM trades WHERE user_id = ? ORDER BY created_at DESC LIMIT 3');
$stmt->execute([$_SESSION['user_id']]);
$recent_trades = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trade Entry - Trade Journal</title>
    <link rel="stylesheet" href="css/responsive.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background: url('images/image.jpg') no-repeat center center fixed;
            background-size: cover;
            margin: 0;
            padding: 0;
            color: #fff;
        }
        .container {
            max-width: 1400px; /* Increased width */
            margin: 50px auto;
            padding: 40px; /* Increased padding */
            background-color: rgba(0, 0, 0, 0.8);
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.5);
            border-radius: 8px;
        }
        h1 {
            text-align: center;
            color: #fff;
        }
        .form-row {
            display: flex;
            flex-wrap: wrap;
            margin-bottom: 15px;
        }
        .form-row > div {
            flex: 1;
            min-width: 120px;
            margin-right: 10px;
        }
        .form-row > div:last-child {
            margin-right: 0;
        }
        label {
            display: block;
            margin-bottom: 5px;
            color: #ddd;
        }
        input, select, textarea, button {
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
            font-size: 1.2rem; /* Increased font size */
        }
        button:hover {
            background-color: #218838;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background-color: rgba(0, 0, 0, 0.8);
        }
        th, td {
            padding: 10px;
            border: 1px solid #555;
            text-align: left;
            color: #ddd;
        }
        th {
            background-color: #444;
        }
        a {
            color: #007bff;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
        .message {
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 4px;
            text-align: center;
            font-weight: bold;
        }
        .success {
            background-color: rgba(40, 167, 69, 0.2);
            border: 1px solid #28a745;
            color: #28a745;
        }
        .error {
            background-color: rgba(220, 53, 69, 0.2);
            border: 1px solid #dc3545;
            color: #dc3545;
        }
        .nav-buttons {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
        }
        .nav-buttons a {
            padding: 10px 15px;
            border-radius: 4px;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            transition: background-color 0.3s;
        }
        .nav-buttons a:hover {
            background-color: #0056b3;
        }
        .bottom-button {
            display: block;
            width: 200px;
            margin: 20px auto 0;
            padding: 12px 20px;
            background-color: #007bff;
            color: white;
            text-align: center;
            border-radius: 4px;
            text-decoration: none;
            transition: background-color 0.3s;
            font-weight: bold;
        }
        .bottom-button:hover {
            background-color: #0056b3;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Trade Journal Entry</h1>
        
        <?php if ($message): ?>
            <div class="message <?php echo strpos($message, 'successfully') !== false ? 'success' : 'error'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <form action="data_entry.php" method="POST" id="tradeForm">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['entry_csrf_token']); ?>">
            <div class="form-row">
                <div>
                    <label for="trade_date">Entry Date:</label>
                    <input type="date" id="trade_date" name="trade_date" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div>
                    <label for="trade_direction">Long/Short:</label>
                    <select id="trade_direction" name="trade_direction" required class="calc-trigger">
                        <option value="">Select Direction</option>
                        <option value="short">Short</option>
                        <option value="long">Long</option>
                    </select>
                </div>        
                <div>
                    <label for="symbol">Symbol:</label>
                    <input list="symbols" id="symbol" name="symbol" required>
                    <datalist id="symbols">
                        <option value="EUR/USD">
                        <option value="USD/JPY">
                        <option value="GBP/USD">
                        <option value="USD/CHF">
                        <option value="AUD/USD">
                        <option value="USD/CAD">
                        <option value="NZD/USD">
                        <option value="EUR/GBP">
                        <option value="EUR/JPY">
                        <option value="GBP/JPY">
                        <option value="DJIA">
                        <option value="S&P 500">
                        <option value="US30"> <!-- Moved US30 above NASDAQ -->
                        <option value="NASDAQ">
                        <option value="FTSE 100">
                        <option value="DAX">
                        <option value="CAC 40">
                        <option value="Nikkei 225">
                        <option value="Hang Seng">
                        <option value="ASX 200">
                    </datalist>
                </div>
                <div>
                    <label for="quantity">Quantity (Lots):</label>
                    <input type="number" step="0.001" id="quantity" name="quantity" required>
                </div>
                <div>
                    <label for="price">Entry Price:</label> <!-- Renamed header -->
                    <input type="number" step="0.00001" id="price" name="price" required>
                </div>
            </div>
            <div class="form-row">
                <div>
                    <label for="exit_date">Exit Date:</label>
                    <input type="date" id="exit_date" name="exit_date" value="<?php echo date('Y-m-d'); ?>">
                </div>
                <div>
                    <label for="exit_price">Exit Price:</label>
                    <input type="number" step="0.00001" id="exit_price" name="exit_price">
                </div>
                <div>
                    <label for="stop_loss">Stop Loss:</label>
                    <input type="number" step="0.00001" id="stop_loss" name="stop_loss">
                </div>
                <div>
                    <label for="take_profit">Take Profit:</label>
                    <input type="number" step="0.00001" id="take_profit" name="take_profit">
                </div>
                <div>
                    <label for="profit_loss">Profit/Loss:</label>
                    <input type="number" step="0.01" id="profit_loss" name="profit_loss" required>
                </div>
            </div>
            <div class="form-row">
                <div>
                    <label for="strategy">Strategy:</label>
                    <select id="strategy" name="strategy">
                        <option value="">Select Strategy</option>
                        <option value="Scalping">Scalping</option>
                        <option value="Day Trading">Day Trading</option>
                        <option value="Swing Trading">Swing Trading</option>
                        <option value="Position Trading">Position Trading</option>
                        <option value="Momentum Trading">Momentum Trading</option>
                        <option value="Algorithmic Trading">Algorithmic Trading</option>
                        <option value="News Trading">News Trading</option>
                    </select>
                </div>
                <div>
                    <label for="comment">Comment:</label>
                    <textarea id="comment" name="comment"></textarea>
                </div>
            </div>
            <div class="form-row">
                <div>
                    <button type="submit">Save Trade</button> <!-- Renamed button -->
                </div>
                <div>
                    <button type="reset">Reset Form</button>
                </div>
            </div>
        </form>

        <h2>Last Three Trades</h2>
        <table style="width: 100%;">
            <tr>
                <th>Entry Date</th>
                <th>Long/Short</th>
                <th>Symbol</th>
                <th>Quantity</th>
                <th>Entry Price</th> <!-- Renamed header -->
                <th>Exit Date</th>
                <th>Exit Price</th>
                <th>Stop Loss</th>
                <th>Take Profit</th>
                <th>Profit/Loss</th>
                <th>Strategy</th>
                <th>Comment</th>
                <th>Action</th> <!-- Added Action column -->
            </tr>
            <?php foreach ($recent_trades as $trade): ?>
            <tr>
                <td><?php echo htmlspecialchars($trade['trade_date']); ?></td>
                <td><?php echo htmlspecialchars($trade['trade_direction']); ?></td>
                <td><?php echo htmlspecialchars($trade['symbol']); ?></td>
                <td><?php echo htmlspecialchars(removeTrailingZeros($trade['quantity'])); ?></td>
                <td><?php echo htmlspecialchars(removeTrailingZeros($trade['price'])); ?></td>
                <td><?php echo htmlspecialchars($trade['exit_date']); ?></td>
                <td><?php echo htmlspecialchars(removeTrailingZeros($trade['exit_price'])); ?></td>
                <td><?php echo htmlspecialchars(removeTrailingZeros($trade['stop_loss'])); ?></td>
                <td><?php echo htmlspecialchars(removeTrailingZeros($trade['take_profit'])); ?></td>
                <td><?php echo htmlspecialchars(removeTrailingZeros($trade['profit_loss'])); ?></td>
                <td><?php echo htmlspecialchars($trade['strategy']); ?></td>
                <td><?php echo htmlspecialchars($trade['comment']); ?></td>
                <td>
                    <form action="delete_trade.php" method="POST" style="display:inline;" onsubmit="return confirmDelete()">
                        <input type="hidden" name="trade_id" value="<?php echo $trade['id']; ?>">
                        <button type="submit" style="background-color: #dc3545; color: white; border: none; padding: 5px 10px; cursor: pointer;">Delete</button>
                    </form>
                    <form action="edit_trade.php" method="GET" style="display:inline;">
                        <input type="hidden" name="trade_id" value="<?php echo $trade['id']; ?>">
                        <button type="submit" style="background-color: #007bff; color: white; border: none; padding: 5px 10px; cursor: pointer; margin-left: 5px;">Edit</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
        
        <!-- Create a button container with both buttons at the bottom -->
        <div style="display: flex; justify-content: center; gap: 20px; margin-top: 30px;">
            <a href="http://trading.3-21.eu/statistics.php" class="bottom-button">View Statistics</a>
            <a href="http://trading.3-21.eu/logout.php" class="bottom-button" style="background-color: #dc3545;">Logout</a>
        </div>
    </div>
</body>
</html>
