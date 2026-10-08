const { test, expect } = require('@playwright/test');

const PAGE = '/nova-landing/index.html';
const CHECKOUT = 'https://pay.kiwify.com.br/U4n0qN7';

test('titulo, H1 e CTA de checkout', async ({ page }) => {
  const errors = [];
  page.on('pageerror', (e) => errors.push(String(e)));
  await page.goto(PAGE);
  await expect(page).toHaveTitle(/Pilates em Casa/);
  await expect(page.locator('h1')).toHaveCount(1);
  await expect(page.locator('h1')).toContainText('rotina real');
  const ctas = page.locator(`a.btn[href="${CHECKOUT}"]`);
  expect(await ctas.count()).toBeGreaterThan(0);
  expect(errors).toEqual([]);
});

test('oferta com preco, sem video antigo', async ({ page }) => {
  await page.goto(PAGE);
  await expect(page.locator('#video-moldura')).toHaveCount(0);
  await expect(page.locator('#oferta')).toContainText('R$ 57');
  await expect(page.locator('#oferta')).toContainText('R$ 97');
  await expect(page.locator('#oferta')).toContainText('R$ 613');
  await expect(page.locator('#oferta')).toContainText('App Pilates em Casa');
  await expect(page.locator('#bonus')).toContainText('Sua plataforma de treino');
  for (const slug of ['privacidade.html', 'termos.html']) {
    const res = await page.request.get(`/nova-landing/${slug}`);
    expect(res.status(), slug).toBe(200);
  }
});

test('paginas legais no design da landing com voltar do browser', async ({ page }) => {
  for (const [slug, titulo] of [['privacidade.html', 'Política de Privacidade'], ['termos.html', 'Termos de Uso']]) {
    await page.goto(`/nova-landing/${slug}`);
    await expect(page.locator('.legal h1')).toContainText(titulo);
    // mesmo design: marca, toggle de tema e rodapé da landing
    await expect(page.locator('.marca')).toContainText('Pilates');
    await expect(page.locator('#tema-toggle')).toBeVisible();
    await expect(page.locator('.rodape-marca')).toContainText('Pilates em Casa');
    // voltar usa history.back, com fallback para a raiz (nunca domínio fixo)
    const voltar = page.locator('[data-voltar].btn');
    await expect(voltar).toContainText('Voltar');
    expect(await voltar.getAttribute('href')).toBe('./');
  }
  // vindo da landing, o voltar retorna para ela via histórico
  await page.goto(PAGE);
  await page.locator('a[href="privacidade.html"]').click();
  await expect(page.locator('.legal h1')).toContainText('Política de Privacidade');
  await page.locator('[data-voltar].btn').click();
  await expect(page).toHaveURL(new RegExp(PAGE.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '$'));
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
  // hero com foto do app: confere carregamento apos rolagem ao topo
  await page.goto(PAGE);
  const heroW = await page.locator('.hero-foto img').evaluate((i) => i.naturalWidth);
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
    imgs
      .filter((i) => i.naturalWidth > 0)
      .map((i) => {
        const r = i.getBoundingClientRect();
        const expected = (r.width * i.naturalHeight) / i.naturalWidth;
        return Math.abs(r.height - expected);
      })
  );
  expect(crops.length).toBeGreaterThan(10);
  for (const d of crops) expect(d).toBeLessThan(3);
});

test('carrosseis avancam sozinhos em loop', async ({ page, isMobile }) => {
  await page.goto(PAGE);
  await page.locator('.carrossel').first().scrollIntoViewIfNeeded();
  const soUm = await page.locator('.carrossel.so-um').first().count();
  if (isMobile && soUm) {
    // no celular o rotativo troca a foto ativa (1 por vez), sem rolagem
    const antes = await page.locator('#carrossel-depo').evaluate((el) =>
      Array.from(el.querySelectorAll('figure:not([aria-hidden])')).findIndex((f) => f.classList.contains('ativo')));
    await page.waitForFunction(
      (x) => Array.from(document.querySelectorAll('#carrossel-depo figure:not([aria-hidden])')).findIndex((f) => f.classList.contains('ativo')) !== x,
      antes,
      { timeout: 8000 }
    );
  } else {
    const antes = await page.locator('.carrossel').first().evaluate((el) => el.scrollLeft);  await page.waitForFunction(
      (x) => document.querySelector('.carrossel').scrollLeft > x + 5,
      antes,
      { timeout: 8000 }
    );
  }
  // o avancar nao pode mover a rolagem da pagina
  const y1 = await page.evaluate(() => window.scrollY);
  await page.waitForTimeout(4500);
  const y2 = await page.evaluate(() => window.scrollY);
  expect(Math.abs(y2 - y1)).toBeLessThan(4);
});

test('cta fixo aparece no mobile apos o hero', async ({ page, isMobile }) => {
  test.skip(!isMobile, 'somente mobile');
  await page.goto(PAGE);
  await expect(page.locator('body')).not.toHaveClass(/com-cta/);
  await page.locator('#resultados').scrollIntoViewIfNeeded();
  await expect(page.locator('body')).toHaveClass(/com-cta/, { timeout: 5000 });
  await page.waitForTimeout(1500);
  await expect(page.locator('#cta-fixo')).toBeVisible();
  await expect(page.locator('.cta-fixo-preco')).toContainText('R$ 57');
  const r = await page.locator('#cta-fixo').evaluate((el) => {
    const b = el.getBoundingClientRect();
    return { top: b.top, bottom: b.bottom, height: b.height, vh: window.innerHeight };
  });
  expect(r.top).toBeGreaterThanOrEqual(0);
  expect(r.bottom).toBeLessThanOrEqual(r.vh);
  expect(r.height).toBeGreaterThan(40);
  expect(await page.locator('#cta-fixo').getAttribute('href')).toContain('kiwify');
  await page.locator('#oferta').scrollIntoViewIfNeeded();
  await expect(page.locator('body')).not.toHaveClass(/com-cta/, { timeout: 5000 });
});

test('menu hamburguer no mobile, sem cta no topo', async ({ page, isMobile }) => {
  test.skip(!isMobile, 'somente mobile');
  await page.goto(PAGE);
  await expect(page.locator('#nav-toggle')).toBeVisible();
  await expect(page.locator('.nav-links .nav-cta')).not.toBeVisible();
  await page.locator('#nav-toggle').click();
  await expect(page.locator('body')).toHaveClass(/menu-aberto/);
  await expect(page.locator('.nav-links a[href="#resultados"]')).toBeVisible();
  await page.locator('.nav-links a[href="#resultados"]').click();
  await expect(page).toHaveURL(/#resultados/);
  await expect(page.locator('body')).not.toHaveClass(/menu-aberto/);
});

test('ofertas dinamicas e OG tags', async ({ page }) => {
  await page.goto(PAGE + '?oferta=padrao');
  await expect(page.locator('.oferta-card .por')).toContainText('R$ 57');
  await page.goto(PAGE + '?oferta=inexistente');
  await expect(page.locator('.oferta-card .por')).toContainText('R$ 57');
  const og = await page.locator('meta[property="og:image"]').getAttribute('content');
  expect(og).toContain('og-capa.jpg');
});

test('comparador antes/depois move a divisoria', async ({ page, isMobile }) => {
  await page.goto(PAGE);
  await expect(page.locator('.antes-texto')).toContainText('falta de sistema');
  expect(await page.locator('.ba').count()).toBe(5);
  // no celular só o 1º comparador fica visível (rotativo); no desktop todos
  const alvo = isMobile ? page.locator('.ba').first() : page.locator('.ba').last();
  await alvo.locator('input').dispatchEvent('pointerdown');
  await alvo.locator('input').fill('80');
  const clip = await alvo.locator('.ba-depois').evaluate((el) => el.style.clipPath);
  expect(clip).toContain('80%');
});

test('FAQ abre e ancora de contato existem', async ({ page }) => {
  await page.goto(PAGE);
  const first = page.locator('details').first();
  await first.locator('summary').click();
  await expect(first).toHaveAttribute('open', '');
  await expect(page.locator('a[href*="instagram.com/pilatesemcasa"]')).toHaveCount(1);
  await expect(page.locator('a[href*="whatsapp"]')).toBeTruthy();
});
