// Search Functionality
class ProductSearch {
  constructor() {
    this.init();
  }

  init() {
    // Get all search inputs
    const searchInputs = document.querySelectorAll('input[type="search"]');
    
    searchInputs.forEach(input => {
      // Search on input change
      input.addEventListener('input', (e) => {
        this.performSearch(e.target.value);
      });

      // Search on enter key
      input.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') {
          e.preventDefault();
          this.performSearch(e.target.value);
        }
      });
    });
  }

  performSearch(query) {
    const searchTerm = query.toLowerCase().trim();

    // If on homepage and we have products in memory, search through them
    if (window.ravenProducts && Array.isArray(window.ravenProducts)) {
      this.searchInMemoryProducts(searchTerm);
      return;
    }

    // Fallback: search through DOM elements (for other pages)
    const products = document.querySelectorAll('.product-card, article[data-name]');

    if (!products.length) {
      console.log('No products found on this page');
      return;
    }

    let visibleCount = 0;

    if (searchTerm === '') {
      // Show all products if search is empty
      products.forEach(product => {
        product.style.display = '';
        visibleCount++;
      });
    } else {
      // Filter products based on search term
      products.forEach(product => {
        const productName = (product.dataset.name || '').toLowerCase();
        const productCategory = (product.dataset.category || '').toLowerCase();
        const productCaliber = (product.dataset.caliber || '').toLowerCase();
        const titleElement = product.querySelector('.prod-title');
        const categoryElement = product.querySelector('.cat-label');

        const titleText = titleElement ? titleElement.textContent.toLowerCase() : '';
        const categoryText = categoryElement ? categoryElement.textContent.toLowerCase() : '';

        // Check if search term matches any product attribute
        const matches =
          productName.includes(searchTerm) ||
          productCategory.includes(searchTerm) ||
          productCaliber.includes(searchTerm) ||
          titleText.includes(searchTerm) ||
          categoryText.includes(searchTerm);

        if (matches) {
          product.style.display = '';
          visibleCount++;
        } else {
          product.style.display = 'none';
        }
      });
    }

    // Update product count if element exists
    const productCountElement = document.getElementById('product-count');
    if (productCountElement) {
      productCountElement.textContent = visibleCount;
    }

    // Show a message if no results found
    this.showSearchResults(visibleCount, searchTerm);
  }

  searchInMemoryProducts(searchTerm) {
    console.log(`[Search] Searching ${window.ravenProducts.length} products for: "${searchTerm}"`);

    let filteredProducts;

    if (searchTerm === '') {
      // Show all products if search is empty
      filteredProducts = window.ravenProducts;
    } else {
      // Filter products based on search term
      filteredProducts = window.ravenProducts.filter(product => {
        const productName = (product.name || '').toLowerCase();
        const productCategory = (product.category || '').toLowerCase();
        const productDescription = (product.description || '').toLowerCase();
        const productId = String(product.id || '').toLowerCase();

        // Check if search term matches any product attribute
        return (
          productName.includes(searchTerm) ||
          productCategory.includes(searchTerm) ||
          productDescription.includes(searchTerm) ||
          productId.includes(searchTerm)
        );
      });
    }

    console.log(`[Search] Found ${filteredProducts.length} matching products`);

    // Re-render products with filtered results
    if (window.RavenWeapon && typeof window.RavenWeapon.renderProducts === 'function') {
      window.RavenWeapon.renderProducts(filteredProducts);
    }

    // Update product count if element exists
    const productCountElement = document.getElementById('product-count');
    if (productCountElement) {
      productCountElement.textContent = filteredProducts.length;
    }

    // Show a message if no results found
    this.showSearchResults(filteredProducts.length, searchTerm);
  }

  showSearchResults(count, searchTerm) {
    // Remove any existing search result message
    const existingMessage = document.getElementById('search-result-message');
    if (existingMessage) {
      existingMessage.remove();
    }

    if (searchTerm && count === 0) {
      const productGrid = document.getElementById('productGrid');
      if (productGrid) {
        const message = document.createElement('div');
        message.id = 'search-result-message';
        message.className = 'col-span-full text-center py-12';
        message.innerHTML = `
          <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
          </svg>
          <p class="text-gray-600 text-lg font-medium">Keine Produkte gefunden</p>
          <p class="text-gray-500 text-sm mt-2">Versuchen Sie es mit anderen Suchbegriffen</p>
        `;
        productGrid.appendChild(message);
      }
    }
  }

  clearSearch() {
    const searchInputs = document.querySelectorAll('input[type="search"]');
    searchInputs.forEach(input => {
      input.value = '';
    });
    this.performSearch('');
  }
}

// Initialize search when DOM is loaded
let productSearch;
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => {
    productSearch = new ProductSearch();
    window.productSearch = productSearch;
    console.log('Search initialized');
  });
} else {
  productSearch = new ProductSearch();
  window.productSearch = productSearch;
  console.log('Search initialized');
}

