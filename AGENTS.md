# AGENTS.md

## State
- Rebuild do maricoachfit.com (WordPress/Elementor) em andamento.
- `site-original/` — espelho wget da home + `/links/` com CSS/JS/fontes (~15 MB). Não editar; é arquivo.
- `conteudo/imagens/` — 23 imagens originais reaproveitáveis (sem thumbnails). `conteudo/prova/` — 23 imagens (8 depoimentos + 5 grupo + 5 pares antes/depois 9:16 com cinturas alinhadas e rostos removidos). `conteudo/layout/` — HTML de referência.
- `INVENTARIO.md` — mapa do que foi baixado, vídeo (embed YouTube `youtu.be/WDEG6QFKQlo`, sem MP4 próprio) e problemas do original.
- `ANALISE-COPY.md` — auditoria de copy seção por seção (skill `copywriting`), proposta de reordenação e backlog. Ler antes de mexer em textos.
- `QUESTIONARIO.md` — perguntas para a dona (oferta, público, prova, produto, marca, jurídico). Aguardando respostas; aplicar na landing quando chegarem.
- `BACKLOG.md` — futuro (não fazer agora): site dinâmico com ofertas via `?oferta=` e preview de link com imagem (OG).
- `nova-landing/index.php` — PRINCIPAL (produção): mesma página do `index.html`, mas a oferta é resolvida no servidor via `?o=CODIGO` (cai na `padrao` sem parâmetro). O HTML entregue contém SÓ a oferta ativa — as demais nunca aparecem no fonte. `og:url`/`og:image` montados do host atual (funciona na raiz e em subpasta `/nova/`, sem editar no go-live). `nova-landing/index.html` — snapshot estático da padrão (fallback + preview local sem PHP + base dos testes). `nova-landing/private/ofertas.php` — ARQUIVO LOCAL EDITÁVEL com todas as ofertas/checkouts (sobe no deploy mas bloqueado por `private/.htaccess` + guarda 403 no código). Nova oferta = novo checkout Kiwify + entrada com código aleatório nesse arquivo + link `?o=CODIGO`. Oferta secreta recebe `noindex` automático. `privacidade.html` + `termos.html` (LGPD/CDC padrão). Sem vídeo (dona pediu remoção). Abrir o arquivo direto no browser p/ preview.
- `nova-landing/indexnovo.html` e `nova-landing/indexdesign.html` — variantes de experimento, SOMENTE locais (não publicam; testes Playwright usam o preview local). Experimentos vão aqui, nunca direto no `index.html`/`index.php`.
- `tests/` (`npm test`) — Playwright smoke da landing (título/H1/CTAs, oferta com preço, imagens 200, FAQ, páginas legais). Config sobe o preview sozinho via `webServer` na porta 8901. Requer deps de sistema do Chromium (ver abaixo).
- Dev: `npm run dev` (serve a raiz na 8901). Deploy: push na `main` → Action `deploy-cpanel.yml` monta `deploy/` (gitignored) e envia p/ cPanel do maricoachfit.com via FTPS (secrets `FTP_SERVER/FTP_USERNAME/FTP_PASSWORD` + variable `CPANEL_SERVER_DIR`). Ver `README.md`.
- `nova-landing/painel/` — CMS próprio da proprietária (PHP, sem framework): login por senha, tabela de cliques por canal, criação de link (`?src=CANAL`), export CSV. Senha = HASH bcrypt em `painel/config.php` (nunca texto puro); a Action **falha o deploy** enquanto o placeholder `COLE_AQUI_O_HASH` estiver lá. Guardas: CSRF token, sessão httpOnly, `noindex` no painel, pasta `private/` negada por `.htaccess`.
- `nova-landing/private/rastreio.php` — loga 1 linha por visita (canal, oferta, hora, origem externa, identificador anônimo) e **repassa `src`/UTM pro checkout Kiwify**. Venda se confirma em Kiwify → Vendas → Filtros → Parâmetros da URL → `src`. LGPD: IP nunca em texto claro (HMAC com sal do dia). Dados em `private/dados/` (gitignored no servidor, criado em uso).
- Bônus no mobile: todos os 9 visíveis sempre (o gate "Ver todos os 9 bônus" foi removido — dona pediu full).
- Skills: `design-taste-frontend` + `frontend-design` + `ui-ux-pro-max` (criação UI), `web-design-guidelines` (auditoria UI), `accessibility` (WCAG), `copywriting`, `playwright-cli`, `find-skills` (via `npx skills`, pin em `skills-lock.json`).
- Tema claro/escuro: segue o sistema por padrão, toggle sol/lua no nav alterna e persiste (`localStorage tema` + `html[data-tema]` + tokens `light-dark()`).
- Sem build/test/lint além de `tests/`.

## Working here
- Do not assume a stack. Ask or inspect before adding tooling.
- When a stack is chosen, update this file with: exact install/dev/build/test/lint commands, entrypoints, and monorepo boundaries.
- Prefer editing existing files over creating new ones; keep new files only when needed.

## Skills
- `design-taste-frontend` is installed for landing-page / portfolio / redesign work. Follow its brief-inference + design-read workflow when doing frontend design; ignore it for non-design tasks.
