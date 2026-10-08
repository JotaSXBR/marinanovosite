const { test, expect } = require('@playwright/test');

// Ofertas resolvidas no servidor: estas checagens rodam sobre o FONTE
// (o preview local via python não executa PHP — e é exatamente isso que
// o teste prova: nem o fonte do index.php vaza as demais ofertas).
const PHP = '/nova-landing/index.php';

test('index.php nao vaza checkout nem lista de ofertas no fonte', async ({ page }) => {
  const res = await page.request.get(PHP);
  expect(res.status()).toBe(200);
  const fonte = await res.text();
  // nenhum checkout hardcoded: todos saem de private/ofertas.php via $of
  expect(fonte).not.toMatch(/pay\.kiwify\.com\.br\/[A-Za-z0-9]+/);
  // sem o objeto JS que expunha tudo no Ver código-fonte
  expect(fonte).not.toContain('var OFERTAS');
  // resolve no servidor via ?o= (com compat para ?oferta= antigo)
  expect(fonte).toContain("require __DIR__ . '/private/ofertas.php'");
  expect(fonte).toContain("$pedir('o')");
  // secreta nunca indexa (meta + header)
  expect(fonte).toContain('name="robots" content="noindex, nofollow"');
  expect(fonte).toContain('X-Robots-Tag');
});

test('private/ bloqueado em duas camadas (htaccess + guarda no codigo)', async ({ page }) => {
  const ht = await (await page.request.get('/nova-landing/private/.htaccess')).text();
  expect(ht).toContain('Require all denied');
  const cadastro = await (await page.request.get('/nova-landing/private/ofertas.php')).text();
  expect(cadastro).toContain('OFERTAS_BOOT');
  expect(cadastro).toContain('http_response_code(403)');
  // .htaccess raiz: index.php é o principal e ?o= nunca usa cache
  const raiz = await (await page.request.get('/nova-landing/.htaccess')).text();
  expect(raiz).toContain('DirectoryIndex index.php index.html');
  expect(raiz).toContain('o=');
});
