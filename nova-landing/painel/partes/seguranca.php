<?php
// PARTES DO PAINEL — não são acessíveis por URL (partes/.htaccess nega tudo);
// o index.php carrega por require. São só templates: recebem dados por variável.
// Para criar uma nova parte: copie um bloco daqui e chame com require em index.php.
//
// Última seção do painel: o que o antibrute registrou (tentativas erradas de
// entrar e bloqueios) e as limpezas. São DOIS botões separados de propósito —
// apagar as visitas não pode levar junto o registro de segurança, e vice-versa.
?>
  <h2 id="sec-login">Tentativas de login</h2>
  <?php if (!$tentativas): ?>
    <div class="vazio">
      <b>Nenhuma tentativa errada registrada</b>
      <p>É assim que deve ficar. Se alguém errar a senha deste painel, aparece uma linha aqui — e, a partir de 5 erros em menos de 1 hora, o acesso fica bloqueado por alguns minutos.</p>
    </div>
  <?php else: ?>
  <div class="card scroll">
    <?php $tt = tentativasTotais($tentativas); ?>
    <p style="margin:0 0 12px">
      <b>Tentativas erradas registradas: <?= (int) $tt['total'] ?></b>
      <?php if ($tt['bloqueados'] > 0): ?><span class="pill perigo"><?= (int) $tt['bloqueados'] ?> em bloqueio agora</span><?php endif; ?>
    </p>
    <table>
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
        <tr>
          <td data-label="Identificador"><span class="pill" title="Identificador anônimo de quem errou a senha — o IP não fica guardado"><?= e(substr($t['chave'], 0, 8)) ?></span></td>
          <td class="num" data-label="Tentativas"><?= (int) $t['total'] ?></td>
          <td class="muted nowrap" data-label="Última tentativa"><?= e(brDataHoraDe((int) $t['ultima'])) ?></td>
          <td data-label="Situação"><?= $t['libera'] > 0
            ? '<span class="pill perigo">Bloqueado até ' . e(brDataHoraDe((int) $t['libera'], 'H:i')) . '</span>'
            : '<span class="muted">sem bloqueio</span>' ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <p class="muted" style="margin-bottom:0">
      Cada linha é um identificador anônimo de quem errou a senha (o IP não fica guardado, igual às visitas do site, e o identificador gira todo dia).
      A partir de 5 erros em menos de 1 hora o acesso é bloqueado: 1 minuto; com 7, 5 minutos; com 10, 30 minutos.
      O contador zera 1 hora depois da última tentativa, e entrar com a senha certa limpa o registro de quem acertou.
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
        <p>Apagar o registro de tentativas erradas de login? As visitas e os links ficam como estão. O contador de bloqueio também zera — se houver um bloqueio ativo, ele acaba na hora.</p>
        <form method="post">
          <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
          <input type="hidden" name="acao" value="limpar_tentativas">
          <span class="acoes"><button class="perigo" type="submit">Sim, apagar as tentativas</button></span>
        </form>
      </div>
    </details>
  </div>
