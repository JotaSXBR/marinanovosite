# Pilates em Casa (antes Mari Coach Fit)

Landing do maricoachfit.com: **PHP + painel da proprietária**, sem build e sem
banco (HTML + CSS + JS inline). O `AGENTS.md` é o mapa técnico e as armadilhas;
este README detalha o ambiente local e o deploy.

## Pastas

| Caminho | O que é |
|---|---|
| `nova-landing/index.php` | **Fonte de produção.** A oferta é resolvida no servidor (`?o=CODIGO`) |
| `nova-landing/index.html` | Artefato gerado: espelho estático da oferta `padrão` (fallback sem PHP e alvo do `npm test`) |
| `nova-landing/privacidade.html`, `termos.html` | Páginas legais |
| `nova-landing/private/` | Ofertas, gestão de carrinhos e rastreio — nunca acessível por URL |
| `nova-landing/painel/` | Painel da proprietária (login, carrinhos, links por canal) |
| `nova-landing/fontes/` | Anton + Montserrat em `.woff2` (self-host) |
| `conteudo/imagens/`, `conteudo/prova/` | Imagens reaproveitadas do original (`.webp`) |
| `conteudo/layout/`, `conteudo/referencia/`, `conteudo/guardado/` | Referência do original (não publica) |
| `research/` | Pesquisa de concorrente (não publica) |
| `ferramentas/` | `deploy-ftp.py` — deploy manual por FTPS |
| `tests/` | Testes Playwright (`npm test`, `npm run test:php`) |
| `deploy/` | Gerada pela Action, não versionada (é o que sobe para o cPanel) |
| `.agents/skills/` | Skills instaladas via `npx skills` (pin em `skills-lock.json`) |

Docs: `AGENTS.md` (mapa técnico + armadilhas), `INVENTARIO.md` (o que veio do
WordPress), `ANALISE-COPY.md` (auditoria de copy), `QUESTIONARIO.md` (respostas da
dona), `BACKLOG.md` (futuro).

## Scripts

| Comando | O que faz |
|---|---|
| `npm run dev` | Preview **estático** em `http://localhost:8901` (não executa PHP) |
| `npm run dev:php` | Apache + PHP 8.2 (Docker) em `http://localhost:8080` — o PHP real |
| `npm run dev:php:log` / `npm run dev:php:down` | Log de erros do PHP / derruba o container |
| `npm test` | Suíte estática (46 passam + 2 pulados) |
| `npm run test:php` | Suíte do PHP real no container (38; 14 pulados sem `PAINEL_SENHA`) |

## Ambiente dev

Há **dois** servidores locais, porque eles servem para coisas diferentes:

### 1. Estático (`npm run dev`) — o padrão

```bash
npm run dev
```

Abre `http://localhost:8901/nova-landing/`. É só `python3 -m http.server`: serve
arquivo puro, **não executa PHP**. Serve para conferir layout, copy e CSS do
`index.html`.

### 2. PHP real (`npm run dev:php`) — o que importa para o painel

```bash
npm run dev:php          # sobe em http://localhost:8080
npm run dev:php:log      # erros de PHP em tempo real
npm run dev:php:down     # derruba
```

Sobe **Apache + PHP 8.2 em Docker**, espelhando o stack do cPanel. É o único
jeito de testar de verdade o que importa:

| O que só funciona aqui | Por quê |
|---|---|
| Oferta por `?o=` | precisa de PHP |
| Rastreio de canal (`?src=`) e repasse ao checkout | precisa de PHP |
| Painel em `/painel/` (login, carrinhos, links, tentativas de login) | precisa de PHP |
| Antibrute e cookies de sessão | precisa de PHP |

A pasta `nova-landing/` é montada como document root e o `.htaccess` vale de
verdade — os bloqueios de `private/` e `painel/` podem ser conferidos local
(`curl -I localhost:8080/private/ofertas.php` tem que dar 403).

> Docker (e não `apt install php`) porque o WSL aqui exige senha de sudo; assim
> a máquina host fica intocada e o PHP local é exatamente a versão do servidor.

## Testes

Duas suítes, porque testam coisas diferentes:

```bash
npm test         # 46 passam + 2 pulados · contra o index.html estático (layout/copy/CSS/fontes/srcset)

PAINEL_SENHA='...' npm run test:php
                  # 38 · contra o PHP real no container (:8080) — o script sobe o container
```

**`npm test`** sobe o preview estático e confere o que dá para conferir sem
executar PHP: layout, copy, carrosséis, tema, bônus, a **sincronia do artefato**
(o `index.html` tem que espelhar o corpo do `index.php` — sem isso, produção e
fallback divergem em silêncio), as **fontes self-hosted** (nenhuma origem externa
e todo `.woff2` em `fontes/` respondendo 200), todo **candidato de `srcset`** e o
`canonical` absoluto.

**`npm run test:php`** sobe o container e cobre o que só existe com PHP:
oferta por `?o=`, repasse do canal para o checkout, painel (login, carrinhos,
ciclo de criar/apagar link, validação que reabre o formulário com o que foi
digitado, seção de tentativas de login com a limpeza própria, acesso único,
logout), cookie de sessão, exigência de CSRF, antibrute (bloqueio de 24 h e o
permanente por insistência) e os 403 de `private/` e `painel/`.

A senha do painel vem da variável de ambiente `PAINEL_SENHA` — nunca fica no
repo (no `config.php` só existe o hash bcrypt). Sem ela, os 14 testes de painel
são pulados com aviso.

> Detalhe do desenho: `workers: 1` na suíte do servidor. Os testes mexem em
> estado compartilhado (log de visitas, contador de tentativas) e o bloqueio do
> antibrute é por IP — em paralelo, um teste derrubaria o login dos outros.

## Auditoria Lighthouse

O relatório mede **a página no ar**, então rode contra `https://maricoachfit.com/`
(o `npm run dev:php` mede a mesma coisa em `http://localhost:8080`).

**Sem extensão é obrigatório** — adblock e afins injetam `chrome-extension://` no
relatório e sujam 3 audits (342 KiB de JS "não usado", 73 KiB de "minificar JS" e
um `image-alt` do ícone da própria extensão). Janela anônima **não** resolve:
extensão marcada "permitir em anônima" continua injetando.

```bash
# perfil novo e vazio (o caminho confiável) — no WSL:
"/mnt/c/Program Files/Google/Chrome/Application/chrome.exe" \
  --user-data-dir=/tmp/lh-perfil --no-first-run --no-default-browser-check

# ou direto pela CLI, sem perfil nenhum:
npx lighthouse https://maricoachfit.com/ \
  --preset=desktop --output=json --output-path=research/lh.json \
  --chrome-flags="--headless=new"
```

No navegador: `F12` → aba **Lighthouse** → modo **Navigation** → **Analyze page
load** (não rolar a página durante o teste). O placar final (Performance 100 · Acessibilidade 100 · Boas práticas 100 ·
SEO 100 — desktop e mobile emulados) e a lista do que cada ponto
pedia estão no `AGENTS.md`, seção **Lighthouse**. A única pendência fora do código
é o **HTTP/2**, que é chamado no WHM do host.

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
6. **Limite:** sem limite (ou o que couber) — as imagens somam ~2,5 MB.

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
| `CPANEL_SERVER_DIR` | *(deixar **vazia/ausente** — é o estado atual, publica na raiz. Criar com `nova/` só para testar em subpasta.)* |

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

### 5. Plano B: deploy manual, sem GitHub Actions

Em 14/10/2026 os créditos de Actions da conta acabaram e o deploy automático
parou — mas **voltou a funcionar** (conferido em 18/10/2026: o push `e6a6fbd`
deployou em 14 s pelo `deploy-cpanel.yml`). Se parar de novo, o caminho manual é
`ferramentas/deploy-ftp.py`: mesma montagem de pacote, mesma conta, mesmas
travas — só muda quem executa.

```bash
# 1. Montar o pacote (o mesmo bloco que a Action executa)
#    as linhas exatas estão em .github/workflows/deploy-cpanel.yml, passo
#    "Montar pasta de deploy". Não esquecer a linha das FONTES
#    (cp nova-landing/fontes/*.woff2 deploy/fontes/): sem ela o site publica
#    inteiro sem a fonte da marca, e a página continua "funcionando".

# 2. Credenciais no AMBIENTE — nunca na linha de comando,
#    senão a senha fica no histórico do shell
export FTP_SERVER=ftp.maricoachfit.com
export FTP_USERNAME=deploy@maricoachfit.com
read -rs -p "senha FTP: " FTP_PASSWORD; export FTP_PASSWORD; echo

# 3. Subir
./ferramentas/deploy-ftp.py
```

O que ele garante:

| | |
|---|---|
| **TLS obrigatório** | `AUTH TLS` + `PROT P` — a senha nunca trafega em texto claro |
| **Nada de segredo no repo** | credenciais só do ambiente |
| **Nunca apaga** | o que existe no servidor e não existe aqui fica |
| **Nunca vai para `/nova/`** | o login da conta já cai na raiz do site |
| **Idempotente** | arquivo já no ar com o mesmo tamanho é pulado |

> A porta **990** (FTPS implícito) está bloqueada a partir desta rede; o
> servidor atende **AUTH TLS na 21**. É por isso que o script usa a 21 e não a
> 990 — e é também por isso que o `curl ftps://` sem porta explícita trava.

### 6. Painel da proprietária

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
