import Plugin from 'src/plugin-system/plugin.class';

export default class RavenOffcanvasCartPlugin extends Plugin {
    init() {
        this._registerEvents();
        window.ravenOpenCart = this.openCart.bind(this);
        window.ravenCloseCart = this.closeCart.bind(this);
        window.ravenRefreshCart = this.refreshCart.bind(this);
    }

    _registerEvents() {
        // Handle clicks on cart buttons
        document.addEventListener('click', (e) => {
            const cartButton = e.target.closest('[data-offcanvas-cart]');
            if (cartButton) {
                e.preventDefault();
                e.stopPropagation();
                this.openCart();
            }
        });

        // Intercept add to cart form submissions
        document.addEventListener('submit', (e) => {
            const form = e.target;
            if (form.getAttribute('action')?.includes('checkout/line-item/add')) {
                e.preventDefault();
                this._addToCartAjax(form);
            }
        });
    }

    _addToCartAjax(form) {
        const formData = new FormData(form);
        const action = form.getAttribute('action');

        fetch(action, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => {
            // After adding to cart, open the sidebar
            this.openCart();
        })
        .catch(error => {
            console.error('Add to cart error:', error);
            this.openCart();
        });
    }

    openCart() {
        fetch('/checkout/offcanvas', {
            method: 'GET',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(response => response.text())
        .then(html => {
            this._renderCart(html);
        });
    }

    refreshCart() {
        fetch('/checkout/offcanvas', {
            method: 'GET',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(response => response.text())
        .then(html => {
            const offcanvas = document.getElementById('raven-offcanvas-cart');
            if (offcanvas) {
                offcanvas.innerHTML = '<div class="offcanvas-body p-0">' + html + '</div>';
                this._initCartHandlers(offcanvas);
            }
        });
    }

    _renderCart(html) {
        let offcanvas = document.getElementById('raven-offcanvas-cart');
        if (!offcanvas) {
            offcanvas = document.createElement('div');
            offcanvas.id = 'raven-offcanvas-cart';
            offcanvas.className = 'offcanvas offcanvas-end';
            // Responsive width: 100% on mobile (<480px), 420px on larger screens
            const isMobile = window.innerWidth <= 480;
            const cartWidth = isMobile ? '100%' : '420px';
            offcanvas.style.cssText = `position:fixed;top:0;right:0;bottom:0;width:${cartWidth};max-width:100vw;z-index:1050;background:#fff;transform:translateX(100%);transition:transform 0.3s ease;box-shadow:-4px 0 15px rgba(0,0,0,0.1);`;
            document.body.appendChild(offcanvas);
        }
        offcanvas.innerHTML = '<div class="offcanvas-body p-0">' + html + '</div>';
        offcanvas.style.transform = 'translateX(0)';
        offcanvas.classList.add('show');

        let backdrop = document.getElementById('raven-offcanvas-backdrop');
        if (!backdrop) {
            backdrop = document.createElement('div');
            backdrop.id = 'raven-offcanvas-backdrop';
            backdrop.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.5);z-index:1040;opacity:0;transition:opacity 0.15s;';
            backdrop.onclick = () => this.closeCart();
            document.body.appendChild(backdrop);
        }
        setTimeout(() => { backdrop.style.opacity = '1'; }, 10);
        document.body.style.overflow = 'hidden';

        this._initCartHandlers(offcanvas);
    }

    _initCartHandlers(container) {
        // Attach close handlers
        container.querySelectorAll('[data-offcanvas-close], .js-offcanvas-close, .raven-cart-close').forEach(btn => {
            btn.onclick = (e) => {
                e.preventDefault();
                this.closeCart();
            };
        });

        // Handle ALL form submissions via AJAX
        container.querySelectorAll('form').forEach(form => {
            form.onsubmit = (e) => {
                e.preventDefault();
                this._submitFormAjax(form);
            };
        });

        // Handle minus buttons
        container.querySelectorAll('.js-btn-minus').forEach(btn => {
            btn.onclick = (e) => {
                e.preventDefault();
                const form = btn.closest('form');
                const input = form.querySelector('input[name="quantity"]');
                if (input) {
                    const min = parseInt(input.min) || 1;
                    const current = parseInt(input.value) || 1;
                    if (current > min) {
                        input.value = current - 1;
                        this._submitFormAjax(form);
                    }
                }
            };
        });

        // Handle plus buttons
        container.querySelectorAll('.js-btn-plus').forEach(btn => {
            btn.onclick = (e) => {
                e.preventDefault();
                const form = btn.closest('form');
                const input = form.querySelector('input[name="quantity"]');
                if (input) {
                    const max = parseInt(input.max) || 100;
                    const current = parseInt(input.value) || 1;
                    if (current < max) {
                        input.value = current + 1;
                        this._submitFormAjax(form);
                    }
                }
            };
        });

        // Handle delete buttons
        container.querySelectorAll('.raven-item-remove').forEach(btn => {
            btn.onclick = (e) => {
                e.preventDefault();
                const form = btn.closest('form');
                if (form) {
                    this._submitFormAjax(form);
                }
            };
        });
    }

    _submitFormAjax(form) {
        const formData = new FormData(form);
        const action = form.getAttribute('action');

        fetch(action, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => {
            // After form submission, refresh the cart content
            this.refreshCart();
        })
        .catch(error => {
            console.error('Cart update error:', error);
            this.refreshCart();
        });
    }

    closeCart() {
        const offcanvas = document.getElementById('raven-offcanvas-cart');
        const backdrop = document.getElementById('raven-offcanvas-backdrop');
        if (offcanvas) {
            offcanvas.style.transform = 'translateX(100%)';
            offcanvas.classList.remove('show');
        }
        if (backdrop) {
            backdrop.style.opacity = '0';
            setTimeout(() => backdrop.remove(), 150);
        }
        document.body.style.overflow = '';
    }
}
