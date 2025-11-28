// Raven Theme - Off-Canvas Cart Handler with AJAX
// Uses document-level event delegation to intercept ALL cart operations
(function() {
    'use strict';

    // Refresh cart content in any open offcanvas
    function refreshOffcanvasCart() {
        fetch('/checkout/offcanvas', {
            method: 'GET',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(response) { return response.text(); })
        .then(function(html) {
            // Find any open offcanvas and update its content
            var offcanvas = document.querySelector('.offcanvas.show .offcanvas-body');
            if (offcanvas) {
                offcanvas.innerHTML = html;
            }
            // Also check for Shopware's offcanvas
            var swOffcanvas = document.querySelector('.offcanvas-cart .offcanvas-body');
            if (swOffcanvas) {
                swOffcanvas.innerHTML = html;
            }
        });
    }

    // Submit form via AJAX and refresh cart
    function submitCartFormAjax(form) {
        var formData = new FormData(form);
        var action = form.getAttribute('action');

        fetch(action, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(function(response) {
            refreshOffcanvasCart();
        })
        .catch(function(error) {
            console.error('Cart update error:', error);
            refreshOffcanvasCart();
        });
    }

    // DOCUMENT-LEVEL EVENT DELEGATION for form submissions
    // This catches ALL form submissions, including dynamically loaded content
    document.addEventListener('submit', function(e) {
        var form = e.target;
        var action = form.getAttribute('action');
        if (!action) return;

        // Intercept cart line-item operations (delete, change quantity)
        if (action.indexOf('checkout/line-item/delete') !== -1 ||
            action.indexOf('checkout/line-item/change-quantity') !== -1) {
            e.preventDefault();
            e.stopPropagation();
            submitCartFormAjax(form);
            return false;
        }

        // Intercept add to cart
        if (action.indexOf('checkout/line-item/add') !== -1) {
            e.preventDefault();
            e.stopPropagation();
            var formData = new FormData(form);
            fetch(action, {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function() {
                // Trigger Shopware's cart button to open offcanvas
                var cartBtn = document.querySelector('[data-offcanvas-cart]');
                if (cartBtn) {
                    cartBtn.click();
                }
            })
            .catch(function(error) {
                console.error('Add to cart error:', error);
            });
            return false;
        }
    }, true); // Use capture phase to intercept before other handlers

    // DOCUMENT-LEVEL EVENT DELEGATION for button clicks
    document.addEventListener('click', function(e) {
        // Handle minus buttons
        var minusBtn = e.target.closest('.js-btn-minus');
        if (minusBtn) {
            e.preventDefault();
            e.stopPropagation();
            var form = minusBtn.closest('form');
            var input = form ? form.querySelector('input[name="quantity"]') : null;
            if (input) {
                var min = parseInt(input.min) || 1;
                var current = parseInt(input.value) || 1;
                if (current > min) {
                    input.value = current - 1;
                    submitCartFormAjax(form);
                }
            }
            return;
        }

        // Handle plus buttons
        var plusBtn = e.target.closest('.js-btn-plus');
        if (plusBtn) {
            e.preventDefault();
            e.stopPropagation();
            var form = plusBtn.closest('form');
            var input = form ? form.querySelector('input[name="quantity"]') : null;
            if (input) {
                var max = parseInt(input.max) || 100;
                var current = parseInt(input.value) || 1;
                if (current < max) {
                    input.value = current + 1;
                    submitCartFormAjax(form);
                }
            }
            return;
        }

        // Handle delete buttons (raven-item-remove)
        var removeBtn = e.target.closest('.raven-item-remove');
        if (removeBtn) {
            e.preventDefault();
            e.stopPropagation();
            var form = removeBtn.closest('form');
            if (form) {
                submitCartFormAjax(form);
            }
            return;
        }
    }, true); // Use capture phase

    // Expose functions globally
    window.ravenRefreshCart = refreshOffcanvasCart;

    console.log('Raven Cart AJAX handler initialized');
})();
