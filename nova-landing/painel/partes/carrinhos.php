<?php
// PARTES DO PAINEL — não são acessíveis por URL (partes/.htaccess nega tudo);
// o index.php carrega por require. São só templates: recebem dados por variável.
// Para criar uma nova parte: copie um bloco daqui e chame com require em index.php.
?>
  <h2 id="sec-carrinhos">Carrinhos (Kiwify)</h2>
  <div class="card scroll">
    <table>
      <thead>
        <tr>
          <th>Código</th><th>Nome</th><th class="num">De</th><th class="num">Por</th>
          <th class="num">Total</th><th>Parcelas</th><th>Origem</th><th>Ações</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($ofertas as $k => $of): ?>
        <?php $editado = carrinhoFoiEditadoNoPainel($k); ?>
        <tr>
          <td data-label="Código"><b><?= e($k) ?></b><?= $k === carrinhosChavePadrao() ? ' <span class="pill pill-bloco" title="Carrinho padrão: é o que o site mostra para quem chega sem link de canal. Por isso não pode ser apagado.">padrão</span>' : '' ?></td>
          <td data-label="Nome">
            <span class="nome-linha" title="<?= e($of['rotulo'] ?? '') ?>"><?= e($of['rotulo'] ?? '') ?></span>
            <?php if (($of['checkout'] ?? '') !== ''): ?>
              <span class="checkout">
                <span class="muted linkbox" title="<?= e($of['checkout']) ?>"><?= e($of['checkout']) ?></span>
                <button class="copiar-url" type="button"
                        onclick="copiar(this, '<?= e(addslashes($of['checkout'])) ?>')"
                        aria-label="Copiar o endereço do checkout de <?= e($k) ?>">copiar</button>
              </span>
            <?php endif; ?>
          </td>
          <td class="num" data-label="De"><?= isset($of['cheio']) ? 'R$ ' . (int) $of['cheio'] : '—' ?></td>
          <td class="num" data-label="Por"><b><?= isset($of['preco']) ? 'R$ ' . (int) $of['preco'] : '—' ?></b></td>
          <td class="num" data-label="Total"><?= isset($of['total']) ? 'R$ ' . (int) $of['total'] : '—' ?></td>
          <td class="nowrap" data-label="Parcelas"><?= isset($of['parcN']) ? (int) $of['parcN'] . 'x de R$' . e($of['parcV'] ?? '') : '—' ?></td>
          <td data-label="Origem"><?= $editado ? '<span class="pill">editado no painel</span>' : '<span class="muted">do código</span>' ?></td>
          <td data-label="Ações">
            <span class="acoes-linha">
              <a class="btn sec" href="?editar_carrinho=<?= e(rawurlencode($k)) ?>#carrinho">Editar</a>
              <?php if ($editado): ?>
                <details class="confirm">
                  <summary>Restaurar</summary>
                  <div class="confirm-corpo">
                    <p>Voltar <b><?= e($k) ?></b> ao valor original do código?</p>
                    <form method="post">
                      <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
                      <input type="hidden" name="acao" value="restaurar_carrinho">
                      <input type="hidden" name="chave" value="<?= e($k) ?>">
                      <span class="acoes">
                        <button class="perigo" type="submit">Sim, restaurar</button>
                      </span>
                    </form>
                  </div>
                </details>
              <?php endif; ?>
              <?php /* A linha do carrinho padrão não recebe botão "Apagar" e não recebe
        texto nenhum no lugar. A nota "não pode apagar" encostava no botão
        Editar e a célula se lia como um rótulo só — "Editar / não pode
        apagar". A informação vive no title da etiqueta "padrão" e na nota
        abaixo da tabela, que é onde alguém realmente lê. */ ?>
              <?php if ($k !== carrinhosChavePadrao()): ?>
                <details class="confirm">
                  <summary>Apagar</summary>
                  <div class="confirm-corpo">
                    <p>Apagar <b><?= e($k) ?></b>? Links que apontam para ele passam a mostrar o padrão. Dá para criar de novo depois.</p>
                    <form method="post">
                      <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
                      <input type="hidden" name="acao" value="apagar_carrinho">
                      <input type="hidden" name="chave" value="<?= e($k) ?>">
                      <span class="acoes">
                        <button class="perigo" type="submit">Sim, apagar</button>
                      </span>
                    </form>
                  </div>
                </details>
              <?php endif; ?>
            </span>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <p class="muted" style="margin-bottom:0">
      O que a Kiwify <b>cobra</b> é sempre o do checkout (1 checkout por preço).
      Estes valores controlam só o texto que aparece na landing — se divergirem
      da Kiwify, a pessoa vê um preço e paga outro.
      O carrinho marcado <b>padrão</b> é o que o site mostra para quem chega sem link de canal, e é o único que <b>não pode ser apagado</b> — troque os valores dele em "Editar".
    </p>
  </div>

  <div class="card" id="carrinho">
    <b><?= $editCarrinho ? 'Editar carrinho: ' . e($editCarrinho) : 'Cadastrar novo carrinho' ?></b>
    <p class="muted" style="margin:6px 0 0">
      Crie o checkout na Kiwify primeiro (um por preço), depois cadastre aqui.
    </p>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
      <input type="hidden" name="acao" value="salvar_carrinho">
      <input type="hidden" name="chave_original" value="<?= e($editCarrinho) ?>">
      <div class="grid2">
        <div>
          <label for="chave">Código do carrinho</label>
          <input id="chave" name="chave" placeholder="promo-wpp…" pattern="[a-z0-9_-]+" maxlength="24"
                 autocomplete="off" autocapitalize="none" spellcheck="false"
                 value="<?= e($formCarrinho['chave'] ?? '') ?>" required>
          <p class="ajuda">Aparece na URL: <code>?o=promo-wpp</code>. Só minúsculas, números, <b>-</b> e <b>_</b>.</p>
        </div>
        <div>
          <label for="rotulo_c">Nome</label>
          <input id="rotulo_c" name="rotulo" placeholder="WhatsApp (R$ 97 por R$ 47)…" maxlength="60"
                 value="<?= e($formCarrinho['rotulo'] ?? '') ?>" required>
        </div>
      </div>
      <label for="checkout">Checkout (URL da Kiwify)</label>
      <input id="checkout" name="checkout" type="url" inputmode="url" required
             placeholder="https://pay.kiwify.com.br/SEU_CODIGO" translate="no"
             value="<?= e($formCarrinho['checkout'] ?? '') ?>">
      <p class="ajuda">O que a pessoa paga é o valor configurado <b>na Kiwify</b>. Aqui é só o texto que aparece na página.</p>
      <div class="grid4">
        <div>
          <label for="cheio">De (R$)</label>
          <input id="cheio" name="cheio" inputmode="numeric" value="<?= e($formCarrinho['cheio'] ?? '') ?>" required>
        </div>
        <div>
          <label for="preco">Por (R$)</label>
          <input id="preco" name="preco" inputmode="numeric" value="<?= e($formCarrinho['preco'] ?? '') ?>" required>
        </div>
        <div>
          <label for="total">Total (R$)</label>
          <input id="total" name="total" inputmode="numeric" value="<?= e($formCarrinho['total'] ?? '') ?>">
        </div>
        <div>
          <label for="parcN">Parcelas</label>
          <input id="parcN" name="parcN" inputmode="numeric" value="<?= e($formCarrinho['parcN'] ?? '') ?>">
        </div>
      </div>
      <label for="parcV">Valor da parcela</label>
      <input id="parcV" name="parcV" placeholder="10,69" value="<?= e($formCarrinho['parcV'] ?? '') ?>">
      <div class="bar" style="margin-top:18px">
        <button type="submit"><?= $editCarrinho ? 'Salvar alterações' : 'Cadastrar carrinho' ?></button>
        <?php if ($editCarrinho): ?>
          <a class="btn sec" href="./">Cancelar</a>
        <?php endif; ?>
      </div>
    </form>
  </div>
