<?php
// MUST BE FIRST LINE
session_start();

// Debug session
if (isset($_SESSION['user_id'])) {
    error_log("User ID in session: " . $_SESSION['user_id']);
    error_log("Session username: " . ($_SESSION['username'] ?? 'NOT SET'));
    error_log("Session user_name: " . ($_SESSION['user_name'] ?? 'NOT SET'));
}

// Get user name
$user_name = 'Guest';

// Check different session variables
if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
    if (isset($_SESSION['username']) && !empty($_SESSION['username'])) {
        $user_name = htmlspecialchars($_SESSION['username']);
    } elseif (isset($_SESSION['user_name']) && !empty($_SESSION['user_name'])) {
        $user_name = htmlspecialchars($_SESSION['user_name']);
    } elseif (isset($_SESSION['email']) && !empty($_SESSION['email'])) {
        $user_name = htmlspecialchars($_SESSION['email']);
    } else {
        $user_name = 'User'; // Default
    }
}

// Get cart count for logged-in users
$cart_count = 0;
if (isset($_SESSION['user_id'])) {
    $host = "127.0.0.1";
    $username = "root";
    $password = "";
    $database = "sneakysheets";

    $cart_conn = mysqli_connect($host, $username, $password, $database);
    if ($cart_conn) {
        $user_id = $_SESSION['user_id'];
        $cart_query = "SELECT cart_id FROM cart WHERE user_id = '$user_id'";
        $cart_result = mysqli_query($cart_conn, $cart_query);

        if ($cart_result && mysqli_num_rows($cart_result) > 0) {
            $cart = mysqli_fetch_assoc($cart_result);
            $cart_id = $cart['cart_id'];

            $count_query = "SELECT SUM(quantity) as total_qty FROM cart_items WHERE cart_id = '$cart_id'";
            $count_result = mysqli_query($cart_conn, $count_query);
            if ($count_result && $row = mysqli_fetch_assoc($count_result)) {
                $cart_count = $row['total_qty'] ?: 0;
            }
        }
        mysqli_close($cart_conn);
    }
}

ob_start();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Products | SneakyPlay</title>
    <link rel="stylesheet" href="../assets/css/shop.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" type="image/png" href="../assets/image/logo.png">
</head>

<body>
    <!-- Header with Back Button to Dashboard -->
    <header class="shop-header">
        <div class="container">
            <a href="index.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
            <h1><i class="fas fa-gamepad"></i> Browse Products</h1>
            <div class="header-actions">
                <a href="cart.php" class="cart-btn">
                    <i class="fas fa-shopping-cart"></i> View Cart
                    <span class="cart-count"><?php echo $cart_count; ?></span>
                </a>
                <div class="user-welcome">
                    <i class="fas fa-user"></i>
                    <span class="user-name"><?php echo $user_name; ?></span>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="shop-container">
        <!-- Database Connection -->
        <?php
        $host = "127.0.0.1";
        $username = "root";
        $password = "";
        $database = "sneakysheets";

        $conn = mysqli_connect($host, $username, $password, $database);

        if (!$conn) {
            echo "<div class='error'>Database connection failed</div>";
            exit();
        }
        ?>

        <!-- Search and Filter Section -->
        <section class="shop-controls">
            <div class="search-box">
                <input type="text" id="searchInput" placeholder="Search products...">
                <button onclick="searchProducts()"><i class="fas fa-search"></i></button>
            </div>

            <!-- Organized Category Filter -->
            <div class="category-filter-section">
                <h3><i class="fas fa-filter"></i> Filter by Category</h3>
                <div class="category-filter">
                    <button class="filter-btn active" data-category="all">
                        <i class="fas fa-th-large"></i> All Products
                    </button>
                    <?php
                    $cat_query = "SELECT * FROM categories ORDER BY category_name";
                    $cat_result = mysqli_query($conn, $cat_query);

                    if (mysqli_num_rows($cat_result) > 0) {
                        while ($cat = mysqli_fetch_assoc($cat_result)) {
                            echo '<button class="filter-btn" data-category="' . $cat['categories_id'] . '">
                                    <i class="fas fa-tag"></i> '
                                . htmlspecialchars($cat['category_name']) .
                                '</button>';
                        }
                    }
                    ?>
                </div>
            </div>
        </section>

        <!-- Products Grid -->
        <section class="products-section">
            <h2><i class="fas fa-list"></i> Available Products</h2>

            <div class="products-grid" id="productsGrid">
                <?php
                $sql = "SELECT p.*, s.quantity as stock_qty, c.category_name 
                        FROM products p 
                        LEFT JOIN stock s ON p.product_id = s.product_id
                        LEFT JOIN categories c ON p.categories_id = c.categories_id
                        WHERE s.quantity > 0
                        ORDER BY p.product_id DESC";

                $result = mysqli_query($conn, $sql);

                if (mysqli_num_rows($result) > 0) {
                    while ($product = mysqli_fetch_assoc($result)) {
                        $stock_status = "";
                        $stock_class = "";
                        if ($product['stock_qty'] > 10) {
                            $stock_status = "In Stock";
                            $stock_class = "in-stock";
                        } elseif ($product['stock_qty'] > 0) {
                            $stock_status = "Low Stock (" . $product['stock_qty'] . " left)";
                            $stock_class = "low-stock";
                        } else {
                            $stock_status = "Out of Stock";
                            $stock_class = "out-stock";
                        }

                        $formatted_price = "₱" . number_format($product['price'], 2);

                        // Get image URL - CHANGED THIS PART
                        $image_src = '/sneakyplay2/assets/image/' . htmlspecialchars($product['image']);

                        echo '
                        <div class="product-card" data-category="' . $product['categories_id'] . '">
                            <div class="product-image">
                                <span class="category-badge">' . htmlspecialchars($product['category_name']) . '</span>
                                <img src="' . $image_src . '" 
                                     alt="' . htmlspecialchars($product['product_name']) . '"
                                     onerror="this.onerror=null; this.src=\'https://picsum.photos/seed/product' . $product['product_id'] . '/300/200.jpg\';">
                            </div>
                            
                            <div class="product-info">
                                <h3 class="product-name">' . htmlspecialchars($product['product_name']) . '</h3>
                                <p class="product-desc">' . substr(htmlspecialchars($product['description']), 0, 100) . '...</p>
                                
                                <div class="price-section">
                                    <span class="price">' . $formatted_price . '</span>
                                    <span class="stock-status ' . $stock_class . '">
                                        <i class="fas fa-box"></i> ' . $stock_status . '
                                    </span>
                                </div>
                                
                                <div class="quantity-wrapper">
                                    <div class="quantity-control">
                                        <button type="button" class="qty-btn qty-decrease"><i class="fas fa-minus"></i></button>
                                        <input type="number" name="quantity" value="1" min="1" max="' . min($product['stock_qty'], 10) . '" class="qty-input">
                                        <button type="button" class="qty-btn qty-increase"><i class="fas fa-plus"></i></button>
                                    </div>
                                </div>
                                
                                <form class="add-to-cart-form">
                                    <input type="hidden" name="product_id" value="' . $product['product_id'] . '">
                                    <button type="submit" class="add-cart-btn" ' . ($product['stock_qty'] <= 0 ? 'disabled' : '') . '>
                                        <i class="fas fa-cart-plus"></i> Add to Cart
                                    </button>
                                </form>
                            </div>
                        </div>';
                    }
                } else {
                    echo '<div class="no-products">
                            <i class="fas fa-gamepad"></i>
                            <h3>No products available</h3>
                            <p>Check back soon for new arrivals!</p>
                          </div>';
                }

                mysqli_close($conn);
                ?>
            </div>
        </section>
    </main>

    <!-- Footer -->
    <footer class="shop-footer">
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> SneakyPlay Gaming Store. All rights reserved.</p>
            <p>Browse our latest gaming products and accessories</p>
        </div>
    </footer>

    <!-- JavaScript -->
    <script src="../assets/js/shop.js"></script>
    <?php ob_end_flush(); ?>
</body>

</html>