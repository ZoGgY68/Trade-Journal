<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'Trade Journal'; ?></title>
    <link rel="stylesheet" href="css/responsive.css">
    <?php if (isset($additional_css)) echo $additional_css; ?>
</head>
<body>
    <div class="container">
        <header>
            <h1>Trade Journal</h1>
            <nav class="nav-container">
                <a href="index.php" class="nav-item">Home</a>
                <a href="data_entry.php" class="nav-item">New Trade</a>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="logout.php" class="nav-item">Logout</a>
                <?php else: ?>
                    <a href="login.php" class="nav-item">Login</a>
                    <a href="register.php" class="nav-item">Register</a>
                <?php endif; ?>
            </nav>
        </header>
        <main>
