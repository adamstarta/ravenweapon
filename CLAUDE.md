# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Swiss firearms e-commerce platform for RAVEN WEAPON AG built on Shopware 6.6/6.7 with custom RavenTheme.

- **Production:** https://ortak.ch (CHF currency)
- **Tech Stack:** Shopware 6.7.5.0, Docker (dockware), PHP 8.3, MySQL 8, Twig, SCSS
- **Products:** 193+ Snigel tactical gear products, firearms, ammunition

## Development Commands

### Local Setup
```bash
# Start local environment
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

### Production Deployment
```bash
# SSH to server
ssh root@77.42.19.154

# Deploy theme changes
docker cp /path/to/RavenTheme shopware-chf:/var/www/html/custom/plugins/
docker exec shopware-chf bash -c "cd /var/www/html && bin/console theme:compile && bin/console cache:clear"
```

### Common Shopware Console Commands
```bash
bin/console cache:clear           # Clear all caches
bin/console theme:compile         # Recompile theme SCSS/JS
bin/console plugin:refresh        # Detect plugin changes
bin/console assets:install        # Reinstall public assets
bin/console database:migrate --all
```

### Scraping Scripts (Node.js)
```bash
cd scripts
npm install
node snigel-variants-scraper.js   # Scrape product variants & colors
node snigel-description-scraper-fast.js  # Scrape descriptions
```

### Import Scripts (PHP)
```bash
cd scripts
php shopware-import.php           # Import products
php shopware-upload-images.php    # Upload product images
php shopware-update-prices.php    # Update prices
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
│           ├── app/storefront/src/scss/base.scss  # Main stylesheet (2,133 lines)
│           └── views/storefront/      # Twig templates
├── scripts/                      # PHP/JS data management scripts
│   ├── snigel-merged-products.json    # Main product data
│   └── snigel-data/images/       # 2,491 product images
├── PayrexxPaymentGateway/        # Payment plugin
└── docker-compose.yml            # Local development
```

### Key Shopware Patterns

**Event Subscribers** - Business logic hooks registered in `services.xml`:
- `ProductDetailSubscriber`: Sets SEO category for breadcrumbs
- `CartLineItemSubscriber`: Captures selected color variant on add-to-cart

**Custom Controllers** - Extend `StorefrontController`:
- `ManufacturerPageController`: Brand pages at `/hersteller/{slug}`
- `LegalPagesController`: AGB, privacy, imprint pages

**Twig Template Inheritance** - Use `{% sw_extends %}` to extend Shopware base templates and override specific blocks.

**Dependency Injection** - Services configured in `src/Resources/config/services.xml` with Symfony DI syntax.

### Product Data Flow (Snigel)
```
B2B Portal → Playwright Scraper → JSON files → PHP Import → Shopware API → Custom Fields → Twig Display
```

Custom fields store variant data: `snigel_color_options`, `snigel_has_colors`

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

### Containers
| Container | Purpose | Ports |
|-----------|---------|-------|
| `shopware-chf` | Production (CHF) | 80/443 |
| `ravenweapon-shop` | Local dev | 80/443, 8888 |

### API Credentials (for import scripts)
```
Client ID: SWIAC3HJVHFJMHQYRWRUM1E1SG
Client Secret: RGtsN1Z2TklqU1ZZSVFTOFB6bWZXNWZNNk40V2h4RWY5Q2tPblg
```

### Branding Colors
| Color | Hex | Usage |
|-------|-----|-------|
| Gold Gradient | `#FDE047 → #F59E0B → #D97706` | Headlines, CTAs |
| Dark Background | `#111827` | Headers, cards |
| Price Red | `#E53935` | Prices |
| Available Green | `#77C15A` | Stock status |

Typography: Chakra Petch (700) for headlines, Inter (400/500/600) for body.

## Key Files Reference

| Purpose | Path |
|---------|------|
| Main SCSS | `shopware-theme/RavenTheme/src/Resources/app/storefront/src/scss/base.scss` |
| Header | `shopware-theme/.../views/storefront/layout/header/header.html.twig` |
| Homepage | `shopware-theme/.../views/storefront/page/content/index.html.twig` |
| Product detail | `shopware-theme/.../views/storefront/page/product-detail/index.html.twig` |
| Product card | `shopware-theme/.../views/storefront/component/product/card/box-standard.html.twig` |
| Services config | `shopware-theme/RavenTheme/src/Resources/config/services.xml` |
| Product data | `scripts/snigel-merged-products.json` |
