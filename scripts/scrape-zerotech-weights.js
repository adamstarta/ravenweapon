/**
 * Scrape ZeroTech website for product weights
 * Maps to Shopware product numbers
 */

const https = require('https');
const fs = require('fs');

// Product mapping: Shopware product number -> ZeroTech URL path
const PRODUCT_URLS = {
    // VENGEANCE scopes
    'ZRT-ZF-VEN-000-XX-00102': 'https://zerotechoptics.com/p/1-6x24mm-vengeance-sfp-rar-moa-riflescope/',
    'ZRT-ZF-VEN-000-XX-00077': 'https://zerotechoptics.com/p/3-12x40-zeroplex-riflescope/', // 3-12x40 Zeroplex
    'ZRT-ZF-VEN-000-XX-00076': 'https://zerotechoptics.com/p/6-24x50-ffp-rmg-moa-riflescope/', // 6-24x50 FFP RMG MOA
    'ZRT-ZF-VEN-000-XX-00075': 'https://zerotechoptics.com/p/6-24x50-ffp-rmg-riflescope/', // 6-24x50 FFP RMG MRAD
    'ZRT-ZF-VEN-000-XX-00074': 'https://zerotechoptics.com/p/4-20x50-phr-ii-riflescope/', // 4-20x50 PHR II
    'ZRT-ZF-VEN-000-XX-00073': 'https://zerotechoptics.com/p/4-20x50-r3-riflescope/', // 4-20x50 R3
    'ZRT-ZF-VEN-000-XX-00072': 'https://zerotechoptics.com/p/4-20x50-phr-ii-ir-riflescope/', // 4-20x50 PHR II IR
    'ZRT-ZF-VEN-000-XX-00071': 'https://zerotechoptics.com/p/4-20x50-r3-illuminated-riflescope/', // 4-20x50 R3 Illum
    'ZRT-ZF-VEN-000-XX-00066': 'https://zerotechoptics.com/p/5-25x56-rmg-ffp-mrad-zero-stop-riflescope/', // 5-25x56

    // THRIVE scopes
    'ZRT-ZF-THR-000-XX-00107': 'https://zerotechoptics.com/p/4-16x50mm-zeroplex-thrive-riflescopes/', // 4-16x50 Zeroplex
    'ZRT-ZF-THR-000-XX-00106': 'https://zerotechoptics.com/p/4-16x50mm-phr-ii-thrive-riflescopes/', // 4-16x50 PHR II
    'ZRT-ZF-THR-000-XX-00105': 'https://zerotechoptics.com/p/3-12x44mm-zeroplex-thrive-riflescope/', // 3-12x44 Zeroplex
    'ZRT-ZF-THR-000-XX-00104': 'https://zerotechoptics.com/p/3-9x40mm-phr-4-thrive-riflescope/', // 3-9x40 PHR4
    'ZRT-ZF-THR-000-XX-00103': 'https://zerotechoptics.com/p/3-9x40mm-zeroplex-thrive-riflescope/', // 3-9x40 Zeroplex
    'ZRT-ZF-THR-000-XX-00108': 'https://zerotechoptics.com/p/4-16x50mm-mildot-thrive-riflescope/', // 4-16x50 Mildot

    // THRIVE HD scopes
    'ZRT-ZF-THD-000-XX-00117': 'https://zerotechoptics.com/p/2-5-15x50mm-phr-ii-illuminated-thrive-hd-riflescope/', // 2.5-15x50 PHR II IR
    'ZRT-ZF-THD-000-XX-00116': 'https://zerotechoptics.com/p/2-5-15x50mm-phr-ii-thrive-hd-riflescope/', // 2.5-15x50 PHR II
    'ZRT-ZF-THD-000-XX-00115': 'https://zerotechoptics.com/p/3-18x56mm-phr-ii-ir-thrive-hd-riflescope/', // 3-18x56 PHR II IR
    'ZRT-ZF-THD-000-XX-00114': 'https://zerotechoptics.com/p/6-24x50mm-ir-lr-hunter-mrad-thrive-hd-riflescope/', // 6-24x50 FFP
    'ZRT-ZF-THD-000-XX-00113': 'https://zerotechoptics.com/p/1-8x24mm-g4-ir-thrive-hd-riflescope/', // 1-8x24 G4 IR
    'ZRT-ZF-THD-000-XX-00112': 'https://zerotechoptics.com/p/1-8x24mm-phr-4-ir-thrive-hd-riflescope/', // 1-8x24 PHR4 IR

    // TRACE scopes
    'ZRT-ZF-TRD-000-XX-00118': 'https://zerotechoptics.com/p/1-10x24-lpvo-rmg-l-illuminated-trace-ed-riflescope/', // 1-10x24 ED
    'ZRT-ZF-TRC-000-XX-00111': 'https://zerotechoptics.com/p/4-5-27x50mm-r3-trace-riflescope/', // 4.5-27x50 R3 MOA
    'ZRT-ZF-TRC-000-XX-00110': 'https://zerotechoptics.com/p/4-5-27x50mm-rmg-trace-riflescope/', // 4.5-27x50 RMG FFP
    'ZRT-ZF-TRC-000-XX-00109': 'https://zerotechoptics.com/p/4-5-27x50mm-rmg-moa-trace-riflescope/', // 4.5-27x50 RMG MOA
    'ZRT-ZF-TRA-000-XX-00081': 'https://zerotechoptics.com/p/4-24x50mm-ffp-rmg-ir-trace-advanced-riflescope/', // Trace Advanced
    'ZRT-ZF-TRA-000-XX-00080': 'https://zerotechoptics.com/p/4-24x50mm-ffp-tremor-3-trace-advanced-riflescope/', // Trace Advanced
    'ZRT-ZF-TRA-000-XX-00079': 'https://zerotechoptics.com/p/5-30x56mm-rmg2-ed-trace-advanced-riflescope/', // Trace Advanced
    'ZRT-ZF-TRA-000-XX-00078': 'https://zerotechoptics.com/p/5-30x56mm-t3-ed-trace-advanced-riflescope/', // Trace Advanced

    // Spotting scopes
    'ZRT-SSC-TRA-000-XX-00094': 'https://zerotechoptics.com/p/20-60x80mm-ffp-osr-mrad-trace-spotting-scope/', // 20-60x80
    'ZRT-SSC-THR-000-XX-00095': 'https://zerotechoptics.com/p/20-60x85mm-ting-thrive-spotting-scope/', // 20-60x85

    // Red dots
    'ZRT-RV-TRC-000-XX-00093': 'https://zerotechoptics.com/p/ras-1x25mm-trace-digital-red-dot/', // RAS Digital
    'ZRT-RV-TRC-000-XX-00091': 'https://zerotechoptics.com/p/trace-halo-aspherical-enclosed-red-dot/', // Trace Halo
    'ZRT-RV-TRC-000-FD-00092': 'https://zerotechoptics.com/p/trace-halo-aspherical-enclosed-red-dot-fde/', // Trace Halo FDE
    'ZRT-RV-THR-000-XX-00101': 'https://zerotechoptics.com/p/thrive-red-dot-3-moa/', // Thrive Red Dot
    'ZRT-RV-THD-000-XX-00100': 'https://zerotechoptics.com/p/thrive-hd-reflex-3-moa-high/', // Thrive HD Reflex
    'ZRT-RV-THD-000-XX-00099': 'https://zerotechoptics.com/p/thrive-hd-reflex-green-dot-3-moa/', // Thrive HD Reflex Green
    'ZRT-RV-THD-000-XX-00098': 'https://zerotechoptics.com/p/thrive-hd-multi-reticle-high-reflex/', // Thrive HD Multi
    'ZRT-RV-THD-000-XX-00097': 'https://zerotechoptics.com/p/micro-prism-1x20-thrive-hd/', // Micro Prism
    'ZRT-RV-THD-000-XX-00096': 'https://zerotechoptics.com/p/1-4x24mm-variable-prism-thrive-hd/', // Variable Prism
};

function delay(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

function fetchPage(url) {
    return new Promise((resolve, reject) => {
        const urlObj = new URL(url);
        const options = {
            hostname: urlObj.hostname,
            path: urlObj.pathname,
            method: 'GET',
            headers: {
                'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'Accept': 'text/html,application/xhtml+xml'
            }
        };

        const req = https.request(options, (res) => {
            let data = '';
            res.on('data', chunk => data += chunk);
            res.on('end', () => resolve(data));
        });
        req.on('error', reject);
        req.end();
    });
}

function extractWeight(html) {
    // Look for weight in the specifications table
    // Pattern: <td>Weight</td><td>XXXg</td> or similar
    const patterns = [
        /Weight<\/(?:td|cell)>\s*<(?:td|cell)[^>]*>([^<]+)/i,
        /"Weight"[^>]*>([^<]+)/i,
        /Weight[:\s]+(\d+(?:\.\d+)?)\s*(?:g|oz|grams?|ounces?)/i,
        /<td[^>]*>Weight<\/td>\s*<td[^>]*>([^<]+)<\/td>/i,
    ];

    for (const pattern of patterns) {
        const match = html.match(pattern);
        if (match) {
            let weight = match[1].trim();
            // Convert oz to grams if needed
            if (weight.toLowerCase().includes('oz')) {
                const oz = parseFloat(weight);
                if (!isNaN(oz)) {
                    weight = Math.round(oz * 28.35) + 'g';
                }
            }
            return weight;
        }
    }
    return null;
}

async function main() {
    console.log('='.repeat(80));
    console.log('  SCRAPING ZEROTECH WEBSITE FOR PRODUCT WEIGHTS');
    console.log('='.repeat(80));
    console.log(`\nTotal products to scrape: ${Object.keys(PRODUCT_URLS).length}\n`);

    const results = [];
    const failed = [];
    let count = 0;

    for (const [productNumber, url] of Object.entries(PRODUCT_URLS)) {
        count++;
        try {
            console.log(`[${count}/${Object.keys(PRODUCT_URLS).length}] Fetching ${productNumber}...`);
            const html = await fetchPage(url);
            const weight = extractWeight(html);

            if (weight) {
                console.log(`    ✓ Weight: ${weight}`);
                results.push({
                    productNumber,
                    url,
                    weight,
                    weightGrams: parseInt(weight) || weight
                });
            } else {
                console.log(`    ✗ Weight not found`);
                failed.push({ productNumber, url, reason: 'Weight not found in page' });
            }
        } catch (err) {
            console.log(`    ✗ Error: ${err.message}`);
            failed.push({ productNumber, url, reason: err.message });
        }

        await delay(500); // Be nice to the server
    }

    // Print results
    console.log('\n' + '='.repeat(80));
    console.log('  RESULTS: WEIGHTS FOUND');
    console.log('='.repeat(80));
    console.log(`\nSuccessfully scraped: ${results.length}`);
    console.log(`Failed: ${failed.length}\n`);

    console.log('Product Number                       Weight     URL');
    console.log('-'.repeat(80));
    for (const r of results) {
        console.log(`${r.productNumber.padEnd(35)} ${String(r.weight).padStart(8)}   ${r.url.substring(0, 40)}`);
    }

    if (failed.length > 0) {
        console.log('\n' + '='.repeat(80));
        console.log('  FAILED PRODUCTS');
        console.log('='.repeat(80));
        for (const f of failed) {
            console.log(`${f.productNumber}: ${f.reason}`);
        }
    }

    // Save to JSON
    const output = {
        scrapedAt: new Date().toISOString(),
        successful: results,
        failed: failed
    };
    fs.writeFileSync('zerotech-weights.json', JSON.stringify(output, null, 2));
    console.log('\n\nSaved to zerotech-weights.json');
}

main().catch(err => {
    console.error('Fatal error:', err.message);
    process.exit(1);
});
