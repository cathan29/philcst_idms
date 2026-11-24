import { chromium } from 'file:///C:/Users/Cathan/AppData/Local/Temp/idms-playwright-runtime/node_modules/playwright/index.mjs';
import fs from 'node:fs';
import path from 'node:path';

const base = process.env.IDMS_PHASE5_URL ?? 'http://127.0.0.1:8010';
const email = process.env.IDMS_BROWSER_EMAIL;
const password = process.env.IDMS_BROWSER_PASSWORD;
if (!email || !password) throw new Error('Phase 5 browser credentials are required.');

const root = process.cwd();
const screenshotDir = path.join(root, 'docs', 'audit', 'screenshots', 'phase5');
fs.mkdirSync(screenshotDir, { recursive: true });

const browser = await chromium.launch({ headless: true, channel: 'chrome' });
const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
const page = await context.newPage();
const checks = [];
const consoleErrors = [];
const pageErrors = [];
page.on('console', message => { if (message.type() === 'error') consoleErrors.push(message.text()); });
page.on('pageerror', error => pageErrors.push(error.message));
const check = (passed, name, detail = '') => {
    checks.push({ name, passed: Boolean(passed), detail });
    if (!passed) throw new Error(name + (detail ? ': ' + detail : ''));
};

await page.goto(base + '/login', { waitUntil: 'networkidle' });
await page.screenshot({ path: path.join(screenshotDir, 'login-desktop.png'), fullPage: true });
await page.getByLabel('Email Address').fill(email);
await page.locator('#password').fill(password);
await page.getByRole('button', { name: /sign in/i }).click();
await page.waitForURL(base + '/');
check(page.url() === base + '/', 'Authorized isolated UAT login');

const pages = [
    ['dashboard', '/'],
    ['student-records', '/students'],
    ['repro-production', '/production'],
    ['released-ids', '/production?status=released'],
    ['verification', '/scanner'],
    ['reports', '/reports'],
    ['users', '/users'],
    ['academic-settings', '/academic-terms'],
    ['audit-logs', '/logs'],
];

for (const [name, url] of pages) {
    const response = await page.goto(base + url, { waitUntil: 'networkidle' });
    check(response?.status() === 200, name + ' returns HTTP 200', String(response?.status()));
    const state = await page.evaluate(() => ({
        overflow: document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
        unlabeledControls: [...document.querySelectorAll('button,input,select,textarea')].filter(element => {
            if (element.type === 'hidden') return false;
            if (element.matches('input[type="checkbox"],input[type="radio"]') && element.closest('label')) return false;
            return !element.getAttribute('aria-label') && !element.getAttribute('title') &&
                !element.labels?.length && !(element.textContent || '').trim();
        }).length,
        missingAlt: document.querySelectorAll('img:not([alt])').length,
    }));
    check(!state.overflow, name + ' has no desktop document overflow');
    check(state.unlabeledControls === 0, name + ' has no unnamed controls', String(state.unlabeledControls));
    check(state.missingAlt === 0, name + ' images have alternative text');
    await page.screenshot({ path: path.join(screenshotDir, name + '-desktop.png'), fullPage: true });
}

await page.goto(base + '/scanner', { waitUntil: 'networkidle' });
await page.locator('#scanPayload').fill('00001001');
await page.locator('#scanPayload').press('Enter');
await page.waitForFunction(() => document.querySelector('#scanPayload')?.value === '');
await page.getByText('00001001', { exact: true }).first().waitFor();
check(await page.locator('#scanPayload').inputValue() === '', 'Manual verification clears after Enter');
check(await page.locator('#scanPayload').evaluate(element => element === document.activeElement), 'Manual verification restores keyboard focus');

const viewports = [
    ['desktop-wide', 1920, 1080],
    ['laptop', 1366, 768],
    ['tablet-landscape', 1024, 768],
    ['tablet-portrait', 768, 1024],
    ['mobile', 390, 844],
];

for (const [label, width, height] of viewports) {
    await page.setViewportSize({ width, height });
    for (const [name, url] of [['dashboard', '/'], ['student-records', '/students'], ['verification', '/scanner'], ['reports', '/reports']]) {
        await page.goto(base + url, { waitUntil: 'networkidle' });
        const overflow = await page.evaluate(() => ({
            page: document.body.scrollWidth > document.documentElement.clientWidth + 1,
            offenders: [...document.querySelectorAll('body *')]
                .filter(element => element.getBoundingClientRect().right > document.documentElement.clientWidth + 1)
                .slice(0, 5)
                .map(element => element.tagName.toLowerCase() + (element.className ? '.' + String(element.className).trim().replace(/\s+/g, '.') : '')),
        }));
        check(!overflow.page, name + ' has no ' + label + ' document overflow', overflow.offenders.join(', '));
        if (['dashboard', 'mobile'].includes(name) || label === 'mobile') {
            await page.screenshot({ path: path.join(screenshotDir, name + '-' + label + '.png'), fullPage: true });
        }
    }
}

await page.setViewportSize({ width: 390, height: 844 });
await page.goto(base + '/', { waitUntil: 'networkidle' });
await page.locator('#mobileMenuButton').click();
await page.waitForFunction(() => {
    const rect = document.querySelector('#appSidebar')?.getBoundingClientRect();
    return rect && rect.left >= -1 && rect.right > 0;
});
check(await page.locator('#appSidebar').evaluate(element => {
    const rect = element.getBoundingClientRect();
    return rect.left >= -1 && rect.right > 0;
}), 'Mobile navigation opens into the viewport');
await page.screenshot({ path: path.join(screenshotDir, 'mobile-navigation-open.png'), fullPage: true });

check(consoleErrors.length === 0, 'No browser console errors', consoleErrors.join(' | '));
check(pageErrors.length === 0, 'No browser page errors', pageErrors.join(' | '));

const result = {
    generated_at: new Date().toISOString(),
    environment: 'isolated SQLite UAT',
    browser: 'Playwright Chromium',
    base_url: base,
    passed: checks.filter(item => item.passed).length,
    total: checks.length,
    checks,
    console_errors: consoleErrors,
    page_errors: pageErrors,
};
fs.writeFileSync(path.join(root, 'docs', 'audit', 'phase-5-browser-results.json'), JSON.stringify(result, null, 2));
await browser.close();
console.log(JSON.stringify({ passed: result.passed, total: result.total }, null, 2));
