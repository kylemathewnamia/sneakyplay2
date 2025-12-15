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

// Get active orders count
$orders_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM orders WHERE user_id = ? AND status IN ('paid', 'pending')");
$orders_stmt->execute([$user_id]);
$active_orders = $orders_stmt->fetch(PDO::FETCH_ASSOC);

// Get total amount from recent orders
$total_spent_stmt = $pdo->prepare("SELECT SUM(total_amount) as total FROM orders WHERE user_id = ?");
$total_spent_stmt->execute([$user_id]);
$total_spent = $total_spent_stmt->fetch(PDO::FETCH_ASSOC);

// Get cart items count
$cart_count_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM cart_items ci 
                                 JOIN cart c ON ci.cart_id = c.cart_id 
                                 WHERE c.user_id = ?");
$cart_count_stmt->execute([$user_id]);
$cart_count = $cart_count_stmt->fetch(PDO::FETCH_ASSOC);

// Get recent orders
$recent_orders_stmt = $pdo->prepare("SELECT o.*, 
                                    (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.order_id) as item_count,
                                    (SELECT GROUP_CONCAT(p.product_name SEPARATOR ', ') 
                                     FROM order_items oi 
                                     JOIN products p ON oi.product_id = p.product_id 
                                     WHERE oi.order_id = o.order_id LIMIT 1) as product_names
                                    FROM orders o 
                                    WHERE o.user_id = ? 
                                    ORDER BY o.order_date DESC 
                                    LIMIT 3");
$recent_orders_stmt->execute([$user_id]);
$recent_orders = $recent_orders_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get popular products
$popular_products_stmt = $pdo->prepare("SELECT p.*, c.category_name 
                                       FROM products p 
                                       LEFT JOIN categories c ON p.categories_id = c.categories_id 
                                       ORDER BY RAND() LIMIT 2");
$popular_products_stmt->execute();
$popular_products = $popular_products_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get special offers
$offers_stmt = $pdo->prepare("SELECT p.*, 
                             (SELECT AVG(rating) FROM reviews r WHERE r.product_id = p.product_id) as avg_rating,
                             (SELECT COUNT(*) FROM reviews r WHERE r.product_id = p.product_id) as review_count
                             FROM products p 
                             WHERE p.product_id IN (2, 3, 4, 5, 7) 
                             ORDER BY RAND() LIMIT 2");
$offers_stmt->execute();
$special_offers = $offers_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gamer Dashboard - SneakyPlay</title>
    <link rel="stylesheet" href="/sneakyplay2/assets/css/user_dashboard.css">
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
            <a href="index.php" class="active"><i class="fas fa-home"></i> Dashboard</a>
            <a href="orders.php"><i class="fas fa-shopping-bag"></i> My Orders</a>
            <a href="cart.php"><i class="fas fa-shopping-cart"></i> My Cart</a>
        </nav>

        <main class="main-content">
            <section class="welcome-section">
                <h1>Welcome back, <?php echo htmlspecialchars($user['name'] ?? 'Gamer'); ?>!</h1>
                <p>Your gaming journey continues here. Check out your latest orders and exclusive deals.</p>
                <p class="member-since"><i class="fas fa-calendar-alt"></i> Member since: <?php echo date('F Y', strtotime($user['date_registered'] ?? 'now')); ?></p>
            </section>

            <!-- NEW: Single row stats table with 4 columns -->
            <section class="stats-table-section">
                <h2><i class="fas fa-chart-line"></i> Quick Stats</h2>
                <div class="stats-table-container">
                    <table class="stats-table">
                        <tbody>
                            <tr>
                                <td>
                                    <div class="stat-cell">
                                        <i class="fas fa-shopping-cart"></i>
                                        <div class="stat-content">
                                            <h3><?php echo $active_orders['count'] ?? 0; ?></h3>
                                            <p>Active Orders</p>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="stat-cell">
                                        <i class="fas fa-money-bill-wave"></i>
                                        <div class="stat-content">
                                            <h3>₱<?php echo number_format($total_spent['total'] ?? 0, 2); ?></h3>
                                            <p>Total Spent</p>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="stat-cell">
                                        <i class="fas fa-box"></i>
                                        <div class="stat-content">
                                            <h3><?php echo $cart_count['count'] ?? 0; ?></h3>
                                            <p>Cart Items</p>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="stat-cell">
                                        <i class="fas fa-shipping-fast"></i>
                                        <div class="stat-content">
                                            <h3><?php echo count($recent_orders); ?></h3>
                                            <p>Total Orders</p>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="recent-orders">
                <h2><i class="fas fa-clock"></i> Recent Orders</h2>
                <div class="orders-table">
                    <?php if (!empty($recent_orders)): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Date</th>
                                    <th>Items</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_orders as $order): ?>
                                    <tr>
                                        <td>#<?php echo str_pad($order['order_id'], 6, '0', STR_PAD_LEFT); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($order['order_date'])); ?></td>
                                        <td title="<?php echo htmlspecialchars($order['product_names'] ?? 'Various items'); ?>">
                                            <?php
                                            $items = $order['product_names'] ?? 'Various items';
                                            echo strlen($items) > 30 ? substr($items, 0, 30) . '...' : $items;
                                            ?>
                                        </td>
                                        <td>₱<?php echo number_format($order['total_amount'], 2); ?></td>
                                        <td>
                                            <span class="status <?php echo strtolower($order['status']); ?>">
                                                <?php echo ucfirst($order['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-primary btn-sm">
                                                View Details
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-shopping-bag"></i>
                            <h3>No orders yet</h3>
                            <p>Start shopping to see your orders here</p>
                            <a href="/sneakyplay2/user/shop.php" class="btn btn-primary">Browse Products</a>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <section class="popular-products">
                <h2><i class="fas fa-fire"></i> Popular Products</h2>
                <div class="products-grid">
                    <?php if (!empty($popular_products)): ?>
                        <?php foreach ($popular_products as $product): ?>
                            <div class="product-card">
                                <div class="product-image">
                                    <img src="/sneakyplay2/assets/image/<?php echo htmlspecialchars($product['image']); ?>"
                                        alt="<?php echo htmlspecialchars($product['product_name']); ?>"
                                        onerror="this.onerror=null; this.src='https://picsum.photos/seed/product<?php echo $product['product_id']; ?>/200/150.jpg';">
                                </div>
                                <div class="product-info">
                                    <h3><?php echo htmlspecialchars($product['product_name']); ?></h3>
                                    <p class="product-category"><?php echo htmlspecialchars($product['category_name'] ?? 'Gaming'); ?></p>
                                    <p class="product-price">₱<?php echo number_format($product['price'], 2); ?></p>
                                    <div class="product-actions">
                                        <button class="btn btn-primary">
                                            View Details
                                        </button>
                                        <button class="btn btn-secondary add-to-cart"
                                            data-product-id="<?php echo $product['product_id']; ?>"
                                            data-product-name="<?php echo htmlspecialchars($product['product_name']); ?>">
                                            <i class="fas fa-cart-plus"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="no-products">No products available at the moment.</p>
                    <?php endif; ?>
                </div>
            </section>

            <section class="special-offers">
                <h2><i class="fas fa-gift"></i> Special Offers</h2>
                <div class="offers-container">
                    <?php if (!empty($special_offers)): ?>
                        <?php foreach ($special_offers as $offer): ?>
                            <div class="offer-card">
                                <?php if ($offer['avg_rating'] >= 4): ?>
                                    <div class="offer-badge">Highly Rated</div>
                                <?php elseif ($offer['review_count'] > 0): ?>
                                    <div class="offer-badge">Customer Choice</div>
                                <?php else: ?>
                                    <div class="offer-badge">Popular</div>
                                <?php endif; ?>

                                <img src="/sneakyplay2/assets/image/<?php echo htmlspecialchars($offer['image']); ?>"
                                    alt="<?php echo htmlspecialchars($offer['product_name']); ?>"
                                    onerror="this.onerror=null; this.src='https://picsum.photos/seed/offer<?php echo $offer['product_id']; ?>/200/150.jpg';">
                                <h3><?php echo htmlspecialchars($offer['product_name']); ?></h3>
                                <p>
                                    <?php if ($offer['avg_rating']): ?>
                                        <span class="rating">
                                            <i class="fas fa-star"></i> <?php echo number_format($offer['avg_rating'], 1); ?>
                                            (<?php echo $offer['review_count']; ?> reviews)
                                        </span>
                                    <?php else: ?>
                                        New arrival
                                    <?php endif; ?>
                                </p>
                                <div class="price">
                                    <span class="sale-price">₱<?php echo number_format($offer['price'], 2); ?></span>
                                </div>
                                <button class="btn btn-primary">
                                    Shop Now
                                </button>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="no-offers">Check back soon for special offers!</p>
                    <?php endif; ?>
                </div>
            </section>
        </main>
    </div>

    <footer class="dashboard-footer">
        <p>&copy; <?php echo date('Y'); ?> SneakyPlay. All rights reserved.</p>
    </footer>

    <script src="/sneakyplay2/assets/js/user_dashboard.js"></script>
</body>

</html>