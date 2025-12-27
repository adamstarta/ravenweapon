# CLAUDE.md

Guidance for Claude Code when working with this repository.

## Project Overview

Swiss firearms e-commerce platform for RAVEN WEAPON AG built on Shopware 6.6 with custom RavenTheme.

- **Production:** https://ravenweapon.ch
- **CDN/SSL:** Cloudflare (Full strict SSL mode)
- **Tech Stack:** Shopware 6.6.0.0, Docker (dockware), PHP 8.3, MySQL 8, Twig, SCSS
- **Server:** Hetzner (77.42.19.154)
- **Container:** `shopware-chf`

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

### Shopware Console Commands
```bash
bin/console cache:clear           # Clear all caches
bin/console theme:compile         # Recompile theme SCSS/JS
bin/console plugin:refresh        # Detect plugin changes
bin/console assets:install        # Reinstall public assets
bin/console database:migrate --all
```

## Architecture

### Directory Structure
```
ravenweapon/
├── shopware-theme/RavenTheme/    # Custom Shopware 6 theme plugin
│   └── src/
│       ├── Controller/           # Custom route controllers
│       ├── Subscriber/           # Event subscribers
│       └── Resources/
│           ├── config/services.xml
│           ├── app/storefront/src/    # SCSS + JS source
│           └── views/storefront/      # Twig templates
├── scripts/server/               # Server-side utility scripts
├── PayrexxPaymentGateway/        # Swiss payment plugin
├── assets/                       # Brand assets, logos
└── docker-compose.yml
```

### Theme Controllers

**ManufacturerPageController** (`/hersteller/{slug}`)
- Displays brand pages with filtered product grids

**LegalPagesController** (`/agb`, `/impressum`, `/datenschutz`)
- Renders static legal pages

### Event Subscribers

Registered in `services.xml`:
- `ProductDetailSubscriber`: Sets SEO category for breadcrumb navigation
- `CartLineItemSubscriber`: Captures `selectedColor` from POST
- `HomepageProductsSubscriber`: Loads navigation categories for header

### JavaScript Plugins

**RavenOffcanvasCartPlugin** (`plugin/raven-offcanvas-cart.plugin.js`)
- AJAX-based add-to-cart
- Off-canvas cart sidebar
- Methods: `window.ravenOpenCart()`, `ravenCloseCart()`, `ravenRefreshCart()`

**RavenToastPlugin** (`plugin/raven-toast.plugin.js`)
- Toast notifications (success, error, warning, info)
- Usage: `window.ravenToast('success', 'Message here')`

### Twig Template Inheritance

Use `{% sw_extends %}` to extend Shopware base templates:
```twig
{% sw_extends '@Storefront/storefront/page/product-detail/index.html.twig' %}
{% block page_product_detail_buy %}
    {# Custom content #}
{% endblock %}
```

## Development Guidelines

### Shopware Best Practices
- Use `bin/console` commands, never manually edit database
- Always clear cache after template/config changes
- Extend templates with `{% sw_extends %}`, don't replace entirely
- Register services/subscribers in `services.xml`
- Navigation depth is set to 5 levels for RAPAX category structure

### Common Gotchas
- After ANY Twig template change: `theme:compile && cache:clear`
- Use `{% sw_extends %}` not `{% extends %}` for Shopware templates
- Twig must be explicitly injected into controllers for Shopware 6.6+

### Branding Colors
| Color | Hex | Usage |
|-------|-----|-------|
| Gold Gradient | `#FDE047 → #F59E0B → #D97706` | Headlines, CTAs |
| Dark Background | `#111827` | Headers, cards |
| Price Red | `#E53935` | Prices |
| Available Green | `#77C15A` | Stock status |

Typography: Chakra Petch (700) for headlines, Inter (400/500/600) for body.

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
| Login | `page/account/login/index.html.twig` |
| Register | `page/account/register/index.html.twig` |

## SEO URLs & Breadcrumbs

### Language Configuration

The sales channel uses **English language** but with **German category slugs**:

| Setting | Value |
|---------|-------|
| Sales Channel ID | `0191c12dd4b970949e9aeec40433be3e` |
| Sales Channel Language | English (`2fbb5fe2e29a4d70aa5854ce7ce3e20b`) |
| Category SEO Slugs | German (e.g., `taschen-rucksaecke`) |

All SEO URLs in `seo_url` table must use the English language ID.

### Regenerating SEO URLs

Use the scripts in `scripts/server/`:

```bash
# Copy script to container and run
docker cp scripts/server/generate-product-seo-urls-fixed.php shopware-chf:/tmp/
docker exec shopware-chf php /tmp/generate-product-seo-urls-fixed.php
docker exec shopware-chf bash -c "cd /var/www/html && bin/console cache:clear"
```

### The Right Way: Use Shopware's Native Systems

```twig
{# CORRECT: Use Shopware's native seoUrl function #}
{{ seoUrl('frontend.detail.page', {'productId': product.id}) }}
{{ seoUrl('frontend.navigation.page', {'navigationId': category.id}) }}
```

## Server Utility Scripts

Located in `scripts/server/`:

| Script | Purpose |
|--------|---------|
| `check-nav-categories.php` | Debug navbar category structure |
| `check-seo-template-config.php` | Check SEO URL configuration |
| `fix-all-main-categories.php` | Set main_category for breadcrumbs |
| `generate-product-seo-urls-fixed.php` | Generate product SEO URLs |
| `generate-category-seo-urls-fixed.php` | Generate category SEO URLs |

Run inside container:
```bash
docker exec shopware-chf php /path/to/script.php
```

## Testing

No automated tests. Manual browser testing is the primary method.
