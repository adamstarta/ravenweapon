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
  async function loadProduct() {
    const productId = getProductIdFromURL();

    if (!productId) {
      showError('Kein Produkt ausgewählt');
      return;
    }

    try {
      // Load products from API
      const response = await fetch('http://localhost/ravenweaponwebapp/api/products-direct.php');
      const data = await response.json();

      if (!data.success) {
        throw new Error(data.error || 'Failed to load products');
      }

      // Find product by ID
      currentProduct = data.data.find(p => String(p.id) === String(productId) || p.productCode === productId || p.name === productId);

      if (!currentProduct) {
        showError('Produkt nicht gefunden');
        return;
      }

      displayProduct();
    } catch (error) {
      console.error('[Product Page] Error loading product:', error);
      showError('Fehler beim Laden des Produkts');
    }
  }

  // Display product information
  function displayProduct() {
    // Update page title
    document.title = `${currentProduct.name} - RAVEN WEAPON AG`;

    // Update breadcrumb
    document.getElementById('breadcrumb-category').textContent = currentProduct.category;

    // Update product info
    document.getElementById('product-name').textContent = currentProduct.name;
    document.getElementById('product-description').textContent = currentProduct.description;
    document.getElementById('product-categories').textContent = currentProduct.category;

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

  // ===== REVIEWS FUNCTIONALITY =====

  // Generate star icons HTML
  function generateStars(rating, size = 'small') {
    const sizeClass = size === 'large' ? 'w-6 h-6' : 'w-4 h-4';
    let starsHTML = '';

    for (let i = 1; i <= 5; i++) {
      if (i <= Math.floor(rating)) {
        // Full star
        starsHTML += `<svg class="${sizeClass} text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>`;
      } else if (i === Math.ceil(rating) && rating % 1 !== 0) {
        // Half star
        starsHTML += `<svg class="${sizeClass} text-yellow-400" fill="currentColor" viewBox="0 0 20 20"><defs><linearGradient id="half-star-${rating}"><stop offset="50%" stop-color="currentColor"/><stop offset="50%" stop-color="#D1D5DB"/></linearGradient></defs><path fill="url(#half-star-${rating})" d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>`;
      } else {
        // Empty star
        starsHTML += `<svg class="${sizeClass} text-gray-300" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>`;
      }
    }

    return starsHTML;
  }

  // Load and display product reviews
  async function loadProductReviews() {
    const productId = getProductIdFromURL();

    if (!productId) {
      console.log('[Reviews] No product ID found');
      return;
    }

    const reviewsLoading = document.getElementById('reviews-loading');
    if (!reviewsLoading) return; // No reviews section on this page
    const reviewsError = document.getElementById('reviews-error');
    const reviewsList = document.getElementById('reviews-list');
    const noReviews = document.getElementById('no-reviews');

    // Show loading state
    if (reviewsLoading) reviewsLoading.classList.remove('hidden');
    if (reviewsError) reviewsError.classList.add('hidden');
    if (reviewsList) reviewsList.innerHTML = '';
    if (noReviews) noReviews.classList.add('hidden');

    try {
      const response = await fetch(`http://localhost/ravenweaponwebapp/api/api/reviews.php?product_id=${productId}`);
      const data = await response.json();

      console.log('[Reviews] API Response:', data);

      if (!data.success) {
        throw new Error(data.error || 'Failed to load reviews');
      }

      // Hide loading
      if (reviewsLoading) reviewsLoading.classList.add('hidden');

      const { statistics, reviews } = data.data;

      // Display rating statistics
      displayRatingStatistics(statistics);

      // Display reviews
      if (reviews && reviews.length > 0) {
        displayReviews(reviews);
      } else {
        if (noReviews) noReviews.classList.remove('hidden');
      }

    } catch (error) {
      console.error('[Reviews] Error loading reviews:', error);
      if (reviewsLoading) reviewsLoading.classList.add('hidden');
      reviewsError.classList.remove('hidden');
      document.getElementById('reviews-error-message').textContent = error.message;
    }
  }

  // Display rating statistics
  function displayRatingStatistics(stats) {
    // Update average rating
    document.getElementById('average-rating').textContent = stats.average_rating.toFixed(1);
    document.getElementById('total-reviews').textContent = stats.total_reviews;

    // Update average stars
    const averageStars = document.getElementById('average-stars');
    averageStars.innerHTML = generateStars(stats.average_rating, 'small');

    // Update rating breakdown
    const breakdownContainer = document.getElementById('rating-breakdown');
    breakdownContainer.innerHTML = '';

    // Create bars for each rating (5 to 1)
    for (let rating = 5; rating >= 1; rating--) {
      const count = stats.rating_breakdown[rating] || 0;
      const percentage = stats.total_reviews > 0 ? (count / stats.total_reviews) * 100 : 0;

      const barHTML = `
        <div class="flex items-center gap-2">
          <div class="flex items-center gap-1 w-16">
            <span class="text-sm font-medium text-gray-700">${rating}</span>
            <svg class="w-4 h-4 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
              <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
            </svg>
          </div>
          <div class="flex-1 h-2 bg-gray-200 rounded-full overflow-hidden">
            <div class="h-full bg-yellow-400 transition-all duration-500" style="width: ${percentage}%"></div>
          </div>
          <span class="text-sm text-gray-600 w-8 text-right">${count}</span>
        </div>
      `;

      breakdownContainer.innerHTML += barHTML;
    }
  }

  // Display individual reviews
  function displayReviews(reviews) {
    const reviewsList = document.getElementById('reviews-list');
    if (reviewsList) reviewsList.innerHTML = '';

    reviews.forEach((review, index) => {
      const reviewCard = document.createElement('div');
      reviewCard.className = 'border-b border-gray-200 pb-6 last:border-b-0 animate-on-scroll fade-in-up';
      reviewCard.style.animationDelay = `${index * 0.1}s`;

      reviewCard.innerHTML = `
        <div class="flex items-start justify-between mb-3">
          <div>
            <div class="flex items-center gap-2 mb-1">
              <span class="font-semibold text-gray-900">${review.customer_name}</span>
              ${review.verified_purchase ? `
                <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-green-100 text-green-800 text-xs font-medium rounded">
                  <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                  </svg>
                  Verifizierter Kauf
                </span>
              ` : ''}
            </div>
            <div class="flex items-center gap-1 mb-2">
              ${generateStars(review.rating, 'small')}
            </div>
          </div>
          <span class="text-sm text-gray-500">${review.relative_date}</span>
        </div>
        ${review.title ? `<h3 class="font-semibold text-gray-900 mb-2">${review.title}</h3>` : ''}
        <p class="text-gray-700 leading-relaxed mb-3">${review.text}</p>
        <div class="flex items-center gap-4 text-sm">
          <button class="text-gray-600 hover:text-gray-900 flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 10h4.764a2 2 0 011.789 2.894l-3.5 7A2 2 0 0115.263 21h-4.017c-.163 0-.326-.02-.485-.06L7 20m7-10V5a2 2 0 00-2-2h-.095c-.5 0-.905.405-.905.905 0 .714-.211 1.412-.608 2.006L7 11v9m7-10h-2M7 20H5a2 2 0 01-2-2v-6a2 2 0 012-2h2.5"/>
            </svg>
            Hilfreich (${review.helpful_count})
          </button>
        </div>
      `;

      reviewsList.appendChild(reviewCard);
    });

    // Trigger animations for newly added review cards
    setTimeout(() => {
      initScrollAnimations();
    }, 100);
  }

  // ===== REVIEW SUBMISSION FORM =====

  let selectedRating = 0;

  // Setup star rating selector
  function setupStarRating() {
    const starButtons = document.querySelectorAll('.star-btn');
    if (starButtons.length === 0) return; // No star rating elements
    const ratingValueInput = document.getElementById('rating-value');
    const ratingText = document.getElementById('selected-rating-text');

    starButtons.forEach((btn, index) => {
      const rating = parseInt(btn.dataset.rating);

      // Hover effect
      btn.addEventListener('mouseenter', () => {
        updateStarDisplay(rating);
      });

      // Click to select
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        selectedRating = rating;
        ratingValueInput.value = rating;
        updateStarDisplay(rating);
        updateRatingText(rating);
      });
    });

    // Reset on mouse leave
    const starContainer = document.getElementById('star-rating-input');
    if (starContainer) starContainer.addEventListener('mouseleave', () => {
      updateStarDisplay(selectedRating);
    });
  }

  // Update star display
  function updateStarDisplay(rating) {
    const starButtons = document.querySelectorAll('.star-btn');
    if (starButtons.length === 0) return; // No star rating elements
    starButtons.forEach((btn, index) => {
      const star = btn.querySelector('svg');
      const btnRating = parseInt(btn.dataset.rating);

      if (btnRating <= rating) {
        star.classList.remove('text-gray-300');
        star.classList.add('text-yellow-400');
      } else {
        star.classList.remove('text-yellow-400');
        star.classList.add('text-gray-300');
      }
    });
  }

  // Update rating text
  function updateRatingText(rating) {
    const ratingText = document.getElementById('selected-rating-text');
    const labels = {
      1: 'Schlecht',
      2: 'Geht so',
      3: 'Gut',
      4: 'Sehr gut',
      5: 'Ausgezeichnet'
    };
    ratingText.textContent = labels[rating] || '';
  }

  // Setup review form submission
  function setupReviewForm() {
    const form = document.getElementById('review-form');

    if (!form) {
      console.log('[Review Form] Form not found');
      return;
    }

    form.addEventListener('submit', async (e) => {
      e.preventDefault();

      const submitBtn = document.getElementById('submit-review-btn');
      const formStatus = document.getElementById('form-status');
      const successMsg = document.getElementById('review-success');
      const errorMsg = document.getElementById('review-error');
      const errorMsgText = document.getElementById('review-error-message');

      // Hide previous messages
      successMsg.classList.add('hidden');
      errorMsg.classList.add('hidden');

      // Validate rating
      if (selectedRating === 0) {
        errorMsg.classList.remove('hidden');
        errorMsgText.textContent = 'Bitte wählen Sie eine Bewertung aus.';
        return;
      }

      // Get form data
      const formData = {
        product_id: getProductIdFromURL(),
        product_name: currentProduct?.name || 'Produkt',
        customer_name: document.getElementById('customer-name').value.trim(),
        customer_email: document.getElementById('customer-email').value.trim() || null,
        rating: selectedRating,
        review_title: document.getElementById('review-title').value.trim() || null,
        review_text: document.getElementById('review-text').value.trim()
      };

      // Validate required fields
      if (!formData.customer_name || !formData.review_text) {
        errorMsg.classList.remove('hidden');
        errorMsgText.textContent = 'Bitte füllen Sie alle erforderlichen Felder aus.';
        return;
      }

      // Disable submit button
      submitBtn.disabled = true;
      submitBtn.textContent = 'Wird gesendet...';
      formStatus.textContent = '';

      try {
        const response = await fetch('http://localhost/ravenweaponwebapp/api/api/reviews.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify(formData)
        });

        const data = await response.json();

        if (!data.success) {
          throw new Error(data.error || 'Fehler beim Absenden der Bewertung');
        }

        // Show success message
        successMsg.classList.remove('hidden');

        // Reset form
        form.reset();
        selectedRating = 0;
        updateStarDisplay(0);
        updateRatingText(0);
        document.getElementById('rating-value').value = '0';

        // Reload reviews after 2 seconds
        setTimeout(() => {
          loadProductReviews();
          successMsg.classList.add('hidden');
        }, 2000);

      } catch (error) {
        console.error('[Review Form] Error:', error);
        errorMsg.classList.remove('hidden');
        errorMsgText.textContent = error.message;
      } finally {
        // Re-enable submit button
        submitBtn.disabled = false;
        submitBtn.textContent = 'Bewertung absenden';
      }
    });
  }

  // ===== SCROLL ANIMATIONS =====

  // Initialize scroll animations
  function initScrollAnimations() {
    // Get all animated elements
    const animatedElements = document.querySelectorAll('.animate-on-scroll');

    // Check which elements are already visible on page load
    animatedElements.forEach(element => {
      const rect = element.getBoundingClientRect();
      const isVisible = rect.top < window.innerHeight && rect.bottom > 0;

      if (isVisible) {
        // Element is already visible - animate immediately
        triggerAnimation(element);
      }
    });

    // Create Intersection Observer for scroll animations
    const observerOptions = {
      threshold: 0.1,
      rootMargin: '0px 0px -50px 0px'
    };

    const animationObserver = new IntersectionObserver(function(entries) {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          // Only animate if not already animated
          if (!entry.target.classList.contains('animate-visible')) {
            triggerAnimation(entry.target);
          }
          // Stop observing after animation
          animationObserver.unobserve(entry.target);
        }
      });
    }, observerOptions);

    // Observe all elements with animate-on-scroll class
    animatedElements.forEach(element => {
      // Only observe if not already visible/animated
      if (!element.classList.contains('animate-visible')) {
        animationObserver.observe(element);
      }
    });

    console.log('[Animations] Initialized scroll animations for', animatedElements.length, 'elements');
  }

  // Trigger animation on element
  function triggerAnimation(element) {
    element.classList.add('animate-visible');
    element.style.opacity = '1';
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
    loadProductReviews();
    setupStarRating();
    setupReviewForm();
    initScrollAnimations();
  }

  // Run when DOM is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  // Make loadProductReviews available globally for retry button
  window.loadProductReviews = loadProductReviews;
})();
