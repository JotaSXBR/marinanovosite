<?php
// PARTES DO PAINEL — não são acessíveis por URL (partes/.htaccess nega tudo);
// o index.php carrega por require. São só templates: recebem dados por variável.
// Para criar uma nova parte: copie um bloco daqui e chame com require em index.php.
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#FFF7FC">
<title>Painel de Links | Pilates em Casa</title>
<style>
/* Tokens da marca + escala de raio documentada (Shape Consistency Lock):
   botao = pill (999) | card = 14 | campo = 10 | pílula/etiqueta = pill.
   Notar: nao ha design system instalavel aqui — o painel e PHP single-file,
   sem build e sem CDN, num cPanel compartilhado. As referencias de "design
   system" (Carbon/Fluent) ficariam de fora por serem npm + build. */
:root{
  --tinta:#2B1226; --cinza:#6B5565; --fundo:#FFF7FC; --branco:#fff;
  --borda:#F0D4E6; --faixa:#FBE9F5; --cta:#A80077; --cta-hover:#86025F;
  --sucesso:#0a7d3e; --sucesso-fundo:#e8f7ee; --sucesso-borda:#b7e3c8;
  --perigo:#a11; --perigo-fundo:#fdecec; --perigo-borda:#f2c2c2;
  --foco:0 0 0 3px #fff, 0 0 0 5.5px var(--cta);
}
*{box-sizing:border-box}
html{overflow-x:hidden}
body{
  margin:0;font-family:system-ui,"Segoe UI",Arial,sans-serif;background:var(--fundo);
  color:var(--tinta);line-height:1.6;-webkit-font-smoothing:antialiased;
  touch-action:manipulation;overflow-x:hidden;
}
body *::selection{background:#FFD9EF}
.wrap{max-width:980px;margin:0 auto;padding:28px 20px 60px}
h1{font-size:1.5rem;margin:0 0 4px;text-wrap:balance}
h2{font-size:1.1rem;margin:34px 0 12px;text-wrap:balance}
.sub{color:var(--cinza);font-size:.92rem;margin:0 0 22px;max-width:68ch}
.card{background:var(--branco);border:1px solid var(--borda);border-radius:14px;padding:22px;margin-bottom:18px}
label{display:block;font-size:.85rem;font-weight:600;margin:12px 0 4px}
.ajuda{color:var(--cinza);font-size:.82rem;margin:5px 0 0}
input,select{
  width:100%;padding:11px 12px;border:1px solid var(--borda);border-radius:10px;
  font-size:.95rem;font-family:inherit;background:#fff;color:var(--tinta);
  color-scheme:light;
}
input::placeholder{color:#8a7684}
/* Foco visivel em TUDO que e clicavel (nao so em campos) */
input:focus-visible,select:focus-visible,button:focus-visible,.btn:focus-visible,summary:focus-visible,a:focus-visible{
  outline:2px solid var(--cta);outline-offset:2px;border-radius:10px;
}
button,.btn{
  background:var(--cta);color:#fff;border:0;border-radius:999px;padding:12px 22px;
  font-size:.95rem;font-weight:700;cursor:pointer;font-family:inherit;text-decoration:none;
  display:inline-block;transition:background .15s ease,transform .08s ease;
}
button:hover,.btn:hover{background:var(--cta-hover)}
button:active,.btn:active{transform:scale(.97)}
button.sec{background:#fff;color:var(--cta);border:1px solid var(--borda)}
button.sec:hover{background:var(--faixa)}
button.perigo{background:#fff;color:var(--perigo);border:1px solid var(--perigo-borda)}
button.perigo:hover{background:var(--perigo-fundo)}
table{width:100%;border-collapse:collapse;font-size:.92rem}
th,td{text-align:left;padding:11px 10px;border-bottom:1px solid var(--faixa);vertical-align:top}
th{font-size:.78rem;text-transform:uppercase;letter-spacing:.06em;color:var(--cinza);white-space:nowrap}
/* alinhar: numero no th e no td vao para a direita, com a mesma largura de
   digito (tabular-nums) para as colunas ficarem retas */
th.num,td.num{text-align:right;font-variant-numeric:tabular-nums}
td.num,th.num{padding-left:20px}
.nowrap{white-space:nowrap}
tfoot .totais td{font-weight:700;background:var(--faixa);border-bottom:0;border-top:2px solid var(--borda)}
.scroll{overflow-x:auto;-webkit-overflow-scrolling:touch}
.linkbox{
  font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:.82rem;background:var(--faixa);
  border:1px solid var(--borda);border-radius:10px;padding:11px;word-break:break-all;
}
.flash,.erro{border-radius:10px;padding:11px 14px;margin-bottom:16px;font-size:.92rem}
.flash{background:var(--sucesso-fundo);border:1px solid var(--sucesso-borda);color:var(--sucesso)}
.erro{background:var(--erro-fundo);border:1px solid var(--perigo-borda);color:var(--perigo)}
.muted{color:var(--cinza);font-size:.82rem}
.pill{display:inline-block;background:var(--faixa);border:1px solid var(--borda);border-radius:999px;padding:2px 11px;font-size:.78rem;font-weight:600}
.login{max-width:400px;margin:8vh auto}
.bar{display:flex;justify-content:space-between;align-items:center;gap:14px;flex-wrap:wrap}
.acoes-topo{display:flex;gap:10px}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.grid4{display:grid;grid-template-columns:repeat(4,1fr);gap:14px}
.chips{display:flex;flex-wrap:wrap;gap:10px;margin-top:14px}
.chips button{font-weight:600;font-size:.9rem;padding:10px 16px}

/* Apagar em 2 passos, sem JS: o primeiro toque so revela a confirmacao.
   Motivo: o painel mexe em PRECO e CHECKOUT — um toque errado aqui quebra
   a venda. Um confirm() nativo e facil de passar sem ler. */
.confirm{margin:0}
.confirm>summary{
  list-style:none;cursor:pointer;display:inline-block;background:#fff;color:var(--perigo);
  border:1px solid var(--perigo-borda);border-radius:999px;padding:8px 18px;
  font-size:.85rem;font-weight:700;
}
.confirm>summary::-webkit-details-marker{display:none}
.confirm>summary:hover{background:var(--perigo-fundo)}
.confirm[open]>summary{background:var(--perigo);color:#fff;border-color:var(--perigo)}
.confirm-corpo{
  margin-top:10px;padding:12px;border:1px solid var(--perigo-borda);
  border-radius:10px;background:var(--perigo-fundo);font-size:.85rem;max-width:320px;
}
.confirm-corpo p{margin:0 0 10px;color:var(--tinta)}
.confirm-corpo .acoes{display:flex;gap:8px}
.confirm-corpo button{padding:8px 16px;font-size:.85rem}

/* Estado vazio */
.vazio{
  text-align:center;padding:30px 18px;border:1px dashed var(--borda);
  border-radius:14px;background:var(--branco);color:var(--cinza);
}
.vazio b{display:block;color:var(--tinta);margin-bottom:6px;font-size:.98rem}
.vazio p{margin:0;font-size:.88rem}

.skip{
  position:absolute;left:12px;top:-70px;z-index:100;background:var(--tinta);color:#fff;
  padding:10px 18px;border-radius:0 0 12px 12px;font-weight:700;text-decoration:none;
  transition:top .2s ease;
}
.skip:focus{top:0;color:#fff}

@media(max-width:640px){
  .grid2,.grid4{grid-template-columns:1fr}
  .wrap{padding:20px 14px 50px}
}

/* ---- Celular -----------------------------------------------------------
   A dona usa o celular o dia inteiro (negócio roda no WhatsApp/TikTok), então
   o painel precisa caber em 360-430px. Três regras que se sustentam:

   1) NADA pode forçar a largura do documento. Se um elemento estica a página,
      o browser amplia o viewport de layout, o breakpoint @media deixa de
      casar e o layout "mobile" nunca entra — um loop. Foi exatamente o que
      acontecia: a tabela empurrava para 814px e a regra de cards (720px)
      nunca aplicava. Por isso max-width/min-width:0 em tudo e a tabela
      dentro de um contêiner com overflow próprio.
   2) Alvo de toque mínimo 44px (WCAG 2.5.5) e fonte de input ≥16px, senão o
      iOS dá zoom ao focar e a página "pula".
   3) Regra por seção, declarada aqui — não "o framework cuida". */
@media(max-width:760px){
  html,body{max-width:100%;overflow-x:hidden}

  /* espaço no fim da página para a barra de seções fixa + notch do iPhone */
  .wrap{padding:16px 14px calc(104px + env(safe-area-inset-bottom))}

  h1{font-size:1.3rem}
  h2{margin:28px 0 10px;scroll-margin-top:12px}
  .card{padding:16px 14px}

  /* (2) alvos de toque e zoom do iOS */
  button,.btn{min-height:44px;padding:10px 18px;display:inline-flex;align-items:center;justify-content:center}
  input,select{font-size:16px;padding:12px}
  label{font-size:.88rem;margin:14px 0 5px}
  .confirm>summary{min-height:44px;display:inline-flex;align-items:center;padding:10px 18px}
  .confirm[open]>summary{min-height:44px}
  /* canais em 2 colunas: em coluna unica os 6 botoes ocupavam a tela inteira */
  .chips{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:12px}
  .chips button{
    width:100%;justify-content:flex-start;text-align:left;
    font-size:.82rem;font-weight:600;line-height:1.25;padding:10px 12px;min-height:52px;
  }
  .acoes-linha{width:100%;flex-wrap:wrap}
  .acoes-linha > *{flex:1 1 auto}
  /* topo: titulo, subtítulo curto e acoes lado a lado */
  .bar{gap:10px}
  .sub{font-size:.88rem;margin:0 0 4px}
  .acoes-topo{width:100%}
  .acoes-topo .btn{flex:1 1 auto;font-size:.86rem}
  .confirm{width:100%}
  .confirm-corpo{max-width:none}

  /* (1) tabela vira card. O wrapper MANTÉM overflow:auto como rede de
     segurança: se algo escapar, rola dentro do card em vez de alargar a
     página inteira.

     Rótulo ACIMA do valor, não ao lado: em coluna dupla o rótulo comia 34%
     e o valor ficava espremido — a URL do checkout quebrava em ~6 caracteres
     por linha e o nome do carrinho ia para 4 linhas. Empilhado, o valor usa
     a largura toda. */
  .scroll{overflow-x:auto;max-width:100%}
  table,tbody,tr,td,tfoot,thead{display:block;max-width:100%}
  thead{display:none}
  tr{
    border:1px solid var(--borda);border-radius:14px;background:var(--branco);
    margin-bottom:10px;padding:4px 0;min-width:0;
  }
  td{
    border:0;padding:7px 14px;min-width:0;overflow-wrap:anywhere;
  }
  td::before{
    content:attr(data-label);display:block;
    font-weight:700;color:var(--cinza);font-size:.68rem;
    text-transform:uppercase;letter-spacing:.07em;margin-bottom:1px;
  }
  td.num{text-align:left}
  td:empty{display:none}
  td.sem-rotulo{padding-top:10px}
  td.sem-rotulo::before{content:none}
  /* a URL do checkout agora tem a largura inteira da linha */
  .linkbox{max-width:100%;word-break:break-all;white-space:normal}
  /* nome + checkout sao dois blocos, nao coisa nenhuma na mesma linha */
  td > .linkbox{display:block;margin-top:5px}
  tfoot .totais{border:2px solid var(--borda);background:var(--faixa);border-radius:14px}
  tfoot .totais td{background:transparent;border:0}
  tfoot .totais td:first-child::before{content:"Total geral"}
  tfoot .totais td:first-child{font-weight:700}

  /* barra fixa de seções: 4 telas de 700px de rolagem sem recourse */
  .menu-secoes{
    display:flex;position:fixed;left:0;right:0;bottom:0;z-index:50;
    background:rgba(255,255,255,.96);backdrop-filter:blur(8px);
    border-top:1px solid var(--borda);
    padding:6px 6px calc(6px + env(safe-area-inset-bottom));
  }
  .menu-secoes a{
    flex:1;text-align:center;text-decoration:none;color:var(--cta);
    font-size:.86rem;font-weight:700;padding:9px 2px;min-height:46px;
    display:flex;align-items:center;justify-content:center;
  }
  .menu-secoes a:active{background:var(--faixa)}
}
@media(min-width:761px){.menu-secoes{display:none}}

@media(prefers-reduced-motion:reduce){
  *,*::before,*::after{transition:none !important;animation:none !important}
  html{scroll-behavior:auto}
}
</style>
</head>
<body>
<a class="skip" href="#conteudo">Ir para o conteúdo</a>
<div class="wrap" id="conteudo">
