<?php
session_start();
require_once 'db/config.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'], $_POST['action'], $_POST['csrf_token'])) {
    if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }

    $productId = intval($_POST['id']);
    $action = $_POST['action'];
    if ($productId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
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

        if (($action === 'add' || $action === 'increment') && isset($_SESSION['cart'][$productId]) && $_SESSION['cart'][$productId] >= 10) {
            echo json_encode(['success' => false, 'message' => 'Maximum quantity per product is 10']);
            exit;
        }

        switch ($action) {
            case 'add':
                if (!isset($_SESSION['cart'][$productId]) && count($_SESSION['cart']) >= 5) {
                    echo json_encode(['success' => false, 'message' => 'Cannot add more than 5 unique products to the cart']);
                    exit;
                }
                $_SESSION['cart'][$productId] = ($_SESSION['cart'][$productId] ?? 0) + 1;
                break;
            case 'increment':
                $_SESSION['cart'][$productId]++;
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
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
                exit;
        }

        if (isset($_SESSION['user_id'])) {
            $userId = $_SESSION['user_id'];
            $quantity = isset($_SESSION['cart'][$productId]) ? $_SESSION['cart'][$productId] : 0;
            $pdo->beginTransaction();
            if ($action === 'add' && $quantity > 0 && !isset($_SESSION['cart'][$productId])) {
                $stmt = $pdo->prepare("SELECT COUNT(DISTINCT product_id) FROM cart WHERE user_id = ?");
                $stmt->execute([$userId]);
                if ($stmt->fetchColumn() >= 5) {
                    unset($_SESSION['cart'][$productId]);
                    $pdo->rollBack();
                    echo json_encode(['success' => false, 'message' => 'Cannot add more than 5 unique products to the cart']);
                    exit;
                }
            }
            if ($quantity > 0) {
                $stmt = $pdo->prepare("SELECT quantity FROM cart WHERE user_id = ? AND product_id = ?");
                $stmt->execute([$userId, $productId]);
                if ($stmt->fetch()) {
                    $stmt = $pdo->prepare("UPDATE cart SET quantity = ? WHERE user_id = ? AND product_id = ?");
                    $stmt->execute([$quantity, $userId, $productId]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)");
                    $stmt->execute([$userId, $productId, $quantity]);
                }
            } else {
                $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ? AND product_id = ?");
                $stmt->execute([$userId, $productId]);
            }
            $pdo->commit();
        }

        $cartCount = array_sum($_SESSION['cart']);
        $message = [
            'add' => 'Product added to cart!',
            'increment' => 'Quantity increased!',
            'decrement' => 'Quantity decreased!',
            'remove' => 'Product removed from cart!'
        ][$action] ?? 'Cart updated!';
        echo json_encode(['success' => true, 'cartCount' => $cartCount, 'message' => $message]);
        exit;
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Cart action error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error']);
        exit;
    }
}

echo json_encode(['success' => false, 'message' => 'Invalid request']);
exit;
?>