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
const BLOQUEIOS = path.join(DADOS, 'bloqueios.json');
const SESSAO = path.join(DADOS, 'sessao.json');
const OFERTAS = path.join(DADOS, 'ofertas.json');

// O estado do antibrute/acesso único é arquivo no SERVIDOR (não no navegador) e
// a suíte roda em workers:1, então ele atravessa os testes. Cada caso parte do
// zero — as regras em si têm testes dedicados (no fim do arquivo).
function limparEstado() {
  for (const f of [TENTATIVAS, BLOQUEIOS, SESSAO]) fs.rmSync(f, { force: true });
}

// O PHP grava array vazio como `[]` e mapa vazio como `{}` — os dois querem
// dizer "sem registro". Arquivo ausente também.
function jsonVazio(arquivo) {
  if (!fs.existsSync(arquivo)) return true;
  const bruto = fs.readFileSync(arquivo, 'utf8').trim();
  if (bruto === '') return true;
  const d = JSON.parse(bruto);
  return Array.isArray(d) ? d.length === 0 : Object.keys(d).length === 0;
}

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
    '/private/dados/bloqueios.json',
    '/private/dados/sessao.json',
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

// Estado limpo no começo de cada rodada: bloqueios/tentativas/acesso são
// arquivos no servidor e sobreviveriam de uma execução para a outra.
test.beforeAll(() => limparEstado());

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

  test.beforeEach(() => limparEstado());

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
    // A barra de seções (visível só no celular, por CSS) precisa apontar para
    // âncoras que existem de verdade no documento.
    const ancoras = await page.locator('.menu-secoes a').evaluateAll((as) => as.map((a) => ({
      href: a.getAttribute('href'),
      alvo: !!document.querySelector(a.getAttribute('href')),
    })));
    expect(ancoras.map((a) => a.href)).toEqual(['#sec-cliques', '#sec-carrinhos', '#sec-links', '#sec-login']);
    expect(ancoras.every((a) => a.alvo)).toBe(true);
  });

  test('carrinho padrão aparece com os preços da Kiwify', async ({ page }) => {
    await login(page);
    const linha = page.locator('tr', { hasText: 'padrao' }).first();
    await expect(linha).toContainText('R$ 97');
    await expect(linha).toContainText('R$ 57');
    await expect(linha).toContainText('6x de R$10,69');
  });

  test('erro de validação reabre o formulário com o que foi digitado', async ({ page }) => {
    await login(page);
    // O `required`/`type=url` do navegador cobre campo vazio e URL sintática;
    // quem recusa "http://" (sem https) é o servidor. Antes, o redirect
    // devolvia o aviso no topo com o formulário FECHADO — e o que ela tinha
    // digitado se perdia (o merge da sessão só valia para o form do carrinho).
    await page.click('details#carrinho > summary');
    await page.fill('#chave', 'teste-erro');
    await page.fill('#rotulo_c', 'Teste erro');
    await page.fill('#checkout', 'http://exemplo.com/x');
    await page.fill('#cheio', '97');
    await page.fill('#preco', '47');
    await page.click('#carrinho button[type=submit]');
    await page.waitForLoadState('domcontentloaded');

    await expect(page.locator('.erro')).toContainText('https://');
    await expect(page.locator('details#carrinho')).toHaveAttribute('open', '');
    await expect(page.locator('#chave')).toHaveValue('teste-erro');
    await expect(page.locator('#rotulo_c')).toHaveValue('Teste erro');
    await expect(page.locator('#checkout')).toHaveValue('http://exemplo.com/x');

    // e nada foi gravado
    const ofertas = fs.existsSync(OFERTAS) ? JSON.parse(fs.readFileSync(OFERTAS, 'utf8')) : {};
    expect(ofertas['teste-erro']).toBeUndefined();
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

  test('tentativas e bloqueios aparecem no painel e a limpeza é só deles', async ({ page }) => {
    // Uma visita de verdade: ela TEM que sobreviver à limpeza das tentativas.
    await page.goto('/?src=teste-limpeza');
    const ACESSOS = path.join(DADOS, 'acessos.jsonl');

    // Estado sintético, no mesmo formato que o login grava: dois identificadores
    // com falhas (um deles bloqueado), um bloqueio permanente e um caso que
    // prova que bloqueio é ESTADO — 6 falhas sem bloqueio gravado não bloqueiam.
    const agora = Math.floor(Date.now() / 1000);
    fs.mkdirSync(DADOS, { recursive: true });
    fs.writeFileSync(TENTATIVAS, JSON.stringify({
      a1b2c3d4e5f60718: [agora - 300, agora - 240],
      deadbeefdeadbeef: [agora - 120, agora - 90, agora - 60, agora - 30, agora - 20, agora - 10],
      cafebabe12341234: [agora - 200, agora - 190, agora - 180, agora - 170, agora - 160, agora - 150],
      feedfacefeedface: [agora - 30],
    }));
    fs.writeFileSync(BLOQUEIOS, JSON.stringify({
      deadbeefdeadbeef: { desde: agora - 120, libera: agora + 86400 - 120, permanente: false, vezes: 1 },
      feedfacefeedface: { desde: agora - 30, libera: 0, permanente: true, vezes: 2 },
    }));

    await login(page);
    await expect(page.locator('h2:has-text("Tentativas de login")')).toBeVisible();
    await expect(page.locator('b', { hasText: 'Tentativas erradas registradas' })).toContainText('15'); // 2+6+6+1
    await expect(page.locator('.tentativas-topo .pill.perigo', { hasText: 'em bloqueio agora' })).toContainText('2');
    await expect(page.locator('.tentativas-topo .pill.perigo', { hasText: 'permanente' })).toContainText('1');

    // A ordem põe o permanente antes de todos.
    await expect(page.locator('#tabela-tentativas tbody tr').first()).toContainText('feedface');

    const bloqueado = page.locator('tr', { hasText: 'deadbeef' });
    await expect(bloqueado.locator('td[data-label="Tentativas"]')).toHaveText('6');
    await expect(bloqueado.locator('td[data-label="Situação"]')).toContainText('Bloqueado até');
    await expect(page.locator('tr', { hasText: 'feedface' }).locator('td[data-label="Situação"]')).toContainText('Bloqueado permanentemente');
    await expect(page.locator('tr', { hasText: 'cafebabe' }).locator('td[data-label="Situação"]')).toContainText('sem bloqueio');
    await expect(page.locator('tr', { hasText: 'a1b2c3d4' }).locator('td[data-label="Situação"]')).toContainText('sem bloqueio');

    // Limpa SÓ as tentativas: registro e bloqueios saem; o log de visitas fica.
    await page.locator('summary:has-text("Apagar tentativas de login")').click();
    await page.locator('button:has-text("Sim, apagar as tentativas")').click();
    await page.waitForLoadState('domcontentloaded');
    await expect(page.locator('.flash')).toContainText('bloqueios apagados');
    await expect(page.locator('.vazio', { hasText: 'Nenhuma tentativa errada' })).toBeVisible();
    await expect(page.locator('tr', { hasText: 'deadbeef' })).toHaveCount(0);
    expect(jsonVazio(BLOQUEIOS)).toBe(true);
    expect(fs.readFileSync(ACESSOS, 'utf8')).toContain('teste-limpeza');
  });

  test('o painel aceita um acesso por vez', async ({ page, browser }) => {
    await login(page);

    // Segundo "aparelho" (outro contexto = outro cookie de sessão), senha CERTA.
    const ctx = await browser.newContext();
    const outro = await ctx.newPage();
    try {
      await outro.goto('/painel/');
      await outro.fill('#senha', SENHA);
      await outro.click('button[type=submit]');
      await expect(outro.locator('.erro')).toContainText('acesso aberto');
      // quem está dentro continua dentro
      await page.goto('/painel/');
      await expect(page.locator('h1:has-text("Painel de Links")')).toBeVisible();

      // Senha certa com o painel ocupado NÃO vira tentativa errada no registro
      expect(jsonVazio(TENTATIVAS)).toBe(true);

      // O primeiro sai → o segundo entra
      await page.click('a[href="?sair=1"]');
      await outro.fill('#senha', SENHA);
      await outro.click('button[type=submit]');
      await expect(outro.locator('h1:has-text("Painel de Links")')).toBeVisible({ timeout: 15000 });
    } finally {
      await ctx.close();
    }
  });
});

// ------------------------------------------------------------ antibrute

// Por último e com cleanup: o bloqueio é por IP e o container é o mesmo, então
// contaminaria os testes de login se rodassem depois. Sem 'serial' no escopo do
// arquivo — isso faz uma falha cascatear e pular o resto da suite.
test('5 erros bloqueiam por 24 horas e a resposta avisa para não insistir', async ({ request }) => {
  limparEstado();

  try {
    // Contexto com sessão própria: o POST do login exige o token CSRF da tela.
    const html = await (await request.get('/painel/')).text();
    const csrf = html.match(/name="csrf" value="([^"]+)"/)[1];

    let ultima;
    for (let i = 1; i <= 5; i++) {
      ultima = await request.post('/painel/', { form: { csrf, acao: 'entrar', senha: `errada-${i}` } });
    }
    const tela = await ultima.text();
    expect(tela).toContain('bloqueado por 24 horas');
    expect(tela).toContain('Não insista');

    // O bloqueio ficou GRAVADO (é estado, não a contagem de falhas) com
    // vencimento de 24 horas.
    const agora = Math.floor(Date.now() / 1000);
    const bloqueios = JSON.parse(fs.readFileSync(BLOQUEIOS, 'utf8'));
    const chave = Object.keys(bloqueios)[0];
    expect(bloqueios[chave].permanente).toBe(false);
    expect(bloqueios[chave].libera - agora).toBeGreaterThan(23 * 3600);
    expect(bloqueios[chave].libera - agora).toBeLessThanOrEqual(24 * 3600);
  } finally {
    // devolve o IP ao normal para os próximos testes
    limparEstado();
  }
});

test('insistir durante o bloqueio vira bloqueio permanente', async ({ request }) => {
  limparEstado();

  try {
    const html = await (await request.get('/painel/')).text();
    const csrf = html.match(/name="csrf" value="([^"]+)"/)[1];
    for (let i = 1; i <= 5; i++) {
      await request.post('/painel/', { form: { csrf, acao: 'entrar', senha: `errada-${i}` } });
    }

    // O 6º POST (a insistência) vira permanente — sem chegar a ver a senha.
    const r = await request.post('/painel/', { form: { csrf, acao: 'entrar', senha: 'errada-6' } });
    expect(await r.text()).toContain('bloqueado permanentemente');

    const bloqueios = JSON.parse(fs.readFileSync(BLOQUEIOS, 'utf8'));
    expect(bloqueios[Object.keys(bloqueios)[0]].permanente).toBe(true);

    // Nem a senha CERTA abre (o bloqueio é checado antes do password_verify).
    const rc = await request.post('/painel/', { form: { csrf, acao: 'entrar', senha: SENHA || 'x' } });
    expect(await rc.text()).toContain('bloqueado permanentemente');
  } finally {
    limparEstado();
  }
});