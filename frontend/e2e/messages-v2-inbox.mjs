/**
 * Goal 6 headless-browser journey for the V2 chat inbox.
 *
 * Seeds a disposable database with synthetic members/thread/message, starts
 * Uvicorn (FastAPI) and `vite preview` (the built frontend), then drives Chromium
 * through the real login UI into `/messages` and asserts the seeded conversation
 * renders from the live `chat_api/v2_get_threads` response.
 *
 * Because the app has no CORS middleware yet (Goal 10), same-origin requests to the
 * preview host are transparently proxied to Uvicorn inside the browser context, so
 * the journey exercises the real frontend + real API without changing app code.
 */
import { spawn, spawnSync } from 'node:child_process';
import { generateKeyPairSync } from 'node:crypto';
import { mkdirSync, writeFileSync } from 'node:fs';
import { setTimeout as sleep } from 'node:timers/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

import { chromium } from '@playwright/test';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, '..', '..');
const backendDir = path.join(repoRoot, 'python-backend');
const frontendDir = path.join(repoRoot, 'frontend');

const DB_URL =
  process.env.E2E_DATABASE_URL || 'mysql+pymysql://root@127.0.0.1:3313/alumni_portal_reaper_test';
const API_PORT = Number(process.env.E2E_API_PORT || 8099);
const WEB_PORT = Number(process.env.E2E_WEB_PORT || 4173);
const API_ORIGIN = `http://127.0.0.1:${API_PORT}`;
const WEB_ORIGIN = `http://127.0.0.1:${WEB_PORT}`;
const UPLOAD_ROOT = path.join(repoRoot, 'tmp', 'e2e-uploads');
const PYTHON = path.join(backendDir, '.venv', 'Scripts', 'python.exe');
const API_PREFIXES = ['/api/', '/chat_api/', '/blog_api/', '/news', '/product/', '/socials/', '/health/', '/uploads/'];

const children = [];

function stopAll() {
  for (const child of children) {
    if (!child.pid) continue;
    if (process.platform === 'win32') {
      spawnSync('taskkill', ['/pid', String(child.pid), '/T', '/F'], { stdio: 'ignore' });
    } else {
      child.kill('SIGTERM');
    }
  }
}

function run(command, args, options = {}) {
  return new Promise((resolve, reject) => {
    const child = spawn(command, args, { ...options, stdio: ['ignore', 'pipe', 'pipe'] });
    let stdout = '';
    let stderr = '';
    child.stdout.on('data', (chunk) => (stdout += chunk));
    child.stderr.on('data', (chunk) => (stderr += chunk));
    child.on('error', reject);
    child.on('close', (code) => {
      if (code === 0) resolve(stdout);
      else reject(new Error(`${command} exited ${code}\n${stderr || stdout}`));
    });
  });
}

async function waitFor(url, timeoutMs = 60000) {
  const deadline = Date.now() + timeoutMs;
  let lastError = 'not started';
  while (Date.now() < deadline) {
    try {
      const response = await fetch(url);
      if (response.status < 500) return;
      lastError = `status ${response.status}`;
    } catch (error) {
      lastError = error instanceof Error ? error.message : String(error);
    }
    await sleep(500);
  }
  throw new Error(`Timed out waiting for ${url}: ${lastError}`);
}

async function main() {
  mkdirSync(UPLOAD_ROOT, { recursive: true });
  for (const sub of [
    'profiles',
    'announcements',
    'marketplace',
    'projects',
    'leadership',
    'vacancies',
    'events',
    'homepage/carousel',
    'blog/gallery',
    'products',
    'chat',
  ]) {
    mkdirSync(path.join(UPLOAD_ROOT, sub), { recursive: true });
  }

  const seedOutput = await run(
    PYTHON,
    ['-m', 'scripts.seed_chat_journey_fixture', '--database-url', DB_URL],
    { cwd: backendDir },
  );
  const fixture = JSON.parse(seedOutput.trim().split(/\r?\n/).pop());
  if (fixture.status !== 'ok') throw new Error(`Seed refused: ${JSON.stringify(fixture)}`);

  const { privateKey, publicKey } = generateKeyPairSync('rsa', {
    modulusLength: 2048,
    publicKeyEncoding: { type: 'spki', format: 'pem' },
    privateKeyEncoding: { type: 'pkcs8', format: 'pem' },
  });

  const api = spawn(
    PYTHON,
    [
      '-m', 'uvicorn', 'app.main:app',
      '--host', '127.0.0.1',
      '--port', String(API_PORT),
      '--log-level', 'warning',
    ],
    {
      cwd: backendDir,
      env: {
        ...process.env,
        PIP_REQUIRE_VIRTUALENV: 'true',
        ALUMNI_ENVIRONMENT: 'development',
        ALUMNI_DATABASE_URL: DB_URL,
        ALUMNI_JWT_SIGNING_KEY: privateKey,
        ALUMNI_JWT_VERIFICATION_KEY: publicKey,
        ALUMNI_PUBLIC_BASE_URL: `${API_ORIGIN}/`,
        ALUMNI_FRONTEND_BASE_URL: `${WEB_ORIGIN}/`,
        ALUMNI_UPLOAD_ROOT: UPLOAD_ROOT,
        ALUMNI_EXPOSE_DOCS: 'false',
      },
      stdio: ['ignore', 'pipe', 'pipe'],
    },
  );
  children.push(api);
  api.stderr.on('data', (chunk) => process.stderr.write(`[api] ${chunk}`));
  await waitFor(`${API_ORIGIN}/health/live`);

  const web = spawn(
    process.platform === 'win32' ? 'npm.cmd' : 'npm',
    ['run', 'preview', '--', '--host', '127.0.0.1', '--port', String(WEB_PORT), '--strictPort'],
    {
      cwd: frontendDir,
      env: { ...process.env },
      stdio: ['ignore', 'pipe', 'pipe'],
      shell: process.platform === 'win32',
    },
  );
  children.push(web);
  web.stderr.on('data', (chunk) => process.stderr.write(`[web] ${chunk}`));
  await waitFor(`${WEB_ORIGIN}/`);

  const browser = await chromium.launch();
  try {
    const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const page = await context.newPage();
    const threadStatuses = [];
    page.on('response', (response) => {
      if (response.url().includes('/chat_api/v2_get_threads')) {
        threadStatuses.push(response.status());
      }
    });

    await page.route('**/*', async (route) => {
      const request = route.request();
      const url = new URL(request.url());
      if (url.origin === WEB_ORIGIN && API_PREFIXES.some((p) => url.pathname.startsWith(p))) {
        const response = await route.fetch({ url: `${API_ORIGIN}${url.pathname}${url.search}` });
        await route.fulfill({ response });
        return;
      }
      await route.continue();
    });

    await page.goto(`${WEB_ORIGIN}/messages`, { waitUntil: 'domcontentloaded' });
    await page.waitForURL('**/auth/login**', { timeout: 30000 });
    await page.fill('#email', fixture.email);
    await page.fill('#password', fixture.password);
    const loginResponsePromise = page.waitForResponse(
      (response) => response.url().includes('/api/login'),
      { timeout: 30000 },
    );
    await page.getByRole('button', { name: 'Sign In', exact: true }).click();
    const loginResponse = await loginResponsePromise;
    if (loginResponse.status() !== 200) {
      throw new Error(
        `Login failed: status=${loginResponse.status()} body=${(await loginResponse.text()).slice(0, 400)}`,
      );
    }
    await page.goto(`${WEB_ORIGIN}/messages`, { waitUntil: 'domcontentloaded' });
    try {
      await page
        .getByText(fixture.thread_title, { exact: false })
        .first()
        .waitFor({ timeout: 30000 });
    } catch (error) {
      const failureScreenshot = path.join(repoRoot, 'tmp', 'e2e-messages-v2-inbox-failure.png');
      await page.screenshot({ path: failureScreenshot, fullPage: true }).catch(() => {});
      const bodyText = await page
        .locator('body')
        .innerText()
        .catch(() => '');
      throw new Error(
        `Inbox did not render ${fixture.thread_title}. url=${page.url()} ` +
          `threads=${JSON.stringify(threadStatuses)} body=${bodyText.slice(0, 600)} ` +
          `screenshot=${failureScreenshot}`,
        { cause: error },
      );
    }

    const screenshot = path.join(repoRoot, 'tmp', 'e2e-messages-v2-inbox.png');
    await page.screenshot({ path: screenshot, fullPage: true });

    if (!threadStatuses.includes(200)) {
      throw new Error(`v2_get_threads never returned 200 (saw ${JSON.stringify(threadStatuses)})`);
    }

    const evidence = {
      status: 'ok',
      database: DB_URL.split('/').pop(),
      final_url: page.url(),
      thread_title: fixture.thread_title,
      thread_statuses: threadStatuses,
      screenshot,
    };
    writeFileSync(path.join(repoRoot, 'tmp', 'e2e-messages-v2-inbox.json'), JSON.stringify(evidence, null, 2));
    console.log(JSON.stringify(evidence, null, 2));
  } finally {
    await browser.close();
  }
}

main()
  .then(() => {
    stopAll();
    process.exit(0);
  })
  .catch((error) => {
    console.error(error instanceof Error ? error.stack : error);
    stopAll();
    process.exit(1);
  });
