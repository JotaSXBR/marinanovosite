<?php
// PARTES DO PAINEL — não são acessíveis por URL (partes/.htaccess nega tudo);
// o index.php carrega por require. São só templates: recebem dados por variável.
// Para criar uma nova parte: copie um bloco daqui e chame com require em index.php.
?>
  <div class="bar">
    <div>
      <h1>Painel de Links</h1>
      <p class="sub">Visitas por canal. As <b>vendas</b> aparecem na Kiwify, pelo mesmo código de canal.</p>
    </div>
    <div class="acoes-topo">
      <button type="button" class="btn sec tema-btn" id="temaBtn" title="Alternar entre tema claro e escuro">
        <svg class="tema-i" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"></svg>
        <span id="temaRotulo">Escuro</span>
      </button>
      <a class="btn sec" href="?csv=1">Baixar CSV</a>
      <a class="btn sec" href="?sair=1">Sair</a>
    </div>
  </div>

  <div aria-live="polite">
    <?php if ($flash): ?><div class="flash"><?= e($flash) ?></div><?php endif; ?>
    <?php if ($erro): ?><div class="erro"><?= e($erro) ?></div><?php endif; ?>
  </div>

  <?php // Atalhos das seções: só aparecem no celular (CSS), onde a rolagem
        // de ~7 telas tornava "achar os carrinhos" custoso. "Segurança" leva
        // ao fim da página (tentativas/bloqueios + as limpezas).
        // Sem ícones: glifo geométrico (▤ ▣) não é garantido nas fontes de
        // Android e vira quadradinho vazio. O rótulo já diz tudo. ?>
  <nav class="menu-secoes" aria-label="Seções do painel">
    <a href="#sec-cliques">Cliques</a>
    <a href="#sec-carrinhos">Carrinhos</a>
    <a href="#sec-links">Links</a>
    <a href="#sec-login">Segurança</a>
  </nav>
