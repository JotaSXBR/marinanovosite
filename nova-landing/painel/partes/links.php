<?php
// PARTES DO PAINEL — não são acessíveis por URL (partes/.htaccess nega tudo);
// o index.php carrega por require. São só templates: recebem dados por variável.
// Para criar uma nova parte: copie um bloco daqui e chame com require em index.php.
?>
  <h2 id="sec-links">Links por canal</h2>
  <div class="card">
    <b>Criar links prontos</b>
    <p class="muted" style="margin:6px 0 14px">Escolha o carrinho e clique no canal — o link já nasce pronto para colar.</p>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
      <input type="hidden" name="acao" value="salvar_link">
      <label for="oferta_q">Carrinho que estes links vão mostrar</label>
      <select id="oferta_q" name="oferta">
        <?php foreach ($ofertas as $k => $of): ?>
          <option value="<?= e($k) ?>"><?= e($of['rotulo'] ?? $k) ?> — R$ <?= (int) ($of['preco'] ?? 0) ?></option>
        <?php endforeach; ?>
      </select>
      <div class="chips">
        <?php foreach (carrinhosCanaisSugeridos() as $codigo => $nome): ?>
          <button class="sec" type="submit" name="canal" value="<?= e($codigo) ?>">+ <?= e($nome) ?></button>
        <?php endforeach; ?>
      </div>
    </form>
  </div>

  <div class="card" id="links">
    <b><?= $editLink ? 'Editando o link: ' . e($editLink) : 'Link personalizado' ?></b>
    <p class="muted" style="margin:6px 0 0">
      <?= $editLink
        ? 'Salve para atualizar. O código do canal não muda (é o que já está nas redes).'
        : 'Para um canal fora da lista (ex.: <code>ig-stories</code>, <code>parceiro-joao</code>).' ?>
    </p>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
      <input type="hidden" name="acao" value="salvar_link">
      <div class="grid2">
        <div>
          <label for="canal">Código do canal</label>
          <input id="canal" name="canal" placeholder="ig-bio…" pattern="[a-z0-9_-]+" maxlength="40" required
                 autocomplete="off" autocapitalize="none" spellcheck="false"
                 value="<?= e($formLink['canal'] ?? '') ?>" <?= $editLink ? 'readonly' : '' ?>>
          <p class="ajuda">Só letras minúsculas, números, <b>-</b> e <b>_</b>.</p>
        </div>
        <div>
          <label for="rotulo">Nome para você reconhecer</label>
          <input id="rotulo" name="rotulo" placeholder="Bio do Instagram…" maxlength="60"
                 value="<?= e($formLink['rotulo'] ?? '') ?>">
          <label for="oferta">Carrinho</label>
          <select id="oferta" name="oferta">
            <?php foreach ($ofertas as $k => $of): ?>
              <option value="<?= e($k) ?>" <?= (($formLink['oferta'] ?? '') === $k) ? 'selected' : '' ?>><?= e($of['rotulo'] ?? $k) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="bar" style="margin-top:18px">
        <button type="submit">Salvar link</button>
        <?php if ($editLink): ?><a class="btn sec" href="./">Cancelar</a><?php endif; ?>
      </div>
    </form>
  </div>

  <h2>Links prontos para copiar</h2>
  <?php if (!$links): ?>
    <div class="vazio">
      <b>Nenhum link criado ainda</b>
      <p>Use os botões de canal acima para gerar o primeiro link em um clique.</p>
    </div>
  <?php else: ?>
  <div class="card scroll">
    <table>
      <thead>
        <tr><th>Canal</th><th>Nome</th><th>Carrinho</th><th>Link</th><th>Ações</th></tr>
      </thead>
      <tbody>
      <?php foreach ($links as $canal => $d):
        $chaveOferta = $d['oferta'] ?? carrinhosChavePadrao();
        $rotOferta   = $ofertas[$chaveOferta]['rotulo'] ?? $chaveOferta;
        $precoOferta = isset($ofertas[$chaveOferta]['preco']) ? 'R$ ' . (int) $ofertas[$chaveOferta]['preco'] : '';
        $url = $base . '/?src=' . rawurlencode($canal);
        if ($chaveOferta !== carrinhosChavePadrao() && isset($ofertas[$chaveOferta])) {
          $url .= '&o=' . rawurlencode($chaveOferta);
        }
      ?>
        <tr>
          <td data-label="Canal"><span class="pill"><?= e($canal) ?></span></td>
          <td data-label="Nome"><?= e($d['rotulo'] !== '' ? $d['rotulo'] : '—') ?></td>
          <td class="nowrap" data-label="Carrinho"><?= e($rotOferta) ?><?= $precoOferta !== '' ? ' <b>(' . e($precoOferta) . ')</b>' : '' ?></td>
          <td data-label="Link"><span class="linkbox" style="display:inline-block"><?= e($url) ?></span></td>
          <td class="sem-rotulo" data-label="">
            <span class="acoes-linha">
              <button class="sec" type="button" onclick="copiar(this, '<?= e(addslashes($url)) ?>')">Copiar</button>
              <a class="btn sec" href="?editar_link=<?= e(rawurlencode($canal)) ?>#links">Editar</a>
              <details class="confirm">
                <summary>Apagar</summary>
                <div class="confirm-corpo">
                  <p>Apagar o link <b><?= e($canal) ?></b>? O relatório de cliques dele é mantido.</p>
                  <form method="post">
                    <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
                    <input type="hidden" name="acao" value="apagar_link">
                    <input type="hidden" name="canal" value="<?= e($canal) ?>">
                    <span class="acoes">
                      <button class="perigo" type="submit">Sim, apagar</button>
                    </span>
                  </form>
                </div>
              </details>
            </span>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <p class="muted" style="margin-bottom:0">
      Link com <b>?o=</b> mostra um carrinho diferente do padrão.
      Para ver a venda: Kiwify → Vendas → Filtros → Parâmetros da URL → <b>src</b> = código do canal.
    </p>
  </div>
  <?php endif; ?>

  <div class="card" style="margin-top:34px">
    <b>Zona de cuidado</b>
    <p class="muted" style="margin:6px 0 14px">Apagar o histórico não afeta vendas já registradas na Kiwify.</p>
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
  </div>
