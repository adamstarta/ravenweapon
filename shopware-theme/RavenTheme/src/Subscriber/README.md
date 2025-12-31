# RavenTheme Event Subscribers

Symfony event subscribers for custom Shopware behavior.

## CartLineItemSubscriber

Captures variant selections (color/size) from add-to-cart requests.

**Event:** `BeforeLineItemAddedEvent` (priority: 100)

**Captured Fields:**
- `selectedColor` - Color variant name
- `selectedSize` - Size variant name
- `variantenDisplay` - Combined display string (e.g., "Black / Large")
- `selectedSizePrice` - Variant-specific price (triggers price override)

**Price Override:**
When `selectedSizePrice` is provided, creates a new `QuantityPriceDefinition` with Swiss VAT (8.1%) to override the default product price.

---

## ProductDetailSubscriber

Loads breadcrumb categories with correct SEO URLs for product pages.

**Event:** `ProductPageLoadedEvent`

**Flow:**
1. Looks up product's `main_category` entry
2. Loads category with SEO URLs filtered by **current language ID**
3. Parses category `path` to get parent category IDs
4. Loads all parent categories with their SEO URLs
5. Stores as page extension: `page.extensions.breadcrumbCategories`

**Critical:** SEO URLs must use **English language ID** (`2fbb5fe2e29a4d70aa5854ce7ce3e20b`) even though slugs are German. Wrong language = 404 on breadcrumb links.

---

## OrderNotificationSubscriber

Sends admin email notifications when orders are placed.

**Event:** `CheckoutOrderPlacedEvent`

**Recipients:** Defined in `ADMIN_EMAILS` constant:
- `mirco@ravenweapon.ch`
- `business.mitrovic@gmail.com`

**SMTP:** Uses `MAILER_DSN` directly from `.env` (info@ravenweapon.ch via Infomaniak)

**Features:**
- Rich HTML email with Raven gold branding
- Product thumbnail images in order items
- Extracts variant info from line item payload
- Swiss CHF formatting with apostrophes (CHF 1'234.56)
- "Show in admin panel" button linking to backend

---

## NavigationProductsSubscriber

Loads products for navigation dropdown menus.

**Event:** `HeaderPageletLoadedEvent`

**Logic:**
- Finds level 3 categories without level 4 children
- Loads max 8 products per category
- Stores as header extension: `navigationProducts`

---

## HomepageProductsSubscriber

Loads featured products for homepage.

**Events:** `NavigationPageLoadedEvent`, `LandingPageLoadedEvent`

**Products:**
- 3 "CALIBER KIT" products
- 3 "RAVEN" rifle products (excluding Caliber Kits)

---

## LogoutRedirectSubscriber

Redirects to homepage after logout (instead of login page).

**Events:** `CustomerLogoutEvent`, `KernelEvents::RESPONSE`

---

## Registration

All subscribers are registered in `config/services.xml` with proper DI.
