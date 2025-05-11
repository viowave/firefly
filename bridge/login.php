<?php
require 'includes/db.php';

session_start();

// Handle login form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Fetch user from database
    $stmt = $pdo->prepare("SELECT id, username, password FROM admins WHERE username = :username");
    $stmt->execute(['username' => $username]);
    $user = $stmt->fetch();

    // Verify password
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['admin_id'] = $user['id'];
        header("Location: index.php");
        exit;
    } else {
        $error = "Invalid username or password.";
    }
}
?>

<?php
require 'includes/db.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/foundation-sites/dist/css/foundation.min.css">
    <link rel="stylesheet" href="css/admin.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/foundation-sites/dist/js/foundation.min.js"></script>
</head>
<body>
    <div class="grid-container fluid">
        <div class="cell small-12 medium-6 large-4">
            <h2 class="text-center">Admin Login</h2>

            <?php if (isset($error)): ?>
                <div class="callout alert"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

            <form method="POST" class="callout">
                <label>Username:
                    <input type="text" name="username" required>
                </label>
                <label>Password:
                    <input type="password" name="password" required>
                </label>
                <button type="submit" class="button expanded primary">Login</button>
            </form>
        </div>
    </div>
    <footer class="grid-container">
        <div class="grid-x align-center">
            <div class="cell small-12 text-center">
                <p>&copy; <?= date("Y"); ?> Firefly Admin Panel. All rights reserved.</p>
            </div>
        </div>
    </footer>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/foundation-sites/dist/js/foundation.min.js"></script>
<script>
    $(document).foundation(); // Initialize Foundation components
</script>

</body>
</html>

