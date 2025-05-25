<?php
session_start();
require_once 'includes/header.php';
require_once 'db/config.php';
echo '<link rel="stylesheet" href="css/cart.css">';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['product_id'], $_POST['csrf_token'])) {
    if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = "Invalid CSRF token";
    } else {
        $productId = (int) $_POST['product_id'];
        $stmt = $pdo->prepare("SELECT id FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        if (!$stmt->fetch()) {
            $errors[] = "Invalid product ID: $productId";
        } else {
            if (!isset($_SESSION['cart'])) {
                $_SESSION['cart'] = [];
            }
            if (($_POST['action'] === 'add' || $_POST['action'] === 'increment') && isset($_SESSION['cart'][$productId]) && $_SESSION['cart'][$productId] >= 10) {
                $errors[] = "Maximum quantity per product is 10";
            } else {
                switch ($_POST['action']) {
                    case 'add':
                        if (!isset($_SESSION['cart'][$productId]) && count($_SESSION['cart']) >= 5) {
                            $errors[] = "Cannot add more than 5 unique products to the cart";
                            break;
                        }
                        $_SESSION['cart'][$productId] = ($_SESSION['cart'][$productId] ?? 0) + 1;
                        $_SESSION['flash_message'] = "Product added to cart!";
                        break;
                    case 'increment':
                        $_SESSION['cart'][$productId]++;
                        $_SESSION['flash_message'] = "Quantity increased!";
                        break;
                    case 'decrement':
                        if (isset($_SESSION['cart'][$productId])) {
                            $_SESSION['cart'][$productId]--;
                            if ($_SESSION['cart'][$productId] <= 0) {
                                unset($_SESSION['cart'][$productId]);
                            }
                            $_SESSION['flash_message'] = "Quantity decreased!";
                        }
                        break;
                    case 'remove':
                        unset($_SESSION['cart'][$productId]);
                        $_SESSION['flash_message'] = "Product removed from cart!";
                        break;
                }
                if (isset($_SESSION['user_id']) && empty($errors)) {
                    $userId = $_SESSION['user_id'];
                    $quantity = isset($_SESSION['cart'][$productId]) ? $_SESSION['cart'][$productId] : 0;
                    $pdo->beginTransaction();
                    if ($_POST['action'] === 'add' && $quantity > 0 && !isset($_SESSION['cart'][$productId])) {
                        $stmt = $pdo->prepare("SELECT COUNT(DISTINCT product_id) FROM cart WHERE user_id = ?");
                        $stmt->execute([$userId]);
                        if ($stmt->fetchColumn() >= 5) {
                            unset($_SESSION['cart'][$productId]);
                            $pdo->rollBack();
                            $errors[] = "Cannot add more than 5 unique products to the cart";
                        }
                    }
                    if ($quantity > 0 && empty($errors)) {
                        $stmt = $pdo->prepare("SELECT quantity FROM cart WHERE user_id = ? AND product_id = ?");
                        $stmt->execute([$userId, $productId]);
                        if ($stmt->fetch()) {
                            $stmt = $pdo->prepare("UPDATE cart SET quantity = ? WHERE user_id = ? AND product_id = ?");
                            $stmt->execute([$quantity, $userId, $productId]);
                        } else {
                            $stmt = $pdo->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)");
                            $stmt->execute([$userId, $productId, $quantity]);
                        }
                    } else {
                        $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ? AND product_id = ?");
                        $stmt->execute([$userId, $productId]);
                    }
                    $pdo->commit();
                }
            }
        }
    }
    header("Location: cart.php");
    exit;
}

$cart = $_SESSION['cart'] ?? [];
$productsInCart = [];
$total = 0.0;

if (!empty($cart)) {
    try {
        $placeholders = implode(',', array_fill(0, count($cart), '?'));
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id IN ($placeholders)");
        $stmt->execute(array_keys($cart));
        $productsInCart = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($productsInCart as $product) {
            $productId = $product['id'];
            $quantity = $cart[$productId];
            $total += $product['price'] * $quantity;
        }
    } catch (PDOException $e) {
        $errors[] = "Failed to load cart products: " . htmlspecialchars($e->getMessage());
    }
}
?>

<main>
    <h1>Your Shopping Cart</h1>

    <?php if (isset($_SESSION['flash_message'])): ?>
        <div class="success" style="background-color: #e6ffed; color: #2e7d32; border-left: 6px solid #4caf50; padding: 18px 25px; margin-bottom: 30px; border-radius: 8px; font-weight: 600;">
            <?php echo htmlspecialchars($_SESSION['flash_message']); ?>
        </div>
        <?php unset($_SESSION['flash_message']); ?>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="errors">
            <?php foreach ($errors as $error): ?>
                <p><?php echo htmlspecialchars($error); ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (count($cart) >= 5): ?>
        <div class="warning" style="background-color: #fff3cd; color: #856404; border-left: 6px solid #ffc107; padding: 18px 25px; margin-bottom: 30px; border-radius: 8px; font-weight: 600;">
            You have reached the maximum of 5 unique products in your cart.
        </div>
    <?php endif; ?>

    <?php if (empty($productsInCart)): ?>
        <div class="empty-cart" style="text-align: center; margin-top: 50px;">
            <img src="images/cart.jpg" alt="Empty Cart" style="max-width: 300px;">
            <h2>Your cart is currently empty</h2>
            <p>Looks like you haven't added anything to your cart yet.</p>
            <a href="products.php" class="btn-mix">Start Shopping</a>
        </div>
    <?php else: ?>
        <table border="1" cellpadding="10" cellspacing="0" style="width: 100%;">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Image</th>
                    <th>Price</th>
                    <th>Quantity</th>
                    <th>Subtotal</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($productsInCart as $product): 
                    $productId = $product['id'];
                    $quantity = $cart[$productId];
                    $subtotal = $product['price'] * $quantity;
                ?>
                    <tr>
                        <td data-label="Product"><?php echo htmlspecialchars($product['name']); ?></td>
                        <td data-label="Image">
                            <img src="assets/<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" style="max-height: 50px;" onerror="this.src='<?php echo defined('PLACEHOLDER_IMAGE') ? PLACEHOLDER_IMAGE : 'assets/placeholder.jpg'; ?>';">
                        </td>
                        <td data-label="Price">$<?php echo number_format($product['price'], 2); ?></td>
                        <td data-label="Quantity"><?php echo $quantity; ?></td>
                        <td data-label="Subtotal">$<?php echo number_format($subtotal, 2); ?></td>
                        <td data-label="Actions">
                            <button class="btn-mix" onclick="addToCart(<?php echo $productId; ?>, 'decrement')">-</button>
                            <button class="btn-mix" onclick="addToCart(<?php echo $productId; ?>, 'increment')">+</button>
                            <button class="btn-mix" onclick="addToCart(<?php echo $productId; ?>, 'remove')">Remove</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="4" style="text-align: right;"><strong>Total:</strong></td>
                    <td colspan="2"><strong>$<?php echo number_format($total, 2); ?></strong></td>
                </tr>
            </tfoot>
        </table>
    <?php endif; ?>
</main>

<?php require_once 'includes/footer.php'; ?>