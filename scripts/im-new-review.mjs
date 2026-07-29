#!/usr/bin/env node
/**
 * @file
 * Captures /im-new screenshots, audits readability/visual quality, and writes
 * an HTML handoff file for Claude Design.
 *
 * Usage:
 *   node scripts/im-new-review.mjs
 *   node scripts/im-new-review.mjs --base http://127.0.0.1 --path /get-involved
 *   node scripts/im-new-review.mjs --out .im-new-review
 */

import { spawn, spawnSync } from 'node:child_process';
import fs from 'node:fs';
import fsp from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');

function parseArgs(argv) {
  const opts = {
    base: 'http://127.0.0.1',
    route: '/get-involved',
    out: '.im-new-review',
    enableTwigDebug: true,
    keep: false,
  };
  for (let i = 0; i < argv.length; i++) {
    const arg = argv[i];
    if (arg === '--base') opts.base = argv[++i];
    else if (arg === '--path') opts.route = argv[++i];
    else if (arg === '--out') opts.out = argv[++i];
    else if (arg === '--keep') opts.keep = true;
    else if (arg === '--no-twig-debug') opts.enableTwigDebug = false;
    else if (arg === '--help' || arg === '-h') opts.help = true;
    else throw new Error(`Unknown argument: ${arg}`);
  }
  return opts;
}

function usage() {
  return `Usage:
  node scripts/im-new-review.mjs [--base URL] [--path /get-involved] [--out .im-new-review]
                                 [--no-twig-debug] [--keep]

Defaults:
  --base http://127.0.0.1
  --path /get-involved
  --out  .im-new-review
`;
}

function run(command, args, { cwd = ROOT, input, quiet = false } = {}) {
  const result = spawnSync(command, args, {
    cwd,
    input,
    encoding: 'utf8',
    stdio: ['pipe', 'pipe', 'pipe'],
  });
  if (result.status !== 0) {
    const rendered = [
      `Command failed: ${command} ${args.join(' ')}`,
      result.stdout?.trim(),
      result.stderr?.trim(),
    ].filter(Boolean).join('\n');
    throw new Error(rendered);
  }
  if (!quiet && result.stderr?.trim()) {
    process.stderr.write(`${result.stderr.trim()}\n`);
  }
  return result.stdout ?? '';
}

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
  const port = 9000 + (process.pid % 500);
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

  let noise = '';
  child.stderr.on('data', (chunk) => { noise = (noise + chunk).slice(-2000); });

  for (let i = 0; i < 300; i++) {
    try {
      const res = await fetch(`http://127.0.0.1:${port}/json/version`);
      if (res.ok) return { child, wsUrl: (await res.json()).webSocketDebuggerUrl };
    } catch {
      // Not up yet.
    }
    await sleep(100);
  }

  child.kill();
  throw new Error(`Chromium did not expose a DevTools endpoint on port ${port}.\n${noise}`);
}

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

  async evaluate(expression) {
    const { result, exceptionDetails } = await this.call('Runtime.evaluate', {
      expression, returnByValue: true, awaitPromise: true,
    });
    if (exceptionDetails) {
      throw new Error(exceptionDetails.exception?.description ?? exceptionDetails.text);
    }
    return result.value;
  }

  async goto(url, { settle = 1200 } = {}) {
    const loaded = this.cdp.once('Page.loadEventFired', this.sessionId);
    await this.call('Page.navigate', { url });
    await loaded;
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
}

const AUDIT_PROBE = `(() => {
  const isVisible = (el) => {
    if (!el || !el.getBoundingClientRect) return false;
    const style = getComputedStyle(el);
    if (style.display === 'none' || style.visibility === 'hidden') return false;
    const rect = el.getBoundingClientRect();
    return rect.width > 0 && rect.height > 0;
  };

  // mcc_theme's tokens are color-mix(in oklch, …) and oklch() values, which
  // getComputedStyle returns verbatim rather than as rgb(). Matching only
  // rgb() therefore failed to read every brand background, the walk-up fell
  // through to white, and light-on-dark text was scored against white — that
  // is what reported the newsletter band's oatmeal-on-green as 1.28 when it
  // actually measures ~10.5. Normalise through a canvas, which accepts any
  // CSS colour syntax the browser can parse and hands back sRGB.
  const probeCanvas = document.createElement('canvas');
  probeCanvas.width = probeCanvas.height = 1;
  const probeCtx = probeCanvas.getContext('2d', { willReadFrequently: true });
  const rgbCache = new Map();
  const parseRgb = (value) => {
    if (!value) return null;
    if (rgbCache.has(value)) return rgbCache.get(value);
    let out = null;
    const m = value.match(/rgba?\\((\\d+),\\s*(\\d+),\\s*(\\d+)(?:,\\s*([\\d.]+))?\\)/i);
    if (m) {
      out = { r: Number(m[1]), g: Number(m[2]), b: Number(m[3]), a: m[4] === undefined ? 1 : Number(m[4]) };
    } else if (value !== 'transparent' && value !== 'none') {
      try {
        // An unparseable value leaves fillStyle at whatever it already was, so
        // probe from two different sentinels: a real colour resolves to the
        // same thing from both, a bad one just keeps each sentinel.
        probeCtx.fillStyle = '#ff00ff';
        probeCtx.fillStyle = value;
        const first = probeCtx.fillStyle;
        probeCtx.fillStyle = '#00ff00';
        probeCtx.fillStyle = value;
        if (probeCtx.fillStyle === first) {
          probeCtx.clearRect(0, 0, 1, 1);
          probeCtx.fillRect(0, 0, 1, 1);
          const d = probeCtx.getImageData(0, 0, 1, 1).data;
          out = { r: d[0], g: d[1], b: d[2], a: d[3] / 255 };
        }
      } catch (e) {
        out = null;
      }
    }
    rgbCache.set(value, out);
    return out;
  };

  const toLin = (c) => {
    const s = c / 255;
    return s <= 0.04045 ? s / 12.92 : Math.pow((s + 0.055) / 1.055, 2.4);
  };

  const luminance = ({ r, g, b }) => 0.2126 * toLin(r) + 0.7152 * toLin(g) + 0.0722 * toLin(b);

  const contrast = (fg, bg) => {
    const l1 = luminance(fg);
    const l2 = luminance(bg);
    const hi = Math.max(l1, l2);
    const lo = Math.min(l1, l2);
    return (hi + 0.05) / (lo + 0.05);
  };

  const resolvedBg = (el) => {
    let sawBackgroundImage = false;
    let cur = el;
    while (cur) {
      const style = getComputedStyle(cur);
      if (style.backgroundImage && style.backgroundImage !== 'none') {
        sawBackgroundImage = true;
      }
      const bg = parseRgb(style.backgroundColor);
      if (bg && bg.a > 0.98) return bg;
      cur = cur.parentElement;
    }
    return sawBackgroundImage ? null : { r: 255, g: 255, b: 255, a: 1 };
  };

  const selector = (el) => {
    const parts = [];
    let cur = el;
    for (let i = 0; i < 3 && cur; i++) {
      const part = cur.tagName.toLowerCase();
      const className = (cur.className && typeof cur.className === 'string')
        ? cur.className.trim().split(/\\s+/).slice(0, 2).join('.')
        : '';
      parts.unshift(className ? part + '.' + className : part);
      cur = cur.parentElement;
    }
    return parts.join(' > ');
  };

  const headingNodes = [...document.querySelectorAll('h1, h2, h3, h4, h5, h6, .section-intro__heading')]
    .filter((el) => isVisible(el) && (el.textContent || '').trim().length > 0)
    .slice(0, 80);
  const bodyNodes = [...document.querySelectorAll('p, li, .section-intro__description')]
    .filter((el) => isVisible(el) && (el.textContent || '').trim().length > 0)
    .slice(0, 120);

  const headingCalistoga = headingNodes.filter((el) => getComputedStyle(el).fontFamily.includes('Calistoga')).length;
  const bodyNunito = bodyNodes.filter((el) => getComputedStyle(el).fontFamily.includes('Nunito')).length;

  const contrastSamples = [...new Set([...headingNodes, ...bodyNodes, ...document.querySelectorAll('a, button')])]
    .filter((el) => isVisible(el) && (el.textContent || '').trim().length >= 3)
    .slice(0, 220);
  const contrastFails = [];
  let uncertainContrastCount = 0;
  let minContrast = 999;
  for (const el of contrastSamples) {
    const style = getComputedStyle(el);
    const fg = parseRgb(style.color);
    if (!fg) continue;
    const bg = resolvedBg(el);
    if (!bg) {
      uncertainContrastCount++;
      continue;
    }
    const ratio = contrast(fg, bg);
    minContrast = Math.min(minContrast, ratio);
    const isLarge = Number.parseFloat(style.fontSize) >= 24 || Number.parseFloat(style.fontWeight) >= 700;
    const floor = isLarge ? 3.0 : 4.5;
    if (ratio + 0.01 < floor) {
      contrastFails.push({
        selector: selector(el),
        ratio: Number(ratio.toFixed(2)),
        text: (el.textContent || '').trim().slice(0, 80),
      });
    }
  }

  const lineLengthNodes = bodyNodes.slice(0, 80);
  const badLineLengths = [];
  const lineEstimates = [];
  for (const el of lineLengthNodes) {
    const rect = el.getBoundingClientRect();
    const fontSize = Number.parseFloat(getComputedStyle(el).fontSize) || 16;
    const estChars = rect.width / (fontSize * 0.52);
    const textLength = (el.textContent || '').trim().length;
    lineEstimates.push(estChars);
    // Line length only means something for text that actually wraps. Nav
    // labels and eyebrows ("Ministries", "Serve & Connect") fit on one line by
    // definition, so measuring their box width against a prose ideal reports
    // every one of them as a too-narrow column.
    if (textLength <= estChars) continue;
    if (estChars > 95 || estChars < 35) {
      badLineLengths.push({
        selector: selector(el),
        charsPerLine: Number(estChars.toFixed(1)),
      });
    }
  }

  const targets = [...document.querySelectorAll('a, button, input[type="button"], input[type="submit"]')]
    .filter((el) => isVisible(el))
    // A skip link is *supposed* to be 1x1 until it takes focus, at which point
    // it sizes up. Measuring it at rest reports correct markup as a defect.
    .filter((el) => !el.closest('.visually-hidden, .sr-only, .visually-hidden.focusable'))
    .slice(0, 200);
  const smallTargets = [];
  for (const el of targets) {
    const r = el.getBoundingClientRect();
    if (r.width < 44 || r.height < 44) {
      smallTargets.push({
        selector: selector(el),
        width: Number(r.width.toFixed(1)),
        height: Number(r.height.toFixed(1)),
        text: (el.textContent || '').trim().slice(0, 60),
      });
    }
  }

  return {
    typography: {
      headingSamples: headingNodes.length,
      headingCalistoga,
      headingCalistogaPct: headingNodes.length ? Number((headingCalistoga / headingNodes.length).toFixed(2)) : null,
      bodySamples: bodyNodes.length,
      bodyNunito,
      bodyNunitoPct: bodyNodes.length ? Number((bodyNunito / bodyNodes.length).toFixed(2)) : null,
    },
    contrast: {
      checked: contrastSamples.length,
      minRatio: Number((minContrast === 999 ? 0 : minContrast).toFixed(2)),
      uncertainCount: uncertainContrastCount,
      fails: contrastFails.slice(0, 20),
      failCount: contrastFails.length,
    },
    lineLength: {
      checked: lineEstimates.length,
      averageCharsPerLine: lineEstimates.length
        ? Number((lineEstimates.reduce((a, b) => a + b, 0) / lineEstimates.length).toFixed(1))
        : null,
      outOfRange: badLineLengths.slice(0, 20),
      outOfRangeCount: badLineLengths.length,
    },
    tapTargets: {
      checked: targets.length,
      smallTargets: smallTargets.slice(0, 20),
      smallTargetCount: smallTargets.length,
    },
  };
})()`;

function scoreAudit(audit) {
  let score = 100;
  const penalties = [];

  const headingPct = audit.typography.headingCalistogaPct ?? 0;
  if (headingPct < 0.6) {
    const p = Math.round((0.6 - headingPct) * 25);
    score -= p;
    penalties.push(`Heading font mismatch (${Math.round(headingPct * 100)}% Calistoga)`);
  }

  const bodyPct = audit.typography.bodyNunitoPct ?? 0;
  if (bodyPct < 0.75) {
    const p = Math.round((0.75 - bodyPct) * 25);
    score -= p;
    penalties.push(`Body font mismatch (${Math.round(bodyPct * 100)}% Nunito)`);
  }

  if (audit.contrast.failCount > 0) {
    const p = Math.min(40, audit.contrast.failCount * 6);
    score -= p;
    penalties.push(`${audit.contrast.failCount} low-contrast text sample(s)`);
  }

  if (audit.lineLength.outOfRangeCount > 0) {
    const p = Math.min(18, audit.lineLength.outOfRangeCount * 2);
    score -= p;
    penalties.push(`${audit.lineLength.outOfRangeCount} body text block(s) outside ideal line length`);
  }

  if (audit.tapTargets.smallTargetCount > 0) {
    const p = Math.min(18, audit.tapTargets.smallTargetCount * 3);
    score -= p;
    penalties.push(`${audit.tapTargets.smallTargetCount} interactive target(s) smaller than 44x44`);
  }

  score = Math.max(0, score);
  const rating = score >= 90 ? 'Excellent' : score >= 75 ? 'Good' : score >= 60 ? 'Needs polish' : 'Needs redesign';
  return { score, rating, penalties };
}

function escapeHtml(value) {
  return value
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;');
}

function extractTwigHints(html) {
  const lines = html.split('\n');
  return lines
    .filter((line) => line.includes('THEME DEBUG') || line.includes('THEME HOOK') || line.includes('FILE NAME SUGGESTIONS'))
    .slice(0, 200);
}

function componentComments(componentMap) {
  const comments = [];
  comments.push(`<!-- Source alias: ${componentMap.alias} -->`);
  comments.push(`<!-- Resolved path: ${componentMap.resolved_path} -->`);
  comments.push(`<!-- Canvas page: ${componentMap.canvas_page_label} (id ${componentMap.canvas_page_id}) -->`);
  comments.push(`<!-- Component count: ${componentMap.component_count} -->`);
  for (const comp of componentMap.components) {
    const keys = comp.input_keys.join(', ');
    const summary = Object.entries(comp.summary || {})
      .map(([k, v]) => `${k}="${String(v).replaceAll('"', "'").slice(0, 90)}"`)
      .join('; ');
    comments.push(`<!-- [Canvas ${comp.position}] ${comp.component_id} uuid=${comp.uuid} keys=[${keys}] ${summary} -->`);
  }
  return comments.join('\n');
}

function buildHandoffHtml({
  route,
  desktopPng,
  mobilePng,
  audit,
  score,
  componentMap,
  sourceHtmlPath,
  twigHintLines,
}) {
  const penalties = score.penalties.length
    ? `<ul>${score.penalties.map((p) => `<li>${escapeHtml(p)}</li>`).join('')}</ul>`
    : '<p>No high-priority issues were detected by the automated checks.</p>';

  const contrastRows = audit.contrast.fails.map((f) =>
    `<tr><td>${escapeHtml(f.selector)}</td><td>${f.ratio}</td><td>${escapeHtml(f.text)}</td></tr>`).join('');
  const tapRows = audit.tapTargets.smallTargets.map((t) =>
    `<tr><td>${escapeHtml(t.selector)}</td><td>${t.width} x ${t.height}</td><td>${escapeHtml(t.text)}</td></tr>`).join('');

  const twigHints = twigHintLines.length
    ? `<pre>${escapeHtml(twigHintLines.join('\n'))}</pre>`
    : '<p>No Twig debug comments were found in the captured source.</p>';

  const componentJson = escapeHtml(JSON.stringify(componentMap, null, 2));

  return `<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>/im-new design handoff for Claude Design</title>
  <style>
    body { margin: 0; padding: 24px; font: 14px/1.5 "Nunito", system-ui, sans-serif; background: #f6f4ee; color: #2a1f18; }
    h1, h2, h3 { margin: 0 0 10px; }
    h1 { font-family: "Calistoga", Georgia, serif; font-size: 30px; color: #1e4d2b; }
    h2 { margin-top: 28px; font-size: 18px; color: #1e4d2b; }
    .card { background: #fff; border: 1px solid #d9d0bd; border-radius: 10px; padding: 14px 16px; margin-top: 10px; }
    .score { display: inline-block; padding: 4px 10px; border-radius: 999px; font-weight: 700; background: #e6f1e8; color: #1e4d2b; }
    .pair { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
    figure { margin: 0; border: 1px solid #d9d0bd; border-radius: 8px; overflow: hidden; background: #fff; }
    figcaption { font-weight: 700; background: #efe8da; padding: 6px 10px; }
    img { width: 100%; display: block; }
    table { border-collapse: collapse; width: 100%; }
    th, td { border: 1px solid #e5ddca; padding: 6px 8px; vertical-align: top; text-align: left; }
    th { background: #f1eadc; }
    pre { white-space: pre-wrap; word-break: break-word; background: #faf8f2; border: 1px solid #e5ddca; border-radius: 8px; padding: 10px; }
    .meta { color: #5f4e3f; }
  </style>
</head>
<body>
  ${componentComments(componentMap)}
  <!-- Twig debug comments are expected in source capture when local twig debug is enabled. -->
  <!-- Claude Design: edit Drupal templates/components, not this artifact HTML. -->

  <h1>/im-new visual handoff for Claude Design</h1>
  <p class="meta">Route: <code>${escapeHtml(route)}</code> · Source snapshot: <code>${escapeHtml(sourceHtmlPath)}</code></p>

  <section class="card">
    <h2>Readability + beauty assessment</h2>
    <p><span class="score">Score ${score.score}/100 · ${score.rating}</span></p>
    <p>This score is grounded in typography consistency (Calistoga/Nunito), contrast, line-length comfort, and touch target sizing. It is calibrated against the accepted design direction in the bio/calendar handoff files (warm palette, strong hierarchy, restrained spacing and contrast discipline).</p>
    ${penalties}
  </section>

  <section>
    <h2>Screenshots</h2>
    <div class="pair">
      <figure>
        <figcaption>Desktop capture</figcaption>
        <img src="${escapeHtml(desktopPng)}" alt="">
      </figure>
      <figure>
        <figcaption>Mobile capture</figcaption>
        <img src="${escapeHtml(mobilePng)}" alt="">
      </figure>
    </div>
  </section>

  <section class="card">
    <h2>Audit metrics</h2>
    <table>
      <tbody>
        <tr><th>Heading font coverage (Calistoga)</th><td>${Math.round((audit.typography.headingCalistogaPct ?? 0) * 100)}% (${audit.typography.headingCalistoga}/${audit.typography.headingSamples})</td></tr>
        <tr><th>Body font coverage (Nunito)</th><td>${Math.round((audit.typography.bodyNunitoPct ?? 0) * 100)}% (${audit.typography.bodyNunito}/${audit.typography.bodySamples})</td></tr>
        <tr><th>Contrast fails</th><td>${audit.contrast.failCount} of ${audit.contrast.checked} samples (min ratio ${audit.contrast.minRatio}; skipped ${audit.contrast.uncertainCount} image-background sample(s))</td></tr>
        <tr><th>Line-length out of range</th><td>${audit.lineLength.outOfRangeCount} of ${audit.lineLength.checked} samples (avg ${audit.lineLength.averageCharsPerLine} chars/line)</td></tr>
        <tr><th>Small tap targets (&lt;44x44)</th><td>${audit.tapTargets.smallTargetCount} of ${audit.tapTargets.checked} targets</td></tr>
      </tbody>
    </table>
  </section>

  <section class="card">
    <h3>Low-contrast samples</h3>
    ${contrastRows ? `<table><thead><tr><th>Selector</th><th>Ratio</th><th>Text sample</th></tr></thead><tbody>${contrastRows}</tbody></table>` : '<p>None detected.</p>'}
  </section>

  <section class="card">
    <h3>Small target samples</h3>
    ${tapRows ? `<table><thead><tr><th>Selector</th><th>Size</th><th>Text sample</th></tr></thead><tbody>${tapRows}</tbody></table>` : '<p>None detected.</p>'}
  </section>

  <section class="card">
    <h2>Twig template hints from source</h2>
    <p>These come from Drupal Twig debug comments and help locate the template stack for each rendered region/component.</p>
    ${twigHints}
  </section>

  <section class="card">
    <h2>Canvas entity/component map</h2>
    <p>Use this to map rendered sections back to Canvas component IDs and editable inputs.</p>
    <pre>${componentJson}</pre>
  </section>
</body>
</html>`;
}

async function main() {
  const opts = parseArgs(process.argv.slice(2));
  if (opts.help) {
    console.log(usage());
    return;
  }

  const out = path.resolve(ROOT, opts.out);
  await fsp.mkdir(out, { recursive: true });

  if (opts.enableTwigDebug) {
    run('bash', [path.join(ROOT, 'scripts/enable-local-twig-debug.sh')], { quiet: true });
  }

  const componentJson = run('ddev', [
    'drush',
    'php:script',
    'scripts/im-new-component-map.php',
    '--',
    opts.route,
  ], { quiet: true }).trim();
  const componentMap = JSON.parse(componentJson);
  await fsp.writeFile(path.join(out, 'component-map.json'), `${JSON.stringify(componentMap, null, 2)}\n`);

  const url = `${opts.base.replace(/\/$/, '')}${opts.route}`;
  const sourceHtml = run('curl', ['-sS', url], { quiet: true });
  const sourceHtmlPath = path.join(out, 'im-new-source.html');
  await fsp.writeFile(sourceHtmlPath, sourceHtml);

  const tmpDir = await fsp.mkdtemp(path.join(os.tmpdir(), 'im-new-review-'));
  let chrome;
  let cdp;
  try {
    chrome = await launchChrome(tmpDir);
    cdp = await Cdp.connect(chrome.wsUrl);

    const desktop = await Page.open(cdp, { width: 1440, height: 1200, scale: 2 });
    await desktop.goto(url);
    const desktopPng = 'drupal-im-new-desktop.png';
    await desktop.screenshot(path.join(out, desktopPng), { fullPage: true });
    const audit = await desktop.evaluate(AUDIT_PROBE);

    const mobile = await Page.open(cdp, { width: 430, height: 1200, scale: 2 });
    await mobile.goto(url);
    const mobilePng = 'drupal-im-new-mobile.png';
    await mobile.screenshot(path.join(out, mobilePng), { fullPage: true });

    const score = scoreAudit(audit);
    const twigHintLines = extractTwigHints(sourceHtml);
    const handoffHtml = buildHandoffHtml({
      route: opts.route,
      desktopPng,
      mobilePng,
      audit,
      score,
      componentMap,
      sourceHtmlPath: path.basename(sourceHtmlPath),
      twigHintLines,
    });

    const handoffPath = path.join(out, 'claude-design-handoff-im-new.html');
    await fsp.writeFile(handoffPath, handoffHtml);
    await fsp.writeFile(path.join(out, 'audit.json'), `${JSON.stringify({ audit, score }, null, 2)}\n`);

    const md = [
      '# /im-new review report',
      '',
      `Generated: ${new Date().toISOString()}`,
      '',
      `- Route: \`${opts.route}\``,
      `- Score: **${score.score}/100 (${score.rating})**`,
      `- Twig debug comments detected: **${twigHintLines.length ? 'yes' : 'no'}**`,
      '',
      '## Files',
      '',
      '- `claude-design-handoff-im-new.html`',
      '- `drupal-im-new-desktop.png`',
      '- `drupal-im-new-mobile.png`',
      '- `im-new-source.html`',
      '- `component-map.json`',
      '- `audit.json`',
      '',
    ].join('\n');
    await fsp.writeFile(path.join(out, 'report.md'), md);

    console.log('Generated /im-new review artifacts:');
    console.log(`  ${path.relative(ROOT, handoffPath)}`);
    console.log(`  score: ${score.score}/100 (${score.rating})`);
  } finally {
    if (cdp) cdp.close();
    if (chrome?.child && !chrome.child.killed) chrome.child.kill();
    if (!opts.keep) {
      await fsp.rm(tmpDir, { recursive: true, force: true }).catch(() => {});
    }
  }
}

main().catch((error) => {
  console.error(error.message);
  process.exit(1);
});
