<?php
// PARTES DO PAINEL — não são acessíveis por URL (partes/.htaccess nega tudo);
// o index.php carrega por require. São só templates: recebem dados por variável.
// Para criar uma nova parte: copie um bloco daqui e chame com require em index.php.
?>
<script>
function copiar(btn, url){
  var done = function(){ var t = btn.textContent; btn.textContent = 'Copiado!'; setTimeout(function(){ btn.textContent = t; }, 1600); };
  if (navigator.clipboard && window.isSecureContext) { navigator.clipboard.writeText(url).then(done, function(){}); return; }
  var ta = document.createElement('textarea');
  ta.value = url; ta.setAttribute('readonly',''); ta.style.position='absolute'; ta.style.left='-9999px';
  document.body.appendChild(ta); ta.select();
  try { document.execCommand('copy'); done(); } catch (e) {}
  document.body.removeChild(ta);
}

/* Interruptor sol/lua. O `data-tema` ja foi resolvido no <head>, entao aqui so
   falta reagir ao clique. O rotulo mostra o DESTINO ("Escuro" = clicar fica
   escuro), e o icone e SVG de verdade: glifo de caractere vira quadradinho
   vazio em Android. */
(function () {
  var b = document.getElementById('temaBtn'); if (!b) return;
  var r = document.getElementById('temaRotulo');
  var svg = b.querySelector('.tema-i');
  var ICONE = {
    lua: '<path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/>',
    sol: '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>'
  };
  function escuro() { return document.documentElement.getAttribute('data-tema') === 'escuro'; }
  function pintar() {
    var e = escuro();
    r.textContent = e ? 'Claro' : 'Escuro';
    b.setAttribute('aria-label', e ? 'Mudar para o tema claro' : 'Mudar para o tema escuro');
    svg.innerHTML = e ? ICONE.sol : ICONE.lua;
    /* a barra do navegador no celular segue o tema pelo meta theme-color, que
       somente a preferencia do sistema e nao a este botao */
    var cor = e ? '#16101A' : '#FFF7FC';
    var metas = document.querySelectorAll('meta[name="theme-color"]');
    for (var i = 0; i < metas.length; i++) { metas[i].removeAttribute('media'); metas[i].setAttribute('content', cor); }
  }
  b.addEventListener('click', function () {
    var novo = escuro() ? 'claro' : 'escuro';
    document.documentElement.setAttribute('data-tema', novo);
    try { localStorage.setItem('painelTema', novo); } catch (e) {}
    pintar();
  });
  pintar();
})();
</script>
</div>
</body>
