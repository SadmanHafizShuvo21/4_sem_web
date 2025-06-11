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

    // Fetch ratings for all products in one query
    if (!empty($products)) {
        $productIds = array_column($products, 'id');
        $placeholders = implode(',', array_fill(0, count($productIds), '?'));
        $ratingStmt = $pdo->prepare("
            SELECT product_id, AVG(rating) as avg_rating
            FROM product_ratings 
            WHERE product_id IN ($placeholders)
            GROUP BY product_id
        ");
        $ratingStmt->execute($productIds);
        $ratings = $ratingStmt->fetchAll(PDO::FETCH_ASSOC);

        // Map ratings to products
        $ratingMap = [];
        foreach ($ratings as $rating) {
            $ratingMap[$rating['product_id']] = round($rating['avg_rating'], 1);
        }

        // Attach ratings to products
        foreach ($products as &$product) {
            $product['avg_rating'] = $ratingMap[$product['id']] ?? 0;
        }
        unset($product); // Unset reference
    }
} catch (PDOException $e) {
    $errors[] = "Failed to fetch products: " . htmlspecialchars($e->getMessage());
    $products = [];
}

// Define placeholder image path for JavaScript
$placeholderImage = defined('PLACEHOLDER_IMAGE') ? PLACEHOLDER_IMAGE : 'assets/placeholder.jpg';
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
                     onerror="setPlaceholderImage(this)">
                <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                <p>$<?php echo number_format($product['price'], 2); ?></p>
                <!-- Average rating -->
                <div class="rating" aria-label="Average rating: <?php echo $product['avg_rating']; ?> out of 5">
                    <?php
                    for ($i = 1; $i <= 5; $i++) {
                        echo $i <= round($product['avg_rating']) ? '★' : '☆';
                    }
                    echo " (" . $product['avg_rating'] . ")";
                    ?>
                </div>
                <a href="product.php?id=<?php echo $product['id']; ?>" class="btn-mix">View</a>
                <button class="btn-mix" onclick="addToCartWithFeedback(<?php echo $product['id']; ?>, this)" aria-label="Add <?php echo htmlspecialchars($product['name']); ?> to cart">
                    Add to Cart
                </button>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ($totalProducts > 5): ?>
        <div style="text-align: center; margin: 30px 0;">
            <a href="products.php" class="btn-mix">Load More</a>
        </div>
    <?php endif; ?>
</main>

<script>
// Define placeholder image path
const placeholderImage = '<?php echo htmlspecialchars($placeholderImage); ?>';

// Set placeholder image on error
function setPlaceholderImage(img) {
    img.src = placeholderImage;
    img.onerror = null; // Prevent infinite loop
}

// Enhanced addToCart function with loading feedback
function addToCartWithFeedback(productId, button) {
    const originalText = button.textContent;
    button.textContent = 'Adding...';
    button.disabled = true;

    addToCart(productId, 'add').finally(() => {
        button.textContent = originalText;
        button.disabled = false;
    });
}
</script>

<?php require_once 'includes/footer.php'; ?>