import RavenOffcanvasCartPlugin from './plugin/raven-offcanvas-cart.plugin';
import RavenToastPlugin from './plugin/raven-toast.plugin';

const PluginManager = window.PluginManager;

PluginManager.register('RavenOffcanvasCart', RavenOffcanvasCartPlugin, 'body');
PluginManager.register('RavenToast', RavenToastPlugin, 'body');

// Fix cart page delete - bypass AJAX and do full page reload
// Cloudflare sometimes blocks AJAX redirects, so we force page reload after delete
document.addEventListener('DOMContentLoaded', function() {
    // Only run on cart page
    if (!window.location.pathname.includes('/checkout/cart')) return;

    document.addEventListener('click', function(e) {
        const deleteBtn = e.target.closest('form[action*="line-item/delete"] button[type="submit"]');
        if (!deleteBtn) return;

        e.preventDefault();
        e.stopPropagation();

        const form = deleteBtn.closest('form');
        if (!form) return;

        // Submit form via fetch, then reload page
        const formData = new FormData(form);
        const action = form.getAttribute('action');

        fetch(action, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(() => {
            // Force full page reload regardless of response
            window.location.reload();
        })
        .catch(() => {
            // Even on error, try reloading
            window.location.reload();
        });
    }, true); // Use capture phase
});

// Variant Image Capture - captures current product image on add-to-cart
// This ensures the correct variant image shows in cart, checkout, and orders
document.addEventListener('DOMContentLoaded', function() {
    document.addEventListener('click', function(e) {
        const buyButton = e.target.closest('.btn-buy, .buy-widget-submit');
        if (!buyButton) return;

        const form = buyButton.closest('form');
        if (!form) return;

        // Find the main product image currently displayed
        const galleryImage = document.querySelector('.gallery-slider-image.is-active img')
            || document.querySelector('.gallery-slider-image img')
            || document.querySelector('.product-detail-media img')
            || document.querySelector('.product-image-wrapper img');

        if (!galleryImage) return;

        const imageUrl = galleryImage.src || galleryImage.dataset.src;
        if (!imageUrl) return;

        // Create or update hidden input for variant image
        let hiddenInput = form.querySelector('input[name="variantImageUrl"]');
        if (!hiddenInput) {
            hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'variantImageUrl';
            form.appendChild(hiddenInput);
        }
        hiddenInput.value = imageUrl;
    }, true); // Use capture phase to run before form submit
});
