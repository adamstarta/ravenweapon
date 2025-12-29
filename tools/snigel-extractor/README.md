# Snigel B2B Product Extractor

Tools for scraping products, prices, and stock levels from the Snigel B2B portal (products.snigel.se) for import into Shopware.

## Prerequisites

- Node.js 18+ (for JS scrapers)
- PHP 8.x (for PHP scrapers)
- Playwright (`npm install playwright`)

## Tools

### scrapers/snigel-scraper.php
Main product scraper. Logs into Snigel B2B portal and extracts:
- Product names, SKUs, descriptions
- Prices in EUR
- Product images

```bash
php scrapers/snigel-scraper.php
```

### scrapers/snigel-stock-scraper.js
Stock level scraper using Playwright for browser automation.
Extracts stock levels for all product variants.

```bash
node scrapers/snigel-stock-scraper.js
```

### scrapers/snigel-b2b-sync.js
Comprehensive B2B sync tool. Syncs prices from Snigel B2B to Shopware.

```bash
node scrapers/snigel-b2b-sync.js
```

## Output

Data is saved to the `data/` directory:
- `products.json` - Full product catalog
- `products.csv` - CSV export for spreadsheet use
- `stock-YYYY-MM-DD.json` - Daily stock snapshots
- `images/` - Downloaded product images

## Configuration

Edit the credentials in each scraper file:
- `username`: B2B portal username
- `password`: B2B portal password
- `currency`: EUR (default)
