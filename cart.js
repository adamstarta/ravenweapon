// Cart Management System for RAVEN WEAPON AG
//
// This script drives the add‑to‑cart and cart sidebar behaviour on the site.
// It persists the cart to localStorage under several keys for backwards
// compatibility with different pages/scripts and gracefully handles cases
// where those keys may not yet exist. When a user clicks on a product's
// cart button, the item is added to the cart, the item count bubble is
// updated, and the cart sidebar is opened. Quantities can be increased or
// decreased via the +/- controls within the cart. The script also
// automatically keeps the DOM up to date with the latest cart state.

class ShoppingCart {
  constructor() {
    // Load existing items from localStorage, falling back to an empty array
    this.items = this.loadCart();
    this.init();
  }

  /**
   * Perform initialisation: update badge counts, attach event listeners and
   * render the existing cart state.
   */
  init() {
    this.updateCartCount();
    this.setupEventListeners();
    this.renderCart();
  }

  /**
   * Attach all event listeners needed for cart functionality. This includes
   * listening on product buttons to add items, toggling the cart overlay and
   * handling close interactions.
   */
  setupEventListeners() {
    // Cart toggle buttons (for sidebar cart). We look for any element with
    // aria‑label="Warenkorb" – these are the header cart icons on mobile and
    // desktop. On the cart page itself we don't open the sidebar.
    const cartButtons = document.querySelectorAll('[aria-label="Warenkorb"]');
    cartButtons.forEach(btn => {
      // On the dedicated cart page, disable pointer events entirely on the
      // cart icon so it appears inert and cannot be clicked. We still
      // attach the click handler to prevent default behaviour but we also
      // remove interactivity via CSS to convey to the user that it is
      // non‑interactive.
      if (window.location.pathname.includes('cart.html')) {
        btn.style.pointerEvents = 'none';
        btn.style.cursor = 'default';
      }
      btn.addEventListener('click', (e) => {
        // Prevent default link/button behaviour
        e.preventDefault();
        // Do nothing on the dedicated cart page (cart.html)
        if (!window.location.pathname.includes('cart.html')) {
          this.toggleCart();
        }
      });
    });

    // Close cart button – hides the sidebar overlay
    const closeCartBtn = document.getElementById('close-cart');
    if (closeCartBtn) {
      closeCartBtn.addEventListener('click', () => this.closeCart());
    }

    // Clicking the dimmed overlay also closes the cart
    const cartOverlay = document.getElementById('cart-overlay');
    if (cartOverlay) {
      cartOverlay.addEventListener('click', (e) => {
        if (e.target === cartOverlay) {
          this.closeCart();
        }
      });
    }

    // Product add‑to‑cart buttons. The `.cart-btn` class is used on the
    // little basket icon in each product card, and `.add-to-cart-btn` can be
    // applied elsewhere if needed. When clicked, we find the closest
    // `[data-name]` ancestor (the article element on product cards) and
    // construct an item object from its dataset and inner DOM structure.
    const viewCartButtons = document.querySelectorAll('.cart-btn, .add-to-cart-btn');
    viewCartButtons.forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        const productCard = btn.closest('[data-name]');
        if (productCard) {
          const product = {
            id: (productCard.dataset.name || '').replace(/\s+/g, '-').toLowerCase(),
            name: productCard.dataset.name || 'Produkt',
            price: parseFloat(productCard.dataset.price),
            category: productCard.querySelector('.cat-label')?.textContent?.trim() || 'Produkt',
            image: productCard.querySelector('img')?.src || ''
          };
          this.addItem(product);
        }
      });
    });
  }

  /**
   * Attempt to load the cart from any known localStorage key. This supports
   * legacy keys used by older scripts (`raven_cart`, `rw_cart`, `cart`) in
   * addition to the current canonical key (`ravenweapon_cart`). The first
   * non‑empty key wins.
   *
   * @returns {Array<Object>} the array of cart items
   */
  loadCart() {
    const keys = ['ravenweapon_cart', 'raven_cart', 'rw_cart', 'cart'];
    for (const key of keys) {
      try {
        const raw = localStorage.getItem(key);
        if (raw) {
          const parsed = JSON.parse(raw);
          if (Array.isArray(parsed)) {
            return parsed;
          }
        }
      } catch (e) {
        // ignore parse errors
      }
    }
    return [];
  }

  /**
   * Persist the current cart array to localStorage. To maximise
   * interoperability with other scripts, we write to all known keys. If
   * multiple tabs are open, this keeps them broadly in sync.
   */
  saveCart() {
    const payload = JSON.stringify(this.items);
    const keys = ['ravenweapon_cart', 'raven_cart', 'rw_cart', 'cart'];
    keys.forEach(k => {
      try {
        localStorage.setItem(k, payload);
      } catch (e) {
        // localStorage might be full or unavailable; ignore gracefully
      }
    });
  }

  /**
   * Add an item to the cart. If the item already exists (matching id), its
   * quantity is incremented. Afterwards the cart is saved, counts and
   * summaries are updated, and a brief notification is shown.
   *
   * @param {Object} product object containing id, name, price, category, image
   */
  addItem(product) {
    const existingItem = this.items.find(item => item.id === product.id);
    if (existingItem) {
      existingItem.quantity += 1;
    } else {
      this.items.push({ ...product, quantity: 1 });
    }
    this.saveCart();
    this.updateCartCount();
    this.renderCart();
    this.openCart();
    this.showNotification('Produkt hinzugefügt');
  }

  /**
   * Remove an item entirely from the cart by id.
   *
   * @param {String} productId the id of the item to remove
   */
  removeItem(productId) {
    this.items = this.items.filter(item => item.id !== productId);
    this.saveCart();
    this.updateCartCount();
    this.renderCart();
  }

  /**
   * Update the quantity of a particular item. Quantity is clamped to a
   * minimum of 1. If quantity is set to 0, the item is removed.
   *
   * @param {String} productId the id of the item to update
   * @param {Number} quantity the new quantity
   */
  updateQuantity(productId, quantity) {
    const item = this.items.find(item => item.id === productId);
    if (!item) return;
    if (quantity <= 0) {
      this.removeItem(productId);
      return;
    }
    item.quantity = Math.max(1, quantity);
    this.saveCart();
    this.updateCartCount();
    this.renderCart();
  }

  /**
   * Compute the total price of the cart. Sums price * quantity for all
   * entries.
   *
   * @returns {Number}
   */
  getTotal() {
    return this.items.reduce((sum, item) => sum + (item.price * item.quantity), 0);
  }

  /**
   * Compute the total number of items across all line items.
   *
   * @returns {Number}
   */
  getTotalItems() {
    return this.items.reduce((sum, item) => sum + item.quantity, 0);
  }

  /**
   * Update the numerical badge(s) that show the number of items in the cart.
   * If there are no items, hide the badges.
   */
  updateCartCount() {
    const badges = document.querySelectorAll('.cart-count');
    const count = this.getTotalItems();
    badges.forEach(badge => {
      badge.textContent = String(count);
      badge.style.display = count > 0 ? 'flex' : 'none';
    });
  }

  /**
   * Render the cart contents into the sidebar. This function updates the
   * `cart-items` container with each item, toggles visibility of the
   * `cart-empty` and `cart-content` sections depending on whether the cart
   * contains anything, and sets the total amount displayed.
   */
  renderCart() {
    const cartItems = document.getElementById('cart-items');
    const cartEmpty = document.getElementById('cart-empty');
    const cartContent = document.getElementById('cart-content');
    const cartTotal = document.getElementById('cart-total');
    if (!cartItems) return;
    // Show empty state if no items
    if (this.items.length === 0) {
      if (cartEmpty) cartEmpty.classList.remove('hidden');
      if (cartContent) cartContent.classList.add('hidden');
      // Reset total display
      if (cartTotal) cartTotal.textContent = 'CHF 0.00';
      return;
    }
    // Otherwise hide empty state and show content
    if (cartEmpty) cartEmpty.classList.add('hidden');
    if (cartContent) cartContent.classList.remove('hidden');
    // Build HTML for each item in sidebar
    cartItems.innerHTML = this.items.map(item => {
      return `
      <div class="cart-item flex gap-4 py-4 border-b border-gray-200">
        <div class="w-20 h-20 flex-shrink-0 bg-gray-100 rounded-md overflow-hidden">
          <img src="${item.image}" alt="${item.name}" class="w-full h-full object-contain p-2">
        </div>
        <div class="flex-1 min-w-0">
          <div class="flex justify-between items-start gap-2 mb-2">
            <div class="flex-1">
              <p class="text-xs text-gray-500 uppercase tracking-wide">${item.category}</p>
              <h4 class="font-semibold text-gray-900 text-sm leading-tight">${item.name}</h4>
            </div>
            <button onclick="cart.removeItem('${item.id}')" class="text-gray-400 hover:text-red-600 transition-colors p-1" aria-label="Entfernen">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
          <div class="flex items-center justify-between">
            <div class="price text-base font-semibold" style="color: #E53935;">CHF ${item.price.toFixed(2)}</div>
            <div class="flex items-center gap-2">
              <button onclick="cart.updateQuantity('${item.id}', ${item.quantity - 1})" class="w-8 h-8 flex items-center justify-center border border-gray-300 rounded-md hover:bg-gray-50 transition-colors" aria-label="Verringern">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4" />
                </svg>
              </button>
              <span class="w-10 text-center font-medium">${item.quantity}</span>
              <button onclick="cart.updateQuantity('${item.id}', ${item.quantity + 1})" class="w-8 h-8 flex items-center justify-center border border-gray-300 rounded-md hover:bg-gray-50 transition-colors" aria-label="Erhöhen">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
              </button>
            </div>
          </div>
        </div>
      </div>`;
    }).join('');
    // Update the total amount in sidebar
    const total = this.getTotal();
    if (cartTotal) {
      cartTotal.textContent = `CHF ${total.toFixed(2)}`;
    }
  }

  /**
   * Show or hide the cart overlay. If the cart is open, calling this will
   * close it, and vice versa.
   */
  toggleCart() {
    const cartSidebar = document.getElementById('cart-sidebar');
    const cartOverlay = document.getElementById('cart-overlay');
    if (!cartSidebar || !cartOverlay) return;
    const isOpen = cartSidebar.classList.contains('translate-x-0');
    if (isOpen) {
      this.closeCart();
    } else {
      this.openCart();
    }
  }

  /**
   * Open the cart overlay and prevent the body from scrolling.
   */
  openCart() {
    const cartSidebar = document.getElementById('cart-sidebar');
    const cartOverlay = document.getElementById('cart-overlay');
    if (!cartSidebar || !cartOverlay) return;
    cartOverlay.classList.remove('hidden');
    cartOverlay.classList.add('opacity-0');
    cartSidebar.classList.remove('translate-x-full');
    cartSidebar.classList.add('translate-x-0');
    // Start fade‑in animation for overlay
    setTimeout(() => {
      cartOverlay.classList.remove('opacity-0');
      cartOverlay.classList.add('opacity-100');
    }, 10);
    document.body.style.overflow = 'hidden';
  }

  /**
   * Close the cart overlay and restore body scrolling.
   */
  closeCart() {
    const cartSidebar = document.getElementById('cart-sidebar');
    const cartOverlay = document.getElementById('cart-overlay');
    if (!cartSidebar || !cartOverlay) return;
    cartOverlay.classList.remove('opacity-100');
    cartOverlay.classList.add('opacity-0');
    cartSidebar.classList.remove('translate-x-0');
    cartSidebar.classList.add('translate-x-full');
    // After the fade‑out animation completes, hide the overlay
    setTimeout(() => {
      cartOverlay.classList.add('hidden');
    }, 300);
    document.body.style.overflow = '';
  }

  /**
   * Display a brief notification message in the top right corner. The
   * notification animates in and out automatically after a few seconds.
   *
   * @param {String} message the text to display
   */
  showNotification(message) {
    const notification = document.createElement('div');
    notification.className = 'fixed top-4 right-4 bg-green-600 text-white px-6 py-3 rounded-lg shadow-lg z-[9999] transition-all transform translate-x-0';
    notification.style.animation = 'slideInRight 0.3s ease-out';
    notification.innerHTML = `
      <div class="flex items-center gap-2">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
        </svg>
        <span class="font-medium">${message}</span>
      </div>`;
    document.body.appendChild(notification);
    // Animate out after 3 seconds
    setTimeout(() => {
      notification.style.animation = 'slideOutRight 0.3s ease-in';
      setTimeout(() => notification.remove(), 300);
    }, 3000);
  }

  /**
   * Completely empty the cart. Useful for future features such as a
   * "Clear Cart" button.
   */
  clearCart() {
    this.items = [];
    this.saveCart();
    this.updateCartCount();
    this.renderCart();
  }

  /**
   * Navigate to the cart page. The logic inspects the current path to
   * determine how many levels up to traverse before pointing at `cart.html`.
   */
  viewCart() {
    const currentPath = window.location.pathname;
    let cartPath = 'cart.html';
    if (currentPath.includes('/shop/category/')) {
      cartPath = '../../cart.html';
    } else if (currentPath.includes('/shop/')) {
      cartPath = '../cart.html';
    }
    window.location.href = cartPath;
  }

  /**
   * Render the cart page (cart.html) contents. This is a separate function
   * because the markup for the full cart page is different from the sidebar.
   * It populates the `cart-items` container and toggles visibility of
   * `empty-cart` and `cart-summary` sections. It also displays a
   * free shipping notice when appropriate.
   */
  renderCartPage() {
    const cartItems = document.getElementById('cart-items');
    const emptyCart = document.getElementById('empty-cart');
    const cartSummary = document.getElementById('cart-summary');
    const subtotal = document.getElementById('subtotal');
    if (!cartItems) return;
    if (this.items.length === 0) {
      if (emptyCart) emptyCart.classList.remove('hidden');
      if (cartSummary) cartSummary.classList.add('hidden');
      return;
    }
    if (emptyCart) emptyCart.classList.add('hidden');
    if (cartSummary) cartSummary.classList.remove('hidden');
    // Build markup for each item
    cartItems.innerHTML = this.items.map(item => {
      return `
      <div class="flex items-center gap-4 py-4 px-4 border-b border-gray-200">
        <div class="flex items-center gap-3 flex-1">
          <div class="w-12 h-12 flex-shrink-0 bg-gray-100 rounded overflow-hidden">
            <img src="${item.image}" alt="${item.name}" class="w-full h-full object-contain p-1">
          </div>
          <div class="flex-1 min-w-0">
            <h3 class="font-medium text-gray-900 text-sm leading-tight">${item.name}</h3>
            <p class="text-xs text-gray-500 mt-1">Variante: 30g - Neutral</p>
          </div>
        </div>
        <div class="flex items-center gap-2">
          <button onclick="cart.updateQuantity('${item.id}', ${item.quantity - 1})" class="w-6 h-6 flex items-center justify-center border border-gray-300 hover:bg-gray-50 ${item.quantity <= 1 ? 'opacity-50 cursor-not-allowed' : ''}" ${item.quantity <= 1 ? 'disabled' : ''} aria-label="Decrease quantity">−</button>
          <span class="w-8 text-center text-sm">${item.quantity}</span>
          <button onclick="cart.updateQuantity('${item.id}', ${item.quantity + 1})" class="w-6 h-6 flex items-center justify-center border border-gray-300 hover:bg-gray-50" aria-label="Increase quantity">+</button>
        </div>
        <div class="flex items-center gap-3">
          <span class="font-medium text-gray-900 text-sm w-16 text-right">CHF ${(item.price * item.quantity).toFixed(2)}</span>
          <button onclick="cart.removeItem('${item.id}')" class="w-6 h-6 flex items-center justify-center text-gray-400 hover:text-red-600" aria-label="Remove item">×</button>
        </div>
      </div>`;
    }).join('');
    // Update subtotal in cart page
    const total = this.getTotal();
    if (subtotal) {
      subtotal.textContent = `CHF ${total.toFixed(2)}`;
    }
    // Free shipping logic: show message if total is below threshold
    const freeShippingNotice = document.getElementById('free-shipping-notice');
    const remainingAmount = document.getElementById('remaining-amount');
    const freeShippingThreshold = 100;
    if (total < freeShippingThreshold && freeShippingNotice && remainingAmount) {
      const remaining = (freeShippingThreshold - total).toFixed(2);
      remainingAmount.textContent = `CHF ${remaining}`;
      freeShippingNotice.classList.remove('hidden');
    } else if (freeShippingNotice) {
      freeShippingNotice.classList.add('hidden');
    }
  }
}

// Animation keyframes for notifications. We insert these once when the
// script is loaded to avoid duplicating styles for each notification.
(() => {
  const style = document.createElement('style');
  style.textContent = `
    @keyframes slideInRight {
      from { transform: translateX(100%); opacity: 0; }
      to   { transform: translateX(0); opacity: 1; }
    }
    @keyframes slideOutRight {
      from { transform: translateX(0); opacity: 1; }
      to   { transform: translateX(100%); opacity: 0; }
    }
  `;
  document.head.appendChild(style);
})();

// Initialise the global cart instance once the DOM is ready. We expose the
// instance on `window.cart` so that inline onclick handlers can call
// cart.updateQuantity(), cart.removeItem(), etc.
let cart;
document.addEventListener('DOMContentLoaded', () => {
  cart = new ShoppingCart();
  window.cart = cart;
  // For debugging: log current cart items on initialisation
  console.log('Cart initialized with items:', cart.items);
});