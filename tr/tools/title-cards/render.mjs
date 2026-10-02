// Renders one title-card variant (1-4) from hero-card.html into numbered PNG frames.
//   node render.mjs <variant> <output-dir> [fps]        (default 30 fps, 192 frames = one 6.4 s loop)
// Drives headless Chrome over the DevTools protocol; needs Chrome and node >= 22 (global WebSocket).
import { spawn } from 'node:child_process';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const HERE = path.dirname(fileURLToPath(import.meta.url));
const CHROME = process.env.CHROME || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const PORT = 9333;
const sleep = ms => new Promise(r => setTimeout(r, ms));

const [variant, outDir, fpsArg] = process.argv.slice(2);
if (!variant || !outDir) { console.error('usage: node render.mjs <variant 1-4> <output-dir> [fps]'); process.exit(2); }
const fps = +(fpsArg || 30);
const profile = fs.mkdtempSync(path.join(os.tmpdir(), 'title-cards-chrome-'));
const proc = spawn(CHROME, [
  '--headless=new', '--disable-gpu', '--hide-scrollbars', '--no-first-run', '--no-default-browser-check',
  `--remote-debugging-port=${PORT}`, '--remote-allow-origins=*', `--user-data-dir=${profile}`,
  '--window-size=1512,790', 'about:blank'], { stdio: 'ignore' });
const done = code => { try { proc.kill('SIGKILL'); } catch {} fs.rmSync(profile, { recursive: true, force: true }); process.exit(code); };

async function main() {
  let up = false;
  for (let i = 0; i < 100 && !up; i++) {
    try { up = (await fetch(`http://127.0.0.1:${PORT}/json/version`)).ok; } catch {}
    if (!up) await sleep(150);
  }
  if (!up) throw new Error('chrome did not start');
  const page = (await (await fetch(`http://127.0.0.1:${PORT}/json/list`)).json()).find(t => t.type === 'page');
  const ws = new WebSocket(page.webSocketDebuggerUrl);
  let id = 0; const pending = new Map();
  ws.onmessage = ev => { const m = JSON.parse(ev.data); if (m.id && pending.has(m.id)) { pending.get(m.id)(m); pending.delete(m.id); } };
  const send = (method, params = {}) => new Promise((res, rej) => {
    const i = ++id; pending.set(i, m => m.error ? rej(new Error(JSON.stringify(m.error))) : res(m.result));
    ws.send(JSON.stringify({ id: i, method, params }));
  });
  await new Promise(r => ws.onopen = r);
  await send('Page.enable');
  await send('Page.navigate', { url: 'file://' + path.join(HERE, 'hero-card.html') });
  for (let i = 0; i < 100; i++) {
    if ((await send('Runtime.evaluate', { expression: 'typeof window.render === "function"', returnByValue: true })).result.value) break;
    await sleep(100);
  }
  const T = (await send('Runtime.evaluate', { expression: 'window.META.T', returnByValue: true })).result.value;
  fs.rmSync(outDir, { recursive: true, force: true }); fs.mkdirSync(outDir, { recursive: true });
  const n = Math.round(T * fps);
  for (let k = 0; k < n; k++) {
    const r = await send('Runtime.evaluate', { expression: `window.render(${+variant}, ${(k / fps).toFixed(5)})`, returnByValue: true });
    if (r.exceptionDetails) throw new Error(JSON.stringify(r.exceptionDetails));
    fs.writeFileSync(path.join(outDir, String(k).padStart(4, '0') + '.png'), Buffer.from(r.result.value.split(',')[1], 'base64'));
  }
  console.log(`variant ${variant}: ${n} frames -> ${outDir}`);
  try { await send('Browser.close'); } catch {}
}
main().then(() => done(0)).catch(e => { console.error(e); done(1); });
