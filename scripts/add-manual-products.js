/**
 * Add manually scraped products to the stock JSON
 */

const fs = require('fs');
const path = require('path');

const stockFile = path.join(__dirname, 'snigel-stock-data', 'stock-2025-12-23.json');
const stockData = JSON.parse(fs.readFileSync(stockFile, 'utf8'));

function parseStock(stockText) {
    if (!stockText || stockText === 'no info') return { stock: 999, status: 'no_info' };
    const text = stockText.toLowerCase().trim();

    if (text === 'out of stock') {
        return { stock: 0, status: 'out_of_stock' };
    }

    if (text.includes('available on backorder') || text.includes('available on back-order')) {
        return { stock: 0, status: 'backorder_only', canBackorder: true };
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

// 3 manually scraped products
const manualProducts = [
    {
        name: "Oyster pouch 1.0",
        slug: "oyster-pouch-1-0",
        url: "https://products.snigel.se/product/oyster-pouch-1-0/",
        rawVariants: [
            {"colour":"black","sizes":"small","sku":"21-01774A01-001","stock":"245 in stock (can be backordered)"},
            {"colour":"black","sizes":"medium","sku":"21-01774A01-002","stock":"2977 in stock (can be backordered)"},
            {"colour":"grey","sizes":"xsmall","sku":"21-01774A09-000","stock":"234 in stock"},
            {"colour":"grey","sizes":"small","sku":"21-01774A09-001","stock":"993 in stock (can be backordered)"},
            {"colour":"grey","sizes":"medium","sku":"21-01774A09-002","stock":"3194 in stock (can be backordered)"},
            {"colour":"grey","sizes":"large","sku":"21-01774A09-003","stock":"656 in stock (can be backordered)"},
            {"colour":"olive","sizes":"xsmall","sku":"21-01774A17-000","stock":"656 in stock (can be backordered)"},
            {"colour":"olive","sizes":"small","sku":"21-01774A17-001","stock":"656 in stock (can be backordered)"},
            {"colour":"olive","sizes":"medium","sku":"21-01774A17-002","stock":"656 in stock (can be backordered)"},
            {"colour":"olive","sizes":"large","sku":"21-01774A17-003","stock":"656 in stock (can be backordered)"}
        ]
    },
    {
        name: "Covert stretch vest -16",
        slug: "covert-stretch-vest-16",
        url: "https://products.snigel.se/product/covert-stretch-vest-16/",
        rawVariants: [
            {"colour":"black","v-size":"size-15","sku":"19-01098-01-000","stock":"37 in stock (can be backordered)"},
            {"colour":"black","v-size":"size-1","sku":"19-01098-01-001","stock":"32 in stock (can be backordered)"},
            {"colour":"black","v-size":"size-2","sku":"19-01098-01-002","stock":"182 in stock (can be backordered)"},
            {"colour":"black","v-size":"size-3","sku":"19-01098-01-003","stock":"165 in stock (can be backordered)"},
            {"colour":"white","v-size":"size-1","sku":"19-01098-25-001","stock":"29 in stock"},
            {"colour":"white","v-size":"size-2","sku":"19-01098-25-002","stock":"Out of stock"},
            {"colour":"white","v-size":"size-3","sku":"19-01098-25-003","stock":"Out of stock"}
        ]
    },
    {
        name: "Rigid trouser belt -05",
        slug: "rigid-trouser-belt-05",
        url: "https://products.snigel.se/product/rigid-trouser-belt-05/",
        rawVariants: [
            {"colour":"black","sizes":"xs-s","sku":"11-00498-01-0GJ","stock":"117 in stock"},
            {"colour":"black","sizes":"m-l","sku":"11-00498-01-000","stock":"179 in stock (can be backordered)"},
            {"colour":"grey","sizes":"xs-s","sku":"11-00498-09-0GJ","stock":"179 in stock (can be backordered)"},
            {"colour":"grey","sizes":"m-l","sku":"11-00498-09-000","stock":"179 in stock (can be backordered)"},
            {"colour":"olive","sizes":"m-l","sku":"11-00498-17-000","stock":"Out of stock"}
        ]
    }
];

// Process and add each product
for (const product of manualProducts) {
    const variants = product.rawVariants.map(v => {
        const stockInfo = parseStock(v.stock);
        const options = {};
        if (v.colour) options.colour = v.colour;
        if (v.sizes) options.sizes = v.sizes;
        if (v['v-size']) options['v-size'] = v['v-size'];

        return {
            sku: v.sku,
            options,
            ...stockInfo
        };
    });

    stockData.products.push({
        name: product.name,
        slug: product.slug,
        url: product.url,
        variantCount: variants.length,
        variants,
        scrapedAt: new Date().toISOString(),
        manuallyScraped: true
    });

    console.log(`Added: ${product.name} (${variants.length} variants)`);
}

// Update totals
stockData.totalProducts = stockData.products.length;
stockData.totalVariants = stockData.products.reduce((sum, p) => sum + p.variants.length, 0);
stockData.inStock = stockData.products.filter(p => p.variants.some(v => v.status === 'in_stock')).length;
stockData.outOfStock = stockData.products.filter(p => p.variants.every(v => v.status === 'out_of_stock')).length;
stockData.errors = []; // Clear errors since we manually added them

// Save
fs.writeFileSync(stockFile, JSON.stringify(stockData, null, 2));

console.log('\n=== Updated Stock Data ===');
console.log(`Total Products: ${stockData.totalProducts}`);
console.log(`Total Variants: ${stockData.totalVariants}`);
console.log(`In Stock: ${stockData.inStock}`);
console.log(`Out of Stock: ${stockData.outOfStock}`);
console.log('Errors cleared!');
