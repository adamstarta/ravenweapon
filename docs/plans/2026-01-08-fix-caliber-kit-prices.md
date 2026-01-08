# Fix Caliber Kit Prices Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Update all 5 caliber kit prices to be exactly 900 CHF less than their corresponding complete rifle.

**Architecture:** Use Shopware Admin API to PATCH product prices directly. Verify changes via API read-back.

**Tech Stack:** Node.js, Shopware Admin API (OAuth2)

---

## Price Updates Required

| Product Number | Product Name | Current Price | New Price |
|----------------|--------------|---------------|-----------|
| KIT-22LR | .22LR CALIBER KIT | 1,716.67 | **2,950.00** |
| KIT-223 | .223 CALIBER KIT | 1,716.67 | **2,950.00** |
| KIT-300AAC | 300 AAC CALIBER KIT | 1,809.26 | **3,050.00** |
| KIT-762 | 7.62x39 CALIBER KIT | 1,809.26 | **3,050.00** |
| KIT-9MM | 9mm CALIBER KIT | 1,564.35 | **2,440.00** |

---

### Task 1: Create Price Update Script

**Files:**
- Create: `scripts/update-caliber-kit-prices.js`

**Step 1: Write the update script**

```javascript
/**
 * Update Caliber Kit Prices
 * Rule: Kit Price = Complete Rifle Price - 900 CHF
 */

const https = require('https');

const CONFIG = {
    shopware: {
        apiUrl: 'https://ravenweapon.ch/api',
        clientId: 'SWIAC3HJVHFJMHQYRWRUM1E1SG',
        clientSecret: 'RGtsN1Z2TklqU1ZZSVFTOFB6bWZXNWZNNk40V2h4RWY5Q2tPblg'
    }
};

// New prices (Complete Rifle - 900)
const PRICE_UPDATES = [
    { productNumber: 'KIT-22LR', newPrice: 2950.00, name: '.22LR CALIBER KIT' },
    { productNumber: 'KIT-223', newPrice: 2950.00, name: '.223 CALIBER KIT' },
    { productNumber: 'KIT-300AAC', newPrice: 3050.00, name: '300 AAC CALIBER KIT' },
    { productNumber: 'KIT-762', newPrice: 3050.00, name: '7.62x39 CALIBER KIT' },
    { productNumber: 'KIT-9MM', newPrice: 2440.00, name: '9mm CALIBER KIT' }
];

function httpRequest(url, options = {}) {
    return new Promise((resolve, reject) => {
        const urlObj = new URL(url);
        const reqOptions = {
            hostname: urlObj.hostname,
            port: 443,
            path: urlObj.pathname + urlObj.search,
            method: options.method || 'GET',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                ...options.headers
            }
        };
        const req = https.request(reqOptions, (res) => {
            let data = '';
            res.on('data', chunk => data += chunk);
            res.on('end', () => {
                try {
                    resolve({ status: res.statusCode, data: data ? JSON.parse(data) : {} });
                } catch (e) {
                    resolve({ status: res.statusCode, data: data });
                }
            });
        });
        req.on('error', reject);
        if (options.body) req.write(JSON.stringify(options.body));
        req.end();
    });
}

let accessToken = null;

async function getToken() {
    const res = await httpRequest(`${CONFIG.shopware.apiUrl}/oauth/token`, {
        method: 'POST',
        body: {
            grant_type: 'client_credentials',
            client_id: CONFIG.shopware.clientId,
            client_secret: CONFIG.shopware.clientSecret
        }
    });
    if (!res.data.access_token) throw new Error('Failed to get token');
    accessToken = res.data.access_token;
    return accessToken;
}

async function getProductByNumber(productNumber) {
    const res = await httpRequest(`${CONFIG.shopware.apiUrl}/search/product`, {
        method: 'POST',
        headers: { 'Authorization': `Bearer ${accessToken}` },
        body: {
            limit: 1,
            filter: [{ type: 'equals', field: 'productNumber', value: productNumber }],
            includes: { product: ['id', 'productNumber', 'name', 'price'] }
        }
    });
    return res.data.data?.[0] || null;
}

async function updateProductPrice(productId, newGrossPrice) {
    // Shopware 6 uses net price internally, assuming 7.7% Swiss VAT
    const vatRate = 0.077;
    const netPrice = newGrossPrice / (1 + vatRate);

    const res = await httpRequest(`${CONFIG.shopware.apiUrl}/product/${productId}`, {
        method: 'PATCH',
        headers: { 'Authorization': `Bearer ${accessToken}` },
        body: {
            price: [{
                currencyId: 'b7d2554b0ce847cd82f3ac9bd1c0dfca', // CHF currency ID
                gross: newGrossPrice,
                net: netPrice,
                linked: true
            }]
        }
    });
    return res.status === 204 || res.status === 200;
}

async function main() {
    console.log('\n' + '='.repeat(70));
    console.log('  CALIBER KIT PRICE UPDATE');
    console.log('  Rule: Kit = Complete Rifle - 900 CHF');
    console.log('='.repeat(70) + '\n');

    await getToken();
    console.log('API token obtained.\n');

    const results = [];

    for (const update of PRICE_UPDATES) {
        console.log(`Processing: ${update.productNumber} (${update.name})`);

        // Get current product
        const product = await getProductByNumber(update.productNumber);
        if (!product) {
            console.log(`  ERROR: Product not found!\n`);
            results.push({ ...update, status: 'NOT FOUND', oldPrice: null });
            continue;
        }

        const oldPrice = product.price?.[0]?.gross || 0;
        console.log(`  Current price: CHF ${oldPrice.toFixed(2)}`);
        console.log(`  New price:     CHF ${update.newPrice.toFixed(2)}`);

        // Update price
        const success = await updateProductPrice(product.id, update.newPrice);

        if (success) {
            console.log(`  Status: UPDATED\n`);
            results.push({ ...update, status: 'UPDATED', oldPrice });
        } else {
            console.log(`  Status: FAILED\n`);
            results.push({ ...update, status: 'FAILED', oldPrice });
        }
    }

    // Summary
    console.log('='.repeat(70));
    console.log('  SUMMARY');
    console.log('='.repeat(70));
    console.log('\nProduct Number'.padEnd(20) + 'Old Price'.padStart(12) + 'New Price'.padStart(12) + '  Status');
    console.log('-'.repeat(70));

    for (const r of results) {
        const old = r.oldPrice !== null ? `CHF ${r.oldPrice.toFixed(2)}` : 'N/A';
        console.log(
            r.productNumber.padEnd(20) +
            old.padStart(12) +
            `CHF ${r.newPrice.toFixed(2)}`.padStart(12) +
            `  ${r.status}`
        );
    }

    const updated = results.filter(r => r.status === 'UPDATED').length;
    const failed = results.filter(r => r.status !== 'UPDATED').length;

    console.log('\n' + '='.repeat(70));
    console.log(`  Updated: ${updated} | Failed: ${failed}`);
    console.log('='.repeat(70) + '\n');
}

main().catch(err => {
    console.error('Error:', err.message);
    process.exit(1);
});
```

---

### Task 2: Run the Price Update Script

**Step 1: Execute the script**

Run:
```bash
cd scripts && node update-caliber-kit-prices.js
```

Expected output:
- All 5 products show "UPDATED" status
- Old prices match our analysis
- New prices are correct (2950, 2950, 3050, 3050, 2440)

---

### Task 3: Verify Prices Were Updated

**Step 1: Run verification script**

Run:
```bash
cd scripts && node check-caliber-prices.js
```

Expected: All caliber kits now show correct prices:
- KIT-22LR: 2,950.00
- KIT-223: 2,950.00
- KIT-300AAC: 3,050.00
- KIT-762: 3,050.00
- KIT-9MM: 2,440.00

**Step 2: Verify on live site**

Check https://ravenweapon.ch and confirm the caliber kit prices display correctly.

---

### Task 4: Commit the Update Script (Optional)

**Step 1: Commit**

```bash
git add scripts/update-caliber-kit-prices.js scripts/check-caliber-prices.js
git commit -m "feat: add caliber kit price management scripts"
```

---

## Rollback Plan

If prices need to be reverted, modify `PRICE_UPDATES` in the script with original values:

```javascript
const PRICE_UPDATES = [
    { productNumber: 'KIT-22LR', newPrice: 1716.67, name: '.22LR CALIBER KIT' },
    { productNumber: 'KIT-223', newPrice: 1716.67, name: '.223 CALIBER KIT' },
    { productNumber: 'KIT-300AAC', newPrice: 1809.26, name: '300 AAC CALIBER KIT' },
    { productNumber: 'KIT-762', newPrice: 1809.26, name: '7.62x39 CALIBER KIT' },
    { productNumber: 'KIT-9MM', newPrice: 1564.35, name: '9mm CALIBER KIT' }
];
```

Then run the script again.
