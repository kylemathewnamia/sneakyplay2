    document.addEventListener('DOMContentLoaded', function () {
    console.log('Cart page loaded successfully!');

    // ====== QUANTITY CONTROLS - FIXED ======
    function setupQuantityControls() {
        // Remove any onclick attributes that might cause double firing
        document.querySelectorAll('.quantity-btn').forEach(button => {
            if (button.hasAttribute('onclick')) {
                button.removeAttribute('onclick');
            }
        });

        // Add click event listeners
        document.querySelectorAll('.quantity-btn').forEach(button => {
            button.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();

                const change = this.classList.contains('plus') ? 1 : -1;
                updateQuantity(this, change);
            });
        });

        // Add change event listeners to inputs
        document.querySelectorAll('.quantity-input').forEach(input => {
            // Remove any existing change listeners to prevent duplicates
            input.removeEventListener('change', handleQuantityChange);
            input.addEventListener('change', handleQuantityChange);

            input.addEventListener('blur', function () {
                const maxStock = parseInt(this.getAttribute('max')) || 99;
                const currentValue = parseInt(this.value) || 1;

                if (currentValue > maxStock) {
                    showNotification(`Only ${maxStock} items available in stock`, 'warning');
                    this.value = maxStock;

                    const form = this.closest('.quantity-form');
                    if (form) {
                        submitQuantityForm(form);
                    }
                }
            });
        });
    }

    function handleQuantityChange(e) {
        e.preventDefault();
        const form = this.closest('.quantity-form');
        if (form) {
            submitQuantityForm(form);
        }
    }

    function updateQuantity(button, change) {
        const form = button.closest('.quantity-form');
        const input = form.querySelector('.quantity-input');
        const currentValue = parseInt(input.value) || 1;
        const maxValue = parseInt(input.getAttribute('max')) || 99;
        const minValue = parseInt(input.getAttribute('min')) || 1;

        let newValue = currentValue + change;

        // Validate bounds
        if (newValue < minValue) newValue = minValue;
        if (newValue > maxValue) newValue = maxValue;

        input.value = newValue;

        // Only submit if value actually changed
        if (newValue !== currentValue) {
            submitQuantityForm(form);
        }
    }

    function submitQuantityForm(form) {
        // Show loading state
        const cartItem = form.closest('.cart-item-card');
        if (cartItem) {
            cartItem.classList.add('loading');
        }

        // Submit immediately (remove setTimeout to prevent double submits)
        form.submit();
    }

    // Initialize quantity controls
    setupQuantityControls();

    // ====== REMOVE ITEM CONFIRMATION ======
    document.querySelectorAll('.remove-form').forEach(form => {
        form.addEventListener('submit', function (e) {
            if (!confirm('Remove this item from your cart?')) {
                e.preventDefault();
                return false;
            }

            // Show loading state
            const cartItem = this.closest('.cart-item-card');
            if (cartItem) {
                cartItem.classList.add('loading');
                cartItem.style.opacity = '0.5';
            }

            return true;
        });
    });

    // ====== CLEAR CART CONFIRMATION ======
    const clearCartForm = document.querySelector('.clear-cart-form');
    if (clearCartForm) {
        clearCartForm.addEventListener('submit', function (e) {
            if (!confirm('Are you sure you want to clear your entire cart? This action cannot be undone.')) {
                e.preventDefault();
                return false;
            }

            // Show loading state
            const button = this.querySelector('button[type="submit"]');
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Clearing...';
            button.disabled = true;

            return true;
        });
    }

    // ====== NOTIFICATION SYSTEM ======
    function showNotification(message, type = 'info') {
        // Remove any existing notification
        const existingNotification = document.querySelector('.cart-message-notification');
        if (existingNotification) {
            existingNotification.remove();
        }

        // Create notification
        const notification = document.createElement('div');
        notification.className = `cart-message-notification ${type}`;

        let icon = 'fa-info-circle';
        let borderColor = 'var(--info-color)';

        switch (type) {
            case 'success':
                icon = 'fa-check-circle';
                borderColor = 'var(--success-color)';
                break;
            case 'error':
                icon = 'fa-exclamation-circle';
                borderColor = 'var(--danger-color)';
                break;
            case 'warning':
                icon = 'fa-exclamation-triangle';
                borderColor = 'var(--warning-color)';
                break;
        }

        notification.innerHTML = `
            <div class="notification-content">
                <i class="fas ${icon}"></i>
                <span>${message}</span>
            </div>
            <button class="notification-close">&times;</button>
        `;

        notification.style.borderLeftColor = borderColor;

        // Insert after header
        const mainContent = document.querySelector('.main-content');
        if (mainContent) {
            mainContent.insertBefore(notification, mainContent.firstChild);
        }

        // Auto remove after 5 seconds
        setTimeout(() => {
            notification.style.opacity = '0';
            notification.style.transform = 'translateY(-10px)';
            setTimeout(() => notification.remove(), 300);
        }, 5000);

        // Close button
        const closeBtn = notification.querySelector('.notification-close');
        closeBtn.addEventListener('click', () => {
            notification.style.opacity = '0';
            notification.style.transform = 'translateY(-10px)';
            setTimeout(() => notification.remove(), 300);
        });
    }

    // ====== CHECKOUT BUTTON VALIDATION ======
    const checkoutButtons = document.querySelectorAll('a[href="checkout.php"]');
    checkoutButtons.forEach(button => {
        button.addEventListener('click', function (e) {
            // Check if any items are out of stock
            const outOfStockItems = document.querySelectorAll('.stock-info.out-of-stock');
            if (outOfStockItems.length > 0) {
                e.preventDefault();
                showNotification('Please remove out-of-stock items before checkout', 'error');
                return false;
            }

            // Check if cart is empty
            const emptyCart = document.querySelector('.empty-cart-state');
            if (emptyCart) {
                e.preventDefault();
                showNotification('Your cart is empty. Add items before checkout.', 'warning');
                return false;
            }

            // Show loading state
            this.classList.add('loading');
            showNotification('Redirecting to secure checkout...', 'info');
            return true;
        });
    });

    // ====== PAGE ANIMATIONS ======
    // Add fade-in animation to cart items
    const cartItems = document.querySelectorAll('.cart-item-card');
    cartItems.forEach((item, index) => {
        item.style.opacity = '0';
        item.style.transform = 'translateY(20px)';

        setTimeout(() => {
            item.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
            item.style.opacity = '1';
            item.style.transform = 'translateY(0)';
        }, index * 100);
    });

    // ====== MOBILE OPTIMIZATIONS ======
    function handleMobileLayout() {
        const isMobile = window.innerWidth < 768;

        // Update quantity controls for mobile
        const updateButtons = document.querySelectorAll('.btn-update');
        updateButtons.forEach(btn => {
            if (isMobile) {
                btn.classList.remove('hidden');
            } else {
                btn.classList.add('hidden');
            }
        });
    }

    // Initial mobile layout check
    handleMobileLayout();

    // Update on resize
    window.addEventListener('resize', handleMobileLayout);

    // ====== CART COUNT UPDATE ======
    function updateCartCount() {
        const itemCount = document.querySelector('.item-count');
        if (itemCount) {
            const currentCount = parseInt(itemCount.textContent) || 0;

            // Update header cart count if exists
            const headerCartCount = document.querySelector('.header-cart-count');
            if (headerCartCount) {
                headerCartCount.textContent = currentCount;
            }
        }
    }

    // Initialize
    updateCartCount();

    // ====== CLOSE NOTIFICATION ======
    const closeNotificationBtn = document.querySelector('.notification-close');
    if (closeNotificationBtn) {
        closeNotificationBtn.addEventListener('click', function () {
            const notification = this.closest('.cart-message-notification');
            if (notification) {
                notification.style.opacity = '0';
                notification.style.transform = 'translateY(-10px)';
                setTimeout(() => notification.remove(), 300);
            }
        });

        // Auto-remove after 5 seconds
        setTimeout(() => {
            const notification = document.querySelector('.cart-message-notification');
            if (notification) {
                notification.style.opacity = '0';
                notification.style.transform = 'translateY(-10px)';
                setTimeout(() => notification.remove(), 300);
            }
        }, 5000);
    }

});
