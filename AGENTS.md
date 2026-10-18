# AGENTS.md

Rebuild do **maricoachfit.com** (saiu de WordPress/Elementor) para uma landing
própria em **PHP + painel da proprietária**. Sem framework, sem build, sem banco.
Leia este arquivo antes de mexer: ele guarda as **armadilhas já pagas** — quase
todo erro caro aqui já aconteceu uma vez e está anotado.

## Comandos

```bash
npm run dev          # preview ESTÁTICO (python http.server) em :8901
npm run dev:php      # Apache + PHP 8.2 (Docker) em :8080 — o PHP de verdade
npm run dev:php:log  # erros de PHP em tempo real
npm run dev:php:down # derruba o container
npm test             # suíte estática → 46 passam + 2 pulados (layout/copy/CSS/fontes/srcset)
PAINEL_SENHA='...' npm run test:php   # suíte do PHP real → 30 (sobe o container; 8 pulados sem a senha)
./ferramentas/deploy-ftp.py           # deploy manual por FTPS (ver "Deploy")
```

**Regra de ouro:** o preview estático **não executa PHP**. Tudo que depende do
servidor (`?o=`, repasse de `src`, painel, `.htaccess`, 403) só se prova no
`dev:php` / `test:php`. Para editar a landing, edita-se o `index.php` e roda-se
`npm test` (a guarda de sincronia confere o espelho `index.html`).

## Mapa do repositório

| Caminho | Papel |
|---|---|
| `nova-landing/index.php` | **FONTE de produção** da landing. Oferta resolvida no servidor. Editar aqui. |
| `nova-landing/index.html` | **Artefato gerado**: espelho estático da oferta `padrao` (fallback sem PHP + alvo da suíte `npm test`). Não é fonte. |
| `nova-landing/privacidade.html`, `termos.html` | páginas legais (estáticas: **não** passam pela compressão do PHP) |
| `nova-landing/private/` | `ofertas.php` (cardápio base), `carrinhos.php` (gestão), `rastreio.php`. **Nunca acessível por URL.** |
| `nova-landing/private/dados/` | runtime do painel (JSON/JSONL) — **gitignored**, criado em uso |
| `nova-landing/painel/` | CMS da dona; `partes/cabeca.php` concentra **todo** o CSS |
| `nova-landing/fontes/` | Anton + Montserrat em `.woff2` (self-host, subsets latin/latin-ext). Público: sobe como `fontes/`. |
| `nova-landing/.htaccess` | cache, HTTPS, 403, headers de segurança |
| `conteudo/imagens/`, `conteudo/prova/` | imagens usadas pela landing (`.webp`) |
| `conteudo/layout/`, `conteudo/referencia/`, `conteudo/guardado/` | referência do original; **não publica** |
| `research/` | pesquisa (screenshots/HTML de concorrente); **não publica** |
| `ferramentas/deploy-ftp.py` | deploy manual por FTPS |
| `docker/`, `docker-compose.yml` | Apache + PHP 8.2 local |
| `tests/` | Playwright (`npm test`, `npm run test:php`) |
| `REVISAO-CONTEUDO.xlsx` | revisão de conteúdo da dona |
| `deploy/`, `entrega-site*`, `cgi-bin.zip` | artefatos locais, **gitignored** |
| `.agents/skills/`, `skills-lock.json` | skills instaladas via `npx skills` |

Docs de contexto: `README.md` (deploy passo a passo), `INVENTARIO.md` (o que veio
do WordPress), `ANALISE-COPY.md` (**ler antes de mexer em texto**),
`QUESTIONARIO.md` (respostas da dona), `BACKLOG.md` (futuro),
`KIWIFY-TUTORIAL.md` (criar oferta na Kiwify + cadastrar carrinho no painel).

## Landing: oferta resolvida no servidor

`index.php` lê `?o=CODIGO` e entrega **só a oferta ativa** — as demais nunca
aparecem no Ver código-fonte. Sem parâmetro (ou código inválido) cai na `padrao`.
`?oferta=` continua aceito como alias de links antigos. Oferta ≠ `padrao` recebe
`noindex` automático (meta + `X-Robots-Tag`).

- `og:url` / `og:image` / `canonical` são montados do host atual (funciona na
  raiz e em subpasta sem editar no go-live). O `canonical` precisa ser **URL
  absoluta**: com `href="./"` o audit `canonical` do Lighthouse reprova
  (`Is not an absolute URL (./)`) — ver "Lighthouse".
- O preço **cobrado** é sempre o da Kiwify (1 checkout por preço). Os valores do
  painel controlam só o texto da landing — se divergirem, a pessoa vê um preço e
  paga outro.
- `private/ofertas.php` é o cardápio **base versionado**; `private/dados/ofertas.json`
  é o que a dona cria/edita pelo painel (**fora do git**, o deploy não apaga). Na
  leitura o JSON **sobrepõe** o base — por isso o painel nunca grava no `.php`.
  `carrinhoApagar` de um carrinho do base guarda a chave em `_ocultos`;
  `carrinhoRestaurar` desfaz. `carrinhoValidar` normaliza/valida (chave
  `[a-z0-9_-]`, checkout https, `preco<=cheio`, parcelas 1–24, parcela `10,69`).
  O carrinho `padrao` não pode ser apagado. **`index.php` chama `carrinhosTodos()`,
  nunca o base direto.**

### Artefato gerado: `index.html`

`index.html` é **artefato**, não fonte: espelha o corpo do `index.php` com os
valores da oferta `padrao`. Serve de fallback sem PHP, de preview local e de alvo
da suíte estática. **Editar sempre no `index.php` e refletir no `index.html`** — a
sincronia é conferida por um teste (`npm test`, caso "sem drift do artefato"), que
exige que toda linha estática do `index.php` exista no `index.html` na mesma
ordem e que o `index.html` não carregue `var OFERTAS` (objeto antigo que vazava as
ofertas no fonte). A Action está sem créditos, então a guarda vive no `npm test`,
não no CI.

## Painel da proprietária (`nova-landing/painel/`)

CMS próprio (PHP, sem framework): login por senha, relatório de cliques por canal
(alinhado, com total), **gestão de carrinhos** (cadastrar/editar/apagar/restaurar),
**links prontos por canal** (um clique para WhatsApp/IG/TikTok/Kwai/Meta + link
personalizado), export CSV, botão copiar.

- Senha = **hash bcrypt** em `painel/config.php` (nunca texto puro). A Action
  **falha o deploy** enquanto o placeholder `COLE_AQUI_O_HASH` estiver lá (guarda
  contra senha vazia).
- Guardas: CSRF token, sessão httpOnly + SameSite=Strict, `noindex`, `private/`
  negada por `.htaccess`.
- `partes/cabeca.php` = **todo** o CSS do painel. **Tema noturno com
  `light-dark()`**: quem escolhe o lado é `color-scheme`, e o toggle sol/lua só
  força esse atributo (`html[data-tema]` + `localStorage painelTema`) — sem lista
  de tokens duplicada. O `data-tema` é escrito **sempre** no `<head>` (valor salvo;
  senão a preferência do sistema), porque sem ele nenhum CSS consegue perguntar
  qual lado vale. Consequência: **não pode sobrar cor fixa no arquivo** (todo `#fff`
  viraria mancha branca no escuro) — se precisar, criar token.

## Rastreio e clique por canal (`private/rastreio.php`)

Loga 1 linha por visita (canal, oferta, hora, origem externa, identificador
anônimo) e **repassa `src`/UTM ao checkout Kiwify**. A venda se confirma em
Kiwify → Vendas → Filtros → Parâmetros da URL → `src`. LGPD: o IP nunca fica em
texto claro (HMAC com sal do dia). Dados em `private/dados/` (gitignored no
servidor, criado em uso). **Hora:** o log grava em UTC (`date('c')` embute o
offset no `ts` de cada linha) e o painel **exibe** em `America/Sao_Paulo` —
`tzPainel()` em `painel/apoio.php` converte na exibição e nos baldes de
hoje/semana. Nunca mudar o fuso do LOG: linhas antigas (UTC) e futuras
convertem igual só porque cada uma carrega o próprio offset.

## Testes (`tests/`)

Duas suítes, porque testam coisas diferentes:

- **`npm test`** (46 passam + 2 pulados) — contra o `index.html` estático, via
  python http.server. Layout, copy, carrosséis, tema, bônus, sincronia do artefato,
  **fontes self-hosted** (nenhuma origem externa + todo `.woff2` responde 200),
  **candidatos de `srcset`** e `canonical` absoluto.
- **`npm run test:php`** (30) — contra o **PHP real** no container (`docker compose
  up -d`, :8080). O próprio script sobe o container antes. Cobre: `?o=`, repasse de
  `src` ao checkout, painel (login/carrinhos/ciclo de link/logout), cookie, CSRF,
  antibrute e os 403. Senha do painel via env `PAINEL_SENHA` (nunca no repo); sem
  ela os **8 testes de painel** são pulados.

> Rodar o Playwright do servidor **direto**, com o container fora do ar, falha com
> "webServer exited early": o comando `docker compose up -d` sai na hora e o
> Playwright lê como servidor caído. Use o `npm run test:php` (reuseExistingServer
> reaproveita o container já no ar).

`workers: 1` na suíte do servidor: os testes mexem em estado compartilhado (log de
visitas, contador de tentativas) e o antibrute bloqueia por IP — em paralelo um
teste derrubaria o login dos outros.

## Deploy

Push na `main` → Action `deploy-cpanel.yml` monta `deploy/` (link reescrito de
`conteudo/` → `imagens/`/`prova/`) e envia por **FTPS**. Guardo os detalhes e o
passo a passo no **`README.md`** (criar conta FTP, secrets, destino).

- **Destino ATUAL: raiz `public_html/`.** A variable `CPANEL_SERVER_DIR` foi
  removida de propósito (`server-dir` vazio → publica em
  `https://maricoachfit.com/`, painel em `/painel/`). Para testar numa subpasta,
  criar a variable em **Variables** (nunca em Secrets, que é ignorado em
  silêncio) com valor `nova/`.
- A pasta `/nova/` no servidor é **cópia congelada** de uma publicação antiga.
  Não é alvo de deploy — **nunca** conferir o estado atual ali, sempre na raiz.
- A Action também roda `php -l` em todo `.php` e aborta o deploy se a sintaxe
  estiver inválida (foi assim que um `painel/partes/` quebrado foi pego).
- **Fontes:** `nova-landing/fontes/*.woff2` vão para `deploy/fontes/` (o
  `@font-face` usa caminho relativo, então não passa pelo `sed` dos `conteudo/`).
  A Action **falha o deploy** se um `fontes/*.woff2` citado no `index.html` não
  existir no pacote — fonte ausente não dá erro visível, só cai no fallback.
- **Créditos do GitHub Actions acabaram em 14/10/2026** → caminho disponível é
  `ferramentas/deploy-ftp.py` (lê `FTP_SERVER`/`FTP_USERNAME`/`FTP_PASSWORD` do
  ambiente — nenhum segredo no repo; força `AUTH TLS` + `PROT P`; **não apaga**
  nada do servidor; **nunca** sobe para `/nova/`; idempotente por tamanho). O
  login da conta FTP já cai em `public_html`, então não há `cd`. A porta 990
  (FTPS implícito) está bloqueada desta rede; o servidor atende AUTH TLS na 21.
- **`cgi-bin.zip`** (109 MB, na raiz, gitignored) é o único backup do WordPress e
  contém o `wp-config.php`. **NUNCA versionar.** Decisão de 14/10/2026: manter no
  lugar (não está em `public_html`, `/wp-admin/` dá 404).

## Receitas

**Trocar texto/seção da landing** → edite `nova-landing/index.php`, reflita no
`index.html`, rode `npm test`. Copy: ler `ANALISE-COPY.md` antes.

**Adicionar oferta/carrinho** → crie o checkout na Kiwify (1 por preço) e adicione
uma entrada em `private/ofertas.php` com **chave aleatória** (ex.: `a7X9k2` —
nunca `vip`/`promo`, que são adivinháveis) + preencha `checkout/cheio/preco/total/
parcN/parcV/rotulo`. Divulgue `https://maricoachfit.com/?o=CODIGO`. A dona também
cadastra pelo painel (grave em `private/dados/ofertas.json`, fora do git).

**Trocar/adicionar imagem** → coloque o `.webp` em `conteudo/imagens/` ou
`conteudo/prova/` e **mude o nome do arquivo** (ver "Armadilhas → Cache").
Prova antes/depois: gerar também a variante `-360.webp` e ajustar `srcset`/`sizes`.
Gerar variante: `python3 -c "from PIL import Image; im=Image.open('x.webp'); im.resize((360, round(im.height*360/im.width)), Image.LANCZOS).save('x-360.webp','WEBP',quality=80,method=6)"`.

**Trocar/adicionar fonte** → baixar o `.woff2` (subset latin/latin-ext) para
`nova-landing/fontes/`, **mudar o nome do arquivo**, ajustar o `@font-face` no
`<style>` do `index.php` (e espelhar no `index.html`) e rodar `npm test`.

**Gerar hash da senha do painel** →
`php -r "echo password_hash('SENHA', PASSWORD_DEFAULT), PHP_EOL;"`, colar em
`nova-landing/painel/config.php`, commit e push.

**Publicar** → `git push` na `main` (Action) ou, sem créditos, montar o `deploy/`
como a Action faz e rodar `./ferramentas/deploy-ftp.py`.

## Armadilhas já pagas (não repetir)

### Cache e compressão — o servidor NÃO ajuda

- **Compressão é feita no PHP, não no servidor.** Medido em 14/10/2026: o nginx
  da frente **não repassa `Accept-Encoding`** ao PHP e o `mod_deflate` **não está
  carregado** no Apache. A solução é `comprimir_resposta_gzip()` no topo do
  `index.php` (bufferiza e comprime com `gzencode()` numa
  `register_shutdown_function`). Medido no ar: **80.552 → 20.852 bytes (−74%)**.
  Os headers só são declarados se a compressão aconteceu — nunca gzip pela metade.
  **Detalhe completo no comentário do `index.php`** (inclusive por que
  `ob_start('ob_gzhandler')` não resolve e por que `DeflateCompressionLevel` no
  `.htaccess` derruba o site com 500). O `<IfModule mod_deflate.c>` do `.htaccess`
  fica como rede de segurança para o dia que a host corrigir o proxy.
- **No cPanel não dá para ligar compressão**: o *Optimize Website* não funciona com
  NGINX; o único caminho é **WHM como root** → EasyApache 4 → pacotes
  `ea-nginx-gzip` / `ea-nginx-brotli`. **HTTP/2 também está desligado** (falta
  `ea-nginx-http2`). Ambos são chamado de WHM, não do site. Instalar depois não
  duplica: o nginx não re-comprime resposta que já chega com `Content-Encoding`.
  O audit `modern-http-insight` do Lighthouse reprova o site inteiro por isso
  (`protocol: http/1.1`) — é a última pendência de Performance e **não tem
  conserto no código**: pedir ao host o `ea-nginx-http2` (e o `ea-nginx-gzip`) no
  WHM. Texto pronto para o chamado: *"Peço habilitar HTTP/2 e gzip/brotli no
  nginx do domínio maricoachfit.com — instalar os pacotes `ea-nginx-http2`,
  `ea-nginx-gzip` e `ea-nginx-brotli` no EasyApache 4."*
- **Como sabemos que o `mod_deflate` não está carregado:** ele **sempre** põe
  `Vary: Accept-Encoding` quando o filtro roda, mesmo recusando comprimir (visto
  no container com o módulo ligado). Em produção `/termos.html` (estático, fora do
  PHP) **não tinha `Vary`** = filtro nunca rodou.
- **Cache:** `ExpiresByType` **não funciona** neste host (mod_expires inativo) —
  tudo é feito com `Header set` (`mod_headers`, que funciona). Estáticos: 1 ano
  `immutable`; HTML/PHP: `private, no-cache`; `?o=` presente: `private, no-store`.
  **REGRA OPERACIONAL: trocar imagem = MUDAR O NOME DO ARQUIVO.** Com `immutable`
  por 1 ano, sobrescrever mantendo o nome deixa a versão velha presa no cache de
  quem já visitou (foi assim com o hero: `nath-hero-b3` → `nath-hero-v2`).
- **BREACH:** conferido que `?o=CODIGO` **não** aparece no HTML (0 ocorrências) e
  o painel não passa pelo `index.php` (não é comprimido). Risco nulo.
- **`srcset` das 10 fotos de antes/depois:** variantes `-360.webp` com
  `sizes="(max-width:760px) 92vw, 350px"`. **O `sizes` está hardcoded em 350px
  porque o quadro tem `max-width: 420px` e o celular ocupa 92vw** — se o CSS
  mudar, o `sizes` muda junto, senão o navegador escolhe a variante errada.
- **`srcset` do hero e dos carrosséis** (17/10/2026, achado do Lighthouse: 647 KiB
  de imagem desperdiçados). Cada card do carrossel é `grid-auto-columns:
  min(300px,78vw)`, daí `sizes="(max-width:384px) 78vw, 300px"`; o hero é
  `.hero-grid` de 2 colunas (509px no desktop) e vira 92vw abaixo de 900px, daí
  `sizes="(max-width:900px) 92vw, 509px"`. Variantes: hero `nath-hero-v2-640.webp`
  (53 KB, era 106), `dp1..dp8-360.webp` (8→11 KB, eram 29→54) e
  `1/2/1-1/2-1/3-1-360.webp` (17→21 KB, eram 48→80). **`sizes` e CSS andam juntos.**

### Imagens e conteúdo

- `conteudo/imagens/` e `conteudo/prova/` são **100% `.webp`** desde 14/10/2026
  (−11 MB). Exceções fora do WebP: `og-capa.jpg` (WhatsApp/Facebook não renderizam
  WebP no preview de link) e `Satisfacao-Garantida.svg` (vetor).
- `conteudo/imagens/nath-hero-v2.webp` (106 KB) é o hero, recorte com alfa +
  brilho rosa via **matting** (BiRefNet portrait / rembg). A v1 (inundação) foi
  descartada: deixava halo branco no cabelo.
- `conteudo/prova/` tem 23 imagens (8 depoimentos + 5 grupo + 5 pares antes/depois
  9:16 com cinturas alinhadas e rostos removidos).
- Bônus no mobile: os **9 visíveis sempre** (o gate "Ver todos os 9 bônus" foi
  removido — dona pediu full).

### Fontes self-hosted (17/10/2026)

- **Antes:** Google Fonts (`<link>` + 2 `preconnect`) — 180–279 ms de
  render-blocking e uma cadeia de 3 requisições (`fonts.googleapis` →
  `fonts.gstatic`). Era o **único** recurso bloqueante da página, porque todo o CSS
  já é inline.
- **Agora:** `@font-face` inline no `<style>` + `.woff2` em `nova-landing/fontes/`.
  Zero origem externa, zero requisição bloqueante. `font-display:swap` (igual ao
  `&display=swap` que o Google usava).
- **Montserrat normal é fonte VARIÁVEL** (`wght 100–900`, conferido com fontTools):
  um arquivo cobre 400/500/600/700 — daí o `@font-face` declarar
  `font-weight:100 900`. A itálica é instância estática 400 (é o que o Google
  serve para `1,400`).
- Subsets: só `latin` + `latin-ext`. Todo o pt-BR cai em `latin` (inclusive `ã`,
  `õ`, `ç`, `−`); `-ext` fica de reserva. Descartei cyrillic/vietnamese.
- Trocar arquivo de fonte **exige mudar o nome** (mesma armadilha do `immutable`).
- O teste "fontes self-hosted" quebra se voltar qualquer origem `fonts.g*`.

### Contraste no tema escuro: `--cta` (fundo) ≠ `--cta-texto` (texto)

- `--cta` (#A80077) é cor de **fundo** (`.btn`, `.off-pill`, `.ba-puxador`, dots):
  no escuro continua #A80077 com texto branco por cima (7,16:1, ok).
- Usar `var(--cta)` como **texto** sobre superfície escura dava **1,84:1**
  (`.oferta-card .micro b`) e `--rosa` #FF51C9 no bloco rosa dava **4,06:1**
  (`.eyebrow`, `.pausa`, `.trust::before`) — os 4 itens que o `color-contrast` do
  Lighthouse reprovava.
- Solução: token `--cta-texto:light-dark(#A80077,#F87DFF)`. No claro é **o mesmo
  valor** (zero mudança visual); no escuro #F87DFF dá 5,24:1 no bloco rosa e
  5,88–7,04:1 nas demais superfícies. `--rosa-claro` já era token da marca.
- **Não** transformar `--cta` em `light-dark()`: o botão viraria rosa claro com
  texto branco (~2,5:1) e trocaria um problema por outro pior.

### Outros

- `site-original/` saiu da pasta em 14/10/2026 (limpeza de disco), mas o espelho
  wget do WordPress **continua no histórico do git**: `git checkout 727727c --
  site-original/`. Não voltar a versionar.
- Variantes de experimento vão em **branch**, nunca no `main` (`indexnovo`/
  `indexdesign` foram removidas).
- Tema claro/escuro da landing: segue o sistema por padrão, toggle sol/lua no nav
  alterna e persiste (`localStorage tema` + `html[data-tema]` + tokens `light-dark()`).
- Sem build. `php -l` roda só no deploy (importante distinguir "php ausente" de
  "php com erro" — o lint precisa **falhar o job** quando há erro).

## Segurança (auditoria)

- Skill `web-app-penetration-testing` (Strix) instalada, mas o binário `strix` não
  está no ambiente e **exige chave de LLM** (custo do dono). Até ter isso, a
  auditoria é à mão, com a mesma checklist dela: auth bypass, broken access
  control/IDOR, injeção, XSS, SSRF, business logic.
- Conduta: sondas **não destrutivas e de baixo volume**, só no site da cliente e
  com autorização explícita. Preferir o container local (`:8080`) para o que mexe
  em dados; produção só para leitura/config.
- **TLS: ok, sem pendência.** Um relato meu de TLS 1.0/1.1 era **falso positivo**
  (o grep casava com `Cipher is (NONE)`, que sai em handshake aceito e recusado).
  Refeito com socket Python: o servidor **recusa** 1.0/1.1 e aceita 1.2/1.3.
  Confirma a doc do cPanel 134+. **Não procurar "Security Levels" no cPanel** (no
  134+ o Wizard virou só loja de certificados).
- **HTTP → HTTPS: RESOLVIDO no `.htaccess`** (2026-10-11). Antes devolvia 200 e,
  como o form de login do painel não tem `action`, uma 1ª visita por http mandava
  a senha em claro. Regras: redireciona só se **nenhum** sinal diz https
  (`%{HTTPS}` e `X-Forwarded-Proto`, porque o nginx fica na frente do Apache);
  destino com **host fixo** (`maricoachfit.com`, nunca `%{HTTP_HOST}` — senão é
  open redirect via `Host: evil.com`); `www` normalizado ao canônico; só age em
  `maricoachfit.com`/`www`, então `localhost:8080` não é afetado.

## Lighthouse: como rodar limpo e o placar

**Por que o número "de verdade" precisa de navegador sem extensão:** o adblock e
afins injetam `chrome-extension://…` no relatório e derrubam/enganam 3 audits.
Medido em 17/10/2026: 342 KiB de "unused JS" e 73 KiB de "minify JS" eram **só
extensão**, e o `image-alt` reprovava por causa do `<img>` do ícone da extensão.
Nada disso é do site.

**Rodar sem extensão (o jeito que funciona):**

1. Abrir o Chrome com um perfil **novo e vazio** — janela anônima **não** resolve,
   porque extensão marcada "permitir em anônima" continua injetando:
   ```bash
   chrome --user-data-dir=/tmp/lh-perfil --no-first-run --no-default-browser-check
   ```
   (mesmo caminho para Edge/Brave; no Windows/WSL: `/mnt/c/Program Files/Google/Chrome/Application/chrome.exe`)
2. Nesse perfil, abrir `chrome://extensions` e conferir que está **vazio**.
3. Carregar `https://maricoachfit.com/`, `F12` → aba **Lighthouse** → modo
   **Navigation**, categoria **Mobile** (é a que pontua mais duro) → **Analyze page
   load**. Não tocar/rolar a página durante o teste.
4. ⋮ → *Save as JSON* em `research/` para comparar depois (não publica no site).

**Ou pela CLI, sem perfil nenhum (mais fiel entre execuções):**
```bash
npx lighthouse https://maricoachfit.com/ --preset=desktop --output=json --output-path=research/lh.json --chrome-flags="--headless=new"
```
`--preset=desktop` mede a experiência de desktop; sem ele, emula mobile.

**Placar após as correções (medido em 17/10/2026, sem extensão, auditoria limpa):**
Desktop Performance 100 · Acessibilidade 100 · Boas práticas 100 · SEO 100 · Agentic 100.
Mobile (emulado) Performance 100 · Acessibilidade 100 · Boas práticas 100 · SEO 100.
Antes: Performance 94 · Acessibilidade 91 · Boas práticas 100 · SEO 83 · Agentic 100.

| Audit que não estava cheio | Causa real | Status |
|---|---|---|
| `color-contrast` (a11y) | `--cta` como texto e `--rosa` no bloco rosa, só no tema escuro | corrigido (`--cta-texto`) |
| `canonical` (seo) | `href="./"` é **relativo** → "Is not an absolute URL (./)" | corrigido (absoluto via `$baseUrl`) |
| `image-delivery-insight` (perf) | 647 KiB: hero e carrosséis sem `srcset` | corrigido: hero 106→53 KB (ladder 640/828/1024/1244), cards 29–80→8–21 KB. Restam 22–29 KiB no ladder dos cards (abaixo da dobra, lazy) — não afeta a nota |
| `render-blocking-insight` (perf) | 180–279 ms do CSS do Google Fonts | corrigido (self-host, zero bloqueio) |
| `network-dependency-tree-insight` (perf) | cadeia `fonts.googleapis` → `fonts.gstatic` (879 ms) | corrigido junto |
| `modern-http-insight` (perf) | `protocol: http/1.1` | **depende do WHM** (`ea-nginx-http2`) — ver "Cache e compressão" |
| `unused-javascript` / `unminified-javascript` | extensão do navegador | ruído: some sem extensão |
| `image-alt` | `<img>` do ícone da extensão | ruído: some sem extensão |

⚠️ **O audit `canonical` NÃO reprova canonical apontando para a raiz** (a nota
antiga aqui dizia isso). Lido no fonte do Lighthouse
(`core/audits/seo/canonical.js`, 17/10/2026): reprova quando (a) o `href` não é
absoluto, (b) há dois canonicals conflitantes, ou (c) o canonical é a **raiz do
domínio e a própria página não é a raiz** — este último é o caso de página interna
apontando para a home, que não é o nosso. Se o `<link>` **não existe**, o audit
passa como "not applicable". Preferi manter o canonical absoluto: além de passar,
ele consolida as URLs de campanha (`?o=`, `?src=`, UTM) numa só.

## Skills do projeto

Usar a skill certa **antes** de começar, conforme o tipo de trabalho:

| Tipo de trabalho | Skill |
|---|---|
| Criar/redesenhar tela, hero, seção | `design-taste-frontend` (obrigatório: declarar antes o "Design Read" e os 3 dials) |
| Direção de arte, tipografia, layout | `frontend-design` |
| Componente/controle/estado específico | `ui-ux-pro-max` |
| Auditar UI já pronto | `web-design-guidelines` (regras atuais no `command.md` do vercel-labs) |
| Acessibilidade / WCAG | `accessibility` |
| Texto de venda, seção, oferta | `copywriting` (ler `ANALISE-COPY.md` antes) |
| Automação de browser / screenshot | `playwright-cli` |

- Instaladas via `npx skills`, pin em `skills-lock.json`.
- **`find-skills` é a ÚNICA chamada manual**: só quando não houver skill para o
  pedido — nunca carregar todas de uma vez.

## Documentação (Context7 é regra, não sugestão)

- **REGRA PERMANENTE: sempre que a tarefa envolver API, função, header HTTP,
  diretiva de servidor, CLI, configuração ou comportamento de QUALQUER tecnologia
  do projeto, a PRIMEIRA ação é consultar o Context7 (MCP `context7`) — nunca
  responder de memória nem copiar de blog.** Vale para todas as tecnologias
  usadas aqui: **PHP**, **Apache / .htaccess** (mod_headers, mod_rewrite,
  mod_deflate…), **Docker / Compose**, **Playwright / Node / npm**,
  **HTML / CSS / JS e Web APIs** (ex.: `light-dark()`, `srcset`, `matchMedia`),
  **Lighthouse**, **cPanel**, **Kiwify** e **Google Fonts**.
- Fluxo: `resolve-library-id` (nome + o que procuro) → escolher o melhor ID →
  `query-docs` com **uma pergunta por chamada** (um tema por vez; misturar temas
  dilui o ranking e volta resposta rasa).
- IDs já confirmados: PHP `/websites/php_net_manual_en` (`language: "php"`);
  Apache HTTP Server 2.4 `/websites/httpd_apache_2_4` (`language: "apache"`). Para
  o resto, resolver o ID na hora.
- Se o Context7 não tiver a lib ou truncar, `webfetch` na **doc oficial** (ex.:
  `developer.chrome.com/docs/lighthouse/…`, MDN) — `websearch` só para contexto
  (blog, notícia), **nunca** para decidir sintaxe/parâmetro.
- ✅ **ESTADO em 17/10/2026: chave renovada e funcionando.** A `Authorization:
  Bearer ctx7sk-…` fica em `~/.config/opencode/opencode.json` (fora do repo). Se
  voltar `Invalid API key`, renovar em context7.com e atualizar ali — até voltar,
  usar a doc oficial via `webfetch`.
- Motivo prático (por que não confiar em memória/blog): a doc oficial do
  `mod_ratelimit` avisa que ele limita **bandwidth**, não nº de requisições, e
  ocupa um worker durante a transferência toda. Usá-lo como "rate limit de
  requisições" seria errado por dois motivos. E o audit `canonical` do Lighthouse
  reprova canonical **relativo** ("Is not an absolute URL") — coisa que nenhum
  snippet de blog conta e que o fonte do Lighthouse confirma.

## Working here

- Não assumir stack: inspecionar antes de adicionar ferramenta.
- Preferir editar arquivo existente a criar novo; arquivo novo só quando necessário.
- Ao mexer em stack, atualizar este arquivo (comandos exatos, entrypoints, limites).
