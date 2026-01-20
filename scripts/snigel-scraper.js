const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const SNIGEL_URL = 'https://products.snigel.se';
const USERNAME = 'Raven Weapon AG';
const PASSWORD = 'wVREVbRZfqT&Fba@f(^2UKOw';

const OUTPUT_DIR = path.join(__dirname, 'output');
const PROGRESS_FILE = path.join(OUTPUT_DIR, 'scrape-progress.json');
const LINKS_FILE = path.join(OUTPUT_DIR, 'product-links.json');

// Timeouts - site is VERY slow
const LOGIN_TIMEOUT = 60000;   // 60s for login
const PAGE_TIMEOUT = 60000;    // 60s for pagination
const PRODUCT_TIMEOUT = 30000; // 30s for products

// Ensure output directory exists
if (!fs.existsSync(OUTPUT_DIR)) {
  fs.mkdirSync(OUTPUT_DIR, { recursive: true });
}

// Load ravenweapon products from TSV
function loadRavenweaponProducts() {
  const tsvPath = path.join(OUTPUT_DIR, 'ravenweapon-products.tsv');
  const content = fs.readFileSync(tsvPath, 'utf-8');
  const products = [];

  for (const line of content.split('\n')) {
    if (!line.trim()) continue;
    const [sku, ...nameParts] = line.split('\t');
    const name = nameParts.join('\t').trim();
    if (sku && name) {
      products.push({ sku: sku.trim(), name });
    }
  }

  return products;
}

// Load progress if exists
function loadProgress() {
  if (fs.existsSync(PROGRESS_FILE)) {
    try {
      const data = JSON.parse(fs.readFileSync(PROGRESS_FILE, 'utf-8'));
      if (!data.failedUrls) data.failedUrls = [];
      return data;
    } catch (e) {
      return { scrapedUrls: [], products: [], failedUrls: [] };
    }
  }
  return { scrapedUrls: [], products: [], failedUrls: [] };
}

// Save progress
function saveProgress(progress) {
  fs.writeFileSync(PROGRESS_FILE, JSON.stringify(progress, null, 2));
}

// Load saved product links
function loadProductLinks() {
  if (fs.existsSync(LINKS_FILE)) {
    try {
      return JSON.parse(fs.readFileSync(LINKS_FILE, 'utf-8'));
    } catch (e) {
      return null;
    }
  }
  return null;
}

// Save product links
function saveProductLinks(links) {
  fs.writeFileSync(LINKS_FILE, JSON.stringify(links, null, 2));
}

// Normalize product name for comparison
function normalizeName(name) {
  return name
    .toLowerCase()
    .replace(/[^a-z0-9]/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();
}

// ============================================================
// VARIANT SCRAPING HELPER FUNCTIONS
// ============================================================

/**
 * Get all dropdown options from the product page
 * Returns: { "Colour": ["Black", "Grey", "Multicam"], "Size": ["S", "M", "L"] }
 */
async function getDropdownOptions(page) {
  return await page.evaluate(() => {
    const options = {};

    // Find all select elements (WooCommerce variation dropdowns)
    const selects = document.querySelectorAll('select[name^="attribute_"]');

    selects.forEach(select => {
      // Get the label name from the attribute name (e.g., "attribute_pa_colour" -> "Colour")
      const name = select.name
        .replace('attribute_pa_', '')
        .replace('attribute_', '')
        .replace(/-/g, ' ')
        .split(' ')
        .map(word => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');

      // Get all options except "Choose an option"
      const optionValues = [];
      select.querySelectorAll('option').forEach(opt => {
        const value = opt.textContent.trim();
        if (value && value !== 'Choose an option' && opt.value) {
          optionValues.push(value);
        }
      });

      if (optionValues.length > 0) {
        options[name] = optionValues;
      }
    });

    return options;
  });
}

/**
 * Generate all combinations from options object
 * Input: { "Colour": ["Black", "Grey"], "Size": ["S", "M"] }
 * Output: [{ Colour: "Black", Size: "S" }, { Colour: "Black", Size: "M" }, ...]
 */
function generateCombinations(options) {
  const keys = Object.keys(options);
  if (keys.length === 0) return [];

  const combinations = [];

  function generate(index, current) {
    if (index === keys.length) {
      combinations.push({ ...current });
      return;
    }

    const key = keys[index];
    const values = options[key];

    for (const value of values) {
      current[key] = value;
      generate(index + 1, current);
    }
  }

  generate(0, {});
  return combinations;
}

/**
 * Get the main product image filename (not full URL)
 */
async function getMainImageFilename(page) {
  return await page.evaluate(() => {
    // Try multiple selectors for the main product image
    const selectors = [
      '.woocommerce-product-gallery__image img',
      '.woocommerce-main-image img',
      '.wp-post-image',
      '.product-images img',
      '.woocommerce-product-gallery img'
    ];

    for (const selector of selectors) {
      const img = document.querySelector(selector);
      if (img && img.src) {
        // Extract filename from URL
        const url = img.src;
        const parts = url.split('/');
        return parts[parts.length - 1].split('?')[0]; // Remove query params
      }
    }

    return null;
  });
}

/**
 * Select dropdown values and capture the resulting image
 * Returns the image filename after selection
 */
async function selectAndCapture(page, combination, delay = 500) {
  // Select each dropdown value
  for (const [attrName, value] of Object.entries(combination)) {
    // Build the select name (e.g., "Colour" -> "attribute_pa_colour")
    const selectName = `attribute_pa_${attrName.toLowerCase().replace(/ /g, '-')}`;

    const selectExists = await page.$(`select[name="${selectName}"]`);
    if (!selectExists) {
      // Try without "pa_" prefix
      const altSelectName = `attribute_${attrName.toLowerCase().replace(/ /g, '-')}`;
      const altSelect = await page.$(`select[name="${altSelectName}"]`);
      if (altSelect) {
        await page.selectOption(`select[name="${altSelectName}"]`, { label: value });
      }
    } else {
      await page.selectOption(`select[name="${selectName}"]`, { label: value });
    }
  }

  // Wait for image to update
  await page.waitForTimeout(delay);

  // Get the current image filename
  return await getMainImageFilename(page);
}

// Find matching ravenweapon product
function findMatch(snigelName, ravenProducts) {
  const normalizedSnigel = normalizeName(snigelName);

  // Exact match first
  for (const rp of ravenProducts) {
    if (normalizeName(rp.name) === normalizedSnigel) {
      return { ...rp, matchType: 'exact' };
    }
  }

  // Partial match
  for (const rp of ravenProducts) {
    const normalizedRaven = normalizeName(rp.name);
    if (normalizedRaven.includes(normalizedSnigel) || normalizedSnigel.includes(normalizedRaven)) {
      return { ...rp, matchType: 'partial' };
    }
  }

  return null;
}

// Retry with longer delays for slow site
async function quickRetry(fn, retries = 2) {
  const delays = [3000, 5000];  // 3s, 5s delays
  for (let i = 0; i <= retries; i++) {
    try {
      return await fn();
    } catch (err) {
      if (i === retries) throw err;
      process.stdout.write(` retry...`);
      await new Promise(r => setTimeout(r, delays[i]));
    }
  }
}

// Check if page has login form
async function needsLogin(page) {
  const loginForm = await page.$('input[name="username"]');
  return !!loginForm;
}

// Perform login
async function doLogin(page) {
  console.log('  Filling login form...');
  await page.fill('input[name="username"]', USERNAME);
  await page.fill('input[name="password"]', PASSWORD);
  await page.click('button[name="login"]');
  await page.waitForTimeout(3000);

  // Verify login succeeded
  const stillNeedsLogin = await needsLogin(page);
  return !stillNeedsLogin;
}

async function scrapeSnigel() {
  console.log('='.repeat(60));
  console.log('SNIGEL PRODUCT SCRAPER');
  console.log('='.repeat(60));

  console.log('\nLoading ravenweapon products...');
  const ravenProducts = loadRavenweaponProducts();
  console.log(`Loaded ${ravenProducts.length} ravenweapon products`);

  const progress = loadProgress();
  console.log(`Resuming: ${progress.products.length} products already scraped`);

  console.log('\nLaunching browser (headless)...');
  const browser = await chromium.launch({
    headless: true
  });

  const context = await browser.newContext({
    userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
  });

  const page = await context.newPage();

  // Block images, fonts, media, analytics for speed
  await page.route('**/*', (route) => {
    const type = route.request().resourceType();
    const url = route.request().url();

    if (['image', 'font', 'media'].includes(type)) {
      return route.abort();
    }
    if (url.includes('google-analytics') || url.includes('facebook') ||
        url.includes('hotjar') || url.includes('analytics')) {
      return route.abort();
    }
    return route.continue();
  });

  let allProductLinks = loadProductLinks();

  try {
    // === LOGIN PHASE ===
    console.log('\nChecking login status...');

    // Go directly to product listing (not my-account)
    await page.goto(`${SNIGEL_URL}/product-category/all/`, {
      waitUntil: 'domcontentloaded',
      timeout: LOGIN_TIMEOUT
    });

    // Check if we need to login
    if (await needsLogin(page)) {
      console.log('Login required. Attempting login...');

      let loggedIn = false;
      for (let attempt = 1; attempt <= 3; attempt++) {
        console.log(`  Login attempt ${attempt}/3...`);
        try {
          loggedIn = await doLogin(page);
          if (loggedIn) {
            console.log('Login successful!');
            break;
          }
        } catch (err) {
          console.log(`  Login attempt failed: ${err.message.split('\n')[0]}`);
        }

        if (!loggedIn && attempt < 3) {
          console.log('  Waiting 5s before retry...');
          await page.waitForTimeout(5000);
          await page.goto(`${SNIGEL_URL}/my-account/`, {
            waitUntil: 'domcontentloaded',
            timeout: LOGIN_TIMEOUT
          });
        }
      }

      if (!loggedIn) {
        console.log('\nERROR: Could not login after 3 attempts.');
        console.log('Check credentials or try again later.');
        await browser.close();
        return;
      }
    } else {
      console.log('Already logged in!');
    }

    // === PAGINATION PHASE ===
    if (!allProductLinks || allProductLinks.length === 0) {
      allProductLinks = [];

      // Site has ~196 products across 20 pages (10 per page)
      const TOTAL_PAGES = 20;

      console.log('\nCollecting product links from all 20 pages...');
      console.log('(Will retry forever until each page loads - no skipping)\n');

      for (let pageNum = 1; pageNum <= TOTAL_PAGES; pageNum++) {
        const pageUrl = pageNum === 1
          ? `${SNIGEL_URL}/product-category/all/`
          : `${SNIGEL_URL}/product-category/all/?upage=${pageNum}`;

        process.stdout.write(`  Page ${pageNum}/${TOTAL_PAGES}...`);

        // Keep retrying FOREVER until page loads - no skipping
        let pageLoaded = false;
        let attempt = 0;
        while (!pageLoaded) {
          attempt++;
          try {
            await page.goto(pageUrl, {
              waitUntil: 'domcontentloaded',
              timeout: PAGE_TIMEOUT * 3  // 3 minutes timeout
            });
            // Wait for products to load
            await page.waitForTimeout(3000);
            await page.waitForSelector('a[href*="/product/"]', { timeout: 30000 });
            pageLoaded = true;
          } catch (err) {
            process.stdout.write(` retry ${attempt}...`);
            // Wait longer between retries (10 seconds)
            await new Promise(r => setTimeout(r, 10000));
          }
        }

        const links = await page.evaluate(() => {
          const allLinks = new Set();
          document.querySelectorAll('a[href*="/product/"]').forEach(a => {
            const href = a.href;
            if (href && href.includes('/product/') && !href.includes('/product-category/')) {
              allLinks.add(href);
            }
          });
          return [...allLinks];
        });

        let newCount = 0;
        links.forEach(link => {
          if (!allProductLinks.includes(link)) {
            allProductLinks.push(link);
            newCount++;
          }
        });

        console.log(` ${newCount} new (total: ${allProductLinks.length})`);
        saveProductLinks(allProductLinks);

        // Small delay between pages
        await page.waitForTimeout(2000);
      }

      console.log(`\nFound ${allProductLinks.length} total products`);
    } else {
      console.log(`\nLoaded ${allProductLinks.length} product links from cache`);
    }

    // === SCRAPING PHASE ===
    const urlsToScrape = allProductLinks.filter(url => !progress.scrapedUrls.includes(url));
    console.log(`\nTo scrape: ${urlsToScrape.length} | Already done: ${progress.scrapedUrls.length}`);
    console.log('(Will retry forever until each product loads - no skipping)');
    console.log('='.repeat(60));

    let successCount = 0;

    for (let i = 0; i < urlsToScrape.length; i++) {
      const link = urlsToScrape[i];
      const productSlug = link.split('/product/')[1]?.replace(/\/$/, '') || link;
      const current = progress.products.length + 1;

      process.stdout.write(`[${current}/${allProductLinks.length}] ${productSlug}...`);

      // Keep retrying FOREVER until product page loads
      let productLoaded = false;
      let attempt = 0;
      let productData = null;

      while (!productLoaded) {
        attempt++;
        try {
          await page.goto(link, {
            waitUntil: 'domcontentloaded',
            timeout: PAGE_TIMEOUT * 2  // 2 minutes timeout
          });

          await page.waitForSelector('h1', { timeout: 30000 });
          await page.waitForTimeout(1000);

          // Basic product info
          productData = await page.evaluate(() => {
            const result = { name: null, articleNo: null, colour: null };

            const titleEl = document.querySelector('h1.product_title, h1.entry-title, .product_title, h1');
            if (titleEl) result.name = titleEl.textContent.trim();

            const skuEl = document.querySelector('span.sku');
            if (skuEl) result.articleNo = skuEl.textContent.trim();

            const colourLink = document.querySelector('a[href*="/colour/"]');
            if (colourLink) result.colour = colourLink.textContent.trim();

            return result;
          });

          if (!productData.name) {
            throw new Error('No product name found');
          }

          // === VARIANT SCRAPING ===
          // Get all dropdown options
          const options = await getDropdownOptions(page);
          productData.options = options;
          productData.variant_images = [];

          // If there are dropdown options, capture all variant images
          if (Object.keys(options).length > 0) {
            const combinations = generateCombinations(options);
            process.stdout.write(` [${combinations.length} variants]`);

            for (const combo of combinations) {
              try {
                const imageFilename = await selectAndCapture(page, combo, 500);
                productData.variant_images.push({
                  selection: combo,
                  image: imageFilename
                });
              } catch (err) {
                // Log error but continue with other combinations
                productData.variant_images.push({
                  selection: combo,
                  image: null,
                  error: err.message.split('\n')[0]
                });
              }
            }
          }

          productLoaded = true;
        } catch (err) {
          if (attempt > 1) {
            process.stdout.write(` retry ${attempt}...`);
          } else {
            process.stdout.write(` retry...`);
          }
          // Wait 10 seconds between retries
          await new Promise(r => setTimeout(r, 10000));
        }
      }

      const match = findMatch(productData.name, ravenProducts);

      progress.products.push({
        snigel_name: productData.name,
        snigel_article_no: productData.articleNo,
        snigel_colour: productData.colour,
        snigel_url: link,
        options: productData.options || {},
        variant_images: productData.variant_images || [],
        ravenweapon_name: match ? match.name : null,
        ravenweapon_sku: match ? match.sku : null,
        match_status: match ? match.matchType : 'not_found'
      });
      progress.scrapedUrls.push(link);

      // Remove from failed if it was there
      progress.failedUrls = progress.failedUrls.filter(u => u !== link);

      successCount++;
      saveProgress(progress);

      const matchSymbol = match ? (match.matchType === 'exact' ? '=' : '~') : 'X';
      console.log(` OK [${matchSymbol}] ${productData.articleNo || '-'}`);

      // Small delay between products
      await page.waitForTimeout(1000);
    }

    // === REPORT ===
    console.log('\n' + '='.repeat(60));
    console.log('COMPLETE');
    console.log('='.repeat(60));

    // Count products with variants
    const withVariants = progress.products.filter(p => p.variant_images && p.variant_images.length > 0).length;
    const totalVariants = progress.products.reduce((sum, p) => sum + (p.variant_images?.length || 0), 0);

    const report = {
      scraped_at: new Date().toISOString(),
      snigel_products: progress.products,
      summary: {
        total_snigel: progress.products.length,
        with_article_no: progress.products.filter(p => p.snigel_article_no).length,
        with_colour: progress.products.filter(p => p.snigel_colour).length,
        with_variants: withVariants,
        total_variant_images: totalVariants,
        matched_exact: progress.products.filter(p => p.match_status === 'exact').length,
        matched_partial: progress.products.filter(p => p.match_status === 'partial').length,
        not_found: progress.products.filter(p => p.match_status === 'not_found').length
      }
    };

    const outputPath = path.join(OUTPUT_DIR, `snigel-full-scrape-${new Date().toISOString().split('T')[0]}.json`);
    fs.writeFileSync(outputPath, JSON.stringify(report, null, 2));

    console.log(`\nTotal:    ${report.summary.total_snigel} products`);
    console.log(`Variants: ${report.summary.with_variants} products with variants (${report.summary.total_variant_images} total images)`);
    console.log(`Matched:  ${report.summary.matched_exact} exact, ${report.summary.matched_partial} partial`);
    console.log(`Missing:  ${report.summary.not_found} not on ravenweapon`);
    console.log(`\nReport: ${outputPath}`);

  } catch (err) {
    console.error('\nError:', err.message.split('\n')[0]);
    console.log('Progress saved. Run again to resume.');
  } finally {
    await browser.close();
  }
}

// ============================================================
// COMPARISON MODE
// ============================================================

/**
 * Compare scraped Snigel data with staging products
 * Run with: node snigel-scraper.js --compare
 */
async function compareWithStaging() {
  console.log('='.repeat(60));
  console.log('SNIGEL vs STAGING COMPARISON');
  console.log('='.repeat(60));

  // Find the most recent full scrape file
  const files = fs.readdirSync(OUTPUT_DIR).filter(f => f.startsWith('snigel-full-scrape-'));
  if (files.length === 0) {
    console.log('\nNo scrape data found. Run scraper first: node snigel-scraper.js');
    return;
  }

  files.sort().reverse(); // Most recent first
  const scrapeFile = path.join(OUTPUT_DIR, files[0]);
  console.log(`\nLoading: ${files[0]}`);

  const scrapeData = JSON.parse(fs.readFileSync(scrapeFile, 'utf-8'));
  console.log(`Found ${scrapeData.snigel_products.length} Snigel products`);

  // Load ravenweapon products
  console.log('\nLoading ravenweapon products...');
  const ravenProducts = loadRavenweaponProducts();
  console.log(`Loaded ${ravenProducts.length} staging products`);

  const comparisons = [];

  for (const snigelProduct of scrapeData.snigel_products) {
    // Find matching staging product
    const match = findMatch(snigelProduct.snigel_name, ravenProducts);

    // Extract colors from Snigel (from options.Colour or snigel_colour)
    let snigelColors = [];
    if (snigelProduct.options && snigelProduct.options.Colour) {
      snigelColors = snigelProduct.options.Colour;
    } else if (snigelProduct.snigel_colour) {
      snigelColors = [snigelProduct.snigel_colour];
    }

    // Extract sizes from Snigel (from options)
    let snigelSizes = [];
    if (snigelProduct.options) {
      // Look for Size, Type, or other non-Colour options
      for (const [key, values] of Object.entries(snigelProduct.options)) {
        if (key !== 'Colour') {
          snigelSizes = [...snigelSizes, ...values];
        }
      }
    }

    const comparison = {
      product: snigelProduct.snigel_name,
      snigel_article_no: snigelProduct.snigel_article_no,
      snigel_url: snigelProduct.snigel_url,
      snigel_colors: snigelColors,
      snigel_sizes: snigelSizes,
      staging_name: match ? match.name : null,
      staging_sku: match ? match.sku : null,
      match_status: match ? match.matchType : 'not_found',
      image_comparison: []
    };

    // Add image comparison data
    if (snigelProduct.variant_images && snigelProduct.variant_images.length > 0) {
      for (const variant of snigelProduct.variant_images) {
        const colorKey = variant.selection?.Colour || 'default';
        comparison.image_comparison.push({
          selection: variant.selection,
          snigel_image: variant.image,
          // Note: staging_has would require additional scraping of staging site
          staging_has: null // Will be determined if staging images are scraped
        });
      }
    }

    comparisons.push(comparison);
  }

  // Generate summary statistics
  const summary = {
    total_snigel_products: comparisons.length,
    matched: comparisons.filter(c => c.match_status !== 'not_found').length,
    not_found: comparisons.filter(c => c.match_status === 'not_found').length,
    products_with_multiple_colors: comparisons.filter(c => c.snigel_colors.length > 1).length,
    products_with_sizes: comparisons.filter(c => c.snigel_sizes.length > 0).length,
    total_variant_images: comparisons.reduce((sum, c) => sum + c.image_comparison.length, 0)
  };

  const report = {
    compared_at: new Date().toISOString(),
    source_scrape: files[0],
    comparisons,
    summary
  };

  const outputPath = path.join(OUTPUT_DIR, `snigel-comparison-${new Date().toISOString().split('T')[0]}.json`);
  fs.writeFileSync(outputPath, JSON.stringify(report, null, 2));

  console.log('\n' + '='.repeat(60));
  console.log('COMPARISON RESULTS');
  console.log('='.repeat(60));
  console.log(`\nTotal Snigel products:   ${summary.total_snigel_products}`);
  console.log(`Matched on staging:      ${summary.matched}`);
  console.log(`Not found on staging:    ${summary.not_found}`);
  console.log(`\nProducts with colors:    ${summary.products_with_multiple_colors}`);
  console.log(`Products with sizes:     ${summary.products_with_sizes}`);
  console.log(`Total variant images:    ${summary.total_variant_images}`);
  console.log(`\nReport saved: ${outputPath}`);

  // List products not found on staging
  const notFound = comparisons.filter(c => c.match_status === 'not_found');
  if (notFound.length > 0) {
    console.log(`\n--- Products NOT FOUND on staging (${notFound.length}) ---`);
    notFound.forEach(p => {
      console.log(`  - ${p.product} (${p.snigel_article_no || 'no article no'})`);
    });
  }
}

// Main entry point
const args = process.argv.slice(2);
if (args.includes('--compare')) {
  compareWithStaging();
} else {
  scrapeSnigel();
}
