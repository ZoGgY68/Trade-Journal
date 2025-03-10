<?php
session_start();
require 'config.php';

// Security checks
if (!isset($_SESSION['user_id'])) {
    header('Location: http://journal.hopto.org/login.php');
    exit;
}

// Security headers
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline';");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");

$pdo = getDatabaseConnection();

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['trade_id'])) {
    $trade_id = filter_var($_POST['trade_id'], FILTER_VALIDATE_INT);
    
    if ($trade_id) {
        // Make sure the trade belongs to the current user (security check)
        $stmt = $pdo->prepare('DELETE FROM trades WHERE id = ? AND user_id = ?');
        $result = $stmt->execute([$trade_id, $_SESSION['user_id']]);
        
        if ($result) {
            $_SESSION['success_message'] = 'Trade successfully deleted.';
        } else {
            $_SESSION['error_message'] = 'Failed to delete trade.';
        }
    }
    
    header('Location: http://journal.hopto.org/data_entry.php');
    exit;
} else {
    // Invalid request
    header('Location: http://journal.hopto.org/data_entry.php');
    exit;
}
?>
