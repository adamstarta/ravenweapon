const XLSX = require('xlsx');
const wb = XLSX.readFile(String.raw`c:\Users\alama\Downloads\products (2).xlsx`);
const sheet = wb.Sheets[wb.SheetNames[0]];
const products = XLSX.utils.sheet_to_json(sheet);

// Find AIMPACT products
const aimpact = products.filter(p =>
    (p.name_en && p.name_en.toLowerCase().includes('aimpact')) ||
    (p.name_de && p.name_de.toLowerCase().includes('aimpact'))
);

console.log('AIMPACT products in Excel:', aimpact.length);
console.log('');
aimpact.forEach(p => {
    console.log('Name EN:', p.name_en);
    console.log('Number:', p.number);
    console.log('image_1:', p.image_1 ? p.image_1.substring(0,70)+'...' : 'NO');
    console.log('image_2:', p.image_2 ? 'YES' : 'NO');
    console.log('---');
});
