# CLAUDE.md - Windows Development Setup

## Project Overview

Swiss firearms e-commerce platform for **RAVEN WEAPON AG** built on Shopware 6.6.

- **Live Site:** https://ravenweapon.ch
- **Staging Site:** https://developing.ravenweapon.ch
- **GitHub Repo:** https://github.com/adamstarta/ravenweapon
- **Tech Stack:** Shopware 6.6, PHP 8.3, MySQL 8, Twig, SCSS
- **Production Server:** Hetzner Linux (77.42.19.154) running Docker containers

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

## Deployment Workflow

**Two-stage deployment with staging preview:**

1. Edit files locally on Windows
2. Commit and push to `main` branch
3. GitHub Actions automatically deploys to **staging** first
4. Preview changes at https://developing.ravenweapon.ch
5. If staging looks good, approve the production deployment in GitHub Actions
6. GitHub Actions deploys to **production**

**Deployment triggers on changes to:**
- `shopware-theme/**` (theme files)
- `scripts/server/**` (utility scripts)
- `.github/workflows/deploy.yml`

### Setting Up GitHub Environments (One-time Setup)

To enable the approval workflow, create these environments in GitHub:

1. Go to https://github.com/adamstarta/ravenweapon/settings/environments
2. Create environment: `staging` (no protection rules needed)
3. Create environment: `production`
   - Add protection rule: "Required reviewers" (add yourself)
   - This will require manual approval before production deployment

## Repository Structure

```
ravenweapon/
├── .github/workflows/
│   └── deploy.yml              # Two-stage deployment workflow
├── shopware-theme/
│   └── RavenTheme/             # Main theme plugin
│       └── src/
│           ├── Controller/     # Custom route controllers
│           ├── Subscriber/     # Event subscribers
│           └── Resources/
│               ├── config/services.xml
│               ├── app/storefront/src/    # SCSS + JS
│               └── views/storefront/      # Twig templates
├── scripts/server/             # Server utility scripts
├── PayrexxPaymentGateway/      # Swiss payment plugin
└── assets/                     # Brand assets, logos
```

## Key Files to Edit

### Styling & JavaScript
| Purpose | Path |
|---------|------|
| Main SCSS | `shopware-theme/RavenTheme/src/Resources/app/storefront/src/scss/base.scss` |
| JS Entry | `shopware-theme/RavenTheme/src/Resources/app/storefront/src/main.js` |
| Cart Plugin | `shopware-theme/RavenTheme/src/Resources/app/storefront/src/plugin/raven-offcanvas-cart.plugin.js` |
| Toast Plugin | `shopware-theme/RavenTheme/src/Resources/app/storefront/src/plugin/raven-toast.plugin.js` |

### Templates (Twig)
Located in `shopware-theme/RavenTheme/src/Resources/views/storefront/`:

| Purpose | Path |
|---------|------|
| Header | `layout/header/header.html.twig` |
| Footer | `layout/footer/footer.html.twig` |
| Product Detail | `page/product-detail/index.html.twig` |
| Product Card | `component/product/card/box-standard.html.twig` |
| Off-canvas Cart | `component/checkout/offcanvas-cart.html.twig` |
| Homepage | `page/content/index.html.twig` |
| Login | `page/account/login/index.html.twig` |
| Register | `page/account/register/index.html.twig` |

### Configuration
| Purpose | Path |
|---------|------|
| Theme Config | `shopware-theme/RavenTheme/src/Resources/theme.json` |
| Services DI | `shopware-theme/RavenTheme/src/Resources/config/services.xml` |

## Development Guidelines

### Twig Templates
- Use `{% sw_extends %}` (NOT `{% extends %}`) to extend Shopware base templates
- Template inheritance example:
```twig
{% sw_extends '@Storefront/storefront/page/product-detail/index.html.twig' %}
{% block page_product_detail_buy %}
    {# Your custom content #}
{% endblock %}
```

### SCSS
- Main entry point: `base.scss`
- Brand colors are defined there - follow existing patterns

### JavaScript Plugins
Custom Shopware plugins are registered in `main.js`:
- `RavenOffcanvasCartPlugin` - AJAX cart with off-canvas sidebar
- `RavenToastPlugin` - Toast notifications

### Testing Changes
After pushing to `main`:
1. Wait ~2 minutes for staging deployment
2. Check https://developing.ravenweapon.ch (hard refresh: Ctrl+Shift+R)
3. If staging looks good, go to GitHub Actions and approve production deployment
4. Check https://ravenweapon.ch after production deployment
5. Check GitHub Actions tab for deployment status: https://github.com/adamstarta/ravenweapon/actions

## Branding

### Colors
| Color | Hex | Usage |
|-------|-----|-------|
| Gold Gradient | `#FDE047 → #F59E0B → #D97706` | Headlines, CTAs |
| Dark Background | `#111827` | Headers, cards |
| Price Red | `#E53935` | Prices |
| Available Green | `#77C15A` | Stock status |

### Typography
- **Headlines:** Chakra Petch (700 weight)
- **Body:** Inter (400/500/600 weights)

## Git Workflow

```bash
# Clone the repository (first time only)
git clone https://github.com/adamstarta/ravenweapon.git
cd ravenweapon

# Make your changes to files in shopware-theme/RavenTheme/

# Stage, commit, and push
git add .
git commit -m "feat: description of your change"
git push origin main

# Deployment to staging happens automatically!
# Then approve production deployment in GitHub Actions
```

### Commit Message Conventions
- `feat:` - New feature
- `fix:` - Bug fix
- `style:` - CSS/styling changes
- `refactor:` - Code restructuring
- `chore:` - Maintenance tasks
- `ci:` - CI/CD changes

## Checking Deployment Status

### Via GitHub
Visit: https://github.com/adamstarta/ravenweapon/actions

### Common Issues
| Issue | Solution |
|-------|----------|
| Changes not showing | Hard refresh (Ctrl+Shift+R), Cloudflare may cache |
| Deployment failed | Check Actions tab for error logs |
| Twig syntax error | Verify `{% sw_extends %}` usage |
| Staging works but production doesn't | Check if production deployment was approved |

## Important Notes

1. **No local Shopware needed** - The servers handle everything
2. **No Docker commands needed** - GitHub Actions handles deployment
3. **No SSH needed** - Everything goes through Git
4. **Always preview on staging** - Test at developing.ravenweapon.ch first
5. **Cloudflare caching** - May need to wait or hard refresh to see changes

## Server Information (Reference Only)

The server environment (you don't need to access this):
- **Server:** Hetzner Linux at 77.42.19.154
- **Staging Container:** `shopware-dev` (port 8082)
- **Production Container:** `shopware-chf` (port 8081)
- **Reverse Proxy:** nginx routes by hostname
- **CDN/SSL:** Cloudflare (Full SSL mode)

## Getting Help

- Check GitHub Actions logs for deployment errors
- Review Shopware 6 documentation: https://developer.shopware.com/docs/
- The Linux server has a Claude Code agent that can run server-side commands if needed
