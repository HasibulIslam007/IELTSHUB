import { test, expect, type Page } from '@playwright/test';
import { readFileSync } from 'node:fs';
import { randomUUID } from 'node:crypto';

const browserErrors = new WeakMap<Page, string[]>();
test.beforeEach(async ({ page }) => {
  const errors: string[] = [];
  browserErrors.set(page, errors);
  page.on('pageerror', error => errors.push(error.message));
  page.on('console', message => {
    if (message.type() === 'error' && /content security policy|violates.*directive/i.test(message.text())) errors.push(message.text());
  });
});
test.afterEach(async ({ page }) => {
  expect(browserErrors.get(page)).toEqual([]);
});

function account(role: string) {
  const path = readFileSync('.local/e2e-credentials-path', 'utf8');
  return JSON.parse(readFileSync(path, 'utf8')).find((a: {email: string}) => a.email === `${role}@ielts.local`);
}
async function login(page: Page, role = 'student') {
  const credentials = account(role);
  await page.goto('/login');
  await page.getByLabel('Email address').fill(credentials.email);
  await page.getByLabel('Password', { exact: true }).fill(credentials.password);
  await page.getByRole('button', { name: 'Log in', exact: true }).click();
  await expect(page).toHaveURL(/\/dashboard$/);
}
async function api(page: Page, path: string, method = 'GET', body?: unknown) {
  return page.evaluate(async ({ path, method, body }) => {
    const token = decodeURIComponent(document.cookie.split('; ').find(c => c.startsWith('XSRF-TOKEN='))?.split('=').slice(1).join('=') ?? '');
    const response = await fetch(`/api/v1${path}`, {
      method, headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-XSRF-TOKEN': token },
      body: body === undefined ? undefined : JSON.stringify(body),
    });
    if (!response.ok) throw new Error(`${method} ${path}: ${response.status} ${await response.text()}`);
    return response.status === 204 ? null : response.json();
  }, { path, method, body });
}
async function begin(page: Page, skill: string) {
  const tests = await api(page, `/tests?skill=${skill}&access=free&sort=duration`);
  const attempt = await api(page, `/tests/${tests.data[0].id}/attempts`, 'POST', { mode: 'practice', request_key: randomUUID() });
  await page.goto(`/attempts/${attempt.id}`);
  await expect(page.getByRole('button', { name: 'Finish session' })).toBeVisible();
  return attempt;
}
async function submit(page: Page) {
  await page.getByRole('button', { name: 'Finish session' }).click();
  await page.getByRole('button', { name: 'Submit session', exact: true }).click();
  await expect(page).toHaveURL(/\/results\//);
}

test('public pages, library filtering, and registration work without overflow', async ({ page }, info) => {
  const errors: string[] = [];
  page.on('pageerror', error => errors.push(error.message));
  await page.goto('/');
  await expect(page.getByRole('heading', { name: /A little practice/ })).toBeVisible();
  await page.screenshot({ path: `test-results/home-${info.project.name}.png`, fullPage: true });
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
  await page.getByRole('link', { name: 'Explore the library', exact: true }).click();
  await expect(page).toHaveTitle('IELTS Practice Library | IELTS Practice Hub');
  await expect(page.locator('meta[property="og:title"]')).toHaveAttribute('content', 'IELTS Practice Library | IELTS Practice Hub');
  await page.getByRole('button', { name: 'Reading', exact: true }).click();
  await expect(page.locator('.test-card').first()).toBeVisible();
  await expect(page.locator('.test-cover.listening')).toHaveCount(0);
  await page.getByLabel('Search tests').fill('no-such-exercise-987654');
  await expect(page.getByText('A fresh search might help.')).toBeVisible();
  await page.goto('/register');
  await page.getByLabel('Your name').fill('New Learner');
  await page.getByLabel('Email address').fill(`${randomUUID()}@example.test`);
  await page.locator('input[name=password]').fill('A-local-learning-password');
  await page.getByLabel('Confirm password').fill('A-local-learning-password');
  await page.getByRole('button', { name: 'Create your account' }).click();
  await expect(page).toHaveURL(/\/onboarding$/);
  await page.getByLabel('Target band').selectOption('7.5');
  await page.getByRole('button', { name: 'Make a start' }).click();
  await expect(page).toHaveURL(/\/dashboard$/);
  await expect(page.locator('meta[name=robots]')).toHaveAttribute('content', 'noindex, nofollow');
  await expect(page.getByRole('link', { name: /Target 7.5/ })).toBeVisible();
  await page.screenshot({ path: `test-results/dashboard-${info.project.name}.png`, fullPage: true });
  expect(errors).toEqual([]);
});

test('reading saves, clears, survives refresh, submits and bookmarks results', async ({ page }, info) => {
  await login(page);
  const attempt = await begin(page, 'reading');
  const input = page.getByPlaceholder('Your answer').first();
  await input.fill('green');
  await expect(page.getByRole('status')).toHaveText('Saved');
  await page.reload();
  await expect(input).toHaveValue('green');
  await input.fill('');
  await expect(page.getByRole('status')).toHaveText('Saved');
  await page.reload();
  await expect(input).toHaveValue('');
  await input.fill('river');
  await page.getByRole('button', { name: 'Pause', exact: true }).click();
  await expect(page.getByRole('heading', { name: 'Take a breather.' })).toBeVisible();
  await page.getByRole('button', { name: 'Resume practice', exact: true }).click();
  await expect(input).toHaveValue('river');
  await page.screenshot({ path: `test-results/reading-${info.project.name}.png`, fullPage: true });
  await submit(page);
  await expect(page.locator('.review-card').first()).toBeVisible();
  expect((await api(page, `/attempts/${attempt.id}`)).status).toBe('submitted');
  await page.getByRole('button', { name: /Save.*mistake|Save.*notebook|Save question/i }).first().click();
  await page.goto('/mistakes');
  await expect(page.locator('.notebook-list')).toBeVisible();
});

test('a failed save retries without losing newer edits', async ({ page }) => {
  await login(page);
  const attempt = await begin(page, 'reading');
  let fail = true;
  await page.route(`**/api/v1/attempts/${attempt.id}`, async route => {
    if (route.request().method() === 'PUT' && fail) return route.abort('failed');
    return route.continue();
  });
  const input = page.getByPlaceholder('Your answer').first();
  await input.fill('first draft');
  await expect(page.getByRole('status')).toContainText('Offline');
  await input.fill('latest draft');
  fail = false;
  await expect(page.getByRole('status')).toHaveText('Saved');
  await page.reload();
  await expect(input).toHaveValue('latest draft');
});

test('listening audio loads and speaking uploads enter teacher review', async ({ page }) => {
  await login(page);
  await begin(page, 'listening');
  await page.getByRole('button', { name: 'Play audio', exact: true }).click();
  await expect.poll(() => page.locator('audio').first().evaluate((a: HTMLAudioElement) => a.readyState)).toBeGreaterThan(0);
  await submit(page);
  const speaking = await begin(page, 'speaking');
  await page.locator('input[type=file]').first().setInputFiles('backend/database/content/listening-1.wav');
  await page.getByRole('button', { name: 'Upload response', exact: true }).first().click();
  await expect(page.getByText('Uploaded and saved privately.').first()).toBeVisible();
  await submit(page);
  const result = await api(page, `/attempts/${speaking.id}`);
  expect(result.status).toBe('awaiting_review');
  expect(result.recordings).toHaveLength(1);
});

test('writing can be assessed and published through the admin workspace', async ({ page }, info) => {
  await login(page);
  const writing = await begin(page, 'writing');
  await page.getByPlaceholder('Give your ideas room to grow…').first().fill('Public transport gives people more affordable ways to reach education and work. Frequent services can help communities thrive.');
  await submit(page);
  expect((await api(page, `/attempts/${writing.id}`)).status).toBe('awaiting_review');
  await api(page, '/auth/logout', 'POST');
  await login(page, 'admin');
  for (const path of ['admin', 'admin/exams', 'admin/users', 'admin/assessments', 'admin/media-assets', 'admin/entitlements', 'admin/contacts', 'admin/plans', 'admin/settings', 'admin/payments', 'admin/collections', 'admin/audit-events']) {
    const response = await page.goto(`/${path}`);
    expect(response?.status(), path).toBe(200);
    await expect(page.locator('body')).not.toContainText('Internal Server Error');
  }
  const reviews = await api(page, '/reviews');
  const review = reviews.data.find((r: {attempt_id: string}) => r.attempt_id === writing.id);
  expect(review).toBeTruthy();
  await page.goto('/admin/assessments');
  await page.screenshot({ path: `test-results/admin-${info.project.name}.png`, fullPage: true });
  const row = page.locator('tr').filter({ hasText: writing.test.title }).first();
  await row.getByRole('button', { name: 'Publish feedback', exact: true }).click();
  await page.getByLabel('Feedback', { exact: true }).fill('Clear ideas and a relevant example. Develop your supporting paragraphs and vary sentence structures.');
  for (const input of await page.locator('input[type=number]:visible').all()) await input.fill('6.5');
  await page.getByRole('button', { name: 'Submit', exact: true }).click();
  await expect.poll(async () => (await api(page, `/reviews/${review.id}`)).status).toBe('published');
  await api(page, '/auth/logout', 'POST');
  await login(page);
  await page.goto(`/results/${writing.id}`);
  await expect(page.getByText(/Clear ideas and a relevant example/)).toBeVisible();
  await page.goto('/notifications');
  await expect(page.locator('.notification-row').first()).toBeVisible();
});
