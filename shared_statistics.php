<?php
$log_file = '/var/www/Trade-Journal-2/shared_statistics.log';
function log_message($message) {
    global $log_file;
    file_put_contents($log_file, date('Y-m-d H:i:s') . " - " . $message . "\n", FILE_APPEND);
}

if (!isset($_GET['user_id'])) {
    log_message("User ID is required.");
    die("User ID is required.");
}

$user_id = filter_var($_GET['user_id'], FILTER_SANITIZE_NUMBER_INT);
log_message("User ID: $user_id");

$statistics_file = "/var/www/Trade-Journal-2/shared_statistics/shared_statistics_{$user_id}.json";
if (!file_exists($statistics_file)) {
    log_message("Statistics file not found: $statistics_file");
    die("Statistics file not found.");
}

$statistics = json_decode(file_get_contents($statistics_file), true);
log_message("Loaded statistics from file: $statistics_file");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shared Trading Statistics</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f4f4f4;
            margin: 0;
            padding: 0;
            color: #333;
        }
        .container {
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background-color: #fff;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
        }
        h1 {
            text-align: center;
            color: #333;
            font-size: 2rem;
            margin-bottom: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 15px;
            border: 1px solid #ddd;
            text-align: left;
        }
        th {
            background-color: #007bff;
            color: #fff;
            font-weight: bold;
        }
        td {
            background-color: #f9f9f9;
        }
        .stats-header {
            font-size: 1.2rem;
            margin-bottom: 10px;
            color: #007bff;
            border-bottom: 2px solid #007bff;
            padding-bottom: 5px;
        }
        .stats-value {
            font-size: 1.5rem;
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
    </style>
</head>
<body>
    <div class="container">
        <h1>Shared Trading Statistics</h1>
        <table>
            <tr>
                <th>Total Trades</th>
                <td><?php echo $statistics['total_trades']; ?></td>
            </tr>
            <tr>
                <th>Total P&L</th>
                <td class="<?php echo $statistics['total_profit_loss'] >= 0 ? 'positive' : 'negative'; ?>">
                    <?php echo number_format($statistics['total_profit_loss'], 2); ?>
                </td>
            </tr>
            <tr>
                <th>Average P&L</th>
                <td class="<?php echo $statistics['average_profit_loss'] >= 0 ? 'positive' : 'negative'; ?>">
                    <?php echo number_format($statistics['average_profit_loss'], 2); ?>
                </td>
            </tr>
            <tr>
                <th>Win Rate</th>
                <td class="<?php echo $statistics['win_rate'] >= 50 ? 'positive' : ($statistics['win_rate'] < 40 ? 'negative' : 'neutral'); ?>">
                    <?php echo number_format($statistics['win_rate'], 2); ?>%
                </td>
            </tr>
            <tr>
                <th>Winning Trades</th>
                <td><?php echo $statistics['count_won']; ?></td>
            </tr>
            <tr>
                <th>Losing Trades</th>
                <td><?php echo $statistics['count_lost']; ?></td>
            </tr>
            <tr>
                <th>Total Won</th>
                <td class="positive"><?php echo number_format($statistics['total_won'], 2); ?></td>
            </tr>
            <tr>
                <th>Total Lost</th>
                <td class="negative"><?php echo number_format($statistics['total_lost'], 2); ?></td>
            </tr>
            <tr>
                <th>Average Win</th>
                <td class="positive"><?php echo number_format($statistics['average_won'], 2); ?></td>
            </tr>
            <tr>
                <th>Average Loss</th>
                <td class="negative"><?php echo number_format($statistics['average_lost'], 2); ?></td>
            </tr>
            <tr>
                <th>Profit Factor</th>
                <td class="<?php echo $statistics['profit_factor'] > 1.5 ? 'positive' : ($statistics['profit_factor'] < 1 ? 'negative' : 'neutral'); ?>">
                    <?php echo number_format($statistics['profit_factor'], 2); ?>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
