<?php
require_once 'includes/header.php';
echo '<link rel="stylesheet" href="css/admin_dashboard.css">';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

require_once 'db/config.php';

// CSRF Protection
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];
$success = false;
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_product') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = "Invalid CSRF token.";
    } else {
        $name = trim($_POST['name']);
        $price = (float)$_POST['price'];
        $description = trim($_POST['description'] ?? '');
        $image = $_FILES['image'];

        if (empty($name) || empty($price)) {
            $errors[] = "Name and price are required.";
        } elseif ($image['error'] !== UPLOAD_ERR_OK) {
            $errors[] = "Image upload failed. Error code: " . $image['error'];
        } elseif ($image['size'] > 2 * 1024 * 1024) {
            $errors[] = "Image size exceeds 2MB.";
        } else {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $file_type = mime_content_type($image['tmp_name']);
            
            if (!in_array($file_type, $allowed_types)) {
                $errors[] = "Only JPG, PNG, and GIF files are allowed.";
            } else {
                $target_dir = __DIR__ . '/assets/';
                if (!is_dir($target_dir)) {
                    if (!mkdir($target_dir, 0755, true)) {
                        $errors[] = "Failed to create assets directory.";
                    }
                }
                if (!is_writable($target_dir)) {
                    $errors[] = "Assets directory is not writable.";
                } else {
                    $file_name = uniqid() . '_' . basename($image['name']);
                    $target_file = $target_dir . $file_name;

                    if (move_uploaded_file($image['tmp_name'], $target_file)) {
                        try {
                            $stmt = $pdo->prepare("INSERT INTO products (name, price, description, image, created_at) VALUES (?, ?, ?, ?, NOW())");
                            if ($stmt->execute([$name, $price, $description, $file_name])) {
                                $success = true;
                                $success_message = "Product added successfully! <a href='products.php'>View in Products</a>";
                                error_log("Product added: $name by admin ID {$_SESSION['user_id']}");
                            } else {
                                $errors[] = "Failed to add product to database.";
                            }
                        } catch (PDOException $e) {
                            $errors[] = "Database error: " . htmlspecialchars($e->getMessage());
                            error_log("Product insertion error: " . $e->getMessage());
                        }
                    } else {
                        $errors[] = "Failed to move uploaded image.";
                    }
                }
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_role') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = "Invalid CSRF token.";
    } else {
        $user_id = (int)$_POST['user_id'];
        $new_role = $_POST['role'];

        if (!in_array($new_role, ['user', 'admin'])) {
            $errors[] = "Invalid role selected.";
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
                if ($stmt->execute([$new_role, $user_id])) {
                    $success = true;
                    $success_message = "User role updated successfully!";
                    error_log("User ID $user_id role changed to $new_role by admin ID {$_SESSION['user_id']}");
                } else {
                    $errors[] = "Failed to update user role.";
                }
            } catch (PDOException $e) {
                $errors[] = "Database error: " . htmlspecialchars($e->getMessage());
                error_log("Role update error: " . $e->getMessage());
            }
        }
    }
}

try {
    $stmt = $pdo->query("SELECT id, username, email, role FROM users");
    $users = $stmt->fetchAll();
    error_log("Fetched " . count($users) . " users");
} catch (PDOException $e) {
    $errors[] = "Failed to fetch users: " . htmlspecialchars($e->getMessage());
    $users = [];
    error_log("User fetch error: " . $e->getMessage());
}
?>

<main>
    <div class="admin-dashboard">
        <h1>Admin Dashboard</h1>
        
        <?php if (!empty($errors)): ?>
            <div class="errors">
                <?php foreach ($errors as $error): ?>
                    <p><?= htmlspecialchars($error) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success">
                <p><?= $success_message ?></p>
            </div>
        <?php endif; ?>

        <h2>Add Product</h2>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="action" value="add_product">

            <div class="form-group">
                <label>Product Name</label>
                <input type="text" name="name" required>
            </div>
            
            <div class="form-group">
                <label>Price</label>
                <input type="number" step="0.01" name="price" required>
            </div>
            
            <div class="form-group">
                <label>Description</label>
                <textarea name="description"></textarea>
            </div>
            
            <div class="form-group">
                <label>Product Image</label>
                <input type="file" name="image" accept="image/*" required>
            </div>
            
            <button type="submit" class="btn-mix">Add Product</button>
        </form>

        <h2>Manage Users</h2>
        <table>
            <tr>
                <th>ID</th>
                <th>Username</th>
                <th>Email</th>
                <th>Role</th>
                <th>Action</th>
            </tr>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td><?php echo htmlspecialchars($user['id']); ?></td>
                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                    <td><?php echo htmlspecialchars($user['role']); ?></td>
                    <td>
                        <form method="POST" action="admin_dashboard.php">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            <input type="hidden" name="action" value="update_role">
                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                            <select name="role">
                                <option value="user" <?php echo $user['role'] === 'user' ? 'selected' : ''; ?>>User</option>
                                <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                            </select>
                            <button type="submit" class="btn-mix">Update Role</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
</main>

<?php require_once 'includes/footer.php'; ?>