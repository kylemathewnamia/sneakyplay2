document.addEventListener('DOMContentLoaded', function () {
    console.log('Checkout page loaded successfully!');

    // ====== FORM VALIDATION ======
    const checkoutForm = document.getElementById('checkoutForm');
    if (checkoutForm) {
        checkoutForm.addEventListener('submit', function (e) {
            e.preventDefault();

            // Check all required fields
            let isValid = true;
            const requiredFields = checkoutForm.querySelectorAll('[required]');

            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    field.style.borderColor = 'red';
                }
            });

            if (!isValid) {
                alert('Please fill in all required fields');
                return false;
            }

            // Show loading
            const submitBtn = checkoutForm.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            submitBtn.disabled = true;

            // Submit form
            checkoutForm.submit();
        });
    }

    // ====== PAYMENT METHOD SELECTION ======
    const paymentOptions = document.querySelectorAll('.payment-option input[type="radio"]');
    paymentOptions.forEach(option => {
        option.addEventListener('change', function () {
            document.querySelectorAll('.payment-option label').forEach(label => {
                label.style.borderColor = '';
                label.style.background = '';
            });

            const label = this.closest('.payment-option').querySelector('label');
            if (label) {
                label.style.borderColor = 'var(--secondary-color)';
                label.style.background = 'rgba(255, 107, 107, 0.05)';
            }
        });

        if (option.checked) {
            option.dispatchEvent(new Event('change'));
        }
    });

    // ====== PHONE NUMBER FORMATTING ======
    const phoneInput = document.getElementById('phone');
    if (phoneInput) {
        phoneInput.addEventListener('input', function (e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 0) {
                if (!value.startsWith('0')) {
                    value = '0' + value;
                }
                if (value.length <= 11) {
                    e.target.value = value;
                }
            }
        });
    }

});
