#!/usr/bin/env node
/**
 * @file
 * Screenshots the ministries listing and a ministry detail page at three
 * widths, anonymously and logged in.
 *
 * Usage:
 *   node scripts/ministries-review.mjs
 *   node scripts/ministries-review.mjs --login "$(ddev drush uli --uri=http://127.0.0.1)"
 *
 * The logged-in pass is not optional politeness. For a user with "access
 * contextual links" Drupal wraps every rendered entity in `.contextual-region`,
 * which contextual.module gives `position: relative` — and that wrapper becomes
 * the containing block for anything absolutely positioned inside it. That is
 * what collapsed every bio portrait to 0px tall for editors while the anonymous
 * page looked perfect. curl and headless screenshots are both anonymous by
 * default, so this class of bug is invisible without an explicit login.
 *
 * Runs on the Codespace host (no ddev); output lands in the gitignored
 * .ministries-review/.
 */

import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');

const opts = { base: 'http://127.0.0.1', out: '.ministries-review', login: null };
for (let i = 2; i < process.argv.length; i++) {
  const arg = process.argv[i];
  if (arg === '--base') opts.base = process.argv[++i];
  else if (arg === '--out') opts.out = process.argv[++i];
  else if (arg === '--login') opts.login = process.argv[++i];
}

const OUT = path.resolve(ROOT, opts.out);
fs.mkdirSync(OUT, { recursive: true });

const ROUTES = [
  ['listing', '/ministries'],
  ['detail', '/ministries/womens'],
  ['detail-partner', '/ministries/missions'],
];

const WIDTHS = [
  ['d', 1280],
  ['t', 820],
  ['m', 390],
];

const browser = await chromium.launch();
const report = [];

async function shoot(context, label) {
  for (const [name, route] of ROUTES) {
    const page = await context.newPage();
    for (const [suffix, width] of WIDTHS) {
      await page.setViewportSize({ width, height: 1000 });
      const response = await page.goto(opts.base + route, { waitUntil: 'networkidle' });
      const status = response?.status() ?? 0;

      const file = path.join(OUT, `${label}-${name}-${suffix}.png`);
      await page.screenshot({ path: file, fullPage: true });

      // Two things worth asserting rather than eyeballing: nothing scrolls
      // sideways, and no card lost its icon tile.
      const metrics = await page.evaluate(() => ({
        overflow: document.documentElement.scrollWidth > document.documentElement.clientWidth,
        cards: document.querySelectorAll('.mcc-ministry-card').length,
        icons: document.querySelectorAll('.mcc-ministry-card .lucide').length,
        zeroHeight: [...document.querySelectorAll('.mcc-ministry-card__tile, .mcc-ministry-card__logo, .mcc-ministry-sidebar__portrait')]
          .filter((el) => el.getBoundingClientRect().height === 0).length,
      }));

      report.push({ label, route, width, status, ...metrics });
    }
    await page.close();
  }
}

const anon = await browser.newContext();
await shoot(anon, 'anon');
await anon.close();

if (opts.login) {
  const authed = await browser.newContext();
  const page = await authed.newPage();
  await page.goto(opts.login, { waitUntil: 'networkidle' });
  await page.close();
  await shoot(authed, 'auth');
  await authed.close();
} else {
  console.warn('No --login given: the contextual-region layout trap was NOT checked.');
}

await browser.close();

let failed = false;
for (const row of report) {
  const problems = [];
  if (row.status !== 200) problems.push(`HTTP ${row.status}`);
  if (row.overflow) problems.push('horizontal overflow');
  if (row.zeroHeight) problems.push(`${row.zeroHeight} collapsed tile(s)`);
  if (row.cards && row.icons < row.cards - 2) problems.push(`only ${row.icons}/${row.cards} icons`);
  if (problems.length) failed = true;
  console.log(
    `${row.label.padEnd(4)} ${row.route.padEnd(22)} ${String(row.width).padStart(4)}  ` +
    `cards=${String(row.cards).padStart(2)} icons=${String(row.icons).padStart(2)}  ` +
    (problems.length ? 'FAIL: ' + problems.join(', ') : 'ok')
  );
}

console.log(`\nScreenshots in ${path.relative(ROOT, OUT)}/`);
process.exit(failed ? 1 : 0);
