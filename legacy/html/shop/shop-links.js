// Make product cards clickable - redirect to product page
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('.product-card').forEach(card => {
    card.style.cursor = 'pointer';
    card.addEventListener('click', function(e) {
      // Don't navigate if clicking the cart button
      if (e.target.closest('.cart-btn')) return;

      // Use numeric product ID if available, fallback to name
      const productId = this.dataset.productId || this.dataset.name;
      if (productId) {
        window.location.href = '../product.html?id=' + encodeURIComponent(productId);
      }
    });
  });
});
