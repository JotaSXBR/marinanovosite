<?php
// PARTES DO PAINEL — não são acessíveis por URL (partes/.htaccess nega tudo);
// o index.php carrega por require. São só templates: recebem dados por variável.
// Para criar uma nova parte: copie um bloco daqui e chame com require em index.php.
?>
  <h2 id="sec-cliques">Cliques por canal</h2>
  <?php if (!$resumo): ?>
    <div class="vazio">
      <b>Nenhum clique registrado ainda</b>
      <p>Crie um link de canal abaixo e compartilhe na sua bio. As visitas aparecem aqui assim que alguém clicar.</p>
    </div>
  <?php else: ?>
  <div class="card scroll">
    <table>
      <thead>
        <tr>
          <th>Canal</th>
          <th>Carrinho que mostra</th>
          <th class="num">Hoje</th>
          <th class="num">7 dias</th>
          <th class="num">Total</th>
          <th class="num">Pessoas hoje</th>
          <th>Última visita</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($resumo as $canal => $r):
        $lk = $links[$canal] ?? null;
        $carr = $lk ? ($ofertas[$lk['oferta']]['rotulo'] ?? $lk['oferta']) : null;
        $off = ($lk && isset($ofertas[$lk['oferta']])) ? (int) $ofertas[$lk['oferta']]['preco'] : null;
      ?>
        <tr>
          <td data-label="Canal"><b><?= e($canal) ?></b><?= $lk && ($lk['rotulo'] ?? '') !== '' ? '<br><span class="muted" style="font-size:.82rem">' . e($lk['rotulo']) . '</span>' : '' ?></td>
          <td data-label="Carrinho">
            <?php if ($carr !== null): ?>
              <?= e($carr) ?><?= $off !== null ? ' <span class="pill">R$ ' . $off . '</span>' : '' ?>
            <?php else: ?>
              <span class="muted">sem link criado</span>
            <?php endif; ?>
          </td>
          <td class="num tira" data-label="Hoje"><?= (int) $r['hoje'] ?></td>
          <td class="num tira" data-label="7 dias"><?= (int) $r['semana'] ?></td>
          <td class="num tira" data-label="Total"><?= (int) $r['total'] ?></td>
          <td class="num tira" data-label="Pessoas hoje"><?= count($r['uid']) ?></td>
          <td class="muted nowrap" data-label="Última visita"><?= e(brDataHora((string) $r['ultima'])) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
      <tfoot>
        <?php $tt = totais($resumo); ?>
        <tr class="totais">
          <td>Todos os canais</td>
          <td></td>
          <td class="num tira" data-label="Hoje"><?= (int) $tt['hoje'] ?></td>
          <td class="num tira" data-label="7 dias"><?= (int) $tt['semana'] ?></td>
          <td class="num tira" data-label="Total"><?= (int) $tt['total'] ?></td>
          <td class="num tira" data-label="Pessoas hoje"><?= (int) $tt['uid'] ?></td>
          <td></td>
        </tr>
      </tfoot>
    </table>
    <p class="muted" style="margin-bottom:0">
      <b>Pessoas hoje</b> conta visitantes diferentes (identificador anônimo que gira todo dia, sem guardar IP).
      Quem chega sem <b>?src=</b> entra como <b>direto</b>.
      As <b>vendas</b> não aparecem aqui — se confirmam na Kiwify pelo mesmo código de canal.
    </p>
  </div>
  <?php endif; ?>
