# RavenTheme JavaScript Plugins

Custom Shopware 6 storefront plugins for RAVEN WEAPON AG.

## RavenOffcanvasCartPlugin

AJAX-powered off-canvas cart sidebar with no page refresh.

### Global API
```javascript
window.ravenOpenCart()    // Open cart sidebar
window.ravenCloseCart()   // Close cart sidebar
window.ravenRefreshCart() // Refresh cart content
```

### Features
- Fetches `/checkout/offcanvas` via AJAX
- Responsive: 100% width on mobile (<480px), 420px on desktop
- Backdrop overlay with click-to-close
- Handles quantity +/- buttons and item removal
- Cache-busting on all fetch requests

### Triggers
- `[data-offcanvas-cart]` - Any element with this attribute opens the cart
- After add-to-cart, Shopware's native plugin opens the cart

---

## RavenToastPlugin

Modern toast notifications appearing in top-right corner.

### Global API
```javascript
window.ravenToast('success', 'Item added to cart');
window.ravenToast('error', 'Invalid credentials');
window.ravenToast('warning', 'Low stock warning');
window.ravenToast('info', 'Processing...');
window.ravenToast('success', 'Custom', 8000); // Custom duration
```

### Types & Durations
| Type | Color | Default Duration |
|------|-------|------------------|
| success | Gold (#D4A847) | 3000ms |
| error | Red | 5000ms |
| warning | Amber | 4000ms |
| info | Blue | 4000ms |

### Features
- Auto-intercepts Shopware `.alert` elements and converts to toasts
- Pause timer on hover, resume on mouse leave
- Click anywhere on toast to dismiss
- XSS protection via `_escapeHtml()`
- Accessible: `role="alert"` and `aria-live="polite"`

### Login/Register Pages
These pages use custom templates without PluginManager. Toast script is **inlined directly** in:
- `views/storefront/page/account/login/index.html.twig`
- `views/storefront/page/account/register/index.html.twig`

---

## Registration

Plugins are registered in `main.js`:
```javascript
import RavenOffcanvasCartPlugin from './plugin/raven-offcanvas-cart.plugin';
import RavenToastPlugin from './plugin/raven-toast.plugin';

PluginManager.register('RavenOffcanvasCart', RavenOffcanvasCartPlugin, '[data-raven-offcanvas-cart]');
PluginManager.register('RavenToast', RavenToastPlugin, 'body');
```
