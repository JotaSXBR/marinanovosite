# Inventário do site original — maricoachfit.com

Data do scrape: 2026-09-30. Origem: WordPress + Elementor (tema Hello Elementor) na Hostinger.
Domínio correto: **maricoachfit.com** (sem "t"). `maricoatchfit.com` não existe.

## Onde está cada coisa
- `site-original/index.html` — HTML completo da home (256 KB, espelho wget com CSS/JS/fontes).
- `site-original/links.html` — página `/links/` (bio/links).
- `site-original/wp-content/uploads/2025/06/` — todas as mídias baixadas (~15 MB total).
- `conteudo/imagens/` — 23 originais com nome limpo, sem thumbnails WordPress `-300x200` (prontos p/ reaproveitar).
- `conteudo/layout/home.html`, `links.html` — cópias de referência do layout.

## Vídeo
- Nenhum MP4/WebM hospedado no site. O "vídeo" da home é um embed YouTube via widget Elementor:
  `https://youtu.be/WDEG6QFKQlo` com overlay `capa-video-Mari.png`.
- Para reaproveitar: manter o embed ou re-uploadar; não há arquivo de vídeo para baixar do WordPress.

## Imagens reaproveitáveis (23)
- Marca: `Logo-Mari.png`, `capa-video-Mari.png`, `Banner-Mari-rosa-2.png`, `Banner-tijolinhos-Mari.png`
- Persona/autoridade: `Nath-com-fundo.png` (foto da instrutora Natália Pereira)
- Prova social: `1.jpeg`, `2.jpeg`, `xrthdtyhy.jpeg`, `e56yhrtyyt.jpeg`, `dp1.jpeg`–`dp8.jpeg`, `1-1.png`, `2-1.png`, `3-1.png`
- Oferta/bônus: `cha-secreto.png`, `mockup-e-book-1.png`, `Satisfacao-Garantida.svg`

## Textos reaproveitáveis (resumo, ver HTML p/ íntegra)
- Promessa: "mais de 45 mil alunas", treinos 15–20 min/dia, sem equipamentos, acesso vitalício, app exclusivo, Kiwify checkout `pay.kiwify.com.br/U4n0qN7` (6 CTAs).
- Bônus: Chá Secreto, Plano Alimentar (+vegano/vegetariano), 380+ receitas, jejum intermitente, celulite, marmita fitness, comunidade WhatsApp, planner de resultados.
- FAQ (10 perguntas), garantia 7 dias, contato WhatsApp `553173080374`, Instagram `pilatesnaparede.desafio`.
- Persona: Natália Pereira, fisioterapeuta 11+ anos.

## Problemas detectados no original (não replicar sem corrigir)
- Seções duplicadas no HTML: "O que os nossos alunos falam" 2x e "Olha só tudo o que te espera" 2x.
- Typos visíveis: "Oha só" (2x), "excusivo", "De R$ 59,90 / Por R$ 0,00" (preço zerado confunde).
- SEO: `<title>` genérico "Mari Coach Fit", sem meta description, sem H1 (só H2s).
