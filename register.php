<?php
require_once 'includes/header.php';
echo '<link rel="stylesheet" href="css/register.css">';
require_once 'db/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = "Invalid CSRF token.";
    } else {
        // Get and sanitize inputs
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $phone = trim($_POST['phone'] ?? '');
        $bio = trim($_POST['bio'] ?? '');
        $role = $_POST['role'] ?? 'user';

        // Additional sanitization
        $username = filter_var($username, FILTER_SANITIZE_STRING);
        $bio = htmlspecialchars($bio, ENT_QUOTES, 'UTF-8');

        // Validate required fields
        if (empty($username) || empty($email) || empty($password)) {
            $errors[] = "Username, email, and password are required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format.";
        } elseif (!preg_match('/^(?=.*[A-Za-z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{6,}$/', $password)) {
            $errors[] = "Password must be at least 6 characters and include a letter, a number, and a special character.";
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            $errors[] = "Username can only contain letters, numbers, and underscores.";
        } elseif (!empty($phone) && !preg_match('/^\+?[0-9]{1,15}$/', $phone)) {
            $errors[] = "Invalid phone number format. Use digits only, optionally starting with +, up to 15 digits.";
        } elseif (!in_array($role, ['user', 'admin'])) {
            $errors[] = "Invalid role selected.";
        } elseif (strlen($bio) > 500) {
            $errors[] = "Bio cannot exceed 500 characters.";
        } else {
            // Check for duplicate username/email
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            if ($stmt->fetchColumn() > 0) {
                $errors[] = "Username or email already exists.";
            } else {
                try {
                    // Hash password and insert user with role
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO users (username, email, password, phone, bio, role) VALUES (?, ?, ?, ?, ?, ?)");
                    if ($stmt->execute([$username, $email, $hashed_password, $phone, $bio, $role])) {
                        // Regenerate session ID to prevent session fixation
                        session_regenerate_id(true);
                        // Regenerate CSRF token
                        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                        error_log("User registered: $username as $role");
                        header("Location: login.php");
                        exit;
                    } else {
                        $errors[] = "Registration failed. Please try again later.";
                    }
                } catch (PDOException $e) {
                    error_log("Registration error: " . $e->getMessage());
                    $errors[] = "An unexpected error occurred. Please try again later.";
                }
            }
        }
    }
}
?>

<main>
    <div class="register-container">
        <h1>Create Account</h1>

        <?php if (!empty($errors)): ?>
            <div class="errors">
                <?php foreach ($errors as $error): ?>
                    <p><?php echo htmlspecialchars($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="register.php" class="register-form">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required class="form-input">
            </div>

            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" required class="form-input">
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required class="form-input">
            </div>

            <div class="form-group">
                <label for="phone">Phone Number (Optional)</label>
                <input type="tel" id="phone" name="phone" class="form-input" placeholder="e.g., +1234567890">
            </div>

            <div class="form-group">
                <label for="role">Role</label>
                <select id="role" name="role" required class="form-input">
                    <option value="">Select Role</option>
                    <option value="user">User</option>
                    <option value="admin">Admin</option>
                </select>
            </div>

            <div style="margin-bottom: 1.5rem;">
                <label for="bio" style="display: block; margin-bottom: 0.5rem; font-weight: bold; color: #2c3e50;">
                    Bio (Optional)
                </label>
                <textarea id="bio" name="bio" rows="4"
                    style="width: 100%; padding: 0.75rem; border: 2px solid rgba(44, 62, 80, 0.1); border-radius: 8px; background: rgba(255,255,255,0.9); font-size: 1rem; resize: vertical;">
                </textarea>
            </div>

            <button type="submit" style="display: block; width: 100%; text-align: center; padding: 0.8rem; background: #3498db; color: white; font-weight: bold; border: none; border-radius: 8px; font-size: 1rem; cursor: pointer;">
                Register Now
            </button>

            <p class="login-link">Already have an account?
                <a href="login.php" class="text-accent"> Login here</a>
            </p>
        </form>
    </div>
</main>

<?php require_once 'includes/footer.php'; ?>