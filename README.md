# RAVEN WEAPON AG - Shopware E-Commerce

Swiss firearms e-commerce platform built on Shopware 6 with custom RavenTheme.

**Client:** RAVEN WEAPON AG (Swiss firearms dealer)
**Domain:** ortak.ch (production)
**Tech Stack:** Shopware 6.7.5.0, Docker (dockware), PHP 8.3, MySQL 8, Twig, SCSS

---

## Table of Contents

1. [Project Overview](#project-overview)
2. [Production Server](#production-server)
3. [Backup & Recovery](#backup--recovery)
4. [Local Development](#local-development)
5. [Project Structure](#project-structure)
6. [Product Data & Import Scripts](#product-data--import-scripts)
7. [Theme Development](#theme-development)
8. [Branding Guidelines](#branding-guidelines)
9. [Useful Commands](#useful-commands)
10. [Troubleshooting](#troubleshooting)
11. [Migration Notes](#migration-notes)
12. [Snigel Product Images Upload](#snigel-product-images-upload)
13. [Twig Injection Fix for Brand Pages](#twig-injection-fix-for-brand-pages)

---

## Project Overview

### What This Project Is

This is a complete e-commerce solution for RAVEN WEAPON AG, a Swiss firearms dealer. The shop sells:
- Tactical gear (Snigel brand - 193 products)
- Firearms and accessories
- Ammunition
- Outdoor equipment

### Current Status (December 2024)

| Item | Status |
|------|--------|
| Products imported | 193 Snigel products with CHF prices |
| Theme | RavenTheme (custom, gold/black design) |
| Payment | Payrexx (requires Shopware 6.6.5+ upgrade) |
| Base Currency | **CHF ✅** (NEW installation) |
| Sales Channel | CHF with proper visibility |

### Active Installations

| Installation | URL | Currency | Status |
|-------------|-----|----------|--------|
| **NEW (CHF)** | http://new.ortak.ch:8080 | CHF ✅ | Ready for Go-Live |
| OLD (EUR) | https://ortak.ch | EUR | Backup only |

---

## Infrastructure Overview

### Service Providers

| Service | Provider | URL |
|---------|----------|-----|
| **Domain Registrar** | Hostpoint | https://admin.hostpoint.ch |
| **DNS/CDN** | Cloudflare | https://dash.cloudflare.com |
| **Server Hosting** | Hetzner | https://console.hetzner.cloud |

### How Traffic Flows

```
User → Cloudflare (CDN/SSL) → Hetzner Server (77.42.19.154) → Docker Container → Shopware
```

**Important:** Cloudflare only supports standard ports (80/443). Custom ports like 8080 won't work through Cloudflare proxy.

---

## Production Server

### Hetzner VPS Access

| Info | Value |
|------|-------|
| **Provider** | Hetzner |
| **IP Address** | 77.42.19.154 |
| **SSH User** | root |
| **SSH Password** | 93cupECnm3xH |
| **Domain** | ortak.ch |

### Shopware Admin (Current - EUR)

| Info | Value |
|------|-------|
| **Admin URL** | https://ortak.ch/admin |
| **Username** | admin |
| **Password** | shopware |
| **Shopware Version** | 6.6.0.0 (dockware) |
| **Base Currency** | EUR (problem - should be CHF) |

### Shopware Admin (New - CHF) - IN PROGRESS

| Info | Value |
|------|-------|
| **Admin URL** | http://new.ortak.ch/admin (needs DNS setup) |
| **Username** | admin |
| **Password** | shopware |
| **Shopware Version** | 6.6.0.0 (dockware) |
| **Base Currency** | CHF (correct!) |

### API Credentials (for import scripts - EUR installation)

```
Client ID: SWIAC3HJVHFJMHQYRWRUM1E1SG
Client Secret: RGtsN1Z2TklqU1ZZSVFTOFB6bWZXNWZNNk40V2h4RWY5Q2tPblg
```

### Docker Containers on Server

```bash
# Main shop (EUR base - current production on ports 80/443)
ravenweapon-shop    ports 80/443    dockware/dev:6.6.0.0

# CHF installation (NEW - ports 8080/8443)
shopware-chf        ports 8080/8443  dockware/dev:6.6.0.0
```

### SSH Quick Access

```bash
# Connect to server
ssh root@77.42.19.154

# Enter main EUR container
docker exec -it ravenweapon-shop bash

# Enter new CHF container
docker exec -it shopware-chf bash

# Check container status
docker ps
```

---

## DNS Setup for New CHF Installation

### Problem
The new CHF installation runs on port 8080, but Cloudflare doesn't support custom ports. We need a subdomain to access it.

### Solution: Create Subdomain `new.ortak.ch`

**Option A: If DNS is managed in Cloudflare**

1. Go to https://dash.cloudflare.com
2. Select ortak.ch → DNS → Records
3. Add new record:
   - **Type:** A
   - **Name:** `new`
   - **IPv4 address:** `77.42.19.154`
   - **Proxy status:** **DNS only** (grey cloud, NOT orange!)
   - **TTL:** Auto
4. Save

**Option B: If DNS is managed in Hostpoint**

1. Go to https://admin.hostpoint.ch
2. Find DNS settings for ortak.ch
3. Add A record:
   - **Subdomain:** `new`
   - **Type:** A
   - **Value:** `77.42.19.154`
4. Save

### After DNS Setup

Access the new CHF shop at:
- **Storefront:** http://new.ortak.ch:8080
- **Admin:** http://new.ortak.ch:8080/admin

Then update Shopware domain:
```bash
ssh root@77.42.19.154
docker exec shopware-chf bash -c "mysql -u root -proot shopware -e \"UPDATE sales_channel_domain SET url='http://new.ortak.ch:8080' WHERE url LIKE '%ortak%'\""
docker exec shopware-chf bash -c "cd /var/www/html && bin/console cache:clear"
```

### Final Migration (When Ready)

When the new CHF installation is tested and ready:

1. **Stop both containers:**
   ```bash
   docker stop ravenweapon-shop shopware-chf
   ```

2. **Swap the ports:**
   ```bash
   # Remove old containers
   docker rm ravenweapon-shop shopware-chf

   # Restart CHF container on main ports
   docker run -d --name ravenweapon-shop-chf -p 80:80 -p 443:443 ... (from shopware-chf image)
   ```

3. **Update domain in Shopware to ortak.ch**

4. **Test everything works**

---

## Backup & Recovery

### CRITICAL: Three Backup Locations

All backups were created on **2024-12-11** before the CHF migration.

#### 1. Server Backup (310MB)

**Location:** `/var/backups/shopware-eur-20251211/`

| File/Folder | Size | Description |
|-------------|------|-------------|
| `database.sql.gz` | 1.4MB | Full MySQL dump (305 products, customers, orders) |
| `media/` | 206MB | 914 uploaded product images |
| `RavenTheme/` | 29MB | Theme plugin (115 files) |
| `PayrexxPaymentGatewaySW6/` | 1.3MB | Payment gateway plugin |
| `bundles/` | 63MB | Compiled public assets |
| `theme/` | 9.2MB | Compiled CSS/JS |
| `files/` | 668KB | Import/export files |
| `.env` | 1KB | Shopware configuration |

**Restore database from server backup:**
```bash
ssh root@77.42.19.154
docker exec -i ravenweapon-shop bash -c 'mysql -u root -proot shopware' < /var/backups/shopware-eur-20251211/database.sql
```

#### 2. GitHub Repository

**URL:** https://github.com/adamstarta/ravenweapon
**Commit:** `e6aa206` - "chore: complete backup before fresh CHF installation"

Contains:
- Theme source code (78 files)
- All import/scraping scripts (18 scripts)
- Product data JSON (390KB with all prices)
- 2,599 product images (scraped from Snigel B2B)

**Clone fresh:**
```bash
git clone https://github.com/adamstarta/ravenweapon.git
```

#### 3. Local Copy

**Path:** `C:\Users\alama\Desktop\NIKOLA WORK\ravenweapon`

Same as GitHub - synchronized via git.

### How to Restore Everything

**Option A: Quick restore from server backup**
```bash
# 1. Connect to server
ssh root@77.42.19.154

# 2. Restore database
zcat /var/backups/shopware-eur-20251211/database.sql.gz | docker exec -i ravenweapon-shop mysql -u root -proot shopware

# 3. Restore media
docker cp /var/backups/shopware-eur-20251211/media/. ravenweapon-shop:/var/www/html/public/media/

# 4. Restore theme
docker cp /var/backups/shopware-eur-20251211/RavenTheme ravenweapon-shop:/var/www/html/custom/plugins/

# 5. Clear cache
docker exec ravenweapon-shop bash -c "cd /var/www/html && bin/console cache:clear"
```

**Option B: Full rebuild from git repo**
```bash
# 1. Clone repo
git clone https://github.com/adamstarta/ravenweapon.git
cd ravenweapon

# 2. Start local Docker
docker-compose up -d

# 3. Install theme
docker cp shopware-theme/RavenTheme ravenweapon-shop:/var/www/html/custom/plugins/
docker exec ravenweapon-shop bash -c "cd /var/www/html && bin/console plugin:refresh && bin/console plugin:install RavenTheme --activate"

# 4. Import products using scripts (see Product Data section)
```

---

## Local Development

### Prerequisites

- Docker Desktop (Windows/Mac/Linux)
- Git
- Node.js 18+ (for scraping scripts)
- PHP 8.1+ (for import scripts)

### Quick Start

```bash
# 1. Clone repository
git clone https://github.com/adamstarta/ravenweapon.git
cd ravenweapon

# 2. Start Docker container
docker-compose up -d
# Wait ~2 minutes for Shopware to initialize

# 3. Activate RavenTheme
docker exec ravenweapon-shop bash -c "cd /var/www/html && bin/console plugin:refresh && bin/console plugin:install RavenTheme --activate && bin/console theme:change RavenTheme --all && bin/console cache:clear"

# 4. Access the site
# Storefront: http://localhost/
# Admin: http://localhost/admin (admin / shopware)
# Database: http://localhost:8888 (Adminer)
```

### Deploy Theme Changes

```bash
# Copy theme to container
docker cp shopware-theme/RavenTheme ravenweapon-shop:/var/www/html/custom/plugins/

# Compile and clear cache
docker exec ravenweapon-shop bash -c "cd /var/www/html && bin/console theme:compile && bin/console cache:clear"
```

---

## Project Structure

```
ravenweapon/
├── assets/                         # Brand assets
│   ├── Favicon/                   # Favicon files
│   ├── wallpaper hero.png         # Homepage hero background
│   ├── snigel logo.png            # Brand logos
│   └── ...
│
├── scripts/                        # Import & scraping scripts
│   ├── snigel-merged-products.json    # MAIN PRODUCT DATA (390KB)
│   ├── shopware-import.php            # Import products to Shopware
│   ├── shopware-update-prices.php     # Update product prices
│   ├── shopware-upload-images.php     # Upload product images
│   ├── snigel-b2b-*.js                # B2B price scraping scripts
│   ├── snigel-data/                   # Scraped public product data
│   │   └── images/                    # Product images (1,300+ files)
│   └── snigel-b2b-data/               # B2B pricing data
│       ├── images/                    # Additional images
│       └── products-*.json            # Price data files
│
├── shopware-theme/                 # Shopware theme plugin
│   └── RavenTheme/
│       └── src/
│           ├── Resources/
│           │   ├── app/storefront/src/scss/   # SCSS styles
│           │   │   └── base.scss              # Main stylesheet
│           │   ├── views/storefront/          # Twig templates
│           │   │   ├── layout/                # Header, footer
│           │   │   ├── page/                  # Page templates
│           │   │   └── component/             # Reusable components
│           │   └── theme.json                 # Theme configuration
│           ├── Controller/                    # Custom controllers
│           └── RavenTheme.php                 # Plugin main class
│
├── legacy/                         # Original static HTML (reference only)
│   ├── html/                      # Static HTML pages
│   ├── css/                       # Original CSS
│   └── js/                        # Original JavaScript
│
├── docs/plans/                     # Design documentation
├── docker-compose.yml              # Local Docker setup
└── README.md                       # This file
```

---

## Product Data & Import Scripts

### Main Product Data File

**Location:** `scripts/snigel-merged-products.json`

This JSON file contains all 193 Snigel products with:
- Product names (German)
- Descriptions
- B2B purchase prices (EUR)
- Selling prices (calculated with 50% markup)
- Image references
- Article numbers

### Import Scripts

All scripts are in the `scripts/` folder and use the Shopware API.

| Script | Purpose |
|--------|---------|
| `shopware-import.php` | Import products to Shopware |
| `shopware-import-chf.php` | Import products to CHF Shopware installation |
| `shopware-update-prices.php` | Update prices for all products |
| `shopware-upload-images.php` | Upload product images |
| `shopware-upload-images-chf.php` | Upload images to CHF installation |
| `upload-snigel-images-v4.php` | Upload Snigel images (server-side, fixed) |
| `update-missing-prices.php` | Fix 6 products with missing prices |

**Run import script:**
```bash
cd scripts
php shopware-import.php
```

### Scraping Scripts (Node.js)

These scripts scrape product data from Snigel's B2B portal.

| Script | Purpose |
|--------|---------|
| `snigel-b2b-final.js` | Main B2B scraper (requires login) |
| `scrape-missing-products.js` | Scrape specific missing products |
| `extract-from-html.js` | Parse saved HTML for product data |

**Snigel B2B Portal Credentials:**
```
URL: https://products.snigel.se
Username: Raven Weapon AG
Password: wVREVbRZfqT&Fba@f(^2UKOw
```

**Run scraper:**
```bash
cd scripts
npm install
node snigel-b2b-final.js
```

---

## Theme Development

### File Locations

| What | Path |
|------|------|
| Main SCSS | `shopware-theme/RavenTheme/src/Resources/app/storefront/src/scss/base.scss` |
| Homepage | `shopware-theme/RavenTheme/src/Resources/views/storefront/page/content/index.html.twig` |
| Header | `shopware-theme/RavenTheme/src/Resources/views/storefront/layout/header/header.html.twig` |
| Footer | `shopware-theme/RavenTheme/src/Resources/views/storefront/layout/footer/footer.html.twig` |
| Product card | `shopware-theme/RavenTheme/src/Resources/views/storefront/component/product/card/box-standard.html.twig` |

### Development Workflow

1. **Edit files** in `shopware-theme/RavenTheme/`

2. **Copy to container:**
   ```bash
   docker cp shopware-theme/RavenTheme ravenweapon-shop:/var/www/html/custom/plugins/
   ```

3. **Compile theme:**
   ```bash
   docker exec ravenweapon-shop bash -c "cd /var/www/html && bin/console theme:compile && bin/console cache:clear"
   ```

### Key Templates

| Template | Description |
|----------|-------------|
| `page/content/index.html.twig` | Homepage with hero, categories, brands |
| `page/product-detail/index.html.twig` | Product detail page |
| `page/checkout/confirm/index.html.twig` | Checkout confirmation |
| `page/account/order/index.html.twig` | Order history |
| `component/checkout/offcanvas-cart.html.twig` | Cart sidebar |

---

## Branding Guidelines

### Colors

| Color | Hex | Usage |
|-------|-----|-------|
| Gold Gradient | `linear-gradient(135deg, #FDE047 0%, #F59E0B 50%, #D97706 100%)` | Headlines, CTAs |
| Primary Gold | `#F59E0B` | Buttons, accents |
| Dark Background | `#111827` | Headers, cards |
| Body Text | `#374151` | Paragraphs |
| Light Background | `#F9FAFB` | Page backgrounds |

### Typography

| Element | Font | Weight |
|---------|------|--------|
| Hero Headlines | Chakra Petch | 700 |
| Body Text | Inter | 400/500 |
| Buttons | Inter | 600 |

### Logo

Gold gradient text "RAVEN WEAPON" with tagline "PRÄZISION TRIFFT LEIDENSCHAFT"

---

## Useful Commands

### Docker Commands

```bash
# View container logs
docker logs ravenweapon-shop -f

# Access container shell
docker exec -it ravenweapon-shop bash

# Restart container
docker-compose restart

# Stop container
docker-compose down
```

### Shopware Console Commands

```bash
# Clear all caches
docker exec ravenweapon-shop bash -c "cd /var/www/html && bin/console cache:clear"

# Recompile theme
docker exec ravenweapon-shop bash -c "cd /var/www/html && bin/console theme:compile"

# Refresh plugins
docker exec ravenweapon-shop bash -c "cd /var/www/html && bin/console plugin:refresh"

# Install assets
docker exec ravenweapon-shop bash -c "cd /var/www/html && bin/console assets:install"

# Database migrations
docker exec ravenweapon-shop bash -c "cd /var/www/html && bin/console database:migrate --all"
```

### MySQL Access

```bash
# Inside container
mysql -u root -proot shopware

# Product count
SELECT COUNT(*) FROM product;

# Check currencies
SELECT iso_code, factor FROM currency;
```

---

## Troubleshooting

### Container won't start

```bash
# Check if port 80 is in use
netstat -an | findstr :80

# Check Docker status
docker ps -a

# View container logs
docker logs ravenweapon-shop
```

### MySQL won't start in container

```bash
# Fix permissions and start
docker exec -u root ravenweapon-shop bash -c 'chown -R mysql:mysql /var/lib/mysql && service mysql start'
```

### Theme not showing

```bash
# Reinstall and activate theme
docker exec ravenweapon-shop bash -c "cd /var/www/html && bin/console plugin:refresh && bin/console plugin:install RavenTheme --activate && bin/console theme:change RavenTheme --all && bin/console cache:clear"
```

### Assets 404 errors

```bash
# Reinstall assets
docker exec ravenweapon-shop bash -c "cd /var/www/html && bin/console assets:install && bin/console cache:clear"
```

### Products not showing prices

- Check if prices exist in admin: `/admin#/sw/product/index`
- Run price update script: `php scripts/shopware-update-prices.php`
- Verify currency setup in Settings > Shop > Currencies

---

## Migration Notes

### EUR to CHF Base Currency Migration

**Problem:** Shopware was installed with EUR as base currency. This is hardcoded during installation and cannot be changed via UI.

**Solution:** Fresh Shopware installation with CHF as base currency.

---

### Current Setup (December 2024)

| Container | URL | Port | Currency | Status |
|-----------|-----|------|----------|--------|
| `ravenweapon-shop` | https://ortak.ch | 80/443 | EUR ❌ | OLD - Backup only |
| `shopware-chf` | http://new.ortak.ch:8080 | 8080/8443 | CHF ✅ | NEW - Testing |

---

### MIGRATION PLAN

#### PHASE 1: Setup & Testing ✅ COMPLETED
- [x] Create fresh Shopware installation with CHF base currency
- [x] Install RavenTheme
- [x] Setup DNS subdomain `new.ortak.ch` (Cloudflare, DNS only mode)
- [x] Configure Shopware domain for `http://new.ortak.ch:8080`
- [x] Create API credentials for CHF installation
- [x] Import 193 Snigel products with CHF prices
- [x] Upload product images (all 193 products)
- [x] Fix product visibility on storefront
- [x] Test site with Playwright (products showing, CHF prices visible)
- [x] Fix Twig injection error on "Unsere Marken" (brand) pages
- [x] Upload Snigel product images (193 images uploaded successfully)
- [ ] Configure Payrexx payment (requires Shopware upgrade to 6.6.5+)

#### PHASE 2: Go Live - Swap Containers
When testing is complete and everything works:

```bash
# 1. SSH to server
ssh root@77.42.19.154

# 2. Stop both containers
docker stop ravenweapon-shop shopware-chf

# 3. Change port mappings
# Remove old containers (data persists in volumes)
docker rm ravenweapon-shop shopware-chf

# 4. Restart CHF container on main ports (80/443)
cd /root
docker-compose -f docker-compose-chf.yml down
# Edit docker-compose-chf.yml: change 8080:80 to 80:80, 8443:443 to 443:443
docker-compose -f docker-compose-chf.yml up -d

# 5. Update Shopware domain to https://ortak.ch
docker exec shopware-chf bash -c "mysql -u root -proot shopware -e \"UPDATE sales_channel_domain SET url='https://ortak.ch' WHERE url LIKE '%new.ortak.ch%'\""
docker exec shopware-chf bash -c "sed -i 's|APP_URL=.*|APP_URL=https://ortak.ch|' /var/www/html/.env"
docker exec shopware-chf bash -c "cd /var/www/html && bin/console cache:clear"

# 6. Optionally start old EUR container on backup port
docker-compose up -d  # This starts ravenweapon-shop on 8080
```

#### PHASE 3: Cleanup
After confirming everything works on the live site:

1. **Delete DNS subdomain:**
   - Go to Cloudflare → ortak.ch → DNS
   - Delete the `new` A record

2. **Keep or remove old EUR container:**
   - Keep as backup: Leave running on port 8080
   - Remove completely: `docker rm ravenweapon-shop`

---

### Final Result

| What | URL | Description |
|------|-----|-------------|
| **Live Site** | https://ortak.ch | CHF installation (correct currency) |
| **Admin** | https://ortak.ch/admin | Shopware admin panel |
| **Backup** | http://77.42.19.154:8080 | Old EUR installation (optional) |

---

### Quick Commands for Migration Day

```bash
# Check container status
docker ps

# Check which container is on which port
docker port ravenweapon-shop
docker port shopware-chf

# View logs if something goes wrong
docker logs shopware-chf -f

# Emergency rollback - restart old EUR container on main ports
docker stop shopware-chf
docker run -d --name ravenweapon-shop -p 80:80 -p 443:443 ... (from backup)
```

---

## Snigel Product Images Upload

### Problem
After importing Snigel products to the CHF installation, the products had `coverId` references in the database but **no actual media files** uploaded. This caused placeholder icons to show instead of product images.

### Solution
Created `upload-snigel-images-v4.php` script that:
1. Identifies products with cover associations but no actual media files (empty URL/path)
2. Matches Shopware products to local image files via slug (SKU format: `SN-{slug}`)
3. Creates new media entities and uploads actual image files
4. Updates products with new cover media IDs

### How to Run (if needed again)

```bash
# 1. Upload images to server (if not already there)
scp -r scripts/snigel-data/images root@77.42.19.154:/tmp/snigel-images/
scp scripts/snigel-data/products.json root@77.42.19.154:/tmp/

# 2. Upload script to server
scp scripts/upload-snigel-images-v4.php root@77.42.19.154:/tmp/

# 3. Copy to container and run
ssh root@77.42.19.154 "docker cp /tmp/upload-snigel-images-v4.php shopware-chf:/tmp/ && docker exec -w /tmp shopware-chf php upload-snigel-images-v4.php"
```

### Results (December 12, 2024)
- **193 out of 194** Snigel product images uploaded successfully
- 1 product (`13-00110-01-000`) has no matching image in the downloaded set
- All brand pages now display proper product images

---

## Twig Injection Fix for Brand Pages

### Problem
"Unsere Marken" (Our Brands) pages were showing error:
```
Class RavenTheme\Controller\ManufacturerPageController does not have twig injected
```

### Solution
Updated `shopware-theme/RavenTheme/src/Resources/config/services.xml` to add Twig service injection:

```xml
<service id="RavenTheme\Controller\ManufacturerPageController" public="true">
    <!-- ... existing arguments ... -->
    <call method="setContainer">
        <argument type="service" id="service_container"/>
    </call>
    <call method="setTwig">
        <argument type="service" id="twig"/>
    </call>
</service>
```

This is required for Shopware 6.6+ where Twig must be explicitly injected into controllers.

---

## License

Proprietary - RAVEN WEAPON AG

---

*Last updated: December 12, 2024*
