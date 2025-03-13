<?php
session_start();
require 'config.php';

// Enable error handling
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("Error $errno: $errstr in $errfile on line $errline");
    http_response_code(500);
    echo "An internal server error occurred. Please try again later.";
    exit;
});

// Verify admin access
if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header('Location: http://trading.3-21.eu/login.php');
    exit;
}

try {
    $pdo = getDatabaseConnection();
    $message = '';

    // Handle user deletion
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
        $user_id = filter_var($_POST['delete_user'], FILTER_VALIDATE_INT);
        if ($user_id) {
            try {
                // Delete user's trades first
                $stmt = $pdo->prepare('DELETE FROM trades WHERE user_id = ?');
                $stmt->execute([$user_id]);
                
                // Then delete the user
                $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
                $stmt->execute([$user_id]);
                
                $message = 'User and their trades deleted successfully.';
            } catch (PDOException $e) {
                $message = 'Error deleting user: ' . $e->getMessage();
            }
        }
    }

    // Handle user locking/unlocking
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_lock'])) {
        $user_id = filter_var($_POST['toggle_lock'], FILTER_VALIDATE_INT);
        if ($user_id) {
            try {
                $stmt = $pdo->prepare('UPDATE users SET is_locked = NOT is_locked, locked_at = CASE WHEN is_locked = 0 THEN NOW() ELSE NULL END WHERE id = ?');
                $stmt->execute([$user_id]);
                $message = 'User status updated successfully.';
            } catch (PDOException $e) {
                $message = 'Error updating user status: ' . $e->getMessage();
            }
        }
    }

    // Get all users with error handling
    $stmt = $pdo->prepare('SELECT id, username, email, created_at, last_login, is_verified, is_locked FROM users WHERE is_admin = 0');
    if (!$stmt->execute()) {
        throw new PDOException("Failed to fetch users");
    }
    $users = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Admin panel error: " . $e->getMessage());
    $message = "An error occurred while processing your request.";
    $users = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Trade Journal</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: url('images/image.jpg') no-repeat center center fixed;
            background-size: cover;
            margin: 0;
            padding: 20px;
            color: #fff;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background-color: rgba(0, 0, 0, 0.8);
            padding: 20px;
            border-radius: 8px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border: 1px solid #444;
        }
        th {
            background-color: #333;
        }
        .message {
            padding: 10px;
            margin: 10px 0;
            border-radius: 4px;
        }
        .success { background-color: #28a745; }
        .error { background-color: #dc3545; }
        .delete-btn {
            background-color: #dc3545;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
        }
        .delete-btn:hover {
            background-color: #c82333;
        }
        .nav-buttons {
            margin-bottom: 20px;
        }
        .nav-buttons a {
            display: inline-block;
            padding: 10px 20px;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin-right: 10px;
        }
        .nav-buttons a:hover {
            background-color: #0056b3;
        }
        .lock-btn {
            background-color: #ffc107;
            color: black;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
            margin-right: 5px;
        }
        .unlock-btn {
            background-color: #28a745;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
            margin-right: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Admin Panel</h1>
        
        <div class="nav-buttons">
            <a href="http://trading.3-21.eu/data_entry.php">Trade Entry</a>
            <a href="http://trading.3-21.eu/statistics.php">Statistics</a>
            <a href="http://trading.3-21.eu/admin_password.php">Change Password</a>
            <a href="http://trading.3-21.eu/logout.php">Logout</a>
        </div>

        <?php if ($message): ?>
            <div class="message <?php echo strpos($message, 'successfully') !== false ? 'success' : 'error'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <h2>User Management</h2>
        <table>
            <tr>
                <th>Username</th>
                <th>Email</th>
                <th>Created</th>
                <th>Last Login</th>
                <th>Verified</th>
                <th>Locked</th>
                <th>Actions</th>
            </tr>
            <?php foreach ($users as $user): ?>
            <tr>
                <td><?php echo htmlspecialchars($user['username']); ?></td>
                <td><?php echo htmlspecialchars($user['email']); ?></td>
                <td><?php echo htmlspecialchars($user['created_at']); ?></td>
                <td><?php echo $user['last_login'] ? htmlspecialchars($user['last_login']) : 'Never'; ?></td>
                <td><?php echo $user['is_verified'] ? 'Yes' : 'No'; ?></td>
                <td><?php echo $user['is_locked'] ? 'Yes' : 'No'; ?></td>
                <td>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="toggle_lock" value="<?php echo $user['id']; ?>">
                        <button type="submit" class="<?php echo $user['is_locked'] ? 'unlock-btn' : 'lock-btn'; ?>">
                            <?php echo $user['is_locked'] ? 'Unlock' : 'Lock'; ?>
                        </button>
                    </form>
                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this user and all their trades?');">
                        <input type="hidden" name="delete_user" value="<?php echo $user['id']; ?>">
                        <button type="submit" class="delete-btn">Delete</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
</body>
</html>
