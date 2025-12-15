<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$order_id = $_GET['id'] ?? 0;
$user_id = $_SESSION['user_id'];

// Database connection
$host = 'localhost';
$dbname = 'sneakysheets';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Get user data for header
$user_stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$user_stmt->execute([$user_id]);
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

// Get order details
$order_stmt = $pdo->prepare("
    SELECT o.*, 
           COUNT(oi.order_item_id) as item_count
    FROM orders o
    LEFT JOIN order_items oi ON o.order_id = oi.order_id
    WHERE o.order_id = ? AND o.user_id = ?
    GROUP BY o.order_id
");
$order_stmt->execute([$order_id, $user_id]);
$order = $order_stmt->fetch(PDO::FETCH_ASSOC);

// Get order items with product details including images
$items_stmt = $pdo->prepare("
    SELECT oi.*, p.product_name, p.price, p.image, p.description
    FROM order_items oi
    JOIN products p ON oi.product_id = p.product_id
    WHERE oi.order_id = ?
");
$items_stmt->execute([$order_id]);
$order_items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$order) {
    header('Location: orders.php');
    exit();
}

// Handle order cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_order'])) {
    try {
        $cancel_stmt = $pdo->prepare("UPDATE orders SET status = 'cancelled' WHERE order_id = ? AND user_id = ? AND status = 'pending'");
        $cancel_stmt->execute([$order_id, $user_id]);

        // Return stock to inventory
        foreach ($order_items as $item) {
            $update_stmt = $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity + ? WHERE product_id = ?");
            $update_stmt->execute([$item['quantity'], $item['product_id']]);
        }

        header('Location: orders.php?message=order_cancelled');
        exit();
    } catch (Exception $e) {
        $error_message = "Failed to cancel order: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmation - SneakyPlay</title>
    <link rel="stylesheet" href="/sneakyplay2/assets/css/user_dashboard.css">
    <link rel="stylesheet" href="/sneakyplay2/assets/css/order-confirmation.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" type="image/png" href="../assets/image/logo.png">
</head>

<body>
    <header class="dashboard-header">
        <div class="logo">
            <i class="fas fa-gamepad"></i> SneakyPlay
        </div>
        <div class="header-right">
            <div class="search-bar">
                <input type="text" placeholder="Search games, consoles, accessories...">
                <button><i class="fas fa-search"></i></button>
            </div>
            <div class="user-menu">
                <div class="avatar-placeholder">
                    <?php echo strtoupper(substr($user['name'] ?? 'U', 0, 1)); ?>
                </div>
                <span><?php echo htmlspecialchars($user['name'] ?? $_SESSION['username'] ?? 'User'); ?></span>
                <div class="dropdown">
                    <a href="profile.php"><i class="fas fa-user"></i> My Profile</a>
                    <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
                    <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
        </div>
    </header>

    <div class="dashboard-container">
        <nav class="sidebar">
            <a href="index.php"><i class="fas fa-home"></i> Dashboard</a>
            <a href="orders.php"><i class="fas fa-shopping-bag"></i> My Orders</a>
            <a href="cart.php"><i class="fas fa-shopping-cart"></i> My Cart</a>
        </nav>

        <main class="main-content">
            <div class="order-confirmation-container">
                <div class="confirmation-header">
                    <div class="confirmation-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h1 class="confirmation-title">🎉 Order Confirmed!</h1>
                    <p class="confirmation-subtitle">
                        Thank you for your purchase. Your order has been successfully placed.
                    </p>
                </div>

                <div class="order-details-card">
                    <div class="order-info-row">
                        <div>
                            <span class="order-info-label">Order Number:</span>
                            <strong style="font-size: 1.2em;">#<?php echo str_pad($order_id, 6, '0', STR_PAD_LEFT); ?></strong>
                        </div>
                        <div>
                            <span class="order-info-label">Order Date:</span>
                            <span><?php echo date('F d, Y h:i A', strtotime($order['order_date'])); ?></span>
                        </div>
                        <div>
                            <span class="order-info-label">Status:</span>
                            <span class="status-badge status-<?php echo strtolower($order['status']); ?>">
                                <?php echo ucfirst($order['status']); ?>
                            </span>
                        </div>
                    </div>

                    <div class="order-info-row">
                        <div>
                            <span class="order-info-label">Payment Method:</span>
                            <span><?php echo ucfirst($order['payment_method']); ?></span>
                        </div>
                        <div>
                            <span class="order-info-label">Shipping Address:</span>
                            <span><?php echo htmlspecialchars($order['shipping_address']); ?></span>
                        </div>
                    </div>
                </div>

                <div class="order-items-section">
                    <h2 class="section-title"><i class="fas fa-box-open"></i> Order Items</h2>
                    <?php foreach ($order_items as $item):
                        // Build the correct image path like in checkout.php
                        $image_src = !empty($item['image'])
                            ? '/sneakyplay2/assets/image/' . htmlspecialchars($item['image'])
                            : 'https://picsum.photos/seed/product' . $item['product_id'] . '/120/120.jpg';
                    ?>
                        <div class="order-item-card">
                            <!-- Display the image from your database -->
                            <img src="<?php echo $image_src; ?>"
                                alt="<?php echo htmlspecialchars($item['product_name']); ?>"
                                class="item-image"
                                onerror="this.onerror=null; this.src='https://picsum.photos/seed/product<?php echo $item['product_id']; ?>/120/120.jpg';">

                            <div class="item-details">
                                <h4 class="item-name"><?php echo htmlspecialchars($item['product_name']); ?></h4>
                                <?php if (!empty($item['description'])): ?>
                                    <p style="color: #666; font-size: 0.9em; margin-bottom: 10px;">
                                        <?php echo htmlspecialchars(substr($item['description'], 0, 100)); ?>...
                                    </p>
                                <?php endif; ?>
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <span class="item-price">₱<?php echo number_format($item['price'], 2); ?> each</span>
                                    <span class="item-quantity">Qty: <?php echo $item['quantity']; ?></span>
                                </div>
                            </div>
                            <div style="font-weight: bold; font-size: 1.1em;">
                                ₱<?php echo number_format($item['quantity'] * $item['price'], 2); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="order-summary-card">
                    <h2 class="section-title"><i class="fas fa-receipt"></i> Order Summary</h2>
                    <div class="summary-row">
                        <span>Subtotal (<?php echo $order['item_count']; ?> items)</span>
                        <span>₱<?php echo number_format($order['subtotal'] ?? $order['total_amount'], 2); ?></span>
                    </div>
                    <div class="summary-row">
                        <span>Shipping Fee</span>
                        <span>
                            <?php echo ($order['shipping_fee'] ?? 0) == 0 ? 'FREE' : '₱' . number_format($order['shipping_fee'], 2); ?>
                        </span>
                    </div>
                    <div class="summary-row">
                        <span>Tax (12%)</span>
                        <span>₱<?php echo number_format($order['tax'] ?? 0, 2); ?></span>
                    </div>
                    <div class="summary-row total">
                        <span>TOTAL AMOUNT</span>
                        <span>₱<?php echo number_format($order['total_amount'], 2); ?></span>
                    </div>
                </div>

                <?php if ($order['status'] === 'pending'): ?>
                    <form method="POST" class="cancel-form" onsubmit="return confirm('Are you sure you want to cancel this order? This action cannot be undone.')">
                        <div class="cancel-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            You can cancel this order as it's still pending. Once cancelled, items will be returned to inventory.
                        </div>
                        <button type="submit" name="cancel_order" class="btn btn-danger">
                            <i class="fas fa-times"></i> Cancel Order
                        </button>
                    </form>
                <?php endif; ?>

                <div class="confirmation-actions">
                    <a href="orders.php" class="btn btn-primary">
                        <i class="fas fa-shopping-bag"></i> View All Orders
                    </a>
                    <button onclick="window.print()" class="btn btn-secondary">
                        <i class="fas fa-print"></i> Print Invoice
                    </button>
                    <a href="shop.php" class="btn btn-success">
                        <i class="fas fa-store"></i> Continue Shopping
                    </a>
                </div>
            </div>
        </main>
    </div>

    <footer class="dashboard-footer">
        <p>&copy; <?php echo date('Y'); ?> SneakyPlay. All rights reserved.</p>
    </footer>

    <script src="/sneakyplay2/assets/js/user_dashboard.js"></script>
    <script src="/sneakyplay2/assets/js/order-confirmation.js"></script>
</body>

</html>