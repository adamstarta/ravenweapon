const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const SNIGEL_URL = 'https://products.snigel.se';
const USERNAME = 'Raven Weapon AG';
const PASSWORD = 'wVREVbRZfqT&Fba@f(^2UKOw';

const OUTPUT_DIR = path.join(__dirname, 'output');
const PROGRESS_FILE = path.join(OUTPUT_DIR, 'description-scrape-progress.json');
const LINKS_FILE = path.join(OUTPUT_DIR, 'product-links.json');

// Timeouts - site is VERY slow
const LOGIN_TIMEOUT = 60000;
const PAGE_TIMEOUT = 60000;

// Ensure output directory exists
if (!fs.existsSync(OUTPUT_DIR)) {
  fs.mkdirSync(OUTPUT_DIR, { recursive: true });
}

// Load progress if exists
function loadProgress() {
  if (fs.existsSync(PROGRESS_FILE)) {
    try {
      const data = JSON.parse(fs.readFileSync(PROGRESS_FILE, 'utf-8'));
      return data;
    } catch (e) {
      return { scrapedUrls: [], products: [] };
    }
  }
  return { scrapedUrls: [], products: [] };
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
  const stillNeedsLogin = await needsLogin(page);
  return !stillNeedsLogin;
}

// Extract description from product page
async function extractDescription(page) {
  return await page.evaluate(() => {
    const result = {
      cleanText: '',
      features: [],
      paragraphs: [],
      fitsIn: null,
      fitsOn: null
    };

    // The description is typically in a div after the price section
    const summaryDiv = document.querySelector('.summary.entry-summary');
    if (!summaryDiv) return result;

    // Method 1: Look for direct description div
    const descDiv = summaryDiv.querySelector('.woocommerce-product-details__short-description');
    if (descDiv) {
      result.cleanText = descDiv.innerText.trim();

      // Extract bullet points
      descDiv.querySelectorAll('li').forEach(li => {
        result.features.push(li.innerText.trim());
      });

      // Extract paragraphs
      descDiv.querySelectorAll('p').forEach(p => {
        const text = p.innerText.trim();
        if (text && !text.startsWith('Fits in') && !text.startsWith('Fits on') && !text.startsWith('Download')) {
          result.paragraphs.push(text);
        }

        // Check for Fits in/on
        if (text.startsWith('Fits in:')) {
          result.fitsIn = text.replace('Fits in:', '').trim();
        }
        if (text.startsWith('Fits on:')) {
          result.fitsOn = text.replace('Fits on:', '').trim();
        }
      });

      return result;
    }

    // Method 2: Look for any descriptive content in summary
    const allParagraphs = summaryDiv.querySelectorAll('p');
    const descParagraphs = [];

    allParagraphs.forEach(p => {
      const text = p.innerText.trim();
      // Skip price-related, stock-related, and meta content
      if (text &&
          !text.includes('€') &&
          !text.includes('in stock') &&
          !text.includes('Article no') &&
          !text.includes('EAN:') &&
          !text.includes('Weight:') &&
          !text.includes('Dimensions:') &&
          !text.includes('Category') &&
          !text.includes('Colour') &&
          !text.match(/^\d+ and more/) &&
          text.length > 20) {
        descParagraphs.push(text);
      }
    });

    if (descParagraphs.length > 0) {
      result.cleanText = descParagraphs.join('\n\n');
      result.paragraphs = descParagraphs;
    }

    return result;
  });
}

async function scrapeDescriptions() {
  console.log('='.repeat(60));
  console.log('SNIGEL DESCRIPTION SCRAPER');
  console.log('='.repeat(60));

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
    await page.goto(`${SNIGEL_URL}/product-category/all/`, {
      waitUntil: 'domcontentloaded',
      timeout: LOGIN_TIMEOUT
    });

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

    for (let i = 0; i < urlsToScrape.length; i++) {
      const link = urlsToScrape[i];
      const productSlug = link.split('/product/')[1]?.replace(/\/$/, '') || link;
      const current = progress.products.length + 1;

      process.stdout.write(`[${current}/${allProductLinks.length}] ${productSlug.substring(0, 35)}...`);

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

          // Get basic info
          const basicInfo = await page.evaluate(() => {
            const result = { name: null, articleNo: null };
            const titleEl = document.querySelector('h1.product_title, h1.entry-title, .product_title, h1');
            if (titleEl) result.name = titleEl.textContent.trim();
            const skuEl = document.querySelector('span.sku');
            if (skuEl) result.articleNo = skuEl.textContent.trim();
            return result;
          });

          if (!basicInfo.name) {
            throw new Error('No product name found');
          }

          // Get description
          const description = await extractDescription(page);

          productData = {
            name: basicInfo.name,
            articleNo: basicInfo.articleNo,
            url: link,
            description: description
          };

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

      progress.products.push(productData);
      progress.scrapedUrls.push(link);
      saveProgress(progress);

      const hasDesc = productData.description.cleanText.length > 0 ? 'Y' : 'N';
      console.log(` OK [desc:${hasDesc}]`);

      // Small delay between products
      await page.waitForTimeout(1000);
    }

    // === REPORT ===
    console.log('\n' + '='.repeat(60));
    console.log('COMPLETE');
    console.log('='.repeat(60));

    const withDesc = progress.products.filter(p => p.description.cleanText.length > 0).length;
    const withFeatures = progress.products.filter(p => p.description.features.length > 0).length;

    const report = {
      scraped_at: new Date().toISOString(),
      products: progress.products,
      summary: {
        total: progress.products.length,
        with_description: withDesc,
        without_description: progress.products.length - withDesc,
        with_features_list: withFeatures
      }
    };

    const outputPath = path.join(OUTPUT_DIR, `snigel-descriptions-${new Date().toISOString().split('T')[0]}.json`);
    fs.writeFileSync(outputPath, JSON.stringify(report, null, 2));

    console.log(`\nTotal:            ${report.summary.total} products`);
    console.log(`With description: ${report.summary.with_description}`);
    console.log(`No description:   ${report.summary.without_description}`);
    console.log(`With features:    ${report.summary.with_features_list}`);
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

async function compareDescriptions() {
  console.log('='.repeat(60));
  console.log('SNIGEL vs RAVENWEAPON DESCRIPTION COMPARISON');
  console.log('='.repeat(60));

  // Find the most recent description scrape file
  const files = fs.readdirSync(OUTPUT_DIR).filter(f => f.startsWith('snigel-descriptions-'));
  if (files.length === 0) {
    console.log('\nNo description data found. Run scraper first: node snigel-description-scraper.js');
    return;
  }

  files.sort().reverse();
  const scrapeFile = path.join(OUTPUT_DIR, files[0]);
  console.log(`\nLoading: ${files[0]}`);

  const scrapeData = JSON.parse(fs.readFileSync(scrapeFile, 'utf-8'));
  console.log(`Found ${scrapeData.products.length} Snigel products`);

  const withDesc = scrapeData.products.filter(p => p.description.cleanText.length > 0);
  const withoutDesc = scrapeData.products.filter(p => p.description.cleanText.length === 0);

  console.log(`\n--- Products WITH descriptions (${withDesc.length}) ---`);
  withDesc.slice(0, 10).forEach(p => {
    console.log(`  - ${p.name}`);
    console.log(`    "${p.description.cleanText.substring(0, 100)}..."`);
  });
  if (withDesc.length > 10) {
    console.log(`  ... and ${withDesc.length - 10} more`);
  }

  console.log(`\n--- Products WITHOUT descriptions (${withoutDesc.length}) ---`);
  withoutDesc.forEach(p => {
    console.log(`  - ${p.name} (${p.articleNo || 'no article'})`);
  });

  // Save analysis report
  const analysisPath = path.join(OUTPUT_DIR, `description-analysis-${new Date().toISOString().split('T')[0]}.json`);
  fs.writeFileSync(analysisPath, JSON.stringify({
    analyzed_at: new Date().toISOString(),
    source: files[0],
    products_with_description: withDesc.map(p => ({
      name: p.name,
      articleNo: p.articleNo,
      description: p.description.cleanText,
      features_count: p.description.features.length
    })),
    products_without_description: withoutDesc.map(p => ({
      name: p.name,
      articleNo: p.articleNo,
      url: p.url
    }))
  }, null, 2));

  console.log(`\nAnalysis saved: ${analysisPath}`);
}

// Main entry point
const args = process.argv.slice(2);
if (args.includes('--compare')) {
  compareDescriptions();
} else {
  scrapeDescriptions();
}
