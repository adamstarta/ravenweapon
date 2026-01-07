/**
 * Analyze Excel file for weight data
 * Excludes Raven Swiss products
 */

const XLSX = require('xlsx');
const path = require('path');

const excelPath = 'c:\\Users\\alama\\Downloads\\products (2).xlsx';

const workbook = XLSX.readFile(excelPath);
const sheet = workbook.Sheets[workbook.SheetNames[0]];
const data = XLSX.utils.sheet_to_json(sheet);

// Filter out Raven Swiss products
const products = data.filter(p => {
    const number = (p.number || '').toUpperCase();
    const name = (p.name_en || p.name_de || '').toLowerCase();
    // Exclude Raven Swiss rifles (LHT-GW-RVSW prefix or "raven swiss" in name)
    return !number.startsWith('LHT-GW-RVSW') && !name.includes('raven swiss');
});

console.log('='.repeat(90));
console.log('  EXCEL PRODUCTS WEIGHT ANALYSIS (Excluding Raven Swiss Rifles)');
console.log('='.repeat(90));
console.log(`Total products in Excel: ${data.length}`);
console.log(`After excluding Raven Swiss: ${products.length}`);

// Categorize by weight status
const withWeight = [];
const withoutWeight = [];

for (const p of products) {
    const grossWeight = p.gross_weight_in_kg;
    const netWeight = p.net_weight_in_kg;
    const hasWeight = (grossWeight && grossWeight > 0) || (netWeight && netWeight > 0);

    const item = {
        number: p.number,
        name: p.name_en || p.name_de || 'Unknown',
        grossWeight: grossWeight || null,
        netWeight: netWeight || null,
        brand: p.brand_id || ''
    };

    if (hasWeight) {
        withWeight.push(item);
    } else {
        withoutWeight.push(item);
    }
}

console.log(`\nProducts WITH weight: ${withWeight.length}`);
console.log(`Products WITHOUT weight: ${withoutWeight.length}`);

// Show products WITH weight
console.log('\n' + '='.repeat(90));
console.log('  PRODUCTS WITH WEIGHT IN EXCEL');
console.log('='.repeat(90));
console.log('Product Number                         Gross(kg)   Net(kg)   Name');
console.log('-'.repeat(90));
withWeight.sort((a,b) => (a.number || '').localeCompare(b.number || ''));
for (const p of withWeight) {
    const num = (p.number || '').substring(0, 35).padEnd(35);
    const gross = p.grossWeight ? String(p.grossWeight).padStart(10) : '      -   ';
    const net = p.netWeight ? String(p.netWeight).padStart(10) : '      -   ';
    const name = (p.name || '').substring(0, 35);
    console.log(`${num}  ${gross}  ${net}   ${name}`);
}

// Show products WITHOUT weight
console.log('\n' + '='.repeat(90));
console.log('  PRODUCTS WITHOUT WEIGHT IN EXCEL (Need weight data!)');
console.log('='.repeat(90));
console.log('Product Number                         Name');
console.log('-'.repeat(90));
withoutWeight.sort((a,b) => (a.number || '').localeCompare(b.number || ''));
for (const p of withoutWeight) {
    const num = (p.number || '').substring(0, 35).padEnd(35);
    const name = (p.name || '').substring(0, 55);
    console.log(`${num}  ${name}`);
}

// Summary
console.log('\n' + '='.repeat(90));
console.log('  SUMMARY');
console.log('='.repeat(90));
console.log(`Total products (excl. Raven Swiss): ${products.length}`);
console.log(`With weight:                        ${withWeight.length} (${(withWeight.length/products.length*100).toFixed(1)}%)`);
console.log(`Without weight:                     ${withoutWeight.length} (${(withoutWeight.length/products.length*100).toFixed(1)}%)`);
console.log('='.repeat(90));
