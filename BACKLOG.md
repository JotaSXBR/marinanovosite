# Backlog futuro

> **Estado em 17/10/2026:** os 3 itens abaixo estão **implementados** e ficam como
> registro do que foi feito e decidido. O que segue realmente aberto é
> `QUESTIONARIO.md` (respostas da dona) e o ajuste de plataforma no servidor
> (ver "Compressão/HTTP2" no `AGENTS.md`, que depende de WHM).

## 1. Site dinâmico: botões para checkouts/preços diferentes ✅ IMPLEMENTADO (server-side)

> SUPERSEDE o plano original com objeto `OFERTAS` no JS (vazava tudo no fonte).
> Implementado em out/2026: `nova-landing/index.php` resolve `?o=CODIGO` no
> servidor lendo `nova-landing/private/ofertas.php` (bloqueado por `.htaccess`
> + guarda 403); HTML entregue contém só a oferta ativa; secreta = `noindex`
> automático; `?oferta=` antigo mantido como alias. `index.html` virou snapshot
> estático da padrão (conteúdo = `indexdesign.html` promovido a principal).

Objetivo da dona: poder trocar o destino dos botões (outro link Kiwify, outro preço) sem editar o HTML na mão.

Como vamos fazer:
- Criar um bloco `OFERTAS` no topo de `nova-landing/index.html` (objeto JS): cada oferta tem `checkout` (URL Kiwify), `preco`, `precoCheio` e `rotulo`.
- Chave de seleção via parâmetro de URL: `?oferta=padrao | promo | vip`. Ex.: Instagram usa `?oferta=promo`, WhatsApp usa `?oferta=vip`.
- JS na carga lê o parâmetro e troca: todos os CTAs (`a.btn`), valores do cartão da oferta (De/por) e linha de total. Sem parâmetro, cai na oferta padrão (a atual: R$ 57).
- Teste Playwright novo: abrir com `?oferta=X` e conferir href dos botões + valores na tela.
- Deploy continua igual (arquivo único, sem backend).

Pendente da dona: quais ofertas (nome, preço cheio, preço final, link Kiwify de cada uma). Só acrescentar entradas no objeto `OFERTAS`. ✅ Base no ar com `padrao`.

## 2. Preview de link com imagem (WhatsApp/Instagram) ✅ IMPLEMENTADO

Hoje o link mostra só texto (ver print). Para aparecer a capa como no exemplo do checkout Kiwify:
- Criar `imagens/og-capa.jpg` 1200x630 (montar a partir da capa atual: fundo rosa + "DESAFIO PILATES EM CASA" + foto; reaproveitar `capa-video-Mari.png` ou `Nath-com-fundo.png`).
- Adicionar OG/Twitter tags no `<head>`: `og:title`, `og:description`, `og:image` (URL absoluta `https://<dominio-final>/imagens/og-capa.jpg`), `og:type=website`, `twitter:card=summary_large_image`.
- Atenção: WhatsApp guarda cache do preview; após publicar, forçar releitura (reenviar com parâmetro bobo `?v=2` ou aguardar expirar).
- Validar com os testes: checar presença das tags no HTML. ✅ `og-capa.jpg` 1200x630 gerada + tags no ar. ✅ RESOLVIDO: `index.php` monta `og:url`/`og:image` do host atual (não há mais URL fixa a trocar no go-live) e o `index.html` usa `https://maricoachfit.com/`. Único cuidado residual: o WhatsApp guarda cache do preview — ao publicar mudança de capa, reenviar o link com um parâmetro bobo (`?v=2`) para forçar releitura.

## 3. Botão fixo no mobile com animações (CTA sempre visível) ✅ IMPLEMENTADO (base) — ver pesquisa abaixo

**Pesquisa (out/2026):** o padrão vencedor em infoprodutos e ecommerce é barra/botão fixo no rodapé do mobile, surgindo após o CTA do hero sair da tela e sumindo perto da oferta (exatamente o nosso comportamento; estudos citam +5–15% conversão mobile). ✅ Aplicado: minibarra com preço (De R$ 97 por R$ 57) + pulse rosa no lugar da borda giratória + estrelas mantidas discretas.
1. Incluir preço na barra fixa (âncora riscada + preço atual) — item de maior impacto segundo os guias; nosso botão hoje é só texto.
2. Animação padrão do mercado é o "botão pulsante" (brilho pulsante via box-shadow, ~2s) — mais testado que borda giratória; avaliar trocar ou A/B testar.
3. Altura mínima 56px, entrada deslizante 200–300ms, 1 único CTA por barra.
4. Desktop: barra fixa tem menos efeito (CTAs já visíveis); manter mobile-only por ora.

Objetivo: no celular, ao rolar para baixo, um botão "Quero entrar para o desafio" fixo no rodapé da tela, com borda animada + microexplosão de estrelinhas douradas/prateadas dentro do botão.

Como vamos fazer (só CSS + JS leve, sem biblioteca):
- **Fixo + inteligente:** `position:fixed; bottom:0` só abaixo de 768px; aparece após rolar ~600px (IntersectionObserver no hero como gatilho) e se esconde perto do CTA final e da dobra de oferta (para não duplicar botão na tela). Respeita `prefers-reduced-motion` (vira botão fixo estático).
- **Borda animada:** `conic-gradient` girando via `@property --angulo` + `@keyframes` (fallback: borda sólida rosa em navegador antigo), só `transform/opacity` no loop para não pesar o mobile.
- **Estrelas:** 6–8 partículas (✦ via CSS/`content`, dourado `#FFD166` + prata `#E8E8EC`) emitidas dentro do botão a cada ~4s com Web Animations API (`element.animate`, sem reflow de layout). Pausam com a aba oculta (`document.hidden`) e com `prefers-reduced-motion`.
- **Acessibilidade:** `aria-hidden` nas partículas, botão com mesmo rótulo e destino dos demais (regra de CTA único), altura mínima 48px, não cobre o conteúdo (adicionar `padding-bottom` de respiro no `footer` no mobile).
- **Teste Playwright novo:** no viewport mobile, rolar a página e conferir que o botão aparece, tem o href do checkout e não sobrepõe o CTA da oferta (checar `getBoundingClientRect` sem interseção).

## 5. Lighthouse 100% (rodada de 17/10/2026) ✅ FEITO, 1 pendência de host

Relatório inicial em `research/maricoachfit.com-20261017T093242.json`:
Performance 94 · Acessibilidade 91 · Boas práticas 100 · SEO 83 · Agentic 100.

**Resultado final (medido em 17/10/2026, sem extensão, auditoria limpa):**
Desktop Performance 100 · Acessibilidade 100 · Boas práticas 100 · SEO 100 · Agentic 100.
Mobile (emulado) Performance 100 · Acessibilidade 100 · Boas práticas 100 · SEO 100.

**Corrigido no código (17/10/2026):**

1. **Acessibilidade → `color-contrast`**: só no tema escuro. Token novo
   `--cta-texto:light-dark(#A80077,#F87DFF)` para texto (o `--cta` continua cor de
   fundo); 1,84:1 e 4,06:1 → 5,24:1 / 5,88–7,04:1.
2. **SEO → `canonical`**: o `href="./"` era reprovado por ser **relativo**
   ("Is not an absolute URL (./)"), não por apontar para a raiz. Agora é absoluto
   (`$baseUrl`), o que também consolida as URLs `?o=`/`?src=`/UTM.
3. **Performance → imagens**: `srcset`/`sizes` no hero e nos 13 cards dos
   carrosséis. Hero 106→53 KB; depoimentos 29–54→8–11 KB; grupo 48–80→17–21 KB
   (647 KiB que o Lighthouse apontava como desperdício).
   **Rodada 2 (mobile):** ladder do hero foi 640/1244 — em DPR ≥1,75 o
   navegador pulava para o arquivo completo (77 KiB desperdiçados). Adicionados
   828w e 1024w (640/828/1024/1244) → mobile Perf 99→100. Sobram 22–29 KiB
   no ladder dos cards (lazy, abaixo da dobra) — opcional, não afeta a nota.
4. **Performance → fontes**: self-host (Anton + Montserrat em `.woff2`). Sai o
   `<link>` do Google Fonts (180–279 ms de render-blocking + cadeia de 879 ms
   até o `gstatic`); tudo vira `@font-face` inline + `fontes/`. **Zero recurso
   bloqueante** na página.
5. **Bug achado no caminho**: a porta entrava no `$host` sem o `:`
   (`127.0.0.1:8080` → `127.0.0.18080`) e sujava `og:url`/`canonical`.

**Ruído (não é do site):** `unused-javascript` (342 KiB), `unminified-javascript`
(73 KiB) e `image-alt` vinham de extensões do navegador — somem numa auditoria sem
extensão (receita no `README.md`).

**Pendência única, fora do código:** `modern-http-insight` reprova tudo por
`protocol: http/1.1`. Precisa do pacote `ea-nginx-http2` (e `ea-nginx-gzip` /
`ea-nginx-brotli`) instalado no WHM como root — chamado do host, não do site.
