const XLSX = require('xlsx');
const fs = require('fs');
const wb = XLSX.readFile(String.raw`c:\Users\alama\Downloads\products (2).xlsx`);
const sheet = wb.Sheets[wb.SheetNames[0]];
const products = XLSX.utils.sheet_to_json(sheet);

// Get products with Makaris IDs (exclude RAV- prefix)
const withMakarisId = products.filter(p => {
    if (!p.public_url || !p.number) return false;
    if (p.number.startsWith('RAV-')) return false;
    const match = p.public_url.match(/-(\d+)$/);
    return match !== null;
}).map(p => {
    const match = p.public_url.match(/-(\d+)$/);
    return {
        number: p.number,
        name: p.name_en || p.name_de,
        makarisId: match[1]
    };
});

console.log('Products with Makaris ID (excluding RAV-):', withMakarisId.length);
console.log('\nList:');
withMakarisId.forEach((p, i) => {
    console.log((i+1) + '. ' + p.number + ' (ID: ' + p.makarisId + ')');
});

// Save to file for scraping
fs.writeFileSync('makaris-products-list.json', JSON.stringify(withMakarisId, null, 2));
console.log('\nSaved to makaris-products-list.json');
