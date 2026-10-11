// Playwright config: sobe o preview estático sozinho (reuse se porta ocupada).
const { defineConfig, devices } = require('@playwright/test');

module.exports = defineConfig({
  testDir: '.',
  // A suite do servidor (servidor.spec.js) precisa de PHP real e roda pela
  // playwright.servidor.config.js contra o container. Aqui ela só geraria
  // falso positivo, porque o python http.server não executa PHP.
  testIgnore: /servidor\.spec\.js/,
  fullyParallel: true,
  reporter: 'list',
  use: { baseURL: 'http://127.0.0.1:8901' },
  webServer: {
    command: 'python3 -m http.server 8901 --bind 127.0.0.1 --directory ..',
    port: 8901,
    reuseExistingServer: true,
  },
  projects: [
    { name: 'desktop', use: { ...devices['Desktop Chrome'] } },
    { name: 'mobile', use: { ...devices['Pixel 7'] } },
  ],
});
