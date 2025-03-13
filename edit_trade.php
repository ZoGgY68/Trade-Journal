<?php
session_start();
require 'config.php';

// Security checks
if (!isset($_SESSION['user_id'])) {
    header('Location: http://trading.3-21.eu/login.php');
    exit;
}

$pdo = getDatabaseConnection();

$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['trade_id'])) {
    $trade_id = filter_var($_GET['trade_id'], FILTER_VALIDATE_INT);
    $stmt = $pdo->prepare('SELECT * FROM trades WHERE id = ? AND user_id = ?');
    $stmt->execute([$trade_id, $_SESSION['user_id']]);
    $trade = $stmt->fetch();

    if (!$trade) {
        $message = 'Trade not found or you do not have permission to edit this trade.';
    }
} elseif ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['trade_id'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['entry_csrf_token']) {
        $message = 'Invalid form submission. Please try again.';
    } else {
        // Sanitize and validate inputs
        $trade_id = filter_var($_POST['trade_id'], FILTER_VALIDATE_INT);
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
            $stmt = $pdo->prepare('UPDATE trades SET trade_date = ?, trade_direction = ?, symbol = ?, quantity = ?, price = ?, exit_date = ?, exit_price = ?, stop_loss = ?, take_profit = ?, profit_loss = ?, strategy = ?, comment = ? WHERE id = ? AND user_id = ?');
            $stmt->execute([$trade_date, $trade_direction, $symbol, $quantity, $price, $exit_date, $exit_price, $stop_loss, $take_profit, $profit_loss, $strategy, $comment, $trade_id, $_SESSION['user_id']]);
            
            $message = 'Trade updated successfully!';
            
            // Set success message in session to display after redirect
            $_SESSION['success_message'] = 'Trade updated successfully!';
            
            // Redirect to data entry page
            header('Location: http://trading.3-21.eu/data_entry.php');
            exit;
        } catch (PDOException $e) {
            $message = 'Error: ' . $e->getMessage();
        }
    }
}

// Function to remove trailing zeros
function removeTrailingZeros($number) {
    return rtrim(rtrim($number, '0'), '.');
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Trade - Trade Journal</title>
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
    </style>
</head>
<body>
    <div class="container">
        <h1>Edit Trade</h1>
        
        <?php if ($message): ?>
            <div class="message <?php echo strpos($message, 'successfully') !== false ? 'success' : 'error'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($trade): ?>
        <form action="edit_trade.php" method="POST" id="tradeForm">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['entry_csrf_token']); ?>">
            <input type="hidden" name="trade_id" value="<?php echo htmlspecialchars($trade['id']); ?>">
            <div class="form-row">
                <div>
                    <label for="trade_date">Entry Date:</label>
                    <input type="date" id="trade_date" name="trade_date" value="<?php echo htmlspecialchars($trade['trade_date']); ?>" required>
                </div>
                <div>
                    <label for="trade_direction">Long/Short:</label>
                    <select id="trade_direction" name="trade_direction" required class="calc-trigger">
                        <option value="short" <?php echo $trade['trade_direction'] == 'short' ? 'selected' : ''; ?>>Short</option>
                        <option value="long" <?php echo $trade['trade_direction'] == 'long' ? 'selected' : ''; ?>>Long</option>
                    </select>
                </div>
                <div>
                    <label for="symbol">Symbol:</label>
                    <input list="symbols" id="symbol" name="symbol" value="<?php echo htmlspecialchars($trade['symbol']); ?>" required>
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
                    <input type="number" step="0.001" id="quantity" name="quantity" value="<?php echo htmlspecialchars(removeTrailingZeros($trade['quantity'])); ?>" required>
                </div>
                <div>
                    <label for="price">Entry Price:</label> <!-- Renamed header -->
                    <input type="number" step="0.00001" id="price" name="price" value="<?php echo htmlspecialchars($trade['price']); ?>" required>
                </div>
            </div>
            <div class="form-row">
                <div>
                    <label for="exit_date">Exit Date:</label>
                    <input type="date" id="exit_date" name="exit_date" value="<?php echo htmlspecialchars($trade['exit_date']); ?>">
                </div>
                <div>
                    <label for="exit_price">Exit Price:</label>
                    <input type="number" step="0.00001" id="exit_price" name="exit_price" value="<?php echo htmlspecialchars($trade['exit_price']); ?>">
                </div>
                <div>
                    <label for="stop_loss">Stop Loss:</label>
                    <input type="number" step="0.00001" id="stop_loss" name="stop_loss" value="<?php echo htmlspecialchars($trade['stop_loss']); ?>">
                </div>
                <div>
                    <label for="take_profit">Take Profit:</label>
                    <input type="number" step="0.00001" id="take_profit" name="take_profit" value="<?php echo htmlspecialchars($trade['take_profit']); ?>">
                </div>
                <div>
                    <label for="profit_loss">Profit/Loss:</label>
                    <input type="number" step="0.01" id="profit_loss" name="profit_loss" value="<?php echo htmlspecialchars($trade['profit_loss']); ?>" required>
                </div>
            </div>
            <div class="form-row">
                <div>
                    <label for="strategy">Strategy:</label>
                    <select id="strategy" name="strategy">
                        <option value="Scalping" <?php echo $trade['strategy'] == 'Scalping' ? 'selected' : ''; ?>>Scalping</option>
                        <option value="Day Trading" <?php echo $trade['strategy'] == 'Day Trading' ? 'selected' : ''; ?>>Day Trading</option>
                        <option value="Swing Trading" <?php echo $trade['strategy'] == 'Swing Trading' ? 'selected' : ''; ?>>Swing Trading</option>
                        <option value="Position Trading" <?php echo $trade['strategy'] == 'Position Trading' ? 'selected' : ''; ?>>Position Trading</option>
                        <option value="Momentum Trading" <?php echo $trade['strategy'] == 'Momentum Trading' ? 'selected' : ''; ?>>Momentum Trading</option>
                        <option value="Algorithmic Trading" <?php echo $trade['strategy'] == 'Algorithmic Trading' ? 'selected' : ''; ?>>Algorithmic Trading</option>
                        <option value="News Trading" <?php echo $trade['strategy'] == 'News Trading' ? 'selected' : ''; ?>>News Trading</option>
                    </select>
                </div>
                <div>
                    <label for="comment">Comment:</label>
                    <textarea id="comment" name="comment"><?php echo htmlspecialchars($trade['comment']); ?></textarea>
                </div>
            </div>
            <div class="form-row">
                <div>
                    <button type="submit">Update Trade</button> <!-- Renamed button -->
                </div>
                <div>
                    <button type="reset">Reset Form</button>
                </div>
            </div>
        </form>
        <?php else: ?>
            <p>Trade not found or you do not have permission to edit this trade.</p>
        <?php endif; ?>
    </div>
</body>
</html>
