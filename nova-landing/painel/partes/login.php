<?php
// PARTES DO PAINEL — não são acessíveis por URL (partes/.htaccess nega tudo);
// o index.php carrega por require. São só templates: recebem dados por variável.
// Para criar uma nova parte: copie um bloco daqui e chame com require em index.php.
  <div class="login">
    <div class="card">
      <h1>Painel de Links</h1>
      <p class="sub">Pilates em Casa — área restrita da proprietária.</p>
      <?php if ($erro): ?><div class="erro"><?= e($erro) ?></div><?php endif; ?>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="acao" value="entrar">
        <label for="senha">Senha</label>
        <input id="senha" name="senha" type="password" autocomplete="current-password" required>
        <div style="margin-top:18px"><button type="submit" style="width:100%">Entrar</button></div>
      </form>
    </div>
  </div>
