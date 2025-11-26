/**
 * RavenWeapon Authentication System
 * Handles login, registration, session management
 * Integrates with Shopware Store API
 */

class AuthSystem {
  constructor() {
    this.API_BASE = 'http://localhost/store-api';
    this.ACCESS_KEY = 'SWSCWWRXRLLXNWHNB0F2NJNIUG';
    this.currentUser = null;
    this.initialized = false;
    this.init();
  }

  getContextToken() {
    return localStorage.getItem('sw-context-token') || '';
  }

  setContextToken(token) {
    if (token) {
      localStorage.setItem('sw-context-token', token);
    }
  }

  async apiRequest(endpoint, method = 'GET', body = null) {
    const headers = {
      'Content-Type': 'application/json',
      'sw-access-key': this.ACCESS_KEY,
    };

    const contextToken = this.getContextToken();
    if (contextToken) {
      headers['sw-context-token'] = contextToken;
    }

    const options = { method, headers, credentials: 'include' };
    if (body && method !== 'GET') {
      options.body = JSON.stringify(body);
    }

    const response = await fetch(`${this.API_BASE}${endpoint}`, options);
    const newToken = response.headers.get('sw-context-token');
    if (newToken) {
      this.setContextToken(newToken);
    }
    return response;
  }

  async init() {
    await this.checkSession();

    if (this.currentUser) {
      const isLoginPage = window.location.pathname.includes('login.html');
      const isRegisterPage = window.location.pathname.includes('register.html');

      if (isLoginPage || isRegisterPage) {
        console.log('[Auth] User already logged in, redirecting...');
        let redirectUrl = new URLSearchParams(window.location.search).get('redirect') || '/raven/account.html';
        setTimeout(() => { window.location.href = redirectUrl; }, 200);
        return;
      }
    }

    this.updateUI();
    this.setupEventListeners();
    this.initialized = true;
    console.log('[Auth] Authentication system initialized');
  }

  setupEventListeners() {
    const loginForm = document.getElementById('login-form');
    if (loginForm) {
      loginForm.addEventListener('submit', (e) => {
        e.preventDefault();
        this.handleLogin(e.target);
      });
    }

    const registerForm = document.getElementById('register-form');
    if (registerForm) {
      registerForm.addEventListener('submit', (e) => {
        e.preventDefault();
        this.handleRegister(e.target);
      });
    }

    const logoutButtons = document.querySelectorAll('[data-action="logout"]');
    logoutButtons.forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        this.logout();
      });
    });
  }

  async checkSession() {
    try {
      const response = await this.apiRequest('/account/customer', 'POST', {});
      if (response.ok) {
        const data = await response.json();
        if (data && data.id) {
          this.currentUser = {
            id: data.id,
            email: data.email,
            name: `${data.firstName} ${data.lastName}`,
            firstName: data.firstName,
            lastName: data.lastName
          };
          console.log('[Auth] User logged in:', this.currentUser.name);
        }
      } else {
        this.currentUser = null;
        console.log('[Auth] No active session');
      }
    } catch (error) {
      console.error('[Auth] Session check failed:', error);
      this.currentUser = null;
    }
  }

  async handleLogin(form) {
    const email = form.querySelector('[name="email"], [type="email"], input[placeholder*="email"]')?.value ||
                  form.querySelector('input[type="text"]')?.value;
    const password = form.querySelector('[name="password"], [type="password"]')?.value;

    if (!email || !password) {
      this.showError(form, 'Bitte E-Mail und Passwort eingeben');
      return;
    }

    try {
      this.showLoading(form);

      const response = await this.apiRequest('/account/login', 'POST', {
        email: email,
        password: password
      });

      const data = await response.json();

      if (response.ok) {
        // Context token is saved from response headers in apiRequest
        await this.checkSession();
        this.hideLoading(form);
        this.showSuccess(form, 'Login erfolgreich! Weiterleitung...');

        const redirectUrl = new URLSearchParams(window.location.search).get('redirect') || '/raven/account.html';
        setTimeout(() => { window.location.href = redirectUrl; }, 1000);
      } else {
        this.hideLoading(form);
        const errorMsg = data.errors?.[0]?.detail || 'Ungueltige E-Mail oder Passwort';
        this.showError(form, errorMsg);
      }
    } catch (error) {
      console.error('[Auth] Login error:', error);
      this.hideLoading(form);
      this.showError(form, 'Login fehlgeschlagen. Bitte versuchen Sie es erneut.');
    }
  }

  async handleRegister(form) {
    // Get full name and split into first/last
    const fullName = form.querySelector('[name="name"]')?.value || '';
    const nameParts = fullName.trim().split(' ');
    const firstName = nameParts[0] || '';
    const lastName = nameParts.slice(1).join(' ') || nameParts[0] || '';

    const email = form.querySelector('[name="email"], [type="email"]')?.value;
    const password = form.querySelector('[name="password"]')?.value;
    const passwordConfirm = form.querySelector('[name="password2"], [name="passwordConfirm"]')?.value;

    if (!fullName || !email || !password) {
      this.showError(form, 'Bitte alle Pflichtfelder ausfuellen');
      return;
    }

    if (password !== passwordConfirm) {
      this.showError(form, 'Passwoerter stimmen nicht ueberein');
      return;
    }

    if (password.length < 8) {
      this.showError(form, 'Passwort muss mindestens 8 Zeichen haben');
      return;
    }

    try {
      this.showLoading(form);
      const salutationId = await this.getSalutationId();

      const response = await this.apiRequest('/account/register', 'POST', {
        salutationId: salutationId,
        firstName: firstName,
        lastName: lastName,
        email: email,
        password: password,
        storefrontUrl: window.location.origin + '/raven',
        acceptedDataProtection: true
      });

      const data = await response.json();

      if (response.ok && data.id) {
        this.hideLoading(form);
        this.showSuccess(form, 'Registrierung erfolgreich! Weiterleitung zum Login...');
        setTimeout(() => { window.location.href = '/raven/login.html'; }, 2000);
      } else {
        this.hideLoading(form);
        const errorMsg = data.errors?.[0]?.detail || 'Registrierung fehlgeschlagen';
        this.showError(form, errorMsg);
      }
    } catch (error) {
      console.error('[Auth] Registration error:', error);
      this.hideLoading(form);
      this.showError(form, 'Registrierung fehlgeschlagen. Bitte versuchen Sie es erneut.');
    }
  }

  async getSalutationId() {
    try {
      const response = await this.apiRequest('/salutation', 'POST', {});
      const data = await response.json();
      if (data.elements && data.elements.length > 0) {
        const mr = data.elements.find(s => s.salutationKey === 'mr');
        return mr ? mr.id : data.elements[0].id;
      }
    } catch (error) {
      console.error('[Auth] Failed to get salutation:', error);
    }
    return null;
  }

  async logout() {
    try {
      await this.apiRequest('/account/logout', 'POST', {});
    } catch (error) {
      console.error('[Auth] Logout error:', error);
    }
    this.currentUser = null;
    localStorage.removeItem('sw-context-token');
    window.location.href = '/raven/index.html';
  }

  updateUI() {
    const userButtons = document.querySelectorAll('[data-auth="user-button"]');
    const guestButtons = document.querySelectorAll('[data-auth="guest-button"]');
    const userNames = document.querySelectorAll('[data-auth="user-name"]');

    if (this.currentUser) {
      userButtons.forEach(el => el.classList.remove('hidden'));
      guestButtons.forEach(el => el.classList.add('hidden'));
      userNames.forEach(el => el.textContent = this.currentUser.name);
    } else {
      userButtons.forEach(el => el.classList.add('hidden'));
      guestButtons.forEach(el => el.classList.remove('hidden'));
    }
  }

  isLoggedIn() { return !!this.currentUser; }
  getUser() { return this.currentUser; }

  showError(form, message) {
    this.clearMessages(form);
    const errorDiv = document.createElement('div');
    errorDiv.className = 'auth-error bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4';
    errorDiv.textContent = message;
    form.parentNode.insertBefore(errorDiv, form);
  }

  showSuccess(form, message) {
    this.clearMessages(form);
    const successDiv = document.createElement('div');
    successDiv.className = 'auth-success bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4';
    successDiv.textContent = message;
    form.parentNode.insertBefore(successDiv, form);
  }

  clearMessages(form) {
    const messages = form.parentNode.querySelectorAll('.auth-error, .auth-success');
    messages.forEach(msg => msg.remove());
  }

  showLoading(form) {
    const submitBtn = form.querySelector('[type="submit"], button');
    if (submitBtn) {
      submitBtn.disabled = true;
      submitBtn.dataset.originalText = submitBtn.textContent;
      submitBtn.textContent = 'Bitte warten...';
    }
  }

  hideLoading(form) {
    const submitBtn = form.querySelector('[type="submit"], button');
    if (submitBtn) {
      submitBtn.disabled = false;
      submitBtn.textContent = submitBtn.dataset.originalText || 'Submit';
    }
  }
}

// Initialize auth system
const auth = new AuthSystem();
window.auth = auth;
console.log('[Auth] Auth system ready');
