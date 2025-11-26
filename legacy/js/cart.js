// Cart Management System for RAVEN WEAPON AG
//
// This script drives the add‑to‑cart and cart sidebar behaviour on the site.
// Server-side only cart - all data from HikaShop API, no localStorage.
// compatibility with different pages/scripts and gracefully handles cases
// where those keys may not yet exist. When a user clicks on a product's
// cart button, the item is added to the cart, the item count bubble is
// updated, and the cart sidebar is opened. Quantities can be increased or
// decreased via the +/- controls within the cart. The script also
// automatically keeps the DOM up to date with the latest cart state.

class ShoppingCart {
  constructor() {
    // Load existing items from localStorage, falling back to an empty array
    this.items = [];  // Server-side only
    this.init();
  }

  /**
   * Map product name to local image path
   * HikaShop API returns invalid image URLs, so we map to local assets
   */
  getProductImage(productName) {
    const imageMap = {
      '.300 AAC RAVEN Kaliber Kit': 'assets/300_AAC_CALIBER_KIT.png',
      '.300 AAC RAVEN': 'assets/300_AAC_RAVEN.png',
      '7.62×39 RAVEN': 'assets/762x39_RAVEN.png',
      '7.62×39 RAVEN Caliber Kit': 'assets/762X39_CALIBER_KIT.png',
      '7.62x39 RAVEN': 'assets/762x39_RAVEN.png',
      '7.62x39 RAVEN Caliber Kit': 'assets/762X39_CALIBER_KIT.png',
      '.22 RAVEN': 'assets/22_RAVEN.png',
      '.22 RAVEN Caliber Kit': 'assets/22LR_CALIBER_KIT.png',
      '.22LR RAVEN Caliber Kit': 'assets/22LR_CALIBER_KIT.png',
      '9mm RAVEN': 'assets/9mm_RAVEN.png',
      '9mm RAVEN Caliber Kit': 'assets/9mm_CALIBER_KIT.png',
      '5.56 RAVEN': 'assets/556_RAVEN.png',
      '.223 RAVEN Caliber Kit': 'assets/223_CALIBER_KIT.png'
    };

    // Try exact match first
    if (imageMap[productName]) {
      return imageMap[productName];
    }

    // Try case-insensitive partial match
    const lowerName = productName.toLowerCase();
    for (const [key, value] of Object.entries(imageMap)) {
      if (lowerName.includes(key.toLowerCase()) || key.toLowerCase().includes(lowerName)) {
        return value;
      }
    }

    // Default placeholder
    return 'assets/placeholder.svg';
  }

  /**
   * Perform initialisation: update badge counts, attach event listeners and
   * render the existing cart state.
   */
  init() {
    this.updateCartCount();
    this.setupEventListeners();
    this.renderCart();

    // If user is logged in, sync cart from API
    this.syncWithAPI();
  }

  /**
   * Sync cart with HikaShop API if user is logged in
   */
  async syncWithAPI() {
    const waitForAuth = () => {
      return new Promise((resolve) => {
        const check = () => {
          if (window.authSystem && window.authSystem.initialized) {
            resolve();
          } else {
            setTimeout(check, 100);
          }
        };
        check();
      });
    };

    await waitForAuth();

    if (window.authSystem.isLoggedIn()) {
      console.log('[Cart] User logged in, syncing with HikaShop API...');
      await this.loadFromAPI();
    } else {
      console.log('[Cart] User not logged in - cart empty'); this.items = []; this.updateCartCount(); this.renderCart();
    }
  }

  /**
   * Load cart from HikaShop API
   */
  async loadFromAPI() {
    try {
      console.log('[Cart] Loading cart from HikaShop API...');
      const response = await fetch('http://localhost/ravenweaponwebapp/api/api/cart.php', {
        credentials: 'include'
      });
      const result = await response.json();

      if (result.success && result.data.items && result.data.items.length > 0) {
        console.log('[Cart] Loaded', result.data.items.length, 'items from API');
        this.items = result.data.items.map(item => {
          const quantity = item.quantity || 1;
          // API returns unit price in 'price' field (already per-unit, NOT line total)
          // Use priceWithTax for display if available, otherwise use price
          let unitPrice = 0;
          if (item.priceWithTax) {
            // Use price with tax for Swiss market (MwSt included)
            unitPrice = parseFloat(item.priceWithTax) || 0;
          } else if (item.price) {
            // Fallback to base price
            unitPrice = parseFloat(item.price) || 0;
          }
          // Get local image path instead of API image URL
          const productName = item.name || 'Unknown Product';
          const localImage = this.getProductImage(productName);

          return {
            id: item.productId || item.id,
            productId: item.productId || item.id,
            name: productName,
            price: unitPrice,
            quantity: quantity,
            image: localImage,
            variant: item.variant || 'Standard',
            category: item.category || item.categoryName || 'Produkt'
          };
        });
        this.updateCartCount();
        this.renderCart();
        console.log('[Cart] Cart synced with HikaShop');
        // Dispatch custom event so checkout page can update
        window.dispatchEvent(new CustomEvent('cartLoaded', { detail: { items: this.items } }));
        return true;
      }
      console.log('[Cart] No items in API cart');
      window.dispatchEvent(new CustomEvent('cartLoaded', { detail: { items: [] } }));
      return false;
    } catch (error) {
      console.error('[Cart] Failed to load from API:', error);
      window.dispatchEvent(new CustomEvent('cartLoaded', { detail: { items: [], error: true } }));
      return false;
    }
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
    //
    // NOTE: For dynamically loaded products (via dynamic-system.js), the event
    // listeners are attached there after products are rendered. This only handles
    // static product cards present in the initial HTML.
    const viewCartButtons = document.querySelectorAll('.cart-btn, .add-to-cart-btn');
    viewCartButtons.forEach(btn => {
      // Skip if button is inside productGrid (handled by dynamic-system.js)
      if (btn.closest('#productGrid')) return;

      btn.addEventListener('click', (e) => {
        e.preventDefault();
        const productCard = btn.closest('[data-name]');
        if (productCard) {
          const productId = productCard.dataset.productId;
          const product = {
            id: productId,
            productId: productId,
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
   * @param {Object} product object containing id, name, price, category, image, quantity
   */
  async addItem(product) {
    // Wait for auth and require login
    if (window.authSystem && !window.authSystem.initialized) {
      for (let i = 0; i < 20; i++) {
        await new Promise(r => setTimeout(r, 100));
        if (window.authSystem.initialized) break;
      }
    }
    if (!window.authSystem || !window.authSystem.isLoggedIn()) {
      alert('Bitte melden Sie sich an, um Artikel in den Warenkorb zu legen!');
      window.location.href = '/ravenweapon/login.html';
      return;
    }

    const quantityToAdd = product.quantity || 1;
    const existingItem = this.items.find(item => String(item.id) === String(product.id));
    if (existingItem) {
      existingItem.quantity += quantityToAdd;
    } else {
      this.items.push({ ...product, quantity: quantityToAdd });
    }
    this.updateCartCount();
    this.renderCart();
    this.openCart();
    const message = quantityToAdd > 1 ? `${quantityToAdd}x hinzugefügt` : 'Produkt hinzugefügt';
    this.showNotification(message);

    // Sync with HikaShop API
    // Use productId (numeric) instead of id (which may include variant suffix like "128-0")
    const apiProductId = product.productId || product.id;
    try {
      const response = await fetch('http://localhost/ravenweaponwebapp/api/api/cart.php', {
        method: 'POST', credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ productId: apiProductId, quantity: quantityToAdd })
      });
      const result = await response.json();
      console.log('[Cart] API add:', result.success ? 'success' : result.error);
    } catch (e) { console.error('[Cart] API error:', e); }
  }

  /**
   * Remove an item entirely from the cart by id.
   *
   * @param {String} productId the id of the item to remove
   */
  async removeItem(productId) {
    const idToRemove = String(productId);
    // Find the item first to get the API productId before removing
    const itemToRemove = this.items.find(item => String(item.id) === idToRemove);
    const apiProductId = itemToRemove ? (itemToRemove.productId || itemToRemove.id) : productId;

    this.items = this.items.filter(item => String(item.id) !== idToRemove);
    this.updateCartCount();
    this.renderCart();

    // Sync with HikaShop API if logged in
    if (window.authSystem && window.authSystem.isLoggedIn()) {
      try {
        const response = await fetch('http://localhost/ravenweaponwebapp/api/api/cart.php', {
          method: 'DELETE',
          credentials: 'include',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ productId: apiProductId })
        });
        const result = await response.json();
        console.log('[Cart] API remove:', result.success ? 'success' : result.error);
      } catch (e) { console.error('[Cart] API error:', e); }
    }
  }

  /**
   * Update the quantity of a particular item. Quantity is clamped to a
   * minimum of 1. If quantity is set to 0, the item is removed.
   *
   * @param {String} productId the id of the item to update
   * @param {Number} quantity the new quantity
   */
  async updateQuantity(productId, quantity) {
    const item = this.items.find(item => String(item.id) === String(productId));
    if (!item) return;
    if (quantity <= 0) {
      this.removeItem(productId);
      return;
    }
    item.quantity = Math.max(1, quantity);
    this.updateCartCount();
    this.renderCart();

    // Sync with HikaShop API if logged in
    // Use productId (numeric) instead of id (which may include variant suffix)
    const apiProductId = item.productId || item.id;
    if (window.authSystem && window.authSystem.isLoggedIn()) {
      try {
        const response = await fetch('http://localhost/ravenweaponwebapp/api/api/cart.php', {
          method: 'PUT', credentials: 'include',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ productId: apiProductId, quantity: quantity })
        });
        const result = await response.json();
        console.log('[Cart] API update:', result.success ? 'success' : result.error);
      } catch (e) { console.error('[Cart] API error:', e); }
    }
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
        <div class="relative w-20 h-20 flex-shrink-0">
          <span class="absolute -top-2 -left-2 z-10 bg-gray-900 text-white text-xs font-bold w-6 h-6 rounded-full flex items-center justify-center shadow-md border-2 border-white">${item.quantity}</span>
          <div class="w-full h-full bg-gray-100 rounded-md overflow-hidden">
            <img src="${item.image}" alt="${item.name}" class="w-full h-full object-contain p-2">
          </div>
        </div>
        <div class="flex-1 min-w-0">
          <div class="flex justify-between items-start gap-2 mb-2">
            <div class="flex-1">
              <p class="text-xs text-gray-500 uppercase tracking-wide">${item.category}</p>
              <h4 class="font-semibold text-gray-900 text-sm leading-tight">${item.name}</h4>
              ${item.variant && item.variant !== 'Standard' ? `<p class="text-xs text-gray-500 mt-1">${item.variant}</p>` : ''}
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