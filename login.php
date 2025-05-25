<?php
echo '<link rel="stylesheet" href="css/login.css">';
require_once 'db/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $role = $_POST['role'] ?? '';

    if (empty($email) || empty($password) || empty($role)) {
        $errors[] = "Email, password, and role are required.";
    } elseif (!in_array($role, ['user', 'admin'])) {
        $errors[] = "Invalid role selected.";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                if ($user['role'] !== $role) {
                    $errors[] = "Selected role does not match your account role.";
                } else {
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    error_log("User {$user['username']} logged in as {$user['role']}");

                    // LOAD CART FROM DATABASE INTO SESSION
                    try {
                        $stmt = $pdo->prepare("SELECT product_id, quantity FROM cart WHERE user_id = ?");
                        $stmt->execute([$user['id']]);
                        $cartItems = $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // product_id => quantity
                        $_SESSION['cart'] = $cartItems ?: [];
                    } catch (PDOException $e) {
                        error_log("Failed to load user cart on login: " . $e->getMessage());
                        $_SESSION['cart'] = [];
                    }

                    header("Location: index.php");
                    exit;
                }
            } else {
                $errors[] = "Invalid email or password.";
            }
        } catch (PDOException $e) {
            $errors[] = "Database error: " . htmlspecialchars($e->getMessage());
            error_log("Login error: " . $e->getMessage());
        }
    }
}
?>

<main>
    <div class="login-container">
        <h1>Login</h1>

        <?php if (!empty($errors)): ?>
            <div class="errors">
                <?php foreach ($errors as $error): ?>
                    <p><?php echo htmlspecialchars($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php" class="login-form">
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required class="form-input" aria-required="true">
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required class="form-input" aria-required="true">
            </div>

            <div class="form-group">
                <label for="role">Role</label>
                <select id="role" name="role" required class="form-input" aria-required="true">
                    <option value="">Select Role</option>
                    <option value="user">User</option>
                    <option value="admin">Admin</option>
                </select>
            </div>

            <button type="submit" class="btn-mix">Login</button>

            <p class="register-link">Don't have an account? 
                <a href="register.php" class="text-accent">Register here</a>
            </p>
        </form>
    </div>
</main>

<?php require_once 'includes/footer.php'; ?>