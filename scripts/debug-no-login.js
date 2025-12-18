/**
 * Debug what prices show without login
 */
const { chromium } = require('playwright');

async function debug() {
    console.log('Testing WITHOUT login...\n');

    const browser = await chromium.launch({ headless: true });
    const page = await browser.newPage();

    const url = 'https://products.snigel.se/product/badge-holder/';
    console.log('Loading:', url);

    await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 30000 });
    await page.waitForTimeout(2000);

    const info = await page.evaluate(() => {
        const prices = [];
        const els = document.querySelectorAll('.woocommerce-Price-amount');
        els.forEach((el, i) => {
            prices.push({ index: i, text: el.textContent.trim() });
        });

        // Check if login required
        const pageText = document.body.innerText;
        const hasLogin = pageText.includes('Login') || pageText.includes('Log in');
        const hasSEK = pageText.includes('kr');
        const hasEUR = pageText.includes('€');

        return { prices, hasLogin, hasSEK, hasEUR, snippet: pageText.substring(0, 500) };
    });

    console.log('\nPrices found:', info.prices);
    console.log('Has SEK (kr):', info.hasSEK);
    console.log('Has EUR (€):', info.hasEUR);
    console.log('Has Login prompt:', info.hasLogin);
    console.log('\nPage snippet:', info.snippet);

    await browser.close();
}

debug();
