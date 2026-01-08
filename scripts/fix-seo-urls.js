/**
 * Fix SEO URLs for Categories
 *
 * Problem: Category SEO URLs are missing parent path segments
 * Solution: Delete old SEO URLs and regenerate with correct full paths
 */

const https = require('https');

const CONFIG = {
    shopware: {
        apiUrl: 'https://ravenweapon.ch/api',
        clientId: 'SWIAC3HJVHFJMHQYRWRUM1E1SG',
        clientSecret: 'RGtsN1Z2TklqU1ZZSVFTOFB6bWZXNWZNNk40V2h4RWY5Q2tPblg'
    }
};

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

async function getAllCategories() {
    const allCategories = [];
    let page = 1;
    const limit = 100;

    while (true) {
        const res = await httpRequest(`${CONFIG.shopware.apiUrl}/search/category`, {
            method: 'POST',
            headers: { 'Authorization': `Bearer ${accessToken}` },
            body: {
                limit: limit,
                page: page,
                associations: {
                    seoUrls: {}
                }
            }
        });

        if (!res.data.data || res.data.data.length === 0) break;
        allCategories.push(...res.data.data);
        if (res.data.data.length < limit) break;
        page++;
    }

    return allCategories;
}

// Generate correct SEO path from breadcrumb (matching Shopware's format)
function generateSeoPath(breadcrumb) {
    if (!breadcrumb || breadcrumb.length < 2) return null;

    // Skip the root category (first item in breadcrumb)
    const pathParts = breadcrumb.slice(1);

    return pathParts.map(part => {
        return part
            .toLowerCase()
            .replace(/ä/g, 'ae')
            .replace(/ö/g, 'oe')
            .replace(/ü/g, 'ue')
            .replace(/ß/g, 'ss')
            .replace(/&/g, '-')
            .replace(/\s+/g, '-')
            .replace(/\//g, '-')
            .replace(/-+/g, '-')  // Collapse multiple dashes into one
            .replace(/^-|-$/g, ''); // Remove leading/trailing dashes
    }).join('/') + '/';
}

async function deleteSeoUrl(seoUrlId) {
    const res = await httpRequest(`${CONFIG.shopware.apiUrl}/seo-url/${seoUrlId}`, {
        method: 'DELETE',
        headers: { 'Authorization': `Bearer ${accessToken}` }
    });
    return res.status === 204;
}

async function updateSeoUrlCanonical(seoUrlId, isCanonical) {
    const res = await httpRequest(`${CONFIG.shopware.apiUrl}/seo-url/${seoUrlId}`, {
        method: 'PATCH',
        headers: { 'Authorization': `Bearer ${accessToken}` },
        body: {
            isCanonical: isCanonical
        }
    });
    return res.status === 204 || res.status === 200;
}

async function main() {
    console.log('\n' + '='.repeat(80));
    console.log('  SEO URL FIX - Update canonical URLs to use full breadcrumb paths');
    console.log('='.repeat(80) + '\n');

    await getToken();
    console.log('API token obtained.\n');

    console.log('Fetching all categories...');
    const categories = await getAllCategories();
    console.log(`Found ${categories.length} categories.\n`);

    let fixed = 0;
    let skipped = 0;
    let errors = 0;

    for (const cat of categories) {
        // Skip root category (level 1)
        if (cat.level <= 1) continue;

        const seoUrls = cat.seoUrls || [];
        if (seoUrls.length === 0) continue;

        const expectedPath = generateSeoPath(cat.breadcrumb);
        if (!expectedPath) continue;

        // Find current canonical and the correct full-path URL
        const currentCanonical = seoUrls.find(u => u.isCanonical);
        const correctUrl = seoUrls.find(u => u.seoPathInfo === expectedPath);

        // Check if canonical is already correct
        if (currentCanonical && currentCanonical.seoPathInfo === expectedPath) {
            continue; // Already correct
        }

        console.log(`\nCategory: ${cat.name} (Level ${cat.level})`);
        console.log(`  Expected path: /${expectedPath}`);
        console.log(`  Current canonical: /${currentCanonical?.seoPathInfo || 'none'}`);

        // If correct URL exists, make it canonical
        if (correctUrl) {
            console.log(`  Found correct URL, updating canonical flag...`);

            // First, unset the old canonical
            if (currentCanonical && currentCanonical.id !== correctUrl.id) {
                const unsetResult = await updateSeoUrlCanonical(currentCanonical.id, false);
                if (!unsetResult) {
                    console.log(`  ERROR: Failed to unset old canonical`);
                    errors++;
                    continue;
                }
            }

            // Set the correct URL as canonical
            const setResult = await updateSeoUrlCanonical(correctUrl.id, true);
            if (setResult) {
                console.log(`  SUCCESS: Updated canonical to /${expectedPath}`);
                fixed++;
            } else {
                console.log(`  ERROR: Failed to set new canonical`);
                errors++;
            }
        } else {
            console.log(`  WARNING: Correct URL not found, will be created on next index`);
            skipped++;
        }
    }

    console.log('\n' + '='.repeat(80));
    console.log('  SUMMARY');
    console.log('='.repeat(80));
    console.log(`  Fixed: ${fixed}`);
    console.log(`  Skipped (correct URL not found): ${skipped}`);
    console.log(`  Errors: ${errors}`);
    console.log('='.repeat(80) + '\n');

    if (skipped > 0) {
        console.log('NOTE: For skipped categories, run "Indizes aktualisieren" in Shopware Admin');
        console.log('      to generate the correct SEO URLs, then run this script again.\n');
    }
}

main().catch(err => {
    console.error('Error:', err.message);
    process.exit(1);
});
