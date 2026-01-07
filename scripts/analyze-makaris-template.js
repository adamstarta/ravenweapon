const XLSX = require('xlsx');
const path = require('path');

const templatePath = path.join(
    'C:', 'Users', 'alama', 'Desktop', 'NIKOLA WORK', 'ravenweapon',
    '.playwright-mcp', 'product-update-template.xlsx'
);

console.log('Reading template from:', templatePath);

const workbook = XLSX.readFile(templatePath);
console.log('Sheet names:', workbook.SheetNames);

const sheet = workbook.Sheets[workbook.SheetNames[0]];
const data = XLSX.utils.sheet_to_json(sheet, { header: 1 });

console.log('\n=== ALL ROWS ===');
data.slice(0, 10).forEach((row, rowIdx) => {
    console.log(`\nRow ${rowIdx}:`, row);
});

// Check for weight-related columns
console.log('\n=== SEARCHING FOR WEIGHT COLUMNS ===');
data.forEach((row, rowIdx) => {
    if (Array.isArray(row)) {
        row.forEach((cell, colIdx) => {
            if (cell && cell.toString().toLowerCase().includes('weight')) {
                console.log(`Found weight at Row ${rowIdx}, Col ${colIdx}: ${cell}`);
            }
            if (cell && cell.toString().toLowerCase().includes('gewicht')) {
                console.log(`Found gewicht at Row ${rowIdx}, Col ${colIdx}: ${cell}`);
            }
        });
    }
});
