<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$cartCount = 0;
if (isset($_SESSION['cart'])) {
    $cartCount = array_sum($_SESSION['cart']);
}
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
            <li><a href="index.php" class="btn-mix"><span class="shine"></span><span class="particles"></span>Home</a></li>
            <li><a href="products.php" class="btn-mix"><span class="particles"></span>Products</a></li>

            <?php if (isset($_SESSION['user_id'])): ?>
                <?php if ($_SESSION['role'] === 'admin'): ?>
                    <li><a href="admin_dashboard.php" class="btn-mix"><span class="particles"></span>Admin Dashboard</a></li>
                    <li><a href="logout.php" class="btn-mix"><span class="particles"></span>Logout</a></li>
                <?php else: ?>
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
                <li>
                    <a href="cart.php" class="btn-mix">
                        <span class="particles"></span>
                        Cart (<span id="cart-count"><?php echo $cartCount; ?></span>)
                    </a>
                </li>
                <li><a href="login.php" class="btn-mix"><span class="particles"></span>Login</a></li>
                <li><a href="register.php" class="btn-mix"><span class="particles"></span>Register</a></li>
            <?php endif; ?>
        </ul>
    </nav>
</header>

<!-- Place this script near the end of the body or in footer -->
<script>
function addToCart(productId) {
    fetch('add_to_cart.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'id=' + encodeURIComponent(productId)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('cart-count').textContent = data.cartCount;
        } else {
            alert('Failed to add product to cart.');
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });
}
</script>
