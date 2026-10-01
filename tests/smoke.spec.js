const { test, expect } = require('@playwright/test');

const PAGE = '/nova-landing/index.html';
const CHECKOUT = 'https://pay.kiwify.com.br/U4n0qN7';

test('titulo, H1 e CTA de checkout', async ({ page }) => {
  const errors = [];
  page.on('pageerror', (e) => errors.push(String(e)));
  await page.goto(PAGE);
  await expect(page).toHaveTitle(/Pilates em Casa/);
  await expect(page.locator('h1')).toHaveCount(1);
  await expect(page.locator('h1')).toContainText('20 minutos');
  const ctas = page.locator(`a.btn[href="${CHECKOUT}"]`);
  expect(await ctas.count()).toBeGreaterThan(0);
  expect(errors).toEqual([]);
});

test('oferta com preco, sem video antigo', async ({ page }) => {
  await page.goto(PAGE);
  await expect(page.locator('#video-moldura')).toHaveCount(0);
  await expect(page.locator('#oferta')).toContainText('R$ 57');
  await expect(page.locator('#oferta')).toContainText('R$ 97');
  await expect(page.locator('#oferta')).toContainText('R$ 710');
  for (const slug of ['privacidade.html', 'termos.html']) {
    const res = await page.request.get(`/nova-landing/${slug}`);
    expect(res.status(), slug).toBe(200);
  }
});

test('todas as imagens locais retornam 200', async ({ page }) => {
  await page.goto(PAGE);
  // usa o atributo src declarado (nao depende de lazy-load da viewport)
  const srcs = await page.locator('img').evaluateAll((imgs, base) =>
    imgs.map((i) => new URL(i.getAttribute('src'), base).href),
    page.url()
  );
  const locais = [...new Set(srcs)].filter((s) => s.includes('127.0.0.1'));
  expect(locais.length).toBeGreaterThan(10);
  for (const src of locais) {
    const res = await page.request.get(src);
    expect(res.status(), src).toBe(200);
  }
  // hero sem foto: confere imagem da bio carregada apos rolagem
  await page.locator('.bio img').scrollIntoViewIfNeeded();
  await page.waitForFunction(() => {
    const i = document.querySelector('.bio img');
    return i && i.complete && i.naturalWidth > 0;
  });
  const heroW = await page.locator('.bio img').evaluate((i) => i.naturalWidth);
  expect(heroW).toBeGreaterThan(0);
});

test('carrossel nao quebra o layout ao rolar', async ({ page }) => {
  await page.goto(PAGE);
  const tracks = page.locator('.carrossel');
  expect(await tracks.count()).toBe(2);
  for (let i = 0; i < 2; i++) {
    await tracks.nth(i).evaluate((el) => el.scrollTo({ left: el.scrollWidth }));
  }
  await page.waitForTimeout(300);
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
  expect(overflow).toBeLessThanOrEqual(1);
  const crops = await page.locator('.carrossel img').evaluateAll((imgs) =>
    imgs.map((i) => {
      const r = i.getBoundingClientRect();
      const expected = (r.width * i.naturalHeight) / i.naturalWidth;
      return Math.abs(r.height - expected);
    })
  );
  expect(crops.length).toBeGreaterThan(10);
  for (const d of crops) expect(d).toBeLessThan(3);
});

test('carrosseis avancam sozinhos em loop', async ({ page }) => {
  await page.goto(PAGE);
  await page.locator('.carrossel').first().scrollIntoViewIfNeeded();
  const antes = await page.locator('.carrossel').first().evaluate((el) => el.scrollLeft);  await page.waitForFunction(
    (x) => document.querySelector('.carrossel').scrollLeft > x + 5,
    antes,
    { timeout: 8000 }
  );
  // o avancar nao pode mover a rolagem da pagina
  const y1 = await page.evaluate(() => window.scrollY);
  await page.waitForTimeout(4500);
  const y2 = await page.evaluate(() => window.scrollY);
  expect(Math.abs(y2 - y1)).toBeLessThan(4);
});

test('FAQ abre e ancora de contato existem', async ({ page }) => {
  await page.goto(PAGE);
  const first = page.locator('details').first();
  await first.locator('summary').click();
  await expect(first).toHaveAttribute('open', '');
  await expect(page.locator('a[href*="instagram.com/pilatesnaparede"]')).toHaveCount(1);
  await expect(page.locator('a[href*="whatsapp"]')).toBeTruthy();
});
