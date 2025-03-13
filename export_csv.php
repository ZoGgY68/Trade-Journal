<?php
session_start();
require 'config.php';

// Security checks
if (!isset($_SESSION['user_id'])) {
    header('Location: http://trading.3-21.eu/login.php');
    exit;
}

$pdo = getDatabaseConnection();

header('Content-Type: text/csv');
header('Content-Disposition: attachment;filename=trades.csv');

$output = fopen('php://output', 'w');
fputcsv($output, ['Trade Date', 'Trade Direction', 'Symbol', 'Quantity', 'Price', 'Exit Date', 'Exit Price', 'Stop Loss', 'Take Profit', 'Profit/Loss', 'Strategy', 'Comment']);

$stmt = $pdo->prepare('SELECT * FROM trades WHERE user_id = ?');
$stmt->execute([$_SESSION['user_id']]);
$trades = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($trades as $trade) {
    fputcsv($output, $trade);
}

fclose($output);
exit;
?>
