const { test, expect } = require('@playwright/test');

const NOVO = '/nova-landing/indexnovo.html';

test('preview novo carrega sem erros e tem skip link', async ({ page }) => {
  const errors = [];
  page.on('pageerror', (e) => errors.push(String(e)));
  await page.goto(NOVO);
  await expect(page).toHaveTitle(/Pilates em Casa/);
  await expect(page.locator('h1')).toHaveCount(1);
  await expect(page.locator('a.skip[href="#topo"]')).toHaveCount(1);
  expect(errors).toEqual([]);
});

test('preview novo tem botoes de pausa e pilulas dentro da foto', async ({ page }) => {
  await page.goto(NOVO);
  expect(await page.locator('button[data-pausa]').count()).toBe(2);
  expect(await page.locator('.ba-legenda').count()).toBe(0);
  expect(await page.locator('.ba b').count()).toBe(10);
  // grupo VIP sem clones de loop (rolagem manual + grade no desktop)
  expect(await page.locator('.carrossel.grupo figure').count()).toBe(5);
});

test('preview novo: carrossel de depoimentos avanca em loop', async ({ page }) => {
  await page.goto(NOVO);
  await page.locator('#carrossel-depo').scrollIntoViewIfNeeded();
  const antes = await page.locator('#carrossel-depo').evaluate((el) => el.scrollLeft);
  await page.waitForFunction(
    (x) => document.querySelector('#carrossel-depo').scrollLeft > x + 5,
    antes,
    { timeout: 8000 }
  );
});

test('preview novo: pausar comparadores congela a divisoria', async ({ page }) => {
  await page.goto(NOVO);
  await page.locator('.ba').first().scrollIntoViewIfNeeded();
  await page.locator('button[data-pausa="comparadores"]').click();
  const v1 = await page.locator('.ba input').first().evaluate((el) => el.value);
  await page.waitForTimeout(1500);
  const v2 = await page.locator('.ba input').first().evaluate((el) => el.value);
  expect(v1).toBe(v2);
});
