<?php
session_start();
require_once 'db/config.php';

if (!isset($_SESSION['user_id']) || !isset($_POST['product_id'], $_POST['rating'], $_POST['csrf_token'])) {
    header("Location: products.php");
    exit;
}

if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION['flash_error'] = "Invalid CSRF token";
    header("Location: products.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$product_id = (int)$_POST['product_id'];
$rating = (int)$_POST['rating'];

if ($rating < 1 || $rating > 5) {
    $_SESSION['flash_error'] = "Invalid rating value";
    header("Location: products.php");
    exit;
}

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("SELECT id FROM product_ratings WHERE user_id = ? AND product_id = ?");
    $stmt->execute([$user_id, $product_id]);
    if ($stmt->rowCount() > 0) {
        $update = $pdo->prepare("UPDATE product_ratings SET rating = ?, updated_at = NOW() WHERE user_id = ? AND product_id = ?");
        $update->execute([$rating, $user_id, $product_id]);
        $_SESSION['flash_message'] = "Rating updated successfully!";
    } else {
        $insert = $pdo->prepare("INSERT INTO product_ratings (user_id, product_id, rating, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())");
        $insert->execute([$user_id, $product_id, $rating]);
        $_SESSION['flash_message'] = "Rating submitted successfully!";
    }
    $pdo->commit();
} catch (PDOException $e) {
    $pdo->rollBack();
    $_SESSION['flash_error'] = "Failed to submit rating: " . htmlspecialchars($e->getMessage());
}

header("Location: products.php");
exit;
?>