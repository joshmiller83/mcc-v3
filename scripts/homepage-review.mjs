#!/usr/bin/env node
/**
 * @file
 * Screenshots the front page at desktop and phone widths, anonymously and
 * logged in, and asserts the announcements band is healthy.
 *
 * Usage:
 *   node scripts/homepage-review.mjs
 *   node scripts/homepage-review.mjs --login "$(ddev drush uli --uri=http://127.0.0.1)"
 *
 * The logged-in pass matters for the same reason as in ministries-review.mjs:
 * an editor's `.contextual-region` wrappers are positioned, and a layout that
 * is fine for an anonymous visitor can collapse or overlap for them. curl and
 * a headless browser are both anonymous unless told otherwise.
 *
 * Asserts, per shot: HTTP 200, no horizontal scroll, the band is present, and
 * every flyer image is loaded, taller than a sliver, inside its own card, and
 * not overlapping another card. Runs on the Codespace host (no ddev); output
 * lands in the gitignored .homepage-review/.
 */

import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');

const opts = { base: 'http://127.0.0.1', out: '.homepage-review', login: null };
for (let i = 2; i < process.argv.length; i++) {
  const arg = process.argv[i];
  if (arg === '--base') opts.base = process.argv[++i];
  else if (arg === '--out') opts.out = process.argv[++i];
  else if (arg === '--login') opts.login = process.argv[++i];
}

const OUT = path.resolve(ROOT, opts.out);
fs.mkdirSync(OUT, { recursive: true });

const WIDTHS = [
  ['d', 1280],
  ['m', 390],
];

const browser = await chromium.launch();
const report = [];

async function shoot(context, label) {
  const page = await context.newPage();
  for (const [suffix, width] of WIDTHS) {
    await page.setViewportSize({ width, height: 1000 });
    const response = await page.goto(opts.base + '/', { waitUntil: 'networkidle' });
    const status = response?.status() ?? 0;

    await page.screenshot({ path: path.join(OUT, `${label}-front-${suffix}.png`), fullPage: true });
    const band = page.locator('#home-announcements');
    if (await band.count()) {
      // Scroll the band into view first: the pictures are lazy-loaded, and on
      // a phone the band starts below the fold, so measuring them straight
      // after load reports an image that simply has not been fetched yet.
      await band.scrollIntoViewIfNeeded();
      for (const card of await page.locator('#home-announcements .mcc-announcement-card').all()) {
        await card.scrollIntoViewIfNeeded();
      }
      await page.waitForFunction(
        () => [...document.querySelectorAll('#home-announcements .mcc-announcement-card__image img')]
          .every((img) => img.complete && img.naturalWidth > 0),
        null,
        { timeout: 15000 },
      ).catch(() => {});
      await band.scrollIntoViewIfNeeded();
      await band.screenshot({ path: path.join(OUT, `${label}-band-${suffix}.png`) });
    }

    const metrics = await page.evaluate(() => {
      const band = document.querySelector('#home-announcements');
      const cards = [...document.querySelectorAll('#home-announcements .mcc-announcement-card')];
      const rects = cards.map((c) => c.getBoundingClientRect());
      const intersects = (a, b) =>
        a.left < b.right - 1 && b.left < a.right - 1 && a.top < b.bottom - 1 && b.top < a.bottom - 1;
      let overlapping = 0;
      for (let i = 0; i < rects.length; i++) {
        for (let j = i + 1; j < rects.length; j++) {
          if (intersects(rects[i], rects[j])) overlapping++;
        }
      }
      const images = cards.map((c) => c.querySelector('.mcc-announcement-card__image img'));
      let collapsed = 0;
      let spilled = 0;
      images.forEach((img, i) => {
        if (!img) return;
        const r = img.getBoundingClientRect();
        if (!img.complete || img.naturalWidth === 0 || r.height < 50) collapsed++;
        const c = rects[i];
        if (r.left < c.left - 1 || r.right > c.right + 1 || r.top < c.top - 1 || r.bottom > c.bottom + 1) spilled++;
      });
      return {
        overflow: document.documentElement.scrollWidth > document.documentElement.clientWidth,
        band: !!band,
        cards: cards.length,
        images: images.filter(Boolean).length,
        anchors: document.querySelectorAll('#home-announcements .mcc-announcement-card__link').length,
        nested: document.querySelectorAll('#home-announcements a a').length,
        collapsed,
        spilled,
        overlapping,
        columns: new Set(rects.map((r) => Math.round(r.left))).size,
        // The scroll row must be sized by the band, never by its own cards:
        // a wider row pushes the band's content track past the viewport and
        // the heading above it is clipped, with document scrollWidth
        // reporting nothing wrong.
        rowOverflow: (() => {
          const row = document.querySelector('#home-announcements .view-content');
          return row ? row.clientWidth > document.documentElement.clientWidth : false;
        })(),
      };
    });

    report.push({ label, width, status, ...metrics });
  }
  await page.close();
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
  if (row.rowOverflow) problems.push('row wider than the viewport');
  if (!row.band) problems.push('no #home-announcements band');
  if (row.cards === 0) problems.push('no cards');
  if (row.images !== row.cards) problems.push(`${row.cards - row.images} card(s) without a picture`);
  if (row.collapsed) problems.push(`${row.collapsed} collapsed image(s)`);
  if (row.spilled) problems.push(`${row.spilled} image(s) outside their card`);
  if (row.overlapping) problems.push(`${row.overlapping} overlapping card pair(s)`);
  if (row.nested) problems.push(`${row.nested} nested anchor(s)`);
  if (problems.length) failed = true;
  console.log(
    `${row.label.padEnd(4)} ${String(row.width).padStart(4)}  ` +
    `cards=${row.cards} linked=${row.anchors} columns=${row.columns}  ` +
    (problems.length ? 'FAIL: ' + problems.join(', ') : 'ok')
  );
}

console.log(`\nScreenshots in ${path.relative(ROOT, OUT)}/`);
process.exit(failed ? 1 : 0);
