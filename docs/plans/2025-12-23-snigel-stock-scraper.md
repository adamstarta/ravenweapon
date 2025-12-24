# Snigel B2B Stock Scraper Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Build a Playwright scraper that captures stock levels for ALL variant combinations from Snigel B2B portal and outputs JSON for Shopware sync.

**Architecture:** Login to B2B portal, collect all product URLs, visit each product page, detect variant dropdowns (Color/Size), iterate through all combinations, capture stock per variant with unique SKU.

**Tech Stack:** Node.js, Playwright, JSON output

---

## Task 1: Create Base Script Structure

**Files:**
- Create: `scripts/snigel-stock-scraper.js`

**Step 1: Create the script file with config and imports**

```javascript
/**
 * Snigel B2B Stock Scraper
 * Scrapes stock levels for all product variants from B2B portal
 */

const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const config = {
    baseUrl: 'https://products.snigel.se',
    username: 'Raven Weapon AG',
    password: 'wVREVbRZfqT&Fba@f(^2UKOw',
    outputDir: path.join(__dirname, 'snigel-stock-data'),
    currency: 'EUR'
};

// Create output directory
if (!fs.existsSync(config.outputDir)) {
    fs.mkdirSync(config.outputDir, { recursive: true });
}

function delay(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

function log(msg) {
    console.log(`[${new Date().toISOString().substr(11, 8)}] ${msg}`);
}

async function main() {
    log('========================================');
    log('   SNIGEL B2B STOCK SCRAPER');
    log('========================================');

    // TODO: Implement in next tasks
}

main().catch(console.error);
```

**Step 2: Verify file was created**

Run: `dir scripts\snigel-stock-scraper.js`
Expected: File exists

**Step 3: Test script runs without errors**

Run: `cd scripts && node snigel-stock-scraper.js`
Expected: Shows header output

---

## Task 2: Implement Login Function

**Files:**
- Modify: `scripts/snigel-stock-scraper.js`

**Step 1: Add login function after delay function**

```javascript
async function login(page) {
    log('Logging in to B2B portal...');

    await page.goto(`${config.baseUrl}/my-account/`);
    await page.waitForLoadState('networkidle');
    await delay(2000);

    // Dismiss cookie popup if present
    try {
        await page.click('button:has-text("Ok")', { timeout: 3000 });
    } catch (e) {}

    // Fill login form
    await page.fill('#username', config.username);
    await page.fill('#password', config.password);
    await page.click('button[name="login"]');
    await page.waitForLoadState('networkidle');
    await delay(3000);

    // Verify login success
    const logoutLink = await page.$('a:has-text("LOG OUT")');
    if (!logoutLink) {
        throw new Error('Login failed - logout link not found');
    }

    log('Login successful!');
}
```

**Step 2: Update main function to use login**

```javascript
async function main() {
    log('========================================');
    log('   SNIGEL B2B STOCK SCRAPER');
    log('========================================');

    const browser = await chromium.launch({ headless: false });
    const context = await browser.newContext({
        userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        viewport: { width: 1920, height: 1080 }
    });
    const page = await context.newPage();
    page.setDefaultTimeout(60000);

    try {
        await login(page);
        // TODO: Next tasks
    } catch (error) {
        log(`ERROR: ${error.message}`);
        console.error(error.stack);
    } finally {
        await browser.close();
    }
}
```

**Step 3: Test login works**

Run: `cd scripts && node snigel-stock-scraper.js`
Expected: "Login successful!" message

---

## Task 3: Implement Product URL Collection

**Files:**
- Modify: `scripts/snigel-stock-scraper.js`

**Step 1: Add function to collect all product URLs**

```javascript
async function collectProductUrls(page) {
    log('Collecting product URLs...');

    await page.goto(`${config.baseUrl}/product-category/all/?currency=${config.currency}`);
    await page.waitForLoadState('networkidle');
    await delay(3000);

    // Dismiss cookie popup
    try {
        await page.click('button:has-text("Ok")', { timeout: 2000 });
    } catch (e) {}

    // Scroll to load all products (infinite scroll)
    let lastCount = 0;
    let stableCount = 0;

    while (stableCount < 5) {
        await page.evaluate(() => window.scrollBy(0, 1000));
        await delay(1500);

        const count = await page.$$eval('.tmb.tmb-woocommerce', items => items.length);

        if (count === lastCount) {
            stableCount++;
        } else {
            stableCount = 0;
            lastCount = count;
            process.stdout.write(`\r  Found ${count} products...`);
        }
    }
    console.log('');

    // Extract unique product URLs
    const urls = await page.evaluate(() => {
        const links = document.querySelectorAll('.tmb.tmb-woocommerce a[href*="/product/"]');
        const uniqueUrls = new Set();
        links.forEach(link => uniqueUrls.add(link.href));
        return Array.from(uniqueUrls);
    });

    log(`Collected ${urls.length} product URLs`);
    return urls;
}
```

**Step 2: Update main to call collectProductUrls**

```javascript
// In main(), after login:
const productUrls = await collectProductUrls(page);
log(`Total products to scrape: ${productUrls.length}`);
```

**Step 3: Test URL collection**

Run: `cd scripts && node snigel-stock-scraper.js`
Expected: "Collected 195 product URLs" (approximately)

---

## Task 4: Implement Single Product Stock Scraper

**Files:**
- Modify: `scripts/snigel-stock-scraper.js`

**Step 1: Add function to parse stock text**

```javascript
function parseStock(stockText) {
    if (!stockText) return { stock: 999, status: 'no_info' };

    const text = stockText.toLowerCase().trim();

    if (text.includes('out of stock')) {
        return { stock: 0, status: 'out_of_stock' };
    }

    const match = text.match(/(\d+)\s*in stock/i);
    if (match) {
        return {
            stock: parseInt(match[1]),
            status: 'in_stock',
            canBackorder: text.includes('backordered')
        };
    }

    return { stock: 999, status: 'no_info' };
}
```

**Step 2: Add function to scrape single product with all variants**

```javascript
async function scrapeProductStock(page, url) {
    await page.goto(url);
    await page.waitForLoadState('networkidle');
    await delay(1500);

    // Get product name
    const name = await page.$eval('h1', el => el.textContent.trim()).catch(() => 'Unknown');
    const slug = url.split('/product/')[1]?.replace(/\/$/, '') || '';

    // Find all variant dropdowns (Color, Size, etc.)
    const dropdowns = await page.$$('select[id^="pa_"], select[name^="attribute_"]');

    const variants = [];

    if (dropdowns.length === 0) {
        // SIMPLE PRODUCT - no variants
        const stockText = await page.$eval('.stock', el => el.textContent).catch(() => null);
        const sku = await page.$eval('.sku', el => el.textContent.trim()).catch(() => '');
        const stockInfo = parseStock(stockText);

        variants.push({
            sku,
            options: {},
            ...stockInfo
        });
    } else if (dropdowns.length === 1) {
        // SINGLE DROPDOWN - iterate through options
        const dropdown = dropdowns[0];
        const options = await dropdown.$$eval('option[value]:not([value=""])', opts =>
            opts.map(o => ({ value: o.value, label: o.textContent.trim() }))
        );
        const attrName = await dropdown.getAttribute('id') || 'option';

        for (const option of options) {
            await dropdown.selectOption(option.value);
            await delay(1000);

            const stockText = await page.$eval('.stock', el => el.textContent).catch(() => null);
            const sku = await page.$eval('.sku', el => el.textContent.trim()).catch(() => '');
            const stockInfo = parseStock(stockText);

            variants.push({
                sku,
                options: { [attrName.replace('pa_', '')]: option.label },
                ...stockInfo
            });
        }
    } else {
        // MULTIPLE DROPDOWNS - iterate through all combinations
        const allOptions = [];

        for (const dropdown of dropdowns) {
            const attrName = await dropdown.getAttribute('id') || 'option';
            const options = await dropdown.$$eval('option[value]:not([value=""])', opts =>
                opts.map(o => ({ value: o.value, label: o.textContent.trim() }))
            );
            allOptions.push({ dropdown, attrName: attrName.replace('pa_', ''), options });
        }

        // Generate all combinations
        const combinations = generateCombinations(allOptions.map(a => a.options));

        for (let i = 0; i < combinations.length; i++) {
            const combo = combinations[i];
            const optionsMap = {};

            // Select each dropdown value
            for (let j = 0; j < allOptions.length; j++) {
                const { dropdown, attrName, options } = allOptions[j];
                const option = combo[j];
                await dropdown.selectOption(option.value);
                optionsMap[attrName] = option.label;
            }

            await delay(1000);

            const stockText = await page.$eval('.stock', el => el.textContent).catch(() => null);
            const sku = await page.$eval('.sku', el => el.textContent.trim()).catch(() => '');
            const stockInfo = parseStock(stockText);

            variants.push({
                sku,
                options: optionsMap,
                ...stockInfo
            });
        }
    }

    return {
        name,
        slug,
        url,
        variants,
        scrapedAt: new Date().toISOString()
    };
}
```

**Step 3: Add helper function for combinations**

```javascript
function generateCombinations(arrays) {
    if (arrays.length === 0) return [[]];

    const [first, ...rest] = arrays;
    const restCombos = generateCombinations(rest);

    const result = [];
    for (const item of first) {
        for (const combo of restCombos) {
            result.push([item, ...combo]);
        }
    }
    return result;
}
```

---

## Task 5: Implement Main Scraping Loop

**Files:**
- Modify: `scripts/snigel-stock-scraper.js`

**Step 1: Update main function with full scraping loop**

```javascript
async function main() {
    log('========================================');
    log('   SNIGEL B2B STOCK SCRAPER');
    log('========================================');

    const browser = await chromium.launch({ headless: false });
    const context = await browser.newContext({
        userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        viewport: { width: 1920, height: 1080 }
    });
    const page = await context.newPage();
    page.setDefaultTimeout(60000);

    const results = [];
    const errors = [];

    try {
        await login(page);
        const productUrls = await collectProductUrls(page);

        log(`\nScraping ${productUrls.length} products...\n`);

        for (let i = 0; i < productUrls.length; i++) {
            const url = productUrls[i];
            const progress = `[${i + 1}/${productUrls.length}]`;

            try {
                const product = await scrapeProductStock(page, url);
                results.push(product);

                const variantCount = product.variants.length;
                const totalStock = product.variants.reduce((sum, v) => sum + v.stock, 0);
                log(`${progress} ${product.name.substring(0, 40).padEnd(40)} | ${variantCount} variants | Stock: ${totalStock}`);

            } catch (error) {
                log(`${progress} ERROR: ${url} - ${error.message}`);
                errors.push({ url, error: error.message });
            }

            // Small delay between products
            await delay(500);
        }

        // Save results
        const timestamp = new Date().toISOString().split('T')[0];
        const outputFile = path.join(config.outputDir, `stock-${timestamp}.json`);

        const output = {
            scrapedAt: new Date().toISOString(),
            totalProducts: results.length,
            totalVariants: results.reduce((sum, p) => sum + p.variants.length, 0),
            products: results,
            errors
        };

        fs.writeFileSync(outputFile, JSON.stringify(output, null, 2));

        // Summary
        log('\n========================================');
        log('   COMPLETE');
        log('========================================');
        log(`Products scraped: ${results.length}`);
        log(`Total variants: ${output.totalVariants}`);
        log(`Errors: ${errors.length}`);
        log(`Output: ${outputFile}`);
        log('========================================\n');

    } catch (error) {
        log(`FATAL ERROR: ${error.message}`);
        console.error(error.stack);
    } finally {
        await browser.close();
    }
}
```

---

## Task 6: Add Progress Save and Resume Feature

**Files:**
- Modify: `scripts/snigel-stock-scraper.js`

**Step 1: Add save/resume functionality**

```javascript
const progressFile = path.join(config.outputDir, 'progress.json');

function saveProgress(results, errors, currentIndex) {
    fs.writeFileSync(progressFile, JSON.stringify({
        results,
        errors,
        currentIndex,
        savedAt: new Date().toISOString()
    }, null, 2));
}

function loadProgress() {
    if (fs.existsSync(progressFile)) {
        return JSON.parse(fs.readFileSync(progressFile, 'utf8'));
    }
    return null;
}

function clearProgress() {
    if (fs.existsSync(progressFile)) {
        fs.unlinkSync(progressFile);
    }
}
```

**Step 2: Update main loop to use progress**

In the main scraping loop, add:
```javascript
// Before loop
const progress = loadProgress();
let startIndex = 0;
if (progress) {
    log(`Resuming from product ${progress.currentIndex + 1}...`);
    results.push(...progress.results);
    errors.push(...progress.errors);
    startIndex = progress.currentIndex + 1;
}

// In loop, after each product
if ((i + 1) % 10 === 0) {
    saveProgress(results, errors, i);
    log(`  [Progress saved at ${i + 1}/${productUrls.length}]`);
}

// After loop completes
clearProgress();
```

---

## Task 7: Test Complete Script

**Files:**
- Test: `scripts/snigel-stock-scraper.js`

**Step 1: Run full scraper**

Run: `cd scripts && node snigel-stock-scraper.js`
Expected:
- Login successful
- Collects ~195 product URLs
- Scrapes each product with variants
- Saves to `snigel-stock-data/stock-YYYY-MM-DD.json`

**Step 2: Verify output JSON structure**

Check output file contains:
```json
{
  "scrapedAt": "2025-12-23T...",
  "totalProducts": 195,
  "totalVariants": 500,
  "products": [
    {
      "name": "Speed Magazine Pouch 2.0",
      "slug": "speed-magazine-pouch-2-0",
      "variants": [
        { "sku": "22-01888B01-000", "options": { "colour": "Black" }, "stock": 706, "status": "in_stock" },
        { "sku": "22-01888B09-000", "options": { "colour": "Grey" }, "stock": 200, "status": "in_stock" }
      ]
    }
  ]
}
```

---

## Summary

| Task | Description | Est. Time |
|------|-------------|-----------|
| 1 | Create base script structure | 2 min |
| 2 | Implement login function | 3 min |
| 3 | Implement product URL collection | 3 min |
| 4 | Implement single product stock scraper | 10 min |
| 5 | Implement main scraping loop | 5 min |
| 6 | Add progress save/resume feature | 3 min |
| 7 | Test complete script | 5 min |

**Total: ~30 minutes**

---

## Execution

After saving this plan, choose execution method:
1. **Subagent-Driven** - I dispatch fresh subagent per task
2. **Direct Implementation** - I implement tasks sequentially now
