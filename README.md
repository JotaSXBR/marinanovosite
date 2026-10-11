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

## Deploy (GitHub → cPanel do maricoachfit.com)

A Action (`.github/workflows/deploy-cpanel.yml`) monta `deploy/` (HTML + imagens
com links reescritos) e envia via **FTPS** a cada `push` na `main`.

### 1. Criar o usuário FTP (no cPanel da proprietária)

1. cPanel → **Contas de FTP** → **+ Adicionar conta FTP**.
2. **Protocolo:** FTP (o action usa FTPS na conexão, mas a conta nasce como FTP).
3. **Nome de usuário:** `deploy` → o login completo será `deploy@maricoachfit.com`.
4. **Senha:** uma forte, gerada pelo próprio cPanel.
5. **Diretório:** apague o padrão e digite exatamente `public_html`.
   > É este campo que define onde os arquivos caem. Se ficar apontando para
   > `public_html/upload` (o padrão quando o campo não é alterado), tudo será
   > enviado para a subpasta errada.
6. **Limite:** sem limite (ou o que couber) — as imagens somam ~12 MB.

O login é sempre `usuario@seudominio.com` — é esse formato (não só `deploy`)
que vai no secret do GitHub.

### 2. Configurar o GitHub

**Secrets** (Settings → Secrets and variables → Actions → *New repository secret*):

| Nome | Valor |
|---|---|
| `FTP_SERVER` | `ftp.maricoachfit.com` |
| `FTP_USERNAME` | `deploy@maricoachfit.com` |
| `FTP_PASSWORD` | a senha gerada no passo 1 |

**Variable** (mesma tela, aba *Variables* → *New repository variable*):

| Nome | Valor | Efeito |
|---|---|---|
| — (não criar) | — | **Estado atual:** sem variable = `server-dir` vazio = deploy na **raiz** (`maricoachfit.com/`) |
| `CPANEL_SERVER_DIR` | `nova/` | Desvia o deploy para `maricoachfit.com/nova/` (testar sem mexer na raiz) |

> ⚠️ **Variables ≠ Secrets.** A `CPANEL_SERVER_DIR` precisa estar na aba
> *Variables*. Criada em *Secrets*, o workflow lê `vars.CPANEL_SERVER_DIR`,
> recebe vazio e **sobe na raiz sem avisar** — foi exatamente o que aconteceu na
> primeira configuração. A raiz é o destino atual e intencional, mas o padrão
> tem que estar certo: **sem variable = raiz**.

> Os secrets são escolhidos porque o `deploy/` é idêntico em qualquer hosting:
> quem muda é só o destino. Para testar em outro servidor, troque só os três
> valores dos secrets.

### 3. Estado atual: no ar na raiz

O site está publicado em `https://maricoachfit.com/` e o painel em `/painel/`.
O WordPress antigo não está mais em `public_html` (os caminhos `/wp-login.php`,
`/wp-admin/`, `/wp-content/` respondem 404). Confirme com a proprietária se a
troca é definitiva antes de continuar publicando na raiz.

A pasta `/nova/` no servidor está vazia e pode ser apagada pelo Gerenciador de
Arquivos do cPanel.

### 4. Painel da proprietária

O `painel/config.php` sobe com **hash placeholder**, e a Action **falha o
deploy** até o hash real ser colado (proteção contra senha vazia). Gere em
qualquer servidor com PHP:

```bash
php -r "echo password_hash('SENHA', PASSWORD_DEFAULT), PHP_EOL;"
```

Cole em `nova-landing/painel/config.php`, commit e push. Aí sobe em
`/painel/` protegido por senha.

### Diagnóstico rápido

- **Action verde mas nada muda:** quase sempre é o `Diretório` da conta FTP
  apontando para a subpasta errada (passo 1.5) ou `server-dir` vazio quando
  você achava que era `nova/`.
- **Erro de conexão FTPS:** o `protocol` da conta em cPanel está como "FTP" e
  algumas contas antigas rejeitam FTPS explícito; nesse caso troque
  `protocol: ftps` para `ftp` no workflow.
- **`private/ofertas.php` acessível:** o `.htaccess` não está sendo lido →
  falta `AllowOverride All` no Apache do domínio.
