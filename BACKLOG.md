# Backlog futuro

## 1. Site dinâmico: botões para checkouts/preços diferentes ✅ IMPLEMENTADO (base)

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
- Validar com os testes: checar presença das tags no HTML. ✅ `og-capa.jpg` 1200x630 gerada + tags no ar. PENDENTE: trocar a URL absoluta para o domínio final (hoje aponta o temporário) e forçar releitura do cache do WhatsApp.

## 3. Botão fixo no mobile com animações (CTA sempre visível) ✅ IMPLEMENTADO

Objetivo: no celular, ao rolar para baixo, um botão "Quero entrar para o desafio" fixo no rodapé da tela, com borda animada + microexplosão de estrelinhas douradas/prateadas dentro do botão.

Como vamos fazer (só CSS + JS leve, sem biblioteca):
- **Fixo + inteligente:** `position:fixed; bottom:0` só abaixo de 768px; aparece após rolar ~600px (IntersectionObserver no hero como gatilho) e se esconde perto do CTA final e da dobra de oferta (para não duplicar botão na tela). Respeita `prefers-reduced-motion` (vira botão fixo estático).
- **Borda animada:** `conic-gradient` girando via `@property --angulo` + `@keyframes` (fallback: borda sólida rosa em navegador antigo), só `transform/opacity` no loop para não pesar o mobile.
- **Estrelas:** 6–8 partículas (✦ via CSS/`content`, dourado `#FFD166` + prata `#E8E8EC`) emitidas dentro do botão a cada ~4s com Web Animations API (`element.animate`, sem reflow de layout). Pausam com a aba oculta (`document.hidden`) e com `prefers-reduced-motion`.
- **Acessibilidade:** `aria-hidden` nas partículas, botão com mesmo rótulo e destino dos demais (regra de CTA único), altura mínima 48px, não cobre o conteúdo (adicionar `padding-bottom` de respiro no `footer` no mobile).
- **Teste Playwright novo:** no viewport mobile, rolar a página e conferir que o botão aparece, tem o href do checkout e não sobrepõe o CTA da oferta (checar `getBoundingClientRect` sem interseção).
