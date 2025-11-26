/**
 * Checkout Page Logic
 * Handles payment methods, shipping, and order processing
 */

// =============================================
// CHECKOUT CONFIGURATION
// =============================================
const CHECKOUT_CONFIG = {
  shipping: { postpac: 9.90, pickup: 0 },
  vatRate: 0.081, // 8.1% Swiss VAT
  freeShippingThreshold: 500
};

// =============================================
// AUTHENTICATION CHECK
// =============================================
function checkAuthenticationBeforeCheckout() {
  const checkAuth = () => {
    if (!window.authSystem) { setTimeout(checkAuth, 100); return; }
    if (!window.authSystem.initialized) { setTimeout(checkAuth, 100); return; }
    if (!window.authSystem.isLoggedIn()) {
      window.location.href = 'login.html?redirect=checkout.html';
    } else {
      updateUserDisplay();
    }
  };
  checkAuth();
}

function updateUserDisplay() {
  if (window.authSystem && window.authSystem.isLoggedIn()) {
    const user = window.authSystem.getUser();
    if (user) {
      const avatarEl = document.getElementById('user-avatar');
      const emailEl = document.getElementById('user-email');
      if (avatarEl && user.name) {
        avatarEl.textContent = user.name.split(' ').map(n => n[0]).join('').toUpperCase().substring(0, 2);
      }
      if (emailEl && user.email) emailEl.textContent = user.email;
    }
  }
}

checkAuthenticationBeforeCheckout();

// =============================================
// SHIPPING METHOD SELECTION
// =============================================
const shippingRows = document.querySelectorAll('[data-shipping-row]');
let selectedShippingMethod = 'postpac';
let shippingCost = CHECKOUT_CONFIG.shipping.postpac;

shippingRows.forEach(row => {
  const input = row.querySelector('input[type="radio"]');
  if (input) {
    input.addEventListener('change', () => {
      shippingRows.forEach(r => r.classList.remove('active'));
      row.classList.add('active');
      selectedShippingMethod = input.value;
      shippingCost = CHECKOUT_CONFIG.shipping[selectedShippingMethod] || 0;
      updateTotals();
    });
  }
});

// =============================================
// PAYMENT METHOD SELECTION
// =============================================
const paymentRows = document.querySelectorAll('[data-row]');
let selectedPaymentMethod = 'banktransfer';

paymentRows.forEach(row => {
  const input = row.querySelector('input[type="radio"]');
  if (input) {
    const paymentMethod = row.closest('.payment-method');
    const detailsSection = paymentMethod ? paymentMethod.querySelector('[id$="-details"], [id$="-note"]') : null;

    input.addEventListener('change', () => {
      paymentRows.forEach(r => r.classList.remove('active'));
      row.classList.add('active');

      // Hide all payment details
      document.querySelectorAll('.payment-method [id$="-details"], .payment-method [id$="-note"]').forEach(el => {
        el.classList.add('hidden');
      });

      // Show selected payment details
      if (detailsSection) detailsSection.classList.remove('hidden');
      selectedPaymentMethod = input.value;
    });
  }
});

// =============================================
// TERMS & CONDITIONS VALIDATION
// =============================================
const termsCheckbox = document.getElementById('terms-checkbox');
const termsError = document.getElementById('terms-error');
const buyButton = document.getElementById('buy');

function validateTerms() {
  if (!termsCheckbox || !termsCheckbox.checked) {
    if (termsError) termsError.classList.remove('hidden');
    return false;
  }
  if (termsError) termsError.classList.add('hidden');
  return true;
}

if (termsCheckbox) {
  termsCheckbox.addEventListener('change', () => {
    if (termsCheckbox.checked && termsError) {
      termsError.classList.add('hidden');
    }
  });
}

// =============================================
// CART SUMMARY & TOTALS
// =============================================
let cartSubtotal = 0;

function loadSummary() {
  let items = [];
  if (window.cart && cart.items && cart.items.length) {
    items = cart.items;
    cartSubtotal = cart.getTotal();
  } else {
    items = [{ name: 'Produkt', image: 'assets/placeholder.svg', price: 0, quantity: 1 }];
    cartSubtotal = 0;
  }

  // Render ALL cart items in the container
  const container = document.getElementById('cart-items-container');
  if (container) {
    container.innerHTML = '';

    items.forEach(function(item) {
      const itemTotal = (item.price || 0) * (item.quantity || 1);
      const imageSrc = item.image || 'assets/placeholder.svg';
      const itemName = item.name || 'Produkt';
      const itemVariant = item.variant || item.category || 'Standard';

      const itemDiv = document.createElement('div');
      itemDiv.className = 'flex items-start gap-3';
      itemDiv.innerHTML =
        '<div class="qty-wrap">' +
          '<span class="qty-badge">' + (item.quantity || 1) + '</span>' +
          '<div class="w-16 h-16 bg-white border border-gray-200 rounded-md overflow-hidden flex items-center justify-center">' +
            '<img src="' + imageSrc + '" class="max-w-full max-h-full object-contain p-1" alt="' + itemName + '" onerror="this.src=\x27assets/placeholder.svg\x27">' +
          '</div>' +
        '</div>' +
        '<div class="flex-1 min-w-0">' +
          '<div class="text-sm font-medium text-gray-900 truncate">' + itemName + '</div>' +
          '<div class="text-xs text-gray-500">' + itemVariant + '</div>' +
        '</div>' +
        '<div class="text-sm font-medium">CHF ' + itemTotal.toFixed(2) + '</div>';
      container.appendChild(itemDiv);
    });
  }
  updateTotals();
}

function updateTotals() {
  let actualShippingCost = shippingCost;
  if (cartSubtotal >= CHECKOUT_CONFIG.freeShippingThreshold) {
    actualShippingCost = 0;
  }

  const total = cartSubtotal + actualShippingCost;
  const vat = total * CHECKOUT_CONFIG.vatRate / (1 + CHECKOUT_CONFIG.vatRate);

  const subtotalEl = document.getElementById('subtotal');
  const shippingEl = document.getElementById('shipping-cost');
  const totalEl = document.getElementById('total');
  const vatEl = document.getElementById('vat');
  const postpacPrice = document.getElementById('postpac-price');

  if (subtotalEl) subtotalEl.textContent = 'CHF ' + cartSubtotal.toFixed(2);

  if (shippingEl) {
    if (actualShippingCost === 0) {
      shippingEl.textContent = 'Kostenlos';
      shippingEl.classList.add('text-green-600');
    } else {
      shippingEl.textContent = 'CHF ' + actualShippingCost.toFixed(2);
      shippingEl.classList.remove('text-green-600');
    }
  }

  if (totalEl) totalEl.textContent = 'CHF ' + total.toFixed(2);
  if (vatEl) vatEl.textContent = vat.toFixed(2);

  if (postpacPrice) {
    if (cartSubtotal >= CHECKOUT_CONFIG.freeShippingThreshold) {
      postpacPrice.textContent = 'Kostenlos';
      postpacPrice.classList.add('text-green-600');
    } else {
      postpacPrice.textContent = 'CHF ' + CHECKOUT_CONFIG.shipping.postpac.toFixed(2);
      postpacPrice.classList.remove('text-green-600');
    }
  }
}

// =============================================
// COUPON HANDLING
// =============================================
const applyCouponBtn = document.getElementById('apply-coupon');
if (applyCouponBtn) {
  applyCouponBtn.addEventListener('click', () => {
    const couponInput = document.getElementById('coupon');
    const couponCode = couponInput ? couponInput.value.trim() : '';
    if (!couponCode) return;
    alert('Gutschein wird überprüft... (Demo)');
  });
}

// =============================================
// CHECKOUT SUBMISSION
// =============================================
if (buyButton) {
  buyButton.addEventListener('click', async () => {
    // Validate terms
    if (!validateTerms()) return;

    // Validate required fields
    const requiredFields = ['shipping-firstname', 'shipping-lastname', 'shipping-address', 'shipping-zip', 'shipping-city'];
    let hasErrors = false;

    requiredFields.forEach(fieldId => {
      const field = document.getElementById(fieldId);
      if (field && !field.value.trim()) {
        field.classList.add('border-red-500');
        hasErrors = true;
      } else if (field) {
        field.classList.remove('border-red-500');
      }
    });

    if (hasErrors) {
      alert('Bitte füllen Sie alle Pflichtfelder aus.');
      return;
    }

    // Collect order data
    const orderData = {
      shipping: {
        country: document.getElementById('shipping-country')?.value || 'Schweiz',
        firstName: document.getElementById('shipping-firstname')?.value || '',
        lastName: document.getElementById('shipping-lastname')?.value || '',
        company: document.getElementById('shipping-company')?.value || '',
        address: document.getElementById('shipping-address')?.value || '',
        zip: document.getElementById('shipping-zip')?.value || '',
        city: document.getElementById('shipping-city')?.value || ''
      },
      shippingMethod: selectedShippingMethod,
      paymentMethod: selectedPaymentMethod,
      cartItems: window.cart ? cart.items : [],
      subtotal: cartSubtotal,
      shippingCost: selectedShippingMethod === 'pickup' ? 0 : (cartSubtotal >= CHECKOUT_CONFIG.freeShippingThreshold ? 0 : shippingCost),
      total: cartSubtotal + (selectedShippingMethod === 'pickup' ? 0 : (cartSubtotal >= CHECKOUT_CONFIG.freeShippingThreshold ? 0 : shippingCost))
    };

    console.log('[Checkout] Order data:', orderData);

    try {
      buyButton.disabled = true;
      buyButton.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-2"></i>Wird verarbeitet...';

      // Process based on payment method
      const orderId = 'RW-' + Date.now();

      switch (selectedPaymentMethod) {
        case 'banktransfer':
          alert(`✅ Vielen Dank für Ihre Bestellung!\n\nBestellnummer: ${orderId}\n\nBitte überweisen Sie CHF ${orderData.total.toFixed(2)} auf unser Konto.\n\nBank: PostFinance\nIBAN: CH00 0000 0000 0000 0000 0\nKontoinhaber: Raven Weapon AG\nVerwendungszweck: ${orderId}\n\nDie Ware wird nach Zahlungseingang versandt.`);
          break;

        case 'twint':
          alert(`✅ Bestellung vorbereitet!\n\nBestellnummer: ${orderId}\n\nSie werden jetzt zu TWINT weitergeleitet...\n\n(In der Produktivumgebung erfolgt hier die TWINT-Zahlung)`);
          // In production: window.location.href = TWINT_PAYMENT_URL + '?order=' + orderId;
          break;

        case 'datatrans':
          alert(`✅ Bestellung vorbereitet!\n\nBestellnummer: ${orderId}\n\nSie werden jetzt zu Datatrans weitergeleitet...\n\n(In der Produktivumgebung erfolgt hier die Kartenzahlung über Datatrans)`);
          // In production: window.location.href = DATATRANS_PAYMENT_URL + '?order=' + orderId;
          break;

        default:
          alert('Unbekannte Zahlungsmethode.');
          buyButton.disabled = false;
          buyButton.innerHTML = 'Jetzt kaufen';
          return;
      }

      // Clear cart and redirect
      if (window.cart) cart.clearCart();
      setTimeout(() => location.href = 'index.html', 500);

    } catch (error) {
      console.error('[Checkout] Error:', error);
      alert('Ein Fehler ist aufgetreten. Bitte versuchen Sie es erneut.');
      buyButton.disabled = false;
      buyButton.innerHTML = 'Jetzt kaufen';
    }
  });
}

// =============================================
// SAVED ADDRESSES
// =============================================
function loadSavedAddresses() {
  return JSON.parse(localStorage.getItem('raven_addresses') || '[]');
}

function populateSavedAddressesDropdown() {
  const select = document.getElementById('saved-addresses-select');
  if (!select) return;

  const addresses = loadSavedAddresses();
  select.innerHTML = '<option value="">-- Adresse auswählen --</option>';

  if (addresses.length === 0) {
    select.innerHTML = '<option value="">Keine gespeicherten Adressen</option>';
    return;
  }

  addresses.sort((a, b) => (b.isDefault ? 1 : 0) - (a.isDefault ? 1 : 0));

  addresses.forEach(addr => {
    const option = document.createElement('option');
    option.value = addr.id;
    option.textContent = `${addr.firstName} ${addr.lastName} - ${addr.street}, ${addr.zip} ${addr.city}${addr.isDefault ? ' (Standard)' : ''}`;
    select.appendChild(option);
  });
}

function fillShippingForm(addressId) {
  const addresses = loadSavedAddresses();
  const address = addresses.find(a => a.id === addressId);
  if (!address) return;

  const fieldMap = {
    'shipping-country': 'country',
    'shipping-firstname': 'firstName',
    'shipping-lastname': 'lastName',
    'shipping-company': 'company',
    'shipping-address': 'street',
    'shipping-zip': 'zip',
    'shipping-city': 'city'
  };

  Object.entries(fieldMap).forEach(([fieldId, addrKey]) => {
    const field = document.getElementById(fieldId);
    if (field && address[addrKey]) {
      if (field.tagName === 'SELECT') {
        const options = Array.from(field.options);
        const match = options.find(opt => opt.value.toLowerCase() === address[addrKey].toLowerCase());
        if (match) field.value = match.value;
      } else {
        field.value = address[addrKey];
      }
    }
  });
}
// Initialize
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', loadSummary);
} else {
  setTimeout(loadSummary, 80);
}

// Listen for cart loaded event from cart.js
window.addEventListener('cartLoaded', (e) => {
  console.log('[Checkout] Cart loaded event received:', e.detail);
  loadSummary();
});

// Also populate saved addresses when DOM is ready
if (document.readyState === 'complete') {
  populateSavedAddressesDropdown();
} else {
  window.addEventListener('load', populateSavedAddressesDropdown);
}

console.log('[Checkout] Logic initialized');
