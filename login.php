<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kotak Saran, Kritik dan Aspirasi</title>
    <link rel="stylesheet" href="./assets/style.css?v=2">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>

<body>

    <nav class="navbar">
        <div class="navbar-inner">
            <a href="index.php" class="nav-brand">
                <span class="nav-icon">
                    <i class="fa-regular fa-comments"></i>
                </span>
                Kotak Saran
            </a>

            <img style="margin-right: 30px;" class="unipi" src="./assets/unipi.png" alt="" srcset="" width="60px"
                height="60px">

        </div>
    </nav>

    <main class="main-container">
        <div class="content-wrapper">
            <div class="content-header">
                <h1>Login Admin</h1>
            </div>

            <form action="admin.php" method="POST">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button type="submit">Login</button>
            </form>
            <?php
            if (isset($_GET['error'])) {
                echo '<p class="error" style="text-align:center; margin-top: 1.5rem;">' . htmlspecialchars($_GET['error']) . '</p>';
            }
            ?>
        </div>
    </main>
    <footer class="site-footer">
        <p>
            &copy; <?php echo date('Y'); ?>
            <span class="footer-name">Muhammad Candra Wijaya</span>
        </p>
        <span class="footer-caption">
            Kotak Saran, Kritik dan Aspirasi
        </span>
    </footer>
</body>

</html>