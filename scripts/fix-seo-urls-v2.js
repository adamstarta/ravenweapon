/**
 * Fix SEO URLs for Categories (v2)
 *
 * Problem: Category SEO URLs are missing parent path segments
 * Solution: Find full-path URLs by pattern matching and set as canonical
 *
 * This version handles categories where the slug doesn't match the German name
 * (e.g., "bags-backpacks" instead of "taschen-rucksaecke")
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

// Get the expected URL prefix based on breadcrumb level 2 category
function getExpectedPrefix(breadcrumb) {
    if (!breadcrumb || breadcrumb.length < 3) return null;

    // The level 2 category (index 1, after root) determines the prefix
    const level2Name = breadcrumb[1];

    // Map level 2 category names to their SEO slugs
    const prefixMap = {
        'Ausrüstung': 'ausruestung',
        'Munition': 'munition',
        'Zubehör': 'zubehoer',
        'Dienstleistungen': 'dienstleistungen',
        'Raven Caliber Kit': 'raven-caliber-kit',
        'Waffen': 'waffen',
        'Zielhilfen, Optik & Zubehör': 'waffenzubehoer',
        'Alle Produkte': 'alle-produkte'
    };

    return prefixMap[level2Name] || null;
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
    console.log('  SEO URL FIX v2 - Pattern matching for full-path URLs');
    console.log('='.repeat(80) + '\n');

    await getToken();
    console.log('API token obtained.\n');

    console.log('Fetching all categories...');
    const categories = await getAllCategories();
    console.log(`Found ${categories.length} categories.\n`);

    let fixed = 0;
    let skipped = 0;
    let alreadyCorrect = 0;
    let errors = 0;

    for (const cat of categories) {
        // Skip root and level 2 categories (they don't need parent prefix)
        if (cat.level <= 2) continue;

        const seoUrls = cat.seoUrls || [];
        if (seoUrls.length === 0) continue;

        // Get expected prefix from breadcrumb
        const expectedPrefix = getExpectedPrefix(cat.breadcrumb);
        if (!expectedPrefix) continue;

        // Find current canonical
        const currentCanonical = seoUrls.find(u => u.isCanonical);
        if (!currentCanonical) continue;

        // Check if canonical already starts with expected prefix
        if (currentCanonical.seoPathInfo.startsWith(expectedPrefix + '/')) {
            alreadyCorrect++;
            continue;
        }

        // Find a URL that starts with the expected prefix
        const correctUrl = seoUrls.find(u =>
            u.seoPathInfo.startsWith(expectedPrefix + '/') &&
            u.id !== currentCanonical.id
        );

        console.log(`\nCategory: ${cat.name} (Level ${cat.level})`);
        console.log(`  Breadcrumb: ${cat.breadcrumb.slice(1).join(' > ')}`);
        console.log(`  Expected prefix: /${expectedPrefix}/...`);
        console.log(`  Current canonical: /${currentCanonical.seoPathInfo}`);

        if (correctUrl) {
            console.log(`  Found correct URL: /${correctUrl.seoPathInfo}`);

            // Unset old canonical
            const unsetResult = await updateSeoUrlCanonical(currentCanonical.id, false);
            if (!unsetResult) {
                console.log(`  ERROR: Failed to unset old canonical`);
                errors++;
                continue;
            }

            // Set new canonical
            const setResult = await updateSeoUrlCanonical(correctUrl.id, true);
            if (setResult) {
                console.log(`  SUCCESS: Updated canonical`);
                fixed++;
            } else {
                console.log(`  ERROR: Failed to set new canonical`);
                errors++;
            }
        } else {
            console.log(`  WARNING: No URL with prefix /${expectedPrefix}/ found`);
            skipped++;
        }
    }

    console.log('\n' + '='.repeat(80));
    console.log('  SUMMARY');
    console.log('='.repeat(80));
    console.log(`  Already correct: ${alreadyCorrect}`);
    console.log(`  Fixed: ${fixed}`);
    console.log(`  Skipped (no correct URL): ${skipped}`);
    console.log(`  Errors: ${errors}`);
    console.log('='.repeat(80) + '\n');

    if (skipped > 0) {
        console.log('NOTE: Skipped categories need SEO URLs regenerated.');
        console.log('      1. Run "Indizes aktualisieren" in Shopware Admin');
        console.log('      2. Run this script again\n');
    }
}

main().catch(err => {
    console.error('Error:', err.message);
    process.exit(1);
});
