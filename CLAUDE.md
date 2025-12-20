# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Swiss firearms e-commerce platform for RAVEN WEAPON AG built on Shopware 6.6/6.7 with custom RavenTheme.

- **Production:** https://ravenweapon.ch / https://shop.ravenweapon.ch (CHF currency)
- **CDN/SSL:** Cloudflare (Full strict SSL mode)
- **Tech Stack:** Shopware 6.6.0.0, Docker (dockware), PHP 8.3, MySQL 8, Twig, SCSS
- **Products:** 193+ Snigel tactical gear products, firearms, ammunition
- **Server:** Hetzner (77.42.19.154) - Helsinki datacenter

> **Note:** Domain migrated from ortak.ch to ravenweapon.ch on 2025-12-19. Do NOT use ortak.ch anymore.

## Development Commands

### Local Setup
```bash
docker-compose up -d

# Activate theme (after fresh install)
docker exec ravenweapon-shop bash -c "cd /var/www/html && \
  bin/console plugin:refresh && \
  bin/console plugin:install RavenTheme --activate && \
  bin/console theme:change RavenTheme --all && \
  bin/console cache:clear"
```

### Theme Development Workflow
```bash
# After editing files in shopware-theme/RavenTheme/
docker cp shopware-theme/RavenTheme ravenweapon-shop:/var/www/html/custom/plugins/
docker exec ravenweapon-shop bash -c "cd /var/www/html && bin/console theme:compile && bin/console cache:clear"
```

### Production Deployment (one-liner)
```bash
scp -r shopware-theme/RavenTheme root@77.42.19.154:/tmp/ && ssh root@77.42.19.154 "docker cp /tmp/RavenTheme shopware-chf:/var/www/html/custom/plugins/ && docker exec shopware-chf bash -c 'cd /var/www/html && bin/console theme:compile && bin/console cache:clear'"
```

### Common Shopware Console Commands
```bash
bin/console cache:clear           # Clear all caches
bin/console theme:compile         # Recompile theme SCSS/JS
bin/console plugin:refresh        # Detect plugin changes
bin/console assets:install        # Reinstall public assets
bin/console database:migrate --all
bin/console raven:assign-manufacturers  # Auto-assign manufacturers by SKU prefix
```

### Scraping Scripts (Node.js)
```bash
cd scripts && npm install
node snigel-variants-scraper.js           # Scrape product variants & colors
node snigel-description-scraper-fast.js   # Scrape descriptions
```

### Import Scripts (PHP)
```bash
cd scripts
php shopware-import.php              # Import products to Shopware
php shopware-upload-images.php       # Upload product images
php shopware-update-prices.php       # Update prices
php shopware-update-descriptions.php # Update descriptions & categories
```

## Architecture

### Directory Structure
```
ravenweapon/
├── shopware-theme/RavenTheme/    # Custom Shopware 6 theme plugin
│   └── src/
│       ├── Controller/           # Custom route controllers
│       ├── Subscriber/           # Event subscribers (cart, breadcrumbs)
│       └── Resources/
│           ├── config/services.xml    # Symfony DI configuration
│           ├── app/storefront/src/    # SCSS + JS source files
│           └── views/storefront/      # 48 Twig templates
├── scripts/                      # PHP/JS data management scripts (90+ files)
│   ├── snigel-merged-products.json    # Main product data (193 products)
│   └── snigel-data/images/            # 2,491 product images
├── PayrexxPaymentGateway/        # Swiss payment plugin (Payrexx)
├── assets/                       # Brand assets, logos
└── docker-compose.yml            # Local development
```

### Theme Controllers

**ManufacturerPageController** (`/hersteller/{slug}`)
- Displays brand pages with filtered product grids
- Loads products via SalesChannelRepository (proper CHF pricing)

**LegalPagesController** (`/agb`, `/impressum`, `/datenschutz`)
- Renders static legal pages with custom templates

### Event Subscribers

Registered in `services.xml`:
- `ProductDetailSubscriber`: Sets SEO category for breadcrumb navigation
- `CartLineItemSubscriber`: Captures `selectedColor` from POST and stores in line item payload
- `HomepageProductsSubscriber`: Loads navigation categories for header dropdowns

### JavaScript Plugins

**RavenOffcanvasCartPlugin** (`plugin/raven-offcanvas-cart.plugin.js`)
- AJAX-based add-to-cart (no page refresh)
- Off-canvas cart sidebar (fixed position, 420px desktop / 100% mobile)
- Exposed methods: `window.ravenOpenCart()`, `ravenCloseCart()`, `ravenRefreshCart()`

**RavenToastPlugin** (`plugin/raven-toast.plugin.js`)
- Modern toast notifications appearing in top-right corner
- 4 types: `success` (gold), `error` (red), `warning` (yellow), `info` (blue)
- Auto-dismiss with configurable duration, pause on hover
- Usage: `window.ravenToast('success', 'Message here')`
- **IMPORTANT:** Login/Register pages use custom templates that don't load Shopware's PluginManager. Toast script is inlined directly in those templates (see `page/account/login/index.html.twig` and `page/account/register/index.html.twig`)
- To intercept Shopware flash messages site-wide, the plugin looks for `.alert` elements

### Twig Template Inheritance

Use `{% sw_extends %}` to extend Shopware base templates:
```twig
{% sw_extends '@Storefront/storefront/page/product-detail/index.html.twig' %}
{% block page_product_detail_buy %}
    {# Custom buy box content #}
{% endblock %}
```

### Product Data Flow (Snigel)
```
Snigel B2B Portal → Playwright Scraper → JSON files → PHP Import → Shopware API → Custom Fields → Twig Display
```

**Custom Fields** for Snigel products:
- `snigel_color_options`: JSON array of color variants `[{name, value}]`
- `snigel_has_colors`: Boolean flag for color selector display

## Critical Guidelines

### German Language
**ALWAYS use proper umlauts** - NEVER ASCII replacements:
- ✅ für, Bestätigung, Übersicht, zurück, verfügbar
- ❌ fuer, Bestaetigung, Uebersicht, zurueck, verfuegbar

### Shopware Development
- Use `bin/console` commands, never manually edit database
- Always clear cache after template/config changes
- Extend templates with `{% sw_extends %}`, don't replace entirely
- Register services/subscribers in `services.xml`
- Navigation depth is set to 5 levels for RAPAX category structure

### Common Gotchas
- After ANY Twig template change, ALWAYS run `theme:compile && cache:clear`
- The `raven-logo.png` (4000x4000px) requires CSS overflow cropping due to whitespace around logo
- Use `{% sw_extends %}` not `{% extends %}` for Shopware templates
- Twig must be explicitly injected into controllers for Shopware 6.6+

### Containers
| Container | Purpose | Ports |
|-----------|---------|-------|
| `shopware-chf` | Production (CHF) | 80/443 |
| `ravenweapon-shop` | Local dev | 80/443, 8888 |

### Branding Colors
| Color | Hex | Usage |
|-------|-----|-------|
| Gold Gradient | `#FDE047 → #F59E0B → #D97706` | Headlines, CTAs |
| Dark Background | `#111827` | Headers, cards |
| Price Red | `#E53935` | Prices |
| Available Green | `#77C15A` | Stock status |

Typography: Chakra Petch (700) for headlines, Inter (400/500/600) for body.

## Logo Handling (Login/Register Pages)

The `raven-logo.png` is A4-sized (4000x4000px) with logo centered in whitespace. **DO NOT simply set height/width** - use CSS overflow cropping:

```html
<div style="height: 60px; overflow: hidden; display: flex; align-items: center; justify-content: center;">
    <img src="{{ asset('bundles/raventheme/assets/raven-logo.png') }}"
         alt="Raven Weapon"
         style="height: 200px; width: auto; object-fit: contain;">
</div>
```

Container `height: 60px` with `overflow: hidden` acts as a window; image `height: 200px` makes the actual logo fill that window.

## Key Files Reference

### Theme Core
| Purpose | Path |
|---------|------|
| Main SCSS | `shopware-theme/RavenTheme/src/Resources/app/storefront/src/scss/base.scss` |
| JS Entry | `shopware-theme/RavenTheme/src/Resources/app/storefront/src/main.js` |
| Cart Plugin | `shopware-theme/RavenTheme/src/Resources/app/storefront/src/plugin/raven-offcanvas-cart.plugin.js` |
| Services DI | `shopware-theme/RavenTheme/src/Resources/config/services.xml` |
| Theme Config | `shopware-theme/RavenTheme/src/Resources/theme.json` |

### Key Templates (under `views/storefront/`)
| Purpose | Path |
|---------|------|
| Header | `layout/header/header.html.twig` |
| Footer | `layout/footer/footer.html.twig` |
| Product detail | `page/product-detail/index.html.twig` |
| Product card | `component/product/card/box-standard.html.twig` |
| Off-canvas cart | `component/checkout/offcanvas-cart.html.twig` |
| Cart page | `page/checkout/cart/index.html.twig` |
| Login | `page/account/login/index.html.twig` |
| Register | `page/account/register/index.html.twig` |
| Brand pages | `page/manufacturer/index.html.twig` |
| Category/navigation | `page/navigation/index.html.twig` |

## SEO URLs & Breadcrumbs (CRITICAL!)

### Language Configuration (VERY IMPORTANT!)

The sales channel uses **English language** but with **German category slugs**:

| Setting | Value |
|---------|-------|
| Sales Channel ID | `0191c12dd4b970949e9aeec40433be3e` |
| Sales Channel Language | **English** (`2fbb5fe2e29a4d70aa5854ce7ce3e20b`) |
| Category SEO Slugs | **German** (e.g., `taschen-rucksaecke` NOT `bags-backpacks`) |

**CRITICAL:** All SEO URLs in `seo_url` table MUST use the **English language ID** to work correctly. If you create SEO URLs with German language ID, they will NOT be found by Shopware's `seoUrl()` function.

### Correct SEO URL Examples

```
Category: /ausruestung/taschen-transport/taschen-rucksaecke/
Product:  /ausruestung/taschen-transport/taschen-rucksaecke/100l-backpack-20
```

### Regenerating Product SEO URLs

When products are imported via API or SEO URLs are broken, use the fixed script:

```bash
# Copy script to server and run
scp scripts/generate-product-seo-urls-fixed.php root@77.42.19.154:/tmp/
ssh root@77.42.19.154 "docker cp /tmp/generate-product-seo-urls-fixed.php shopware-chf:/tmp/"
ssh root@77.42.19.154 "docker exec shopware-chf php /tmp/generate-product-seo-urls-fixed.php"
ssh root@77.42.19.154 "docker exec shopware-chf bash -c 'cd /var/www/html && bin/console cache:clear'"
```

The script `generate-product-seo-urls-fixed.php`:
- Uses **English language ID** (`2fbb5fe2e29a4d70aa5854ce7ce3e20b`)
- Builds paths from category hierarchy using `path` field
- Creates SEO URLs like: `/ausruestung/taschen-transport/taschen-rucksaecke/product-name`

### The Right Way: Use Shopware's Native Systems

**DO NOT** build URLs manually or hardcode category mappings. Let Shopware handle it:

```twig
{# CORRECT: Use Shopware's native seoUrl function #}
{{ seoUrl('frontend.detail.page', {'productId': product.id}) }}
{{ seoUrl('frontend.navigation.page', {'navigationId': category.id}) }}

{# WRONG: Manual URL building #}
/{{ category.name|lower|replace({' ': '-'}) }}/{{ product.name|slugify }}/
```

### Requirements for Native SEO URLs to Work

1. **main_category must be set** for every product (determines breadcrumb path)
2. **seo_url entries must exist** in database with **English language ID**
3. **SEO URL template** configured in Admin → Settings → SEO

### Setting main_category for Products

Every product needs a `main_category` entry to determine its canonical category for breadcrumbs:

```sql
-- Check products without main_category
SELECT p.product_number, pt.name
FROM product p
JOIN product_translation pt ON p.id = pt.product_id
LEFT JOIN main_category mc ON p.id = mc.product_id
WHERE mc.product_id IS NULL AND p.parent_id IS NULL;
```

### Debugging SEO/Breadcrumb Issues

```sql
-- Check if product has SEO URL with CORRECT language
SELECT seo_path_info, is_canonical, LOWER(HEX(language_id)) as lang_id
FROM seo_url
WHERE LOWER(HEX(foreign_key)) = '<product_id>'
AND route_name = 'frontend.detail.page';

-- Verify language is English (2fbb5fe2e29a4d70aa5854ce7ce3e20b)
-- If it shows German language ID, the URL won't work!

-- Check product's main_category
SELECT ct.name FROM main_category mc
JOIN category_translation ct ON mc.category_id = ct.category_id
WHERE LOWER(HEX(mc.product_id)) = '<product_id>';

-- Count products with correct SEO URLs
SELECT COUNT(*) FROM seo_url
WHERE route_name = 'frontend.detail.page'
AND is_canonical = 1 AND is_deleted = 0
AND LOWER(HEX(language_id)) = '2fbb5fe2e29a4d70aa5854ce7ce3e20b';
```

### Common Issues & Fixes

| Problem | Cause | Fix |
|---------|-------|-----|
| `/detail/uuid` URLs | Missing seo_url entries OR wrong language ID | Run `generate-product-seo-urls-fixed.php` |
| 404 on breadcrumb links | Category SEO URLs missing or wrong language | Check seo_url table for correct language |
| Wrong category in breadcrumb | main_category not set | Set main_category for product |
| SEO URLs exist but not working | Created with German language ID instead of English | Regenerate with correct English language ID |

### ProductDetailSubscriber for Breadcrumbs

The `ProductDetailSubscriber` loads breadcrumb categories with SEO URLs filtered by current language:

```php
// Filter seoUrls by current language to get correct SEO paths
$criteria->getAssociation('seoUrls')
    ->addFilter(new EqualsFilter('languageId', $context->getContext()->getLanguageId()))
    ->addFilter(new EqualsFilter('isCanonical', true));
```

This means SEO URLs MUST exist with the sales channel's language ID (English) to appear in breadcrumbs.

### PHP Classes
| Purpose | Path |
|---------|------|
| Manufacturer controller | `shopware-theme/RavenTheme/src/Controller/ManufacturerPageController.php` |
| Product subscriber | `shopware-theme/RavenTheme/src/Subscriber/ProductDetailSubscriber.php` |
| Cart subscriber | `shopware-theme/RavenTheme/src/Subscriber/CartLineItemSubscriber.php` |

## Testing

No automated tests in this codebase. Manual browser testing is primary method. Use Playwright scripts in `scripts/` for scraping verification.

## Domain & Hosting Configuration

### Production Domain
- **Primary:** https://ravenweapon.ch
- **Alternate:** https://shop.ravenweapon.ch
- **Old domain (deprecated):** ortak.ch - DO NOT USE

### Cloudflare Setup
- **SSL Mode:** Full (strict)
- **DNS Records:**
  - A `@` → 77.42.19.154 (Proxied)
  - A `www` → 77.42.19.154 (Proxied)
  - A `shop` → 77.42.19.154 (Proxied)
  - MX `@` → mta-gw.infomaniak.ch (DNS only - for email)

### Domain Registrar
- **Provider:** Infomaniak
- **Nameservers:** Cloudflare (carla.ns.cloudflare.com, tom.ns.cloudflare.com)

### Server (Hetzner)
- **IP:** 77.42.19.154
- **Location:** Helsinki
- **Container:** shopware-chf (dockware)

### Shopware Sales Channel Domains
```sql
-- Current configured domains:
https://ravenweapon.ch
https://shop.ravenweapon.ch
http://shop.ravenweapon.ch
http://77.42.19.154
```

### SSL Certificate
- **CDN SSL:** Cloudflare (auto-managed, auto-renewing)
- **Origin SSL:** Let's Encrypt (inside container at /etc/apache2/ssl/)

### APP_URL Configuration
The `.env` file in the container should have:
```
APP_URL=https://shop.ravenweapon.ch
```

### Backups
Pre-migration backups stored in: `backups/2025-12-19-pre-domain-migration/`
- Database dump (47MB)
- Theme backup
- Domain configuration docs
