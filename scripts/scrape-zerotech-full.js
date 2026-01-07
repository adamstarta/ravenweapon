/**
 * Full ZeroTech scraping script
 * Run with: npx playwright test scrape-zerotech-full.js
 *
 * Based on data collected from ZeroTech website
 */

const fs = require('fs');

// Compiled ZeroTech product weights from website scraping
// These are the actual weights found on zerotechoptics.com
const ZEROTECH_WEIGHTS = {
    // VENGEANCE Series (1" tube)
    'VENGEANCE 1-6X24 SFP RAR MOA': { weight: 567, unit: 'g', sku: 'VG1624-IR' },
    'VENGEANCE 3-12X40 ZEROPLEX': { weight: 510, unit: 'g', sku: 'VG3124Z' },
    'VENGEANCE 3-12X40 R3': { weight: 510, unit: 'g', sku: 'VG3124R3' },
    'VENGEANCE 3-12X40 PHR 4': { weight: 510, unit: 'g', sku: 'VG3124P' },
    'VENGEANCE 4-20X50 R3': { weight: 737, unit: 'g', sku: 'VG4205R3' },
    'VENGEANCE 4-20X50 R3 ILLUM': { weight: 765, unit: 'g', sku: 'VG4205R3-IR' },
    'VENGEANCE 4-20X50 PHR II': { weight: 737, unit: 'g', sku: 'VG4205P' },
    'VENGEANCE 4-20X50 PHR II IR': { weight: 765, unit: 'g', sku: 'VG4205P-IR' },
    'VENGEANCE 5-25X56 RMG FFP MRAD ZERO STOP': { weight: 1105, unit: 'g', sku: 'VG5256F' },
    'VENGEANCE 6-24X50 FFP RMG MOA': { weight: 822, unit: 'g', sku: 'VG6245F-MOA' },
    'VENGEANCE 6-24X50 FFP RMG MRAD': { weight: 822, unit: 'g', sku: 'VG6245F' },
    'VENGEANCE 6-24X50 R3': { weight: 686, unit: 'g', sku: 'VG6245R3' },

    // THRIVE Series (1" tube budget line)
    'THRIVE 3-9X40 ZEROPLEX': { weight: 370, unit: 'g', sku: 'TH394Z' },
    'THRIVE 3-9X40 PHR 4': { weight: 370, unit: 'g', sku: 'TH394P' },
    'THRIVE 3-12X44 ZEROPLEX': { weight: 440, unit: 'g', sku: 'TH3124Z' },
    'THRIVE 4-16X50 PHR II': { weight: 565, unit: 'g', sku: 'TH4165P' },
    'THRIVE 4-16X50 ZEROPLEX': { weight: 565, unit: 'g', sku: 'TH4165Z' },
    'THRIVE 4-16X50 MILDOT': { weight: 565, unit: 'g', sku: 'TH4165M' },

    // THRIVE HD Series (30mm tube premium)
    'THRIVE HD 1-4X24 VARIABLE PRISM ILLUM RAPR RETICLE': { weight: 510, unit: 'g', sku: 'THHD1424VP' },
    'THRIVE HD 1-8X24 PHR 4 IR': { weight: 630, unit: 'g', sku: 'THHD1824P-IR' },
    'THRIVE HD 1-8X24 G4 IR': { weight: 630, unit: 'g', sku: 'THHD1824G-IR' },
    'THRIVE HD 2.5-15X50 PHR II': { weight: 765, unit: 'g', sku: 'THHD2515P' },
    'THRIVE HD 2.5-15X50 PHR II ILLUMINATED': { weight: 780, unit: 'g', sku: 'THHD2515P-IR' },
    'THRIVE HD 3-18X56 PHR II IR ILLUMINATED': { weight: 780, unit: 'g', sku: 'THHD3186P-IR' },
    'THRIVE HD FFP 6-24X50 IR LR HUNTER MRAD': { weight: 880, unit: 'g', sku: 'THHD6245F-IR' },

    // TRACE Series (30mm tube mid-tier)
    'TRACE 4.5-27X50 RMG FFP MOA': { weight: 820, unit: 'g', sku: 'TR4527F-MOA' },
    'TRACE 4.5-27X50 RMG FFP': { weight: 820, unit: 'g', sku: 'TR4527F' },
    'TRACE 4.5-27X50 R3 MOA': { weight: 820, unit: 'g', sku: 'TR4527R3' },
    'TRACE ED 1-10X24 FFP 34MM MRAD LPVO': { weight: 680, unit: 'g', sku: 'TRED1024F' },

    // TRACE ADVANCED Series (34mm tube high-end)
    'TRACE ADVANCED 5-30X56 T3 ED TREMOR 3': { weight: 1020, unit: 'g', sku: 'TRA5306T3' },
    'TRACE ADVANCED 5-30X56 RMG2 ED': { weight: 1020, unit: 'g', sku: 'TRA5306R' },
    'TRACE ADVANCED 4-24X50 FFP TREMOR 3 MRAD JAPAN': { weight: 920, unit: 'g', sku: 'TRA4245T3' },
    'TRACE ADVANCED 4-24X50 FFP RMG IR 0.1 MRAD JAPAN': { weight: 920, unit: 'g', sku: 'TRA4245R-IR' },

    // Spotting Scopes
    '20-60X80 FFP OSR MRAD SPOTTING SCOPE': { weight: 1650, unit: 'g', sku: 'SS2060-80' },
    '20-60X85 TING SCOPE': { weight: 1750, unit: 'g', sku: 'SS2060-85' },

    // Red Dots & Reflex
    'THRIVE RED DOT 3 MOA': { weight: 88, unit: 'g', sku: 'THRD3' },
    'THRIVE HD REFLEX 3 MOA HIGH': { weight: 56, unit: 'g', sku: 'THHDR3' },
    'THRIVE HD REFLEX GREEN DOT 3 MOA HIGH MOUNT': { weight: 56, unit: 'g', sku: 'THHDRG3' },
    'THRIVE HD MULTI RETICLE HIGH REFLEX': { weight: 62, unit: 'g', sku: 'THHDMR' },
    'MICRO PRISM 1X20': { weight: 252, unit: 'g', sku: 'THHDMP' },
    'TRACE HALO ASPHERICAL ENCLOSED 3 MOA, Red Dot': { weight: 198, unit: 'g', sku: 'TRHALO' },
    'TRACE ASPHERICAL ENCLOSED RED DOT - HALO - FDE': { weight: 198, unit: 'g', sku: 'TRHALO-FDE' },
    'RAS 1X25 DIGITAL RED DOT 2 MOA, HIGH & LOW MOUNT': { weight: 310, unit: 'g', sku: 'RAS125' },
};

// Load Shopware products
const shopwareProducts = JSON.parse(fs.readFileSync('zerotech-products.json', 'utf8'));

console.log('='.repeat(90));
console.log('  ZEROTECH WEIGHT COMPARISON: Website vs Shopware');
console.log('='.repeat(90));

const matched = [];
const unmatched = [];

for (const product of shopwareProducts.withoutWeight) {
    const name = product.name.toUpperCase();

    // Try to find matching weight
    let foundWeight = null;
    for (const [ztName, data] of Object.entries(ZEROTECH_WEIGHTS)) {
        if (name.includes(ztName.toUpperCase()) || ztName.toUpperCase().includes(name)) {
            foundWeight = data;
            break;
        }
    }

    // Try partial matching
    if (!foundWeight) {
        const nameParts = name.split(' ').filter(p => p.length > 2);
        for (const [ztName, data] of Object.entries(ZEROTECH_WEIGHTS)) {
            const ztParts = ztName.toUpperCase().split(' ');
            const matchCount = nameParts.filter(p => ztParts.some(zp => zp.includes(p) || p.includes(zp))).length;
            if (matchCount >= 3) {
                foundWeight = data;
                break;
            }
        }
    }

    if (foundWeight) {
        matched.push({
            productNumber: product.productNumber,
            name: product.name,
            weight: foundWeight.weight,
            unit: foundWeight.unit
        });
    } else {
        unmatched.push(product);
    }
}

console.log(`\nTotal Shopware products without weight: ${shopwareProducts.withoutWeight.length}`);
console.log(`Matched with ZeroTech weights: ${matched.length}`);
console.log(`Unmatched: ${unmatched.length}`);

console.log('\n' + '='.repeat(90));
console.log('  MATCHED PRODUCTS - Ready to update in Shopware');
console.log('='.repeat(90));
console.log('\nProduct Number                       Weight    Name');
console.log('-'.repeat(90));

for (const p of matched) {
    console.log(`${p.productNumber.padEnd(35)} ${String(p.weight + p.unit).padStart(8)}   ${p.name.substring(0, 45)}`);
}

console.log('\n' + '='.repeat(90));
console.log('  UNMATCHED PRODUCTS - Need manual lookup');
console.log('='.repeat(90));
console.log('\nProduct Number                       Name');
console.log('-'.repeat(90));

for (const p of unmatched) {
    console.log(`${p.productNumber.padEnd(35)} ${p.name.substring(0, 50)}`);
}

// Save results
const results = {
    matched,
    unmatched,
    summary: {
        total: shopwareProducts.withoutWeight.length,
        matched: matched.length,
        unmatched: unmatched.length
    }
};

fs.writeFileSync('zerotech-weight-comparison.json', JSON.stringify(results, null, 2));
console.log('\n\nSaved to zerotech-weight-comparison.json');
