/**
 * Product Comparison Script
 * Compares Makaris wholesale products with Raven Weapon staging database
 *
 * Usage: node compare-products.js
 */

const fs = require('fs');

// Load Makaris products
function loadMakarisProducts() {
    const data = fs.readFileSync('./makaris-products.json', 'utf8');
    return JSON.parse(data).products;
}

// Load Staging products
function loadStagingProducts() {
    const data = fs.readFileSync('./staging-products.json', 'utf8');
    return JSON.parse(data).products;
}

// Parse Swiss price format (1'234.56 -> 1234.56)
function parsePrice(priceStr) {
    if (!priceStr) return 0;
    if (typeof priceStr === 'number') return priceStr;
    return parseFloat(priceStr.replace(/'/g, '').replace(',', '.'));
}

// Format price for display
function formatPrice(price) {
    return `CHF ${price.toFixed(2)}`;
}

// Compare products
function compareProducts() {
    console.log('='.repeat(70));
    console.log('MAKARIS vs STAGING PRODUCT COMPARISON');
    console.log('='.repeat(70));
    console.log('');

    // Load products
    const makarisProducts = loadMakarisProducts();
    const stagingProducts = loadStagingProducts();

    console.log(`Makaris Products: ${makarisProducts.length}`);
    console.log(`Staging Products: ${stagingProducts.length}`);
    console.log('');

    // Create lookup map for staging products by article number
    const stagingMap = new Map();
    stagingProducts.forEach(p => {
        stagingMap.set(p.articleNumber, p);
    });

    // Compare
    const missing = [];
    const priceDifferences = [];
    const matched = [];

    makarisProducts.forEach(mp => {
        const sp = stagingMap.get(mp.articleNumber);

        if (!sp) {
            missing.push(mp);
        } else {
            const makarisGross = parsePrice(mp.grossPrice);
            const stagingPrice = sp.grossPrice;

            if (Math.abs(makarisGross - stagingPrice) > 0.01) {
                priceDifferences.push({
                    articleNumber: mp.articleNumber,
                    productName: mp.productName,
                    makarisPrice: makarisGross,
                    stagingPrice: stagingPrice,
                    difference: stagingPrice - makarisGross
                });
            } else {
                matched.push({
                    articleNumber: mp.articleNumber,
                    productName: mp.productName,
                    price: makarisGross
                });
            }
        }
    });

    // Report MATCHED products
    console.log('='.repeat(70));
    console.log(`MATCHED PRODUCTS (${matched.length}):`);
    console.log('='.repeat(70));
    matched.forEach(p => {
        console.log(`  ✓ ${p.articleNumber} - ${formatPrice(p.price)}`);
    });
    console.log('');

    // Report PRICE DIFFERENCES
    if (priceDifferences.length > 0) {
        console.log('='.repeat(70));
        console.log(`PRICE DIFFERENCES (${priceDifferences.length}):`);
        console.log('='.repeat(70));
        priceDifferences.forEach(p => {
            const sign = p.difference > 0 ? '+' : '';
            console.log(`  ⚠ ${p.articleNumber}`);
            console.log(`    ${p.productName}`);
            console.log(`    Makaris: ${formatPrice(p.makarisPrice)} | Staging: ${formatPrice(p.stagingPrice)} | Diff: ${sign}${formatPrice(p.difference)}`);
        });
        console.log('');
    }

    // Report MISSING products grouped by category
    if (missing.length > 0) {
        console.log('='.repeat(70));
        console.log(`MISSING FROM STAGING (${missing.length}):`);
        console.log('='.repeat(70));

        // Group by category prefix
        const categories = {};
        missing.forEach(p => {
            const prefix = p.articleNumber.split('-')[0];
            if (!categories[prefix]) categories[prefix] = [];
            categories[prefix].push(p);
        });

        const categoryNames = {
            'ZRT': 'Zero Tech Optics',
            'MGP': 'Magpul Accessories',
            'FCH': 'Fiocchi Ammunition',
            'DEX': 'Dexterity Pistols',
            'AIM': 'Aimpact Mounts',
            'ACH': 'Acheron Accessories',
            'Basic': 'Basic Instruktor Products',
            'Instruktor': 'Instruktor Products'
        };

        for (const [prefix, products] of Object.entries(categories).sort()) {
            console.log(`\n  [${prefix}] ${categoryNames[prefix] || 'Other'} - ${products.length} products missing`);
            products.forEach(p => {
                console.log(`    ✗ ${p.articleNumber}`);
                console.log(`      ${p.productName}`);
                console.log(`      Makaris Price: CHF ${p.grossPrice}`);
            });
        }
        console.log('');
    }

    // Final Summary
    console.log('');
    console.log('='.repeat(70));
    console.log('SUMMARY');
    console.log('='.repeat(70));
    console.log(`  Total Makaris Products:    ${makarisProducts.length}`);
    console.log(`  Total Staging Products:    ${stagingProducts.length}`);
    console.log('');
    console.log(`  ✓ Matched (same price):    ${matched.length}`);
    console.log(`  ⚠ Price Differences:       ${priceDifferences.length}`);
    console.log(`  ✗ Missing from Staging:    ${missing.length}`);
    console.log('');

    // Price difference summary
    if (priceDifferences.length > 0) {
        const totalDiff = priceDifferences.reduce((sum, p) => sum + p.difference, 0);
        console.log(`  Total Price Variance:      ${totalDiff > 0 ? '+' : ''}CHF ${totalDiff.toFixed(2)}`);
        console.log('');
    }

    // Category breakdown for missing
    if (missing.length > 0) {
        console.log('  Missing by Category:');
        const catCounts = {};
        missing.forEach(p => {
            const prefix = p.articleNumber.split('-')[0];
            catCounts[prefix] = (catCounts[prefix] || 0) + 1;
        });
        for (const [prefix, count] of Object.entries(catCounts).sort()) {
            console.log(`    ${prefix}: ${count} products`);
        }
    }
}

// Run comparison
compareProducts();
