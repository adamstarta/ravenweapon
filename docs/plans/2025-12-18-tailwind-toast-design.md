# Tailwind CSS Toast Notification System

**Date:** 2025-12-18
**Status:** Approved
**Scope:** Login/Authentication errors only

## Overview

Implement a modern toast notification system for RAVEN WEAPON AG's Shopware 6 store to display authentication errors (wrong password, login failures, registration errors) with a luxury aesthetic matching the brand.

## Design Decisions

### Scope
- **Login/Auth only**: Wrong password, login success, registration errors
- Future expansion possible for cart/checkout

### Position & Animation
- **Position**: Fixed top-right corner (24px from edges)
- **Animation**: Slide-in from right (300ms ease-out)
- **Z-index**: 9999 (above all other elements)

### Visual Design (Light Minimal)
- **Background**: White `#FFFFFF`
- **Shadow**: `0 10px 40px rgba(0,0,0,0.12)` - soft luxury shadow
- **Border**: 1px solid `#e5e7eb` with 4px left accent
- **Border-radius**: 14px
- **Padding**: 16px

### Toast Types & Colors

| Type | Left Accent | Icon Color | Icon |
|------|-------------|------------|------|
| Error | `#dc2626` | `#dc2626` | X circle |
| Success | `#D4A847` (Raven gold) | `#D4A847` | Checkmark |
| Warning | `#f59e0b` | `#f59e0b` | Alert triangle |

### Typography
- **Font**: Inter (matches site)
- **Size**: 15px
- **Color**: `#1f2937`
- **Close button**: `#9ca3af`, hover `#374151`

### Timing (Smart)
- **Error messages**: 5 seconds auto-dismiss
- **Success messages**: 3 seconds auto-dismiss
- **Click to dismiss**: Always available

## Technical Architecture

### Implementation Approach
JavaScript Plugin (Option B) - matches existing `RavenOffcanvasCartPlugin` pattern.

### File Structure

```
shopware-theme/RavenTheme/src/Resources/
├── app/storefront/src/
│   ├── main.js                          # Register plugin
│   ├── plugin/
│   │   └── raven-toast.plugin.js        # Toast plugin (~120 lines)
│   └── scss/
│       └── _toast.scss                  # Toast styles (~100 lines)
└── views/storefront/
    └── utilities/
        └── flash-messages.html.twig     # Modified for plugin
```

### Plugin API

```javascript
// Global API
window.ravenToast(type, message, duration?)
window.ravenToast('error', 'Ungültige E-Mail oder Passwort')
window.ravenToast('success', 'Erfolgreich angemeldet')

// Types: 'error', 'success', 'warning', 'info'
```

### Integration Flow

1. **Server-side errors**: Shopware generates flash messages → Plugin intercepts hidden `.alert` elements → Displays as toast
2. **AJAX errors**: Call `window.ravenToast()` directly
3. **Login form**: Works automatically with Shopware's authentication system

## Files to Create

### 1. `plugin/raven-toast.plugin.js`
- Extends `window.PluginBaseClass`
- Creates toast container
- Intercepts Shopware flash messages
- Exposes global `ravenToast()` API
- Handles auto-dismiss with smart timing

### 2. `scss/_toast.scss`
- Toast container positioning
- Toast card styling
- Icon styles for each type
- Slide-in/out animations
- Close button styling
- Mobile responsive adjustments

## Files to Modify

### 1. `main.js`
- Import and register `RavenToastPlugin`

### 2. `base.scss`
- Import `_toast.scss`
- Remove `.alert` hiding rules (lines 53-57)

### 3. `flash-messages.html.twig`
- Render hidden alert elements with data attributes
- Plugin reads these and converts to toasts

## HTML Structure

```html
<div id="raven-toast-container">
  <div class="raven-toast raven-toast--error" role="alert">
    <div class="raven-toast__icon">
      <svg>...</svg>
    </div>
    <div class="raven-toast__content">
      <span class="raven-toast__message">Ungültige E-Mail oder Passwort</span>
    </div>
    <button class="raven-toast__close" aria-label="Schließen">
      <svg>...</svg>
    </button>
  </div>
</div>
```

## Testing Plan

1. Navigate to production login page
2. Enter incorrect email/password
3. Submit form
4. Verify toast appears top-right
5. Verify error styling (red accent)
6. Verify auto-dismiss after 5 seconds
7. Test manual dismiss via X button
8. Test on mobile viewport

## German Translations

| Key | German |
|-----|--------|
| Close button aria-label | "Schließen" |
| Login error | "Ungültige E-Mail oder Passwort" |
| Login success | "Erfolgreich angemeldet" |

## Browser Support

- Chrome (latest)
- Firefox (latest)
- Safari (latest)
- Edge (latest)
- Mobile Safari/Chrome
