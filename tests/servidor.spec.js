// Testes contra o servidor PHP de verdade (container Docker).
// Cobre o que o preview estatico nao executa: oferta por ?o=, repasse do
// canal pro checkout, painel da proprietaria, cookie de sessao, antibrute e
// os bloqueios de .htaccess.
//
// A senha do painel vem de PAINEL_SENHA (fora do repo). Sem ela, os testes
// de painel sao pulados — nunca commitar senha.
const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

const SENHA = process.env.PAINEL_SENHA;
const DADOS = path.join(__dirname, '..', 'nova-landing', 'private', 'dados');
const TENTATIVAS = path.join(DADOS, 'tentativas.json');

// ---------------------------------------------------------------- landing

test('landing servida pelo PHP mostra a oferta padrão', async ({ page }) => {
  await page.goto('/');
  await expect(page).toHaveTitle(/Pilates em Casa/);
  await expect(page.locator('h1')).toBeVisible();
  await expect(page.locator('.oferta-card .por')).toContainText('57');
});

test('checkout recebe o canal quando vem ?src=', async ({ page }) => {
  await page.goto('/?src=ig-bio');
  const href = await page.locator('a.btn[href*="pay.kiwify"]').first().getAttribute('href');
  expect(href).toContain('src=ig-bio');
  // e a visita fica registrada para o painel
  const log = path.join(DADOS, 'acessos.jsonl');
  expect(fs.existsSync(log)).toBe(true);
  const linhas = fs.readFileSync(log, 'utf8').trim().split('\n');
  expect(linhas.some((l) => l.includes('"canal":"ig-bio"'))).toBe(true);
});

test('?o= inválido cai na oferta padrão e não vira página secreta', async ({ request }) => {
  const r = await request.get('/?o=inexistente');
  expect(r.status()).toBe(200);
  expect(await r.text()).toContain('por&nbsp;R$&nbsp;57');
  // código inexistente NÃO pode virar link secreto: nem header nem meta
  // de noindex. Página indexável = ausência desses dois.
  expect((r.headers()['x-robots-tag'] || '')).not.toContain('noindex');
  expect(await r.text()).not.toContain('content="noindex, nofollow"');
});

test('oferta padrão explícita (?o=padrao) funciona', async ({ page }) => {
  await page.goto('/?o=padrao');
  await expect(page.locator('.oferta-card .por')).toContainText('57');
});

// ------------------------------------------------------------ proteções

test('arquivos internos respondem 403', async ({ request }) => {
  const alvos = [
    '/private/ofertas.php',
    '/private/carrinhos.php',
    '/private/rastreio.php',
    '/private/dados/acessos.jsonl',
    '/painel/apoio.php',
    '/painel/config.php',
    '/painel/partes/topo.php',
  ];
  for (const a of alvos) {
    const r = await request.get(a);
    expect(r.status(), `${a} deveria ser 403`).toBe(403);
  }
});

test('páginas legais respondem 200', async ({ request }) => {
  for (const a of ['/privacidade.html', '/termos.html']) {
    expect((await request.get(a)).status()).toBe(200);
  }
});

test('página renderizada só carrega o checkout da oferta ativa', async ({ request }) => {
  const html = await (await request.get('/')).text();
  // O HTML renderizado PRECISA conter o checkout da oferta ativa — é assim que
  // o botão funciona. O que não pode é vazar o checkout de uma oferta secreta
  // junto. Como só há a 'padrao' cadastrada, qualquer código extra = vazamento.
  const codigos = [...new Set([...html.matchAll(/pay\.kiwify\.com\.br\/([A-Za-z0-9]+)/g)].map((m) => m[1]))];
  expect(codigos).toHaveLength(1);
});

// -------------------------------------------------------------- painel

test('painel exige senha e não é indexável', async ({ page }) => {
  const r = await page.goto('/painel/');
  expect(r.status()).toBe(200);
  await expect(page.locator('#senha')).toBeVisible();
  const html = await page.content();
  expect(html).toContain('content="noindex, nofollow"');
});

test('CSRF é exigido: POST sem token não entra', async ({ request }) => {
  const r = await request.post('/painel/', { form: { acao: 'entrar', senha: SENHA || 'x' } });
  // sem token válido o login é recusado e a tela de login continua
  expect(await r.text()).toContain('name="senha"');
});

test('cookie de sessão sai HttpOnly + SameSite=Strict', async ({ request }) => {
  const r = await request.post('/painel/', { form: { acao: 'entrar', senha: 'errada-para-nao-entrar' } });
  const setCookie = r.headersArray()
    .filter((h) => h.name.toLowerCase() === 'set-cookie')
    .map((h) => h.value);
  expect(setCookie.length).toBeGreaterThan(0);
  const cookie = setCookie.join(' | ');
  expect(cookie).toMatch(/HttpOnly/);
  expect(cookie).toMatch(/SameSite=Strict/);
  // 'secure' só aparece sob HTTPS; localmente o servidor é http, então a
  // ausência é esperada — o que não pode é aparecer como falso.
  expect(cookie).not.toMatch(/;\s*secure\s*;?\s*$/i);
});

test.describe('com senha do painel', () => {
  test.skip(!SENHA, 'defina PAINEL_SENHA para rodar os testes de painel');

  async function login(page) {
    await page.goto('/painel/');
    await page.fill('#senha', SENHA);
    await page.click('button[type=submit]');
    await page.waitForSelector('h1:has-text("Painel de Links")', { timeout: 15000 });
  }

  test('entra com a senha e vê as seções', async ({ page }) => {
    await login(page);
    await expect(page.locator('h2:has-text("Cliques por canal")')).toBeVisible();
    await expect(page.locator('h2:has-text("Carrinhos")')).toBeVisible();
    await expect(page.locator('h2:has-text("Links por canal")')).toBeVisible();
  });

  test('carrinho padrão aparece com os preços da Kiwify', async ({ page }) => {
    await login(page);
    const linha = page.locator('tr', { hasText: 'padrao' }).first();
    await expect(linha).toContainText('R$ 97');
    await expect(linha).toContainText('R$ 57');
    await expect(linha).toContainText('6x de R$10,69');
  });

  test('criar link gera URL com ?src= e some ao apagar', async ({ page }) => {
    await login(page);
    const canal = 'teste-automatizado';

    // O formulario de link personalizado virou <details> guardado por padrao
    // (reduzia a altura do painel no celular). Abrir antes de preencher.
    await page.click('details#links > summary');
    await expect(page.locator('details#links')).toHaveAttribute('open', '');

    await page.fill('#canal', canal);
    await page.fill('#rotulo', 'Teste automatizado');
    await page.click('button:has-text("Salvar link")');
    await page.waitForLoadState('domcontentloaded');

    const linha = page.locator('tr', { hasText: canal }).last();
    await expect(linha).toBeVisible();
    const url = await linha.locator('.linkbox').textContent();
    expect(url).toMatch(/^https?:\/\/[^/]+\/\?src=/);
    expect(url).toContain(`?src=${canal}`);
    // o canal também entra no relatório de cliques
    await expect(page.locator('h2:has-text("Cliques por canal")')).toBeVisible();

    // apaga (o botão exige 2 toques: revelar + confirmar) e confere
    await linha.locator('summary:has-text("Apagar")').click();
    await linha.locator('button:has-text("Sim, apagar")').click();
    await page.waitForLoadState('domcontentloaded');
    await expect(page.locator('tr', { hasText: canal })).toHaveCount(0);
  });

  test('sair encerra a sessão', async ({ page }) => {
    await login(page);
    await page.click('a[href="?sair=1"]');
    await expect(page.locator('#senha')).toBeVisible();
  });

  test('tentativas erradas aparecem no painel e a limpeza é só delas', async ({ page }) => {
    // Uma visita de verdade: ela TEM que sobreviver à limpeza das tentativas.
    await page.goto('/?src=teste-limpeza');
    const ACESSOS = path.join(DADOS, 'acessos.jsonl');

    // Tentativas sintéticas, no mesmo formato que o login grava: um
    // identificador abaixo do limite e outro já bloqueado (6 falhas na hora).
    const agora = Math.floor(Date.now() / 1000);
    fs.mkdirSync(DADOS, { recursive: true });
    fs.writeFileSync(TENTATIVAS, JSON.stringify({
      a1b2c3d4e5f60718: [agora - 300, agora - 240],
      deadbeefdeadbeef: [agora - 120, agora - 90, agora - 60, agora - 30, agora - 20, agora - 10],
    }));

    try {
      await login(page);
      await expect(page.locator('h2:has-text("Tentativas de login")')).toBeVisible();
      await expect(page.locator('b', { hasText: 'Tentativas erradas registradas' })).toContainText('8'); // 2 + 6
      await expect(page.locator('.pill.perigo', { hasText: 'em bloqueio agora' })).toContainText('1');

      const bloqueado = page.locator('tr', { hasText: 'deadbeef' });
      await expect(bloqueado.locator('td[data-label="Tentativas"]')).toHaveText('6');
      await expect(bloqueado.locator('td[data-label="Situação"]')).toContainText('Bloqueado até');
      await expect(page.locator('tr', { hasText: 'a1b2c3d4' }).locator('td[data-label="Situação"]')).toContainText('sem bloqueio');

      // Limpa SÓ as tentativas: o registro sai e o log de visitas fica.
      await page.locator('summary:has-text("Apagar tentativas de login")').click();
      await page.locator('button:has-text("Sim, apagar as tentativas")').click();
      await page.waitForLoadState('domcontentloaded');
      await expect(page.locator('.flash')).toContainText('tentativas de login');
      await expect(page.locator('.vazio', { hasText: 'Nenhuma tentativa errada' })).toBeVisible();
      await expect(page.locator('tr', { hasText: 'deadbeef' })).toHaveCount(0);
      expect(fs.readFileSync(ACESSOS, 'utf8')).toContain('teste-limpeza');
    } finally {
      fs.rmSync(TENTATIVAS, { force: true });
    }
  });
});

// ------------------------------------------------------------ antibrute

// Por último e com cleanup: o bloqueio é por IP e o container é o mesmo, então
// contaminaria os testes de login se rodassem depois. Sem 'serial' no escopo do
// arquivo — isso faz uma falha cascatear e pular o resto da suite.
test('senha errada repetida bloqueia o acesso', async ({ page }) => {
  fs.rmSync(TENTATIVAS, { force: true });

  try {
    await page.goto('/painel/');
    // 5+ tentativas erradas (o limite de bloqueio começa na 5ª)
    for (let i = 0; i < 6; i++) {
      await page.fill('#senha', `errada-${i}`);
      await page.click('button[type=submit]');
      await page.waitForLoadState('domcontentloaded');
    }
    await expect(page.locator('.erro')).toContainText(/Muitas tentativas|demais/i);
  } finally {
    // devolve o IP ao normal para os próximos testes
    fs.rmSync(TENTATIVAS, { force: true });
  }
});