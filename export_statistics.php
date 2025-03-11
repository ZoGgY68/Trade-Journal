<?php
session_start();
require 'config.php';

$log_file = '/var/www/Trade-Journal-2/export_statistics.log';
function log_message($message) {
    global $log_file;
    file_put_contents($log_file, date('Y-m-d H:i:s') . " - " . $message . "\n", FILE_APPEND);
}

if (!isset($_SESSION['user_id'])) {
    log_message("User not logged in.");
    header('Location: http://journal.hopto.org/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
log_message("User ID: $user_id");

$pdo = getDatabaseConnection();
if (!$pdo) {
    log_message("Failed to connect to the database.");
    die("Failed to connect to the database.");
}

// Get all trades for statistics
$allTradesStmt = $pdo->prepare('SELECT * FROM trades WHERE user_id = ?');
if (!$allTradesStmt) {
    log_message("Failed to prepare statement: " . implode(", ", $pdo->errorInfo()));
    die("Failed to prepare statement: " . implode(", ", $pdo->errorInfo()));
}
if (!$allTradesStmt->execute([$user_id])) {
    log_message("Failed to execute statement: " . implode(", ", $allTradesStmt->errorInfo()));
    die("Failed to execute statement: " . implode(", ", $allTradesStmt->errorInfo()));
}
$trades = $allTradesStmt->fetchAll();
log_message("Fetched " . count($trades) . " trades.");

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

// Prepare data to be shared
$statistics = [
    'total_trades' => $total_trades,
    'total_profit_loss' => $total_profit_loss,
    'average_profit_loss' => $average_profit_loss,
    'win_rate' => $win_rate,
    'count_won' => $count_won,
    'count_lost' => $count_lost,
    'total_won' => $total_won,
    'total_lost' => $total_lost,
    'average_won' => $average_won,
    'average_lost' => $average_lost,
    'profit_factor' => $profit_factor
];

// Ensure the directory exists
$directory = '/var/www/Trade-Journal-2/shared_statistics';
if (!is_dir($directory)) {
    if (!mkdir($directory, 0777, true)) {
        $error = error_get_last();
        log_message("Failed to create directory: $directory. Error: " . $error['message']);
        die("Failed to create directory: $directory. Error: " . $error['message']);
    }
    log_message("Created directory: $directory");
    chown($directory, 'www-data');
    chgrp($directory, 'www-data');
}

// Save statistics to a unique file for the user
$file_path = "$directory/shared_statistics_{$user_id}.json";
if (file_put_contents($file_path, json_encode($statistics)) === false) {
    log_message("Failed to write to file: $file_path");
    die("Failed to write to file: $file_path");
}
log_message("Saved statistics to file: $file_path");
chown($file_path, 'www-data');
chgrp($file_path, 'www-data');

header("Location: shared_statistics.php?user_id={$user_id}");
exit;
?>
