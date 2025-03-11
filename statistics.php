<?php
session_start();
require 'config.php';

// Error handling
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("Error [$errno] $errstr in $errfile on line $errline");
    http_response_code(500);
    echo "An internal server error occurred. Please try again later.";
    exit;
});

set_exception_handler(function($exception) {
    error_log("Uncaught exception: " . $exception->getMessage());
    http_response_code(500);
    echo "An internal server error occurred. Please try again later.";
    exit;
});

// Security checks
if (!isset($_SESSION['user_id'])) {
    header('Location: http://journal.hopto.org/login.php');
    exit;
}

// Regenerate session ID periodically for security
if (!isset($_SESSION['last_session_regen']) || time() - $_SESSION['last_session_regen'] > 1800) {
    session_regenerate_id(true);
    $_SESSION['last_session_regen'] = time();
}

// Generate CSRF token if not exists
if (!isset($_SESSION['stats_csrf_token'])) {
    $_SESSION['stats_csrf_token'] = bin2hex(random_bytes(32));
}

$pdo = getDatabaseConnection();
if (!$pdo) {
    throw new Exception("Failed to connect to the database.");
}

// Generate a nonce for inline scripts
$nonce = bin2hex(random_bytes(16));

// Security headers
header("Content-Security-Policy: default-src 'self'; script-src 'self' https://cdn.jsdelivr.net 'nonce-$nonce'; style-src 'self' 'unsafe-inline';");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");

// Pagination settings
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$tradesPerPage = 10;
$offset = ($page - 1) * $tradesPerPage;

// Get total count for pagination
$countStmt = $pdo->prepare('SELECT COUNT(*) FROM trades WHERE user_id = ?');
if (!$countStmt) {
    throw new Exception("Failed to prepare statement: " . implode(", ", $pdo->errorInfo()));
}
$countStmt->execute([$_SESSION['user_id']]);
$totalTrades = $countStmt->fetchColumn();
$totalPages = ceil($totalTrades / $tradesPerPage);

// Get recent trades
$recentStmt = $pdo->prepare('SELECT * FROM trades WHERE user_id = ? ORDER BY created_at DESC LIMIT 3');
if (!$recentStmt) {
    throw new Exception("Failed to prepare statement: " . implode(", ", $pdo->errorInfo()));
}
$recentStmt->execute([$_SESSION['user_id']]);
$recent_trades = $recentStmt->fetchAll();

// Get all trades with pagination
$tradesStmt = $pdo->prepare('SELECT * FROM trades WHERE user_id = ? ORDER BY trade_date DESC LIMIT ? OFFSET ?');
if (!$tradesStmt) {
    throw new Exception("Failed to prepare statement: " . implode(", ", $pdo->errorInfo()));
}
$tradesStmt->execute([$_SESSION['user_id'], $tradesPerPage, $offset]);
$paginatedTrades = $tradesStmt->fetchAll();

// Get all trades for statistics
$allTradesStmt = $pdo->prepare('SELECT * FROM trades WHERE user_id = ?');
if (!$allTradesStmt) {
    throw new Exception("Failed to prepare statement: " . implode(", ", $pdo->errorInfo()));
}
$allTradesStmt->execute([$_SESSION['user_id']]);
$trades = $allTradesStmt->fetchAll();

// Calculate trading statistics
$total_trades = count($trades);
$total_profit_loss = array_sum(array_column($trades, 'profit_loss'));
$average_profit_loss = $total_trades ? $total_profit_loss / $total_trades : 0;

$winning_trades = array_filter($trades, function($trade) { return $trade['profit_loss'] > 0; });
$losing_trades = array_filter($trades, function($trade) { return $trade['profit_loss'] < 0; });
$count_won = count($winning_trades);
$count_lost = count($losing_trades);
$win_rate = $total_trades ? ($count_won / $total_trades) * 100 : 0;

$total_won = array_sum(array_map(function($trade) { return $trade['profit_loss'] > 0 ? $trade['profit_loss'] : 0; }, $trades));
$total_lost = array_sum(array_map(function($trade) { return $trade['profit_loss'] < 0 ? $trade['profit_loss'] : 0; }, $trades));
$average_won = $count_won ? $total_won / $count_won : 0;
$average_lost = $count_lost ? $total_lost / $count_lost : 0;
$profit_factor = $total_lost != 0 ? abs($total_won / $total_lost) : 0;

// Group by strategy
$strategy_stats = [];
foreach ($trades as $trade) {
    $strategy = $trade['strategy'] ?: 'No Strategy';
    if (!isset($strategy_stats[$strategy])) {
        $strategy_stats[$strategy] = [
            'count' => 0,
            'profit_loss' => 0,
            'wins' => 0,
            'losses' => 0
        ];
    }
    $strategy_stats[$strategy]['count']++;
    $strategy_stats[$strategy]['profit_loss'] += $trade['profit_loss'];
    if ($trade['profit_loss'] > 0) {
        $strategy_stats[$strategy]['wins']++;
    } elseif ($trade['profit_loss'] < 0) {
        $strategy_stats[$strategy]['losses']++;
    }
}

// Symbol counts
$symbol_counts = [];
foreach ($trades as $trade) {
    if (isset($symbol_counts[$trade['symbol']])) {
        $symbol_counts[$trade['symbol']]++;
    } else {
        $symbol_counts[$trade['symbol']] = 1;
    }
}
arsort($symbol_counts); // Sort by count in descending order
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trading Statistics</title>
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
            max-width: 1400px;
            margin: 50px auto;
            padding: 40px;
            background-color: rgba(0, 0, 0, 0.8);
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.5);
            border-radius: 8px;
        }
        h1 {
            text-align: center;
            color: #fff;
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
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stats-card {
            background-color: rgba(30, 30, 30, 0.7);
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
            display: flex;
            flex-direction: column;
        }
        .stats-header {
            font-size: 1.2rem;
            margin-bottom: 10px;
            color: #fff;
            border-bottom: 1px solid #555;
            padding-bottom: 5px;
        }
        .stats-value {
            font-size: 1.8rem;
            font-weight: bold;
            margin: 5px 0;
        }
        .positive {
            color: #28a745;
        }
        .negative {
            color: #dc3545;
        }
        .neutral {
            color: #ffc107;
        }
        .pagination {
            display: flex;
            justify-content: center;
            margin: 20px 0;
        }
        .pagination a, .pagination span {
            margin: 0 5px;
            padding: 8px 15px;
            background-color: rgba(30, 30, 30, 0.7);
            border-radius: 4px;
            color: #fff;
            text-decoration: none;
        }
        .pagination a:hover {
            background-color: rgba(60, 60, 60, 0.7);
        }
        .pagination .current {
            background-color: #007bff;
        }
        .filters {
            background-color: rgba(30, 30, 30, 0.7);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .filters label {
            margin-right: 10px;
        }
        .filters input[type="date"], .filters button {
            padding: 8px;
            border: 1px solid #555;
            border-radius: 4px;
            background-color: #333;
            color: #fff;
        }
        .filters button {
            background-color: #28a745;
            cursor: pointer;
        }
        .filters button:hover {
            background-color: #218838;
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
    </style>
</head>
<body>
    <div class="container">
        <h1>Trade Journal Statistics</h1>
        
        <div class="nav-buttons">
            <a href="http://journal.hopto.org/data_entry.php">Back to Trade Entry</a>
            <a href="http://journal.hopto.org/logout.php">Logout</a>
        </div>
        
        <!-- Main Statistics Overview -->
        <h2>Performance Summary</h2>
        <div class="stats-container">
            <div class="stats-card">
                <div class="stats-header">Win Rate</div>
                <div class="stats-value <?php echo $win_rate > 50 ? 'positive' : ($win_rate < 40 ? 'negative' : 'neutral'); ?>">
                    <?php echo number_format($win_rate, 2); ?>%
                </div>
                <div><?php echo $count_won; ?> wins / <?php echo $count_lost; ?> losses</div>
            </div>
            
            <div class="stats-card">
                <div class="stats-header">Total P&L</div>
                <div class="stats-value <?php echo $total_profit_loss > 0 ? 'positive' : ($total_profit_loss < 0 ? 'negative' : 'neutral'); ?>">
                    <?php echo number_format($total_profit_loss, 2); ?>
                </div>
                <div>Avg: <?php echo number_format($average_profit_loss, 2); ?> per trade</div>
            </div>
            
            <div class="stats-card">
                <div class="stats-header">Profit Factor</div>
                <div class="stats-value <?php echo $profit_factor > 1.5 ? 'positive' : ($profit_factor < 1 ? 'negative' : 'neutral'); ?>">
                    <?php echo number_format($profit_factor, 2); ?>
                </div>
                <div>Win:Loss Ratio</div>
            </div>
            
            <div class="stats-card">
                <div class="stats-header">Average Win</div>
                <div class="stats-value positive">
                    <?php echo number_format($average_won, 2); ?>
                </div>
                <div>Total: <?php echo number_format($total_won, 2); ?></div>
            </div>
            
            <div class="stats-card">
                <div class="stats-header">Average Loss</div>
                <div class="stats-value negative">
                    <?php echo number_format($average_lost, 2); ?>
                </div>
                <div>Total: <?php echo number_format($total_lost, 2); ?></div>
            </div>
            
            <div class="stats-card">
                <div class="stats-header">Total Trades</div>
                <div class="stats-value"><?php echo $total_trades; ?></div>
                <div>Trading Activity</div>
            </div>
        </div>

        <!-- Strategy Performance -->
        <h2>Strategy Performance</h2>
        <table>
            <tr>
                <th>Strategy</th>
                <th>Trades</th>
                <th>Win Rate</th>
                <th>P&L</th>
                <th>Avg P&L</th>
            </tr>
            <?php foreach ($strategy_stats as $strategy => $stats): ?>
                <tr>
                    <td><?php echo htmlspecialchars($strategy); ?></td>
                    <td><?php echo $stats['count']; ?></td>
                    <td><?php 
                        $win_rate = $stats['count'] > 0 ? ($stats['wins'] / $stats['count']) * 100 : 0;
                        echo number_format($win_rate, 2) . '%'; 
                    ?></td>
                    <td class="<?php echo $stats['profit_loss'] > 0 ? 'positive' : ($stats['profit_loss'] < 0 ? 'negative' : ''); ?>">
                        <?php echo number_format($stats['profit_loss'], 2); ?>
                    </td>
                    <td class="<?php echo $stats['profit_loss'] > 0 ? 'positive' : ($stats['profit_loss'] < 0 ? 'negative' : ''); ?>">
                        <?php 
                            $avg = $stats['count'] > 0 ? $stats['profit_loss'] / $stats['count'] : 0;
                            echo number_format($avg, 2); 
                        ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>

        <!-- Recent Trades -->
        <h2>Last Three Trades</h2>
        <table>
            <tr>
                <th>Date</th>
                <th>Symbol</th>
                <th>Direction</th>
                <th>Qty</th>
                <th>Entry</th>
                <th>Exit</th>
                <th>P&L</th>
                <th>Strategy</th>
            </tr>
            <?php foreach ($recent_trades as $trade): ?>
            <tr>
                <td><?php echo htmlspecialchars($trade['trade_date']); ?></td>
                <td><?php echo htmlspecialchars($trade['symbol']); ?></td>
                <td><?php echo htmlspecialchars($trade['trade_direction']); ?></td>
                <td><?php echo number_format($trade['quantity'], 2); ?></td>
                <td><?php echo number_format($trade['price'], 2); ?></td>
                <td><?php echo number_format($trade['exit_price'], 2); ?></td>
                <td class="<?php echo $trade['profit_loss'] > 0 ? 'positive' : ($trade['profit_loss'] < 0 ? 'negative' : ''); ?>">
                    <?php echo number_format($trade['profit_loss'], 2); ?>
                </td>
                <td><?php echo htmlspecialchars($trade['strategy']); ?></td>
            </tr>
            <?php endforeach; ?>
        </table>

        <!-- Symbol Analysis -->
        <h2>Trade Symbols</h2>
        <div style="display: flex; flex-direction: column;">
            <?php
            foreach ($symbol_counts as $symbol => $count) {
                $percentage = ($count / $total_trades) * 100;
                // Calculate P&L for this symbol
                $symbol_pl = array_sum(array_map(function($trade) use ($symbol) { 
                    return $trade['symbol'] == $symbol ? $trade['profit_loss'] : 0; 
                }, $trades));
                ?>
                <div style='display: flex; align-items: center; margin-bottom: 10px;'>
                    <span style='width: 100px; font-weight: bold;'><?php echo htmlspecialchars($symbol); ?></span>
                    <div style='flex: 1; height: 24px; background-color: rgba(0, 123, 255, 0.2); position: relative; border-radius: 4px; overflow: hidden;'>
                        <div style='height: 100%; background-color: <?php echo $symbol_pl >= 0 ? "rgba(40, 167, 69, 0.7)" : "rgba(220, 53, 69, 0.7)"; ?>; width: <?php echo $percentage; ?>%;'></div>
                    </div>
                    <span style='margin-left: 10px; width: 80px;'><?php echo number_format($percentage, 2); ?>%</span>
                    <span style='width: 100px; text-align: right;' class='<?php echo $symbol_pl >= 0 ? "positive" : "negative"; ?>'><?php echo number_format($symbol_pl, 2); ?></span>
                </div>
                <?php
            }
            ?>
        </div>

        <!-- All Trades with Pagination -->
        <h2>All Trades</h2>
        
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=1">First</a>
                <a href="?page=<?php echo $page-1; ?>">Previous</a>
            <?php endif; ?>
            
            <?php
            $startPage = max(1, $page - 2);
            $endPage = min($totalPages, $page + 2);
            
            for ($i = $startPage; $i <= $endPage; $i++) {
                if ($i == $page) {
                    echo "<span class='current'>$i</span>";
                } else {
                    echo "<a href='?page=$i'>$i</a>";
                }
            }
            ?>
            
            <?php if ($page < $totalPages): ?>
                <a href="?page=<?php echo $page+1; ?>">Next</a>
                <a href="?page=<?php echo $totalPages; ?>">Last</a>
            <?php endif; ?>
        </div>
        
        <table>
            <tr>
                <th>Date</th>
                <th>Symbol</th>
                <th>Direction</th>
                <th>Qty</th>
                <th>Entry</th>
                <th>Exit</th>
                <th>Stop Loss</th>
                <th>Take Profit</th>
                <th>P&L</th>
                <th>Strategy</th>
                <th>Comment</th>
            </tr>
            <?php foreach ($paginatedTrades as $trade): ?>
            <tr>
                <td><?php echo htmlspecialchars($trade['trade_date']); ?></td>
                <td><?php echo htmlspecialchars($trade['symbol']); ?></td>
                <td><?php echo htmlspecialchars($trade['trade_direction']); ?></td>
                <td><?php echo $trade['quantity']; ?></td>
                <td><?php echo $trade['price']; ?></td>
                <td><?php echo $trade['exit_price']; ?></td>
                <td><?php echo $trade['stop_loss']; ?></td>
                <td><?php echo $trade['take_profit']; ?></td>
                <td class="<?php echo $trade['profit_loss'] > 0 ? 'positive' : ($trade['profit_loss'] < 0 ? 'negative' : ''); ?>">
                    <?php echo $trade['profit_loss']; ?>
                </td>
                <td><?php echo htmlspecialchars($trade['strategy']); ?></td>
                <td><?php echo htmlspecialchars($trade['comment']); ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        
        <div style="display: flex; justify-content: center; gap: 20px; margin-top: 30px;">
            <a href="http://journal.hopto.org/data_entry.php" class="bottom-button">Back to Data Entry</a>
            <a href="http://journal.hopto.org/logout.php" class="bottom-button" style="background-color: #dc3545;">Logout</a>
            <a href="export_csv.php" class="bottom-button" style="background-color: #28a745; color: white;">Export to CSV</a> <!-- Changed text color to white -->
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script nonce="<?php echo $nonce; ?>">
        document.addEventListener('DOMContentLoaded', function() {
            // Removed chart initialization code
        });
    </script>
</body>
</html>
