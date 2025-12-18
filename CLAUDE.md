# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Swiss firearms e-commerce platform for RAVEN WEAPON AG built on Shopware 6.6/6.7 with custom RavenTheme.

- **Production:** https://ortak.ch (CHF currency)
- **Tech Stack:** Shopware 6.6.0.0, Docker (dockware), PHP 8.3, MySQL 8, Twig, SCSS
- **Products:** 193+ Snigel tactical gear products, firearms, ammunition

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

### PHP Classes
| Purpose | Path |
|---------|------|
| Manufacturer controller | `shopware-theme/RavenTheme/src/Controller/ManufacturerPageController.php` |
| Product subscriber | `shopware-theme/RavenTheme/src/Subscriber/ProductDetailSubscriber.php` |
| Cart subscriber | `shopware-theme/RavenTheme/src/Subscriber/CartLineItemSubscriber.php` |

## Testing

No automated tests in this codebase. Manual browser testing is primary method. Use Playwright scripts in `scripts/` for scraping verification.
