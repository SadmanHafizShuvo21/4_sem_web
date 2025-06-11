<?php
session_start();
require_once 'includes/header.php';
require_once 'db/config.php';
echo '<link rel="stylesheet" href="css/product.css">';

// Initialize variables
$errors = [];
$product = null;
$avg_rating = 0;
$user_rating = null;

// Get product ID from URL
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($product_id <= 0) {
    $errors[] = "Invalid product ID.";
} else {
    try {
        // Fetch product details
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            $errors[] = "Product not found.";
        } else {
            // Fetch ratings for the product
            $ratingStmt = $pdo->prepare("
                SELECT AVG(rating) as avg_rating, 
                       MAX(CASE WHEN user_id = ? THEN rating END) as user_rating
                FROM product_ratings 
                WHERE product_id = ?
            ");
            $ratingStmt->execute([$_SESSION['user_id'] ?? 0, $product_id]);
            $rating = $ratingStmt->fetch(PDO::FETCH_ASSOC);

            $avg_rating = $rating['avg_rating'] ? round($rating['avg_rating'], 1) : 0;
            $user_rating = $rating['user_rating'] ?? null;
        }
    } catch (PDOException $e) {
        $errors[] = "Failed to load product details: " . htmlspecialchars($e->getMessage());
    }
}

// Define placeholder image path for JavaScript
$placeholderImage = defined('PLACEHOLDER_IMAGE') ? PLACEHOLDER_IMAGE : 'assets/placeholder.jpg';
?>

<main>
    <h1>Product Details</h1>

    <!-- Display error messages -->
    <?php if (!empty($errors)): ?>
        <div class="errors" style="color: red; max-width: 600px; margin: 0 auto;">
            <?php foreach ($errors as $error): ?>
                <p><?php echo htmlspecialchars($error); ?></p>
            <?php endforeach; ?>
        </div>
    <?php elseif ($product): ?>
        <div class="product-detail">
            <!-- Product image -->
            <img src="assets/<?php echo htmlspecialchars($product['image']); ?>" 
                 alt="<?php echo htmlspecialchars($product['name']); ?>" 
                 onerror="setPlaceholderImage(this)"
                 style="max-width: 300px; height: auto; border-radius: 8px;">

            <!-- Product details -->
            <h2><?php echo htmlspecialchars($product['name']); ?></h2>
            <p><strong>Price:</strong> $<?php echo number_format($product['price'], 2); ?></p>
            <p><strong>Description:</strong> <?php echo htmlspecialchars($product['description'] ?? 'No description available.'); ?></p>

            <!-- Average rating -->
            <div class="rating" aria-label="Average rating: <?php echo $avg_rating; ?> out of 5">
                <strong>Average Rating:</strong> 
                <?php
                for ($i = 1; $i <= 5; $i++) {
                    echo $i <= round($avg_rating) ? '★' : '☆';
                }
                echo " (" . $avg_rating . ")";
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
                            <option value="<?php echo $i; ?>" <?php echo ($user_rating == $i) ? 'selected' : ''; ?>>
                                <?php echo str_repeat('★', $i); ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                    <span id="rating-help-<?php echo $product['id']; ?>" class="visually-hidden">Select a rating from 1 to 5 stars</span>
                </form>
            <?php endif; ?>
        </div>
        
    <?php endif; ?>
    <!-- Buttons below rating -->
<div class="button-group">
    <button class="btn-mix" onclick="addToCartWithFeedback(<?php echo $product['id']; ?>, this)">
        Add to Cart
    </button>
    <a href="products.php" class="btn-mix">Back to Products</a>
</div>
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