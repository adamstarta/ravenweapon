// Product Page Dynamic Loader
(function() {
  'use strict';

  let currentProduct = null;
  let selectedVariantIndex = 0;
  let quantity = 1;

  // Get product ID from URL parameter
  function getProductIdFromURL() {
    const urlParams = new URLSearchParams(window.location.search);
    return urlParams.get('id');
  }

  // Format price with consistent comma separator
  function formatPrice(price) {
    // Convert to string and add comma separator manually for consistency
    const priceStr = Math.floor(price).toString();
    const formatted = priceStr.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    return `CHF ${formatted}.–`;
  }

  // Load and display product
  function loadProduct() {
    const productId = getProductIdFromURL();

    if (!productId) {
      showError('Kein Produkt ausgewählt');
      return;
    }

    currentProduct = getProductById(productId);

    if (!currentProduct) {
      showError('Produkt nicht gefunden');
      return;
    }

    displayProduct();
  }

  // Display product information
  function displayProduct() {
    // Update page title
    document.title = `${currentProduct.name} - RAVEN WEAPON AG`;

    // Update breadcrumb
    document.getElementById('breadcrumb-category').textContent = currentProduct.category;

    // Update product info
    document.getElementById('product-category').textContent = currentProduct.category;
    document.getElementById('product-name').textContent = currentProduct.name;
    document.getElementById('product-description').textContent = currentProduct.description;

    // Update price
    updatePrice();

    // Display first variant image
    const firstVariant = currentProduct.variants[0];
    updateProductImage(firstVariant.image, currentProduct.name);

    // Create color variants
    createColorVariants();

    // Initialize quantity
    document.getElementById('quantity').value = quantity;
  }

  // Update price based on selected variant
  function updatePrice() {
    const variant = currentProduct.variants[selectedVariantIndex];
    const totalPrice = currentProduct.basePrice + variant.priceModifier;
    document.getElementById('product-price').textContent = formatPrice(totalPrice);
  }

  // Update product image
  function updateProductImage(imagePath, altText) {
    const imgElement = document.getElementById('product-main-image');
    imgElement.src = imagePath;
    imgElement.alt = altText;
  }

  // Create color variant swatches
  function createColorVariants() {
    const variantsContainer = document.getElementById('color-variants');
    variantsContainer.innerHTML = '';

    // If only one variant and it's "Standard", hide the color section
    if (currentProduct.variants.length === 1 && currentProduct.variants[0].color === 'Standard') {
      document.getElementById('color-variants-section').style.display = 'none';
      return;
    }

    const isImageVariant = currentProduct.variantType === 'image';

    currentProduct.variants.forEach((variant, index) => {
      const swatch = document.createElement('div');

      if (isImageVariant) {
        // For weapons - show image thumbnail
        swatch.className = `image-swatch ${index === selectedVariantIndex ? 'selected' : ''}`;
        swatch.innerHTML = `<img src="${variant.thumbnail}" alt="${variant.color}" />`;
      } else {
        // For caliber kits - show color box
        swatch.className = `color-swatch ${index === selectedVariantIndex ? 'selected' : ''}`;
        swatch.style.backgroundColor = variant.colorCode;
      }

      swatch.title = variant.color;
      swatch.dataset.index = index;
      swatch.addEventListener('click', () => selectVariant(index));
      variantsContainer.appendChild(swatch);
    });

    updateSelectedColorName();
  }

  // Select a color variant
  function selectVariant(index) {
    selectedVariantIndex = index;
    const variant = currentProduct.variants[index];
    const isImageVariant = currentProduct.variantType === 'image';

    // Update selected state on swatches (works for both color and image swatches)
    const swatchSelector = isImageVariant ? '.image-swatch' : '.color-swatch';
    document.querySelectorAll(swatchSelector).forEach((swatch, i) => {
      if (i === index) {
        swatch.classList.add('selected');
      } else {
        swatch.classList.remove('selected');
      }
    });

    // Update image
    updateProductImage(variant.image, currentProduct.name);

    // Update price
    updatePrice();

    // Update color name
    updateSelectedColorName();
  }

  // Update selected color name display
  function updateSelectedColorName() {
    const variant = currentProduct.variants[selectedVariantIndex];
    document.getElementById('selected-color-name').textContent = variant.color;
  }

  // Quantity controls
  function setupQuantityControls() {
    const qtyInput = document.getElementById('quantity');
    const decreaseBtn = document.getElementById('qty-decrease');
    const increaseBtn = document.getElementById('qty-increase');

    decreaseBtn.addEventListener('click', () => {
      if (quantity > 1) {
        quantity--;
        qtyInput.value = quantity;
      }
    });

    increaseBtn.addEventListener('click', () => {
      quantity++;
      qtyInput.value = quantity;
    });

    qtyInput.addEventListener('change', (e) => {
      const value = parseInt(e.target.value);
      if (value && value > 0) {
        quantity = value;
      } else {
        quantity = 1;
        qtyInput.value = 1;
      }
    });
  }

  // Add to cart functionality
  function setupAddToCart() {
    const addToCartBtn = document.getElementById('add-to-cart');

    addToCartBtn.addEventListener('click', () => {
      const variant = currentProduct.variants[selectedVariantIndex];
      const price = currentProduct.basePrice + variant.priceModifier;

      const cartItem = {
        id: `${currentProduct.id}-${selectedVariantIndex}`,
        productId: currentProduct.id,
        name: currentProduct.name,
        category: currentProduct.category,
        price: price,
        quantity: quantity,
        variant: variant.color,
        image: variant.image
      };

      // Add to cart (using existing cart system)
      if (typeof cart !== 'undefined') {
        // Add item to cart
        cart.addItem(cartItem);
      } else {
        console.error('Cart system not found');
      }
    });
  }

  // Show error message
  function showError(message) {
    const main = document.querySelector('main');
    main.innerHTML = `
      <div class="container py-12 text-center">
        <svg class="w-24 h-24 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
        </svg>
        <h1 class="text-2xl font-bold text-gray-900 mb-2">${message}</h1>
        <p class="text-gray-600 mb-6">Das angeforderte Produkt konnte nicht gefunden werden.</p>
        <a href="index.html" class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-yellow-300 via-amber-400 to-yellow-500 text-black font-semibold rounded-lg">
          Zurück zur Startseite
        </a>
      </div>
    `;
  }

  // Initialize page
  function init() {
    // Check if we're on the product page
    if (!document.getElementById('product-main-image')) {
      return;
    }

    loadProduct();
    setupQuantityControls();
    setupAddToCart();
  }

  // Run when DOM is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
