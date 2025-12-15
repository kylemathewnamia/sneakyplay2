<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Check if cart has items
if (!isset($_SESSION['cart_summary']) || empty($_SESSION['cart_summary']['cart_items'])) {
    header('Location: cart.php');
    exit();
}

$cart_summary = $_SESSION['cart_summary'];

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

// Get product images for cart items
$product_ids = array_map(function ($item) {
    return $item['product_id'];
}, $cart_summary['cart_items']);

$placeholders = str_repeat('?,', count($product_ids) - 1) . '?';
$image_query = "SELECT product_id, image FROM products WHERE product_id IN ($placeholders)";
$image_stmt = $pdo->prepare($image_query);
$image_stmt->execute($product_ids);
$product_images = $image_stmt->fetchAll(PDO::FETCH_ASSOC);

// Create image lookup array
$image_lookup = [];
foreach ($product_images as $img) {
    $image_lookup[$img['product_id']] = $img['image'];
}

// Add image paths to cart items
foreach ($cart_summary['cart_items'] as &$item) {
    $image_filename = $image_lookup[$item['product_id']] ?? null;
    $item['image_src'] = !empty($image_filename)
        ? '/sneakyplay2/assets/image/' . htmlspecialchars($image_filename)
        : 'https://picsum.photos/seed/product' . $item['product_id'] . '/100/100.jpg';
}

// Handle checkout form submission
$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate required fields
        $required_fields = ['full_name', 'email', 'phone', 'shipping_address', 'payment_method'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Please fill in all required fields.");
            }
        }

        // Validate email
        if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Please enter a valid email address.");
        }

        // Generate unique order number
        $order_number = 'ORD' . date('Ymd') . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);

        // Start transaction
        $pdo->beginTransaction();

        // ==================== FIXED INSERT QUERY ====================
        // Create order with ALL required columns
        $order_query = "INSERT INTO orders (
            order_number,
            user_id,
            order_date, 
            total_amount,
            subtotal,
            shipping_fee,
            tax,
            status, 
            shipping_address, 
            payment_method,
            payment_status,
            shipping_status,
            customer_name,
            customer_email,
            customer_phone,
            notes
        ) VALUES (?, ?, NOW(), ?, ?, ?, ?, 'pending', ?, ?, 'pending', 'pending', ?, ?, ?, ?)";

        $order_stmt = $pdo->prepare($order_query);
        $order_stmt->execute([
            $order_number,      // 1. order_number
            $user_id,           // 2. user_id
            $cart_summary['total'],         // 3. total_amount
            $cart_summary['subtotal'] ?? $cart_summary['total'], // 4. subtotal
            $cart_summary['shipping_fee'] ?? 0,  // 5. shipping_fee
            $cart_summary['tax'] ?? 0,           // 6. tax
            $_POST['shipping_address'],      // 7. shipping_address
            $_POST['payment_method'],        // 8. payment_method
            $_POST['full_name'],             // 9. customer_name
            $_POST['email'],                 // 10. customer_email
            $_POST['phone'],                 // 11. customer_phone
            $_POST['order_notes'] ?? ''      // 12. notes
        ]);
        // ==================== END FIXED INSERT ====================

        $order_id = $pdo->lastInsertId();

        // Create order items
        $order_items_query = "INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)";
        $order_items_stmt = $pdo->prepare($order_items_query);

        foreach ($cart_summary['cart_items'] as $item) {
            $order_items_stmt->execute([
                $order_id,
                $item['product_id'],
                $item['quantity'],
                $item['price']
            ]);

            // Update product stock
            $update_stock_query = "UPDATE products SET stock_quantity = stock_quantity - ? WHERE product_id = ?";
            $update_stmt = $pdo->prepare($update_stock_query);
            $update_stmt->execute([$item['quantity'], $item['product_id']]);
        }

        // Clear cart
        $cart_id = $pdo->query("SELECT cart_id FROM cart WHERE user_id = $user_id")->fetchColumn();
        $clear_cart_query = "DELETE FROM cart_items WHERE cart_id = ?";
        $clear_stmt = $pdo->prepare($clear_cart_query);
        $clear_stmt->execute([$cart_id]);

        // Commit transaction
        $pdo->commit();

        // Clear cart summary from session
        unset($_SESSION['cart_summary']);

        // Store order ID in session for confirmation page
        $_SESSION['last_order_id'] = $order_id;

        // Redirect to confirmation page
        header('Location: order-confirmation.php?id=' . $order_id);
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_message = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - SneakyPlay</title>
    <link rel="stylesheet" href="/sneakyplay2/assets/css/user_dashboard.css">
    <link rel="stylesheet" href="/sneakyplay2/assets/css/checkout.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" type="image/png" href="../assets/image/logo.png">
    <style>
        /* Additional styles for checkout images */
        .item-image {
            width: 60px;
            height: 60px;
            border-radius: 8px;
            overflow: hidden;
            flex-shrink: 0;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .item-image img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            object-position: center;
        }

        .order-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 12px 0;
            border-bottom: 1px solid #e5e7eb;
        }

        .order-item:last-child {
            border-bottom: none;
        }

        .item-details {
            flex: 1;
        }

        .item-details h5 {
            margin: 0 0 5px 0;
            font-size: 14px;
            font-weight: 600;
            color: #111827;
        }

        .item-meta {
            margin: 0 0 3px 0;
            font-size: 12px;
            color: #6b7280;
        }

        .item-price {
            margin: 0;
            font-size: 12px;
            color: #ef4444;
            font-weight: 600;
        }

        .item-total {
            font-weight: 700;
            color: #111827;
            font-size: 14px;
        }
    </style>
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
            <!-- Error/Success Messages -->
            <?php if ($error_message): ?>
                <div class="checkout-message error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo htmlspecialchars($error_message); ?></span>
                    <button class="message-close">&times;</button>
                </div>
            <?php endif; ?>

            <section class="checkout-header-section">
                <h1><i class="fas fa-lock"></i> Secure Checkout</h1>
                <div class="checkout-steps">
                    <div class="step completed">
                        <div class="step-number"><i class="fas fa-check"></i></div>
                        <span>Cart</span>
                    </div>
                    <div class="step-divider"></div>
                    <div class="step active">
                        <div class="step-number">2</div>
                        <span>Checkout</span>
                    </div>
                    <div class="step-divider"></div>
                    <div class="step">
                        <div class="step-number">3</div>
                        <span>Confirmation</span>
                    </div>
                </div>
            </section>

            <div class="checkout-layout">
                <!-- LEFT COLUMN: User Information -->
                <div class="checkout-left">
                    <div class="checkout-card">
                        <h2><i class="fas fa-user"></i> Contact Information</h2>
                        <form method="POST" id="checkoutForm">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="full_name">Full Name *</label>
                                    <input type="text" id="full_name" name="full_name"
                                        value="<?php echo htmlspecialchars($user['name']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="email">Email Address *</label>
                                    <input type="email" id="email" name="email"
                                        value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="phone">Phone Number *</label>
                                    <input type="tel" id="phone" name="phone"
                                        value="<?php echo htmlspecialchars($user['contact_no'] ?? ''); ?>" required>
                                </div>
                            </div>

                            <div class="form-section">
                                <h3><i class="fas fa-map-marker-alt"></i> Shipping Address</h3>
                                <div class="form-group">
                                    <textarea id="shipping_address" name="shipping_address" rows="3" required><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                                </div>
                            </div>

                            <div class="form-section">
                                <h3><i class="fas fa-credit-card"></i> Payment Method</h3>
                                <div class="payment-options">
                                    <div class="payment-option">
                                        <input type="radio" id="cod" name="payment_method" value="cod" checked>
                                        <label for="cod">
                                            <div class="payment-icon">
                                                <i class="fas fa-money-bill-wave"></i>
                                            </div>
                                            <div class="payment-info">
                                                <strong>Cash on Delivery</strong>
                                                <span>Pay when you receive your order</span>
                                            </div>
                                        </label>
                                    </div>
                                    <div class="payment-option">
                                        <input type="radio" id="gcash" name="payment_method" value="gcash">
                                        <label for="gcash">
                                            <div class="payment-icon">
                                                <i class="fas fa-mobile-alt"></i>
                                            </div>
                                            <div class="payment-info">
                                                <strong>GCash</strong>
                                                <span>Pay using GCash wallet</span>
                                            </div>
                                        </label>
                                    </div>
                                    <div class="payment-option">
                                        <input type="radio" id="card" name="payment_method" value="card">
                                        <label for="card">
                                            <div class="payment-icon">
                                                <i class="fas fa-credit-card"></i>
                                            </div>
                                            <div class="payment-info">
                                                <strong>Credit/Debit Card</strong>
                                                <span>Secure card payment</span>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="form-section">
                                <h3><i class="fas fa-sticky-note"></i> Order Notes (Optional)</h3>
                                <div class="form-group">
                                    <textarea id="order_notes" name="order_notes" rows="2"
                                        placeholder="Special instructions for delivery..."></textarea>
                                </div>
                            </div>

                            <!-- ============ MOVED BUTTON INSIDE FORM ============ -->
                            <div class="checkout-actions" style="margin-top: 30px;">
                                <a href="cart.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Back to Cart
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-lock"></i> Place Order
                                </button>
                            </div>
                            <!-- ============ END MOVED BUTTON ============ -->
                        </form>
                    </div>
                </div>

                <!-- RIGHT COLUMN: Order Summary -->
                <div class="checkout-right">
                    <div class="order-summary-card">
                        <h2><i class="fas fa-shopping-bag"></i> Order Summary</h2>

                        <div class="order-items">
                            <h4><?php echo $cart_summary['total_items']; ?> Item(s) in Cart</h4>
                            <div class="items-list">
                                <?php foreach ($cart_summary['cart_items'] as $item): ?>
                                    <div class="order-item">
                                        <div class="item-image">
                                            <img src="<?php echo $item['image_src']; ?>"
                                                alt="<?php echo htmlspecialchars($item['product_name']); ?>"
                                                onerror="this.onerror=null; this.src='https://picsum.photos/seed/product<?php echo $item['product_id']; ?>/100/100.jpg';">
                                        </div>
                                        <div class="item-details">
                                            <h5><?php echo htmlspecialchars($item['product_name']); ?></h5>
                                            <p class="item-meta">Qty: <?php echo $item['quantity']; ?></p>
                                            <p class="item-price">₱<?php echo number_format($item['price'], 2); ?> each</p>
                                        </div>
                                        <div class="item-total">
                                            ₱<?php echo number_format($item['item_total'], 2); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="order-totals">
                            <div class="total-row">
                                <span>Subtotal</span>
                                <span>₱<?php echo number_format($cart_summary['subtotal'], 2); ?></span>
                            </div>
                            <div class="total-row">
                                <span>Shipping</span>
                                <span class="<?php echo $cart_summary['shipping_fee'] === 0 ? 'free' : ''; ?>">
                                    <?php echo $cart_summary['shipping_fee'] === 0 ? 'FREE' : '₱' . number_format($cart_summary['shipping_fee'], 2); ?>
                                </span>
                            </div>
                            <div class="total-row">
                                <span>Tax (12%)</span>
                                <span>₱<?php echo number_format($cart_summary['tax'], 2); ?></span>
                            </div>
                            <div class="total-row grand-total">
                                <span><strong>Total Amount</strong></span>
                                <span><strong>₱<?php echo number_format($cart_summary['total'], 2); ?></strong></span>
                            </div>
                        </div>

                        <div class="checkout-info">
                            <div class="info-card">
                                <i class="fas fa-shipping-fast"></i>
                                <div>
                                    <h5>Free Shipping</h5>
                                    <p>Free delivery on orders over ₱1,000</p>
                                </div>
                            </div>
                            <div class="info-card">
                                <i class="fas fa-shield-alt"></i>
                                <div>
                                    <h5>Secure Payment</h5>
                                    <p>Your payment information is protected</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <footer class="dashboard-footer">
        <p>&copy; <?php echo date('Y'); ?> SneakyPlay. All rights reserved.</p>
    </footer>

    <script src="/sneakyplay2/assets/js/user_dashboard.js"></script>
    <script>
        // Close message notification
        document.addEventListener('DOMContentLoaded', function() {
            const closeBtn = document.querySelector('.message-close');
            if (closeBtn) {
                closeBtn.addEventListener('click', function() {
                    this.closest('.checkout-message').remove();
                });
            }

            // Auto-hide error message after 5 seconds
            const errorMsg = document.querySelector('.checkout-message.error');
            if (errorMsg) {
                setTimeout(() => {
                    errorMsg.style.opacity = '0';
                    setTimeout(() => errorMsg.remove(), 300);
                }, 5000);
            }
        });
    </script>
</body>

</html>