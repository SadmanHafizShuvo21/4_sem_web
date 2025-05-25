<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'includes/header.php';
require_once 'db/config.php';
require_once 'vendor/autoload.php';

\Stripe\Stripe::setApiKey('your_stripe_secret_key'); // Replace with your Stripe secret key

$session_id = $_GET['session_id'] ?? null;
$errors = [];

if (!$session_id) {
    $errors[] = "Invalid session ID.";
}

try {
    $session = \Stripe\Checkout\Session::retrieve($session_id);
    $order_id = $session->metadata['order_id'] ?? null;

    if ($session->payment_status === 'paid' && $order_id) {
        $stmt = $pdo->prepare("UPDATE orders SET status = 'completed' WHERE id = ?");
        $stmt->execute([$order_id]);
    }

    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    $stmt = $pdo->prepare("SELECT o.*, oi.product_id, oi.quantity, oi.price, p.name 
                           FROM orders o 
                           JOIN order_items oi ON o.id = oi.order_id 
                           JOIN products p ON oi.product_id = p.id 
                           WHERE o.id = ? AND (o.user_id = ? OR o.user_id IS NULL)");
    $stmt->execute([$order_id, $user_id]);
    $order = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($order)) {
        $errors[] = "Order not found.";
    }
} catch (Exception $e) {
    error_log("Order confirmation error: " . $e->getMessage());
    $errors[] = "Unable to process order confirmation.";
}
?>
<main class="order-confirmation">
    <h1>Order Confirmation</h1>
    <?php if (!empty($errors)): ?>
        <div class="errors">
            <?php foreach ($errors as $error): ?>
                <p><?php echo htmlspecialchars($error); ?></p>
            <?php endforeach; ?>
        </div>
    <?php elseif ($session->payment_status === 'paid'): ?>
        <p>Thank you for your purchase! Your order has been successfully processed.</p>
        <table class="cart-table">
            <tr>
                <th>Product</th>
                <th>Price</th>
                <th>Quantity</th>
                <th>Total</th>
            </tr>
            <?php $total = 0; ?>
            <?php foreach ($order as $item): ?>
                <tr>
                    <td><?php echo htmlspecialchars($item['name']); ?></td>
                    <td>$<?php echo number_format($item['price'], 2); ?></td>
                    <td><?php echo $item['quantity']; ?></td>
                    <td>$<?php echo number_format($item['price'] * $item['quantity'], 2); ?></td>
                </tr>
                <?php $total += $item['price'] * $item['quantity']; ?>
            <?php endforeach; ?>
            <tr>
                <td colspan="3" style="text-align: right;">Total</td>
                <td>$<?php echo number_format($total, 2); ?></td>
            </tr>
        </table>
        <a href="products.php" class="btn-mix">Continue Shopping</a>
    <?php else: ?>
        <p>Payment not completed. Please try again.</p>
        <a href="checkout.php" class="btn-mix">Back to Checkout</a>
    <?php endif; ?>
</main>
<?php require_once 'includes/footer.php'; ?>