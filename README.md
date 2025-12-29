# RAVEN WEAPON AG - Shopware E-Commerce

Swiss firearms e-commerce platform built on Shopware 6 with a custom premium theme.

**Production:** https://ravenweapon.ch
**Staging:** https://developing.ravenweapon.ch
**Tech Stack:** Shopware 6.6.0.0, PHP 8.3, MySQL 8, Twig, SCSS, Docker

---

## Architecture

```
┌─────────────────────┐      git push       ┌──────────────────┐
│   Windows Machine   │ ─────────────────►  │     GitHub       │
│   (Development)     │                     │   (Repository)   │
└─────────────────────┘                     └────────┬─────────┘
                                                     │
                                            GitHub Actions
                                                     │
                        ┌────────────────────────────┼────────────────────────────┐
                        │                            │                            │
                        ▼                            │                            ▼
              ┌──────────────────┐                   │              ┌──────────────────┐
              │  Staging         │                   │              │  Production      │
              │  shopware-dev    │ ───► Review ──────┘              │  shopware-chf    │
              │  developing.     │       & Approve                  │  ravenweapon.ch  │
              │  ravenweapon.ch  │                                  │                  │
              └──────────────────┘                                  └──────────────────┘
```

**Two-stage deployment workflow:**
1. Push to `main` branch triggers automatic deployment to staging
2. Preview changes at https://developing.ravenweapon.ch
3. Approve production deployment in GitHub Actions
4. Changes go live at https://ravenweapon.ch

---

## Repository Structure

```
ravenweapon/
├── .github/workflows/
│   └── deploy.yml                    # Two-stage CI/CD pipeline
│
├── shopware-theme/RavenTheme/        # Custom Shopware 6 theme plugin
│   └── src/
│       ├── Controller/               # Custom route controllers
│       ├── Subscriber/               # Event subscribers
│       └── Resources/
│           ├── config/services.xml   # Dependency injection
│           ├── theme.json            # Theme configuration
│           ├── app/storefront/src/
│           │   ├── scss/base.scss    # Main stylesheet
│           │   └── main.js           # JS entry point
│           └── views/storefront/     # Twig template overrides
│
├── PayrexxPaymentGateway/            # Swiss payment processing plugin
│
├── tools/snigel-extractor/           # Snigel B2B product sync tools
│   ├── scrapers/                     # PHP & Node.js scrapers
│   └── data/                         # Product data & images
│
├── scripts/server/                   # Server-side utility scripts
│
├── assets/                           # Brand assets, logos, product images
│
├── CLAUDE.md                         # AI development assistant guide
└── docker-compose.yml                # Local development setup
```

---

## Key Customizations

### Theme Features
- **Gold gradient branding** with premium dark aesthetic
- **Custom typography:** Chakra Petch (headlines) + Inter (body)
- **AJAX off-canvas cart** with real-time updates
- **Toast notifications** for add-to-cart feedback
- **Responsive navigation** with mobile flyout menu
- **German language** as primary storefront language

### Custom Templates
| Component | Location |
|-----------|----------|
| Header | `views/storefront/layout/header/header.html.twig` |
| Footer | `views/storefront/layout/footer/footer.html.twig` |
| Product Detail | `views/storefront/page/product-detail/index.html.twig` |
| Product Card | `views/storefront/component/product/card/box-standard.html.twig` |
| Off-canvas Cart | `views/storefront/component/checkout/offcanvas-cart.html.twig` |
| 404 Error Page | `views/storefront/page/error/error-404.html.twig` |

### JavaScript Plugins
- `RavenOffcanvasCartPlugin` - AJAX cart with off-canvas sidebar
- `RavenToastPlugin` - Toast notifications for user feedback

### Template Fixes
- **HTTPS srcset fix** (`utilities/thumbnail.html.twig`) - Forces HTTPS for image srcset URLs due to Cloudflare SSL termination

---

## Development

### Prerequisites
- Git
- Text editor (VS Code recommended)

### Workflow

```bash
# Clone repository
git clone https://github.com/adamstarta/ravenweapon.git
cd ravenweapon

# Edit theme files in shopware-theme/RavenTheme/src/

# Commit and push
git add .
git commit -m "feat: your change description"
git push origin main

# Deployment happens automatically:
# 1. Staging deploys (~2 min)
# 2. Check https://developing.ravenweapon.ch
# 3. Approve production in GitHub Actions
# 4. Production deploys (~2 min)
```

### Twig Templates
Always use `{% sw_extends %}` (NOT `{% extends %}`) for Shopware template inheritance:

```twig
{% sw_extends '@Storefront/storefront/page/product-detail/index.html.twig' %}

{% block page_product_detail_buy %}
    {# Your custom content #}
{% endblock %}
```

### Commit Conventions
- `feat:` - New feature
- `fix:` - Bug fix
- `style:` - CSS/styling changes
- `refactor:` - Code restructuring
- `docs:` - Documentation

---

## Local Development (Optional)

For local testing with Docker:

```bash
# Start container
docker-compose up -d
# Wait ~2 minutes for initialization

# Activate theme
docker exec ravenweapon-shop bash -c "cd /var/www/html && \
  bin/console plugin:refresh && \
  bin/console plugin:install RavenTheme --activate && \
  bin/console theme:change RavenTheme --all && \
  bin/console cache:clear"
```

**Local Access:**
- Storefront: http://localhost/
- Admin Panel: http://localhost/admin (admin/shopware)

---

## Snigel B2B Integration

The `tools/snigel-extractor/` contains scrapers for syncing products from the Snigel B2B portal (products.snigel.se):

```bash
# Scrape products
php tools/snigel-extractor/scrapers/snigel-scraper.php

# Sync stock levels
node tools/snigel-extractor/scrapers/snigel-stock-scraper.js

# Sync B2B prices
node tools/snigel-extractor/scrapers/snigel-b2b-sync.js
```

---

## Server Information

| Setting | Value |
|---------|-------|
| Server | Hetzner (77.42.19.154) |
| Production Container | `shopware-chf` (port 8081) |
| Staging Container | `shopware-dev` (port 8082) |
| Domain | https://ravenweapon.ch |
| CDN/SSL | Cloudflare (Full strict) |
| Currency | CHF |

**Environment Differences:**
- Production: `APP_ENV=prod`, `APP_DEBUG=0`
- Staging: `APP_ENV=dev`, `APP_DEBUG=1`

---

## Branding

### Colors
| Color | Hex | Usage |
|-------|-----|-------|
| Gold Gradient | `#FDE047 → #F59E0B → #D97706` | Headlines, CTAs |
| Primary Gold | `#F59E0B` | Buttons, accents |
| Dark Background | `#111827` | Headers, cards |
| Price Red | `#E53935` | Prices |
| Available Green | `#77C15A` | Stock status |

### Typography
- **Headlines:** Chakra Petch (700 weight)
- **Body:** Inter (400/500/600 weights)

---

## Troubleshooting

| Issue | Solution |
|-------|----------|
| Changes not showing | Hard refresh (Ctrl+Shift+R), Cloudflare caches for ~5 min |
| Deployment failed | Check [GitHub Actions](https://github.com/adamstarta/ravenweapon/actions) |
| Twig syntax error | Verify `{% sw_extends %}` usage |
| Staging works, prod doesn't | Approve production deployment in GitHub Actions |

---

## License

Proprietary - RAVEN WEAPON AG
