# AGENTS.md

## State
- Rebuild do maricoachfit.com (WordPress/Elementor) em andamento.
- `site-original/` — espelho wget da home + `/links/` com CSS/JS/fontes (~15 MB). Não editar; é arquivo.
- `conteudo/imagens/` — 23 imagens originais reaproveitáveis (sem thumbnails). `conteudo/prova/` — 23 imagens (8 depoimentos + 5 grupo + 5 pares antes/depois 9:16 com cinturas alinhadas e rostos removidos). `conteudo/layout/` — HTML de referência.
- `INVENTARIO.md` — mapa do que foi baixado, vídeo (embed YouTube `youtu.be/WDEG6QFKQlo`, sem MP4 próprio) e problemas do original.
- `ANALISE-COPY.md` — auditoria de copy seção por seção (skill `copywriting`), proposta de reordenação e backlog. Ler antes de mexer em textos.
- `QUESTIONARIO.md` — perguntas para a dona (oferta, público, prova, produto, marca, jurídico). Aguardando respostas; aplicar na landing quando chegarem.
- `BACKLOG.md` — futuro (não fazer agora): site dinâmico com ofertas via `?oferta=` e preview de link com imagem (OG).
- `nova-landing/index.html` — rebuild estático single-file (CSS/JS inline, sem build). Título/marca "Pilates em Casa". Imagens via `../conteudo/imagens/`, prova via `../conteudo/prova/`, checkout via `OFERTAS` + `?oferta=` (padrão Kiwify `U4n0qN7`), oferta De R$97 por R$57 (pilha R$613, 8 bônus), OG `imagens/og-capa.jpg` (URL absoluta do domínio temporário — trocar no domínio final), CTA fixo mobile com preço + pulse + estrelas. `privacidade.html` + `termos.html` (LGPD/CDC padrão). Sem vídeo (dona pediu remoção). Abrir o arquivo direto no browser p/ preview.
- `tests/` (`npm test`) — Playwright smoke da landing (título/H1/CTAs, oferta com preço, imagens 200, FAQ, páginas legais). Config sobe o preview sozinho via `webServer` na porta 8901. Requer deps de sistema do Chromium (ver abaixo).
- Dev: `npm run dev` (serve a raiz na 8901). Deploy: push na `main` → Action monta `deploy/` (gitignored) e envia p/ raiz do FTP via FTPS (secrets `FTP_SERVER/USERNAME/PASSWORD`). Ver `README.md`.
- Skills: `design-taste-frontend` + `frontend-design` (criação UI), `web-design-guidelines` (auditoria UI), `accessibility` (WCAG), `copywriting`, `playwright-cli`, `find-skills` (via `npx skills`, pin em `skills-lock.json`).
- Tema claro/escuro: segue o sistema por padrão, toggle sol/lua no nav alterna e persiste (`localStorage tema` + `html[data-tema]` + tokens `light-dark()`).
- Sem build/test/lint além de `tests/`.

## Working here
- Do not assume a stack. Ask or inspect before adding tooling.
- When a stack is chosen, update this file with: exact install/dev/build/test/lint commands, entrypoints, and monorepo boundaries.
- Prefer editing existing files over creating new ones; keep new files only when needed.

## Skills
- `design-taste-frontend` is installed for landing-page / portfolio / redesign work. Follow its brief-inference + design-read workflow when doing frontend design; ignore it for non-design tasks.
