const { test, expect } = require('@playwright/test');

const NOVO = '/nova-landing/index.html';

test('index carrega sem erros e tem skip link', async ({ page }) => {
  const errors = [];
  page.on('pageerror', (e) => errors.push(String(e)));
  await page.goto(NOVO);
  await expect(page).toHaveTitle(/Pilates em Casa/);
  await expect(page.locator('h1')).toHaveCount(1);
  await expect(page.locator('a.skip[href="#topo"]')).toHaveCount(1);
  expect(errors).toEqual([]);
});

test('index tem botoes de pausa e pilulas dentro da foto', async ({ page }) => {
  await page.goto(NOVO);
  expect(await page.locator('button[data-pausa]').count()).toBe(2);
  expect(await page.locator('.ba-legenda').count()).toBe(0);
  expect(await page.locator('.ba b').count()).toBe(10);
  // grupo VIP voltou a ser carrossel com loop (originais sem aria-hidden = 5)
  expect(await page.locator('.carrossel.grupo figure:not([aria-hidden])').count()).toBe(5);
});

test('index: carrossel de depoimentos avanca em loop', async ({ page }) => {
  await page.goto(NOVO);
  await page.locator('#carrossel-depo').scrollIntoViewIfNeeded();
  const antes = await page.locator('#carrossel-depo').evaluate((el) => el.scrollLeft);
  await page.waitForFunction(
    (x) => document.querySelector('#carrossel-depo').scrollLeft > x + 5,
    antes,
    { timeout: 8000 }
  );
});

test('index: pausar comparadores congela a divisoria', async ({ page }) => {
  await page.goto(NOVO);
  await page.locator('.ba').first().scrollIntoViewIfNeeded();
  await page.locator('button[data-pausa="comparadores"]').click();
  const v1 = await page.locator('.ba input').first().evaluate((el) => el.value);
  await page.waitForTimeout(1500);
  const v2 = await page.locator('.ba input').first().evaluate((el) => el.value);
  expect(v1).toBe(v2);
});

test('index: toggle alterna claro/escuro e persiste, sem noindex', async ({ page }) => {
  await page.goto(NOVO);
  await expect(page.locator('meta[name="robots"]')).toHaveCount(0);
  expect(await page.evaluate(() => document.documentElement.getAttribute('data-tema'))).toBeNull();
  const sistemaEscuro = await page.evaluate(() => window.matchMedia('(prefers-color-scheme: dark)').matches);
  const primeiro = sistemaEscuro ? 'claro' : 'escuro';
  const segundo = sistemaEscuro ? 'escuro' : 'claro';
  await page.locator('#tema-toggle').click();
  await expect(page.locator('html')).toHaveAttribute('data-tema', primeiro);
  expect(await page.evaluate(() => localStorage.getItem('tema'))).toBe(primeiro);
  await page.locator('#tema-toggle').click();
  await expect(page.locator('html')).toHaveAttribute('data-tema', segundo);
});

test('index: modo escuro do sistema aplica tokens escuros', async ({ browser }) => {
  const ctx = await browser.newContext({ colorScheme: 'dark' });
  const page = await ctx.newPage();
  const errors = [];
  page.on('pageerror', (e) => errors.push(String(e)));
  await page.goto(NOVO);
  const bg = await page.evaluate(() => getComputedStyle(document.body).backgroundColor);
  expect(bg).not.toBe('rgb(255, 255, 255)');
  expect(errors).toEqual([]);
  await ctx.close();
});
