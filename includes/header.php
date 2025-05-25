<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Generate CSRF token if not set
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$cartCount = isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>E-Shop</title>
    <link rel="stylesheet" href="css/header.css" />
</head>
<body>
<header>
    <nav>
        <div class="logo">E-Shop</div>
        <ul>
            <!-- Always visible links for all users -->
            <li><a href="index.php" class="btn-mix"><span class="shine"></span><span class="particles"></span>Home</a></li>
            <li><a href="products.php" class="btn-mix"><span class="particles"></span>Products</a></li>

            <?php if (isset($_SESSION['user_id'])): ?>
                <!-- Logged-in user menu -->
                <?php if ($_SESSION['role'] === 'admin'): ?>
                    <!-- Admin-specific links -->
                    <li><a href="admin_dashboard.php" class="btn-mix"><span class="particles"></span>Admin Dashboard</a></li>
                    <li><a href="logout.php" class="btn-mix"><span class="particles"></span>Logout</a></li>
                <?php else: ?>
                    <!-- Regular user links -->
                    <li>
                        <a href="cart.php" class="btn-mix">
                            <span class="particles"></span>
                            Cart (<span id="cart-count"><?php echo $cartCount; ?></span>)
                        </a>
                    </li>
                    <li><a href="profile.php" class="btn-mix"><span class="particles"></span>Profile</a></li>
                    <li><a href="logout.php" class="btn-mix"><span class="particles"></span>Logout</a></li>
                <?php endif; ?>
            <?php else: ?>
                <!-- Guest user links -->
                <li><a href="login.php" class="btn-mix"><span class="particles"></span>Login</a></li>
                <li><a href="register.php" class="btn-mix"><span class="particles"></span>Register</a></li>
            <?php endif; ?>
        </ul>
    </nav>
</header>

<script>
function addToCart(productId, action = 'add') {
    const csrfToken = '<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>';
    fetch('add_to_cart.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `id=${encodeURIComponent(productId)}&action=${encodeURIComponent(action)}&csrf_token=${encodeURIComponent(csrfToken)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('cart-count').textContent = data.cartCount;
            alert(data.message); 
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to update cart. Please try again.');
    });
}
</script>