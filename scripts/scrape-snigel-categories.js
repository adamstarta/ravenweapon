const { chromium } = require('playwright');
const fs = require('fs');

const TARGET_URL = 'https://products.snigel.se';
const USERNAME = 'Raven Weapon AG';
const PASSWORD = 'wVREVbRZfqT&Fba@f(^2UKOw';

(async () => {
  const browser = await chromium.launch({
    headless: false,
    slowMo: 50
  });
  const page = await browser.newPage();
  page.setDefaultTimeout(60000);
  page.setDefaultNavigationTimeout(60000);

  try {
    // Login
    console.log('🔐 Logging into Snigel B2B portal...');
    await page.goto(`${TARGET_URL}/my-account/`);
    await page.waitForTimeout(2000);

    await page.fill('input[name="username"]', USERNAME);
    await page.fill('input[name="password"]', PASSWORD);
    await page.click('button[name="login"]');
    await page.waitForTimeout(3000);
    console.log('✅ Logged in successfully!');

    // Go to categories page
    console.log('\n📂 Fetching categories...');
    await page.goto(`${TARGET_URL}/product-categories/`);
    await page.waitForTimeout(2000);

    // Extract category links from dropdown menu (more complete)
    const categories = await page.evaluate(() => {
      const cats = [];
      const links = document.querySelectorAll('a[href*="products.snigel.se/product-category"]');
      links.forEach(link => {
        const name = link.textContent.trim();
        const href = link.href;
        if (name && href && !cats.find(c => c.href === href)) {
          cats.push({ name, href });
        }
      });
      return cats;
    });

    console.log(`Found ${categories.length} categories\n`);

    // Visit each category and count products
    const results = [];

    for (const cat of categories) {
      if (cat.name === 'ALL PRODUCTS') continue; // Skip the "all" category

      console.log(`📦 Checking: ${cat.name}...`);

      try {
        await page.goto(cat.href, { waitUntil: 'domcontentloaded' });
        await page.waitForTimeout(1500);

        // Try to find product count from result count text
        let productCount = 0;

        // Method 1: Look for "Showing X–Y of Z results" text
        const resultText = await page.evaluate(() => {
          const resultCount = document.querySelector('.woocommerce-result-count');
          return resultCount ? resultCount.textContent : null;
        });

        if (resultText) {
          // Parse "Showing 1–24 of 156 results" or "Showing all 5 results"
          const matchAll = resultText.match(/Showing all (\d+) results/);
          const matchRange = resultText.match(/of (\d+) results/);
          const matchSingle = resultText.match(/Showing the single result/);

          if (matchAll) {
            productCount = parseInt(matchAll[1]);
          } else if (matchRange) {
            productCount = parseInt(matchRange[1]);
          } else if (matchSingle) {
            productCount = 1;
          }
        }

        // Method 2: If no result text, count products on page
        if (productCount === 0) {
          productCount = await page.evaluate(() => {
            const products = document.querySelectorAll('.product, .type-product, li.product');
            return products.length;
          });
        }

        results.push({
          name: cat.name,
          count: productCount,
          href: cat.href
        });

        console.log(`   ✅ ${cat.name}: ${productCount} products`);

      } catch (err) {
        console.log(`   ❌ Error: ${err.message}`);
        results.push({
          name: cat.name,
          count: 'ERROR',
          href: cat.href
        });
      }
    }

    // Sort by product count descending
    results.sort((a, b) => (b.count || 0) - (a.count || 0));

    // Print summary
    console.log('\n' + '='.repeat(50));
    console.log('📊 SNIGEL B2B CATEGORY PRODUCT COUNTS');
    console.log('='.repeat(50));

    let totalProducts = 0;
    for (const r of results) {
      const countStr = typeof r.count === 'number' ? r.count : r.count;
      console.log(`${r.name.padEnd(30)} ${String(countStr).padStart(5)} products`);
      if (typeof r.count === 'number') totalProducts += r.count;
    }

    console.log('='.repeat(50));
    console.log(`TOTAL (excluding duplicates): ~${totalProducts} products`);
    console.log('='.repeat(50));

    // Save to JSON
    const outputPath = 'scripts/snigel-data/category-counts.json';
    fs.writeFileSync(outputPath, JSON.stringify(results, null, 2));
    console.log(`\n💾 Saved to ${outputPath}`);

  } catch (error) {
    console.error('❌ Error:', error.message);
  } finally {
    await browser.close();
    console.log('\n🔒 Browser closed');
  }
})();
