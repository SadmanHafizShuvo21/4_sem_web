<?php
session_start();
require_once 'db/config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'], $_POST['action'], $_POST['csrf_token'])) {
    if (!isset($_SESSION['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }

    $productId = intval($_POST['id']);
    $action = $_POST['action'];

    if ($productId <= 0 || !in_array($action, ['add', 'increment', 'decrement', 'remove'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid request']);
        exit;
    }

    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    try {
        $stmt = $pdo->prepare("SELECT id FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        $product = $stmt->fetch();

        if (!$product) {
            echo json_encode(['success' => false, 'message' => 'Product not found']);
            exit;
        }

        $isNewProduct = !isset($_SESSION['cart'][$productId]);

        // Limit checks
        if (($action === 'add' || $action === 'increment') && isset($_SESSION['cart'][$productId]) && $_SESSION['cart'][$productId] >= 10) {
            echo json_encode(['success' => false, 'message' => 'Maximum quantity per product is 10']);
            exit;
        }

        if ($action === 'add') {
            if ($isNewProduct && count($_SESSION['cart']) >= 5) {
                echo json_encode(['success' => false, 'message' => 'Cannot add more than 5 unique products']);
                exit;
            }
            $_SESSION['cart'][$productId] = ($_SESSION['cart'][$productId] ?? 0) + 1;
        } elseif ($action === 'increment') {
            $_SESSION['cart'][$productId]++;
        } elseif ($action === 'decrement') {
            if (isset($_SESSION['cart'][$productId])) {
                $_SESSION['cart'][$productId]--;
                if ($_SESSION['cart'][$productId] <= 0) {
                    unset($_SESSION['cart'][$productId]);
                }
            }
        } elseif ($action === 'remove') {
            unset($_SESSION['cart'][$productId]);
        }

        // DB sync if user is logged in
        if (isset($_SESSION['user_id'])) {
            $userId = $_SESSION['user_id'];
            $quantity = $_SESSION['cart'][$productId] ?? 0;

            $pdo->beginTransaction();

            if ($action === 'add' && $isNewProduct && $quantity > 0) {
                $stmt = $pdo->prepare("SELECT COUNT(DISTINCT product_id) FROM cart WHERE user_id = ?");
                $stmt->execute([$userId]);
                if ($stmt->fetchColumn() >= 5) {
                    unset($_SESSION['cart'][$productId]);
                    $pdo->rollBack();
                    echo json_encode(['success' => false, 'message' => 'Cannot add more than 5 unique products']);
                    exit;
                }
            }

            if ($quantity > 0) {
                $stmt = $pdo->prepare("SELECT quantity FROM cart WHERE user_id = ? AND product_id = ?");
                $stmt->execute([$userId, $productId]);
                if ($stmt->fetch()) {
                    $stmt = $pdo->prepare("UPDATE cart SET quantity = ?, added_at = NOW() WHERE user_id = ? AND product_id = ?");
                    $stmt->execute([$quantity, $userId, $productId]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO cart (user_id, product_id, quantity, added_at) VALUES (?, ?, ?, NOW())");
                    $stmt->execute([$userId, $productId, $quantity]);
                }
            } else {
                $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ? AND product_id = ?");
                $stmt->execute([$userId, $productId]);
            }

            $pdo->commit();
        }

        $cartCount = array_sum($_SESSION['cart']);
        $responseMessage = [
            'add' => 'Product added to cart!',
            'increment' => 'Quantity increased!',
            'decrement' => 'Quantity decreased!',
            'remove' => 'Product removed!'
        ][$action] ?? 'Cart updated!';

        echo json_encode([
            'success' => true,
            'cartCount' => $cartCount,
            'quantity' => $_SESSION['cart'][$productId] ?? 0,
            'message' => $responseMessage
        ]);
        exit;

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log("Cart error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error']);
        exit;
    }
}

echo json_encode(['success' => false, 'message' => 'Invalid request']);
exit;
?>
