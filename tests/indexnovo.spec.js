const { test, expect } = require('@playwright/test');

const PAGE = '/nova-landing/indexnovo.html';

test('indexnovo carrega sem erros', async ({ page }) => {
  const errors = [];
  page.on('pageerror', (e) => errors.push(String(e)));
  await page.goto(PAGE);
  await expect(page).toHaveTitle(/Pilates em Casa/);
  await expect(page.locator('h1')).toHaveCount(1);
  expect(errors).toEqual([]);
});

test('desktop: grade e carrosseis originais', async ({ page, isMobile }) => {
  test.skip(isMobile, 'somente desktop');
  await page.goto(PAGE);
  expect(await page.locator('.ba-grade .ba').count()).toBe(5);
  const visBa = await page.locator('.ba-grade .ba').evaluateAll((els) =>
    els.filter((el) => getComputedStyle(el).display !== 'none').length);
  expect(visBa).toBe(5);
  await expect(page.locator('#ba-mobile-nav')).toBeHidden();
  await expect(page.locator('#depo-nav')).toBeHidden();
  await expect(page.locator('#grupo-nav')).toBeHidden();
  // carrossel de depoimentos continua rolando em loop
  await page.locator('#carrossel-depo').scrollIntoViewIfNeeded();
  const antes = await page.locator('#carrossel-depo').evaluate((el) => el.scrollLeft);
  await page.waitForFunction(
    (x) => document.querySelector('#carrossel-depo').scrollLeft > x + 5,
    antes,
    { timeout: 8000 }
  );
});

test('bio: beneficios em cards e fecho sem repeticao', async ({ page }) => {
  await page.goto(PAGE);
  expect(await page.locator('.trans-lista li').count()).toBe(6);
  expect(await page.locator('.trans-lista .trans-check').count()).toBe(6);
  await expect(page.locator('.trans-painel')).toContainText('abraçada');
  const bio = (await page.locator('.bio').textContent()) || '';
  expect((bio.match(/começando do zero/gi) || []).length).toBeLessThanOrEqual(1);
  await expect(page.locator('.fecho-cta')).toContainText('poucos dias');
});

test('oferta: parcelamento em 2 pontos sem redundancia', async ({ page }) => {
  await page.goto(PAGE);
  await expect(page.locator('.oferta-card .parcela')).toContainText('6x de R$10,69');
  const parcs = await page.locator('[data-parc]').evaluateAll((els) => els.map((el) => el.textContent));
  expect(parcs.length).toBe(2);
  expect(new Set(parcs).size).toBe(1);
  await expect(page.locator('details').first()).toContainText('6x de R$10,69');
  expect(await page.locator('.cta-fixo-preco, .cta-final').evaluateAll((els) =>
    els.filter((el) => (el.textContent || '').includes('10,69')).length)).toBe(0);
});

test('cta final: copy de garantia e respiro do whatsapp', async ({ page }) => {
  await page.goto(PAGE);
  await expect(page.locator('.cta-final h2')).toContainText('funcionar para você');
  await expect(page.locator('.cta-final')).toContainText('7 dias para testar tudo');
  await expect(page.locator('.cta-final')).toContainText('reembolso total, sem perguntas');
  await expect(page.locator('.cta-final .cta-garantia')).toHaveCount(0);
  const len = ((await page.locator('.cta-final p').nth(1).textContent()) || '').length;
  expect(len).toBeLessThanOrEqual(175);
  const h2cor = await page.locator('.cta-final h2').evaluate((el) => getComputedStyle(el).color);
  expect(h2cor).toBe('rgb(255, 255, 255)');
  const mt = await page.locator('.cta-final .duvida').evaluate((el) => parseFloat(getComputedStyle(el).marginTop));
  expect(mt).toBeGreaterThanOrEqual(20);
});

test('mobile: antes/depois em 1 quadrado rotativo', async ({ page, isMobile }) => {
  test.skip(!isMobile, 'somente mobile');
  await page.goto(PAGE);
  await page.locator('.ba-grade').scrollIntoViewIfNeeded();
  const visBa = await page.locator('.ba-grade .ba').evaluateAll((els) =>
    els.filter((el) => getComputedStyle(el).display !== 'none').length);
  expect(visBa).toBe(1);
  await expect(page.locator('#ba-mobile-nav')).toBeVisible();
  expect(await page.locator('#ba-dots button').count()).toBe(5);
  await expect(page.locator('#ba-contador')).toContainText('1 de 5');
  await page.waitForFunction(
    () => document.getElementById('ba-contador').textContent.includes('2 de 5'),
    null,
    { timeout: 9000 }
  );
  await page.locator('#ba-dots button').nth(3).click();
  await expect(page.locator('#ba-contador')).toContainText('4 de 5');
});

test('mobile: depoimentos 1 por vez em rotacao', async ({ page, isMobile }) => {
  test.skip(!isMobile, 'somente mobile');
  await page.goto(PAGE);
  await page.locator('#carrossel-depo').scrollIntoViewIfNeeded();
  const vis = await page.locator('#carrossel-depo figure:not([aria-hidden])').evaluateAll((els) =>
    els.filter((el) => getComputedStyle(el).display !== 'none').length);
  expect(vis).toBe(1);
  await expect(page.locator('#depo-nav')).toBeVisible();
  expect(await page.locator('#depo-dots button').count()).toBe(8);
  const c1 = await page.locator('#depo-contador').textContent();
  await page.waitForFunction(
    (x) => document.getElementById('depo-contador').textContent !== x,
    c1,
    { timeout: 9000 }
  );
  await page.locator('#depo-dots button').nth(4).click();
  await expect(page.locator('#depo-contador')).toContainText('5 de 8');
  const imgVis = await page.locator('#carrossel-depo figure.ativo img').getAttribute('src');
  expect(imgVis).toContain('dp5.jpg');
  await page.locator('#depo-nav [data-nav="next"]').click();
  await expect(page.locator('#depo-contador')).toContainText('6 de 8');
});

test('mobile: grupo VIP 1 por vez em rotacao', async ({ page, isMobile }) => {
  test.skip(!isMobile, 'somente mobile');
  await page.goto(PAGE);
  await page.locator('.carrossel.grupo').scrollIntoViewIfNeeded();
  const vis = await page.locator('.carrossel.grupo figure:not([aria-hidden])').evaluateAll((els) =>
    els.filter((el) => getComputedStyle(el).display !== 'none').length);
  expect(vis).toBe(1);
  await expect(page.locator('#grupo-nav')).toBeVisible();
  expect(await page.locator('#grupo-dots button').count()).toBe(5);
  const c1 = await page.locator('#grupo-contador').textContent();
  await page.waitForFunction(
    (x) => document.getElementById('grupo-contador').textContent !== x,
    c1,
    { timeout: 10000 }
  );
  await page.locator('#grupo-nav [data-nav="prev"]').click();
  const c2 = await page.locator('#grupo-contador').textContent();
  expect(c2).toMatch(/[1-5] de 5/);
});

test('mobile: pausar carrosseis congela depoimentos e grupo', async ({ page, isMobile }) => {
  test.skip(!isMobile, 'somente mobile');
  await page.goto(PAGE);
  await page.locator('#carrossel-depo').scrollIntoViewIfNeeded();
  await page.locator('button[data-pausa="carrossel"]').click();
  const d1 = await page.locator('#depo-contador').textContent();
  const g1 = await page.locator('#grupo-contador').textContent();
  await page.waitForTimeout(5500);
  expect(await page.locator('#depo-contador').textContent()).toBe(d1);
  expect(await page.locator('#grupo-contador').textContent()).toBe(g1);
  await expect(page.locator('button[data-pausa="carrossel"]')).toContainText('Retomar');
});
