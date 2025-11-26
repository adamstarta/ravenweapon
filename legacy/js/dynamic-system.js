/**
 * RavenWeapon - Complete Dynamic System
 * This script makes EVERYTHING dynamic: navbar, products, cart
 */

// =============================================================================
// SECURITY UTILITIES
// =============================================================================

/**
 * Sanitize a string to prevent XSS attacks by escaping HTML entities.
 * @param {string} str - The string to sanitize
 * @returns {string} - The sanitized string safe for HTML insertion
 */
function sanitizeHTML(str) {
  if (str === null || str === undefined) return '';
  const temp = document.createElement('div');
  temp.textContent = String(str);
  return temp.innerHTML;
}

/**
 * Sanitize a string for use as an HTML attribute value.
 * @param {string} str - The string to sanitize
 * @returns {string} - The sanitized string safe for attribute insertion
 */
function sanitizeAttribute(str) {
  if (str === null || str === undefined) return '';
  return String(str)
    .replace(/&/g, '&amp;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#x27;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;');
}

/**
 * Validate and sanitize a URL to prevent javascript: and data: injection.
 * @param {string} url - The URL to validate
 * @returns {string} - The safe URL or a placeholder image path
 */
function sanitizeURL(url) {
  if (!url || typeof url !== 'string') return 'assets/placeholder.png';
  const trimmed = url.trim().toLowerCase();
  // Block javascript:, data:, and vbscript: URLs
  if (trimmed.startsWith('javascript:') ||
      trimmed.startsWith('data:') ||
      trimmed.startsWith('vbscript:')) {
    return 'assets/placeholder.png';
  }
  return url;
}

/**
 * Sanitize a product ID for safe use in URLs and data attributes.
 * @param {string|number} id - The product ID
 * @returns {string} - The sanitized ID
 */
function sanitizeProductId(id) {
  if (id === null || id === undefined) return '';
  // Only allow alphanumeric characters, hyphens, and underscores
  return String(id).replace(/[^a-zA-Z0-9\-_]/g, '');
}

// =============================================================================
// API CONFIGURATION
// =============================================================================

const RAVENWEAPON_API = {
  // NOTE: In production, change to HTTPS and your actual domain
  base: 'http://localhost/ravenweaponwebapp/api',
  products: '/products-direct.php',
  categories: '/categories.php',
  cart: '/cart.php'
};

/**
 * Initialize Dynamic System
 */
document.addEventListener('DOMContentLoaded', async () => {
  console.log('[RavenWeapon] Initializing dynamic system...');

  try {
    // 1. Load categories for navbar
    await loadDynamicNavbar();

    // 2. Load products for homepage
    await loadDynamicProducts();

    // 3. Initialize cart
    initializeCart();

    console.log('[RavenWeapon] ✓ System initialized successfully');

  } catch (error) {
    console.error('[RavenWeapon] System initialization failed:', error);
  }
});

/**
 * 1. DYNAMIC NAVBAR
 * Attach click handlers to existing navbar links
 */
async function loadDynamicNavbar() {
  console.log('[Navbar] Attaching handlers to existing navbar...');

  try {
    // Map your existing navbar text to HikaShop categories
    const navMapping = {
      'homepage': 'all',
      'startseite': 'all',
      'home': 'all',
      'weapons': 'RAVEN COMPLETE',
      'weapon accessories': 'Accessories',
      'ammunition': 'RAVEN CALIBER KITS',
      'equipment': 'Equipment'
    };

    // Find all navbar links
    const navLinks = document.querySelectorAll('nav a, #mobile-menu a');

    navLinks.forEach(link => {
      const linkText = link.textContent.trim().toLowerCase();

      // Check if this link matches a category
      Object.keys(navMapping).forEach(navKey => {
        if (linkText.includes(navKey)) {
          const categoryName = navMapping[navKey];

          // Attach click handler
          link.addEventListener('click', (e) => {
            e.preventDefault();

            // If "all" (homepage), show limited 6 products
            if (categoryName === 'all') {
              const limitedProducts = getLimitedProductsForHomepage(window.ravenProducts || []);
              renderProducts(limitedProducts);
            } else {
              filterProductsByCategory(categoryName);
            }

            // Close mobile menu if open
            const mobileMenu = document.getElementById('mobile-menu');
            if (mobileMenu) {
              mobileMenu.classList.add('hidden');
            }

            // Scroll to products (only if not homepage)
            if (categoryName !== 'all') {
              setTimeout(() => {
                document.getElementById('produkte')?.scrollIntoView({ behavior: 'smooth' });
              }, 100);
            }
          });

          console.log(`[Navbar] Attached handler: "${linkText}" → ${categoryName}`);
        }
      });
    });

    console.log('[Navbar] ✓ Handlers attached successfully');

  } catch (error) {
    console.error('[Navbar] Failed to attach handlers:', error);
  }
}

/**
 * 2. DYNAMIC PRODUCTS
 * Loads products from HikaShop and renders them
 */
async function loadDynamicProducts() {
  const productGrid = document.getElementById('productGrid');
  if (!productGrid) {
    console.log('[Products] No product grid found on this page');
    return;
  }

  console.log('[Products] Loading from API...');

  try {
    const response = await fetch(RAVENWEAPON_API.base + RAVENWEAPON_API.products);
    const data = await response.json();

    if (!data.success) {
      throw new Error(data.error);
    }

    window.ravenProducts = data.data; // Store globally for filtering
    console.log(`[Products] Loaded ${data.data.length} products`);

    // For HOMEPAGE: Show limited products - 3 from each main category
    const limitedProducts = getLimitedProductsForHomepage(data.data);
    console.log(`[Products] Showing ${limitedProducts.length} products on homepage`);

    renderProducts(limitedProducts);

  } catch (error) {
    // Log detailed error for debugging (server-side logging preferred in production)
    console.error('[Products] Failed to load:', error);

    // Show generic error message to users (don't expose internal error details)
    productGrid.innerHTML = `
      <div class="col-span-full text-center py-12">
        <p class="text-red-600 font-bold mb-2">Failed to load products</p>
        <p class="text-gray-600 text-sm">Unable to connect to the product catalog. Please try again later.</p>
        <p class="text-gray-500 text-xs mt-2">If the problem persists, contact support.</p>
        <button onclick="location.reload()" class="mt-4 px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
          Retry
        </button>
      </div>
    `;
  }
}

/**
 * Get product image - maps product names to local assets
 * Falls back to HikaShop image or placeholder
 */
function getProductImage(product) {
  const name = (product.name || '').toLowerCase();

  // Check for caliber/kaliber kits FIRST (more specific matches)
  if (name.includes('caliber kit') || name.includes('kaliber kit')) {
    if (name.includes('.223') || name.includes('223')) return 'assets/223_CALIBER_KIT.png';
    if (name.includes('300 aac') || name.includes('.300 aac')) return 'assets/300_AAC_CALIBER_KIT.png';
    if (name.includes('9mm')) return 'assets/9mm_CALIBER_KIT.png';
    if (name.includes('.22') || name.includes('22lr') || name.includes('.22lr')) return 'assets/22LR_CALIBER_KIT.png';
    if (name.includes('7.62x39') || name.includes('7.62×39')) return 'assets/762X39_CALIBER_KIT.png';
  }

  // Then check for RAVEN weapons (only if NOT a kit)
  if (!name.includes('kit')) {
    if (name.includes('.223') || name.includes('223 raven') || name.includes('5.56')) return 'assets/556_RAVEN.png';
    if (name.includes('300 aac') || name.includes('.300 aac') || name.includes('300 blackout')) return 'assets/300_AAC_RAVEN.png';
    if (name.includes('9mm')) return 'assets/9mm_RAVEN.png';
    if (name.includes('.22') || name.includes('22 raven') || name.includes('.22lr')) return 'assets/22_RAVEN.png';
    if (name.includes('7.62x39') || name.includes('7.62×39')) return 'assets/762x39_RAVEN.png';
  }

  // Fallback to HikaShop image if available
  if (product.variants?.[0]?.image) {
    return sanitizeURL(product.variants[0].image);
  }

  // Final fallback
  return 'assets/placeholder.png';
}

/**
 * Get limited products for homepage display
 * Returns 3 weapons and 3 caliber kits = 6 products total
 */
function getLimitedProductsForHomepage(allProducts) {
  // Filter weapons (RAVEN COMPLETE or products with "RAVEN" in name but not "KIT")
  const weapons = allProducts.filter(p => {
    const cat = (p.category || '').toLowerCase();
    const name = (p.name || '').toLowerCase();
    return cat.includes('complete') || cat.includes('waffe') ||
           (name.includes('raven') && !name.includes('kit'));
  }).slice(0, 3);

  // Filter caliber kits
  const calibers = allProducts.filter(p => {
    const cat = (p.category || '').toLowerCase();
    const name = (p.name || '').toLowerCase();
    return cat.includes('caliber') || cat.includes('kit') ||
           name.includes('caliber kit');
  }).slice(0, 3);

  // Combine: 3 weapons + 3 calibers = 6 products total
  const combined = [...weapons, ...calibers];

  // If filtering didn't work well, just take first 6
  if (combined.length < 6 && allProducts.length >= 6) {
    return allProducts.slice(0, 6);
  }

  return combined;
}

function renderProducts(products) {
  const productGrid = document.getElementById('productGrid');
  if (!productGrid) return;

  productGrid.innerHTML = '';

  if (products.length === 0) {
    // NOTE: Admin panel link removed for security - should not expose admin URLs to users
    productGrid.innerHTML = `
      <div class="col-span-full text-center py-12">
        <p class="text-gray-600">No products available at this time.</p>
        <p class="text-gray-500 text-sm mt-2">Please check back later.</p>
      </div>
    `;
    return;
  }

  products.forEach(product => {
    const card = createProductCard(product);
    productGrid.appendChild(card);
  });

  // Attach cart event listeners to the new product buttons
  // We need to wait for cart.js to initialize
  const attachCartListeners = () => {
    if (window.cart && typeof window.cart.addItem === 'function') {
      const productButtons = productGrid.querySelectorAll('.cart-btn');
      productButtons.forEach(btn => {
        // Remove any existing listeners to prevent duplicates
        const newBtn = btn.cloneNode(true);
        btn.parentNode.replaceChild(newBtn, btn);

        newBtn.addEventListener('click', (e) => {
          e.preventDefault();
          e.stopPropagation(); // Prevent card click from firing
          const productCard = newBtn.closest('[data-name]');
          if (productCard) {
            // Use the HikaShop product ID from data-product-id attribute
            const productId = productCard.dataset.productId;
            const product = {
              id: productId,
              productId: productId, // Also set productId for API compatibility
              name: productCard.dataset.name || 'Produkt',
              price: parseFloat(productCard.dataset.price),
              category: productCard.querySelector('.cat-label')?.textContent?.trim() || 'Produkt',
              image: productCard.querySelector('img')?.src || ''
            };
            console.log('[Products] Adding to cart:', product);
            window.cart.addItem(product);
          }
        });
      });
      console.log('[Products] Attached cart listeners to', productButtons.length, 'product buttons');
    } else {
      // Cart not ready yet, retry
      setTimeout(attachCartListeners, 100);
    }
  };

  attachCartListeners();
}

function createProductCard(product) {
  const card = document.createElement('article');
  card.className = 'group product-card transition';

  // Sanitize all product data before use to prevent XSS
  const safeProductId = sanitizeProductId(product.id);
  const safeName = sanitizeHTML(product.name);
  const safeNameAttr = sanitizeAttribute(product.name);
  const safeCategory = sanitizeAttribute(product.category || '');
  const safePrice = parseFloat(product.basePrice) || 0;

  // Get image - use local assets based on product name for reliability
  const safeImage = getProductImage(product);

  // Set data attributes to match original structure (using sanitized values)
  card.setAttribute('data-category', safeCategory.toLowerCase());
  card.setAttribute('data-name', safeNameAttr);
  card.setAttribute('data-price', safePrice.toString());
  card.setAttribute('data-product-id', safeProductId);

  const priceFormatted = safePrice > 0 ? Math.round(safePrice) + '.–' : '0.–';

  // Map category to German labels
  const categoryLabels = {
    'rifles': 'Waffe',
    'calibers': 'Zubehör',
    'accessories': 'Zubehör',
    'equipment': 'Ausrüstung',
    'raven complete': 'Waffe',
    'raven caliber kits': 'Zubehör'
  };
  const categoryLabel = sanitizeHTML(categoryLabels[safeCategory.toLowerCase()] || product.category || 'Produkt');

  card.innerHTML = `
    <div class="bg-white flex items-center justify-center product-img-wrap">
      <img src="${safeImage}" alt="${safeNameAttr}" class="h-full w-full object-contain p-3 transition-transform duration-300 ease-out group-hover:scale-105" loading="lazy" onerror="this.src='assets/placeholder.png'"/>
    </div>
    <div class="px-4 py-3">
      <p class="cat-label text-[12px] leading-[16px] text-gray-500 mb-2">${categoryLabel}</p>

      <div class="flex items-start justify-between gap-3">
        <div class="flex-1">
          <div class="price">${priceFormatted}</div>
          <div class="prod-title text-base text-gray-800 mt-1">
            ${safeName}
          </div>
        </div>
        <div class="flex flex-col gap-1 items-center -mt-2">
          ${product.available ? `
          <span class="avail-dot" title="Verfügbar">
            <svg viewBox="0 0 24 24" fill="none" class="w-5 h-5" aria-hidden="true">
              <path d="M5 13l4 4L19 7" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </span>` : ''}
          <button class="cart-btn inline-flex items-center justify-center rounded-md hover:bg-gray-100 text-gray-800" aria-label="In den Warenkorb">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" fill="currentColor" aria-hidden="true"><path d="M0 72C0 58.7 10.7 48 24 48L69.3 48C96.4 48 119.6 67.4 124.4 94L124.8 96L524.7 96C549.8 96 568.7 118.9 564 143.6L537.6 280.6C529.6 322 493.4 352 451.2 352L171.4 352L176.5 380.3C178.6 391.7 188.5 400 200.1 400L456 400C469.3 400 480 410.7 480 424C480 437.3 469.3 448 456 448L200.1 448C165.3 448 135.5 423.1 129.3 388.9L77.2 102.6C76.5 98.8 73.2 96 69.3 96L24 96C10.7 96 0 85.3 0 72zM162.6 304L451.2 304C470.4 304 486.9 290.4 490.5 271.6L514.9 144L133.5 144L162.6 304zM208 480C234.5 480 256 501.5 256 528C256 554.5 234.5 576 208 576C181.5 576 160 554.5 160 528C160 501.5 181.5 480 208 480zM432 480C458.5 480 480 501.5 480 528C480 554.5 458.5 576 432 576C405.5 576 384 554.5 384 528C384 501.5 405.5 480 432 480z"/></svg>
          </button>
        </div>
      </div>
    </div>
  `;

  // Add click handler to open product page (but not when clicking cart button)
  // Use sanitized product ID in URL
  card.addEventListener('click', (e) => {
    // Don't navigate if clicking the cart button
    if (!e.target.closest('.cart-btn')) {
      window.location.href = `product.html?id=${encodeURIComponent(product.id)}`;
    }
  });

  // Cart button clicks are handled by cart.js automatically

  return card;
}

/**
 * Filter products by category
 */
function filterProductsByCategory(categoryName) {
  if (!window.ravenProducts) return;

  const filtered = window.ravenProducts.filter(p =>
    p.category.toLowerCase() === categoryName.toLowerCase()
  );

  console.log(`[Filter] Showing ${filtered.length} products in ${categoryName}`);
  renderProducts(filtered);

  // Scroll to products section
  document.getElementById('produkte')?.scrollIntoView({ behavior: 'smooth' });
}

/**
 * 3. CART SYSTEM
 * Cart functionality is fully handled by cart.js
 * No custom cart code needed here - cart.js automatically detects .cart-btn elements
 */
function initializeCart() {
  console.log('[Cart] Cart system managed by cart.js');
  // cart.js handles all cart functionality automatically:
  // - Detects all .cart-btn elements
  // - Reads data-name, data-price from parent [data-name] element
  // - Opens sidebar, updates count, saves to localStorage
}

// Export for use in other scripts
window.RavenWeapon = {
  filterProductsByCategory
};

console.log('[RavenWeapon] Dynamic system loaded');
