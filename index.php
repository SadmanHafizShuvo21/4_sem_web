<?php
session_start();
require_once 'includes/header.php';
require_once 'db/config.php';
echo '<link rel="stylesheet" href="css/index.css">';

$errors = [];

try {
    $stmtCount = $pdo->query("SELECT COUNT(*) FROM products");
    $totalProducts = (int)$stmtCount->fetchColumn();
} catch (PDOException $e) {
    $errors[] = "Failed to count products: " . htmlspecialchars($e->getMessage());
    $totalProducts = 0;
}

try {
    $stmt = $pdo->query("SELECT * FROM products ORDER BY created_at DESC LIMIT 5");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errors[] = "Failed to fetch products: " . htmlspecialchars($e->getMessage());
    $products = [];
}
?>

<main>
    <h1>Welcome to E-Shop</h1>

    <?php if (!empty($errors)): ?>
        <div class="errors" style="color: red; max-width: 600px; margin: 0 auto;">
            <?php foreach ($errors as $error): ?>
                <p><?php echo htmlspecialchars($error); ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="product-grid">
        <?php foreach ($products as $product): ?>
            <div class="product-card">
                <img src="assets/<?php echo htmlspecialchars($product['image']); ?>"
                     alt="<?php echo htmlspecialchars($product['name']); ?>"
                     onerror="this.src='<?php echo defined('PLACEHOLDER_IMAGE') ? PLACEHOLDER_IMAGE : 'assets/placeholder.jpg'; ?>';">
                <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                <p>$<?php echo number_format($product['price'], 2); ?></p>
                <a href="product.php?id=<?php echo $product['id']; ?>" class="btn-mix">View</a>
                <button class="btn-mix" onclick="addToCart(<?php echo $product['id']; ?>, 'add')">Add to Cart</button>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ($totalProducts > 5): ?>
        <div style="text-align: center; margin: 30px 0;">
            <a href="products.php" class="btn-mix">Load More</a>
        </div>
    <?php endif; ?>
</main>

<?php require_once 'includes/footer.php'; ?>