<?php
// PARTES DO PAINEL — não são acessíveis por URL (partes/.htaccess nega tudo);
// o index.php carrega por require. São só templates: recebem dados por variável.
// Para criar uma nova parte: copie um bloco daqui e chame com require em index.php.
?>
  <div class="login">
    <div class="card">
      <h1>Painel de Links</h1>
      <p class="sub">Pilates em Casa — área restrita da proprietária.</p>
      <?php if ($erro): ?><div class="erro"><?= e($erro) ?></div><?php endif; ?>
      <?php /* Trava de duplo toque: dois POSTs seguidos do login contam como
               insistência e viram bloqueio PERMANENTE. O botão desabilita no
               primeiro envio, então o segundo toque não acha o que clicar.
               O rótulo vira "Entrando…" (estado de carregando): sem isso, o
               toque parecia não ter feito nada enquanto o servidor respondia.
               Sem JS o form continua funcionando normal. */ ?>
      <form method="post" onsubmit="var b=this.querySelector('button[type=submit]');b.disabled=true;b.textContent='Entrando…'">
        <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="acao" value="entrar">
        <label for="senha">Senha</label>
        <input id="senha" name="senha" type="password" autocomplete="current-password" required>
        <div style="margin-top:18px"><button type="submit" style="width:100%">Entrar</button></div>
      </form>
    </div>
  </div>
