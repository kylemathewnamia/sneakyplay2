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
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Get user data
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Ensure user has a cart
$stmt = $pdo->prepare("SELECT cart_id FROM cart WHERE user_id = ?");
$stmt->execute([$user_id]);
$cart = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cart) {
    $stmt = $pdo->prepare("INSERT INTO cart (user_id) VALUES (?)");
    $stmt->execute([$user_id]);
    $cart_id = $pdo->lastInsertId();
} else {
    $cart_id = $cart['cart_id'];
}

// Handle cart actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_quantity':
                $cart_item_id = $_POST['cart_item_id'];
                $new_quantity = (int)$_POST['quantity'];

                if ($new_quantity > 0) {
                    $stmt = $pdo->prepare("UPDATE cart_items SET quantity = ? WHERE cart_item_id = ? AND cart_id = ?");
                    $stmt->execute([$new_quantity, $cart_item_id, $cart_id]);
                    $_SESSION['cart_message'] = 'Quantity updated successfully';
                } else {
                    $stmt = $pdo->prepare("DELETE FROM cart_items WHERE cart_item_id = ? AND cart_id = ?");
                    $stmt->execute([$cart_item_id, $cart_id]);
                    $_SESSION['cart_message'] = 'Item removed from cart';
                }
                break;

            case 'remove_item':
                $cart_item_id = $_POST['cart_item_id'];
                $stmt = $pdo->prepare("DELETE FROM cart_items WHERE cart_item_id = ? AND cart_id = ?");
                $stmt->execute([$cart_item_id, $cart_id]);
                $_SESSION['cart_message'] = 'Item removed from cart';
                break;

            case 'clear_cart':
                $stmt = $pdo->prepare("DELETE FROM cart_items WHERE cart_id = ?");
                $stmt->execute([$cart_id]);
                $_SESSION['cart_message'] = 'Cart cleared successfully';
                break;
        }
        header('Location: cart.php');
        exit();
    }
}

// Get cart items with product details
$cart_query = "
    SELECT 
        ci.cart_item_id,
        ci.quantity,
        p.product_id,
        p.product_name,
        p.price,
        p.stock_quantity,
        p.image,
        c.category_name,
        (ci.quantity * p.price) as item_total
    FROM cart_items ci
    JOIN products p ON ci.product_id = p.product_id
    LEFT JOIN categories c ON p.categories_id = c.categories_id
    WHERE ci.cart_id = ?
    ORDER BY ci.cart_item_id DESC
";

$stmt = $pdo->prepare($cart_query);
$stmt->execute([$cart_id]);
$cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate subtotal
$subtotal = 0;
$total_items = 0;
foreach ($cart_items as $item) {
    $subtotal += $item['item_total'];
    $total_items += $item['quantity'];
}

$shipping_fee = $subtotal > 1000 ? 0 : 50;
$tax = $subtotal * 0.12;
$total = $subtotal + $shipping_fee + $tax;

// Save cart summary in session for checkout
$_SESSION['cart_summary'] = [
    'subtotal' => $subtotal,
    'shipping_fee' => $shipping_fee,
    'tax' => $tax,
    'total' => $total,
    'total_items' => $total_items,
    'cart_items' => $cart_items
];

$cart_message = $_SESSION['cart_message'] ?? '';
unset($_SESSION['cart_message']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Cart - SneakyPlay</title>
    <link rel="stylesheet" href="/sneakyplay2/assets/css/user_dashboard.css">
    <link rel="stylesheet" href="/sneakyplay2/assets/css/cart.css">
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
            <a href="cart.php" class="active"><i class="fas fa-shopping-cart"></i> My Cart</a>
        </nav>

        <main class="main-content">
            <!-- Cart Message Notification -->
            <?php if ($cart_message): ?>
                <div class="cart-message-notification">
                    <div class="notification-content">
                        <i class="fas fa-check-circle"></i>
                        <span><?php echo htmlspecialchars($cart_message); ?></span>
                    </div>
                    <button class="notification-close">&times;</button>
                </div>
            <?php endif; ?>

            <section class="cart-header-section">
                <div class="cart-header-content">
                    <h1><i class="fas fa-shopping-cart"></i> Shopping Cart</h1>
                    <p class="cart-summary">You have <span class="item-count"><?php echo $total_items; ?></span> item(s) in your cart</p>
                </div>

                <?php if (!empty($cart_items)): ?>
                    <div class="cart-header-actions">
                        <form method="POST" class="clear-cart-form" onsubmit="return confirm('Clear all items from your cart?')">
                            <input type="hidden" name="action" value="clear_cart">
                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-trash"></i> Clear Cart
                            </button>
                        </form>
                        <a href="checkout.php" class="btn btn-primary">
                            <i class="fas fa-lock"></i> Proceed to Checkout
                        </a>
                    </div>
                <?php endif; ?>
            </section>

            <div class="cart-container">
                <div class="cart-items-container">
                    <?php if (!empty($cart_items)): ?>
                        <?php foreach ($cart_items as $item):
                            // Get image URL - CORRECT PATH
                            $image_src = !empty($item['image']) ?
                                '/sneakyplay2/assets/image/' . htmlspecialchars($item['image']) :
                                'https://picsum.photos/seed/product' . $item['product_id'] . '/300/200.jpg';
                        ?>
                            <div class="cart-item-card" data-item-id="<?php echo $item['cart_item_id']; ?>">
                                <div class="item-image-container">
                                    <img src="<?php echo $image_src; ?>"
                                        alt="<?php echo htmlspecialchars($item['product_name']); ?>"
                                        class="item-image"
                                        onerror="this.onerror=null; this.src='https://picsum.photos/seed/product<?php echo $item['product_id']; ?>/300/200.jpg';">
                                </div>

                                <div class="item-details-container">
                                    <div class="item-info">
                                        <h3 class="item-title"><?php echo htmlspecialchars($item['product_name']); ?></h3>
                                        <p class="item-category"><?php echo htmlspecialchars($item['category_name'] ?? 'Gaming'); ?></p>
                                        <p class="item-price">₱<?php echo number_format($item['price'], 2); ?> each</p>
                                        <p class="stock-info <?php echo $item['stock_quantity'] > 0 ? 'in-stock' : 'out-of-stock'; ?>">
                                            <i class="fas fa-<?php echo $item['stock_quantity'] > 0 ? 'check-circle' : 'times-circle'; ?>"></i>
                                            <?php
                                            echo $item['stock_quantity'] > 0
                                                ? ($item['stock_quantity'] >= $item['quantity']
                                                    ? 'In Stock'
                                                    : 'Only ' . $item['stock_quantity'] . ' left')
                                                : 'Out of Stock';
                                            ?>
                                        </p>
                                    </div>

                                    <div class="item-controls">
                                        <div class="quantity-controls-wrapper">
                                            <form method="POST" class="quantity-form">
                                                <input type="hidden" name="action" value="update_quantity">
                                                <input type="hidden" name="cart_item_id" value="<?php echo $item['cart_item_id']; ?>">

                                                <div class="quantity-controls">
                                                    <button type="button" class="quantity-btn minus" onclick="updateQuantity(this, -1)">-</button>
                                                    <input type="number" name="quantity" value="<?php echo $item['quantity']; ?>"
                                                        min="1" max="<?php echo $item['stock_quantity']; ?>"
                                                        class="quantity-input">
                                                    <button type="button" class="quantity-btn plus" onclick="updateQuantity(this, 1)">+</button>
                                                </div>
                                                <button type="submit" class="btn-update hidden">Update</button>
                                            </form>
                                        </div>

                                        <div class="item-total">
                                            <span class="total-label">Total:</span>
                                            <span class="total-amount">₱<?php echo number_format($item['item_total'], 2); ?></span>
                                        </div>

                                        <div class="item-actions">
                                            <form method="POST" class="remove-form" onsubmit="return confirm('Remove this item from cart?')">
                                                <input type="hidden" name="action" value="remove_item">
                                                <input type="hidden" name="cart_item_id" value="<?php echo $item['cart_item_id']; ?>">
                                                <button type="submit" class="btn-icon" title="Remove item">
                                                    <i class="fas fa-trash"></i> Remove
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-cart-state">
                            <div class="empty-cart-icon">
                                <i class="fas fa-shopping-cart"></i>
                            </div>
                            <h2>Your cart is empty</h2>
                            <p>Looks like you haven't added any items to your cart yet.</p>
                            <div class="empty-cart-actions">
                                <a href="/sneakyplay2/user/shop.php" class="btn btn-primary btn-lg">
                                    <i class="fas fa-gamepad"></i> Browse Products
                                </a>
                                <a href="index.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (!empty($cart_items)): ?>
                    <div class="cart-summary-mobile">
                        <div class="mobile-summary-card">
                            <div class="summary-row">
                                <span>Subtotal (<?php echo $total_items; ?> items)</span>
                                <span>₱<?php echo number_format($subtotal, 2); ?></span>
                            </div>
                            <div class="summary-row">
                                <span>Shipping</span>
                                <span class="<?php echo $shipping_fee === 0 ? 'free-shipping' : ''; ?>">
                                    <?php echo $shipping_fee === 0 ? 'FREE' : '₱' . number_format($shipping_fee, 2); ?>
                                </span>
                            </div>
                            <div class="summary-row total-row">
                                <span><strong>Total</strong></span>
                                <span class="total-amount"><strong>₱<?php echo number_format($total, 2); ?></strong></span>
                            </div>
                            <a href="checkout.php" class="btn btn-primary btn-checkout-mobile">
                                <i class="fas fa-lock"></i> Proceed to Checkout
                            </a>
                        </div>
                    </div>

                    <div class="cart-actions-bottom">
                        <div class="cart-continue-shopping">
                            <a href="/sneakyplay2/user/shop.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Continue Shopping
                            </a>
                        </div>
                        <div class="cart-checkout-section">
                            <div class="order-summary-preview">
                                <div class="summary-preview-row">
                                    <span>Order Total:</span>
                                    <span class="preview-total">₱<?php echo number_format($total, 2); ?></span>
                                </div>
                            </div>
                            <a href="checkout.php" class="btn btn-primary btn-lg">
                                <i class="fas fa-lock"></i> Secure Checkout
                            </a>
                            <div class="secure-checkout-note">
                                <i class="fas fa-shield-alt"></i>
                                <span>Your payment is secure and encrypted</span>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <footer class="dashboard-footer">
        <p>&copy; <?php echo date('Y'); ?> SneakyPlay. All rights reserved.</p>
    </footer>

    <script src="/sneakyplay2/assets/js/user_dashboard.js"></script>
    <script>
        // Cart quantity update function
        function updateQuantity(button, change) {
            const form = button.closest('.quantity-form');
            const input = form.querySelector('.quantity-input');
            let value = parseInt(input.value) + change;
            const max = parseInt(input.max);
            const min = parseInt(input.min);

            if (value > max) value = max;
            if (value < min) value = min;

            input.value = value;

            // Show update button
            const updateBtn = form.querySelector('.btn-update');
            updateBtn.classList.remove('hidden');

            // Auto-submit after 1 second of inactivity
            clearTimeout(window.quantityTimeout);
            window.quantityTimeout = setTimeout(() => {
                if (!updateBtn.classList.contains('hidden')) {
                    form.submit();
                }
            }, 1000);
        }

        // Auto-hide notification after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const notification = document.querySelector('.cart-message-notification');
            if (notification) {
                setTimeout(() => {
                    notification.style.opacity = '0';
                    notification.style.transform = 'translateY(-10px)';
                    setTimeout(() => notification.remove(), 300);
                }, 5000);
            }

            // Close notification on button click
            const closeBtn = document.querySelector('.notification-close');
            if (closeBtn) {
                closeBtn.addEventListener('click', function() {
                    const notification = this.closest('.cart-message-notification');
                    notification.style.opacity = '0';
                    notification.style.transform = 'translateY(-10px)';
                    setTimeout(() => notification.remove(), 300);
                });
            }

            // Auto-submit on quantity input change
            const quantityInputs = document.querySelectorAll('.quantity-input');
            quantityInputs.forEach(input => {
                input.addEventListener('change', function() {
                    const form = this.closest('.quantity-form');
                    const updateBtn = form.querySelector('.btn-update');
                    updateBtn.classList.remove('hidden');
                    setTimeout(() => form.submit(), 500);
                });
            });
        });
    </script>
</body>

</html>
[file content end]