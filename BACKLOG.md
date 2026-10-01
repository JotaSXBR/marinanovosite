# Backlog futuro (fazer depois)

## 1. Site dinâmico: botões para checkouts/preços diferentes

Objetivo da dona: poder trocar o destino dos botões (outro link Kiwify, outro preço) sem editar o HTML na mão.

Como vamos fazer:
- Criar um bloco `OFERTAS` no topo de `nova-landing/index.html` (objeto JS): cada oferta tem `checkout` (URL Kiwify), `preco`, `precoCheio` e `rotulo`.
- Chave de seleção via parâmetro de URL: `?oferta=padrao | promo | vip`. Ex.: Instagram usa `?oferta=promo`, WhatsApp usa `?oferta=vip`.
- JS na carga lê o parâmetro e troca: todos os CTAs (`a.btn`), valores do cartão da oferta (De/por) e linha de total. Sem parâmetro, cai na oferta padrão (a atual: R$ 57).
- Teste Playwright novo: abrir com `?oferta=X` e conferir href dos botões + valores na tela.
- Deploy continua igual (arquivo único, sem backend).

Pendente da dona: quais ofertas (nome, preço cheio, preço final, link Kiwify de cada uma).

## 2. Preview de link com imagem (WhatsApp/Instagram)

Hoje o link mostra só texto (ver print). Para aparecer a capa como no exemplo do checkout Kiwify:
- Criar `imagens/og-capa.jpg` 1200x630 (montar a partir da capa atual: fundo rosa + "DESAFIO PILATES EM CASA" + foto; reaproveitar `capa-video-Mari.png` ou `Nath-com-fundo.png`).
- Adicionar OG/Twitter tags no `<head>`: `og:title`, `og:description`, `og:image` (URL absoluta `https://<dominio-final>/imagens/og-capa.jpg`), `og:type=website`, `twitter:card=summary_large_image`.
- Atenção: WhatsApp guarda cache do preview; após publicar, forçar releitura (reenviar com parâmetro bobo `?v=2` ou aguardar expirar).
- Validar com os testes: checar presença das tags no HTML.
