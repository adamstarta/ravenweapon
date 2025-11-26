# RAVEN WEAPON AG - Shopware E-Commerce

Swiss firearms e-commerce platform built on Shopware 6.6 with custom RavenTheme.

## Quick Start (Windows)

### Prerequisites
- Docker Desktop for Windows
- Git

### Setup

1. **Clone the repository**
   ```bash
   git clone https://github.com/adamstarta/ravenweapon.git
   cd ravenweapon
   ```

2. **Start Docker container**
   ```bash
   docker-compose up -d
   ```
   Wait ~2 minutes for Shopware to initialize.

3. **Activate the RavenTheme**
   ```bash
   docker exec ravenweapon-shop bash -c "cd /var/www/html && bin/console plugin:refresh && bin/console plugin:install RavenTheme --activate && bin/console theme:change RavenTheme --all && bin/console cache:clear"
   ```

4. **Deploy homepage template and assets**
   ```bash
   # Create directories
   docker exec -u root ravenweapon-shop mkdir -p /var/www/html/custom/plugins/RavenTheme/src/Resources/views/storefront/page/content
   docker exec -u root ravenweapon-shop mkdir -p /var/www/html/custom/plugins/RavenTheme/src/Resources/public/assets

   # Copy template files
   docker cp temp-homepage.html.twig ravenweapon-shop:/var/www/html/custom/plugins/RavenTheme/src/Resources/views/storefront/page/content/index.html.twig
   docker cp temp-base.scss ravenweapon-shop:/var/www/html/custom/plugins/RavenTheme/src/Resources/app/storefront/src/scss/base.scss

   # Copy assets
   docker cp "assets/wallpaper hero.png" ravenweapon-shop:/var/www/html/custom/plugins/RavenTheme/src/Resources/public/assets/hero-background.jpg
   docker cp "assets/snigel logo.png" ravenweapon-shop:/var/www/html/custom/plugins/RavenTheme/src/Resources/public/assets/brand-snigel.png
   docker cp "assets/zero tech logo.png" ravenweapon-shop:/var/www/html/custom/plugins/RavenTheme/src/Resources/public/assets/brand-zerotech.png
   docker cp "assets/magpul logo.png" ravenweapon-shop:/var/www/html/custom/plugins/RavenTheme/src/Resources/public/assets/brand-magpul.png
   docker cp "assets/lockhart logo.png" ravenweapon-shop:/var/www/html/custom/plugins/RavenTheme/src/Resources/public/assets/brand-lockhart.png

   # Install assets and compile theme
   docker exec ravenweapon-shop bash -c "cd /var/www/html && bin/console assets:install && bin/console theme:compile && bin/console cache:clear"
   ```

5. **Access the site**
   - Storefront: http://localhost/
   - Admin Panel: http://localhost/admin
     - Username: `admin`
     - Password: `shopware`
   - Database (Adminer): http://localhost:8888

## Project Structure

```
ravenweapon/
├── assets/                    # Product images and brand logos
├── docs/plans/               # Design and implementation documentation
├── import/                   # Product import CSV and scripts
├── legacy/                   # Original HTML/CSS/JS (reference)
│   ├── html/                # Static HTML pages
│   ├── css/                 # Original stylesheets
│   └── js/                  # Original JavaScript
├── shopware-theme/          # Shopware plugin source
│   └── RavenTheme/
│       └── src/
│           ├── Resources/
│           │   ├── app/storefront/src/scss/  # SCSS styles
│           │   ├── views/storefront/         # Twig templates
│           │   └── theme.json               # Theme config
│           └── RavenTheme.php
├── temp-*.scss              # Development SCSS files
├── temp-*.twig              # Development Twig templates
├── docker-compose.yml       # Docker configuration
└── README.md
```

## Development Workflow

### Modifying Styles
1. Edit `temp-base.scss`
2. Copy to container:
   ```bash
   docker cp temp-base.scss ravenweapon-shop:/var/www/html/custom/plugins/RavenTheme/src/Resources/app/storefront/src/scss/base.scss
   ```
3. Compile:
   ```bash
   docker exec ravenweapon-shop bash -c "cd /var/www/html && bin/console theme:compile && bin/console cache:clear"
   ```

### Modifying Templates
1. Edit `temp-homepage.html.twig` (or other temp-*.twig files)
2. Copy to container:
   ```bash
   docker cp temp-homepage.html.twig ravenweapon-shop:/var/www/html/custom/plugins/RavenTheme/src/Resources/views/storefront/page/content/index.html.twig
   ```
3. Clear cache:
   ```bash
   docker exec ravenweapon-shop bash -c "cd /var/www/html && bin/console cache:clear"
   ```

## Branding

### Colors
- **Gold Gradient**: `linear-gradient(135deg, #FDE047 0%, #F59E0B 50%, #D97706 100%)`
- **Primary Gold**: `#F59E0B`
- **Dark Text**: `#111827`
- **Body Text**: `#374151`

### Typography
- Headings: Chakra Petch (hero), Inter (body)
- Gold gradient text for H1 elements

## Homepage Sections

1. **Hero** - Rifle background, gold title, CTAs
2. **Beliebte Produkte** - Featured products (configure in Shopware CMS)
3. **Kategorien** - 4 category cards (Waffen, Munition, Waffenzubehör, Ausrüstung)
4. **Unsere Marken** - Brand logos (Snigel, ZeroTech, Magpul, Lockhart)
5. **Video** - Cloudflare Stream embed with description
6. **Footer** - Trust badges, newsletter, legal links

## Useful Commands

```bash
# View container logs
docker logs ravenweapon-shop -f

# Access container shell
docker exec -it ravenweapon-shop bash

# Restart container
docker-compose restart

# Stop container
docker-compose down

# Clear all Shopware caches
docker exec ravenweapon-shop bash -c "cd /var/www/html && bin/console cache:clear"

# Recompile theme
docker exec ravenweapon-shop bash -c "cd /var/www/html && bin/console theme:compile"

# Refresh plugins
docker exec ravenweapon-shop bash -c "cd /var/www/html && bin/console plugin:refresh"
```

## Troubleshooting

### Container won't start
- Ensure Docker Desktop is running
- Check if port 80 is already in use: `netstat -an | findstr :80`

### Theme not showing
- Run the theme activation commands from step 3
- Clear browser cache

### Assets 404 errors
- Run `bin/console assets:install` in container
- Verify files exist in `/var/www/html/public/bundles/raventheme/assets/`

## License

Proprietary - RAVEN WEAPON AG
