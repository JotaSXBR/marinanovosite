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

/* ---- Colapso da tabela em cards no celular ----------------------------
   A dona usa o celular no dia a dia e a tabela de carrinhos tem 8 colunas:
   rolagem horizontal em 360px e ilegivel. Cada <td> recebe data-label e vira
   uma linha rotulada. Regra explicita por secao, nao "o framework cuida". */
@media(max-width:720px){
  .scroll{overflow-x:visible}
  table,tbody,tr,td,tfoot{display:block;width:100%}
  thead{display:none}
  tr{
    border:1px solid var(--borda);border-radius:14px;background:var(--branco);
    margin-bottom:12px;padding:6px 2px;
  }
  td{
    display:flex;justify-content:space-between;align-items:baseline;gap:16px;
    border:0;padding:9px 14px;
  }
  td::before{
    content:attr(data-label);font-weight:600;color:var(--cinza);
    font-size:.76rem;text-transform:uppercase;letter-spacing:.05em;flex:none;
  }
  td.num{text-align:right}
  /* celula que NAO tem rotulo (ex.: a de acoes) ocupa a linha toda */
  td:empty{display:none}
  td.sem-rotulo{justify-content:flex-start}
  td.sem-rotulo::before{content:none}
  tfoot .totais{border:2px solid var(--borda);background:var(--faixa)}
  tfoot .totais td{background:transparent}
  tfoot .totais td:first-child::before{content:"Total geral"}
  tfoot .totais td:first-child{font-weight:700}
  .acoes-linha{display:flex;flex-wrap:wrap;gap:8px;align-items:center}
}

@media(prefers-reduced-motion:reduce){
  *,*::before,*::after{transition:none !important;animation:none !important}
}
</style>
</head>
<body>
<a class="skip" href="#conteudo">Ir para o conteúdo</a>
<div class="wrap" id="conteudo">
