const yearEl = document.getElementById("year");
if (yearEl) {
  yearEl.textContent = new Date().getFullYear();
}

// Filter removed for the "Popular products" section; leaving this file to handle header menu only.

// Mobile menu toggle (header)
(function () {
  const toggleBtn = document.getElementById("mobile-menu-toggle");
  const mobileMenu = document.getElementById("mobile-menu");
  const hamburger = document.getElementById("hamburger-icon");
  const closeIcon = document.getElementById("close-icon");

  if (!toggleBtn || !mobileMenu || !hamburger || !closeIcon) return;

  toggleBtn.addEventListener("click", () => {
    const isOpen = !mobileMenu.classList.contains("hidden");
    if (isOpen) {
      mobileMenu.classList.add("hidden");
      hamburger.classList.remove("hidden");
      closeIcon.classList.add("hidden");
    } else {
      mobileMenu.classList.remove("hidden");
      hamburger.classList.add("hidden");
      closeIcon.classList.remove("hidden");
    }
  });
})();

// Category tiles -> filter products by data-category
(function(){
  const grid = document.getElementById('productGrid');
  if (!grid) return;

  const cards = Array.from(grid.querySelectorAll('[data-category]'));
  const filterElements = Array.from(document.querySelectorAll('[data-filter]'));

  function applyFilter(filter){
    const normalized = (filter || 'all').toLowerCase();
    // toggle a class on the grid so CSS can adapt separators when filtered
    grid.classList.toggle('is-filtered', normalized !== 'all');
    let visibleCount = 0;
    cards.forEach(card => {
      const type = (card.getAttribute('data-category') || '').toLowerCase();
      const show = normalized === 'all' || type === normalized;
      if (show) visibleCount += 1;
      card.style.display = show ? '' : 'none';
    });
    // If no matches, gracefully fall back to showing all
    if (visibleCount === 0 && normalized !== 'all') {
      applyFilter('all');
    }
  }

  function smoothToProducts(){
    const target = document.getElementById('produkte');
    if (target) target.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  // Show all products by default (filter UI removed)
  applyFilter('all');

  // Wire up click listeners if any filter elements exist
  if (filterElements.length > 0) {
    filterElements.forEach(el => {
      el.addEventListener('click', (e) => {
        // if it's an anchor, prevent default jumping elsewhere; we'll smooth scroll instead
        if (el.tagName === 'A') {
          e.preventDefault();
        }
        const filter = el.getAttribute('data-filter') || 'all';
        applyFilter(filter);
        setTimeout(smoothToProducts, 0);
      });
    });
  }
})();

// Product card click handler - navigate to product page
(function() {
  const productCards = document.querySelectorAll('.product-card[data-product-id]');

  productCards.forEach(card => {
    // Make the entire card clickable except for the cart button
    card.style.cursor = 'pointer';

    card.addEventListener('click', (e) => {
      // Don't navigate if clicking the cart button
      if (e.target.closest('.cart-btn')) {
        return;
      }

      const productId = card.getAttribute('data-product-id');
      if (productId) {
        window.location.href = `product.html?id=${productId}`;
      }
    });
  });
})();
