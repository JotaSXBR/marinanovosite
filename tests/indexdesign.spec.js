const { test, expect } = require('@playwright/test');

const PAGE = '/nova-landing/indexdesign.html';

test('indexdesign carrega sem erros', async ({ page }) => {
  const errors = [];
  page.on('pageerror', (e) => errors.push(String(e)));
  await page.goto(PAGE);
  await expect(page).toHaveTitle(/Pilates em Casa/);
  await expect(page.locator('h1')).toHaveCount(1);
  expect(errors).toEqual([]);
});

test('hero com palavra em degrade', async ({ page }) => {
  await page.goto(PAGE);
  await expect(page.locator('.hero h1 .grad')).toContainText('rotina real');
});

test('botoes com glow', async ({ page }) => {
  await page.goto(PAGE);
  const sombra = await page.locator('.hero .btn').evaluate((el) => getComputedStyle(el).boxShadow);
  expect(sombra).not.toBe('none');
});

test('pill de desconto honesto', async ({ page }) => {
  await page.goto(PAGE);
  await expect(page.locator('.off-pill')).toContainText('−41%');
});

test('fileira de confianca na oferta', async ({ page }) => {
  await page.goto(PAGE);
  const itens = await page.locator('.trust li').evaluateAll((els) => els.map((el) => el.textContent));
  expect(itens.length).toBe(3);
  expect(itens.join(' ')).toContain('7 dias de garantia');
});

test('stagger aplicado nos cards', async ({ page }) => {
  await page.goto(PAGE);
  await expect(page.locator('.trans-lista li.animar').first()).toBeAttached();
  await expect(page.locator('.bento .cel.animar').first()).toBeAttached();
});

test('digitacao rotativa com fallback acessivel', async ({ page }) => {
  await page.goto(PAGE);
  await page.locator('.fecho-cta').scrollIntoViewIfNeeded();
  await expect(page.locator('#fecho-typer')).toBeAttached();
  await expect(page.locator('.fecho-cta .sr')).toContainText('resultado em poucos dias');
  await page.waitForFunction(
    () => {
      const t = document.getElementById('fecho-typer').textContent;
      return t.length > 0 && t.length < 60;
    },
    null,
    { timeout: 15000 }
  );
});

test('digitacao desligada em movimento reduzido', async ({ browser }) => {
  const ctx = await browser.newContext({ reducedMotion: 'reduce' });
  const page = await ctx.newPage();
  await page.goto(PAGE);
  await page.locator('.fecho-cta').scrollIntoViewIfNeeded();
  await page.waitForTimeout(2000);
  expect(await page.locator('#fecho-typer').textContent()).toContain('resultado em poucos dias');
  await ctx.close();
});

test('mobile: dots com alvo de 40px', async ({ page, isMobile }) => {
  test.skip(!isMobile, 'somente mobile');
  await page.goto(PAGE);
  await page.locator('.ba-grade').scrollIntoViewIfNeeded();
  const caixa = await page.locator('#ba-dots button').first().boundingBox();
  expect(caixa.width).toBeGreaterThanOrEqual(40);
  expect(caixa.height).toBeGreaterThanOrEqual(40);
});

test('mobile: micro da oferta alinhado a esquerda', async ({ page, isMobile }) => {
  test.skip(!isMobile, 'somente mobile');
  await page.goto(PAGE);
  await page.locator('#oferta').scrollIntoViewIfNeeded();
  expect(await page.locator('.oferta-card .micro').evaluate((el) => getComputedStyle(el).textAlign)).toBe('left');
});

test('mobile: bonus expansivel mostra 3 e abre todos', async ({ page, isMobile }) => {
  test.skip(!isMobile, 'somente mobile');
  await page.goto(PAGE);
  await page.locator('#bento-bonus').scrollIntoViewIfNeeded();
  expect(await page.locator('#bento-bonus .cel').count()).toBe(9);
  const visAntes = await page.locator('#bento-bonus .cel').evaluateAll((els) =>
    els.filter((el) => getComputedStyle(el).display !== 'none').length);
  expect(visAntes).toBe(3);
  await expect(page.locator('#bento-toggle')).toBeVisible();
  await expect(page.locator('#bento-toggle')).toHaveAttribute('aria-expanded', 'false');
  await page.locator('#bento-toggle').click();
  await expect(page.locator('#bento-toggle')).toHaveAttribute('aria-expanded', 'true');
  await expect(page.locator('#bento-toggle')).toContainText('Mostrar menos');
  const visDepois = await page.locator('#bento-bonus .cel').evaluateAll((els) =>
    els.filter((el) => getComputedStyle(el).display !== 'none').length);
  expect(visDepois).toBe(9);
});

test('mobile: barra fixa colada na base sem flutuar', async ({ page, isMobile }) => {
  test.skip(!isMobile, 'somente mobile');
  await page.goto(PAGE);
  await page.locator('#resultados').scrollIntoViewIfNeeded();
  await expect(page.locator('body')).toHaveClass(/com-cta/, { timeout: 5000 });
  await page.waitForFunction(() => {
    const el = document.querySelector('.cta-fixo');
    const r = el.getBoundingClientRect();
    return Math.abs(window.innerHeight - r.bottom) < 2;
  }, null, { timeout: 5000 });
  const barra = await page.locator('.cta-fixo').evaluate((el) => {
    const r = el.getBoundingClientRect();
    const st = getComputedStyle(el);
    return { left: r.left, bottom: Math.round(window.innerHeight - r.bottom), radius: st.borderBottomLeftRadius };
  });
  expect(barra.left).toBe(0);
  expect(Math.abs(barra.bottom)).toBeLessThanOrEqual(1);
  expect(barra.radius).toBe('0px');
});

test('desktop: bonus todo visivel sem botao', async ({ page, isMobile }) => {
  test.skip(isMobile, 'somente desktop');
  await page.goto(PAGE);
  await page.locator('#bento-bonus').scrollIntoViewIfNeeded();
  const vis = await page.locator('#bento-bonus .cel').evaluateAll((els) =>
    els.filter((el) => getComputedStyle(el).display !== 'none').length);
  expect(vis).toBe(9);
  await expect(page.locator('#bento-toggle')).toBeHidden();
});

test('modo claro mantem contraste AA', async ({ browser }) => {
  const ctx = await browser.newContext({ colorScheme: 'light' });
  const page = await ctx.newPage();
  await page.goto(PAGE);
  const min = await page.evaluate(() => {
    const lum = (c) => {
      const m = c.match(/[\d.]+/g).map(Number);
      const f = (v) => { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); };
      const [r, g, b] = [f(m[0]), f(m[1]), f(m[2])];
      return 0.2126 * r + 0.7152 * g + 0.0722 * b;
    };
    const cr = (a, b) => {
      const x = lum(a), y = lum(b);
      const [hi, lo] = x > y ? [x, y] : [y, x];
      return (hi + 0.05) / (lo + 0.05);
    };
    const pares = [
      ['body', 'body'],
      ['.oferta-card .por', '.oferta-card'],
      ['.trans-painel .fecho strong', '.trans-painel'],
      ['.faq-lista summary', '.faq-lista details'],
    ];
    return Math.min(...pares.map(([ts, bs]) => {
      const t = document.querySelector(ts), b = document.querySelector(bs);
      return cr(getComputedStyle(t).color, getComputedStyle(b).backgroundColor);
    }));
  });
  expect(min).toBeGreaterThanOrEqual(4.5);
  await ctx.close();
});
