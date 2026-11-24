import { chromium } from 'file:///C:/Users/Cathan/AppData/Local/Temp/idms-playwright-runtime/node_modules/playwright/index.mjs';
import fs from 'node:fs';
import path from 'node:path';

const base = 'http://127.0.0.1:8000';
const root = process.cwd();
const auditEmail = process.env.IDMS_BROWSER_EMAIL;
const auditPassword = process.env.IDMS_BROWSER_PASSWORD;
if (!auditEmail || !auditPassword) throw new Error('Set IDMS_BROWSER_EMAIL and IDMS_BROWSER_PASSWORD for an authorized local test account.');
const screenshotDir = path.join(root, 'docs', 'audit', 'screenshots');
fs.mkdirSync(screenshotDir, { recursive: true });

const browser = await chromium.launch({ headless: true, channel: 'chrome' });
const context = await browser.newContext({ viewport: { width: 1440, height: 900 }, acceptDownloads: true });
const page = await context.newPage();
const consoleErrors = [];
const pageErrors = [];
page.on('console', message => { if (message.type() === 'error') consoleErrors.push(message.text()); });
page.on('pageerror', error => pageErrors.push(error.message));

const checks = [];
const check = (condition, name, detail = '') => {
    checks.push({ name, passed: Boolean(condition), detail });
    if (!condition) throw new Error(name + (detail ? ': ' + detail : ''));
};

await page.goto(base + '/login', { waitUntil: 'networkidle' });
await page.screenshot({ path: path.join(screenshotDir, 'panel-final-login-desktop.png'), fullPage: true });
await page.getByLabel('Email Address').fill(auditEmail);
await page.locator('#password').fill(auditPassword);
await page.getByRole('button', { name: /sign in/i }).click();
await page.waitForTimeout(1200);
if (page.url() !== base + '/') { console.log(JSON.stringify({ url: page.url(), body: (await page.locator('body').innerText()).slice(0, 1200) }, null, 2)); throw new Error('UAT login did not complete'); }
check(page.url() === base + '/', 'Authorized UAT login');
if (process.env.PHASE3_CAPTURE_REPORT_ONLY === '1') {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto(base + '/reports', { waitUntil: 'networkidle' });
    const mobileReport = await page.evaluate(() => {
        const heading = document.querySelector('.production-queue > .panel-heading');
        const actions = document.querySelector('.report-actions');
        const finalMetric = document.querySelector('.report-summary > div:last-child');
        const hint = document.querySelector('.report-table-hint');
        const region = document.querySelector('[aria-label="ID issuance records"]');

        return {
            overflow: document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
            headingDirection: getComputedStyle(heading).flexDirection,
            actionColumns: getComputedStyle(actions).gridTemplateColumns.split(' ').length,
            finalMetricColumn: getComputedStyle(finalMetric).gridColumnEnd,
            hintVisible: hint ? getComputedStyle(hint).display !== 'none' : null,
            regionFocusable: region?.tabIndex === 0,
        };
    });
    check(!mobileReport.overflow, 'Fixed mobile report has no document overflow');
    check(mobileReport.headingDirection === 'column', 'Mobile report heading uses full-width stacked layout');
    check(mobileReport.actionColumns === 3, 'Mobile report actions share one full-width row');
    check(mobileReport.finalMetricColumn === 'auto', 'Six report metrics fill the two-column mobile grid');
    await page.screenshot({ path: path.join(screenshotDir, 'phase3-reports-mobile.png'), fullPage: true });

    console.log(JSON.stringify({ passed: checks.filter(item => item.passed).length, total: checks.length, checks, mobileReport }, null, 2));
    await browser.close();
    process.exit(0);
}
await page.screenshot({ path: path.join(screenshotDir, 'phase3-dashboard-desktop.png'), fullPage: true });

const pages = [
    ['dashboard', '/'],
    ['student-records', '/students'],
    ['repro-table', '/production'],
    ['printed-ids', '/production?status=printed'],
    ['released-ids', '/production?status=released'],
    ['verification', '/scanner'],
    ['reports', '/reports'],
    ['user-management', '/users'],
    ['settings', '/academic-terms'],
    ['audit-logs', '/logs'],
];

for (const [name, url] of pages) {
    const response = await page.goto(base + url, { waitUntil: 'networkidle' });
    check(response?.status() === 200, name + ' page returns 200', String(response?.status()));
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth + 1);
    check(!overflow, name + ' has no desktop document overflow');
    await page.screenshot({ path: path.join(screenshotDir, 'phase3-' + name + '-desktop.png'), fullPage: true });
}

await page.goto(base + '/scanner', { waitUntil: 'networkidle' });
await page.locator('#scanPayload').fill('UAT-PANEL-0001');
await page.locator('#scanPayload').press('Enter');
await page.locator('#scannerResult').getByText('UAT-PANEL-0001', { exact: true }).waitFor();
check(await page.locator('#scanPayload').inputValue() === '', 'Manual student-number entry clears after verification');
check(await page.locator('#scanPayload').evaluate(element => element === document.activeElement), 'Manual entry refocuses for continuous scanning');
await page.screenshot({ path: path.join(screenshotDir, 'phase3-manual-student-number.png'), fullPage: true });
await page.locator('#cameraScanner').evaluate(element => { element.open = true; });
await page.locator('#qrImageInput').setInputFiles(path.join(root, 'public', 'images', 'philcst-seal.png'));
await page.getByText('No readable QR code was found in that image. Choose another image or enter the identifier.').waitFor();
check(true, 'Invalid image QR error state');

await page.locator('#qrImageInput').setInputFiles(path.join(root, 'docs', 'audit', 'phase3-unknown-qr.png'));
await page.locator('#scannerResult').getByRole('heading', { name: 'ID not found' }).waitFor();
check(await page.locator('#scannerResult').getByText('No matching student or card record was found.').isVisible(), 'Unknown QR reaches database-backed not-found state');

await page.locator('#qrImageInput').setInputFiles(path.join(root, 'docs', 'audit', 'phase3-valid-qr.png'));
await page.locator('#scannerResult').getByText('UAT-PANEL-0001', { exact: true }).waitFor();
check(await page.locator('.status-ready_for_release').isVisible(), 'Actual QR image decodes valid card and displays lifecycle status');
await page.screenshot({ path: path.join(screenshotDir, 'phase3-actual-qr-valid.png'), fullPage: true });

await page.locator('#qrImageInput').setInputFiles(path.join(root, 'docs', 'audit', 'phase3-valid-qr.png'));
await page.locator('#scannerResult').getByText('Duplicate scan recorded. No action was taken.').waitFor();
check(true, 'Rapid duplicate QR scan is reported without automatic status change');

await page.locator('button[data-mode="releasing"]').click();
await page.locator('#qrImageInput').setInputFiles(path.join(root, 'docs', 'audit', 'phase3-valid-qr.png'));
await page.locator('.scanner-action[data-target="released"]').click();
await page.locator('#releaseDialog').waitFor({ state: 'visible' });
await page.locator('#claimantName').fill('Authorized UAT Claimant');
await page.locator('#claimantRelationship').fill('Self');
await page.locator('#releaseRemarks').fill('Synthetic browser UAT.');
await page.locator('#releaseAcknowledged').check();
await page.getByRole('button', { name: 'Release ID' }).click();
await page.locator('.status-released').waitFor();
check(await page.getByText('Released', { exact: true }).first().isVisible(), 'Release acknowledgement persists lifecycle transition');
await page.screenshot({ path: path.join(screenshotDir, 'phase3-release-acknowledgement.png'), fullPage: true });

await page.locator('#cameraScanner').evaluate(element => { element.open = true; });
await page.getByRole('button', { name: 'Start camera' }).click();
await page.waitForTimeout(3000);
const cameraStatus = await page.locator('#cameraStatus').innerText();
if (/No camera|denied|could not be started/.test(cameraStatus)) check(true, 'Camera-unavailable error remains recoverable', cameraStatus); else checks.push({ name: 'Physical camera permission and preview', passed: false, detail: 'Headless Chrome remained at: ' + cameraStatus });

const viewports = [
    ['laptop', 1280, 800],
    ['tablet', 768, 1024],
    ['mobile', 390, 844],
];
for (const [label, width, height] of viewports) {
    await page.setViewportSize({ width, height });
    for (const [name, url] of [['dashboard', '/'], ['scanner', '/scanner'], ['reports', '/reports']]) {
        await page.goto(base + url, { waitUntil: 'networkidle' });
        const metrics = await page.evaluate(() => ({
            overflow: document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
            missingAlt: document.querySelectorAll('img:not([alt])').length,
            unnamedButtons: [...document.querySelectorAll('button')].filter(button => !(button.textContent || '').trim() && !button.getAttribute('aria-label') && !button.getAttribute('title')).length,
        }));
        check(!metrics.overflow, name + ' has no ' + label + ' document overflow');
        check(metrics.missingAlt === 0, name + ' has image alternative text at ' + label);
        check(metrics.unnamedButtons === 0, name + ' has named buttons at ' + label);
        await page.screenshot({ path: path.join(screenshotDir, 'phase3-' + name + '-' + label + '.png'), fullPage: true });
    }
}

await page.setViewportSize({ width: 1440, height: 900 });
await page.goto(base + '/reports?report_type=released_cards', { waitUntil: 'networkidle' });
check(await page.getByText('UAT-PANEL-0001', { exact: true }).isVisible(), 'Released report reflects scanner lifecycle change');
const printResponse = await page.goto(base + '/reports/print?report_type=released_cards', { waitUntil: 'networkidle' });
check(printResponse?.status() === 200 && await page.getByText('PHILCST Operational Report').isVisible(), 'Print report renders filtered PHILCST output');

check(pageErrors.length === 0, 'No browser page errors', pageErrors.join(' | '));
check(consoleErrors.length === 0, 'No browser console errors', consoleErrors.join(' | '));

const result = {
    generated_at: new Date().toISOString(),
    browser: 'Playwright Chromium headless',
    base_url: base,
    checks,
    console_errors: consoleErrors,
    page_errors: pageErrors,
};
fs.writeFileSync(path.join(root, 'docs', 'audit', 'phase-3-browser-results.json'), JSON.stringify(result, null, 2));
await browser.close();
console.log(JSON.stringify({ passed: checks.filter(item => item.passed).length, total: checks.length }, null, 2));
