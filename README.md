# Pilates em Casa (antes Mari Coach Fit)

Rebuild estático da landing. Sem build, sem banco: HTML + CSS + JS inline.

## Pastas

| Pasta | O que é |
|---|---|
| `nova-landing/` | O site novo (`index.html`, `privacidade.html`, `termos.html`). É aqui que se edita |
| `conteudo/imagens/` | Imagens reaproveitadas do WordPress original |
| `conteudo/layout/` | HTML original de referência (não publica) |
| `site-original/` | Espelho do WordPress antigo, só arquivo (não publica) |
| `tests/` | Testes Playwright (`npm test`) |
| `deploy/` | Gerada pela Action, não versionada (é o que sobe para a Hostinger) |
| `.agents/skills/` | Skills instaladas via `npx skills` (pin em `skills-lock.json`) |

Docs: `INVENTARIO.md` (o que veio do WordPress), `ANALISE-COPY.md` (auditoria de copy), `QUESTIONARIO.md` (respostas da dona).

## Ambiente dev

```bash
npm run dev
```

Abre `http://localhost:8901/nova-landing/` no navegador. Edite `nova-landing/index.html`, salve e atualize a página (F5). É esse o fluxo: você olha, pede a mudança, eu aplico, você confere no dev.

## Testes

```bash
npm test        # roda tests/ (sobe o preview sozinho na porta 8901)
```

## Deploy (GitHub → Hostinger)

1. Suba o repo no GitHub e configure os Secrets (Settings → Secrets → Actions):
   - `FTP_SERVER` (ex.: `ftp.seudominio.com` ou IP da Hostinger)
   - `FTP_USERNAME` (usuário FTP da Hostinger)
   - `FTP_PASSWORD` (senha FTP)
2. Todo `push` na branch `main` monta `deploy/` (HTML + imagens com links reescritos) e envia para `public_html/` via FTPS.
3. Dados de FTP: painel Hostinger → Hospedagem → Gerenciar → FTP. Crie uma conta FTP apontando para `public_html` se preferir.

Primeiro deploy: confira se o WordPress antigo saiu do `public_html` antes, para não misturar arquivos.
