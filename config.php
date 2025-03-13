<?php
// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '/var/www/Trade-Journal-2/error.log');

$host = 'localhost';
$db = 'trade_journal_2'; // updated database name
$user = 'tj2';
$pass = 'journal1234'; // add the correct password here

$dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

$admin_email = 'chris@3-21.net'; // Email for the admin account

function getDatabaseConnection() {
    global $dsn, $user, $pass, $options;
    try {
        $pdo = new PDO($dsn, $user, $pass, $options);
        
        // Check if admin column exists, if not add it
        $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'is_admin'");
        if (!$stmt->fetch()) {
            $pdo->exec("ALTER TABLE users ADD COLUMN is_admin BOOLEAN DEFAULT FALSE");
        }
        
        return $pdo;
    } catch (\PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        throw new \PDOException($e->getMessage(), (int)$e->getCode());
    }
}

function createAdminAccount($pdo) {
    global $admin_email;
    try {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$admin_email]);
        if (!$stmt->fetch()) {
            $stmt = $pdo->prepare('INSERT INTO users (username, password, email, is_verified, is_admin) VALUES (?, ?, ?, 1, 1)');
            $password = password_hash('admin123', PASSWORD_BCRYPT); // Change this password
            $stmt->execute(['admin', $password, $admin_email]);
        }
    } catch (\PDOException $e) {
        error_log("Failed to create admin account: " . $e->getMessage());
    }
}

try {
    $pdo = getDatabaseConnection();
    createAdminAccount($pdo);
} catch (\PDOException $e) {
    error_log("Initial setup failed: " . $e->getMessage());
    // Don't rethrow, let the application handle the error gracefully
}
?>
