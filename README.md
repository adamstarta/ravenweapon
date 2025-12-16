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
13. [Snigel Categories & Alle Produkte Fix](#snigel-categories--alle-produkte-fix)
14. [Twig Injection Fix for Brand Pages](#twig-injection-fix-for-brand-pages)
15. [Snigel Variants & Subcategories Scraper Update](#snigel-variants--subcategories-scraper-update)
16. [CHF Currency Fix & Price Sync](#chf-currency-fix--price-sync-december-15-2024)
17. [German Language Guidelines](#german-language-guidelines-important)
18. [Navigation Dropdown Fix](#navigation-dropdown-fix-december-16-2024)

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

### Shopware Admin (Live - CHF)

| Info | Value |
|------|-------|
| **Admin URL** | https://ortak.ch/admin |
| **Username** | Micro the CEO |
| **Password** | 100%Ravenweapon... |
| **Shopware Version** | 6.6.0.0 (dockware) |
| **Base Currency** | CHF ✅ |
| **Admin Language** | German (de-DE) |

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
| `shopware-chf` | https://ortak.ch | 80/443 | CHF ✅ | **LIVE** |
| `ravenweapon-shop` | - | - | EUR | **DELETED** (Dec 12, 2024) |

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

#### PHASE 2: Go Live - Swap Containers ✅ COMPLETED (Dec 12, 2024)

The CHF container was swapped to the main ports and the old EUR container was deleted.

```bash
# Commands that were executed:
docker stop ravenweapon-shop      # Stop old EUR container
docker rm ravenweapon-shop        # Delete old EUR container
# shopware-chf is now running on ports 80/443
```

#### PHASE 3: Cleanup ✅ COMPLETED (Dec 12, 2024)

1. **Old EUR container deleted** - `docker rm ravenweapon-shop`
2. **Backup preserved** at `/var/backups/shopware-eur-20251211/`
3. **DNS subdomain** - Can be deleted from Cloudflare if no longer needed

---

### Final Result ✅

| What | URL | Description |
|------|-----|-------------|
| **Live Site** | https://ortak.ch | CHF installation (correct currency) |
| **Admin** | https://ortak.ch/admin | Shopware admin panel |
| **Backup** | `/var/backups/shopware-eur-20251211/` | Database + media files (on server) |

---

### Quick Commands (Post-Migration)

```bash
# Check container status
docker ps

# View logs if something goes wrong
docker logs shopware-chf -f

# Clear cache
docker exec shopware-chf bash -c "cd /var/www/html && bin/console cache:clear"

# Emergency rollback from backup (if needed)
# 1. Create new container
docker run -d --name shopware-restore -p 80:80 -p 443:443 dockware/dev:6.6.0.0
# 2. Restore database
zcat /var/backups/shopware-eur-20251211/database.sql.gz | docker exec -i shopware-restore mysql -u root -proot shopware
# 3. Restore media
docker cp /var/backups/shopware-eur-20251211/media/. shopware-restore:/var/www/html/public/media/
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

## Snigel Categories & Alle Produkte Fix

### Problem (Discovered December 12, 2024)
After site check, several issues were found:

1. **"Alle Produkte"** - Only showed 25 products (missing Snigel products)
2. **Snigel English subcategories** - All INACTIVE (Tactical Gear, Bags & backpacks, etc.)
3. **Snigel subcategories empty** - Showing "Produkte folgen in Kürze"

### Root Cause
- Products were assigned to **German subcategories** (Taschen & Rucksäcke) not English ones (Bags & backpacks)
- English subcategories existed but were **INACTIVE**
- Products weren't added to "Alle Produkte" category

### Solution Applied
Created `scripts/fix-snigel-categories.php` that:
1. Activated all 21 English Snigel subcategories
2. Added all 194 Snigel products to "Alle Produkte"

```bash
# Run the fix (already done Dec 12, 2024)
ssh root@77.42.19.154 "docker cp /tmp/fix-snigel-categories.php shopware-chf:/tmp/ && docker exec shopware-chf php /tmp/fix-snigel-categories.php"
```

### Results
- ✅ "Alle Produkte" now shows all 219+ products (5 pages)
- ✅ 21 English subcategories activated
- ⚠️ **Subcategories still empty** - Products need to be assigned

### NEXT TASK: Assign Products to Subcategories

The Snigel subcategories (Bags & backpacks, Medical gear, Tactical clothing, etc.) are **activated but empty** because products were never assigned to them.

**What needs to be done:**
1. Re-scrape B2B portal to get category assignments for ALL products
2. Update Shopware products with proper subcategory assignments

**Scripts available:**
- `scripts/snigel-description-scraper-fast.js` - Scrapes categories from B2B portal
- `scripts/shopware-update-descriptions.php` - Updates products with categories
- `scripts/assign-products-to-english-subcats.php` - Maps German → English categories

**Current category data:**
- B2B scraper got categories for ~145 out of 203 products
- Only ~19 products have subcategory assignments in Shopware

**To fix subcategories completely:**
```bash
# 1. Re-run the B2B scraper to get ALL categories
cd scripts
node snigel-description-scraper-fast.js

# 2. Copy the JSON to server
scp scripts/snigel-data/products-with-descriptions.json root@77.42.19.154:/tmp/

# 3. Run the update script
ssh root@77.42.19.154 "docker cp /tmp/products-with-descriptions.json shopware-chf:/tmp/ && docker cp /tmp/shopware-update-descriptions.php shopware-chf:/tmp/ && docker exec shopware-chf php /tmp/shopware-update-descriptions.php"

# 4. Map German to English categories
ssh root@77.42.19.154 "docker cp /tmp/assign-products-to-english-subcats.php shopware-chf:/tmp/ && docker exec shopware-chf php /tmp/assign-products-to-english-subcats.php"
```

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

## Snigel Variants & Subcategories Scraper Update

### Problem (December 13, 2024)
The original Snigel scraper had issues:
1. **Fake "Graphite Black" property** - When products had multiple images, the system auto-created a fake "Farbe: Graphite Black" property even for products without color variants
2. **Missing subcategories** - Products were all dumped into main "Snigel" category instead of proper subcategories
3. **Missing color data** - Actual color information from product pages wasn't being scraped

### Solution
Created `snigel-variants-scraper.js` that visits each product page and:
1. **Detects color dropdowns** - If product has variant selector, scrapes all color options
2. **Scrapes Category** - Gets category from product meta (e.g., "Bags & backpacks", "Medical gear")
3. **Scrapes Colour** - Gets fixed colour for simple products (e.g., "Grey", "Black")

### Scripts Created

| Script | Purpose |
|--------|---------|
| `snigel-variants-scraper.js` | Main scraper - detects variants, scrapes category/colour |
| `snigel-retry-errors.js` | Retries failed products with longer timeouts |
| `check-data.js` | Verifies data completeness |

### Data Output

**File:** `scripts/snigel-data/products-with-variants.json`

**Data Structure:**
```json
{
  "name": "Product Name",
  "hasColorVariants": true/false,
  "colorOptions": [
    { "name": "Black", "value": "black" },
    { "name": "Grey", "value": "grey" }
  ],
  "category": "Bags & backpacks",
  "colour": "Grey",
  "galleryImages": ["url1", "url2"],
  "local_images": ["file1.jpg", "file2.jpg"]
}
```

### Results (December 13, 2024)

| Metric | Count | Percentage |
|--------|-------|------------|
| Total products | 193 | 100% |
| With images | 193 | 100% |
| With category | 193 | 100% |
| With colour | 178 | 92% |
| With color variants | 92 | 48% |
| With description | 71 | 37% |

### Categories Scraped (20 total)

```
Holders & pouches: 39 products
Patches: 21 products
Medical gear: 18 products
Ballistic protection: 15 products
Bags & backpacks: 13 products
Belts: 13 products
Tactical clothing: 12 products
Slings & holsters: 11 products
Tactical gear: 10 products
Police gear: 9 products
Admin products: 7 products
Miscellaneous products: 6 products
Vests & Chest rigs: 4 products
Leg panels: 3 products
Multicam: 3 products
Sniper gear: 3 products
Source® hydration: 3 products
Covert gear: 1 product
K9-units gear: 1 product
The Brand: 1 product
```

### How to Run Scraper

```bash
cd scripts

# Run main scraper (has resume capability)
node snigel-variants-scraper.js

# If there are errors, retry them
node snigel-retry-errors.js

# Check data completeness
node check-data.js
```

### Local Images

**Location:** `scripts/snigel-data/images/`
**Count:** 2,491 images

---

## CHF Currency Fix & Price Sync (December 15, 2024)

### Problem
After migration, the Shopware instance had:
1. **EUR as system default currency** - Even though CHF was set as factor 1, EUR was still marked as "isSystemDefault" in Shopware's `Defaults.php`
2. **101 products with only EUR prices** - These showed "No price found for currency Swiss francs" error
3. **Incorrect CHF prices** - Some products had EUR values stored as CHF (wrong conversion)

### Solution Applied

#### 1. Fixed System Default Currency
Changed Shopware's default currency from EUR to CHF by editing the core Defaults.php file:

```bash
# SSH into server and update Defaults.php
ssh root@77.42.19.154
docker exec shopware-chf bash -c "sed -i \"s/public const CURRENCY = 'b7d2554b0ce847cd82f3ac9bd1c0dfca'/public const CURRENCY = '0191c12cf40d718a8a3439b74a6f083c'/g\" /var/www/html/vendor/shopware/core/Defaults.php"
docker exec shopware-chf bash -c "cd /var/www/html && bin/console cache:clear"
```

**Currency IDs:**
- CHF: `0191c12cf40d718a8a3439b74a6f083c` (now default)
- EUR: `b7d2554b0ce847cd82f3ac9bd1c0dfca` (was default)

#### 2. Synced Prices from Old Shop
Created `scripts/sync-prices-from-old-shop.js` to:
1. Scrape all products from shop.ravenweapon.ch (old shop)
2. Match products with ortak.ch by name
3. Update CHF prices to match old shop exactly

**Results:**
- 101 products updated with correct CHF prices
- 89 products verified as matching old shop prices
- 47 products not found (different names in old vs new shop)

### Scripts Created

| Script | Purpose |
|--------|---------|
| `sync-prices-from-old-shop.js` | Sync CHF prices from old shop to ortak.ch |
| `compare-shop-prices.js` | Compare prices between old shop and ortak.ch |

### How to Run Price Sync Again

```bash
cd scripts

# Compare prices between shops (excludes Snigel products)
node compare-shop-prices.js

# Sync prices from old shop to ortak.ch
node sync-prices-from-old-shop.js
```

### Verification

After running the sync, prices should match:
```
Same prices: 89
Different prices: 1 (product mismatch - MILDOT vs ZEROPLEX)
Not found: 47
```

---

## German Language Guidelines (IMPORTANT!)

### ALWAYS Use Proper German Umlauts

**CRITICAL:** When writing German text in templates, ALWAYS use proper umlauts (ö, ä, ü) - NEVER use ASCII replacements (oe, ae, ue).

| ❌ WRONG | ✅ CORRECT |
|----------|-----------|
| fuer | für |
| Bestellbestaetigung | Bestellbestätigung |
| ueberweisen | überweisen |
| verfuegbar | verfügbar |
| Bestelluebersicht | Bestellübersicht |
| Zurueck | Zurück |
| Zuerich | Zürich |
| Persoenliche | Persönliche |
| Datenschutzerklaerung | Datenschutzerklärung |
| SSL-verschluesselt | SSL-verschlüsselt |
| Kaeufer | Käufer |
| erhoehen | erhöhen |
| hinzugefuegt | hinzugefügt |
| gueltige | gültige |
| veroeffentlicht | veröffentlicht |
| Pruefung | Prüfung |

### Common German Words with Umlauts

| German Word | Meaning |
|-------------|---------|
| für | for |
| Bestätigung | confirmation |
| überweisen | to transfer |
| verfügbar | available |
| Übersicht | overview |
| zurück | back |
| Zürich | Zurich (city) |
| persönlich | personal |
| Datenschutzerklärung | privacy policy |
| verschlüsselt | encrypted |
| Käufer | buyer |
| erhöhen | to increase |
| hinzugefügt | added |
| gültig | valid |
| veröffentlicht | published |
| Prüfung | review/check |
| schließen | to close |
| fügen | to add |

### How to Type Umlauts

**Windows:**
- ä = Alt + 0228
- ö = Alt + 0246
- ü = Alt + 0252
- Ä = Alt + 0196
- Ö = Alt + 0214
- Ü = Alt + 0220
- ß = Alt + 0223

**Alternative:** Copy-paste from this document or use a German keyboard layout.

### Files to Check for Umlaut Issues

If you need to verify umlauts are correct, check these template files:

```
shopware-theme/RavenTheme/src/Resources/views/storefront/
├── page/checkout/finish/index.html.twig
├── page/checkout/register/index.html.twig
├── page/checkout/confirm/index.html.twig
├── page/checkout/address/index.html.twig
├── page/product-detail/index.html.twig
└── component/checkout/offcanvas-cart.html.twig
```

### Quick Search for Umlaut Issues

```bash
# Find potential umlaut replacements in twig files
grep -rE "(fuer|ue[rn]|ae|oe[^s])" --include="*.twig" shopware-theme/
```

---

## Navigation Dropdown Fix (December 16, 2024)

### Problem
The navigation dropdown on hover for "Raven Weapons" was not showing the full RAPAX subcategory structure. It only showed 2 items instead of the complete nested hierarchy:

**Expected:**
```
Raven Weapons
└── RAPAX
    ├── RAPAX (sub)
    │   ├── RX Sport
    │   ├── RX Tactical
    │   └── RX Compact
    └── Caracal Lynx
        ├── LYNX SPORT
        ├── LYNX OPEN
        └── LYNX COMPACT
```

**Actual:** Only showed hardcoded fallback navigation (Sturmgewehre, RAPAX)

### Root Cause
Two issues were identified:

1. **Wrong Twig variable** - Template was using `page.header.navigation.tree` but the correct variable in Shopware 6 header context is `header.navigation.tree`

2. **Navigation depth too shallow** - Shopware's `navigationCategoryDepth` was set to 3, but RAPAX categories go 5 levels deep

### Solution Applied

#### 1. Fixed Template Variable
Updated `shopware-theme/RavenTheme/src/Resources/views/storefront/layout/header/header.html.twig`:

```twig
{# WRONG - page variable not available in header context #}
{% if page.header.navigation.tree is defined %}

{# CORRECT - header variable is available #}
{% if header.navigation.tree is defined %}
```

#### 2. Increased Navigation Depth
Updated Shopware Sales Channel settings via API:

```php
// Set navigation depth to 5 (was 3)
apiPatch($config, $token, "sales-channel/$salesChannelId", [
    'navigationCategoryDepth' => 5
]);
```

#### 3. Added 4-Level Nesting Support in Template
The template now supports 4 levels of nested categories in the dropdown:

| Level | Symbol | Example |
|-------|--------|---------|
| 1 | (main) | Raven Weapons |
| 2 | ↳ | RAPAX |
| 3 | • | Caracal Lynx, RAPAX (sub) |
| 4 | - | LYNX SPORT, RX Sport |

**Template structure:**
```twig
{% for treeItem in header.navigation.tree %}
    {# Level 1: Main category #}
    {{ category.translated.name }}

    {% for childItem in treeItem.children %}
        {# Level 2: Children (↳) #}
        ↳ {{ childCat.translated.name }}

        {% for grandChildItem in childItem.children %}
            {# Level 3: Grandchildren (•) #}
            • {{ grandChildCat.translated.name }}

            {% for greatGrandChildItem in grandChildItem.children %}
                {# Level 4: Great-grandchildren (-) #}
                - {{ greatGrandChildCat.translated.name }}
            {% endfor %}
        {% endfor %}
    {% endfor %}
{% endfor %}
```

### Scripts Created

| Script | Purpose |
|--------|---------|
| `scripts/fix-nav-depth-5.php` | Set navigation depth to 5 via API |
| `scripts/debug-nav-categories.php` | Debug category tree structure |
| `scripts/check-nav-tree-api.php` | Check Store API navigation tree |

### How to Deploy Navigation Changes

```bash
# 1. Copy updated header template to server
scp shopware-theme/RavenTheme/src/Resources/views/storefront/layout/header/header.html.twig root@77.42.19.154:/tmp/

# 2. Deploy to Docker container
ssh root@77.42.19.154 "docker cp /tmp/header.html.twig shopware-chf:/var/www/html/custom/plugins/RavenTheme/src/Resources/views/storefront/layout/header/header.html.twig"

# 3. Clear cache
ssh root@77.42.19.154 "docker exec shopware-chf php bin/console cache:clear"
```

### Category Structure Reference

**Raven Weapons Categories (with IDs):**

| Category | Level | ID |
|----------|-------|-----|
| Raven Weapons | 2 | `a61f19c9cb4b11f0b4074aca3d279c31` |
| RAPAX (main) | 3 | `1f36ebeb19da4fc6bc9cb3c3acfadafd` |
| RAPAX (sub) | 4 | `95a7cf1575ddc0219d8f11484ab0cbeb` |
| Caracal Lynx | 4 | `2b3fdb3f3dcc00eacf9c9683d5d22c6a` |
| RX Sport | 5 | `34c00eca0b38ba3aa4ae483722859b4e` |
| RX Tactical | 5 | `fa470225519fd7d666f28d89caf25c8d` |
| RX Compact | 5 | `ea2e04075bc0d5c50cfb0a4b52930401` |
| LYNX SPORT | 5 | `66ed5338a8574c803e01da3cb9e1f2d4` |
| LYNX OPEN | 5 | `7048c95bf71dd4802adb7846617b4503` |
| LYNX COMPACT | 5 | `da98c38ad3e48c6965ff0e93769115d4` |

### Result
Navigation dropdown now shows full RAPAX structure on hover:

```
Raven Weapons
↳ RAPAX
  • Caracal Lynx
    - LYNX SPORT
    - LYNX OPEN
    - LYNX COMPACT
  • RAPAX
    - RX Sport
    - RX Compact
    - RX Tactical
```

---

## License

Proprietary - RAVEN WEAPON AG

---

*Last updated: December 16, 2024*
