const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

/**
 * Rapax Products Scraper v2
 * Scrapes all products from RAPAX and Caracal Lynx subcategories
 * Extracts: image, name, price, description, categories, variants
 */

const BASE_URL = 'https://shop.ravenweapon.ch';

// Output directory for scraped data
const OUTPUT_DIR = path.join(__dirname, 'rapax-data');

async function delay(ms) {
  return new Promise(resolve => setTimeout(resolve, ms));
}

async function scrapeProductDetail(page, productUrl, categoryPath) {
  console.log(`   📄 Scraping: ${productUrl}`);

  try {
    await page.goto(productUrl, { waitUntil: 'domcontentloaded', timeout: 30000 });
    await delay(2000);

    const productData = await page.evaluate(() => {
      // Get product name from h1
      const nameEl = document.querySelector('h1');
      const name = nameEl ? nameEl.textContent.trim() : '';

      // Get price - look for price near the product name area
      let price = '';
      const pricePatterns = document.body.innerText.match(/[\d',]+\.\d{2}/g);
      if (pricePatterns && pricePatterns.length > 0) {
        // First price pattern is usually the main price
        price = pricePatterns[0];
      }

      // Get breadcrumb/category path
      const breadcrumbEls = document.querySelectorAll('.breadcrumb a, nav a[href*="categories"]');
      const breadcrumb = [];
      breadcrumbEls.forEach(el => {
        const text = el.textContent.trim();
        if (text && text.length > 1 && !breadcrumb.includes(text)) {
          breadcrumb.push(text);
        }
      });

      // Get description - look for Description section
      let description = '';
      const descSection = document.body.innerText;
      const descMatch = descSection.match(/Description[\s\S]*?(?=Details|Variants|$)/i);
      if (descMatch) {
        description = descMatch[0].replace('Description', '').trim().slice(0, 1000);
      }

      // Parse specifications from description
      const specs = {};
      const specPatterns = [
        /CALIBER[:\s]+([^\n]+)/i,
        /MAGAZINE[:\s]+([^\n]+)/i,
        /BARREL[:\s]+([^\n]+)/i,
        /SIGHT[:\s]+([^\n]+)/i,
        /FRAME[:\s]+([^\n]+)/i,
        /FINISHES[:\s]+([^\n]+)/i,
        /WEIGHT[:\s]+([^\n]+)/i
      ];

      specPatterns.forEach(pattern => {
        const match = descSection.match(pattern);
        if (match) {
          const key = pattern.source.split('[')[0].toLowerCase();
          specs[key] = match[1].trim();
        }
      });

      // Get article number
      let articleNumber = '';
      const artMatch = descSection.match(/Article number\s+([^\n]+)/i);
      if (artMatch) {
        articleNumber = artMatch[1].trim();
      }

      // Get variants with prices
      const variants = [];
      const variantMatches = descSection.matchAll(/([A-Z\s]+)\s+([\d',]+\.\d{2})/g);
      for (const match of variantMatches) {
        const variantName = match[1].trim();
        const variantPrice = match[2];
        if (variantName.length > 3 && variantName.length < 50) {
          variants.push({ name: variantName, price: variantPrice });
        }
      }

      // Get all images
      const images = [];
      document.querySelectorAll('img').forEach(img => {
        const src = img.src || img.getAttribute('data-src');
        if (src && src.includes('makaris') && !images.includes(src)) {
          // Get full size image URL (remove thumb conversion)
          const fullSrc = src.replace('/conversions/', '/').replace('-thumb', '');
          images.push(fullSrc);
        }
      });

      // Also get background images
      document.querySelectorAll('[style*="background-image"]').forEach(el => {
        const style = el.getAttribute('style');
        const match = style.match(/url\(["']?([^"')]+)["']?\)/);
        if (match && match[1].includes('makaris') && !images.includes(match[1])) {
          images.push(match[1]);
        }
      });

      return {
        name,
        price,
        description,
        specifications: specs,
        articleNumber,
        variants,
        images: [...new Set(images)], // Remove duplicates
        breadcrumb
      };
    });

    // Add URL and category
    productData.url = productUrl;
    productData.category = categoryPath;
    productData.scrapedAt = new Date().toISOString();

    console.log(`      ✅ ${productData.name} | CHF ${productData.price}`);

    return productData;

  } catch (error) {
    console.log(`      ❌ Error: ${error.message}`);
    return {
      url: productUrl,
      category: categoryPath,
      error: error.message,
      scrapedAt: new Date().toISOString()
    };
  }
}

async function scrapeCategory(page, categoryUrl, categoryName, categoryPath) {
  console.log(`\n📂 Scraping: ${categoryName}`);
  console.log(`   URL: ${categoryUrl}`);

  const products = [];

  try {
    await page.goto(categoryUrl, { waitUntil: 'domcontentloaded', timeout: 30000 });
    await delay(2000);

    // Get all product links - using the correct URL pattern /products/
    const productLinks = await page.evaluate(() => {
      const links = [];
      document.querySelectorAll('.product-box a[href*="/products/"], a[href*="/products/"]').forEach(a => {
        const href = a.href;
        const title = a.getAttribute('title') || a.textContent.trim();
        if (href && href.includes('/products/') && !links.find(l => l.url === href)) {
          links.push({ url: href, title });
        }
      });
      return links;
    });

    // Remove duplicates
    const uniqueLinks = [...new Map(productLinks.map(l => [l.url, l])).values()];
    console.log(`   Found ${uniqueLinks.length} products`);

    // Scrape each product
    for (let i = 0; i < uniqueLinks.length; i++) {
      const link = uniqueLinks[i];
      console.log(`\n   [${i + 1}/${uniqueLinks.length}] ${link.title || 'Product'}`);

      const productData = await scrapeProductDetail(page, link.url, categoryPath);
      products.push(productData);

      await delay(1500);
    }

  } catch (error) {
    console.log(`   ❌ Error scraping category: ${error.message}`);
  }

  return products;
}

async function main() {
  console.log('='.repeat(70));
  console.log('RAPAX PRODUCTS SCRAPER v2');
  console.log('='.repeat(70));
  console.log(`Started: ${new Date().toISOString()}\n`);

  // Create output directory
  if (!fs.existsSync(OUTPUT_DIR)) {
    fs.mkdirSync(OUTPUT_DIR, { recursive: true });
  }

  const browser = await chromium.launch({ headless: false, slowMo: 50 });
  const page = await browser.newPage();

  const allProducts = {
    scrapedAt: new Date().toISOString(),
    source: 'https://shop.ravenweapon.ch',
    categories: [],
    products: []
  };

  // Define categories to scrape
  const categoriesToScrape = [
    // RAPAX subcategories
    {
      name: 'RX Sport',
      url: `${BASE_URL}/en/categories/weapons-1754/rapax-1768/rapax-1836/rx-sport-1837`,
      path: 'Weapons > RAPAX > RAPAX > RX Sport'
    },
    {
      name: 'RX Tactical',
      url: `${BASE_URL}/en/categories/weapons-1754/rapax-1768/rapax-1836/rx-tactical-1838`,
      path: 'Weapons > RAPAX > RAPAX > RX Tactical'
    },
    {
      name: 'RX Compact',
      url: `${BASE_URL}/en/categories/weapons-1754/rapax-1768/rapax-1836/rx-compact-1839`,
      path: 'Weapons > RAPAX > RAPAX > RX Compact'
    },
    // Caracal Lynx subcategories
    {
      name: 'LYNX SPORT',
      url: `${BASE_URL}/en/categories/weapons-1754/rapax-1768/caracal-lynx-1840/lynx-sport-1841`,
      path: 'Weapons > RAPAX > Caracal Lynx > LYNX SPORT'
    },
    {
      name: 'LYNX OPEN',
      url: `${BASE_URL}/en/categories/weapons-1754/rapax-1768/caracal-lynx-1840/lynx-open-1842`,
      path: 'Weapons > RAPAX > Caracal Lynx > LYNX OPEN'
    },
    {
      name: 'LYNX COMPACT',
      url: `${BASE_URL}/en/categories/weapons-1754/rapax-1768/caracal-lynx-1840/lynx-compact-1843`,
      path: 'Weapons > RAPAX > Caracal Lynx > LYNX COMPACT'
    }
  ];

  try {
    // Scrape each category
    for (const cat of categoriesToScrape) {
      const categoryProducts = await scrapeCategory(page, cat.url, cat.name, cat.path);

      allProducts.categories.push({
        name: cat.name,
        path: cat.path,
        url: cat.url,
        productCount: categoryProducts.length
      });

      allProducts.products.push(...categoryProducts);
    }

    // Remove duplicate products (same URL)
    const uniqueProducts = [...new Map(allProducts.products.map(p => [p.url, p])).values()];
    allProducts.products = uniqueProducts;
    allProducts.totalProducts = uniqueProducts.length;

    // Save JSON results
    const jsonFile = path.join(OUTPUT_DIR, 'rapax-products.json');
    fs.writeFileSync(jsonFile, JSON.stringify(allProducts, null, 2));
    console.log(`\n✅ Saved JSON: ${jsonFile}`);

    // Save CSV for easy viewing
    const csvLines = ['Name,Price,Category,Article Number,Images,URL'];
    uniqueProducts.forEach(p => {
      const name = (p.name || '').replace(/"/g, "'");
      const price = p.price || '';
      const category = (p.category || '').replace(/"/g, "'");
      const articleNumber = p.articleNumber || '';
      const images = (p.images || []).join(' | ');
      csvLines.push(`"${name}","${price}","${category}","${articleNumber}","${images}","${p.url}"`);
    });

    const csvFile = path.join(OUTPUT_DIR, 'rapax-products.csv');
    fs.writeFileSync(csvFile, csvLines.join('\n'));
    console.log(`✅ Saved CSV: ${csvFile}`);

    // Print detailed summary
    console.log('\n' + '='.repeat(70));
    console.log('SCRAPING SUMMARY');
    console.log('='.repeat(70));
    console.log(`\nTotal products scraped: ${uniqueProducts.length}`);

    console.log('\n📊 By Category:');
    const categoryCounts = {};
    uniqueProducts.forEach(p => {
      categoryCounts[p.category] = (categoryCounts[p.category] || 0) + 1;
    });
    for (const [cat, count] of Object.entries(categoryCounts)) {
      console.log(`   📂 ${cat}: ${count} products`);
    }

    console.log('\n📦 Products Detail:');
    uniqueProducts.forEach((p, i) => {
      console.log(`\n   ${i + 1}. ${p.name}`);
      console.log(`      💰 Price: CHF ${p.price}`);
      console.log(`      📁 Category: ${p.category}`);
      console.log(`      🔢 Article: ${p.articleNumber}`);
      console.log(`      🖼️  Images: ${p.images?.length || 0}`);
      if (p.specifications && Object.keys(p.specifications).length > 0) {
        console.log(`      📋 Specs: ${JSON.stringify(p.specifications)}`);
      }
      if (p.variants && p.variants.length > 0) {
        console.log(`      🎨 Variants: ${p.variants.length}`);
      }
    });

  } catch (error) {
    console.log(`\n❌ Fatal error: ${error.message}`);
  } finally {
    await browser.close();
  }

  console.log(`\n✅ Completed: ${new Date().toISOString()}`);
}

// Run the scraper
main().catch(console.error);
