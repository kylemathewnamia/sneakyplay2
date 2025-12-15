<?php
// get_cart_count.php
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'cart_count' => 0]);
    exit();
}

// Database connection
$host = 'localhost';
$dbname = 'sneakysheets';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'cart_count' => 0]);
    exit();
}

$user_id = $_SESSION['user_id'];

try {
    // Get cart ID for user
    $stmt = $pdo->prepare("SELECT cart_id FROM cart WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $cart = $stmt->fetch(PDO::FETCH_ASSOC);

    $cart_count = 0;

    if ($cart) {
        $cart_id = $cart['cart_id'];

        // Get total quantity of items in cart
        $stmt = $pdo->prepare("SELECT SUM(quantity) as total_quantity FROM cart_items WHERE cart_id = ?");
        $stmt->execute([$cart_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        $cart_count = $result['total_quantity'] ?? 0;
    }

    echo json_encode([
        'success' => true,
        'cart_count' => $cart_count
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'cart_count' => 0]);
}
