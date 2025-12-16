/**
 * Scraper for old Raven Weapon shop (shop.ravenweapon.ch/en)
 * Based on discovered menu structure
 */

const https = require('https');
const fs = require('fs');
const path = require('path');

const OUTPUT_DIR = path.join(__dirname, 'old-shop-data');

// Ensure output directory exists
if (!fs.existsSync(OUTPUT_DIR)) {
    fs.mkdirSync(OUTPUT_DIR, { recursive: true });
}

// Category URLs discovered from navigation
const CATEGORIES = {
    'Aiming aids, optics & accessories': {
        url: 'https://shop.ravenweapon.ch/en/categories/aiming-aids-optics-accessories-1743',
        subcategories: {
            'Riflescopes': {
                url: 'https://shop.ravenweapon.ch/en/categories/aiming-aids-optics-accessories-1743/riflescopes-1753',
                children: {
                    'Thrive HD': 'https://shop.ravenweapon.ch/en/categories/aiming-aids-optics-accessories-1743/riflescopes-1753/thrive-hd-1748',
                    'Vengeance': 'https://shop.ravenweapon.ch/en/categories/aiming-aids-optics-accessories-1743/riflescopes-1753/vengeance-1745',
                    'Trace': 'https://shop.ravenweapon.ch/en/categories/aiming-aids-optics-accessories-1743/riflescopes-1753/trace-1749',
                    'Thrive': 'https://shop.ravenweapon.ch/en/categories/aiming-aids-optics-accessories-1743/riflescopes-1753/thrive-1747',
                    'Trace ADV': 'https://shop.ravenweapon.ch/en/categories/aiming-aids-optics-accessories-1743/riflescopes-1753/trace-adv-1751',
                    'Trace ED Riflescopes': 'https://shop.ravenweapon.ch/en/categories/aiming-aids-optics-accessories-1743/riflescopes-1753/trace-ed-riflescopes-1824'
                }
            },
            'Red Dots': {
                url: 'https://shop.ravenweapon.ch/en/categories/aiming-aids-optics-accessories-1743/red-dots-1760',
                children: {
                    'ZeroTech Precision': 'https://shop.ravenweapon.ch/en/categories/aiming-aids-optics-accessories-1743/red-dots-1760/zerotech-precision-1762'
                }
            },
            'Spotting scopes': {
                url: 'https://shop.ravenweapon.ch/en/categories/aiming-aids-optics-accessories-1743/spotting-scopes-1766',
                children: {
                    'ZeroTech Precision Spotting Scopes': 'https://shop.ravenweapon.ch/en/categories/aiming-aids-optics-accessories-1743/spotting-scopes-1766/zerotech-precision-spotting-scopes-1767'
                }
            },
            'Binoculars': {
                url: 'https://shop.ravenweapon.ch/en/categories/aiming-aids-optics-accessories-1743/binoculars-1752',
                children: {
                    'ZeroTech Precision': 'https://shop.ravenweapon.ch/en/categories/aiming-aids-optics-accessories-1743/binoculars-1752/zerotech-precision-1756'
                }
            }
        }
    },
    'Accessories': {
        url: 'https://shop.ravenweapon.ch/en/categories/accessories-1769',
        subcategories: {
            'Magazines': {
                url: 'https://shop.ravenweapon.ch/en/categories/accessories-1769/magazines-1771',
                children: {
                    'Rifle magazines': 'https://shop.ravenweapon.ch/en/categories/accessories-1769/magazines-1771/rifle-magazines-1770',
                    'Pistol magazines': 'https://shop.ravenweapon.ch/en/categories/accessories-1769/magazines-1771/pistol-magazines-1772'
                }
            },
            'Sticks & handles': {
                url: 'https://shop.ravenweapon.ch/en/categories/accessories-1769/sticks-handles-1777',
                children: {
                    'Buttstocks': 'https://shop.ravenweapon.ch/en/categories/accessories-1769/sticks-handles-1777/buttstocks-1776',
                    'Handles': 'https://shop.ravenweapon.ch/en/categories/accessories-1769/sticks-handles-1777/handles-1778'
                }
            },
            'Rails and Accessories': {
                url: 'https://shop.ravenweapon.ch/en/categories/accessories-1769/rails-and-accessories-1779',
                children: {}
            },
            'Bipods': {
                url: 'https://shop.ravenweapon.ch/en/categories/accessories-1769/bipods-1780',
                children: {}
            },
            'Muzzle attachments': {
                url: 'https://shop.ravenweapon.ch/en/categories/accessories-1769/muzzle-attachments-1832',
                children: {
                    'Hexalug': 'https://shop.ravenweapon.ch/en/categories/accessories-1769/muzzle-attachments-1832/hexalug-1830',
                    'Muzzle brake': 'https://shop.ravenweapon.ch/en/categories/accessories-1769/muzzle-attachments-1832/muzzle-brake-1831'
                }
            }
        }
    }
};

function fetchPage(url) {
    return new Promise((resolve, reject) => {
        https.get(url, {
            headers: {
                'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            }
        }, (res) => {
            let data = '';
            res.on('data', chunk => data += chunk);
            res.on('end', () => resolve(data));
        }).on('error', reject);
    });
}

function extractProducts(html, category, subcategory, childCategory = '') {
    const products = [];

    // Match product cards - look for product links and prices
    // Pattern for product name and URL
    const productPattern = /href="(https:\/\/shop\.ravenweapon\.ch\/en\/products\/[^"]+)"[^>]*>[\s\S]*?<h6[^>]*>([^<]+)<\/h6>[\s\S]*?<h4[^>]*>([^<]+)<\/h4>/g;

    let match;
    while ((match = productPattern.exec(html)) !== null) {
        const url = match[1];
        const name = match[2].trim();
        const price = match[3].trim();

        products.push({
            name,
            price,
            url,
            category,
            subcategory,
            childCategory: childCategory || ''
        });
    }

    // Alternative pattern - simpler approach
    if (products.length === 0) {
        // Try to find product-name class
        const namePattern = /class="product-name"[^>]*>[\s\S]*?<a[^>]*href="([^"]+)"[^>]*>([^<]+)<\/a>/g;
        const pricePattern = /<h4[^>]*class="[^"]*price[^"]*"[^>]*>([^<]+)<\/h4>/g;

        const names = [];
        const prices = [];

        while ((match = namePattern.exec(html)) !== null) {
            names.push({ url: match[1], name: match[2].trim() });
        }
        while ((match = pricePattern.exec(html)) !== null) {
            prices.push(match[1].trim());
        }

        for (let i = 0; i < names.length; i++) {
            products.push({
                name: names[i].name,
                price: prices[i] || 'N/A',
                url: names[i].url,
                category,
                subcategory,
                childCategory: childCategory || ''
            });
        }
    }

    return products;
}

function hasNextPage(html) {
    return html.includes('Next »') && html.includes('page=');
}

function getNextPageUrl(currentUrl, html) {
    const match = html.match(/href="([^"]+\?page=\d+)"[^>]*>Next/);
    if (match) {
        return match[1].startsWith('http') ? match[1] : `https://shop.ravenweapon.ch${match[1]}`;
    }

    // Try incrementing page number
    if (currentUrl.includes('?page=')) {
        const pageNum = parseInt(currentUrl.match(/page=(\d+)/)[1]);
        return currentUrl.replace(/page=\d+/, `page=${pageNum + 1}`);
    }
    return currentUrl + '?page=2';
}

async function scrapeCategory(url, category, subcategory, childCategory = '') {
    const allProducts = [];
    let currentUrl = url;
    let pageNum = 1;
    let hasMore = true;

    while (hasMore && pageNum <= 10) { // Max 10 pages per category
        console.log(`    Page ${pageNum}: ${currentUrl}`);

        try {
            const html = await fetchPage(currentUrl);
            const products = extractProducts(html, category, subcategory, childCategory);

            if (products.length === 0) {
                console.log(`      No products found on page ${pageNum}`);
                hasMore = false;
            } else {
                console.log(`      Found ${products.length} products`);
                allProducts.push(...products);

                if (hasNextPage(html)) {
                    currentUrl = getNextPageUrl(currentUrl, html);
                    pageNum++;
                    await new Promise(r => setTimeout(r, 500)); // Rate limiting
                } else {
                    hasMore = false;
                }
            }
        } catch (err) {
            console.log(`      Error: ${err.message}`);
            hasMore = false;
        }
    }

    return allProducts;
}

async function main() {
    console.log('=== Old Shop Scraper v2 ===\n');

    const allProducts = [];
    const categoryData = {};

    for (const [mainCat, mainData] of Object.entries(CATEGORIES)) {
        console.log(`\n=== ${mainCat} ===`);
        categoryData[mainCat] = { products: [] };

        // Scrape main category page
        console.log(`  Main category: ${mainData.url}`);
        const mainProducts = await scrapeCategory(mainData.url, mainCat, 'All', '');
        categoryData[mainCat].products.push(...mainProducts);
        allProducts.push(...mainProducts);

        // Scrape subcategories
        for (const [subCat, subData] of Object.entries(mainData.subcategories || {})) {
            console.log(`\n  Subcategory: ${subCat}`);
            const subProducts = await scrapeCategory(subData.url, mainCat, subCat, '');
            categoryData[mainCat].products.push(...subProducts);
            allProducts.push(...subProducts);

            // Scrape child categories
            for (const [childCat, childUrl] of Object.entries(subData.children || {})) {
                console.log(`\n    Child: ${childCat}`);
                const childProducts = await scrapeCategory(childUrl, mainCat, subCat, childCat);
                categoryData[mainCat].products.push(...childProducts);
                allProducts.push(...childProducts);
            }
        }
    }

    // Remove duplicates based on URL
    const uniqueProducts = [];
    const seenUrls = new Set();
    for (const product of allProducts) {
        if (!seenUrls.has(product.url)) {
            seenUrls.add(product.url);
            uniqueProducts.push(product);
        }
    }

    // Save results
    console.log('\n\n=== Saving Results ===');

    fs.writeFileSync(
        path.join(OUTPUT_DIR, 'all-products.json'),
        JSON.stringify(uniqueProducts, null, 2),
        'utf-8'
    );
    console.log(`Saved ${uniqueProducts.length} unique products to all-products.json`);

    fs.writeFileSync(
        path.join(OUTPUT_DIR, 'category-structure.json'),
        JSON.stringify(CATEGORIES, null, 2),
        'utf-8'
    );
    console.log('Saved category structure');

    // Summary
    console.log('\n=== Summary ===');
    console.log(`Total unique products: ${uniqueProducts.length}`);

    // Group by category
    const byCategory = {};
    for (const p of uniqueProducts) {
        const key = `${p.category} > ${p.subcategory}`;
        byCategory[key] = (byCategory[key] || 0) + 1;
    }

    console.log('\nProducts by category:');
    for (const [cat, count] of Object.entries(byCategory)) {
        console.log(`  ${cat}: ${count}`);
    }

    console.log('\n=== Done ===');
}

main().catch(console.error);
