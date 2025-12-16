/**
 * Ammunition Products Scraper
 * Scrapes all ammunition products from shop.ravenweapon.ch
 * Extracts: image, name, price, description, categories
 *
 * Usage: node scrape-ammunition-products.js
 */

const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const BASE_URL = 'https://shop.ravenweapon.ch';
const AMMUNITION_URL = `${BASE_URL}/en/categories/ammunition-1773`;

// Output directory for scraped data
const OUTPUT_DIR = path.join(__dirname, 'ammunition-data');

async function delay(ms) {
  const randomMs = ms + Math.floor(Math.random() * 500);
  return new Promise(resolve => setTimeout(resolve, randomMs));
}

async function scrapeProductDetail(page, productUrl, category) {
  console.log(`   Scraping product detail: ${productUrl}`);

  try {
    await page.goto(productUrl, { waitUntil: 'domcontentloaded', timeout: 30000 });
    await delay(2000);

    const productData = await page.evaluate(() => {
      // Get product name
      const nameEl = document.querySelector('h1, .product-detail-name, [itemprop="name"]');
      const name = nameEl ? nameEl.textContent.trim() : '';

      // Get price - look for multiple price selectors
      let price = '';
      const priceSelectors = [
        '.product-detail-price',
        '.product-price',
        '[itemprop="price"]',
        '.price',
        'h4.price',
        '.product-detail-price-container .price'
      ];

      for (const selector of priceSelectors) {
        const el = document.querySelector(selector);
        if (el) {
          price = el.textContent.trim().replace(/\s+/g, ' ');
          break;
        }
      }

      // Extract numeric price
      const priceMatch = price.match(/([\d.,]+)/);
      const numericPrice = priceMatch ? parseFloat(priceMatch[1].replace(',', '.')) : 0;

      // Get description
      const descEl = document.querySelector('.product-detail-description, .product-description, [itemprop="description"], .description');
      const description = descEl ? descEl.innerHTML : '';
      const descriptionText = descEl ? descEl.textContent.trim() : '';

      // Get all images
      const images = [];
      document.querySelectorAll('.gallery-slider img, .product-detail-media img, .product-image img, [itemprop="image"], .product-detail-image img').forEach(img => {
        const src = img.src || img.getAttribute('data-src') || img.getAttribute('srcset')?.split(' ')[0];
        if (src && !images.includes(src) && !src.includes('placeholder')) {
          // Get highest resolution version
          const highResSrc = src.replace('-thumb', '').replace('-medium', '').replace('-small', '');
          images.push(highResSrc);
        }
      });

      // Also check for background images in gallery
      document.querySelectorAll('.gallery-slider-item, .product-gallery-item').forEach(item => {
        const style = item.getAttribute('style') || '';
        const bgMatch = style.match(/url\(['"]?([^'"]+)['"]?\)/);
        if (bgMatch && !images.includes(bgMatch[1])) {
          images.push(bgMatch[1]);
        }
      });

      // Get product number/SKU
      const skuEl = document.querySelector('.product-detail-ordernumber, [itemprop="sku"], .product-number, .product-detail-ordernumber-container');
      const sku = skuEl ? skuEl.textContent.trim().replace(/[^a-zA-Z0-9-]/g, '') : '';

      // Get manufacturer
      const manufacturerEl = document.querySelector('.product-detail-manufacturer, [itemprop="brand"], .product-manufacturer');
      const manufacturer = manufacturerEl ? manufacturerEl.textContent.trim() : '';

      // Get specifications/properties
      const specs = {};
      document.querySelectorAll('.product-detail-properties-table tr, .product-properties tr, .specifications tr').forEach(row => {
        const label = row.querySelector('th, td:first-child')?.textContent.trim();
        const value = row.querySelector('td:last-child, td:nth-child(2)')?.textContent.trim();
        if (label && value) {
          specs[label] = value;
        }
      });

      // Get availability
      const availabilityEl = document.querySelector('.delivery-status, .product-detail-delivery, [itemprop="availability"]');
      const availability = availabilityEl ? availabilityEl.textContent.trim() : '';

      // Get breadcrumb categories
      const breadcrumbs = [];
      document.querySelectorAll('.breadcrumb a, .breadcrumb-navigation a, nav[aria-label="breadcrumb"] a').forEach(a => {
        const text = a.textContent.trim();
        if (text && text !== 'Home' && text !== 'Complete assortment') {
          breadcrumbs.push(text);
        }
      });

      // Get EAN if available
      const eanEl = document.querySelector('[itemprop="gtin13"], .product-detail-ean');
      const ean = eanEl ? eanEl.textContent.trim() : '';

      return {
        name,
        price,
        numericPrice,
        description,
        descriptionText,
        images,
        sku,
        manufacturer,
        specifications: specs,
        availability,
        breadcrumbs,
        ean
      };
    });

    // Add category and URL
    productData.url = productUrl;
    productData.category = category;
    productData.scrapedAt = new Date().toISOString();

    return productData;

  } catch (error) {
    console.log(`   ERROR scraping product: ${error.message}`);
    return {
      url: productUrl,
      category,
      error: error.message,
      scrapedAt: new Date().toISOString()
    };
  }
}

async function scrapeCategory(page, categoryUrl, categoryName, allProducts = []) {
  console.log(`\n Scraping category: ${categoryName}`);
  console.log(`   URL: ${categoryUrl}`);

  const products = [];

  try {
    await page.goto(categoryUrl, { waitUntil: 'domcontentloaded', timeout: 30000 });
    await delay(2000);

    // Take screenshot of category page
    await page.screenshot({
      path: path.join(OUTPUT_DIR, `category-${categoryName.replace(/[^a-zA-Z0-9]/g, '-')}.png`),
      fullPage: true
    });

    // Get all product links on the page
    const productLinks = await page.evaluate(() => {
      const links = [];

      // Multiple selectors for product links
      const selectors = [
        '.product-box a[href*="/products/"]',
        '.product-info a[href*="/products/"]',
        'a.product-image-link',
        '.product-name a',
        '.product-card a[href*="/products/"]',
        'a[href*="/products/"][title]'
      ];

      selectors.forEach(selector => {
        document.querySelectorAll(selector).forEach(a => {
          const href = a.href;
          const title = a.getAttribute('title') || a.textContent?.trim() || '';
          if (href && href.includes('/products/') && !links.find(l => l.url === href)) {
            // Try to get price from nearby elements
            let price = '';
            const card = a.closest('.product-box, .product-card, .product-info');
            if (card) {
              const priceEl = card.querySelector('.price, h4, .product-price');
              if (priceEl) {
                price = priceEl.textContent.trim();
              }
            }

            // Try to get image
            let image = '';
            const imgEl = card?.querySelector('img') || a.querySelector('img');
            if (imgEl) {
              image = imgEl.src || imgEl.getAttribute('data-src') || '';
            }

            links.push({ url: href, title, price, image });
          }
        });
      });
      return links;
    });

    console.log(`   Found ${productLinks.length} products in ${categoryName}`);

    // Scrape each product
    for (let i = 0; i < productLinks.length; i++) {
      const link = productLinks[i];

      // Skip if already scraped
      if (allProducts.find(p => p.url === link.url)) {
        console.log(`   [${i + 1}/${productLinks.length}] SKIP (already scraped): ${link.title || link.url}`);
        continue;
      }

      console.log(`\n   [${i + 1}/${productLinks.length}] ${link.title || link.url}`);

      const productData = await scrapeProductDetail(page, link.url, categoryName);

      // Add quick price/image from listing if detail page failed
      if (!productData.numericPrice && link.price) {
        const priceMatch = link.price.match(/([\d.,]+)/);
        productData.numericPrice = priceMatch ? parseFloat(priceMatch[1].replace(',', '.')) : 0;
        productData.price = link.price;
      }
      if (productData.images.length === 0 && link.image) {
        productData.images.push(link.image);
      }

      products.push(productData);
      allProducts.push(productData);

      // Small delay between products
      await delay(1500);
    }

    // Check for pagination
    let pageNum = 2;
    let hasMore = true;

    while (hasMore && pageNum <= 20) {
      const nextPageUrl = `${categoryUrl}?p=${pageNum}`;
      console.log(`\n   Checking page ${pageNum}...`);

      try {
        await page.goto(nextPageUrl, { waitUntil: 'domcontentloaded', timeout: 30000 });
        await delay(2000);

        const moreLinks = await page.evaluate(() => {
          const links = [];
          const selectors = [
            '.product-box a[href*="/products/"]',
            '.product-info a[href*="/products/"]',
            'a.product-image-link',
            'a[href*="/products/"][title]'
          ];

          selectors.forEach(selector => {
            document.querySelectorAll(selector).forEach(a => {
              const href = a.href;
              const title = a.getAttribute('title') || a.textContent?.trim() || '';
              if (href && href.includes('/products/') && !links.find(l => l.url === href)) {
                links.push({ url: href, title });
              }
            });
          });
          return links;
        });

        if (moreLinks.length > 0) {
          console.log(`   Found ${moreLinks.length} more products on page ${pageNum}`);

          for (let i = 0; i < moreLinks.length; i++) {
            const link = moreLinks[i];
            // Check if we already have this product
            if (!allProducts.find(p => p.url === link.url)) {
              const productData = await scrapeProductDetail(page, link.url, categoryName);
              products.push(productData);
              allProducts.push(productData);
              await delay(1500);
            }
          }
          pageNum++;
        } else {
          hasMore = false;
        }
      } catch (e) {
        hasMore = false;
      }
    }

  } catch (error) {
    console.log(`   ERROR scraping category: ${error.message}`);
  }

  return products;
}

async function main() {
  console.log('='.repeat(70));
  console.log('AMMUNITION PRODUCTS SCRAPER');
  console.log('Source: shop.ravenweapon.ch');
  console.log('Target: ortak.ch (Shopware)');
  console.log('='.repeat(70));
  console.log(`Started at: ${new Date().toISOString()}`);

  // Create output directory
  if (!fs.existsSync(OUTPUT_DIR)) {
    fs.mkdirSync(OUTPUT_DIR, { recursive: true });
  }

  const browser = await chromium.launch({ headless: false, slowMo: 50 });
  const context = await browser.newContext({
    userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    viewport: { width: 1920, height: 1080 }
  });
  const page = await context.newPage();

  const allProducts = {
    scrapedAt: new Date().toISOString(),
    source: AMMUNITION_URL,
    categories: {},
    products: []
  };

  try {
    // First, go to the main Ammunition page to find subcategories
    console.log('\n Finding subcategories...');
    await page.goto(AMMUNITION_URL, { waitUntil: 'domcontentloaded', timeout: 30000 });
    await delay(2000);

    // Take screenshot of main page
    await page.screenshot({ path: path.join(OUTPUT_DIR, 'ammunition-main.png'), fullPage: true });

    // Find subcategory links
    const subcategories = await page.evaluate(() => {
      const cats = [];

      // Look for sidebar navigation or subcategory links
      const selectors = [
        '.sidebar-navigation a[href*="/categories/"]',
        '.navigation-sidebar a[href*="/categories/"]',
        'aside a[href*="/categories/"]',
        '.category-navigation a[href*="/categories/"]',
        '.subcategory-list a',
        '.category-teaser a[href*="/categories/"]',
        'a[href*="/categories/"][href*="ammunition"]',
        // Category boxes/cards
        '.cms-element-category-navigation a',
        '.category-link-list a'
      ];

      selectors.forEach(selector => {
        document.querySelectorAll(selector).forEach(a => {
          const href = a.href;
          const text = a.textContent.trim();
          // Filter for ammunition related categories
          if (href && text && href.includes('/categories/') && !cats.find(c => c.url === href)) {
            // Exclude parent ammunition category to avoid duplicates
            if (!href.endsWith('ammunition-1773')) {
              cats.push({ name: text, url: href });
            }
          }
        });
      });

      return cats;
    });

    console.log(`\n Found ${subcategories.length} subcategories:`);
    subcategories.forEach(c => console.log(`   - ${c.name}: ${c.url}`));

    // Always include the main ammunition category
    const categoriesToScrape = [
      { name: 'Ammunition', url: AMMUNITION_URL },
      ...subcategories
    ];

    // Scrape each category
    for (const cat of categoriesToScrape) {
      const categoryProducts = await scrapeCategory(page, cat.url, cat.name, allProducts.products);

      allProducts.categories[cat.name] = {
        url: cat.url,
        productCount: categoryProducts.length
      };
    }

    // Remove duplicate products (by URL)
    const uniqueProducts = [...new Map(allProducts.products.map(p => [p.url, p])).values()];
    allProducts.products = uniqueProducts;
    allProducts.totalProducts = uniqueProducts.length;

    // Save results as JSON
    const outputFile = path.join(OUTPUT_DIR, 'ammunition-products.json');
    fs.writeFileSync(outputFile, JSON.stringify(allProducts, null, 2));
    console.log(`\n Saved ${uniqueProducts.length} products to: ${outputFile}`);

    // Create a summary CSV for easy viewing
    const csvLines = ['Name,Price (CHF),Category,SKU,Image URL,Product URL'];
    uniqueProducts.forEach(p => {
      const name = (p.name || '').replace(/,/g, ';').replace(/"/g, "'");
      const price = p.numericPrice || '';
      const category = (p.category || '').replace(/,/g, ';');
      const sku = (p.sku || '').replace(/,/g, ';');
      const image = p.images?.[0] || '';
      csvLines.push(`"${name}","${price}","${category}","${sku}","${image}","${p.url}"`);
    });

    const csvFile = path.join(OUTPUT_DIR, 'ammunition-products.csv');
    fs.writeFileSync(csvFile, csvLines.join('\n'));
    console.log(` Saved CSV summary to: ${csvFile}`);

    // Download images
    console.log('\n Downloading product images...');
    const imagesDir = path.join(OUTPUT_DIR, 'images');
    if (!fs.existsSync(imagesDir)) {
      fs.mkdirSync(imagesDir, { recursive: true });
    }

    let downloadedImages = 0;
    for (const product of uniqueProducts) {
      if (product.images && product.images.length > 0) {
        for (let i = 0; i < product.images.length; i++) {
          const imageUrl = product.images[i];
          try {
            // Create safe filename from product name
            const safeName = (product.sku || product.name || 'product')
              .replace(/[^a-zA-Z0-9]/g, '-')
              .substring(0, 50);
            const ext = imageUrl.split('.').pop().split('?')[0] || 'jpg';
            const filename = `${safeName}${i > 0 ? `-${i}` : ''}.${ext}`;
            const filepath = path.join(imagesDir, filename);

            // Download image
            const response = await page.goto(imageUrl, { timeout: 15000 });
            if (response && response.ok()) {
              const buffer = await response.body();
              fs.writeFileSync(filepath, buffer);
              product.localImages = product.localImages || [];
              product.localImages.push(filename);
              downloadedImages++;
            }
          } catch (imgError) {
            console.log(`   Failed to download: ${imageUrl.substring(0, 50)}...`);
          }
        }
      }
    }
    console.log(` Downloaded ${downloadedImages} images to: ${imagesDir}`);

    // Save updated JSON with local image paths
    fs.writeFileSync(outputFile, JSON.stringify(allProducts, null, 2));

    // Print summary
    console.log('\n' + '='.repeat(70));
    console.log('SCRAPING SUMMARY');
    console.log('='.repeat(70));
    console.log(`Total products scraped: ${uniqueProducts.length}`);
    console.log(`Total images downloaded: ${downloadedImages}`);
    console.log('\nBy category:');

    const categoryCounts = {};
    uniqueProducts.forEach(p => {
      categoryCounts[p.category] = (categoryCounts[p.category] || 0) + 1;
    });

    for (const [cat, count] of Object.entries(categoryCounts)) {
      console.log(`    ${cat}: ${count} products`);
    }

    console.log('\nProducts:');
    uniqueProducts.forEach((p, i) => {
      console.log(`\n   ${i + 1}. ${p.name}`);
      console.log(`      Price: CHF ${p.numericPrice || 'N/A'}`);
      console.log(`      Category: ${p.category}`);
      console.log(`      SKU: ${p.sku || 'N/A'}`);
      console.log(`      Images: ${p.images?.length || 0}`);
    });

  } catch (error) {
    console.log(`\n FATAL ERROR: ${error.message}`);
    console.log(error.stack);
  } finally {
    await browser.close();
  }

  console.log(`\nCompleted at: ${new Date().toISOString()}`);
  console.log('\nNext step: Run the import script to import to ortak.ch');
  console.log('  php import-ammunition-products.php');
}

// Run the scraper
main().catch(console.error);
