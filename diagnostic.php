<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Diagnostic Information</h1>";

// Check PHP version
echo "<h2>PHP Version</h2>";
echo "PHP Version: " . phpversion() . "<br>";

// Check loaded extensions
echo "<h2>Loaded Extensions</h2>";
echo "<pre>" . print_r(get_loaded_extensions(), true) . "</pre>";

// Check database connection if applicable
echo "<h2>Database Connection Test</h2>";
try {
    // Adjust these settings to match your actual database configuration
    $db_host = 'localhost';
    $db_name = 'your_database_name';
    $db_user = 'your_database_user';
    $db_pass = 'your_database_password';
    
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Database connection successful!";
} catch (PDOException $e) {
    echo "Database connection failed: " . $e->getMessage();
}

// Check file permissions
echo "<h2>File Permissions</h2>";
$app_directory = __DIR__;
echo "Application directory: $app_directory<br>";
echo "Is readable: " . (is_readable($app_directory) ? 'Yes' : 'No') . "<br>";
echo "Is writable: " . (is_writable($app_directory) ? 'Yes' : 'No') . "<br>";

// Display server information
echo "<h2>Server Information</h2>";
echo "Server software: " . $_SERVER['SERVER_SOFTWARE'] . "<br>";

// Check for .htaccess issues (if using Apache)
echo "<h2>.htaccess Check</h2>";
$htaccess_path = __DIR__ . '/.htaccess';
if (file_exists($htaccess_path)) {
    echo ".htaccess file exists. ";
    echo "Is readable: " . (is_readable($htaccess_path) ? 'Yes' : 'No');
} else {
    echo ".htaccess file doesn't exist.";
}
?>
