<?php
session_start();
require_once 'includes/header.php';
require_once 'db/config.php';
echo '<link rel="stylesheet" href="css/products.css">';

// Initialize variables
$errors = [];
$products = [];

try {
    // Fetch all products
    $stmt = $pdo->query("SELECT * FROM products ORDER BY created_at DESC");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch ratings for all products in one query
    if (!empty($products)) {
        $productIds = array_column($products, 'id');
        $placeholders = implode(',', array_fill(0, count($productIds), '?'));
        $ratingStmt = $pdo->prepare("
            SELECT product_id, AVG(rating) as avg_rating, 
                   MAX(CASE WHEN user_id = ? THEN rating END) as user_rating
            FROM product_ratings 
            WHERE product_id IN ($placeholders)
            GROUP BY product_id
        ");
        $params = array_merge([$_SESSION['user_id'] ?? 0], $productIds);
        $ratingStmt->execute($params);
        $ratings = $ratingStmt->fetchAll(PDO::FETCH_ASSOC);

        // Map ratings to products
        $ratingMap = [];
        foreach ($ratings as $rating) {
            $ratingMap[$rating['product_id']] = [
                'avg_rating' => round($rating['avg_rating'], 1),
                'user_rating' => $rating['user_rating']
            ];
        }

        // Attach ratings to products
        foreach ($products as &$product) {
            $product['avg_rating'] = $ratingMap[$product['id']]['avg_rating'] ?? 0;
            $product['user_rating'] = $ratingMap[$product['id']]['user_rating'] ?? null;
        }
        unset($product); // Unset reference
    }
} catch (PDOException $e) {
    $errors[] = "Failed to load products or ratings: " . htmlspecialchars($e->getMessage());
}

// Define placeholder image path for JavaScript
$placeholderImage = defined('PLACEHOLDER_IMAGE') ? PLACEHOLDER_IMAGE : 'assets/placeholder.jpg';
?>

<main>
    <h1>All Products</h1>

    <!-- Display success message -->
    <?php if (isset($_SESSION['flash_message'])): ?>
        <div class="success" style="background-color: #e6ffed; color: #2e7d32; border-left: 6px solid #4caf50; padding: 18px 25px; margin-bottom: 30px; border-radius: 8px; font-weight: 600;">
            <?php echo htmlspecialchars($_SESSION['flash_message']); ?>
        </div>
        <?php unset($_SESSION['flash_message']); ?>
    <?php endif; ?>

    <!-- Display error message -->
    <?php if (isset($_SESSION['flash_error'])): ?>
        <div class="errors">
            <p><?php echo htmlspecialchars($_SESSION['flash_error']); ?></p>
        </div>
        <?php unset($_SESSION['flash_error']); ?>
    <?php endif; ?>

    <!-- Display database errors -->
    <?php if (!empty($errors)): ?>
        <div class="errors">
            <?php foreach ($errors as $error): ?>
                <p><?php echo htmlspecialchars($error); ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (empty($products)): ?>
        <div class="empty-state" style="text-align: center; margin-top: 50px;">
            <h2>No products available</h2>
            <p>Check back later or explore our homepage!</p>
            <a href="index.php" class="btn-mix">Go to Homepage</a>
        </div>
    <?php else: ?>
        <div class="product-grid">
            <?php foreach ($products as $product): ?>
                <div class="product-card">
                    <!-- Product image -->
                    <img src="assets/<?php echo htmlspecialchars($product['image']); ?>" 
                         alt="<?php echo htmlspecialchars($product['name']); ?>" 
                         onerror="setPlaceholderImage(this)">

                    <!-- Product details -->
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

                    <!-- User rating form -->
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <form method="POST" action="rate_product.php" onchange="this.submit()" aria-label="Rate <?php echo htmlspecialchars($product['name']); ?>">
                            <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <label for="rating-<?php echo $product['id']; ?>" class="visually-hidden">Your rating for <?php echo htmlspecialchars($product['name']); ?>:</label>
                            <select name="rating" id="rating-<?php echo $product['id']; ?>" aria-describedby="rating-help-<?php echo $product['id']; ?>">
                                <?php for ($i = 5; $i >= 1; $i--): ?>
                                    <option value="<?php echo $i; ?>" <?php echo ($product['user_rating'] == $i) ? 'selected' : ''; ?>>
                                        <?php echo str_repeat('★', $i); ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                            <span id="rating-help-<?php echo $product['id']; ?>" class="visually-hidden">Select a rating from 1 to 5 stars</span>
                        </form>
                    <?php endif; ?>

                    <!-- Add to cart button -->
                    <button class="btn-mix" onclick="addToCartWithFeedback(<?php echo $product['id']; ?>, this)" aria-label="Add <?php echo htmlspecialchars($product['name']); ?> to cart">
                        Add to Cart
                    </button>
                </div>
            <?php endforeach; ?>
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

<style>
/* Visually hidden class for accessibility */
.visually-hidden {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    border: 0;
}
</style>

<?php require_once 'includes/footer.php'; ?>