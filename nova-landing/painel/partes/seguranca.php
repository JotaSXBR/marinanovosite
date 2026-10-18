<?php
// PARTES DO PAINEL — não são acessíveis por URL (partes/.htaccess nega tudo);
// o index.php carrega por require. São só templates: recebem dados por variável.
// Para criar uma nova parte: copie um bloco daqui e chame com require em index.php.
//
// Última seção do painel: o que o antibrute registrou (tentativas erradas de
// entrar e bloqueios) e as limpezas. São DOIS botões separados de propósito —
// apagar as visitas não pode levar junto o registro de segurança, e vice-versa.
// O segundo botão é também o caminho de volta de quem ficou bloqueado: entre
// de outro aparelho/rede e limpe por aqui.
?>
  <h2 id="sec-login">Tentativas de login</h2>
  <?php if (!$tentativas): ?>
    <div class="vazio">
      <b>Nenhuma tentativa errada registrada</b>
      <p>É assim que deve ficar. Se alguém errar a senha deste painel, aparece uma linha aqui — e, a partir de 5 erros em menos de 1 hora, o acesso fica bloqueado por 24 horas.</p>
    </div>
  <?php else: ?>
  <div class="card scroll">
    <?php $tt = tentativasTotais($tentativas); ?>
    <p class="tentativas-topo">
      <b>Tentativas erradas registradas: <?= (int) $tt['total'] ?></b>
      <?php if ($tt['bloqueados'] > 0): ?><span class="pill perigo"><?= (int) $tt['bloqueados'] ?> em bloqueio agora</span><?php endif; ?>
      <?php if ($tt['permanentes'] > 0): ?><span class="pill perigo"><?= (int) $tt['permanentes'] ?> permanente<?= $tt['permanentes'] > 1 ? 's' : '' ?></span><?php endif; ?>
    </p>
    <table id="tabela-tentativas">
      <thead>
        <tr>
          <th>Identificador</th>
          <th class="num">Tentativas</th>
          <th>Última tentativa</th>
          <th>Situação</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($tentativas as $t): ?>
        <?php $permanente = $t['estado'] === 'permanente'; ?>
        <tr>
          <td data-label="Identificador"><span class="pill" title="Identificador anônimo de quem errou a senha — o IP não fica guardado"><?= e(substr($t['chave'], 0, 8)) ?></span></td>
          <td class="num" data-label="Tentativas"><?= (int) $t['total'] ?></td>
          <td class="muted nowrap" data-label="Última tentativa"><?= e(brDataHoraDe((int) $t['ultima'])) ?></td>
          <td data-label="Situação"><?php
            if ($permanente) {
              echo '<span class="pill perigo" title="Insistiu durante o bloqueio ou foi bloqueado mais de uma vez — só sai com \'Apagar tentativas de login\', abaixo">Bloqueado permanentemente</span>';
            } elseif ($t['estado'] === 'temporario') {
              echo '<span class="pill perigo">Bloqueado até ' . e(brDataHoraDe((int) $t['libera'])) . '</span>';
            } else {
              echo '<span class="muted">sem bloqueio</span>';
            }
          ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <p class="muted" style="margin-bottom:0">
      Cada linha é um identificador anônimo de origem — o IP não fica guardado em texto claro, e o identificador é o mesmo todos os dias (senão o bloqueio de 24 horas evaporaria à meia-noite).
      A partir de 5 erros em menos de 1 hora o acesso é bloqueado por 24 horas. Insistir durante o bloqueio — ou ser bloqueado pela segunda vez — torna o bloqueio permanente, e aí só o botão "Apagar tentativas de login" libera.
      O painel aceita um acesso por vez: enquanto um aparelho está logado, o login em outro falha; o acesso sai no "Sair" ou sozinho depois de 30 minutos sem uso.
      Entrar com a senha certa limpa o registro de quem acertou.
    </p>
  </div>
  <?php endif; ?>

  <h2 id="sec-cuidado">Zona de cuidado</h2>
  <div class="card">
    <p class="muted" style="margin:0 0 14px">Cada botão apaga uma coisa só — e nenhum deles mexe nas vendas já registradas na Kiwify.</p>
    <details class="confirm">
      <summary>Apagar histórico de visitas</summary>
      <div class="confirm-corpo">
        <p>Apagar todas as visitas registradas? As vendas já confirmadas na Kiwify não são afetadas, mas os números deste painel voltam a zero.</p>
        <form method="post">
          <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
          <input type="hidden" name="acao" value="limpar_log">
          <span class="acoes"><button class="perigo" type="submit">Sim, apagar o histórico</button></span>
        </form>
      </div>
    </details>
    <details class="confirm" style="margin-top:12px">
      <summary>Apagar tentativas de login</summary>
      <div class="confirm-corpo">
        <p>Apagar o registro de tentativas erradas de login? As visitas e os links ficam como estão. Os bloqueios também são apagados — inclusive os permanentes. É por aqui que se libera um aparelho bloqueado.</p>
        <form method="post">
          <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
          <input type="hidden" name="acao" value="limpar_tentativas">
          <span class="acoes"><button class="perigo" type="submit">Sim, apagar as tentativas</button></span>
        </form>
      </div>
    </details>
  </div>
