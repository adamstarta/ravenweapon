# RAVEN WEAPON AG - Shopware E-Commerce

Swiss firearms e-commerce platform built on Shopware 6 with custom RavenTheme.

**Production:** https://ravenweapon.ch
**Tech Stack:** Shopware 6.6.0.0, Docker (dockware), PHP 8.3, MySQL 8, Twig, SCSS

---

## Local Development (Windows)

### Prerequisites
- Docker Desktop
- Git

### Setup

```bash
# Clone repository
git clone https://github.com/adamstarta/ravenweapon.git
cd ravenweapon

# Start Docker container
docker-compose up -d
# Wait ~2 minutes for Shopware to initialize

# Activate RavenTheme
docker exec ravenweapon-shop bash -c "cd /var/www/html && \
  bin/console plugin:refresh && \
  bin/console plugin:install RavenTheme --activate && \
  bin/console theme:change RavenTheme --all && \
  bin/console cache:clear"
```

### Access
- **Storefront:** http://localhost/
- **Admin Panel:** http://localhost/admin (admin / shopware)
- **Database:** http://localhost:8888 (Adminer)

---

## Project Structure

```
ravenweapon/
├── shopware-theme/RavenTheme/    # Custom Shopware 6 theme plugin
│   └── src/
│       ├── Controller/           # Custom route controllers
│       ├── Subscriber/           # Event subscribers
│       └── Resources/
│           ├── config/services.xml
│           ├── app/storefront/src/scss/   # Styles
│           └── views/storefront/          # Twig templates
├── scripts/server/               # Server-side utility scripts
├── PayrexxPaymentGateway/        # Swiss payment plugin
├── assets/                       # Brand assets, logos
├── CLAUDE.md                     # AI assistant guide
└── docker-compose.yml
```

---

## Development Workflow

### Edit Theme Files
```bash
# After editing files in shopware-theme/RavenTheme/
docker cp shopware-theme/RavenTheme ravenweapon-shop:/var/www/html/custom/plugins/
docker exec ravenweapon-shop bash -c "cd /var/www/html && bin/console theme:compile && bin/console cache:clear"
```

### Deploy to Production (one-liner)
```bash
scp -r shopware-theme/RavenTheme root@77.42.19.154:/tmp/ && \
ssh root@77.42.19.154 "docker cp /tmp/RavenTheme shopware-chf:/var/www/html/custom/plugins/ && \
docker exec shopware-chf bash -c 'cd /var/www/html && bin/console theme:compile && bin/console cache:clear'"
```

---

## Useful Commands

### Shopware Console
```bash
bin/console cache:clear           # Clear all caches
bin/console theme:compile         # Recompile theme SCSS/JS
bin/console plugin:refresh        # Detect plugin changes
bin/console assets:install        # Reinstall public assets
```

### Docker
```bash
docker logs ravenweapon-shop -f   # View container logs
docker exec -it ravenweapon-shop bash   # Access container shell
docker-compose restart            # Restart container
docker-compose down               # Stop container
```

---

## Production Server

| Setting | Value |
|---------|-------|
| **Server** | Hetzner (77.42.19.154) |
| **Container** | `shopware-chf` |
| **Domain** | https://ravenweapon.ch |
| **CDN/SSL** | Cloudflare (Full strict) |
| **Currency** | CHF |

### SSH Access
```bash
ssh root@77.42.19.154

# Enter container
docker exec -it shopware-chf bash

# Quick cache clear
docker exec shopware-chf bash -c "cd /var/www/html && bin/console cache:clear"
```

---

## Branding

### Colors
| Color | Hex | Usage |
|-------|-----|-------|
| Gold Gradient | `#FDE047 → #F59E0B → #D97706` | Headlines, CTAs |
| Primary Gold | `#F59E0B` | Buttons, accents |
| Dark Background | `#111827` | Headers, cards |

### Typography
- **Headlines:** Chakra Petch (700)
- **Body:** Inter (400/500/600)

---

## License

Proprietary - RAVEN WEAPON AG
