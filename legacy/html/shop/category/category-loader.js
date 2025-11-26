/**
 * Category Page Dynamic Loader
 * Loads products from HikaShop API and displays them by category
 */

const RAVENWEAPON_API = {
  base: 'http://localhost/ravenweaponwebapp/api',
  products: '/products-direct.php'
};

// Category mapping - Match actual HikaShop categories
const CATEGORY_MAP = {
  'weapon': 'RAVEN COMPLETE',
  'ammunition': 'RAVEN CALIBER KITS'
};

// Get category from current page URL
function getCurrentCategory() {
  const path = window.location.pathname;
  if (path.includes('weapon.html')) return 'RAVEN COMPLETE';
  if (path.includes('ammunition.html')) return 'RAVEN CALIBER KITS';
  return 'all';
}

// Load and display products for current category
async function loadCategoryProducts() {
  const productGrid = document.getElementById('productGrid');
  if (!productGrid) {
    console.log('[Category] No product grid found');
    return;
  }

  const category = getCurrentCategory();
  console.log(`[Category] Loading products for: ${category}`);

  try {
    const response = await fetch(RAVENWEAPON_API.base + RAVENWEAPON_API.products);
    const data = await response.json();

    if (!data.success) {
      throw new Error(data.error);
    }

    // Filter products by category
    let products = data.data;
    console.log(`[Category] Total products from API: ${products.length}`);
    console.log('[Category] All categories:', products.map(p => `${p.name} -> ${p.category}`));

    if (category !== 'all') {
      products = products.filter(p => p.category === category);
    }

    console.log(`[Category] Filtered ${products.length} products for category: ${category}`);

    // Update filter counts based on loaded products
    updateFilterCounts(products);

    // Clear grid and render products
    productGrid.innerHTML = '';

    if (products.length === 0) {
      productGrid.innerHTML = `
        <div class="col-span-full text-center py-12">
          <p class="text-gray-600 text-lg mb-4">Keine Produkte in dieser Kategorie gefunden.</p>
          <a href="../../index.html" class="inline-block px-6 py-3 bg-gradient-to-r from-yellow-300 via-amber-400 to-yellow-500 text-black font-semibold rounded-lg">
            Zurück zur Startseite
          </a>
        </div>
      `;
      return;
    }

    products.forEach(product => {
      const card = createProductCard(product);
      productGrid.appendChild(card);
    });

    // Update product count
    const productCountElement = document.getElementById('product-count');
    if (productCountElement) {
      productCountElement.textContent = products.length;
    }

    // Attach cart event listeners to the new product buttons only
    if (window.cart && typeof window.cart.addItem === 'function') {
      const productButtons = productGrid.querySelectorAll('.cart-btn');
      productButtons.forEach(btn => {
        btn.addEventListener('click', (e) => {
          e.preventDefault();
          const productCard = btn.closest('[data-name]');
          if (productCard) {
            const product = {
              id: productCard.dataset.productId || (productCard.dataset.name || '').replace(/\s+/g, '-').toLowerCase(),
              name: productCard.dataset.name || 'Produkt',
              price: parseFloat(productCard.dataset.price),
              category: productCard.querySelector('.cat-label')?.textContent?.trim() || 'Produkt',
              image: productCard.querySelector('img')?.src || ''
            };
            window.cart.addItem(product);
          }
        });
      });
      console.log('[Category] Attached cart listeners to', productButtons.length, 'product buttons');
    }

  } catch (error) {
    console.error('[Category] Error loading products:', error);
    productGrid.innerHTML = `
      <div class="col-span-full text-center py-12">
        <p class="text-red-600 font-bold mb-2">⚠️ Fehler beim Laden der Produkte</p>
        <p class="text-gray-600 text-sm">${error.message}</p>
        <button onclick="location.reload()" class="mt-4 px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
          🔄 Erneut versuchen
        </button>
      </div>
    `;
  }
}

// Create product card (same as homepage)
function createProductCard(product) {
  const card = document.createElement('article');
  card.className = 'group product-card transition';

  // Detect caliber from product name
  const name = product.name.toLowerCase();
  let caliberValue = 'other';
  if (name.includes('300') || name.includes('aac')) caliberValue = '300blk';
  else if (name.includes('5.56') || name.includes('.223') || name.includes('223')) caliberValue = '5.56';
  else if (name.includes('7.62') || name.includes('762')) caliberValue = '7.62x39';
  else if (name.includes('9mm')) caliberValue = '9mm';
  else if (name.includes('.22') || name.includes('22')) caliberValue = '.22lr';

  // Detect category type (rifle or caliber-kit)
  const categorySource = product.category || '';
  const mainCategory = categorySource.toLowerCase();
  let categoryType = 'other';
  if (mainCategory.includes('weapon') || mainCategory.includes('waffe') || mainCategory.includes('rifle') || mainCategory.includes('complete')) {
    categoryType = 'rifle';
  } else if (mainCategory.includes('caliber') || mainCategory.includes('kaliber') || mainCategory.includes('kit')) {
    categoryType = 'caliber-kit';
  }

  card.setAttribute('data-category', categoryType);
  card.setAttribute('data-name', product.name);
  card.setAttribute('data-price', product.basePrice.toString());
  card.setAttribute('data-product-id', product.id);
  card.setAttribute('data-caliber', caliberValue);
  card.setAttribute('data-availability', product.available ? 'in-stock' : 'out-of-stock');

  // Use mainImage (parent product image) for listing, fallback to first variant image
  const image = product.mainImage || product.variants[0]?.image || '../../assets/placeholder.png';
  const priceFormatted = product.basePrice > 0 ? Math.round(product.basePrice) + '.–' : '0.–';

  const categoryLabels = {
    'rifles': 'Waffe',
    'calibers': 'Zubehör',
    'accessories': 'Zubehör',
    'equipment': 'Ausrüstung'
  };
  const categoryLabel = categoryLabels[product.category.toLowerCase()] || product.category;

  card.innerHTML = `
    <div class="bg-white flex items-center justify-center product-img-wrap">
      <img src="${image}" alt="${product.name}" class="h-full w-full object-contain p-3 transition-transform duration-300 ease-out group-hover:scale-105" loading="lazy" onerror="this.src='../../assets/placeholder.png'"/>
    </div>
    <div class="px-4 py-3">
      <p class="cat-label text-[12px] leading-[16px] text-gray-500 mb-2">${categoryLabel}</p>

      <div class="flex items-start justify-between gap-3">
        <div class="flex-1">
          <div class="price">${priceFormatted}</div>
          <div class="prod-title text-base text-gray-800 mt-1">
            ${product.name}
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

  // Click to open product page
  card.addEventListener('click', (e) => {
    if (!e.target.closest('.cart-btn')) {
      window.location.href = `../../product.html?id=${product.id}`;
    }
  });

  return card;
}

// Build dynamic filters based on page type and loaded products
function buildDynamicFilters(products) {
  const category = getCurrentCategory();
  const isWeaponPage = category === 'RAVEN COMPLETE';
  const isCaliberPage = category === 'RAVEN CALIBER KITS';

  console.log('[Category] buildDynamicFilters called');
  console.log('[Category] Current category:', category);
  console.log('[Category] Is weapon page:', isWeaponPage);
  console.log('[Category] Is caliber page:', isCaliberPage);
  console.log('[Category] Products to filter:', products);

  if (isWeaponPage) {
    // Build weapon model filters
    console.log('[Category] Building weapon filters...');
    buildWeaponFilters(products);
  } else if (isCaliberPage) {
    // Build caliber filters
    console.log('[Category] Building caliber filters...');
    buildCaliberFilters(products);
  } else {
    console.warn('[Category] Unknown page type, category:', category);
  }

  // Update availability counts
  let inStock = 0;
  let notAvailable = 0;
  products.forEach(product => {
    if (product.available) inStock++;
    else notAvailable++;
  });
  updateCount('in-stock', inStock);
  updateCount('out-of-stock', notAvailable);

  console.log('[Category] Filters built for', category);
}

// Build weapon model filters for weapon.html
function buildWeaponFilters(products) {
  const weaponOptions = document.getElementById('weapon-options');
  const weaponOptionsMobile = document.getElementById('weapon-options-mobile');

  if (!weaponOptions) return;

  // Clear existing options
  weaponOptions.innerHTML = '';
  if (weaponOptionsMobile) weaponOptionsMobile.innerHTML = '';

  // Count products by weapon name
  const weaponCounts = {};
  products.forEach(product => {
    const name = product.name;
    weaponCounts[name] = (weaponCounts[name] || 0) + 1;
  });

  // Create filter checkboxes for each weapon
  Object.keys(weaponCounts).sort().forEach(weaponName => {
    const count = weaponCounts[weaponName];

    // Desktop filter
    const label = document.createElement('label');
    label.className = 'flex items-center gap-2 cursor-pointer text-sm text-gray-700 hover:text-gray-900';
    label.innerHTML = `
      <input type="checkbox" class="filter-checkbox w-4 h-4 rounded border-gray-300 text-yellow-500" data-filter="weapon" value="${weaponName}" onchange="applyFilters()">
      <span>${weaponName}</span>
      <span class="ml-auto text-gray-500 text-xs">${count}</span>
    `;
    weaponOptions.appendChild(label);

    // Mobile filter
    if (weaponOptionsMobile) {
      const labelMobile = document.createElement('label');
      labelMobile.className = 'flex items-center gap-2 cursor-pointer text-sm text-gray-700';
      labelMobile.innerHTML = `
        <input type="checkbox" class="filter-checkbox w-4 h-4 rounded border-gray-300" data-filter="weapon" value="${weaponName}" onchange="applyFilters()">
        <span>${weaponName}</span>
      `;
      weaponOptionsMobile.appendChild(labelMobile);
    }
  });
}

// Build caliber filters for ammunition.html
function buildCaliberFilters(products) {
  console.log('[Category] Building caliber filters for', products.length, 'products');
  const caliberOptions = document.getElementById('caliber-options');
  const caliberOptionsMobile = document.getElementById('caliber-options-mobile');

  console.log('[Category] caliber-options element:', caliberOptions);
  console.log('[Category] caliber-options-mobile element:', caliberOptionsMobile);

  if (!caliberOptions) {
    console.warn('[Category] caliber-options element not found!');
    return;
  }

  // Clear existing options
  caliberOptions.innerHTML = '';
  if (caliberOptionsMobile) caliberOptionsMobile.innerHTML = '';

  // Count products by caliber
  const caliberCounts = {
    '300blk': { label: '300 AAC Blackout', count: 0 },
    '5.56': { label: '5.56 / .223', count: 0 },
    '7.62x39': { label: '7.62×39', count: 0 },
    '9mm': { label: '9mm', count: 0 },
    '.22lr': { label: '.22 LR', count: 0 }
  };

  products.forEach(product => {
    const name = product.name.toLowerCase();
    console.log('[Category] Checking product:', name);
    if (name.includes('300') || name.includes('aac')) caliberCounts['300blk'].count++;
    if (name.includes('5.56') || name.includes('.223') || name.includes('223')) caliberCounts['5.56'].count++;
    if (name.includes('7.62') || name.includes('762')) caliberCounts['7.62x39'].count++;
    if (name.includes('9mm')) caliberCounts['9mm'].count++;
    if (name.includes('.22') || name.includes('22')) caliberCounts['.22lr'].count++;
  });

  console.log('[Category] Caliber counts:', caliberCounts);

  // Create filter checkboxes for each caliber
  Object.keys(caliberCounts).forEach(caliberValue => {
    const { label, count } = caliberCounts[caliberValue];
    if (count === 0) return; // Skip if no products

    // Desktop filter
    const labelEl = document.createElement('label');
    labelEl.className = 'flex items-center gap-2 cursor-pointer text-sm text-gray-700 hover:text-gray-900';
    labelEl.innerHTML = `
      <input type="checkbox" class="filter-checkbox w-4 h-4 rounded border-gray-300 text-yellow-500" data-filter="caliber" value="${caliberValue}" onchange="applyFilters()">
      <span>${label}</span>
      <span class="ml-auto text-gray-500 text-xs">${count}</span>
    `;
    caliberOptions.appendChild(labelEl);

    // Mobile filter
    if (caliberOptionsMobile) {
      const labelMobile = document.createElement('label');
      labelMobile.className = 'flex items-center gap-2 cursor-pointer text-sm text-gray-700';
      labelMobile.innerHTML = `
        <input type="checkbox" class="filter-checkbox w-4 h-4 rounded border-gray-300" data-filter="caliber" value="${caliberValue}" onchange="applyFilters()">
        <span>${label}</span>
      `;
      caliberOptionsMobile.appendChild(labelMobile);
    }
  });
}

// Update filter counts dynamically based on loaded products
function updateFilterCounts(products) {
  // This function is now replaced by buildDynamicFilters
  buildDynamicFilters(products);
}

// Helper function to update count in HTML
function updateCount(filterValue, count) {
  const checkbox = document.querySelector(`input[value="${filterValue}"]`);
  if (checkbox) {
    const label = checkbox.closest('label');
    const countSpan = label?.querySelector('.text-xs');
    if (countSpan) {
      countSpan.textContent = count;
    }
  }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
  console.log('[Category] Initializing category page...');
  loadCategoryProducts();
});

console.log('[Category] Category loader ready');