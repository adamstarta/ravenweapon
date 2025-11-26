# RAVEN Shopware Branding Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Apply RAVEN luxury branding (gold buttons, dark text, white backgrounds) to Shopware's default storefront using SCSS variable overrides.

**Architecture:** Override Shopware's SCSS variables in `overrides.scss` to change colors globally. Add custom button and heading styles in `base.scss`. Update footer template to include logo. All default Shopware pages (account, cart, checkout) automatically inherit the branding.

**Tech Stack:** Shopware 6.6, Twig templates, SCSS, Docker (ravenweapon-shop container)

---

### Task 1: Update SCSS Variable Overrides

**Files:**
- Modify: `/var/www/html/custom/plugins/RavenTheme/src/Resources/app/storefront/src/scss/overrides.scss`

**Step 1: Create the SCSS variable overrides file**

```scss
// RAVEN WEAPON AG - SCSS Variable Overrides
// These variables override Shopware's default theme colors

// === PRIMARY BRAND COLORS ===
$sw-color-brand-primary: #F59E0B;           // Gold - links, accents
$sw-color-brand-secondary: #1f2937;         // Dark gray - secondary elements

// === TEXT COLORS ===
$sw-text-color: #374151;                    // Body text (gray-700)
$sw-headline-color: #111827;                // Headings (gray-900)

// === BACKGROUNDS ===
$sw-background-color: #FFFFFF;              // White backgrounds

// === BORDERS ===
$sw-border-color: #E5E7EB;                  // Light gray (gray-200)

// === STATUS COLORS ===
$sw-color-success: #10B981;                 // Green
$sw-color-info: #3B82F6;                    // Blue
$sw-color-warning: #F59E0B;                 // Amber
$sw-color-danger: #EF4444;                  // Red

// === BUTTON COLORS ===
$sw-color-buy-button: #F59E0B;              // Gold base (gradient applied in base.scss)
$sw-color-buy-button-text: #000000;         // Black text on gold

// === TYPOGRAPHY ===
$sw-font-family-base: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
$sw-font-family-headline: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
```

**Step 2: Copy file to Docker container**

Run:
```bash
docker cp overrides.scss ravenweapon-shop:/var/www/html/custom/plugins/RavenTheme/src/Resources/app/storefront/src/scss/overrides.scss
```

Expected: File copied successfully (no output)

---

### Task 2: Update Base SCSS with Custom Styles

**Files:**
- Modify: `/var/www/html/custom/plugins/RavenTheme/src/Resources/app/storefront/src/scss/base.scss`

**Step 1: Create comprehensive base.scss with RAVEN styles**

```scss
// RAVEN WEAPON AG - Custom Component Styles
// Applied after Shopware defaults, uses !important where needed

// =============================================================================
// GOLD GRADIENT BUTTONS
// =============================================================================

.btn-primary,
.btn-buy,
.product-detail-buy .btn,
.checkout-aside-action .btn,
.login-submit .btn,
.register-submit .btn,
.account-content .btn-primary,
.cart-actions .btn-primary {
  background: linear-gradient(135deg, #FDE047 0%, #F59E0B 50%, #D97706 100%) !important;
  color: #000000 !important;
  border: none !important;
  font-weight: 600 !important;
  text-shadow: none !important;
  transition: all 0.2s ease !important;

  &:hover,
  &:focus {
    background: linear-gradient(135deg, #FDE047 0%, #D97706 50%, #B45309 100%) !important;
    color: #000000 !important;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(245, 158, 11, 0.4);
  }

  &:active {
    transform: translateY(0);
  }
}

// =============================================================================
// H1 GOLD GRADIENT TEXT
// =============================================================================

h1, .h1,
.cms-element-text h1,
.product-detail-name {
  background: linear-gradient(135deg, #FDE047, #F59E0B, #D97706) !important;
  -webkit-background-clip: text !important;
  -webkit-text-fill-color: transparent !important;
  background-clip: text !important;
  font-weight: 700 !important;
}

// =============================================================================
// H2+ DARK TEXT
// =============================================================================

h2, h3, h4, h5, h6,
.h2, .h3, .h4, .h5, .h6 {
  color: #111827 !important;
  font-weight: 600;
}

// =============================================================================
// BODY & LINKS
// =============================================================================

body {
  color: #374151;
  background-color: #FFFFFF;
}

a {
  color: #D97706;

  &:hover {
    color: #B45309;
  }
}

// =============================================================================
// FORM INPUTS
// =============================================================================

.form-control,
.custom-select,
input[type="text"],
input[type="email"],
input[type="password"],
input[type="search"],
textarea {
  border-color: #E5E7EB !important;
  background-color: #FFFFFF !important;
  color: #374151 !important;

  &:focus {
    border-color: #F59E0B !important;
    box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.1) !important;
  }

  &::placeholder {
    color: #9CA3AF !important;
  }
}

// =============================================================================
// CARDS & PRODUCT BOXES
// =============================================================================

.card,
.product-box,
.cms-element-product-box {
  border-color: #E5E7EB !important;
  background: #FFFFFF !important;
}

.card-title,
.product-name {
  color: #111827 !important;
}

.product-price {
  color: #111827 !important;
  font-weight: 700 !important;
}

// =============================================================================
// NAVIGATION ACTIVE STATES
// =============================================================================

.nav-link.active,
.page-item.active .page-link {
  background: linear-gradient(135deg, #FDE047 0%, #F59E0B 50%, #D97706 100%) !important;
  color: #000000 !important;
  border-color: transparent !important;
}

// =============================================================================
// ALERTS & BADGES
// =============================================================================

.badge-primary,
.alert-primary {
  background: linear-gradient(135deg, #FDE047 0%, #F59E0B 50%, #D97706 100%) !important;
  color: #000000 !important;
}

// =============================================================================
// CHECKOUT PROGRESS
// =============================================================================

.checkout-step.is-current,
.checkout-step.is-completed {
  .checkout-step-icon {
    background: linear-gradient(135deg, #FDE047 0%, #F59E0B 50%, #D97706 100%) !important;
    color: #000000 !important;
  }
}

// =============================================================================
// FOOTER ADJUSTMENTS
// =============================================================================

.footer-main {
  background: #FFFFFF !important;
  border-top: 1px solid #E5E7EB !important;
}
```

**Step 2: Copy file to Docker container**

Run:
```bash
docker cp base.scss ravenweapon-shop:/var/www/html/custom/plugins/RavenTheme/src/Resources/app/storefront/src/scss/base.scss
```

Expected: File copied successfully

---

### Task 3: Add Logo to Footer

**Files:**
- Modify: `/var/www/html/custom/plugins/RavenTheme/src/Resources/views/storefront/layout/footer/footer.html.twig`

**Step 1: Update footer template to include RAVEN logo**

Add logo section before the copyright in the footer template (after newsletter bar, before copyright):

```twig
{# Add after newsletter section, before copyright #}
<div style="text-align: center; padding: 2rem 0; background: #FFFFFF;">
    <a href="{{ path('frontend.home.page') }}">
        <img src="{{ asset('bundles/raventheme/assets/raven-logo-bold.svg') }}"
             alt="RAVEN WEAPON AG"
             style="height: 50px; width: auto;">
    </a>
</div>
```

**Step 2: Copy updated footer to Docker**

Run:
```bash
docker cp footer.html.twig ravenweapon-shop:/var/www/html/custom/plugins/RavenTheme/src/Resources/views/storefront/layout/footer/footer.html.twig
```

---

### Task 4: Clear Cache and Compile Theme

**Step 1: Clear Shopware cache**

Run:
```bash
docker exec ravenweapon-shop bash -c "cd /var/www/html && bin/console cache:clear"
```

Expected: "Cache for the dev environment was successfully cleared"

**Step 2: Compile theme**

Run:
```bash
docker exec ravenweapon-shop bash -c "cd /var/www/html && bin/console theme:compile"
```

Expected: "Compiling theme for sales channel" with timing info

---

### Task 5: Test Branding on Key Pages

**Step 1: Test Homepage**

Navigate to: `http://localhost/`
Verify:
- RAVEN logo in header
- Gold gradient buttons
- White background
- Dark text

**Step 2: Test Account Login**

Navigate to: `http://localhost/account/login`
Verify:
- Gold "Login" button
- H1 has gold gradient (if present)
- Form inputs have gold focus ring
- RAVEN header/footer present

**Step 3: Test Product Page**

Navigate to: `http://localhost/` and click any product
Verify:
- Gold "Add to Cart" button
- Product name in gold gradient (H1)
- Price in dark text

**Step 4: Test Cart**

Add item to cart, navigate to: `http://localhost/checkout/cart`
Verify:
- Gold "Checkout" button
- White background
- Dark text for totals

**Step 5: Test Account Dashboard (if logged in)**

Navigate to: `http://localhost/account`
Verify:
- Gold action buttons
- Dark text for labels
- RAVEN header/footer

---

### Task 6: Upload Favicon via Admin

**Step 1: Access Shopware Admin**

Navigate to: `http://localhost/admin`
Login with admin credentials

**Step 2: Go to Theme Settings**

Navigate to: Content → Themes → RavenTheme

**Step 3: Upload Favicon**

Scroll to "Media" section
Find: `sw-logo-favicon`
Upload: `raven-logo-bold.svg` (or create simplified icon version)

**Step 4: Save and Verify**

Click "Save"
Refresh storefront, check browser tab for favicon

---

### Task 7: Final Verification & Commit

**Step 1: Take screenshots of key pages**

- Homepage
- Account login
- Product detail
- Cart
- Checkout (if accessible)

**Step 2: Commit theme changes**

Run:
```bash
git add docs/plans/ shopware-theme/
git commit -m "feat: apply RAVEN luxury branding to Shopware storefront

- Gold gradient buttons (primary, buy, checkout)
- Gold gradient H1 headings
- Dark gray text on white backgrounds
- Logo in header, footer, favicon
- All Shopware default pages inherit branding"
```

---

## Verification Checklist

- [ ] Gold gradient on all primary buttons
- [ ] Gold gradient text on H1 headings
- [ ] Dark gray body text (#374151)
- [ ] White backgrounds everywhere
- [ ] Form inputs have gold focus ring
- [ ] Logo in header
- [ ] Logo in footer
- [ ] Favicon shows RAVEN logo
- [ ] Account pages styled correctly
- [ ] Cart page styled correctly
- [ ] Checkout flow styled correctly
- [ ] Product pages styled correctly
