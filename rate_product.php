<?php
session_start();

// Validate CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION['flash_error'] = "Invalid request";
    header("Location: products.php");
    exit();
}

// Check authentication
if (!isset($_SESSION['user_id'])) {
    $_SESSION['flash_error'] = "Please login to rate products";
    header("Location: login.php");
    exit();
}

// Validate inputs
$productId = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
$rating = filter_input(INPUT_POST, 'rating', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1, 'max_range' => 5]
]);

if (!$productId || !$rating) {
    $_SESSION['flash_error'] = "Invalid rating data";
    header("Location: products.php");
    exit();
}

require_once 'db/config.php';

// Verify product exists
try {
    $stmt = $pdo->prepare("SELECT id FROM products WHERE id = ?");
    $stmt->execute([$productId]);
    if (!$stmt->fetch()) {
        throw new Exception("Product not found");
    }
} catch (Exception $e) {
    $_SESSION['flash_error'] = $e->getMessage();
    header("Location: products.php");
    exit();
}

// Update rating
try {
    $stmt = $pdo->prepare("
        INSERT INTO product_ratings (user_id, product_id, rating)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE rating = VALUES(rating)
    ");
    $stmt->execute([$_SESSION['user_id'], $productId, $rating]);
    
    $_SESSION['flash_message'] = "Rating updated successfully!";
} catch (PDOException $e) {
    $_SESSION['flash_error'] = "Error saving rating: " . $e->getMessage();
}

header("Location: products.php");
exit();