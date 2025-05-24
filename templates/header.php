<?php if (session_status() === PHP_SESSION_NONE) { session_start(); } ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TaskTrack</title>
    <link rel="stylesheet" href="css/style.css">
    <script src="js/validation.js"></script>
</head>
<body>
    <header>
        <div class="header-content">
            <h1><a href="<?php echo isset($_SESSION['user_id']) ? 'dashboard.php' : 'index.php'; ?>" style="text-decoration:none; color: var(--primary-color);">TaskTrack</a></h1>
            <nav>
                <ul>
                    <?php
                    // Helper function to check if a link is active
                    function isActive($pageName) {
                        return basename($_SERVER['PHP_SELF']) == $pageName ? 'active' : '';
                    }
                    ?>
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li><a href="dashboard.php" class="<?php echo isActive('dashboard.php'); ?>">Dashboard</a></li>
                        <li><a href="logout.php">Logout (<?php echo htmlspecialchars($_SESSION['username']); ?>)</a></li>
                    <?php else: ?>
                        <li><a href="login.php" class="<?php echo isActive('login.php'); ?>">Login</a></li>
                        <li><a href="register.php" class="<?php echo isActive('register.php'); ?>">Register</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>
    <main>