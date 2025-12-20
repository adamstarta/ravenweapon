/**
 * Scrape Footer Pages from shop.ravenweapon.ch
 *
 * Scrapes content from:
 * - About us
 * - Contact
 * - Privacy Policy
 * - Terms of use
 *
 * Usage: node scrape-ravenweapon-footer.js
 */

const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const BASE_URL = 'https://shop.ravenweapon.ch/en';

const PAGES = [
    {
        name: 'about-us',
        url: `${BASE_URL}/about-us`,
        germanTitle: 'Über uns'
    },
    {
        name: 'contact',
        url: `${BASE_URL}/contact`,
        germanTitle: 'Kontakt'
    },
    {
        name: 'privacy-policy',
        url: `${BASE_URL}/privacy-policy`,
        germanTitle: 'Datenschutzerklärung'
    },
    {
        name: 'terms-of-service',
        url: `${BASE_URL}/terms-of-service`,
        germanTitle: 'Allgemeine Geschäftsbedingungen'
    }
];

async function scrapeAllPages() {
    console.log('='.repeat(60));
    console.log('RAVEN WEAPON FOOTER CONTENT SCRAPER');
    console.log('='.repeat(60));
    console.log(`Source: ${BASE_URL}`);
    console.log(`Date: ${new Date().toISOString()}`);
    console.log('='.repeat(60) + '\n');

    const browser = await chromium.launch({ headless: true });
    const context = await browser.newContext();
    const page = await context.newPage();

    const results = {
        scrapedAt: new Date().toISOString(),
        sourceUrl: BASE_URL,
        pages: {}
    };

    // First get footer contact info
    console.log('>>> Scraping Footer Contact Info...\n');
    await page.goto(BASE_URL, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(2000);

    const footerInfo = await page.evaluate(() => {
        const data = {
            instagram: null,
            contact: {
                company: null,
                address: null,
                phone: null,
                email: null
            }
        };

        // Find Instagram link
        const instaLink = document.querySelector('a[href*="instagram.com"]');
        if (instaLink) {
            data.instagram = instaLink.href;
        }

        // Find contact info in footer
        const footer = document.querySelector('footer') || document.querySelector('[class*="footer"]');
        if (footer) {
            const text = footer.innerText;

            // Extract company name
            if (text.includes('Raven Weapon AG')) {
                data.contact.company = 'Raven Weapon AG';
            }

            // Extract address - look for Gorisstrasse or similar
            const addressMatch = text.match(/Gorisstrasse\s+\d+,?\s*\d+\s*St\.\s*Gallenkappel/i);
            if (addressMatch) {
                data.contact.address = addressMatch[0];
            }

            // Extract phone
            const phoneMatch = text.match(/\+41\s*\d+\s*\d+\s*\d+\s*\d+/);
            if (phoneMatch) {
                data.contact.phone = phoneMatch[0];
            }

            // Extract email
            const emailMatch = text.match(/[\w.-]+@[\w.-]+\.\w+/);
            if (emailMatch) {
                data.contact.email = emailMatch[0];
            }
        }

        return data;
    });

    results.footerInfo = footerInfo;

    console.log('FOOTER INFO:');
    console.log('-'.repeat(40));
    console.log(`Instagram: ${footerInfo.instagram}`);
    console.log(`Company: ${footerInfo.contact.company}`);
    console.log(`Address: ${footerInfo.contact.address}`);
    console.log(`Phone: ${footerInfo.contact.phone}`);
    console.log(`Email: ${footerInfo.contact.email}`);
    console.log('\n');

    // Scrape each page
    for (const pageInfo of PAGES) {
        console.log('='.repeat(60));
        console.log(`>>> Scraping: ${pageInfo.name.toUpperCase()}`);
        console.log(`    URL: ${pageInfo.url}`);
        console.log(`    German Title: ${pageInfo.germanTitle}`);
        console.log('='.repeat(60) + '\n');

        await page.goto(pageInfo.url, { waitUntil: 'domcontentloaded' });
        await page.waitForTimeout(2000);

        const content = await page.evaluate(() => {
            const result = {
                pageTitle: '',
                sections: []
            };

            // Get page title (h1)
            const h1 = document.querySelector('h1');
            if (h1) {
                result.pageTitle = h1.textContent.trim();
            }

            // Find main content area
            const mainContent = document.querySelector('main') ||
                               document.querySelector('[class*="content"]') ||
                               document.querySelector('.container');

            if (!mainContent) return result;

            // Get all headings and their content
            const headings = mainContent.querySelectorAll('h2, h3, h4');

            headings.forEach((heading, index) => {
                const section = {
                    level: heading.tagName.toLowerCase(),
                    title: heading.textContent.trim(),
                    content: []
                };

                // Get all siblings until next heading
                let sibling = heading.nextElementSibling;
                while (sibling && !['H1', 'H2', 'H3', 'H4'].includes(sibling.tagName)) {
                    if (sibling.tagName === 'P') {
                        section.content.push({
                            type: 'paragraph',
                            text: sibling.textContent.trim()
                        });
                    } else if (sibling.tagName === 'UL' || sibling.tagName === 'OL') {
                        const items = Array.from(sibling.querySelectorAll('li')).map(li => li.textContent.trim());
                        section.content.push({
                            type: 'list',
                            items: items
                        });
                    } else if (sibling.textContent.trim()) {
                        section.content.push({
                            type: 'text',
                            text: sibling.textContent.trim()
                        });
                    }
                    sibling = sibling.nextElementSibling;
                }

                if (section.title || section.content.length > 0) {
                    result.sections.push(section);
                }
            });

            // If no sections found, get all text
            if (result.sections.length === 0) {
                const allText = mainContent.innerText.trim();
                result.rawText = allText;
            }

            return result;
        });

        results.pages[pageInfo.name] = {
            url: pageInfo.url,
            englishTitle: content.pageTitle,
            germanTitle: pageInfo.germanTitle,
            sections: content.sections,
            rawText: content.rawText || null
        };

        // Print content
        console.log(`Page Title: ${content.pageTitle}`);
        console.log(`Sections Found: ${content.sections.length}`);
        console.log('-'.repeat(40));

        if (content.sections.length > 0) {
            content.sections.forEach((section, idx) => {
                console.log(`\n[${idx + 1}] ${section.level.toUpperCase()}: ${section.title}`);
                section.content.forEach(item => {
                    if (item.type === 'paragraph' || item.type === 'text') {
                        // Truncate long text for display
                        const displayText = item.text.length > 200 ? item.text.substring(0, 200) + '...' : item.text;
                        console.log(`    ${displayText}`);
                    } else if (item.type === 'list') {
                        item.items.forEach(li => {
                            const displayItem = li.length > 100 ? li.substring(0, 100) + '...' : li;
                            console.log(`    • ${displayItem}`);
                        });
                    }
                });
            });
        } else if (content.rawText) {
            console.log('\nRaw Text (first 500 chars):');
            console.log(content.rawText.substring(0, 500) + '...');
        }

        console.log('\n');
    }

    await browser.close();

    // Save full results to JSON
    const outputPath = path.join(__dirname, 'scraped-ravenweapon-content.json');
    fs.writeFileSync(outputPath, JSON.stringify(results, null, 2));

    console.log('='.repeat(60));
    console.log('SCRAPING COMPLETE!');
    console.log('='.repeat(60));
    console.log(`Full results saved to: ${outputPath}`);
    console.log('\nSUMMARY:');
    console.log('-'.repeat(40));
    Object.entries(results.pages).forEach(([name, data]) => {
        console.log(`• ${name}: ${data.sections.length} sections scraped`);
    });

    return results;
}

// Run
scrapeAllPages().catch(console.error);
