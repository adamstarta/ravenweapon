/**
 * RavenWeapon Authentication System
 * Handles login, registration, session management
 * Integrates with Joomla backend API
 */

class AuthSystem {
  constructor() {
    this.API_BASE = 'http://localhost/ravenweapon/api';
    this.currentUser = null;
    this.initialized = false;
    this.init();
  }

  /**
   * Initialize authentication system
   */
  async init() {
    // Check if user is logged in
    await this.checkSession();

    // If user is already logged in and on login/register page, redirect
    if (this.currentUser) {
      const isLoginPage = window.location.pathname.includes('login.html');
      const isRegisterPage = window.location.pathname.includes('register.html');

      if (isLoginPage || isRegisterPage) {
        console.log('[Auth] User already logged in, redirecting...');

        // Get redirect parameter or default to account page
        let redirectUrl = new URLSearchParams(window.location.search).get('redirect') || '/ravenweapon/account.html';

        // If redirect is a relative path, add /ravenweapon/ prefix
        if (redirectUrl && !redirectUrl.startsWith('/') && !redirectUrl.startsWith('http')) {
          redirectUrl = '/ravenweapon/' + redirectUrl;
        }

        console.log('[Auth] Auto-redirecting to:', redirectUrl);

        // Small delay to prevent redirect loops
        setTimeout(() => {
          window.location.href = redirectUrl;
        }, 200);
        return; // Stop initialization
      }
    }

    // Update UI based on login status
    this.updateUI();

    // Setup event listeners
    this.setupEventListeners();

    this.initialized = true;
    console.log('[Auth] Authentication system initialized');
  }

  /**
   * Setup event listeners for login/register forms
   */
  setupEventListeners() {
    // Login form
    const loginForm = document.getElementById('login-form');
    if (loginForm) {
      loginForm.addEventListener('submit', (e) => {
        e.preventDefault();
        this.handleLogin(e.target);
      });
    }

    // Register form
    const registerForm = document.getElementById('register-form');
    if (registerForm) {
      registerForm.addEventListener('submit', (e) => {
        e.preventDefault();
        this.handleRegister(e.target);
      });
    }

    // Logout buttons
    const logoutButtons = document.querySelectorAll('[data-action="logout"]');
    logoutButtons.forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        this.logout();
      });
    });
  }

  /**
   * Check if user has active session
   */
  async checkSession() {
    try {
      const response = await fetch(`${this.API_BASE}/auth.php?action=check`, { credentials: 'include' });
      const data = await response.json();

      if (data.success && data.loggedIn) {
        this.currentUser = data.user;
        console.log('[Auth] User logged in:', this.currentUser.name);
      } else {
        this.currentUser = null;
        // Note: Don't clear cart here - cart.js handles its own state based on auth status
        // Clearing here causes race condition since cart may not be initialized yet
        console.log('[Auth] No active session');
      }

      return data;
    } catch (error) {
      console.error('[Auth] Session check failed:', error);
      this.currentUser = null;
      // Note: Don't clear cart here - let cart.js handle its own initialization
      return { success: false, loggedIn: false };
    }
  }

  /**
   * Handle user registration
   */
  async handleRegister(form) {
    const formData = new FormData(form);
    const data = {
      name: formData.get('name'),
      email: formData.get('email'),
      password: formData.get('password'),
      password2: formData.get('password2')
    };

    // Validate passwords match
    if (data.password !== data.password2) {
      this.showError('Passwords do not match');
      return;
    }

    // Validate password strength
    if (data.password.length < 6) {
      this.showError('Password must be at least 6 characters');
      return;
    }

    try {
      this.showLoading('Creating account...');

      const response = await fetch(`${this.API_BASE}/auth.php?action=register`, {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
      });

      const result = await response.json();

      if (result.success) {
        this.currentUser = result.user;
        this.showSuccess('Account created successfully!');

        // Merge guest cart if exists
        await this.mergeGuestCart();

        // Redirect back or to homepage (changed from account.html)
        // (Cart will auto-sync from API after redirect)
        let redirectUrl = new URLSearchParams(window.location.search).get('redirect') || '/ravenweapon/index.html';

        // If redirect is a relative path (no leading /), add /ravenweapon/ prefix
        if (redirectUrl && !redirectUrl.startsWith('/') && !redirectUrl.startsWith('http')) {
          redirectUrl = '/ravenweapon/' + redirectUrl;
        }

        setTimeout(() => {
          window.location.href = redirectUrl;
        }, 1500);
      } else {
        this.showError(result.error || 'Registration failed');
      }
    } catch (error) {
      console.error('[Auth] Registration error:', error);
      this.showError('Registration failed. Please try again.');
    } finally {
      this.hideLoading();
    }
  }

  /**
   * Handle user login
   */
  async handleLogin(form) {
    const formData = new FormData(form);
    const data = {
      email: formData.get('email'),
      password: formData.get('password')
    };

    try {
      this.showLoading('Logging in...');

      const response = await fetch(`${this.API_BASE}/auth.php?action=login`, {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
      });

      const result = await response.json();

      if (result.success) {
        this.currentUser = result.user;
        this.showSuccess('Login successful!');
        console.log('[Auth] Login successful, user:', result.user.name);

        // Merge guest cart if exists
        await this.mergeGuestCart();

        // Redirect back or to homepage (changed from account.html)
        // (Cart will auto-sync from API after redirect)
        let redirectUrl = new URLSearchParams(window.location.search).get('redirect') || '/ravenweapon/index.html';
        console.log('[Auth] Redirect param:', new URLSearchParams(window.location.search).get('redirect'));

        // If redirect is a relative path (no leading /), add /ravenweapon/ prefix
        if (redirectUrl && !redirectUrl.startsWith('/') && !redirectUrl.startsWith('http')) {
          redirectUrl = '/ravenweapon/' + redirectUrl;
        }

        console.log('[Auth] Redirecting to:', redirectUrl);
        setTimeout(() => {
          window.location.href = redirectUrl;
        }, 1500);
      } else {
        this.showError(result.error || 'Login failed');
      }
    } catch (error) {
      console.error('[Auth] Login error:', error);
      this.showError('Login failed. Please try again.');
    } finally {
      this.hideLoading();
    }
  }

  /**
   * Logout user
   */
  async logout() {
    try {
      const response = await fetch(`${this.API_BASE}/auth.php?action=logout`, { credentials: 'include' });
      const result = await response.json();

      if (result.success) {
        this.currentUser = null;
        // Clear cart UI on logout
        if (window.cart) window.cart.clearCart();
        this.showSuccess('Logged out successfully');

        // Redirect to homepage
        setTimeout(() => {
          window.location.href = '/ravenweapon/index.html';
        }, 1000);
      }
    } catch (error) {
      console.error('[Auth] Logout error:', error);
      // Still redirect even if API fails
      this.currentUser = null;
      // Clear cart UI on logout
      if (window.cart) window.cart.clearCart();
      window.location.href = '/ravenweapon/index.html';
    }
  }

  /**
   * Merge guest cart (localStorage) to user cart (HikaShop)
   */
  async mergeGuestCart() {
    // Get guest cart from localStorage
    const guestCart = this.getGuestCart();

    if (!guestCart || guestCart.length === 0) {
      console.log('[Auth] No guest cart to merge');
      return;
    }

    console.log('[Auth] Merging guest cart with', guestCart.length, 'items');

    try {
      // Add each item to HikaShop cart
      for (const item of guestCart) {
        await fetch('http://localhost/ravenweaponwebapp/api/api/cart.php', {
          method: 'POST',
          credentials: 'include',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            productId: item.productId || item.id,
            quantity: item.quantity
          })
        });
      }

      // Clear localStorage cart
      this.clearGuestCart();

      console.log('[Auth] Guest cart merged successfully');
    } catch (error) {
      console.error('[Auth] Failed to merge cart:', error);
    }
  }

  /**
   * Get guest cart from localStorage
   */
  getGuestCart() {
    const keys = ['ravenweapon_cart', 'raven_cart', 'rw_cart', 'cart'];
    for (const key of keys) {
      const cart = localStorage.getItem(key);
      if (cart) {
        try {
          return JSON.parse(cart);
        } catch (e) {
          console.error('[Auth] Failed to parse cart:', e);
        }
      }
    }
    return [];
  }

  /**
   * Clear guest cart from localStorage
   */
  clearGuestCart() {
    const keys = ['ravenweapon_cart', 'raven_cart', 'rw_cart', 'cart'];
    keys.forEach(key => localStorage.removeItem(key));
  }

  /**
   * Update UI based on login status
   */
  updateUI() {
    // Update user icon/name in header
    const userNameElements = document.querySelectorAll('[data-user-name]');
    const loginButtons = document.querySelectorAll('.login-btn, [data-action="login"]');
    const loggedInElements = document.querySelectorAll('.logout-btn');
    const accountLinks = document.querySelectorAll('[data-auth-required]');

    if (this.currentUser) {
      // User is logged in
      userNameElements.forEach(el => {
        el.textContent = this.currentUser.name;
      });

      loginButtons.forEach(btn => btn.style.display = 'none');
      loggedInElements.forEach(el => el.style.display = '');
      accountLinks.forEach(link => link.style.display = '');
    } else {
      // User is not logged in
      userNameElements.forEach(el => {
        el.textContent = 'Guest';
      });

      loginButtons.forEach(btn => btn.style.display = '');
      loggedInElements.forEach(el => el.style.display = 'none');
      accountLinks.forEach(link => link.style.display = 'none');
    }
  }

  /**
   * Check if user is logged in
   */
  isLoggedIn() {
    return this.currentUser !== null;
  }

  /**
   * Get current user
   */
  getUser() {
    return this.currentUser;
  }

  /**
   * Show error message
   */
  showError(message) {
    const errorDiv = document.getElementById('auth-error');
    if (errorDiv) {
      errorDiv.textContent = message;
      errorDiv.classList.remove('hidden');
      errorDiv.classList.add('text-red-600', 'mb-4', 'p-3', 'bg-red-50', 'rounded');
    } else {
      alert('Error: ' + message);
    }
  }

  /**
   * Show success message
   */
  showSuccess(message) {
    const successDiv = document.getElementById('auth-success');
    if (successDiv) {
      successDiv.textContent = message;
      successDiv.classList.remove('hidden');
      successDiv.classList.add('text-green-600', 'mb-4', 'p-3', 'bg-green-50', 'rounded');
    } else {
      alert(message);
    }
  }

  /**
   * Show loading state
   */
  showLoading(message) {
    const loadingDiv = document.getElementById('auth-loading');
    if (loadingDiv) {
      loadingDiv.textContent = message;
      loadingDiv.classList.remove('hidden');
    }

    // Disable submit buttons
    const submitButtons = document.querySelectorAll('button[type="submit"]');
    submitButtons.forEach(btn => {
      btn.disabled = true;
      btn.classList.add('opacity-50', 'cursor-not-allowed');
    });
  }

  /**
   * Hide loading state
   */
  hideLoading() {
    const loadingDiv = document.getElementById('auth-loading');
    if (loadingDiv) {
      loadingDiv.classList.add('hidden');
    }

    // Re-enable submit buttons
    const submitButtons = document.querySelectorAll('button[type="submit"]');
    submitButtons.forEach(btn => {
      btn.disabled = false;
      btn.classList.remove('opacity-50', 'cursor-not-allowed');
    });
  }
}

// Initialize auth system when DOM is ready
let authSystem;
document.addEventListener('DOMContentLoaded', () => {
  authSystem = new AuthSystem();
  window.authSystem = authSystem; // Make globally accessible
  console.log('[Auth] Auth system ready');
});
