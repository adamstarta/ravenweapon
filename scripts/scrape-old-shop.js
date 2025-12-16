/**
 * Scraper for old Raven Weapon shop (shop.ravenweapon.ch/en)
 * Scrapes products from:
 * - Aiming aids, optics & accessories (with hover submenus)
 * - Accessories (with hover submenus)
 */

const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const BASE_URL = 'https://shop.ravenweapon.ch/en';
const OUTPUT_DIR = path.join(__dirname, 'old-shop-data');

// Ensure output directory exists
if (!fs.existsSync(OUTPUT_DIR)) {
    fs.mkdirSync(OUTPUT_DIR, { recursive: true });
}

async function delay(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

async function scrapeProductsFromPage(page, categoryName, subcategoryName) {
    const products = [];

    // Wait for products to load
    await delay(2000);

    // Try different selectors for product listings
    const productSelectors = [
        '.product-box',
        '.product-item',
        '.product-card',
        '[data-product]',
        '.product',
        '.cms-listing-col'
    ];

    let productElements = [];
    for (const selector of productSelectors) {
        productElements = await page.$$(selector);
        if (productElements.length > 0) {
            console.log(`  Found ${productElements.length} products using selector: ${selector}`);
            break;
        }
    }

    if (productElements.length === 0) {
        // Try to find any product-like elements
        console.log('  Trying alternative product detection...');
        productElements = await page.$$('.card, .listing-item, article');
    }

    for (const productEl of productElements) {
        try {
            // Get product name
            const nameEl = await productEl.$('.product-name, .product-title, h2, h3, .title, .name, a[title]');
            let name = '';
            if (nameEl) {
                name = await nameEl.textContent();
                if (!name) {
                    name = await nameEl.getAttribute('title') || '';
                }
            }

            // Get product price
            const priceEl = await productEl.$('.product-price, .price, [class*="price"], .product-price-info');
            let price = '';
            if (priceEl) {
                price = await priceEl.textContent();
            }

            // Get product URL
            const linkEl = await productEl.$('a[href*="/detail/"], a[href*="/product/"], a.product-link, a');
            let url = '';
            if (linkEl) {
                url = await linkEl.getAttribute('href');
            }

            if (name && name.trim()) {
                products.push({
                    name: name.trim().replace(/\s+/g, ' '),
                    price: price ? price.trim().replace(/\s+/g, ' ') : 'N/A',
                    url: url || '',
                    category: categoryName,
                    subcategory: subcategoryName
                });
            }
        } catch (err) {
            console.log('  Error extracting product:', err.message);
        }
    }

    // Check for pagination
    const nextPageBtn = await page.$('.pagination-next:not(.disabled), .page-next:not(.disabled), a[rel="next"]');
    if (nextPageBtn) {
        console.log('  Found pagination, checking next page...');
        try {
            await nextPageBtn.click();
            await delay(2000);
            const moreProducts = await scrapeProductsFromPage(page, categoryName, subcategoryName);
            products.push(...moreProducts);
        } catch (err) {
            console.log('  Pagination error:', err.message);
        }
    }

    return products;
}

async function getSubmenusFromCategory(page, categorySelector) {
    const submenus = [];

    // Hover over the category to reveal submenus
    const categoryEl = await page.$(categorySelector);
    if (!categoryEl) {
        console.log(`Category not found: ${categorySelector}`);
        return submenus;
    }

    await categoryEl.hover();
    await delay(1000);

    // Find submenu links
    const submenuSelectors = [
        '.navigation-flyout-content a',
        '.dropdown-menu a',
        '.submenu a',
        '.mega-menu a',
        '.flyout a'
    ];

    for (const selector of submenuSelectors) {
        const links = await page.$$(selector);
        if (links.length > 0) {
            for (const link of links) {
                const text = await link.textContent();
                const href = await link.getAttribute('href');
                if (text && href && !href.includes('#')) {
                    submenus.push({
                        name: text.trim(),
                        url: href.startsWith('http') ? href : `${BASE_URL}${href}`
                    });
                }
            }
            break;
        }
    }

    return submenus;
}

async function main() {
    console.log('=== Old Shop Scraper ===\n');
    console.log('Target: ' + BASE_URL);
    console.log('Categories: Aiming aids, optics & accessories | Accessories\n');

    const browser = await chromium.launch({
        headless: false,
        slowMo: 100
    });

    const context = await browser.newContext({
        viewport: { width: 1920, height: 1080 }
    });

    const page = await context.newPage();

    const allProducts = [];
    const categoryData = {};

    try {
        // Navigate to the main page
        console.log('Navigating to main page...');
        await page.goto(BASE_URL, { waitUntil: 'networkidle', timeout: 60000 });
        await delay(2000);

        // Take a screenshot of the main navigation
        await page.screenshot({ path: path.join(OUTPUT_DIR, 'main-page.png'), fullPage: false });

        // Get the navigation structure
        console.log('\nAnalyzing navigation structure...');

        // Find all main navigation items
        const navItems = await page.$$('.main-navigation-link, .nav-link, .navigation-link, nav a');
        console.log(`Found ${navItems.length} navigation items`);

        // Log navigation items
        for (const nav of navItems) {
            const text = await nav.textContent();
            const href = await nav.getAttribute('href');
            console.log(`  - ${text?.trim()}: ${href}`);
        }

        // Categories to scrape
        const targetCategories = [
            {
                name: 'Aiming aids, optics & accessories',
                searchTerms: ['aiming', 'optics', 'accessories', 'zielfernrohr', 'optik']
            },
            {
                name: 'Accessories',
                searchTerms: ['accessories', 'zubehoer', 'zubehör']
            }
        ];

        // Find and scrape each category
        for (const targetCat of targetCategories) {
            console.log(`\n=== Scraping: ${targetCat.name} ===`);
            categoryData[targetCat.name] = { submenus: [], products: [] };

            // Find the navigation link for this category
            let categoryLink = null;
            for (const nav of navItems) {
                const text = (await nav.textContent())?.toLowerCase() || '';
                const href = (await nav.getAttribute('href')) || '';

                for (const term of targetCat.searchTerms) {
                    if (text.includes(term) || href.toLowerCase().includes(term)) {
                        categoryLink = nav;
                        console.log(`Found category link: ${text.trim()}`);
                        break;
                    }
                }
                if (categoryLink) break;
            }

            if (!categoryLink) {
                console.log(`Category "${targetCat.name}" not found in navigation`);
                continue;
            }

            // Hover to reveal submenus
            await categoryLink.hover();
            await delay(1500);

            // Screenshot the hover state
            await page.screenshot({
                path: path.join(OUTPUT_DIR, `${targetCat.name.replace(/[^a-z0-9]/gi, '-')}-hover.png`),
                fullPage: false
            });

            // Find all submenu links in the flyout/dropdown
            const flyoutLinks = await page.$$('.navigation-flyout a, .dropdown-menu a, .submenu a, .mega-menu-content a');
            console.log(`Found ${flyoutLinks.length} submenu links`);

            const submenus = [];
            for (const link of flyoutLinks) {
                const text = await link.textContent();
                const href = await link.getAttribute('href');
                if (text && href && text.trim() && !href.includes('#')) {
                    submenus.push({
                        name: text.trim(),
                        url: href.startsWith('http') ? href : (href.startsWith('/') ? `https://shop.ravenweapon.ch${href}` : `${BASE_URL}/${href}`)
                    });
                    console.log(`  Submenu: ${text.trim()} -> ${href}`);
                }
            }

            categoryData[targetCat.name].submenus = submenus;

            // If no submenus found, try clicking the main category
            if (submenus.length === 0) {
                console.log('No submenus found, clicking main category...');
                await categoryLink.click();
                await delay(2000);

                const products = await scrapeProductsFromPage(page, targetCat.name, 'Main');
                categoryData[targetCat.name].products.push(...products);
                allProducts.push(...products);

                // Go back to main page
                await page.goto(BASE_URL, { waitUntil: 'networkidle', timeout: 60000 });
                await delay(1000);
            } else {
                // Scrape each submenu
                for (const submenu of submenus) {
                    console.log(`\n  Scraping submenu: ${submenu.name}`);
                    try {
                        await page.goto(submenu.url, { waitUntil: 'networkidle', timeout: 60000 });
                        await delay(1500);

                        const products = await scrapeProductsFromPage(page, targetCat.name, submenu.name);
                        console.log(`    Found ${products.length} products`);

                        categoryData[targetCat.name].products.push(...products);
                        allProducts.push(...products);
                    } catch (err) {
                        console.log(`    Error scraping ${submenu.name}: ${err.message}`);
                    }
                }
            }
        }

        // Save results
        console.log('\n=== Saving Results ===');

        // Save all products
        fs.writeFileSync(
            path.join(OUTPUT_DIR, 'all-products.json'),
            JSON.stringify(allProducts, null, 2),
            'utf-8'
        );
        console.log(`Saved ${allProducts.length} total products to all-products.json`);

        // Save category data
        fs.writeFileSync(
            path.join(OUTPUT_DIR, 'category-data.json'),
            JSON.stringify(categoryData, null, 2),
            'utf-8'
        );
        console.log('Saved category structure to category-data.json');

        // Generate summary
        console.log('\n=== Summary ===');
        for (const [catName, data] of Object.entries(categoryData)) {
            console.log(`${catName}:`);
            console.log(`  Submenus: ${data.submenus.length}`);
            console.log(`  Products: ${data.products.length}`);
        }
        console.log(`\nTotal products scraped: ${allProducts.length}`);

    } catch (error) {
        console.error('Error during scraping:', error);
    } finally {
        await browser.close();
    }

    console.log('\n=== Done ===');
}

main().catch(console.error);
