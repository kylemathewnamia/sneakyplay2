<?php
session_start();
// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../login.php');
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

// Get admin data
$admin_id = $_SESSION['admin_id'];
$stmt = $pdo->prepare("SELECT * FROM admins WHERE admin_id = ?");
$stmt->execute([$admin_id]);
$admin = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$admin) {
    header('Location: ../login.php');
    exit();
}

// Get admin name for display
$admin_name = htmlspecialchars($admin['name']);

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$search = $_GET['search'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Build WHERE clause for orders query
$where_conditions = [];
$params = [];

if ($status_filter !== 'all') {
    $where_conditions[] = "o.status = ?";
    $params[] = $status_filter;
}

if (!empty($date_from)) {
    $where_conditions[] = "o.order_date >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $where_conditions[] = "o.order_date <= ?";
    $params[] = $date_to;
}

if (!empty($search)) {
    $where_conditions[] = "(o.order_id LIKE ? OR u.name LIKE ? OR u.email LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM orders o 
              LEFT JOIN users u ON o.user_id = u.user_id 
              $where_clause";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_orders = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_orders / $limit);

// Get orders with pagination
$order_sql = "SELECT o.order_id, o.order_date, o.total_amount, o.status, 
                     u.name as customer_name, u.email,
                     (SELECT COUNT(*) FROM order_items WHERE order_id = o.order_id) as item_count
              FROM orders o 
              LEFT JOIN users u ON o.user_id = u.user_id 
              $where_clause 
              ORDER BY o.order_date DESC 
              LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
$order_stmt = $pdo->prepare($order_sql);
$order_stmt->execute($params);
$orders = $order_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats_sql = "SELECT 
                COUNT(*) as total_orders,
                SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid_orders,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
                SUM(CASE WHEN status = 'paid' THEN total_amount ELSE 0 END) as total_revenue
              FROM orders";
$stats_stmt = $pdo->prepare($stats_sql);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $selected_orders = $_POST['selected_orders'] ?? [];

    if (!empty($selected_orders)) {
        $placeholders = implode(',', array_fill(0, count($selected_orders), '?'));

        if ($_POST['bulk_action'] === 'delete') {
            // Delete orders
            $delete_sql = "DELETE FROM orders WHERE order_id IN ($placeholders)";
            $delete_stmt = $pdo->prepare($delete_sql);
            $delete_stmt->execute($selected_orders);

            $_SESSION['success_message'] = count($selected_orders) . " order(s) deleted successfully!";
        } elseif ($_POST['bulk_action'] === 'update_status' && !empty($_POST['new_status'])) {
            // Update status
            $update_sql = "UPDATE orders SET status = ? WHERE order_id IN ($placeholders)";
            $update_params = array_merge([$_POST['new_status']], $selected_orders);
            $update_stmt = $pdo->prepare($update_sql);
            $update_stmt->execute($update_params);

            $_SESSION['success_message'] = count($selected_orders) . " order(s) updated to " . $_POST['new_status'] . "!";
        }

        // Refresh the page
        header("Location: orders.php");
        exit();
    } else {
        $_SESSION['error_message'] = "Please select at least one order.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orders Management | SneakyPlay</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" type="image/png" href="../assets/image/logo.png">
</head>

<body class="admin-dashboard">
    <!-- Admin Dashboard Header -->
    <nav class="admin-nav">
        <div class="admin-nav-container">
            <!-- Logo -->
            <div class="admin-logo">
                <i class="fas fa-gamepad"></i>
                <span>SneakyPlay Admin</span>
            </div>

            <!-- Navigation Menu -->
            <div class="admin-nav-menu">
                <a href="admin.php" class="nav-link">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
                <a href="product.php" class="nav-link">
                    <i class="fas fa-boxes"></i>
                    <span>Products</span>
                </a>
                <a href="orders.php" class="nav-link active">
                    <i class="fas fa-shopping-cart"></i>
                    <span>Orders</span>
                </a>
                <a href="users.php" class="nav-link">
                    <i class="fas fa-users"></i>
                    <span>Users</span>
                </a>
                </a>

            </div>

            <!-- User & Logout -->
            <div class="admin-user">
                <div class="user-circle">
                    <span class="user-initial"><?php echo strtoupper(substr($admin_name, 0, 1)); ?></span>
                </div>
                <span class="user-welcome-text">Welcome, <?php echo $admin_name; ?></span>
                <a href="logout.php" class="logout-icon">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="admin-main">
        <div class="admin-container">
            <!-- Dashboard Header -->
            <div class="dashboard-header">
                <h1><i class="fas fa-shopping-cart"></i> Orders Management</h1>
                <p>Manage all customer orders from here</p>
            </div>

            <?php if (isset($_SESSION['success_message'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $_SESSION['success_message'];
                    unset($_SESSION['success_message']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $_SESSION['error_message'];
                    unset($_SESSION['error_message']); ?>
                </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon users">
                        <i class="fas fa-shopping-bag"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Total Orders</h3>
                        <p class="stat-value"><?php echo $stats['total_orders'] ?? 0; ?></p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon products">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Paid Orders</h3>
                        <p class="stat-value"><?php echo $stats['paid_orders'] ?? 0; ?></p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon orders">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Pending Orders</h3>
                        <p class="stat-value"><?php echo $stats['pending_orders'] ?? 0; ?></p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon revenue">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="stat-content">
                        <h3>Total Revenue</h3>
                        <p class="stat-value">₱<?php echo number_format($stats['total_revenue'] ?? 0, 2); ?></p>
                    </div>
                </div>
            </div>

            <!-- Filters Section -->
            <div class="filters-section">
                <h3><i class="fas fa-filter"></i> Filter Orders</h3>
                <form method="GET" class="filter-form">
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" class="form-control">
                            <option value="all">All Status</option>
                            <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="processing" <?php echo $status_filter == 'processing' ? 'selected' : ''; ?>>Processing</option>
                            <option value="paid" <?php echo $status_filter == 'paid' ? 'selected' : ''; ?>>Paid</option>
                            <option value="shipped" <?php echo $status_filter == 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                            <option value="delivered" <?php echo $status_filter == 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                            <option value="cancelled" <?php echo $status_filter == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Date From</label>
                        <input type="date" name="date_from" class="form-control" value="<?php echo htmlspecialchars($date_from); ?>">
                    </div>

                    <div class="form-group">
                        <label>Date To</label>
                        <input type="date" name="date_to" class="form-control" value="<?php echo htmlspecialchars($date_to); ?>">
                    </div>

                    <div class="form-group">
                        <label>Search (Order ID, Customer, Email)</label>
                        <input type="text" name="search" class="form-control" placeholder="Search..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>

                    <button type="submit" class="btn-filter">
                        <i class="fas fa-search"></i> Apply Filters
                    </button>

                    <?php if (!empty($status_filter) || !empty($date_from) || !empty($date_to) || !empty($search)): ?>
                        <a href="orders.php" class="btn-filter" style="background: var(--text-secondary);">
                            <i class="fas fa-times"></i> Clear Filters
                        </a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Bulk Actions Form -->
            <div class="dashboard-section">
                <div class="section-header">
                    <h2><i class="fas fa-list"></i> All Orders</h2>
                </div>

                <form method="POST" action="" id="bulkForm">
                    <div class="bulk-actions">
                        <div class="bulk-select-all">
                            <input type="checkbox" id="selectAll" class="order-checkbox">
                            <label for="selectAll">Select All</label>
                            <span style="margin-left: 1rem; font-weight: normal; color: var(--text-secondary);">
                                <?php echo count($orders); ?> order(s) displayed
                            </span>
                        </div>

                        <div class="bulk-buttons">
                            <select name="new_status" class="form-control" style="width: auto; padding: 0.6rem; border-radius: 6px;">
                                <option value="">Update Status to...</option>
                                <option value="pending">Pending</option>
                                <option value="processing">Processing</option>
                                <option value="paid">Paid</option>
                                <option value="shipped">Shipped</option>
                                <option value="delivered">Delivered</option>
                                <option value="cancelled">Cancelled</option>
                            </select>

                            <button type="submit" name="bulk_action" value="update_status" class="btn-small">
                                <i class="fas fa-sync-alt"></i> Update
                            </button>

                            <button type="submit" name="bulk_action" value="delete" class="btn-small" onclick="return confirmBulkDelete()" style="background: var(--danger-color);">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </div>
                    </div>

                    <!-- Orders Table -->
                    <div class="table-container">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th width="50"></th>
                                    <th>Order ID</th>
                                    <th>Customer</th>
                                    <th>Date</th>
                                    <th>Items</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($orders)): ?>
                                    <?php foreach ($orders as $order): ?>
                                        <tr>
                                            <td>
                                                <input type="checkbox" name="selected_orders[]" value="<?php echo $order['order_id']; ?>" class="order-checkbox">
                                            </td>
                                            <td>
                                                <strong>#<?php echo str_pad($order['order_id'], 6, '0', STR_PAD_LEFT); ?></strong>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($order['customer_name']); ?></strong><br>
                                                <small style="color: var(--text-secondary);"><?php echo htmlspecialchars($order['email']); ?></small>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($order['order_date'])); ?></td>
                                            <td><?php echo $order['item_count']; ?> item(s)</td>
                                            <td><strong>₱<?php echo number_format($order['total_amount'], 2); ?></strong></td>
                                            <td>
                                                <span class="status-badge <?php echo strtolower($order['status']); ?>">
                                                    <?php echo strtoupper($order['status']); ?>
                                                </span>
                                            </td>
                                            <td>

                                                <a href="edit-order.php?id=<?php echo $order['order_id']; ?>" class="btn-action btn-edit">
                                                    <i class="fas fa-edit"></i> Edit
                                                </a>
                    </div>
                    </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="8" class="text-center">
                        <div class="empty-state">
                            <i class="fas fa-shopping-bag"></i>
                            <h3>No orders found</h3>
                            <p>No orders match your current filters.</p>
                            <?php if (!empty($status_filter) || !empty($date_from) || !empty($date_to) || !empty($search)): ?>
                                <a href="orders.php" class="btn-filter" style="margin-top: 1rem;">
                                    <i class="fas fa-times"></i> Clear Filters
                                </a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
            </table>
            </div>
            </form>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&status=<?php echo $status_filter; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&search=<?php echo urlencode($search); ?>" class="page-link">
                            <i class="fas fa-chevron-left"></i> Prev
                        </a>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <?php if ($i == 1 || $i == $total_pages || ($i >= $page - 2 && $i <= $page + 2)): ?>
                            <a href="?page=<?php echo $i; ?>&status=<?php echo $status_filter; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&search=<?php echo urlencode($search); ?>"
                                class="page-link <?php echo $i == $page ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php elseif ($i == $page - 3 || $i == $page + 3): ?>
                            <span class="page-link disabled">...</span>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&status=<?php echo $status_filter; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>&search=<?php echo urlencode($search); ?>" class="page-link">
                            Next <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        </div>
    </main>

    <!-- Footer - Optional -->
    <footer class="shop-footer" style="margin-top: 3rem;">
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> SneakyPlay Gaming Store. All rights reserved.</p>
            <p>Order Management System</p>
        </div>
    </footer>

    <script>
        // Select All checkbox functionality
        document.getElementById('selectAll').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('input[name="selected_orders[]"]');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });

        // Confirm bulk delete
        function confirmBulkDelete() {
            const checkboxes = document.querySelectorAll('input[name="selected_orders[]"]:checked');
            if (checkboxes.length === 0) {
                alert('Please select at least one order to delete.');
                return false;
            }
            return confirm(`Are you sure you want to delete ${checkboxes.length} order(s)? This action cannot be undone.`);
        }

        // Update select all checkbox when individual checkboxes change
        document.querySelectorAll('input[name="selected_orders[]"]').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const checkboxes = document.querySelectorAll('input[name="selected_orders[]"]');
                const selectAll = document.getElementById('selectAll');
                const allChecked = Array.from(checkboxes).every(cb => cb.checked);
                const anyChecked = Array.from(checkboxes).some(cb => cb.checked);

                selectAll.checked = allChecked;
                selectAll.indeterminate = anyChecked && !allChecked;
            });
        });
    </script>
</body>


</html>
