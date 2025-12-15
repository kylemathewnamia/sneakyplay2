document.addEventListener('DOMContentLoaded', function () {
    console.log('SneakyPlay Admin Dashboard loaded');

    // Animate stat cards
    const statCards = document.querySelectorAll('.stat-card');
    statCards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';

        setTimeout(() => {
            card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 100);
    });

    // Navigation active state
    const currentPage = window.location.pathname.split('/').pop();
    const navLinks = document.querySelectorAll('.nav-link');

    navLinks.forEach(link => {
        if (link.getAttribute('href') === currentPage) {
            link.classList.add('active');
        }
    });

    // Logout confirmation
    const logoutBtn = document.querySelector('.logout-btn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', function (e) {
            if (!confirm('Are you sure you want to logout?')) {
                e.preventDefault();
            }
        });
    }

    // Table row hover effects
    const tableRows = document.querySelectorAll('.admin-table tbody tr');
    tableRows.forEach(row => {
        row.addEventListener('mouseenter', function () {
            this.style.backgroundColor = '#f8f9fa';
        });

        row.addEventListener('mouseleave', function () {
            this.style.backgroundColor = '';
        });
    });

    // Auto-refresh dashboard every 3 minutes
    let refreshTimer = null;

    function refreshDashboard() {
        console.log('Refreshing dashboard data...');
        // In production, use AJAX to refresh data
        // window.location.reload();
    }

    if (currentPage === 'admin.php' || currentPage === '') {
        refreshTimer = setInterval(refreshDashboard, 180000); // 3 minutes
    }

    window.addEventListener('beforeunload', function () {
        if (refreshTimer) {
            clearInterval(refreshTimer);
        }
    });

    // Add search to all tables
    document.querySelectorAll('.admin-table').forEach(table => {
        const searchInput = document.createElement('input');
        searchInput.type = 'text';
        searchInput.placeholder = 'Search in this table...';
        searchInput.className = 'table-search';
        searchInput.style.cssText = `
            margin-bottom: 15px;
            padding: 8px 15px;
            width: 100%;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 0.9rem;
        `;

        table.parentNode.insertBefore(searchInput, table);

        searchInput.addEventListener('keyup', function () {
            const searchTerm = this.value.toLowerCase();
            const rows = table.querySelectorAll('tbody tr');

            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });
    });

    // Quick actions for status badges
    document.querySelectorAll('.status-badge').forEach(badge => {
        badge.addEventListener('click', function () {
            if (this.classList.contains('clickable')) {
                const statuses = {
                    'pending': ['processing', 'Pending'],
                    'processing': ['paid', 'Processing'],
                    'paid': ['shipped', 'Paid'],
                    'shipped': ['delivered', 'Shipped'],
                    'delivered': ['cancelled', 'Delivered'],
                    'cancelled': ['pending', 'Cancelled']
                };

                const currentStatus = this.textContent.trim().toLowerCase();
                const nextStatus = statuses[currentStatus] || ['pending', 'Unknown'];

                // Update badge
                this.textContent = nextStatus[1];
                this.className = 'status-badge ' + nextStatus[0];

                // Show notification
                showNotification('Order status updated to ' + nextStatus[1], 'success');
            }
        });
    });

    // Initialize notification system
    initializeNotifications();
});

// Notification System
function initializeNotifications() {
    const notificationStyles = `
        .admin-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            background: white;
            padding: 15px 20px;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 12px;
            transform: translateX(120%);
            transition: transform 0.3s ease;
            z-index: 9999;
            min-width: 300px;
            max-width: 400px;
            border-left: 4px solid;
        }
        
        .admin-notification.show {
            transform: translateX(0);
        }
        
        .notification-success {
            border-left-color: #10b981;
        }
        
        .notification-error {
            border-left-color: #ef4444;
        }
        
        .notification-info {
            border-left-color: #3b82f6;
        }
        
        .notification-warning {
            border-left-color: #f59e0b;
        }
        
        .admin-notification i:first-child {
            font-size: 1.2rem;
        }
        
        .notification-success i:first-child { color: #10b981; }
        .notification-error i:first-child { color: #ef4444; }
        .notification-info i:first-child { color: #3b82f6; }
        .notification-warning i:first-child { color: #f59e0b; }
        
        .notification-close {
            background: none;
            border: none;
            cursor: pointer;
            margin-left: auto;
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .notification-close:hover {
            color: #333;
        }
    `;

    const styleSheet = document.createElement('style');
    styleSheet.textContent = notificationStyles;
    document.head.appendChild(styleSheet);
}

window.showNotification = function (message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `admin-notification notification-${type}`;
    notification.innerHTML = `
        <i class="fas fa-${getNotificationIcon(type)}"></i>
        <span>${message}</span>
        <button class="notification-close"><i class="fas fa-times"></i></button>
    `;

    document.body.appendChild(notification);

    setTimeout(() => {
        notification.classList.add('show');
    }, 100);

    // Close button
    notification.querySelector('.notification-close').addEventListener('click', function () {
        closeNotification(notification);
    });

    // Auto close after 5 seconds
    setTimeout(() => {
        if (notification.parentNode) {
            closeNotification(notification);
        }
    }, 5000);
};

function getNotificationIcon(type) {
    const icons = {
        'success': 'check-circle',
        'error': 'exclamation-circle',
        'warning': 'exclamation-triangle',
        'info': 'info-circle'
    };
    return icons[type] || 'info-circle';
}

function closeNotification(notification) {
    notification.classList.remove('show');
    setTimeout(() => {
        if (notification.parentNode) {
            notification.remove();
        }
    }, 300);
}

// Sample dashboard data (for demo purposes)
function loadDashboardData() {
    // This would be an AJAX call in production
    const sampleData = {
        total_users: 15,
        total_orders: 11,
        total_revenue: 500000,
        recent_orders: []
    };

    return sampleData;
}

// Export dashboard data (for printing/exporting)
function exportDashboardData(format = 'json') {
    const data = loadDashboardData();

    switch (format) {
        case 'json':
            downloadJSON(data, 'dashboard-data.json');
            break;
        case 'csv':
            downloadCSV(data, 'dashboard-data.csv');
            break;
        default:
            showNotification('Export format not supported', 'error');
    }
}

function downloadJSON(data, filename) {
    const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    a.click();
    URL.revokeObjectURL(url);
    showNotification('Data exported as JSON', 'success');
}

// Keyboard shortcuts
document.addEventListener('keydown', function (e) {
    // Ctrl + D for dashboard refresh
    if (e.ctrlKey && e.key === 'd') {
        e.preventDefault();
        location.reload();
    }

    // Ctrl + E for export
    if (e.ctrlKey && e.key === 'e') {
        e.preventDefault();
        exportDashboardData('json');
    }

    // Escape to clear searches
    if (e.key === 'Escape') {
        document.querySelectorAll('.table-search').forEach(input => {
            input.value = '';
            const event = new Event('keyup');
            input.dispatchEvent(event);
        });
    }

});
