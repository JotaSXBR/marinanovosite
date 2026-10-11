// Suite que roda contra o PHP REAL (container Docker), não contra o arquivo
// estático. Aqui é possivel testar o que o python http.server nao executa:
// oferta por ?o=, repasse de src pro checkout, painel, sessao e antibrute.
//
// Sobe o container sozinho se nao estiver no ar.
//
//   npm run test:php
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