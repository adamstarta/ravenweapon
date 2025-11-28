const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: false, slowMo: 100 });
  const page = await browser.newPage();
  await page.setViewportSize({ width: 1400, height: 900 });

  // Go to homepage first to check navbar
  console.log('Navigating to homepage...');
  await page.goto('http://localhost/', { waitUntil: 'networkidle', timeout: 30000 });
  await page.waitForTimeout(2000);
  await page.screenshot({ path: 'C:/Users/alama/Desktop/NIKOLA WORK/ravenweapon/screenshot-navbar.png', fullPage: false });
  console.log('Screenshot 1: Homepage navbar saved');

  // Now go to login page
  console.log('Navigating to login page...');
  await page.goto('http://localhost/account/login', { waitUntil: 'networkidle', timeout: 30000 });
  await page.waitForTimeout(2000);
  await page.screenshot({ path: 'C:/Users/alama/Desktop/NIKOLA WORK/ravenweapon/screenshot-login.png', fullPage: true });
  console.log('Screenshot 2: Login page saved');

  await browser.close();
  console.log('Done!');
})();
