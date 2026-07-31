#!/usr/bin/env node
/**
 * @file
 * Renders the About design reference and the live /about page side by side and
 * reports the geometry of the hero region, where the two most visibly diverge.
 *
 * Usage:
 *   node scripts/about-compare.mjs
 *   node scripts/about-compare.mjs --base http://127.0.0.1 --path /about
 */

import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');

const opts = { base: 'http://127.0.0.1', route: '/about', out: '.about-review' };
for (let i = 2; i < process.argv.length; i++) {
  const arg = process.argv[i];
  if (arg === '--base') opts.base = process.argv[++i];
  else if (arg === '--path') opts.route = process.argv[++i];
  else if (arg === '--out') opts.out = process.argv[++i];
}

const OUT = path.resolve(ROOT, opts.out);
fs.mkdirSync(OUT, { recursive: true });

const REFERENCE = path.join(
  ROOT,
  '.about-design/design_handoff_about/MCC About.dc.html',
);

/**
 * Walks the top of the document and records each top-level band's box, so the two
 * pages can be compared by what occupies the first screenful rather than by
 * selectors (which share no vocabulary).
 */
const PROBE = () => {
  // Both documents express a band as a top-level <section> (plus the sticky
  // <header>), but they nest them at different depths — the prototype under a
  // couple of custom-element wrappers, Drupal under main.layout-container. Key
  // off the tag and drop any section that sits inside another one.
  const seen = [];
  for (const el of document.querySelectorAll('header, section')) {
    if (el.parentElement.closest('section')) continue;
    const r = el.getBoundingClientRect();
    if (r.height < 4) continue;
    const cs = getComputedStyle(el);
    seen.push({
      tag: el.tagName.toLowerCase(),
      cls: (el.className || '').toString().slice(0, 60),
      top: Math.round(r.top + window.scrollY),
      height: Math.round(r.height),
      bg: cs.backgroundColor,
      padTop: cs.paddingTop,
      padBottom: cs.paddingBottom,
    });
  }

  const h1 = document.querySelector('h1');
  const h1r = h1 && h1.getBoundingClientRect();
  const crumb = document.querySelector('[aria-label="breadcrumb"], nav.breadcrumb, .breadcrumb');
  const crumbr = crumb && crumb.getBoundingClientRect();
  const header = document.querySelector('header');
  const headerr = header && header.getBoundingClientRect();

  return {
    bands: seen,
    h1: h1
      ? {
          text: h1.textContent.trim().slice(0, 60),
          top: Math.round(h1r.top + window.scrollY),
          bottom: Math.round(h1r.bottom + window.scrollY),
          fontSize: getComputedStyle(h1).fontSize,
          color: getComputedStyle(h1).color,
          textAlign: getComputedStyle(h1).textAlign,
          fontFamily: getComputedStyle(h1).fontFamily.split(',')[0],
        }
      : null,
    breadcrumb: crumb
      ? {
          top: Math.round(crumbr.top + window.scrollY),
          height: Math.round(crumbr.height),
          color: getComputedStyle(crumb).color,
          insideHero: !!crumb.closest('section,div[style*="min-height"]'),
        }
      : null,
    header: headerr ? { height: Math.round(headerr.height) } : null,
    docHeight: document.documentElement.scrollHeight,
  };
};

// The Codespace image ships its own Chromium build, which rarely matches the
// revision this playwright release wants; prefer whatever is actually on disk.
const CHROME_CANDIDATES = [
  process.env.CHROME_PATH,
  ...(fs.globSync?.(`${process.env.HOME}/.cache/ms-playwright/chromium-*/chrome-linux64/chrome`) ?? []),
  ...(fs.globSync?.(`${process.env.HOME}/.cache/ms-playwright/chromium-*/chrome-linux/chrome`) ?? []),
  '/usr/bin/chromium',
].filter(Boolean);
const executablePath = CHROME_CANDIDATES.find((c) => fs.existsSync(c));

const browser = await chromium.launch(executablePath ? { executablePath } : {});
const results = {};

for (const [name, url] of [
  ['reference', `file://${REFERENCE}`],
  ['live', `${opts.base}${opts.route}`],
]) {
  const page = await browser.newPage({
    viewport: { width: 1440, height: 900 },
    deviceScaleFactor: 1,
  });
  await page.goto(url, { waitUntil: 'networkidle', timeout: 60000 }).catch(() => {});
  await page.waitForTimeout(1200);
  results[name] = await page.evaluate(PROBE);
  await page.screenshot({ path: path.join(OUT, `${name}-hero.png`), clip: { x: 0, y: 0, width: 1440, height: 900 } });
  await page.screenshot({ path: path.join(OUT, `${name}-full.png`), fullPage: true });

  // A second screenful pinned to the vision/mission band — the two pages differ
  // most below the fold, and the band starts at a different offset in each.
  const bandTop = results[name].bands[2]?.top ?? 511;
  await page.evaluate((y) => window.scrollTo(0, y), bandTop);
  await page.waitForTimeout(300);
  await page.screenshot({ path: path.join(OUT, `${name}-mid.png`) });
  await page.close();
}

await browser.close();
fs.writeFileSync(path.join(OUT, 'geometry.json'), JSON.stringify(results, null, 2));

for (const name of ['reference', 'live']) {
  const r = results[name];
  console.log(`\n=== ${name.toUpperCase()} ===`);
  console.log(`header height: ${r.header?.height ?? 'n/a'}   doc height: ${r.docHeight}`);
  console.log(`h1: ${JSON.stringify(r.h1)}`);
  console.log(`breadcrumb: ${JSON.stringify(r.breadcrumb)}`);
  console.log('bands:');
  for (const b of r.bands) {
    console.log(
      `  ${b.tag}.${b.cls || '-'} top=${b.top} h=${b.height} bg=${b.bg} pad=${b.padTop}/${b.padBottom}`,
    );
  }
}
