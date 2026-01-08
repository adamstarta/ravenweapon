import Plugin from 'src/plugin-system/plugin.class';

/**
 * RavenToastPlugin - Modern toast notifications for RAVEN WEAPON AG
 *
 * Usage:
 *   window.ravenToast('error', 'Ungültige E-Mail oder Passwort');
 *   window.ravenToast('success', 'Erfolgreich angemeldet');
 *   window.ravenToast('warning', 'Bitte füllen Sie alle Felder aus');
 */
export default class RavenToastPlugin extends Plugin {
    static options = {
        containerSelector: '#raven-toast-container',
        flashMessageSelector: '.alert[data-raven-flash]',
        durations: {
            error: 5000,
            success: 3000,
            warning: 4000,
            info: 4000
        },
        // German translations for common Shopware messages
        translations: {
            // Cart success messages
            'shopping cart updated': 'Warenkorb wurde aktualisiert',
            'cart updated': 'Warenkorb wurde aktualisiert',
            'product added to your shopping cart': 'Artikel zum Warenkorb hinzugefügt',
            'products added to your shopping cart': 'Artikel zum Warenkorb hinzugefügt',
            'added to cart': 'Zum Warenkorb hinzugefügt',
            'added to your cart': 'Zum Warenkorb hinzugefügt',
            'has been added': 'wurde hinzugefügt',
            'item added': 'Artikel hinzugefügt',
            'items added': 'Artikel hinzugefügt',
            'removed from cart': 'Aus dem Warenkorb entfernt',
            'item removed': 'Artikel entfernt',
            // Payment/Shipping cart validation
            'payment is not available': 'Die Zahlungsart wurde automatisch angepasst.',
            'shipping is not available': 'Die Versandart wurde automatisch angepasst.',
            'was changed to': 'wurde geändert zu',
            // Login errors
            'Invalid credentials': 'Die Anmeldedaten sind nicht korrekt.',
            'Bad credentials': 'Die Anmeldedaten sind nicht korrekt.',
            'Your account is locked': 'Ihr Konto ist gesperrt.',
            // Cart errors
            'Product not found': 'Produkt nicht gefunden.',
            'Out of stock': 'Nicht auf Lager.',
            'Not enough stock': 'Nicht genügend auf Lager.',
            // General success/error
            'successfully': 'erfolgreich',
            'Success': 'Erfolg',
            'Error': 'Fehler',
            'Warning': 'Warnung'
        }
    };

    init() {
        this._createContainer();
        this._interceptFlashMessages();
        this._exposeGlobalAPI();
    }

    /**
     * Create the toast container element
     */
    _createContainer() {
        if (document.querySelector(this.options.containerSelector)) {
            this.container = document.querySelector(this.options.containerSelector);
            return;
        }

        this.container = document.createElement('div');
        this.container.id = 'raven-toast-container';
        document.body.appendChild(this.container);
    }

    /**
     * Intercept Shopware flash messages and convert to toasts
     */
    _interceptFlashMessages() {
        // Wait for DOM to be ready
        const processFlashMessages = () => {
            // Look for Shopware's flash messages
            const alerts = document.querySelectorAll('.alert:not([data-raven-processed])');

            alerts.forEach(alert => {
                alert.setAttribute('data-raven-processed', 'true');

                // Determine type from alert class
                let type = 'info';
                if (alert.classList.contains('alert-danger') || alert.classList.contains('alert-error')) {
                    type = 'error';
                } else if (alert.classList.contains('alert-success')) {
                    type = 'success';
                } else if (alert.classList.contains('alert-warning')) {
                    type = 'warning';
                }

                // Get message text
                const content = alert.querySelector('.alert-content') || alert;
                let message = content.textContent?.trim();

                // Clean up message and translate
                if (message) {
                    message = message.replace(/^\s*×?\s*/, '').trim();
                    if (message.length > 0) {
                        message = this._translateMessage(message);
                        this.show(type, message);
                    }
                }

                // Hide the original alert
                alert.style.display = 'none';
            });
        };

        // Process immediately and after a short delay (for dynamic content)
        processFlashMessages();
        setTimeout(processFlashMessages, 100);
        setTimeout(processFlashMessages, 500);
    }

    /**
     * Expose global API for programmatic usage
     */
    _exposeGlobalAPI() {
        window.ravenToast = (type, message, duration) => {
            this.show(type, message, duration);
        };
    }

    /**
     * Show a toast notification
     * @param {string} type - 'error', 'success', 'warning', 'info'
     * @param {string} message - The message to display
     * @param {number} duration - Optional custom duration in ms
     */
    show(type, message, duration) {
        const toast = this._createToast(type, message);
        this.container.appendChild(toast);

        // Trigger animation
        requestAnimationFrame(() => {
            toast.classList.add('raven-toast--visible');
        });

        // Schedule removal
        const dismissTime = duration || this.options.durations[type] || 4000;
        this._scheduleRemoval(toast, dismissTime);
    }

    /**
     * Create toast element
     */
    _createToast(type, message) {
        const toast = document.createElement('div');
        toast.className = `raven-toast raven-toast--${type}`;
        toast.setAttribute('role', 'alert');
        toast.setAttribute('aria-live', 'polite');

        toast.innerHTML = `
            <div class="raven-toast__icon">
                ${this._getIcon(type)}
            </div>
            <div class="raven-toast__content">
                <span class="raven-toast__message">${this._escapeHtml(message)}</span>
            </div>
            <button class="raven-toast__close" aria-label="Schließen" type="button">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        `;

        // Add close handler
        const closeBtn = toast.querySelector('.raven-toast__close');
        closeBtn.addEventListener('click', () => this._removeToast(toast));

        // Click anywhere to dismiss
        toast.addEventListener('click', (e) => {
            if (e.target === toast || e.target.closest('.raven-toast__content')) {
                this._removeToast(toast);
            }
        });

        return toast;
    }

    /**
     * Get SVG icon for toast type
     */
    _getIcon(type) {
        const icons = {
            error: `<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="15" y1="9" x2="9" y2="15"></line>
                <line x1="9" y1="9" x2="15" y2="15"></line>
            </svg>`,
            success: `<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"></circle>
                <polyline points="9 12 12 15 16 10"></polyline>
            </svg>`,
            warning: `<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                <line x1="12" y1="9" x2="12" y2="13"></line>
                <line x1="12" y1="17" x2="12.01" y2="17"></line>
            </svg>`,
            info: `<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="16" x2="12" y2="12"></line>
                <line x1="12" y1="8" x2="12.01" y2="8"></line>
            </svg>`
        };
        return icons[type] || icons.info;
    }

    /**
     * Schedule toast removal
     */
    _scheduleRemoval(toast, duration) {
        const timeoutId = setTimeout(() => {
            this._removeToast(toast);
        }, duration);

        // Store timeout ID for potential cancellation
        toast._timeoutId = timeoutId;

        // Pause on hover
        toast.addEventListener('mouseenter', () => {
            clearTimeout(toast._timeoutId);
        });

        toast.addEventListener('mouseleave', () => {
            toast._timeoutId = setTimeout(() => {
                this._removeToast(toast);
            }, 1000); // Give 1 second after mouse leaves
        });
    }

    /**
     * Remove toast with animation
     */
    _removeToast(toast) {
        if (toast._removing) return;
        toast._removing = true;

        clearTimeout(toast._timeoutId);
        toast.classList.remove('raven-toast--visible');
        toast.classList.add('raven-toast--hiding');

        setTimeout(() => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 300);
    }

    /**
     * Escape HTML to prevent XSS
     */
    _escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Translate message to German if translation exists
     */
    _translateMessage(message) {
        const lowerMessage = message.toLowerCase();

        // Check for "Shopping cart updated" message
        if (lowerMessage.includes('shopping cart updated') || lowerMessage.includes('cart updated') || lowerMessage.includes('cart has been updated')) {
            return 'Warenkorb wurde aktualisiert';
        }

        // Check for "X product(s) added to your shopping cart" pattern
        if (lowerMessage.includes('product') && lowerMessage.includes('added') && lowerMessage.includes('cart')) {
            return 'Artikel zum Warenkorb hinzugefügt';
        }

        // Check for "added to cart" variations
        if (lowerMessage.includes('added to') && (lowerMessage.includes('cart') || lowerMessage.includes('basket'))) {
            return 'Zum Warenkorb hinzugefügt';
        }

        // Check for payment method change message
        if (lowerMessage.includes('payment') && lowerMessage.includes('not available')) {
            return this.options.translations['payment is not available'];
        }

        // Check for shipping method change message
        if (lowerMessage.includes('shipping') && lowerMessage.includes('not available')) {
            return this.options.translations['shipping is not available'];
        }

        // Check for partial matches in translations
        for (const [key, translation] of Object.entries(this.options.translations)) {
            if (lowerMessage.includes(key.toLowerCase())) {
                return translation;
            }
        }

        // Return original if no translation found
        return message;
    }
}
