/**
 * Snigel B2B Multi-Category Scraper
 *
 * Scrapes each of the B2B category pages to get:
 * - How many products are in each category
 * - Which products are in each category
 * - Identifies products that belong to multiple categories
 *
 * Run: node scripts/snigel-multi-category-scraper.js
 */

const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

// B2B credentials from README
const B2B_URL = 'https://products.snigel.se';
const USERNAME = 'Raven Weapon AG';
const PASSWORD = 'wVREVbRZfqT&Fba@f(^2UKOw';

// Categories from B2B site (using /product-category/all/[slug]/ format)
// Mapped to our 19 English category names
const CATEGORIES = [
    { name: 'Tactical Gear', slug: 'tactical-gear' },
    { name: 'Tactical Clothing', slug: 'tactical-clothing' },
    { name: 'Vests & Chest Rigs', slug: 'vests-chest-rigs' },
    { name: 'Bags & Backpacks', slug: 'bags-and-backpacks' },
    { name: 'Belts', slug: 'belts' },
    { name: 'Ballistic Protection', slug: 'ballistic-protection' },
    { name: 'Slings & Holsters', slug: 'slings-holsters' },
    { name: 'Medical Gear', slug: 'medical-gear' },
    { name: 'Police Gear', slug: 'police-gear' },
    { name: 'Admin Gear', slug: 'administrative-products' },
    { name: 'Holders & Pouches', slug: 'holders-pouches' },
    { name: 'Patches', slug: 'patches' },
    { name: 'K9 Gear', slug: 'k9-units-gear' },
    { name: 'Leg Panels', slug: 'leg-panels' },
    { name: 'Duty Gear', slug: 'duty-gear' },
    { name: 'Covert Gear', slug: 'covert-gear' },
    { name: 'Sniper Gear', slug: 'sniper-gear' },
    { name: 'Source Hydration', slug: 'source-hydration' },
    { name: 'Miscellaneous', slug: 'miscellaneous-products' },
    { name: 'HighVis', slug: 'highvis' },
    { name: 'Multicam', slug: 'multicam' },
];

async function login(page) {
    console.log('Logging in to B2B portal...');
    await page.goto(`${B2B_URL}/my-account/`);
    await page.waitForTimeout(2000);

    // Fill login form
    await page.fill('input[name="username"]', USERNAME);
    await page.fill('input[name="password"]', PASSWORD);
    await page.click('button[name="login"], input[type="submit"][value="Log in"], button.woocommerce-button');
    await page.waitForTimeout(3000);

    // Check if logged in
    const loggedIn = await page.evaluate(() => {
        return document.body.innerText.includes('Hello') ||
               document.body.innerText.includes('Log Out') ||
               document.body.innerText.includes('Dashboard');
    });

    if (loggedIn) {
        console.log('Logged in successfully!\n');
    } else {
        console.log('Login may have failed, continuing anyway...\n');
    }
}

async function scrapeCategory(page, category) {
    const url = `${B2B_URL}/product-category/all/${category.slug}/`;
    console.log(`\nScraping: ${category.name}`);
    console.log(`URL: ${url}`);

    try {
        await page.goto(url, { timeout: 60000 });
    } catch (e) {
        console.log(`  Retrying ${category.name}...`);
        await page.waitForTimeout(3000);
        await page.goto(url, { timeout: 60000 });
    }
    await page.waitForTimeout(2000);

    const products = [];
    let pageNum = 1;
    let hasMore = true;

    while (hasMore) {
        // Scroll to load all products (handles lazy loading)
        await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
        await page.waitForTimeout(1000);

        // Get all products on current page using h3 links
        const pageProducts = await page.evaluate(() => {
            const items = [];

            // Find all h3 headings with product links
            const headings = document.querySelectorAll('h3');

            headings.forEach(h3 => {
                const link = h3.querySelector('a[href*="/product/"]');
                if (link) {
                    const name = link.textContent.trim();
                    const href = link.href;
                    const slug = href.split('/product/')[1]?.replace(/\/$/, '') || '';

                    if (name && !items.find(i => i.name === name)) {
                        items.push({ name, slug, url: href });
                    }
                }
            });

            return items;
        });

        if (pageProducts.length > 0) {
            products.push(...pageProducts);
            console.log(`  Page ${pageNum}: Found ${pageProducts.length} products`);
        }

        // Check for next page - look for pagination links
        const nextPageUrl = await page.evaluate((currentPage) => {
            // Check for "Loading..." or numbered pagination
            const nextLink = document.querySelector('.next.page-numbers, a.next, .pagination-next a');
            if (nextLink) return nextLink.href;

            // Check for upage parameter based pagination
            const loadingLink = document.querySelector(`a[href*="upage=${currentPage + 1}"]`);
            if (loadingLink) return loadingLink.href;

            return null;
        }, pageNum);

        if (nextPageUrl && pageNum < 10) { // Safety limit
            pageNum++;
            try {
                await page.goto(nextPageUrl, { timeout: 60000 });
            } catch (e) {
                console.log(`  Pagination timeout, continuing...`);
                hasMore = false;
                continue;
            }
            await page.waitForTimeout(2000);
        } else {
            hasMore = false;
        }
    }

    // Remove duplicates
    const uniqueProducts = [];
    const seen = new Set();
    for (const p of products) {
        if (!seen.has(p.name)) {
            seen.add(p.name);
            uniqueProducts.push(p);
        }
    }

    console.log(`  Total unique products: ${uniqueProducts.length}`);
    return uniqueProducts;
}

async function main() {
    console.log('╔════════════════════════════════════════════════════════════╗');
    console.log('║  SNIGEL B2B MULTI-CATEGORY SCRAPER                        ║');
    console.log('╚════════════════════════════════════════════════════════════╝\n');

    const browser = await chromium.launch({ headless: false });
    const context = await browser.newContext();
    const page = await context.newPage();

    try {
        await login(page);

        const categoryData = {};
        const productCategories = {}; // product name => [categories]

        // Scrape each category
        for (const category of CATEGORIES) {
            const products = await scrapeCategory(page, category);
            categoryData[category.name] = products;

            // Track which categories each product belongs to
            for (const product of products) {
                if (!productCategories[product.name]) {
                    productCategories[product.name] = [];
                }
                if (!productCategories[product.name].includes(category.name)) {
                    productCategories[product.name].push(category.name);
                }
            }
        }

        // Generate report
        console.log('\n');
        console.log('╔════════════════════════════════════════════════════════════╗');
        console.log('║                    CATEGORY SUMMARY                        ║');
        console.log('╠════════════════════════════════════════════════════════════╣');

        let totalProducts = 0;
        for (const [catName, products] of Object.entries(categoryData)) {
            console.log(`║  ${catName.padEnd(35)} ${String(products.length).padStart(3)} products  ║`);
            totalProducts += products.length;
        }
        console.log('╠════════════════════════════════════════════════════════════╣');
        console.log(`║  TOTAL (with duplicates):          ${String(totalProducts).padStart(3)} products  ║`);
        console.log(`║  UNIQUE products:                  ${String(Object.keys(productCategories).length).padStart(3)} products  ║`);
        console.log('╚════════════════════════════════════════════════════════════╝\n');

        // Find products in multiple categories
        const multiCategoryProducts = Object.entries(productCategories)
            .filter(([name, cats]) => cats.length > 1)
            .sort((a, b) => b[1].length - a[1].length);

        console.log('╔════════════════════════════════════════════════════════════╗');
        console.log('║           PRODUCTS IN MULTIPLE CATEGORIES                 ║');
        console.log('╠════════════════════════════════════════════════════════════╣');
        console.log(`║  Total products in multiple categories: ${String(multiCategoryProducts.length).padStart(16)} ║`);
        console.log('╚════════════════════════════════════════════════════════════╝\n');

        if (multiCategoryProducts.length > 0) {
            console.log('Products with multiple categories:');
            for (const [name, cats] of multiCategoryProducts.slice(0, 30)) {
                console.log(`  - ${name}`);
                console.log(`    Categories: ${cats.join(', ')}`);
            }
            if (multiCategoryProducts.length > 30) {
                console.log(`  ... and ${multiCategoryProducts.length - 30} more`);
            }
        }

        // Save results
        const outputDir = path.join(__dirname, 'snigel-data');
        if (!fs.existsSync(outputDir)) {
            fs.mkdirSync(outputDir, { recursive: true });
        }

        // Save category data
        const categoryOutput = path.join(outputDir, 'category-products.json');
        fs.writeFileSync(categoryOutput, JSON.stringify(categoryData, null, 2));
        console.log(`\nSaved category data to: ${categoryOutput}`);

        // Save product-to-categories mapping
        const mappingOutput = path.join(outputDir, 'product-categories-mapping.json');
        fs.writeFileSync(mappingOutput, JSON.stringify(productCategories, null, 2));
        console.log(`Saved product mapping to: ${mappingOutput}`);

        // Save multi-category products
        const multiOutput = path.join(outputDir, 'multi-category-products.json');
        fs.writeFileSync(multiOutput, JSON.stringify(
            Object.fromEntries(multiCategoryProducts),
            null, 2
        ));
        console.log(`Saved multi-category products to: ${multiOutput}`);

        console.log('\nDone! Close browser when ready.');

        // Keep browser open to review
        await page.waitForTimeout(10000);

    } catch (error) {
        console.error('Error:', error);
    } finally {
        await browser.close();
    }
}

main();
