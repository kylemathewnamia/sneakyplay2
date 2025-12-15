<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Database connection
$host = 'localhost';
$dbname = 'sneakysheets';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Get user data
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle order cancellation
if (isset($_GET['cancel_order']) && isset($_GET['order_id'])) {
    $cancel_order_id = $_GET['order_id'];
    $user_id = $_SESSION['user_id'];

    try {
        // Start transaction
        $pdo->beginTransaction();

        // Update order status
        $cancel_stmt = $pdo->prepare("UPDATE orders SET status = 'cancelled' WHERE order_id = ? AND user_id = ? AND status = 'pending'");
        $cancel_stmt->execute([$cancel_order_id, $user_id]);

        if ($cancel_stmt->rowCount() > 0) {
            // Get order items to restore stock
            $items_stmt = $pdo->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = ?");
            $items_stmt->execute([$cancel_order_id]);
            $cancelled_items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);

            // Restore stock
            foreach ($cancelled_items as $item) {
                $update_stmt = $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity + ? WHERE product_id = ?");
                $update_stmt->execute([$item['quantity'], $item['product_id']]);
            }

            $pdo->commit();
            $_SESSION['order_message'] = 'Order cancelled successfully';
        } else {
            throw new Exception("Unable to cancel order. It may already be processed.");
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['order_message'] = 'Error: ' . $e->getMessage();
    }

    header('Location: orders.php');
    exit();
}

// Display order message
if (isset($_SESSION['order_message'])) {
    $order_message = $_SESSION['order_message'];
    unset($_SESSION['order_message']);
}

// Get all orders for the user
$orders_stmt = $pdo->prepare("SELECT o.*, 
                             (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.order_id) as item_count,
                             (SELECT GROUP_CONCAT(p.product_name SEPARATOR ', ') 
                              FROM order_items oi 
                              JOIN products p ON oi.product_id = p.product_id 
                              WHERE oi.order_id = o.order_id) as product_names
                            FROM orders o 
                            WHERE o.user_id = ? 
                            ORDER BY o.order_date DESC");
$orders_stmt->execute([$user_id]);
$all_orders = $orders_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats_stmt = $pdo->prepare("SELECT 
                            COUNT(*) as total_orders,
                            SUM(total_amount) as total_spent,
                            COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_orders,
                            COUNT(CASE WHEN status IN ('pending', 'processing') THEN 1 END) as active_orders
                            FROM orders 
                            WHERE user_id = ?");
$stats_stmt->execute([$user_id]);
$order_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Calculate total pages (show pagination only if more than 5 orders)
$total_orders = count($all_orders);
$orders_per_page = 5;
$total_pages = ceil($total_orders / $orders_per_page);
$current_page = isset($_GET['page']) ? max(1, min($total_pages, intval($_GET['page']))) : 1;
$start_index = ($current_page - 1) * $orders_per_page;
$paginated_orders = array_slice($all_orders, $start_index, $orders_per_page);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - SneakyPlay</title>
    <link rel="stylesheet" href="/sneakyplay2/assets/css/user_dashboard.css">
    <link rel="stylesheet" href="/sneakyplay2/assets/css/orders.css">
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
                <input type="text" placeholder="Search orders...">
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
            <a href="orders.php" class="active"><i class="fas fa-shopping-bag"></i> My Orders</a>
            <a href="cart.php"><i class="fas fa-shopping-cart"></i> My Cart</a>
        </nav>

        <main class="orders-main-content">
            <!-- Notification -->
            <?php if (isset($order_message)): ?>
                <div class="notification <?php echo strpos($order_message, 'Error') !== false ? 'notification-error' : 'notification-success'; ?>">
                    <div class="notification-content">
                        <i class="fas fa-<?php echo strpos($order_message, 'Error') !== false ? 'exclamation-circle' : 'check-circle'; ?>"></i>
                        <span><?php echo htmlspecialchars($order_message); ?></span>
                    </div>
                    <button class="notification-close">&times;</button>
                </div>
            <?php endif; ?>

            <!-- Page Header -->
            <section class="page-header">
                <h1><i class="fas fa-history"></i> Order History</h1>
                <p>View and manage all your past and current orders</p>
                <div class="header-stats">
                    <div class="stat-box">
                        <div class="stat-icon">
                            <i class="fas fa-shopping-bag"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $order_stats['total_orders'] ?? 0; ?></h3>
                            <p>Total Orders</p>
                        </div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-icon">
                            <i class="fas fa-money-bill-wave"></i>
                        </div>
                        <div class="stat-info">
                            <h3>₱<?php echo number_format($order_stats['total_spent'] ?? 0, 2); ?></h3>
                            <p>Total Spent</p>
                        </div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $order_stats['completed_orders'] ?? 0; ?></h3>
                            <p>Completed</p>
                        </div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $order_stats['active_orders'] ?? 0; ?></h3>
                            <p>Active Orders</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Order Filter Controls -->
            <section class="order-controls">
                <div class="filter-tabs">
                    <button class="filter-tab active" data-filter="all">All Orders</button>
                    <button class="filter-tab" data-filter="pending">Pending</button>
                    <button class="filter-tab" data-filter="processing">Processing</button>
                    <button class="filter-tab" data-filter="completed">Completed</button>
                    <button class="filter-tab" data-filter="cancelled">Cancelled</button>
                </div>
                <div class="search-filter">
                    <div class="date-filter">
                        <input type="date" id="dateFrom" placeholder="From Date">
                        <input type="date" id="dateTo" placeholder="To Date">
                        <button class="btn btn-secondary" id="applyDateFilter">
                            <i class="fas fa-filter"></i> Filter
                        </button>
                    </div>
                </div>
            </section>

            <!-- Orders List -->
            <section class="orders-list-section">
                <div class="section-header">
                    <h2><i class="fas fa-list-ol"></i> All Orders</h2>
                    <div class="sort-options">
                        <select id="sortOrders">
                            <option value="newest">Newest First</option>
                            <option value="oldest">Oldest First</option>
                            <option value="price-high">Price: High to Low</option>
                            <option value="price-low">Price: Low to High</option>
                        </select>
                    </div>
                </div>

                <div class="orders-container" id="ordersContainer">
                    <?php if (!empty($paginated_orders)): ?>
                        <?php foreach ($paginated_orders as $order): ?>
                            <div class="order-card" data-status="<?php echo strtolower($order['status']); ?>"
                                data-date="<?php echo date('Y-m-d', strtotime($order['order_date'])); ?>"
                                data-amount="<?php echo $order['total_amount']; ?>">
                                <div class="order-header">
                                    <div class="order-id">
                                        <span class="order-label">Order ID:</span>
                                        <strong>#<?php echo str_pad($order['order_id'], 6, '0', STR_PAD_LEFT); ?></strong>
                                    </div>
                                    <div class="order-date">
                                        <i class="far fa-calendar"></i>
                                        <?php echo date('F d, Y', strtotime($order['order_date'])); ?>
                                    </div>
                                    <div class="order-status">
                                        <span class="status-badge status-<?php echo strtolower($order['status']); ?>">
                                            <?php echo ucfirst($order['status']); ?>
                                        </span>
                                    </div>
                                </div>

                                <div class="order-body">
                                    <div class="order-summary">
                                        <div class="summary-item">
                                            <span class="summary-label">Items:</span>
                                            <span class="summary-value">
                                                <?php echo $order['item_count'] ?? 0; ?> item<?php echo ($order['item_count'] ?? 1) > 1 ? 's' : ''; ?>
                                            </span>
                                        </div>
                                        <div class="summary-item">
                                            <span class="summary-label">Total Amount:</span>
                                            <span class="summary-value price">₱<?php echo number_format($order['total_amount'], 2); ?></span>
                                        </div>
                                        <div class="summary-item">
                                            <span class="summary-label">Payment Method:</span>
                                            <span class="summary-value"><?php echo ucfirst($order['payment_method'] ?? 'Cash on Delivery'); ?></span>
                                        </div>
                                    </div>

                                    <div class="order-products">
                                        <div class="products-label">
                                            <i class="fas fa-box-open"></i> Products:
                                        </div>
                                        <div class="products-list">
                                            <?php
                                            $products = explode(', ', $order['product_names'] ?? '');
                                            $display_products = array_slice($products, 0, 3);
                                            foreach ($display_products as $product): ?>
                                                <span class="product-tag"><?php echo htmlspecialchars($product); ?></span>
                                            <?php endforeach; ?>
                                            <?php if (count($products) > 3): ?>
                                                <span class="product-tag more-items">+<?php echo count($products) - 3; ?> more</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="order-footer">
                                    <div class="order-actions">
                                        <a href="order-confirmation.php?id=<?php echo $order['order_id']; ?>" class="btn btn-primary">
                                            <i class="fas fa-eye"></i> View Details
                                        </a>
                                        <?php if ($order['status'] === 'pending'): ?>
                                            <button class="btn btn-danger cancel-order"
                                                onclick="return confirmCancel(<?php echo $order['order_id']; ?>)">
                                                <i class="fas fa-times"></i> Cancel Order
                                            </button>
                                        <?php endif; ?>
                                        <?php if ($order['status'] === 'completed'): ?>
                                            <button class="btn btn-success reorder"
                                                data-order-id="<?php echo $order['order_id']; ?>">
                                                <i class="fas fa-redo"></i> Reorder
                                            </button>
                                        <?php endif; ?>
                                        <button class="btn btn-outline track-order"
                                            data-order-id="<?php echo $order['order_id']; ?>">
                                            <i class="fas fa-shipping-fast"></i> Track Order
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-orders">
                            <div class="empty-icon">
                                <i class="fas fa-shopping-bag"></i>
                            </div>
                            <h3>No Orders Yet</h3>
                            <p>You haven't placed any orders yet. Start shopping to see your orders here!</p>
                            <a href="shop.php" class="btn btn-primary">
                                <i class="fas fa-shopping-cart"></i> Start Shopping
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Pagination - Only show if more than 1 page -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($current_page > 1): ?>
                            <a href="?page=<?php echo $current_page - 1; ?>" class="pagination-btn prev">
                                <i class="fas fa-chevron-left"></i> Previous
                            </a>
                        <?php else: ?>
                            <span class="pagination-btn prev disabled">
                                <i class="fas fa-chevron-left"></i> Previous
                            </span>
                        <?php endif; ?>

                        <div class="page-numbers">
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <?php if ($i == $current_page): ?>
                                    <span class="page-number active"><?php echo $i; ?></span>
                                <?php else: ?>
                                    <a href="?page=<?php echo $i; ?>" class="page-number"><?php echo $i; ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>
                        </div>

                        <?php if ($current_page < $total_pages): ?>
                            <a href="?page=<?php echo $current_page + 1; ?>" class="pagination-btn next">
                                Next <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php else: ?>
                            <span class="pagination-btn next disabled">
                                Next <i class="fas fa-chevron-right"></i>
                            </span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </section>
        </main>
    </div>

    <footer class="dashboard-footer">
        <p>&copy; <?php echo date('Y'); ?> SneakyPlay. All rights reserved.</p>
    </footer>

    <script src="/sneakyplay2/assets/js/orders.js"></script>
</body>

</html>