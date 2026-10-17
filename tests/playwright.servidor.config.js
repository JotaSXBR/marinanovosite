// Suite que roda contra o PHP REAL (container Docker), não contra o arquivo
// estático. Aqui é possivel testar o que o python http.server nao executa:
// oferta por ?o=, repasse de src pro checkout, painel, sessao e antibrute.
//
// Sobe o container sozinho se nao estiver no ar.
//
//   npm run test:php        (recomendado: sobe o container e roda)
//   npm run dev:php         (sobe o container e deixa no ar)
//   npm run dev:php:down    (derruba)
//
// ATENCAO: rodar `playwright test -c playwright.servidor.config.js` direto, com
// o container FORA DO AR, falha com "webServer exited early" — o comando do
// webServer abaixo e `docker compose up -d`, que sai na hora (destacado) e o
// Playwright entende como servidor que morreu. Por isso o script npm sobe o
// container ANTES (com reuseExistingServer: true, o Playwright entao reaproveita
// o container ja no ar em vez de tentar subir um).
//
// Testes que precisam da senha do painel usam a variavel de ambiente
// PAINEL_SENHA. A senha NUNCA fica no repo (no config.php so existe o hash
// bcrypt). Sem a variavel, esses testes sao pulados com aviso explicito.
const { defineConfig, devices } = require('@playwright/test');

module.exports = defineConfig({
  testDir: '.',
  testMatch: /servidor\.spec\.js/,
  // workers: 1 é obrigatório: os testes mexem em estado do servidor
  // (log de visitas, contador de tentativas). Em paralelo, o teste de
  // antibrute bloquearia o IP do container e derrubaria o login dos outros.
  fullyParallel: false,
  workers: 1,
  reporter: 'list',
  use: { baseURL: 'http://127.0.0.1:8080' },
  webServer: {
    command: 'docker compose up -d',
    url: 'http://127.0.0.1:8080/',
    reuseExistingServer: true,
    timeout: 180000,
  },
  projects: [
    { name: 'desktop', use: { ...devices['Desktop Chrome'] } },
    { name: 'mobile', use: { ...devices['Pixel 7'] } },
  ],
});