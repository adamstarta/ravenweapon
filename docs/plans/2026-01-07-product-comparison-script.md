# Product Comparison Script Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Create a script that compares Excel products against Shopware to find price mismatches and missing images.

**Architecture:** Single Node.js script that loads Excel, queries Shopware API for each product, compares `selling_price` vs Shopware price and image counts, outputs discrepancies to console.

**Tech Stack:** Node.js, xlsx library, Shopware Admin API

---

## Task 1: Create the Comparison Script

**Files:**
- Create: `scripts/compare-products.js`

**Step 1: Create script skeleton with config**

```javascript
/**
 * Compare Products: Excel vs Shopware
 *
 * Compares:
 * - selling_price (Excel) vs gross price (Shopware)
 * - image count (Excel image_1 to image_5) vs media count (Shopware)
 *
 * Usage: node compare-products.js
 */

const https = require('https');

const CONFIG = {
    excelFile: String.raw`c:\Users\alama\Downloads\products (2).xlsx`,
    shopware: {
        apiUrl: 'https://ravenweapon.ch/api',
        clientId: 'SWIAC3HJVHFJMHQYRWRUM1E1SG',
        clientSecret: 'RGtsN1Z2TklqU1ZZSVFTOFB6bWZXNWZNNk40V2h4RWY5Q2tPblg'
    },
    requestDelay: 150
};
```

**Step 2: Add HTTP helper and token management**

```javascript
function delay(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

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
                    resolve({ status: res.statusCode, data: JSON.parse(data) });
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
let tokenExpiry = 0;

async function getToken(forceRefresh = false) {
    const now = Date.now();
    if (!accessToken || forceRefresh || now >= tokenExpiry) {
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
        tokenExpiry = now + ((res.data.expires_in || 600) - 60) * 1000;
    }
    return accessToken;
}

async function refreshToken() {
    accessToken = null;
    tokenExpiry = 0;
    return getToken(true);
}
```

**Step 3: Add Shopware product lookup function**

```javascript
async function getShopwareProduct(productNumber, retry = true) {
    const token = await getToken();
    const res = await httpRequest(`${CONFIG.shopware.apiUrl}/search/product`, {
        method: 'POST',
        headers: { 'Authorization': `Bearer ${token}` },
        body: {
            limit: 1,
            filter: [{ type: 'equals', field: 'productNumber', value: productNumber }],
            associations: { media: {} }
        }
    });

    if (res.status === 401 && retry) {
        await refreshToken();
        return getShopwareProduct(productNumber, false);
    }

    if (res.data.data && res.data.data.length > 0) {
        return res.data.data[0];
    }
    return null;
}
```

**Step 4: Add main comparison logic**

```javascript
async function main() {
    console.log('\n' + '='.repeat(70));
    console.log('  PRODUCT COMPARISON: Excel vs Shopware');
    console.log('='.repeat(70));

    // Load Excel
    let XLSX;
    try {
        XLSX = require('xlsx');
    } catch (e) {
        console.log('Installing xlsx...');
        require('child_process').execSync('npm install xlsx', { stdio: 'inherit' });
        XLSX = require('xlsx');
    }

    console.log(`\nLoading: ${CONFIG.excelFile}`);
    const workbook = XLSX.readFile(CONFIG.excelFile);
    const sheet = workbook.Sheets[workbook.SheetNames[0]];
    const products = XLSX.utils.sheet_to_json(sheet);
    console.log(`Total products in Excel: ${products.length}\n`);

    // Results tracking
    const results = {
        total: 0,
        found: 0,
        notFound: [],
        priceMismatch: [],
        imageMismatch: []
    };

    console.log('Getting API token...');
    await getToken();
    console.log('Comparing products...\n');

    for (const product of products) {
        results.total++;
        const productNumber = product.number;
        const productName = product.name_en || product.name_de || 'Unknown';
        const excelPrice = product.selling_price;

        // Count Excel images
        let excelImageCount = 0;
        for (let i = 1; i <= 5; i++) {
            if (product[`image_${i}`]) excelImageCount++;
        }

        // Get Shopware product
        const swProduct = await getShopwareProduct(productNumber);

        if (!swProduct) {
            results.notFound.push({ number: productNumber, name: productName });
            process.stdout.write(`[${results.total}/${products.length}] ${productNumber} - NOT FOUND\n`);
            await delay(CONFIG.requestDelay);
            continue;
        }

        results.found++;

        // Get Shopware price (gross)
        const swPrice = swProduct.price?.[0]?.gross || 0;

        // Get Shopware media count
        const swMediaCount = swProduct.media?.length || 0;

        // Compare price
        const priceDiff = Math.abs(excelPrice - swPrice);
        if (priceDiff > 0.01) {
            results.priceMismatch.push({
                number: productNumber,
                name: productName,
                excelPrice: excelPrice,
                shopwarePrice: swPrice,
                diff: (excelPrice - swPrice).toFixed(2)
            });
        }

        // Compare images
        if (excelImageCount !== swMediaCount) {
            results.imageMismatch.push({
                number: productNumber,
                name: productName,
                excelImages: excelImageCount,
                shopwareImages: swMediaCount,
                missing: excelImageCount - swMediaCount
            });
        }

        // Progress
        const status = [];
        if (priceDiff > 0.01) status.push('PRICE');
        if (excelImageCount !== swMediaCount) status.push('IMG');
        const statusStr = status.length > 0 ? ` [${status.join(',')}]` : ' OK';
        process.stdout.write(`[${results.total}/${products.length}] ${productNumber}${statusStr}\r`);

        await delay(CONFIG.requestDelay);
    }

    // Print results
    console.log('\n\n' + '='.repeat(70));
    console.log('  RESULTS');
    console.log('='.repeat(70));

    console.log(`\nTotal: ${results.total} | Found: ${results.found} | Not Found: ${results.notFound.length}`);

    // Not Found
    if (results.notFound.length > 0) {
        console.log('\n' + '-'.repeat(70));
        console.log('  PRODUCTS NOT IN SHOPWARE');
        console.log('-'.repeat(70));
        results.notFound.forEach(p => {
            console.log(`  ${p.number} - ${p.name}`);
        });
    }

    // Price Mismatches
    if (results.priceMismatch.length > 0) {
        console.log('\n' + '-'.repeat(70));
        console.log('  PRICE MISMATCHES');
        console.log('-'.repeat(70));
        console.log('  Number                          Excel      Shopware   Diff');
        console.log('  ' + '-'.repeat(65));
        results.priceMismatch.forEach(p => {
            const num = p.number.padEnd(30);
            const excel = `CHF ${p.excelPrice.toFixed(2)}`.padStart(10);
            const sw = `CHF ${p.shopwarePrice.toFixed(2)}`.padStart(10);
            const diff = `${p.diff > 0 ? '+' : ''}${p.diff}`.padStart(8);
            console.log(`  ${num} ${excel} ${sw} ${diff}`);
        });
    }

    // Image Mismatches
    if (results.imageMismatch.length > 0) {
        console.log('\n' + '-'.repeat(70));
        console.log('  IMAGE COUNT MISMATCHES');
        console.log('-'.repeat(70));
        console.log('  Number                          Excel  Shopware  Missing');
        console.log('  ' + '-'.repeat(65));
        results.imageMismatch.forEach(p => {
            const num = p.number.padEnd(30);
            const excel = String(p.excelImages).padStart(5);
            const sw = String(p.shopwareImages).padStart(8);
            const missing = String(p.missing).padStart(8);
            console.log(`  ${num} ${excel} ${sw} ${missing}`);
        });
    }

    // Summary
    console.log('\n' + '='.repeat(70));
    console.log('  SUMMARY');
    console.log('='.repeat(70));
    console.log(`  Products compared:     ${results.total}`);
    console.log(`  Found in Shopware:     ${results.found}`);
    console.log(`  Not found:             ${results.notFound.length}`);
    console.log(`  Price mismatches:      ${results.priceMismatch.length}`);
    console.log(`  Image mismatches:      ${results.imageMismatch.length}`);
    console.log('='.repeat(70) + '\n');
}

main().catch(err => {
    console.error('\nFatal error:', err.message);
    process.exit(1);
});
```

**Step 5: Run the script**

```bash
cd scripts && node compare-products.js
```

**Step 6: Commit**

```bash
git add scripts/compare-products.js
git commit -m "feat: add product comparison script (Excel vs Shopware)"
```

---

## Expected Output Format

```
======================================================================
  PRODUCT COMPARISON: Excel vs Shopware
======================================================================

Loading: c:\Users\alama\Downloads\products (2).xlsx
Total products in Excel: 162

Getting API token...
Comparing products...

[162/162] AIM-ZBH-SCM-XXX-BLK-00150 OK

======================================================================
  RESULTS
======================================================================

Total: 162 | Found: 160 | Not Found: 2

----------------------------------------------------------------------
  PRODUCTS NOT IN SHOPWARE
----------------------------------------------------------------------
  ABC-123 - Product Name

----------------------------------------------------------------------
  PRICE MISMATCHES
----------------------------------------------------------------------
  Number                          Excel      Shopware   Diff
  -----------------------------------------------------------------
  ZRT-ZF-VEN-000-XX-00102        CHF 329.00 CHF 299.00   +30.00

----------------------------------------------------------------------
  IMAGE COUNT MISMATCHES
----------------------------------------------------------------------
  Number                          Excel  Shopware  Missing
  -----------------------------------------------------------------
  ZRT-ZF-VEN-000-XX-00102            5        3        2

======================================================================
  SUMMARY
======================================================================
  Products compared:     162
  Found in Shopware:     160
  Not found:             2
  Price mismatches:      15
  Image mismatches:      23
======================================================================
```
