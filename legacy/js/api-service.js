/**
 * RavenWeapon API Service
 * This file handles all API calls to the Joomla/HikaShop backend
 *
 * SETUP:
 * 1. Add this script to your HTML: <script src="api-service.js"></script>
 * 2. Make sure XAMPP Apache and MySQL are running
 * 3. Access your Joomla at: http://localhost/ravenweaponag/
 */

// API Configuration
const API_CONFIG = {
  // Local development (XAMPP)
  local: 'http://localhost/ravenweaponwebapp/api',

  // Production (Linux server) - Update this later when deploying
  production: 'https://yourdomain.com/api',

  // Current environment
  current: 'local' // Change to 'production' when deploying
};

const API_BASE_URL = API_CONFIG[API_CONFIG.current];

/**
 * API Service Class
 */
class RavenWeaponAPI {

  /**
   * Generic fetch wrapper with error handling
   */
  async request(endpoint, options = {}) {
    const url = `${API_BASE_URL}${endpoint}`;

    const defaultOptions = {
      headers: {
        'Content-Type': 'application/json',
      },
      ...options
    };

    try {
      console.log(`[API] ${options.method || 'GET'} ${url}`);

      const response = await fetch(url, defaultOptions);

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }

      const data = await response.json();

      if (!data.success) {
        throw new Error(data.error || 'API request failed');
      }

      console.log(`[API] Success:`, data);
      return data;

    } catch (error) {
      console.error('[API] Request Error:', error);
      throw error;
    }
  }

  /**
   * Get all products
   * @returns {Promise<Array>} Array of products
   */
  async getProducts() {
    const response = await this.request('/products.php');
    return response.data;
  }

  /**
   * Get single product by ID
   * @param {string} productId - Product ID
   * @returns {Promise<Object>} Product object
   */
  async getProduct(productId) {
    const response = await this.request(`/product.php?id=${productId}`);
    return response.data;
  }

  /**
   * Get current cart
   * @returns {Promise<Object>} Cart object with items
   */
  async getCart() {
    const response = await this.request('/cart.php');
    return response.data;
  }

  /**
   * Add item to cart
   * @param {Object} item - Cart item { productId, quantity, variant }
   * @returns {Promise<Object>} Updated cart
   */
  async addToCart(item) {
    const response = await this.request('/cart.php', {
      method: 'POST',
      body: JSON.stringify(item)
    });
    return response.data;
  }

  /**
   * Update cart item quantity
   * @param {string} productId - Product ID
   * @param {number} quantity - New quantity
   * @returns {Promise<Object>} Updated cart
   */
  async updateCartItem(productId, quantity) {
    const response = await this.request('/cart.php', {
      method: 'PUT',
      body: JSON.stringify({ productId, quantity })
    });
    return response.data;
  }

  /**
   * Remove item from cart
   * @param {string} productId - Product ID
   * @returns {Promise<Object>} Updated cart
   */
  async removeFromCart(productId) {
    const response = await this.request('/cart.php', {
      method: 'DELETE',
      body: JSON.stringify({ productId })
    });
    return response.data;
  }

  /**
   * Clear entire cart
   * @returns {Promise<Object>} Success message
   */
  async clearCart() {
    const response = await this.request('/cart.php', {
      method: 'DELETE',
      body: JSON.stringify({ action: 'clear' })
    });
    return response.data;
  }

  /**
   * Submit checkout order
   * @param {Object} orderData - Order information
   * @returns {Promise<Object>} Order confirmation
   */
  async checkout(orderData) {
    const response = await this.request('/checkout.php', {
      method: 'POST',
      body: JSON.stringify(orderData)
    });
    return response.data;
  }

  /**
   * Get user orders
   * @param {number} userId - User ID
   * @returns {Promise<Array>} Array of orders
   */
  async getOrders(userId) {
    const response = await this.request(`/orders.php?userId=${userId}`);
    return response.data;
  }
}

// Create singleton instance
const api = new RavenWeaponAPI();

// Export for use in other files
if (typeof module !== 'undefined' && module.exports) {
  module.exports = api;
}

/**
 * Helper function to get product by ID (replaces old getProductById)
 * This maintains compatibility with your existing code
 */
async function getProductById(id) {
  try {
    const products = await api.getProducts();
    return products.find(product => product.id === id);
  } catch (error) {
    console.error('[API] Error getting product:', error);
    return null;
  }
}

/**
 * Load all products (replaces old products array)
 * Call this on page load to populate products
 */
let products = [];

async function loadProducts() {
  try {
    console.log('[API] Loading products from HikaShop...');
    products = await api.getProducts();
    console.log(`[API] Loaded ${products.length} products`);
    return products;
  } catch (error) {
    console.error('[API] Error loading products:', error);

    // Fallback: Use local products.js if API fails
    if (typeof window.products !== 'undefined') {
      console.warn('[API] Using fallback local products');
      products = window.products;
      return products;
    }

    return [];
  }
}

// API Status Check
async function checkAPIStatus() {
  try {
    const response = await fetch(`${API_BASE_URL}/products.php`);
    if (response.ok) {
      console.log('[API] ✓ API is reachable');
      return true;
    } else {
      console.error('[API] ✗ API returned error:', response.status);
      return false;
    }
  } catch (error) {
    console.error('[API] ✗ API is not reachable:', error.message);
    console.warn('[API] Make sure XAMPP is running and Joomla is accessible at:', API_BASE_URL);
    return false;
  }
}

// Auto-check API status on load
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => {
    checkAPIStatus();
  });
} else {
  checkAPIStatus();
}
