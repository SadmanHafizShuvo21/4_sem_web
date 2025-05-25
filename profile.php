<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] === 'admin') {
    header("Location: login.php");
    exit;
}
require_once 'includes/header.php';
require_once __DIR__ . '/db/config.php';

$user_id = $_SESSION['user_id'];
$message = "";
$edit_mode = isset($_GET['edit']);

// Fetch user data
$stmt = $pdo->prepare("SELECT username, email, phone, address, bio FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$userData = $stmt->fetch();

if (!$userData) {
    die("User not found.");
}

// Assign user data to variables
$username = $userData['username'];
$email = $userData['email'];
$phone = $userData['phone'];
$address = $userData['address'];
$bio = $userData['bio'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_username = trim($_POST['username']);
    $new_email = trim($_POST['email']);
    $new_phone = trim($_POST['phone']);
    $new_address = trim($_POST['address']);
    $new_bio = trim($_POST['bio']);

    $errors = [];

    // Validate username
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $new_username)) {
        $errors[] = "Username can only contain letters, numbers, and underscores.";
    } elseif ($new_username !== $username) {
        // Check uniqueness if username changed
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? AND id != ?");
        $stmt->execute([$new_username, $user_id]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = "Username already taken.";
        }
    }

    // Validate email
    if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    } elseif ($new_email !== $email) {
        // Check uniqueness if email changed
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$new_email, $user_id]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = "Email already in use.";
        }
    }

    // Validate phone format
    if (!empty($new_phone) && !preg_match('/^\+?[0-9]{1,15}$/', $new_phone)) {
        $errors[] = "Invalid phone number format. Use digits only, optionally starting with +.";
    }

    // Validate bio length
    if (strlen($new_bio) > 500) {
        $errors[] = "Bio cannot exceed 500 characters.";
    }

    // Update database if no errors
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("UPDATE users SET username=?, email=?, phone=?, address=?, bio=? WHERE id=?");
            $stmt->execute([$new_username, $new_email, $new_phone, $new_address, $new_bio, $user_id]);
            
            // Update session username if changed
            $_SESSION['username'] = $new_username;
            
            header("Location: profile.php");
            exit;
        } catch (PDOException $e) {
            $message = "Error updating profile: " . $e->getMessage();
        }
    } else {
        $message = implode("<br>", $errors);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Your Profile</title>
    <link rel="stylesheet" href="css/profile.css">
</head>
<body>
    <div class="profile-container">
        <h2>Your Profile</h2>
        <?php if ($message): ?>
            <div class="message"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <form method="POST">
            <label>Username</label>
            <input type="text" name="username" value="<?= htmlspecialchars($username) ?>" <?= !$edit_mode ? 'disabled' : '' ?>>

            <label>Email</label>
            <input type="email" name="email" value="<?= htmlspecialchars($email) ?>" <?= !$edit_mode ? 'disabled' : '' ?>>

            <label>Phone</label>
            <input type="text" name="phone" value="<?= htmlspecialchars($phone) ?>" <?= !$edit_mode ? 'disabled' : '' ?>>

            <label>Bio</label>
            <textarea name="bio" <?= !$edit_mode ? 'disabled' : '' ?>><?= htmlspecialchars($bio) ?></textarea>

            <label>Address</label>
            <textarea name="address" <?= !$edit_mode ? 'disabled' : '' ?>><?= htmlspecialchars($address) ?></textarea>

            <?php if ($edit_mode): ?>
                <button type="submit" class="btn-save">Save Changes</button>
                <a href="profile.php" class="btn-cancel">Cancel</a>
            <?php else: ?>
                <a href="profile.php?edit=1" class="btn-edit">Edit Profile</a>
            <?php endif; ?>
        </form>
    </div>
</body>
</html>