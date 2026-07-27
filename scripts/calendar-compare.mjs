#!/usr/bin/env node
/**
 * @file
 * Compares the /calendar pages against the design handoff, and enforces the
 * print sheet's "one Letter page, nothing clipped" invariant.
 *
 * The handoff (calendar_design.zip) ships its prototypes as HTML, so the most
 * direct way to check fidelity is to render both the prototype and the real
 * Drupal page in the same browser at the same viewport and look at them side by
 * side. This script does that, and asserts the print invariants the handoff
 * asks us to keep ("Fit invariant — please keep this test").
 *
 * Usage (from the project root, with `ddev start` running):
 *
 *   node scripts/calendar-compare.mjs
 *   node scripts/calendar-compare.mjs --month 2026-08 --month 2026-11
 *   node scripts/calendar-compare.mjs --base http://127.0.0.1 --out .calendar-compare
 *
 * Output lands in .calendar-compare/ (gitignored): PNGs for each view, the
 * print PDFs, compare.html for eyeballing, and report.md. Exit status is
 * non-zero when a print assertion fails, so this doubles as a regression gate.
 *
 * Requirements: node >= 22 (built-in WebSocket) and a Chromium binary. No npm
 * install — the CDP client below is ~80 lines and needs no dependencies.
 */

import { spawn, spawnSync } from 'node:child_process';
import { createServer } from 'node:http';
import fs from 'node:fs';
import fsp from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');

/* ------------------------------------------------------------------ args -- */

function parseArgs(argv) {
  const opts = { base: 'http://127.0.0.1', out: '.calendar-compare', months: [], keep: false };
  for (let i = 0; i < argv.length; i++) {
    const arg = argv[i];
    if (arg === '--base') opts.base = argv[++i];
    else if (arg === '--out') opts.out = argv[++i];
    else if (arg === '--month') opts.months.push(argv[++i]);
    else if (arg === '--keep') opts.keep = true;
    else if (arg === '--help' || arg === '-h') opts.help = true;
    else throw new Error(`Unknown argument: ${arg}`);
  }
  // August 2026 is the handoff's stated worst case (6 weeks, and its busiest
  // Sunday sits under two multi-day lanes); July 2026 is a 5-week month.
  if (!opts.months.length) opts.months = ['2026-07', '2026-08'];
  return opts;
}

/* --------------------------------------------------------------- chromium -- */

const CHROME_CANDIDATES = [
  process.env.CHROME_PATH,
  ...fs.globSync?.(`${process.env.HOME}/.cache/ms-playwright/chromium-*/chrome-linux64/chrome`) ?? [],
  ...fs.globSync?.(`${process.env.HOME}/.cache/ms-playwright/chromium-*/chrome-linux/chrome`) ?? [],
  '/usr/bin/chromium',
  '/usr/bin/chromium-browser',
  '/usr/bin/google-chrome',
].filter(Boolean);

function findChrome() {
  for (const candidate of CHROME_CANDIDATES) {
    if (fs.existsSync(candidate)) return candidate;
  }
  throw new Error(
    'No Chromium found. Set CHROME_PATH, or install one with:\n' +
    '  npx --yes playwright@latest install chromium'
  );
}

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

async function launchChrome(userDataDir) {
  const bin = findChrome();
  const port = 9222 + (process.pid % 500);
  const child = spawn(bin, [
    '--headless=new',
    `--remote-debugging-port=${port}`,
    `--user-data-dir=${userDataDir}`,
    '--no-sandbox',
    '--disable-dev-shm-usage',
    '--disable-gpu',
    '--hide-scrollbars',
    '--force-color-profile=srgb',
    '--disable-lcd-text',
    '--no-first-run',
    '--no-default-browser-check',
    'about:blank',
  ], { stdio: ['ignore', 'ignore', 'pipe'] });
  // Chromium is noisy about dbus and GPU on a container; keep it, but only to
  // report back if the browser never comes up.
  let noise = '';
  child.stderr.on('data', (chunk) => { noise = (noise + chunk).slice(-2000); });

  // Cold start on a loaded Codespace has been seen to take upwards of ten
  // seconds, so this waits far longer than a healthy launch ever needs.
  for (let i = 0; i < 300; i++) {
    try {
      const res = await fetch(`http://127.0.0.1:${port}/json/version`);
      if (res.ok) return { child, wsUrl: (await res.json()).webSocketDebuggerUrl };
    } catch { /* not up yet */ }
    await sleep(100);
  }
  child.kill();
  throw new Error(`Chromium did not expose a DevTools endpoint on port ${port}.\n${noise}`);
}

/** Minimal flat-session CDP client. */
class Cdp {
  static async connect(wsUrl) {
    const ws = new WebSocket(wsUrl);
    await new Promise((resolve, reject) => {
      ws.onopen = resolve;
      ws.onerror = () => reject(new Error('CDP connection failed'));
    });
    return new Cdp(ws);
  }

  constructor(ws) {
    this.ws = ws;
    this.nextId = 1;
    this.pending = new Map();
    this.listeners = new Set();
    ws.onmessage = (event) => {
      const msg = JSON.parse(event.data);
      if (msg.id && this.pending.has(msg.id)) {
        const { resolve, reject } = this.pending.get(msg.id);
        this.pending.delete(msg.id);
        msg.error ? reject(new Error(`${msg.error.message} (${msg.method ?? ''})`)) : resolve(msg.result);
        return;
      }
      for (const listener of this.listeners) listener(msg);
    };
  }

  send(method, params = {}, sessionId) {
    const id = this.nextId++;
    this.ws.send(JSON.stringify({ id, method, params, ...(sessionId ? { sessionId } : {}) }));
    return new Promise((resolve, reject) => this.pending.set(id, { resolve, reject }));
  }

  /** Resolves on the first matching event, or rejects after `timeout` ms. */
  once(method, sessionId, timeout = 30000) {
    return new Promise((resolve, reject) => {
      const timer = setTimeout(() => {
        this.listeners.delete(listener);
        reject(new Error(`Timed out waiting for ${method}`));
      }, timeout);
      const listener = (msg) => {
        if (msg.method !== method || (sessionId && msg.sessionId !== sessionId)) return;
        clearTimeout(timer);
        this.listeners.delete(listener);
        resolve(msg.params);
      };
      this.listeners.add(listener);
    });
  }

  close() { this.ws.close(); }
}

/** A single tab, with the handful of operations this script needs. */
class Page {
  static async open(cdp, { width, height, scale = 2 }) {
    const { targetId } = await cdp.send('Target.createTarget', { url: 'about:blank' });
    const { sessionId } = await cdp.send('Target.attachToTarget', { targetId, flatten: true });
    const page = new Page(cdp, sessionId);
    await page.call('Page.enable');
    await page.call('Runtime.enable');
    await page.setViewport(width, height, scale);
    return page;
  }

  constructor(cdp, sessionId) {
    this.cdp = cdp;
    this.sessionId = sessionId;
  }

  call(method, params) { return this.cdp.send(method, params, this.sessionId); }

  setViewport(width, height, deviceScaleFactor = 2) {
    return this.call('Emulation.setDeviceMetricsOverride', {
      width, height, deviceScaleFactor, mobile: false,
    });
  }

  /** Runs `expression` in the page and returns its (JSON-serialisable) value. */
  async evaluate(expression) {
    const { result, exceptionDetails } = await this.call('Runtime.evaluate', {
      expression, returnByValue: true, awaitPromise: true,
    });
    if (exceptionDetails) {
      throw new Error(exceptionDetails.exception?.description ?? exceptionDetails.text);
    }
    return result.value;
  }

  /** Injects a script that runs before any page script, on every navigation. */
  addInitScript(source) {
    return this.call('Page.addScriptToEvaluateOnNewDocument', { source });
  }

  async goto(url, { settle = 900 } = {}) {
    const loaded = this.cdp.once('Page.loadEventFired', this.sessionId);
    await this.call('Page.navigate', { url });
    await loaded;
    // The design prototypes render from a client-side runtime, and both sides
    // load webfonts; give layout a beat and wait for the fonts to land.
    await this.evaluate('document.fonts ? document.fonts.ready.then(() => true) : true');
    await sleep(settle);
  }

  async screenshot(file, { fullPage = false } = {}) {
    const { data } = await this.call('Page.captureScreenshot', {
      format: 'png',
      captureBeyondViewport: fullPage,
      optimizeForSpeed: false,
    });
    await fsp.writeFile(file, Buffer.from(data, 'base64'));
    return file;
  }

  async pdf(file) {
    const { data } = await this.call('Page.printToPDF', {
      paperWidth: 8.5,
      paperHeight: 11,
      marginTop: 0, marginBottom: 0, marginLeft: 0, marginRight: 0,
      printBackground: true,
      preferCSSPageSize: true,
    });
    const buffer = Buffer.from(data, 'base64');
    await fsp.writeFile(file, buffer);
    return buffer;
  }

  emulatePrint(on = true) {
    return this.call('Emulation.setEmulatedMedia', { media: on ? 'print' : '' });
  }
}

/* ------------------------------------------------------- design reference -- */

const MIME = {
  '.html': 'text/html; charset=utf-8', '.css': 'text/css; charset=utf-8',
  '.js': 'text/javascript; charset=utf-8', '.mjs': 'text/javascript; charset=utf-8',
  '.svg': 'image/svg+xml', '.jpg': 'image/jpeg', '.jpeg': 'image/jpeg',
  '.png': 'image/png', '.woff2': 'font/woff2', '.ttf': 'font/ttf', '.json': 'application/json',
};

/**
 * Unpacks calendar_design.zip once into `dir`.
 *
 * Kept out of git: the zip is the artefact of record, this is just a cache.
 */
async function unpackDesign(dir) {
  const bundle = path.join(dir, 'design_handoff_church_calendar');
  if (!fs.existsSync(path.join(bundle, 'designs'))) {
    const zip = path.join(ROOT, 'calendar_design.zip');
    if (!fs.existsSync(zip)) throw new Error(`Missing ${zip}`);
    await fsp.mkdir(dir, { recursive: true });
    const unzip = spawnSync('unzip', ['-oq', zip, '-d', dir], { stdio: 'inherit' });
    if (unzip.status !== 0) throw new Error('unzip failed');
  }
  return bundle;
}

/**
 * Serves the design bundle.
 *
 * The prototypes link their tokens through a `_ds/<project-uuid>/…` path that
 * only exists inside the design tool, so rewrite that prefix onto the bundle
 * root. Anything genuinely absent (the design tool's component bundle) 404s,
 * which the prototypes survive.
 */
function serveDesign(bundle) {
  const designs = path.join(bundle, 'designs');
  const server = createServer(async (req, res) => {
    let rel = decodeURIComponent(new URL(req.url, 'http://x').pathname);
    const ds = rel.match(/\/_ds\/[^/]+\/(.*)$/);
    const file = ds ? path.join(bundle, ds[1]) : path.join(designs, rel);
    try {
      const body = await fsp.readFile(file);
      res.writeHead(200, { 'content-type': MIME[path.extname(file).toLowerCase()] ?? 'application/octet-stream' });
      res.end(body);
    } catch {
      res.writeHead(404).end('not found');
    }
  });
  return new Promise((resolve) => {
    server.listen(0, '127.0.0.1', () => resolve({ server, port: server.address().port }));
  });
}

// The bundle's @font-face rules point at font files it does not ship, so the
// prototype falls back to Georgia/system-ui and stops being comparable. Load
// the same webfonts the Drupal theme loads.
const WEBFONTS = `
  document.addEventListener('DOMContentLoaded', () => {
    const link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = 'https://fonts.googleapis.com/css2?family=Calistoga&family=Nunito:wght@300;400;500;600;700;800;900&display=swap';
    document.head.appendChild(link);
  });
`;

/* ------------------------------------------------------------- assertions -- */

/**
 * The handoff's fit invariant, verbatim in intent: nothing inside the sheet may
 * be scrolled/clipped, because a clipped cell silently drops an event from the
 * paper calendar the office hands out.
 */
const FIT_PROBE = (rootSelector) => `(() => {
  const root = document.querySelector(${JSON.stringify(rootSelector)});
  if (!root) return { error: 'no sheet element matching ${rootSelector}' };
  const overflowing = [];
  for (const el of [root, ...root.querySelectorAll('*')]) {
    const style = getComputedStyle(el);
    if (style.overflowY === 'visible' && style.overflowX === 'visible') continue;
    // Screen-reader-only text is a 1px box by design, and always "overflows".
    if (el.clientWidth <= 1 || el.clientHeight <= 1) continue;
    const dy = el.scrollHeight - el.clientHeight;
    const dx = el.scrollWidth - el.clientWidth;
    if (dy > 1 || dx > 1) {
      overflowing.push({
        selector: el.tagName.toLowerCase() + (el.className && typeof el.className === 'string'
          ? '.' + el.className.trim().split(/\\s+/).join('.') : ''),
        overflowY: dy, overflowX: dx,
        text: (el.textContent || '').trim().slice(0, 60),
      });
    }
  }
  const box = root.getBoundingClientRect();
  return {
    sheet: { width: +box.width.toFixed(1), height: +box.height.toFixed(1) },
    docHeight: document.documentElement.scrollHeight,
    overflowing: overflowing.slice(0, 8),
    overflowCount: overflowing.length,
  };
})()`;

/** Counts pages in a Chromium-generated PDF. */
function pdfPageCount(buffer) {
  const text = buffer.toString('latin1');
  const counts = [...text.matchAll(/\/Count\s+(\d+)/g)].map((m) => Number(m[1]));
  if (counts.length) return Math.max(...counts);
  return (text.match(/\/Type\s*\/Page[^s]/g) ?? []).length;
}

/* -------------------------------------------------------------- captures -- */

const SCREEN = { width: 1440, height: 1200 };
// 8.5in x 11in at 96 CSS px/in — the print sheet's own coordinate system.
const SHEET = { width: 816, height: 1056 };

async function captureDrupal(cdp, base, out, month, results) {
  const [year, mon] = month.split('-');
  const query = `year=${year}&month=${mon}`;

  const screen = await Page.open(cdp, SCREEN);
  await screen.goto(`${base}/calendar?${query}`);
  results.push({
    id: `drupal-screen-${month}`, side: 'drupal', view: 'screen', month,
    file: path.basename(await screen.screenshot(path.join(out, `drupal-screen-${month}.png`), { fullPage: true })),
  });

  const narrow = await Page.open(cdp, { width: 430, height: 1200, scale: 2 });
  await narrow.goto(`${base}/calendar?${query}`);
  results.push({
    id: `drupal-mobile-${month}`, side: 'drupal', view: 'mobile', month,
    file: path.basename(await narrow.screenshot(path.join(out, `drupal-mobile-${month}.png`), { fullPage: true })),
  });

  const print = await Page.open(cdp, { ...SHEET, scale: 2 });
  await print.goto(`${base}/calendar/print?${query}`);
  const entry = {
    id: `drupal-print-${month}`, side: 'drupal', view: 'print', month,
    file: path.basename(await print.screenshot(path.join(out, `drupal-print-${month}.png`))),
  };
  entry.fit = await print.evaluate(FIT_PROBE('.mcc-print-sheet'));
  await print.emulatePrint(true);
  entry.pages = pdfPageCount(await print.pdf(path.join(out, `drupal-print-${month}.pdf`)));
  entry.fitPrintMedia = await print.evaluate(FIT_PROBE('.mcc-print-sheet'));
  // What actually lands on paper, with the on-screen toolbar hidden.
  entry.file = path.basename(await print.screenshot(path.join(out, `drupal-print-${month}.png`)));
  results.push(entry);
}

const MONTH_NAMES = ['January', 'February', 'March', 'April', 'May', 'June', 'July',
  'August', 'September', 'October', 'November', 'December'];

/**
 * Walks the screen prototype's own month arrows until it shows `month`.
 *
 * One click per round trip: the prototype re-renders asynchronously, so reading
 * its heading in the same tick as the click always sees the previous month.
 */
async function stepPrototypeToMonth(page, month) {
  const want = Number(month.slice(0, 4)) * 12 + Number(month.slice(5)) - 1;
  const readIndex = `(() => {
    const [name, year] = (document.querySelector('h1')?.textContent ?? '').trim().split(/\\s+/);
    const index = ${JSON.stringify(MONTH_NAMES)}.indexOf(name);
    return { label: name ? name + ' ' + year : '', index: index < 0 || !year ? null : Number(year) * 12 + index };
  })()`;

  let state = await page.evaluate(readIndex);
  for (let i = 0; i < 60 && state.index !== want; i++) {
    const direction = state.index > want ? 'Previous' : 'Next';
    await page.evaluate(
      `[...document.querySelectorAll('button')]`
      + `.find((b) => b.getAttribute('aria-label') === '${direction} month')?.click(), true`
    );
    await sleep(180);
    state = await page.evaluate(readIndex);
  }
  await sleep(400);
  return { ok: state.index === want, label: state.label };
}

async function captureReference(cdp, origin, out, months, results) {
  const screen = await Page.open(cdp, SCREEN);
  await screen.addInitScript(WEBFONTS);
  await screen.goto(`${origin}/MCC%20Calendar.dc.html`, { settle: 1500 });

  // The prototype's state starts at August 2026; step to whichever months were
  // asked for by driving its own month buttons.
  for (const month of months) {
    const shift = await stepPrototypeToMonth(screen, month);
    results.push({
      id: `reference-screen-${month}`, side: 'reference', view: 'screen', month,
      note: shift.ok ? '' : `prototype showed "${shift.label}"`,
      file: path.basename(await screen.screenshot(path.join(out, `reference-screen-${month}.png`), { fullPage: true })),
    });
  }

  for (const month of months) {
    const print = await Page.open(cdp, { ...SHEET, scale: 2 });
    await print.addInitScript(WEBFONTS);
    // The print prototype seeds its month from the hash, 0-based month.
    const hash = `${month.slice(0, 4)}-${Number(month.slice(5)) - 1}`;
    await print.goto(`${origin}/MCC%20Calendar%20Print.dc.html#${hash}`, { settle: 1500 });
    const entry = {
      id: `reference-print-${month}`, side: 'reference', view: 'print', month,
      file: path.basename(await print.screenshot(path.join(out, `reference-print-${month}.png`))),
    };
    entry.fit = await print.evaluate(FIT_PROBE('.page'));
    await print.emulatePrint(true);
    entry.pages = pdfPageCount(await print.pdf(path.join(out, `reference-print-${month}.pdf`)));
    entry.file = path.basename(await print.screenshot(path.join(out, `reference-print-${month}.png`)));
    results.push(entry);
  }
}

/* ---------------------------------------------------------------- report -- */

function buildCompareHtml(results, months) {
  const find = (side, view, month) => results.find((r) => r.side === side && r.view === view && r.month === month);
  const rows = [];
  for (const month of months) {
    for (const view of ['screen', 'print']) {
      const ref = find('reference', view, month);
      const drupal = find('drupal', view, month);
      if (!ref && !drupal) continue;
      rows.push(`
        <section>
          <h2>${view === 'screen' ? 'Screen' : 'Print sheet'} &middot; ${month}</h2>
          <div class="pair">
            <figure><figcaption>Design reference</figcaption>${ref ? `<img src="${ref.file}" alt="">` : '<p>not captured</p>'}</figure>
            <figure><figcaption>Drupal /calendar</figcaption>${drupal ? `<img src="${drupal.file}" alt="">` : '<p>not captured</p>'}</figure>
          </div>
        </section>`);
    }
    const mobile = find('drupal', 'mobile', month);
    if (mobile) {
      rows.push(`
        <section>
          <h2>Narrow viewport (430px) &middot; ${month}</h2>
          <div class="pair"><figure><figcaption>Drupal /calendar</figcaption><img class="narrow" src="${mobile.file}" alt=""></figure></div>
        </section>`);
    }
  }

  return `<!doctype html>
<meta charset="utf-8">
<title>MCC calendar: design reference vs Drupal</title>
<style>
  body { margin: 0; padding: 24px; font: 14px/1.5 system-ui, sans-serif; background: #f6f4ee; color: #2a1f18; }
  h1 { font-size: 20px; }
  section { margin: 32px 0; }
  h2 { font-size: 15px; text-transform: uppercase; letter-spacing: .08em; color: #6d5c48; }
  .pair { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; align-items: start; }
  figure { margin: 0; background: #fff; border: 1px solid #d9d0bd; border-radius: 8px; overflow: hidden; }
  figcaption { padding: 6px 10px; font-weight: 700; font-size: 12px; background: #efe8da; }
  img { display: block; width: 100%; height: auto; }
  img.narrow { max-width: 430px; }
</style>
<h1>MCC calendar: design reference vs Drupal</h1>
${rows.join('\n')}
`;
}

function buildReport(results, months, failures) {
  const lines = ['# Calendar comparison report', '', `Generated ${new Date().toISOString()}`, ''];
  lines.push('| view | side | month | print pages | sheet size | clipped elements |');
  lines.push('| --- | --- | --- | --- | --- | --- |');
  for (const r of results) {
    const fit = r.fit ?? {};
    lines.push(`| ${r.view} | ${r.side} | ${r.month} | ${r.pages ?? '—'} | ${fit.sheet ? `${fit.sheet.width}×${fit.sheet.height}` : '—'} | ${fit.overflowCount ?? '—'} |`);
  }
  lines.push('');
  for (const r of results) {
    const clipped = r.fit?.overflowing ?? [];
    if (!clipped.length) continue;
    lines.push(`## Clipped in ${r.id}`, '');
    for (const c of clipped) {
      lines.push(`- \`${c.selector}\` overflow ${c.overflowY}px×${c.overflowX}px — “${c.text}”`);
    }
    lines.push('');
  }
  lines.push(failures.length ? '## Failures\n' : '## Result\n');
  lines.push(failures.length ? failures.map((f) => `- ${f}`).join('\n') : 'All print assertions passed.');
  lines.push('', 'Open `compare.html` in this directory for the side-by-side view.', '');
  return lines.join('\n');
}

/* ------------------------------------------------------------------ main -- */

async function main() {
  const opts = parseArgs(process.argv.slice(2));
  if (opts.help) {
    console.log(fs.readFileSync(fileURLToPath(import.meta.url), 'utf8').split('*/')[0]);
    return 0;
  }

  const out = path.resolve(ROOT, opts.out);
  await fsp.mkdir(out, { recursive: true });
  const designDir = path.resolve(ROOT, '.calendar-design');
  const bundle = await unpackDesign(designDir);
  const { server, port } = await serveDesign(bundle);
  const origin = `http://127.0.0.1:${port}`;

  const profile = await fsp.mkdtemp(path.join(os.tmpdir(), 'mcc-calendar-chrome-'));
  const { child, wsUrl } = await launchChrome(profile);
  const cdp = await Cdp.connect(wsUrl);

  const results = [];
  const failures = [];
  try {
    await captureReference(cdp, origin, out, opts.months, results);
    for (const month of opts.months) {
      try {
        await captureDrupal(cdp, opts.base, out, month, results);
      } catch (error) {
        failures.push(`Could not capture the Drupal pages for ${month} at ${opts.base}: ${error.message}`);
      }
    }
  } finally {
    cdp.close();
    const exited = new Promise((resolve) => child.once('exit', resolve));
    child.kill();
    await Promise.race([exited, sleep(3000)]);
    server.close();
    if (!opts.keep) await fsp.rm(profile, { recursive: true, force: true, maxRetries: 5, retryDelay: 200 });
  }

  // Assertions apply to our own print sheet; the reference numbers are context.
  for (const r of results.filter((x) => x.side === 'drupal' && x.view === 'print')) {
    if (r.pages !== 1) failures.push(`${r.id}: print PDF is ${r.pages} pages, expected exactly 1.`);
    for (const probe of [['screen media', r.fit], ['print media', r.fitPrintMedia]]) {
      const [label, fit] = probe;
      if (!fit) continue;
      if (fit.error) failures.push(`${r.id} (${label}): ${fit.error}`);
      else if (fit.overflowCount > 0) {
        failures.push(`${r.id} (${label}): ${fit.overflowCount} clipped element(s), first “${fit.overflowing[0].text}”.`);
      }
    }
  }

  await fsp.writeFile(path.join(out, 'compare.html'), buildCompareHtml(results, opts.months));
  await fsp.writeFile(path.join(out, 'report.md'), buildReport(results, opts.months, failures));

  for (const r of results) {
    const bits = [r.id.padEnd(28), (r.pages ? `${r.pages}pp` : '').padEnd(5)];
    if (r.fit) bits.push(r.fit.overflowCount ? `CLIPPED×${r.fit.overflowCount}` : 'fit ok');
    if (r.note) bits.push(`(${r.note})`);
    console.log('  ' + bits.join(' '));
  }
  console.log(`\n  → ${path.relative(ROOT, out)}/compare.html`);
  if (failures.length) {
    console.error('\nFAIL:\n' + failures.map((f) => '  - ' + f).join('\n'));
    return 1;
  }
  console.log('\nPASS: print sheet is one Letter page with nothing clipped.');
  return 0;
}

process.exitCode = await main();
