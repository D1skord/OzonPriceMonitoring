import { createRequire } from 'node:module';
import { mkdir, rm } from 'node:fs/promises';

const require = createRequire(import.meta.url);
const { chromium } = require('../../vendor/playwright-php/playwright/bin/node_modules/playwright');

const profilePath = process.env.OZON_PROFILE_PATH;
const proxyServer = process.env.OZON_PROXY_SERVER;

if (!profilePath) {
  console.error('OZON_PROFILE_PATH is required.');
  process.exit(2);
}

const launchOptions = {
  headless: false,
  locale: 'ru-RU',
  viewport: { width: 1440, height: 900 },
  args: [
    '--no-sandbox',
    '--disable-dev-shm-usage',
    '--disable-blink-features=AutomationControlled',
  ],
};

if (proxyServer) {
  launchOptions.proxy = {
    server: proxyServer,
  };

  if (process.env.OZON_PROXY_USERNAME) {
    launchOptions.proxy.username = process.env.OZON_PROXY_USERNAME;
  }

  if (process.env.OZON_PROXY_PASSWORD) {
    launchOptions.proxy.password = process.env.OZON_PROXY_PASSWORD;
  }
}

await mkdir(profilePath, { recursive: true });
await Promise.all(['SingletonLock', 'SingletonSocket', 'SingletonCookie'].map((file) => (
  rm(`${profilePath}/${file}`, { force: true })
)));

const context = await chromium.launchPersistentContext(profilePath, launchOptions);

try {
  const page = await context.newPage();

  await page.goto('https://www.ozon.ru/my/favorites', {
    waitUntil: 'domcontentloaded',
    timeout: 90000,
  });

  await new Promise((resolve) => {
    process.once('SIGINT', resolve);
    process.once('SIGTERM', resolve);
  });
} catch (error) {
  console.error(error instanceof Error ? error.message : String(error));
  process.exitCode = 1;
} finally {
  await context.close();
}
