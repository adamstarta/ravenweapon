const https = require('https');
const http = require('http');

const API_URL = 'http://localhost:8082/store-api/search';
const ACCESS_KEY = 'SWSCWWRXRLLXNWHNB0F2NJNIUG';

// Read product names from stdin
let inputData = '';

process.stdin.on('data', chunk => {
  inputData += chunk;
});

process.stdin.on('end', async () => {
  const products = inputData.trim().split('\n').filter(p => p.trim());
  console.log(`Testing search for ${products.length} products...\n`);

  const results = {
    found: [],
    notFound: [],
    errors: []
  };

  for (let i = 0; i < products.length; i++) {
    const productName = products[i].trim();
    // Use first 2-3 significant words for search
    const searchTerms = productName.split(' ').slice(0, 3).join(' ');

    try {
      const count = await searchProduct(searchTerms);
      if (count > 0) {
        results.found.push({ name: productName, searchTerms, count });
      } else {
        results.notFound.push({ name: productName, searchTerms });
      }

      // Progress update every 20 products
      if ((i + 1) % 20 === 0) {
        console.log(`Progress: ${i + 1}/${products.length} tested...`);
      }
    } catch (err) {
      results.errors.push({ name: productName, error: err.message });
    }
  }

  console.log('\n========== SEARCH TEST RESULTS ==========\n');
  console.log(`✓ Found: ${results.found.length}`);
  console.log(`✗ Not Found: ${results.notFound.length}`);
  console.log(`⚠ Errors: ${results.errors.length}`);

  if (results.notFound.length > 0) {
    console.log('\n--- Products NOT FOUND ---');
    results.notFound.forEach(p => {
      console.log(`  - "${p.name}" (searched: "${p.searchTerms}")`);
    });
  }

  if (results.errors.length > 0) {
    console.log('\n--- Errors ---');
    results.errors.forEach(p => {
      console.log(`  - "${p.name}": ${p.error}`);
    });
  }

  console.log('\n==========================================');
});

function searchProduct(searchTerm) {
  return new Promise((resolve, reject) => {
    const postData = JSON.stringify({ search: searchTerm });

    const options = {
      hostname: 'localhost',
      port: 8082,
      path: '/store-api/search',
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'sw-access-key': ACCESS_KEY,
        'Content-Length': Buffer.byteLength(postData)
      }
    };

    const req = http.request(options, (res) => {
      let data = '';
      res.on('data', chunk => data += chunk);
      res.on('end', () => {
        try {
          const json = JSON.parse(data);
          resolve(json.total || 0);
        } catch (e) {
          reject(new Error('Invalid JSON response'));
        }
      });
    });

    req.on('error', reject);
    req.write(postData);
    req.end();
  });
}
