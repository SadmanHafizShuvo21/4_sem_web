<?php
session_start();
require_once 'includes/header.php';
require_once 'db/config.php';
echo '<link rel="stylesheet" href="css/cart.css">';

$errors = [];

// Handle POST actions (add, increment, decrement, remove)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['product_id'])) {
    $productId = (int) $_POST['product_id'];

    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    switch ($_POST['action']) {
        case 'add':
        case 'increment':
            if (isset($_SESSION['cart'][$productId])) {
                $_SESSION['cart'][$productId]++;
            } else {
                $_SESSION['cart'][$productId] = 1;
            }
            break;
        case 'decrement':
            if (isset($_SESSION['cart'][$productId])) {
                $_SESSION['cart'][$productId]--;
                if ($_SESSION['cart'][$productId] <= 0) {
                    unset($_SESSION['cart'][$productId]);
                }
            }
            break;
        case 'remove':
            unset($_SESSION['cart'][$productId]);
            break;
    }

    // Redirect to prevent form resubmission
    header("Location: cart.php");
    exit;
}

// Load cart products
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

    <?php if (!empty($errors)): ?>
        <div class="errors">
            <?php foreach ($errors as $error): ?>
                <p><?php echo htmlspecialchars($error); ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (empty($productsInCart)): ?>
        <div class="empty-cart" style="text-align: center; margin-top: 50px;">
            <img src="images/cart.jpg" alt="Empty Cart" style="max-width: 300px;">
            <h2>Your cart is currently empty</h2>
            <p>Looks like you haven't added anything to your cart yet.</p>
            <a href="products.php" style="padding: 10px 20px; background-color: #007bff; color: white; text-decoration: none; border-radius: 5px;">Start Shopping</a>
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
                        <td><?php echo htmlspecialchars($product['name']); ?></td>
                        <td>
                            <img src="assets/<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" style="max-height: 50px;" onerror="this.src='assets/placeholder.jpg';">
                        </td>
                        <td>$<?php echo number_format($product['price'], 2); ?></td>
                        <td><?php echo $quantity; ?></td>
                        <td>$<?php echo number_format($subtotal, 2); ?></td>
                        <td>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="product_id" value="<?php echo $productId; ?>">
                                <button type="submit" name="action" value="decrement">-</button>
                            </form>

                            <form method="post" style="display:inline;">
                                <input type="hidden" name="product_id" value="<?php echo $productId; ?>">
                                <button type="submit" name="action" value="increment">+</button>
                            </form>

                            <form method="post" style="display:inline;">
                                <input type="hidden" name="product_id" value="<?php echo $productId; ?>">
                                <button type="submit" name="action" value="remove">Remove</button>
                            </form>
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