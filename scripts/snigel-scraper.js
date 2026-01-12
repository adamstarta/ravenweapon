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
      let pageNum = 1;

      console.log('\nCollecting product links...');

      while (true) {
        const pageUrl = pageNum === 1
          ? `${SNIGEL_URL}/product-category/all/`
          : `${SNIGEL_URL}/product-category/all/?upage=${pageNum}`;

        process.stdout.write(`  Page ${pageNum}...`);

        // Retry pagination up to 3 times with networkidle
        let pageLoaded = false;
        for (let attempt = 1; attempt <= 3; attempt++) {
          try {
            await page.goto(pageUrl, {
              waitUntil: 'networkidle',  // Wait for network to be quiet
              timeout: PAGE_TIMEOUT
            });
            pageLoaded = true;
            break;
          } catch (err) {
            if (attempt < 3) {
              process.stdout.write(` retry ${attempt}...`);
              await new Promise(r => setTimeout(r, 3000));
            }
          }
        }

        if (!pageLoaded) {
          console.log(' timeout - end of pages');
          break;
        }

        // Small delay to not overload the server
        await page.waitForTimeout(1000);
        await page.waitForSelector('a[href*="/product/"]', { timeout: 10000 }).catch(() => {});

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

        if (newCount === 0) {
          console.log('  No more new products');
          break;
        }

        pageNum++;
        if (pageNum > 100) break;
      }

      console.log(`\nFound ${allProductLinks.length} total products`);
    } else {
      console.log(`\nLoaded ${allProductLinks.length} product links from cache`);
    }

    // === SCRAPING PHASE ===
    const urlsToScrape = allProductLinks.filter(url => !progress.scrapedUrls.includes(url));
    console.log(`\nTo scrape: ${urlsToScrape.length} | Already done: ${progress.scrapedUrls.length}`);
    console.log('='.repeat(60));

    let successCount = 0;
    let errorCount = 0;

    for (let i = 0; i < urlsToScrape.length; i++) {
      const link = urlsToScrape[i];
      const productSlug = link.split('/product/')[1]?.replace(/\/$/, '') || link;
      const current = progress.products.length + 1;

      process.stdout.write(`[${current}/${allProductLinks.length}] ${productSlug}...`);

      try {
        await quickRetry(async () => {
          await page.goto(link, {
            waitUntil: 'networkidle',  // Wait for network to be quiet
            timeout: PRODUCT_TIMEOUT
          });
        });

        await page.waitForSelector('h1', { timeout: 5000 }).catch(() => {});
        await page.waitForTimeout(500);  // Small delay

        const productData = await page.evaluate(() => {
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
          console.log(' SKIP (no name)');
          progress.scrapedUrls.push(link);
          saveProgress(progress);
          errorCount++;
          continue;
        }

        const match = findMatch(productData.name, ravenProducts);

        progress.products.push({
          snigel_name: productData.name,
          snigel_article_no: productData.articleNo,
          snigel_colour: productData.colour,
          snigel_url: link,
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

      } catch (err) {
        console.log(` FAIL`);
        if (!progress.failedUrls.includes(link)) {
          progress.failedUrls.push(link);
        }
        saveProgress(progress);
        errorCount++;
      }
    }

    // === RETRY PHASE ===
    if (progress.failedUrls.length > 0) {
      console.log('\n' + '='.repeat(60));
      console.log(`RETRYING ${progress.failedUrls.length} FAILED PRODUCTS`);
      console.log('='.repeat(60));

      const failedToRetry = [...progress.failedUrls];
      for (const link of failedToRetry) {
        const productSlug = link.split('/product/')[1]?.replace(/\/$/, '') || link;
        process.stdout.write(`[RETRY] ${productSlug}...`);

        try {
          await page.goto(link, {
            waitUntil: 'domcontentloaded',
            timeout: PAGE_TIMEOUT * 2  // Longer timeout for retry
          });

          await page.waitForSelector('h1', { timeout: 5000 }).catch(() => {});

          const productData = await page.evaluate(() => {
            const result = { name: null, articleNo: null, colour: null };
            const titleEl = document.querySelector('h1.product_title, h1.entry-title, .product_title, h1');
            if (titleEl) result.name = titleEl.textContent.trim();
            const skuEl = document.querySelector('span.sku');
            if (skuEl) result.articleNo = skuEl.textContent.trim();
            const colourLink = document.querySelector('a[href*="/colour/"]');
            if (colourLink) result.colour = colourLink.textContent.trim();
            return result;
          });

          if (productData.name) {
            const match = findMatch(productData.name, ravenProducts);
            progress.products.push({
              snigel_name: productData.name,
              snigel_article_no: productData.articleNo,
              snigel_colour: productData.colour,
              snigel_url: link,
              ravenweapon_name: match ? match.name : null,
              ravenweapon_sku: match ? match.sku : null,
              match_status: match ? match.matchType : 'not_found'
            });
            progress.scrapedUrls.push(link);
            progress.failedUrls = progress.failedUrls.filter(u => u !== link);
            successCount++;
            saveProgress(progress);
            console.log(` OK`);
          } else {
            console.log(` SKIP`);
          }
        } catch (err) {
          console.log(` FAIL`);
        }
      }
    }

    // === REPORT ===
    console.log('\n' + '='.repeat(60));
    console.log('COMPLETE');
    console.log('='.repeat(60));

    const report = {
      scraped_at: new Date().toISOString(),
      snigel_products: progress.products,
      summary: {
        total_snigel: progress.products.length,
        with_article_no: progress.products.filter(p => p.snigel_article_no).length,
        with_colour: progress.products.filter(p => p.snigel_colour).length,
        matched_exact: progress.products.filter(p => p.match_status === 'exact').length,
        matched_partial: progress.products.filter(p => p.match_status === 'partial').length,
        not_found: progress.products.filter(p => p.match_status === 'not_found').length
      }
    };

    const outputPath = path.join(OUTPUT_DIR, `snigel-comparison-${new Date().toISOString().split('T')[0]}.json`);
    fs.writeFileSync(outputPath, JSON.stringify(report, null, 2));

    console.log(`\nTotal:    ${report.summary.total_snigel} products`);
    console.log(`Matched:  ${report.summary.matched_exact} exact, ${report.summary.matched_partial} partial`);
    console.log(`Missing:  ${report.summary.not_found} not on ravenweapon`);
    console.log(`Success:  ${successCount} | Failed: ${progress.failedUrls.length}`);
    console.log(`\nReport: ${outputPath}`);

    if (progress.failedUrls.length > 0) {
      console.log(`\nRun script again to retry ${progress.failedUrls.length} failed products.`);
    }

  } catch (err) {
    console.error('\nError:', err.message.split('\n')[0]);
    console.log('Progress saved. Run again to resume.');
  } finally {
    await browser.close();
  }
}

scrapeSnigel();
