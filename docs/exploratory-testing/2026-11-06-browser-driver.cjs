const { chromium } = require('./driver/node_modules/playwright');
const { spawn } = require('child_process');
const assert = require('assert');
const fs = require('fs');

const mode = process.argv[2];
const port = 39874;
const url = `http://127.0.0.1:${port}`;
let server;
let browser;
let closing = false;

async function cleanup() {
  if (closing) return;
  closing = true;
  if (browser) await browser.close().catch(() => {});
  if (server && server.exitCode === null) {
    server.kill('SIGTERM');
    await new Promise(resolve => server.once('exit', resolve));
  }
  console.log('cleanup: browser closed and server stopped');
}
process.on('SIGTERM', () => cleanup().finally(() => process.exit(143)));
process.on('SIGINT', () => cleanup().finally(() => process.exit(130)));

(async () => {
  server = spawn('php', ['-d', 'display_errors=1', '-S', `127.0.0.1:${port}`, '-t', 'public', 'public/index.php'], {stdio: ['ignore', 'ignore', fs.openSync('.scratch/php-server.log', 'a')]});
  console.log(`mode=${mode} server_pid=${server.pid} port=${port}`);
  for (let i = 0; i < 100; i++) {
    if (server.exitCode !== null) throw new Error('PHP server exited before readiness');
    try { if ((await fetch(url)).ok) break; } catch (_) {}
    if (i === 99) throw new Error('PHP server not ready');
    await new Promise(r => setTimeout(r, 100));
  }
  browser = await chromium.launch({headless: true, args: ['--no-sandbox']});
  console.log(`chromium=${browser.version()}`);
  const context = await browser.newContext();
  const page = await context.newPage();
  const errors = [];
  const scripts = [];
  page.on('pageerror', e => errors.push(e.message));
  page.on('console', m => { if (m.type() === 'error') errors.push(m.text()); });
  page.on('response', r => { if (r.url().includes('/build/')) scripts.push(`${new URL(r.url()).pathname}:${r.status()}`); });
  const response = await page.goto(url, {waitUntil: 'load'});
  assert.equal(response.status(), 200);
  assert.equal(await page.locator('h1').innerText(), 'It works!');
  await page.waitForFunction(() => typeof window.Alpine !== 'undefined');
  const alpine = await page.evaluate(() => typeof window.Alpine);
  const cookieNamesBefore = (await context.cookies()).map(c => c.name).sort();
  const cookieValuesBefore = Object.fromEntries((await context.cookies()).map(c => [c.name, c.value]));
  if (mode === 'custom') {
    await page.waitForFunction(() => document.documentElement.dataset.exploratoryEntry === 'executed');
    await page.waitForFunction(() => document.querySelector('#counter').textContent.trim() === '0');
    await page.locator('#counter').click();
    await page.waitForFunction(() => document.querySelector('#counter').textContent.trim() === '1');
    console.log('custom entrypoint executed; Alpine counter 0 -> 1');
  }
  await page.reload({waitUntil: 'load'});
  assert.equal(await page.locator('h1').innerText(), 'It works!');
  const cookiesAfter = Object.fromEntries((await context.cookies()).map(c => [c.name, c.value]));
  assert.deepEqual(cookiesAfter, cookieValuesBefore);
  if (mode === 'custom') {
    await page.waitForFunction(() => document.documentElement.dataset.exploratoryEntry === 'executed');
    await page.waitForFunction(() => document.querySelector('#counter').textContent.trim() === '0');
    console.log('reload: custom entrypoint executed again; Alpine counter reset to 0');
  }
  assert.deepEqual(errors, []);
  assert(scripts.length >= 2 && scripts.every(s => s === '/build/app.js:200'));
  console.log(`page=200 heading=It works! Alpine=${alpine} assets=${scripts.join(',')} errors=${errors.length}`);
  console.log(`cookie names=${cookieNamesBefore.join(',')} values unchanged after reload`);
  await context.close();
})().catch(e => { console.error(e.stack); process.exitCode = 1; }).finally(cleanup);
