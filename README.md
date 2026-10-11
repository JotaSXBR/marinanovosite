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

**Variable** (mesma tela, aba **Variables** → *New repository variable*):

| Nome | Valor |
|---|---|
| `CPANEL_SERVER_DIR` | `nova/` |

> ⚠️ **Variables ≠ Secrets.** O workflow lê `vars.CPANEL_SERVER_DIR`, então a
> chave precisa estar na aba **Variables**. Se for criada em **Secrets**, é
> ignorada em silêncio e o deploy vai para a **raiz** sem falhar — foi o que
> aconteceu nas duas primeiras vezes. Para evitar isso, a Action tem um passo
> **"Mostrar destino do deploy"** que imprime a resolução no log do run:
> `📁 Deploy na subpasta: 'nova/'` = deu certo; `⚠️ ... RAIZ` = a variable não
> está sendo lida.

### 3. Trocar o destino do deploy

Edite o **valor** da variable `CPANEL_SERVER_DIR` (não o workflow):

| Valor | Resultado |
|---|---|
| `nova/` | publica em `maricoachfit.com/nova/` (raiz intocada) |
| *(apagar a variable)* | publica na raiz `maricoachfit.com/` |

É um caminho **relativo** à pasta onde a conta FTP entra (que já é
`public_html`) — por isso `nova/`, e **nunca** `public_html/nova/` nem
`/nova/`. Barra inicial tem comportamento inconsistente entre servidores FTP.

A pasta de destino precisa **já existir** no servidor. Para criar:
cPanel → Gerenciador de Arquivos → `public_html` → nova pasta.

### 4. Estado atual: publicado na raiz

O site está em `https://maricoachfit.com/` e o painel em
`https://maricoachfit.com/painel/`. A variable `CPANEL_SERVER_DIR` foi
removida do GitHub de propósito, então `server-dir` fica vazio e cada push
atualiza a **raiz**.

O WordPress antigo não está mais em `public_html` (`/wp-login.php`,
`/wp-admin/`, `/wp-content/` respondem 404).

> ⚠️ Existe uma pasta `/nova/` no servidor com uma **publicação antiga**
> (quando o destino era a subpasta). Ela **não** recebe mais deploy. Para
> conferir o estado atual do site, sempre olhe a raiz — foi o que causou uma
> auditoria longa achando que o deploy não funcionava.

### 5. Painel da proprietária

O `painel/config.php` sobe com **hash placeholder**, e a Action **falha o
deploy** até o hash real ser colado (proteção contra senha vazia). Gere em
qualquer servidor com PHP:

```bash
php -r "echo password_hash('SENHA', PASSWORD_DEFAULT), PHP_EOL;"
```

Cole em `nova-landing/painel/config.php`, commit e push. Aí sobe em
`/painel/` protegido por senha.

O painel também controla os **carrinhos** (checkouts da Kiwify) e os **links por
canal**. Dois arquivos sustentam isso, de propósito:

| Arquivo | Papel |
|---|---|
| `private/ofertas.php` | cardápio **base**, versionado no git, republicado a cada deploy |
| `private/dados/ofertas.json` | o que a dona cria/edita na tela — **não está no git**, então o deploy não apaga |

Na leitura o JSON **sobrescreve** o base. Se o painel gravasse direto no
`ofertas.php`, o próximo `push` sobrescreveria o cadastro dela. O carrinho
`padrao` não pode ser apagado (é o que o site mostra sem parâmetro), e
"Restaurar" devolve um carrinho ao valor original do código.

> O preço **cobrado** é sempre o do checkout Kiwify (1 checkout por preço). Os
> valores do painel controlam só o texto da landing — se divergirem da Kiwify,
> a pessoa vê um preço e paga outro. Confira sempre depois de criar o checkout.

### Diagnóstico rápido

- **Action verde mas nada muda:** quase sempre é o `Diretório` da conta FTP
  apontando para a subpasta errada (passo 1.5) ou `server-dir` vazio quando
  você achava que era `nova/`.
- **Erro de conexão FTPS:** o `protocol` da conta em cPanel está como "FTP" e
  algumas contas antigas rejeitam FTPS explícito; nesse caso troque
  `protocol: ftps` para `ftp` no workflow.
- **`private/ofertas.php` acessível:** o `.htaccess` não está sendo lido →
  falta `AllowOverride All` no Apache do domínio.
