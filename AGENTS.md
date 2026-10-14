# AGENTS.md

## State
- Rebuild do maricoachfit.com (WordPress/Elementor) em andamento.
- ~~`site-original/`~~ — **saiu da pasta em 14/10/2026** (limpeza de disco). O
  espelho wget do WordPress antigo (119 arquivos, 15 MB) **continua no histórico
  do git**: recuperar com `git checkout 727727c -- site-original/`. Não voltar a
  versionar.
- `conteudo/imagens/` — imagens reaproveitadas do WordPress original, **todas em `.webp` desde 14/10/2026** (limpeza de 11 MB). `conteudo/prova/` — 23 imagens (8 depoimentos + 5 grupo + 5 pares antes/depois 9:16 com cinturas alinhadas e rostos removidos). `conteudo/layout/` — HTML de referência. **Duas exceções que continuam fora do WebP:** `og-capa.jpg` (é o og:image que WhatsApp/Facebook leem — nenhum renderiza WebP em preview de link) e `Satisfacao-Garantida.svg` (vetor).
- `conteudo/imagens/nath-hero-v2.webp` — foto do hero (recorte com alfa + brilho rosa, 106 KB). **v1 (inundação) foi descartado: deixava halo branco no cabelo.** Agora usa matting (BiRefNet portrait, MIT, via rembg). Regra para trocar esta imagem: **mudar o nome do arquivo**, não sobrescrever — o `ExpiresByType` do `.htaccess` não vale nesse host (produção não devolve `Cache-Control`/`Expires`), mas trocar o nome elimina qualquer cache de browser de uma vez.
- `INVENTARIO.md` — mapa do que foi baixado, vídeo (embed YouTube `youtu.be/WDEG6QFKQlo`, sem MP4 próprio) e problemas do original.
- `ANALISE-COPY.md` — auditoria de copy seção por seção (skill `copywriting`), proposta de reordenação e backlog. Ler antes de mexer em textos.
- `QUESTIONARIO.md` — perguntas para a dona (oferta, público, prova, produto, marca, jurídico). Aguardando respostas; aplicar na landing quando chegarem.
- `BACKLOG.md` — futuro (não fazer agora): site dinâmico com ofertas via `?oferta=` e preview de link com imagem (OG).
- `nova-landing/index.php` — PRINCIPAL (produção): mesma página do `index.html`, mas a oferta é resolvida no servidor via `?o=CODIGO` (cai na `padrao` sem parâmetro). O HTML entregue contém SÓ a oferta ativa — as demais nunca aparecem no fonte. `og:url`/`og:image` montados do host atual (funciona na raiz e em subpasta `/nova/`, sem editar no go-live). `nova-landing/index.html` — snapshot estático da padrão (fallback + preview local sem PHP + base dos testes). `nova-landing/private/ofertas.php` — ARQUIVO LOCAL EDITÁVEL com todas as ofertas/checkouts (sobe no deploy mas bloqueado por `private/.htaccess` + guarda 403 no código). Nova oferta = novo checkout Kiwify + entrada com código aleatório nesse arquivo + link `?o=CODIGO`. Oferta secreta recebe `noindex` automático. `privacidade.html` + `termos.html` (LGPD/CDC padrão). Sem vídeo (dona pediu remoção). Abrir o arquivo direto no browser p/ preview.
- `tests/` — duas suítes Playwright. `npm test` (38) roda contra o `index.html` estático (layout/copy/carrossel). `npm run test:php` (30) roda contra o **PHP real** no container (`docker compose up -d`, :8080) e cobre o que o estático não executa: `?o=`, repasse de src ao checkout, painel (login/carrinhos/ciclo de link/logout), cookie, CSRF, antibrute e os 403. Senha do painel via env `PAINEL_SENHA` (nunca no repo); sem ela os 8 testes de painel são pulados. `workers: 1` na suíte do servidor porque os testes compartilham estado e o antibrute bloqueia por IP.
- Ambiente PHP local = Docker (`docker/Dockerfile`, `docker-compose.yml`): `php:8.2-apache` + `AllowOverride All` (sem isso o `.htaccess` é ignorado e os bloqueios não existem local). `npm run dev:php`. Docker e não apt porque o WSL exige senha de sudo.
- Dev: `npm run dev` (serve a raiz na 8901). Deploy: push na `main` → Action `deploy-cpanel.yml` monta `deploy/` (gitignored) e envia p/ cPanel do maricoachfit.com via FTPS. Segredos (`FTP_SERVER/FTP_USERNAME/FTP_PASSWORD`). **Destino ATUAL: raiz `public_html/` — a variable `CPANEL_SERVER_DIR` foi removida de propósito**, então `server-dir` fica vazio e publica em `https://maricoachfit.com/` (painel em `/painel/`). Para testar numa subpasta sem mexer na raiz, criar a variable em **Variables** (nunca em Secrets, que é ignorado em silêncio) com valor `nova/`. Ver `README.md`.
- A pasta `/nova/` no servidor é uma **cópia congelada** de uma publicação antiga (quando `CPANEL_SERVER_DIR=nova/`). Não é mais alvo de deploy — não usar para conferir o estado atual, sempre olhar a raiz. Foi por isso que uma auditoria "não encontrava" as mudanças no servidor.
- **Backup do WordPress antigo: `cgi-bin.zip`** (109 MB, na raiz, gitignored, NUNCA versionar — tem `wp-admin`, `wp-includes`, `wp-content` e o `wp-config.php` com a senha do banco). **Decisão de 14/10/2026: MANTER no lugar.** É 94% do peso da pasta, mas é o único backup do WordPress e apaguá-lo torna o site antigo irrecuperável. Não está em `public_html` (404 em `/wp-admin/`).
- `nova-landing/painel/` — CMS próprio da proprietária (PHP, sem framework): login por senha, relatório de cliques por canal (alinhado, com total), **gestão de carrinhos** (cadastrar/editar/apagar/restarurar), **links prontos por canal** (botões de um clique para WhatsApp/IG/TikTok/Kwai/Meta + link personalizado), export CSV, botão copiar. Senha = HASH bcrypt em `painel/config.php` (nunca texto puro); a Action **falha o deploy** enquanto o placeholder `COLE_AQUI_O_HASH` estiver lá. Guardas: CSRF token, sessão httpOnly, `noindex`, `private/` negada por `.htaccess`.
- `nova-landing/painel/partes/cabeca.php` — **todo** o CSS do painel (design
  system + responsivo) num arquivo só. **Tema noturno com `light-dark()`**: quem
  escolhe o lado é `color-scheme`, e o interruptor sol/lua só força esse atributo
  (`html[data-tema]` + `localStorage painelTema`) — sem lista de tokens
  duplicada. O `data-tema` é escrito **sempre** no `<head>` (valor salvo; senão a
  preferência do sistema), porque sem ele nenhum CSS consegue perguntar qual lado
  vale. Consequência: não pode sobrar cor fixa no arquivo (todo `#fff` viraria
  mancha branca no escuro) — se precisar, criar token.
- `nova-landing/private/carrinhos.php` — camada de gestão dos carrinhos. **Dois arquivos, de propósito:** `private/ofertas.php` é o cardápio base versionado no git (re-publicado a cada deploy); `private/dados/ofertas.json` é o que a dona cria/edita pelo painel e **NÃO está no git**, então o deploy não apaga. Na leitura o JSON **sobrepõe** o base. `carrinhoApagar` de um carrinho do base guarda a chave em `_ocultos` (não some do código); `carrinhoRestaurar` desfaz a edição. `index.php` chama `carrinhosTodos()` — nunca o base direto. `carrinhoValidar` normaliza/valida (chave `[a-z0-9_-]`, checkout https, preco<=de, parcelas 1-24, parcela `10,69`). O carrinho `padrao` não pode ser apagado.
- `nova-landing/private/rastreio.php` — loga 1 linha por visita (canal, oferta, hora, origem externa, identificador anônimo) e **repassa `src`/UTM pro checkout Kiwify**. Venda se confirma em Kiwify → Vendas → Filtros → Parâmetros da URL → `src`. LGPD: IP nunca em texto claro (HMAC com sal do dia). Dados em `private/dados/` (gitignored no servidor, criado em uso).
- Bônus no mobile: todos os 9 visíveis sempre (o gate "Ver todos os 9 bônus" foi removido — dona pediu full).
- `index.html` é **artefato gerado**, não fonte: espelha o `index.php` (fallback sem PHP e alvo dos testes). Editar sempre no `index.php` e espelhar; a guarda de sincronia roda no CI.
- Variantes de experimento (`indexnovo`, `indexdesign`) foram removidas — experiments vão num branch, nunca no `main`.
- Tema claro/escuro: segue o sistema por padrão, toggle sol/lua no nav alterna e persiste (`localStorage tema` + `html[data-tema]` + tokens `light-dark()`).
- Sem build/test/lint além de `tests/`.

## Segurança (auditoria)
- Skill `web-app-penetration-testing` (Strix) instalada, mas o binário `strix`
  não está no ambiente e **exige chave de LLM** (custo do dono). Até ter isso,
  a auditoria é feita à mão, aplicando a mesma checklist dela: auth bypass,
  broken access control/IDOR, injeção, XSS, SSRF e business logic.
- Regra de conduta: sondas **não destrutivas e de baixo volume**, sempre no
  próprio site da cliente e com autorização explícita. Preferir o container
  local (`:8080`) para o que mexe em dados; produção só para leitura/config.
- **TLS: ok, sem pendência.** Cheguei a reportar TLS 1.0/1.1 como aceitos —
  era FALSO POSITIVO meu: o grep casava com `Cipher is (NONE)`, que sai tanto
  em handshake aceito quanto recusado. Refeito com socket Python (que ignora o
  `openssl.cnf` da máquina local): o **servidor recusa** 1.0 e 1.1, aceita 1.2 e
  1.3. Confirma a doc do cPanel 134+: *"only supports TLSv1.2 or later"*.
  **Não procurar "Security Levels" no cPanel** — no 134+ o Wizard virou só
  loja de certificados e não tem mais passo de ajustes.
- **HTTP → HTTPS: RESOLVIDO no código** (2026-10-11). Estava devolvendo 200 em
  vez de 301, e como o form de login do painel não tem `action`, uma 1ª visita
  por http mandava a senha em claro. Corrigido em `nova-landing/.htaccess`
  (que o deploy copia para `public_html/`), não no cPanel:
  - Redireciona só quando **nenhum** sinal diz https (`%{HTTPS}` e
    `X-Forwarded-Proto`), porque no cPanel o nginx fica na frente do Apache e o
    Apache pode ver a conexão interna em http mesmo vinda de https.
  - Destino com **host fixo** (`maricoachfit.com`), nunca `%{HTTP_HOST}` — senão
    vira open redirect via `Host: evil.com`. `www` é normalizado ao canônico.
  - Só age em `maricoachfit.com`/`www`, então `localhost:8080` não é afetado e
    as suítes locais continuam funcionando.

## Skills do projeto
Usar a skill certa **antes** de começar a tarefa, conforme o tipo de trabalho:

| Tipo de trabalho | Skill |
|---|---|
| Criar/redesenhar tela, hero, seção | `design-taste-frontend` (obrigatório: primeiro declarar o "Design Read" e os 3 dials) |
| Direção de arte, tipografia, layout | `frontend-design` |
| Componente/controle/estado específico | `ui-ux-pro-max` |
| Auditar UI já pronto | `web-design-guidelines` (buscar as regras atuais no `command.md` do vercel-labs) |
| Acessibilidade / WCAG | `accessibility` |
| Texto de venda, seção, oferta | `copywriting` (ler `ANALISE-COPY.md` antes) |
| Automação de browser / screenshot | `playwright-cli` |

- Instaladas via `npx skills`, pin em `skills-lock.json`.
- **`find-skills` é a ÚNICA chamada manual**: só usar quando não houver skill
  para o que foi pedido — nunca carregar todas as skills de uma vez.

## Working here
- Do not assume a stack. Ask or inspect before adding tooling.
- When a stack is chosen, update this file with: exact install/dev/build/test/lint commands, entrypoints, and monorepo boundaries.
- Prefer editing existing files over creating new ones; keep new files only when needed.

## Documentação (Context7 primeiro, pesquisa web como complemento)
- **Antes de responder sobre API, função, header HTTP, módulo de servidor ou sintaxe
  de plataforma, consultar o Context7 (MCP `context7`).** Ele devolve a doc oficial
  e atualizada da versão certa; é mais confiável que snippet de blog.
- Fluxo: `resolve-library-id` (libraryName + o que estou procurando) → escolher o
  melhor ID → `query-docs` com **uma pergunta por chamada** (um tema por vez;
  misturar temas na mesma query dilui o ranking e volta resposta rasa).
- IDs confirmados neste projeto:
  - PHP: `/websites/php_net_manual_en` (manual oficial; use `language: "php"`)
  - Apache HTTP Server 2.4: `/websites/httpd_apache_2_4` (use `language: "apache"`)
- `webfetch` ainda serve para ler a página exata da doc quando a resposta do
  Context7 vier truncada, e `websearch` para contexto (blogs, casos, notícias
  de versão) — mas **nunca** para decidir sintaxe ou parâmetro de função.
- Motivo prático: doc de blog desatualiza em silêncio. Exemplo real — a doc
  oficial do `mod_ratelimit` avisa que ele limita **bandawidth**, não nº de
  requisições, e que cada resposta limitada **ocupa um worker durante a
  transferência toda**, podendo exaurir o servidor. usar mod_ratelimit como
  "rate limit de requisições" seria errado por dois motivos.

## Skills
- `design-taste-frontend` is installed for landing-page / portfolio / redesign work. Follow its brief-inference + design-read workflow when doing frontend design; ignore it for non-design tasks.
- `web-design-guidelines`: para auditar UI, buscar as regras atuais no source que a skill indica (`command.md` no repo vercel-labs) e revisar no formato `arquivo:linha - problema`.
