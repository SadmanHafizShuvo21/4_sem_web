<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'includes/header.php';
require_once 'db/config.php';

$errors = [];
$cart_items = [];
$total = 0;

// CSRF Protection
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Check for Stripe library
if (!file_exists('vendor/autoload.php')) {
    $errors[] = "Stripe payment processing is not available. Please contact support.";
} else {
    require_once 'vendor/autoload.php';
    \Stripe\Stripe::setApiKey('your_stripe_secret_key'); // Replace with your Stripe secret key
}

// Fetch cart items
try {
    if (isset($_SESSION['user_id'])) {
        $stmt = $pdo->prepare("SELECT p.*, c.quantity FROM cart c JOIN products p ON c.product_id = p.id WHERE c.user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($cart_items as $item) {
            $total += $item['price'] * $item['quantity'];
        }
    } elseif (isset($_SESSION['cart'])) {
        $ids = array_keys($_SESSION['cart']);
        if ($ids) {
            $stmt = $pdo->prepare("SELECT * FROM products WHERE id IN (" . implode(',', array_fill(0, count($ids), '?')) . ")");
            $stmt->execute($ids);
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($products as $product) {
                $product['quantity'] = $_SESSION['cart'][$product['id']];
                $cart_items[] = $product;
                $total += $product['price'] * $product['quantity'];
            }
        }
    }
} catch (PDOException $e) {
    error_log("Error fetching cart items: " . $e->getMessage());
    $errors[] = "Unable to load cart. Please try again.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_checkout') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = "Invalid CSRF token.";
    } elseif (empty($cart_items)) {
        $errors[] = "Your cart is empty.";
    } elseif (!file_exists('vendor/autoload.php')) {
        $errors[] = "Payment processing is not configured.";
    } else {
        try {
            // Validate stock
            foreach ($cart_items as $item) {
                if (!isset($item['stock']) || $item['stock'] < $item['quantity']) {
                    $errors[] = "Insufficient stock for " . htmlspecialchars($item['name']);
                }
            }

            if (empty($errors)) {
                $line_items = [];
                foreach ($cart_items as $item) {
                    $line_items[] = [
                        'price_data' => [
                            'currency' => 'usd',
                            'product_data' => [
                                'name' => $item['name'],
                            ],
                            'unit_amount' => $item['price'] * 100, // Stripe expects cents
                        ],
                        'quantity' => $item['quantity'],
                    ];
                }

                $session = \Stripe\Checkout\Session::create([
                    'payment_method_types' => ['card'],
                    'line_items' => $line_items,
                    'mode' => 'payment',
                    'success_url' => 'http://yourdomain.com/order_confirmation.php?session_id={CHECKOUT_SESSION_ID}',
                    'cancel_url' => 'http://yourdomain.com/checkout.php',
                    'metadata' => ['order_id' => $pdo->lastInsertId()]
                ]);

                // Save order to database
                $pdo->beginTransaction();
                $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
                $stmt = $pdo->prepare("INSERT INTO orders (user_id, total, status, created_at) VALUES (?, ?, 'pending', NOW())");
                $stmt->execute([$user_id, $total]);
                $order_id = $pdo->lastInsertId();

                foreach ($cart_items as $item) {
                    $stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$order_id, $item['id'], $item['quantity'], $item['price']]);
                    $stmt = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
                    $stmt->execute([$item['quantity'], $item['id']]);
                }

                // Clear cart
                if ($user_id) {
                    $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ?");
                    $stmt->execute([$user_id]);
                } else {
                    unset($_SESSION['cart']);
                }

                $pdo->commit();
                echo json_encode(['success' => true, 'sessionId' => $session->id]);
                exit;
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Checkout error: " . $e->getMessage());
            $errors[] = "Checkout failed. Please try again.";
        }
    }
}
?>
<main class="checkout-container">
    <h1>Checkout</h1>
    <?php if (!empty($errors)): ?>
        <div class="errors">
            <?php foreach ($errors as $error): ?>
                <p><?php echo htmlspecialchars($error); ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?php if (empty($cart_items)): ?>
        <p>Your cart is empty.</p>
    <?php else: ?>
        <table class="cart-table">
            <tr>
                <th>Product</th>
                <th>Price</th>
                <th>Quantity</th>
                <th>Total</th>
            </tr>
            <?php foreach ($cart_items as $item): ?>
                <tr>
                    <td><?php echo htmlspecialchars($item['name']); ?></td>
                    <td>$<?php echo number_format($item['price'], 2); ?></td>
                    <td><?php echo $item['quantity']; ?></td>
                    <td>$<?php echo number_format($item['price'] * $item['quantity'], 2); ?></td>
                </tr>
            <?php endforeach; ?>
            <tr>
                <td colspan="3" style="text-align: right;">Total</td>
                <td>$<?php echo number_format($total, 2); ?></td>
            </tr>
        </table>
        <form id="payment-form" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="action" value="create_checkout">
            <button type="submit" class="btn-mix">Pay Now</button>
        </form>
    <?php endif; ?>
</main>
<script src="https://js.stripe.com/v3/"></script>
<script>
const stripe = Stripe('your_stripe_publishable_key'); // Replace with your Stripe publishable key
const form = document.getElementById('payment-form');
form.addEventListener('submit', async (event) => {
    event.preventDefault();
    const response = await fetch('checkout.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams(new FormData(form)).toString()
    });
    const data = await response.json();
    if (data.success) {
        stripe.redirectToCheckout({ sessionId: data.sessionId });
    } else {
        const errorsDiv = document.createElement('div');
        errorsDiv.className = 'errors';
        errorsDiv.innerHTML = `<p>${data.message || 'Checkout failed'}</p>`;
        document.querySelector('.checkout-container').prepend(errorsDiv);
    }
});
</script>
<?php require_once 'includes/footer.php'; ?>