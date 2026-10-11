<?php
// PARTES DO PAINEL — não são acessíveis por URL (partes/.htaccess nega tudo);
// o index.php carrega por require. São só templates: recebem dados por variável.
// Para criar uma nova parte: copie um bloco daqui e chame com require em index.php.
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
</script>
</div>
</body>
