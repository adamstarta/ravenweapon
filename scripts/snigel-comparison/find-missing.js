const b2b = require('./comparison-report-1765968924701.json').missingInShopware;
const updated = require('./pending-updates.json');

const updatedNames = updated.map(p => p.name.toLowerCase());

console.log('=== Products on B2B but NOT on ortak.ch ===\n');
let count = 0;
b2b.forEach(p => {
  const nameL = p.name.toLowerCase();
  const found = updatedNames.some(n => n.includes(nameL.substring(0,10)) || nameL.includes(n.substring(0,10)));
  if (!found) {
    count++;
    console.log(count + '.', p.name, '- EUR', p.b2bEur);
  }
});
console.log('\nTotal missing:', count);
