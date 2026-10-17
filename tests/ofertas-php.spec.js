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
  // A lista de carrinhos vem de private/ (base no git + edicoes do painel),
  // nunca embutida no index.php.
  expect(fonte).toContain("require __DIR__ . '/private/carrinhos.php'");
  expect(fonte).toContain('$ofertas = carrinhosTodos()');
  expect(fonte).toContain("$pedir('o')");
  // secreta nunca indexa (meta + header)
  expect(fonte).toContain('name="robots" content="noindex, nofollow"');
  expect(fonte).toContain('X-Robots-Tag');
  // canonical do fonte tem que ser ABSOLUTO: com href="./" o audit `canonical`
  // do Lighthouse reprova ("Is not an absolute URL") — foi o caso ate 17/10/2026.
  expect(fonte).not.toContain('rel="canonical" href="./"');
  expect(fonte).toContain("rel=\"canonical\" href=\"<?= e($baseUrl . '/') ?>\"");
});

test('private/ bloqueado em duas camadas (htaccess + guarda no codigo)', async ({ page }) => {
  const ht = await (await page.request.get('/nova-landing/private/.htaccess')).text();
  expect(ht).toContain('Require all denied');
  const cadastro = await (await page.request.get('/nova-landing/private/ofertas.php')).text();
  expect(cadastro).toContain('OFERTAS_BOOT');
  expect(cadastro).toContain('http_response_code(403)');
  // carrinhos.php (gestao pelo painel) tem a mesma guarda
  const carrinhos = await (await page.request.get('/nova-landing/private/carrinhos.php')).text();
  expect(carrinhos).toContain('OFERTAS_BOOT');
  expect(carrinhos).toContain('http_response_code(403)');
  // .htaccess raiz: index.php é o principal e ?o= nunca usa cache
  const raiz = await (await page.request.get('/nova-landing/.htaccess')).text();
  expect(raiz).toContain('DirectoryIndex index.php index.html');
  expect(raiz).toContain('o=');
  // carrinhos.php tambem precisa estar coberto pela FilesMatch do .htaccess raiz
  expect(raiz).toContain('carrinhos');
});

// Guarda de sincronia do artefato: index.html precisa ser o espelho estático
// do corpo do index.php (oferta 'padrao'). Sem isso, a landing de produção e o
// fallback divergem em silêncio — foi o que aconteceu com o objeto JS OFERTAS,
// que sobreviveu no index.html muito depois de o index.php passar a resolver a
// oferta no servidor. Esta é a guarda que "roda no CI" — na prática, no
// `npm test`, que é o que executa de verdade (a Action está sem créditos).
test('index.html espelha o corpo do index.php (sem drift do artefato)', async ({ page }) => {
  const php = await (await page.request.get('/nova-landing/index.php')).text();
  const html = await (await page.request.get('/nova-landing/index.html')).text();

  // 1. o snapshot não carrega o objeto que vazava as ofertas no fonte
  expect(html).not.toContain('var OFERTAS');

  // 2. toda linha ESTÁTICA do corpo do index.php existe no index.html, na mesma
  //    ordem. Ficam de fora as linhas com PHP (<?) e o meta de noindex, que só
  //    a oferta secreta renderiza — as demais o index.php já resolve sozinho.
  const corpo = php.slice(php.indexOf('<!DOCTYPE html>'));
  const estaticas = corpo
    .split('\n')
    .filter((l) => !l.includes('<?'))
    .filter((l) => l.trim() !== '<meta name="robots" content="noindex, nofollow">')
    .map((l) => l.trim())
    .filter((l) => l !== '');

  let cursor = 0;
  for (const linha of estaticas) {
    const pos = html.indexOf(linha, cursor);
    expect(pos, `linha do index.php ausente/fora de ordem no index.html: ${linha.slice(0, 90)}`).toBeGreaterThanOrEqual(0);
    cursor = pos + linha.length;
  }
});
