import { createRequire } from 'node:module';
import { mkdir, rm } from 'node:fs/promises';

const require = createRequire(import.meta.url);
const { chromium } = require('../../vendor/playwright-php/playwright/bin/node_modules/playwright');

const url = process.env.OZON_WISHLIST_URL;
const profilePath = process.env.OZON_PROFILE_PATH;
const headless = process.env.CRAWLER_HEADLESS !== '0';

if (!url) {
  console.error('OZON_WISHLIST_URL is required.');
  process.exit(2);
}

if (!profilePath) {
  console.error('OZON_PROFILE_PATH is required.');
  process.exit(2);
}

await mkdir(profilePath, { recursive: true });
await Promise.all(['SingletonLock', 'SingletonSocket', 'SingletonCookie'].map((file) => (
  rm(`${profilePath}/${file}`, { force: true })
)));

const context = await chromium.launchPersistentContext(profilePath, {
  headless,
  locale: 'ru-RU',
  viewport: { width: 1440, height: 1200 },
  args: [
    '--no-sandbox',
    '--disable-dev-shm-usage',
    '--disable-blink-features=AutomationControlled',
  ],
});

try {
  const page = await context.newPage();

  await page.goto(url, {
    waitUntil: 'domcontentloaded',
    timeout: 90000,
  });

  await page.waitForTimeout(3000);

  let previousHeight = 0;

  for (let i = 0; i < 8; i += 1) {
    const height = await page.evaluate(() => document.body.scrollHeight);

    if (height === previousHeight && i > 1) {
      break;
    }

    previousHeight = height;

    await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
    await page.waitForTimeout(800);
  }

  process.stdout.write(await page.content());
} catch (error) {
  console.error(error instanceof Error ? error.message : String(error));
  process.exitCode = 1;
} finally {
  await context.close();
}
