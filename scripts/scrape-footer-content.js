/**
 * Scrape Footer Content from shop.ravenweapon.ch
 *
 * This script scrapes the footer pages (About us, Contact, Privacy Policy, Terms of use)
 * from shop.ravenweapon.ch and saves the content to JSON for use in ortak.ch
 *
 * Usage: node scrape-footer-content.js
 */

const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const BASE_URL = 'https://shop.ravenweapon.ch/en';

const PAGES_TO_SCRAPE = [
    { name: 'about-us', url: `${BASE_URL}/about-us`, titleDe: 'Über uns' },
    { name: 'contact', url: `${BASE_URL}/contact`, titleDe: 'Kontakt' },
    { name: 'privacy-policy', url: `${BASE_URL}/privacy-policy`, titleDe: 'Datenschutzerklärung' },
    { name: 'terms-of-service', url: `${BASE_URL}/terms-of-service`, titleDe: 'Allgemeine Geschäftsbedingungen' }
];

async function scrapeFooterContent() {
    console.log('Starting footer content scraper...\n');

    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext();
    const page = await context.newPage();

    const results = {
        scrapedAt: new Date().toISOString(),
        sourceUrl: BASE_URL,
        footerContact: null,
        pages: {}
    };

    try {
        // First, scrape footer contact info from main page
        console.log('Scraping footer contact info from main page...');
        await page.goto(BASE_URL, { waitUntil: 'domcontentloaded' });

        results.footerContact = await page.evaluate(() => {
            const footer = document.querySelector('footer') || document.querySelector('[class*="footer"]');
            if (!footer) return null;

            // Find contact section
            const contactHeading = Array.from(footer.querySelectorAll('h4')).find(h => h.textContent.includes('Contact'));
            if (contactHeading) {
                const contactList = contactHeading.closest('div')?.querySelector('ul');
                if (contactList) {
                    const items = Array.from(contactList.querySelectorAll('li')).map(li => li.textContent.trim());
                    return {
                        company: 'Raven Weapon AG',
                        address: items.find(i => i.includes('strasse') || i.includes('Goris')) || 'Gorisstrasse 1, 8735 St. Gallenkappel',
                        phone: '+41 79 356 19 86',
                        email: 'info@ravenweapon.ch'
                    };
                }
            }

            return {
                company: 'Raven Weapon AG',
                address: 'Gorisstrasse 1, 8735 St. Gallenkappel',
                phone: '+41 79 356 19 86',
                email: 'info@ravenweapon.ch'
            };
        });

        console.log('Footer contact:', results.footerContact);

        // Scrape each page
        for (const pageInfo of PAGES_TO_SCRAPE) {
            console.log(`\nScraping ${pageInfo.name}...`);

            await page.goto(pageInfo.url, { waitUntil: 'domcontentloaded' });
            await page.waitForTimeout(1000);

            const content = await page.evaluate(() => {
                // Find main content area (excluding header/footer)
                const main = document.querySelector('main') ||
                             document.querySelector('[class*="content"]') ||
                             document.querySelector('.container');

                if (!main) return { title: '', html: '', text: '' };

                // Clone to avoid modifying the page
                const clone = main.cloneNode(true);

                // Remove header, footer, navigation elements from clone
                const removeSelectors = ['header', 'footer', 'nav', '[class*="header"]', '[class*="footer"]', '[class*="nav"]'];
                removeSelectors.forEach(sel => {
                    clone.querySelectorAll(sel).forEach(el => el.remove());
                });

                // Get the title (h1)
                const h1 = clone.querySelector('h1');
                const title = h1 ? h1.textContent.trim() : '';

                // Get the main content div
                const contentDiv = clone.querySelector('[class*="content"]') || clone;

                return {
                    title: title,
                    html: contentDiv.innerHTML,
                    text: contentDiv.textContent.trim()
                };
            });

            results.pages[pageInfo.name] = {
                url: pageInfo.url,
                titleEn: content.title,
                titleDe: pageInfo.titleDe,
                html: content.html,
                text: content.text
            };

            console.log(`  Title: ${content.title}`);
            console.log(`  Content length: ${content.text.length} chars`);
        }

    } catch (error) {
        console.error('Error scraping:', error);
    } finally {
        await browser.close();
    }

    // Save results
    const outputPath = path.join(__dirname, 'scraped-footer-content.json');
    fs.writeFileSync(outputPath, JSON.stringify(results, null, 2));
    console.log(`\nResults saved to: ${outputPath}`);

    // Generate German translations summary
    console.log('\n=== SCRAPED CONTENT SUMMARY ===\n');
    console.log('Footer Contact Information:');
    console.log(`  Company: ${results.footerContact?.company}`);
    console.log(`  Address: ${results.footerContact?.address}`);
    console.log(`  Phone: ${results.footerContact?.phone}`);
    console.log(`  Email: ${results.footerContact?.email}`);
    console.log('\nPages scraped:');
    Object.entries(results.pages).forEach(([name, data]) => {
        console.log(`  - ${name}: "${data.titleEn}" (${data.text.length} chars)`);
    });

    return results;
}

// Run the scraper
scrapeFooterContent().catch(console.error);
