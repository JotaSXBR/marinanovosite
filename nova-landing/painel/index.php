<?php
// Pilates em Casa — painel de links e cliques (uso da proprietária).
//
// O que a proprietária faz aqui:
//   - cria um link por canal (WhatsApp, bio do Instagram, TikTok, Kwai,
//     anúncio pago...) e copia pra colar na bio/descrição;
//   - vê quantas pessoas entraram por cada canal, por dia;
//   - exporta o relatório em CSV pra abrir no Excel.
//
// Onde a venda é confirmada: no painel da Kiwify (Vendas → Filtros →
// Parâmetros da URL → src). O canal é o mesmo nome usado aqui, então dá
// pra cruzar clique e venda pelo mesmo código.
//
// Segurança: senha em hash (ver config.php), sessão httpOnly, token CSRF em
// toda alteração, e a pasta private/ é negada por URL no .htaccess.
define('OFERTAS_BOOT', true);
// private/ fica na RAIZ do site (não dentro de painel/) — mesmo caminho que
// o index.php usa. dirname(__DIR__) sobe de painel/ para a raiz.
$raiz    = dirname(__DIR__) . '/private';
require $raiz . '/carrinhos.php';
$config  = require __DIR__ . '/config.php';

$logDir = $raiz . '/dados';
$ofertas = carrinhosTodos();
$logArq = $logDir . '/acessos.jsonl';

// ---------------------------------------------------------------- sessão
// (lerLinks é usada no bloco POST abaixo; declarada aqui para não depender de
// hoisting.)
function lerLinks(string $file): array {
  if (!is_file($file)) return array();
  $d = json_decode((string) @file_get_contents($file), true);
  return is_array($d) ? $d : array();
}

// "2026-10-11T15:10:00-03:00" -> "11/10 15:10"
function brDataHora(string $iso): string {
  if ($iso === '') return '—';
  try {
    $d = new DateTime($iso);
    return $d->format('d/m H:i');
  } catch (Exception $e) {
    return substr($iso, 8, 2) . '/' . substr($iso, 5, 2) . ' ' . substr($iso, 11, 5);
  }
}
if (session_status() === PHP_SESSION_NONE) {
  session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
  ]);
  session_name('pepainel');
  session_start();
}

function e($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
function csrfToken(): string {
  if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
  return $_SESSION['csrf'];
}
function csrfValido(): bool {
  return !empty($_POST['csrf']) && hash_equals((string) ($_SESSION['csrf'] ?? ''), (string) $_POST['csrf']);
}
function baseUrl(): string {
  $h = isset($_SERVER['HTTP_HOST']) ? preg_replace('/[^A-Za-z0-9.-]/', '', (string) $_SERVER['HTTP_HOST']) : '';
  if ($h === '') $h = 'maricoachfit.com';
  return 'https://' . $h;
}

$logado = !empty($_SESSION['painel_ok']);

// ------------------------------------------------------------------- login
$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'entrar') {
  if (!csrfValido()) {
    $erro = 'Sessão expirada. Tente de novo.';
  } elseif (password_verify((string) ($_POST['senha'] ?? ''), $config['senha_hash'])) {
    session_regenerate_id(true);
    $_SESSION['painel_ok'] = true;
    $logado = true;
  } else {
    $erro = 'Senha incorreta.';
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $logado && csrfValido()) {
  $acao = (string) ($_POST['acao'] ?? '');

  // Links nomeados: canal -> link publicado. Só guarda referência; o
  // checkout/preço continuam vindo de private/ofertas.php.
  $linksFile = $logDir . '/links.json';

  if ($acao === 'salvar_link') {
    $canal  = substr(preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) ($_POST['canal'] ?? ''))), 0, 40);
    $rotulo = substr(trim((string) ($_POST['rotulo'] ?? '')), 0, 60);
    $oferta = (string) ($_POST['oferta'] ?? carrinhosChavePadrao());

    // Link rápido: canal veio de um botão sugerido e o rotulo ficou vazio.
    if ($rotulo === '') {
      $sug = carrinhosCanaisSugeridos();
      if (isset($sug[$canal])) $rotulo = $sug[$canal];
    }
    if (!isset($ofertas[$oferta])) $oferta = carrinhosChavePadrao();
    if (!isset($ofertas[$oferta])) $oferta = (string) array_key_first($ofertas);

    if ($canal === '') {
      $erro = 'Informe um código de canal (ex.: ig-bio).';
    } else {
      if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
      $links = [];
      if (is_file($linksFile)) {
        $lidos = json_decode((string) @file_get_contents($linksFile), true);
        if (is_array($lidos)) $links = $lidos;
      }
      $links[$canal] = ['rotulo' => $rotulo, 'oferta' => $oferta];
      ksort($links);
      if (@file_put_contents($linksFile, json_encode($links, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) === false) {
        $erro = 'Não consegui gravar o link (pasta private/dados sem escrita?).';
      } else {
        $_SESSION['painel_flash'] = 'Link salvo: ' . $canal;
      }
    }
  }

  if ($acao === 'apagar_link') {
    $canal = (string) ($_POST['canal'] ?? '');
    if (is_file($linksFile)) {
      $lidos = json_decode((string) @file_get_contents($linksFile), true);
      if (is_array($lidos)) {
        unset($lidos[$canal]);
        @file_put_contents($linksFile, json_encode($lidos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
      }
    }
    $_SESSION['painel_flash'] = 'Link removido.';
  }

  // ------------------------------------------------------------- carrinhos
  if ($acao === 'salvar_carrinho') {
    list($d, $errosCarrinho) = carrinhoValidar($_POST);
    if ($errosCarrinho) {
      $erro = implode(' ', $errosCarrinho);
      $_SESSION['painel_form'] = $_POST;
    } else {
      $original = trim((string) ($_POST['chave_original'] ?? ''));
      // Renomear: apaga o registro antigo para nao sobrar duplicata.
      if ($original !== '' && $original !== $d['chave']) {
        carrinhoApagar($original);
        foreach (lerLinks($logDir . '/links.json') as $c => $l) {
          if (($l['oferta'] ?? '') === $original) {
            $l['oferta'] = $d['chave'];
            $_SESSION['painel_flash'] = 'Carrinho renomeado. Revise os links que apontam para ele.';
            break;
          }
        }
      }
      list($ok, $errosGrava) = carrinhoGravar($d);
      if ($ok) {
        $_SESSION['painel_flash'] = 'Carrinho salvo: ' . $d['chave'];
      } else {
        $erro = implode(' ', $errosGrava);
        $_SESSION['painel_form'] = $_POST;
      }
    }
  }

  if ($acao === 'apagar_carrinho') {
    list($ok, $errosApaga) = carrinhoApagar((string) ($_POST['chave'] ?? ''));
    if ($ok) {
      $_SESSION['painel_flash'] = 'Carrinho apagado.';
    } else {
      $erro = implode(' ', $errosApaga);
    }
  }

  if ($acao === 'restaurar_carrinho') {
    list($ok, $errosRest) = carrinhoRestaurar((string) ($_POST['chave'] ?? ''));
    if ($ok) {
      $_SESSION['painel_flash'] = 'Carrinho restaurado para o valor original do código.';
    } else {
      $erro = implode(' ', $errosRest);
    }
  }

  if ($acao === 'limpar_log') {
    @file_put_contents($logDir . '/acessos.jsonl', '');
    $_SESSION['painel_flash'] = 'Histórico de visitas apagado.';
  }

  header('Location: ./' . (isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : ''));
  exit;
}

if (isset($_GET['sair'])) { $_SESSION = []; session_destroy(); header('Location: ./'); exit; }

// -------------------------------------------------------------- relatórios

// Top-level dos canais: total, hoje, 7 dias, visitors únicos hoje.
function resumo(array $linhas): array {
  $r = [];
  foreach ($linhas as $l) {
    $c = $l['canal'] ?? 'direto';
    if (!isset($r[$c])) $r[$c] = ['total' => 0, 'hoje' => 0, 'semana' => 0, 'uid' => [], 'ultima' => '', 'oferta' => ''];
    $r[$c]['total']++;
    $r[$c]['oferta'] = $l['o'] ?? '';
    $ts = $l['ts'] ?? '';
    $r[$c]['ultima'] = $ts;
    $dia = substr($ts, 0, 10);
    if ($dia === date('Y-m-d')) {
      $r[$c]['hoje']++;
      $r[$c]['uid'][$l['uid'] ?? ''] = 1;
    }
    if ($dia >= date('Y-m-d', strtotime('-6 days'))) $r[$c]['semana']++;
  }
  uasort($r, fn($a, $b) => $b['total'] <=> $a['total']);
  return $r;
}

$linhas = [];
if (is_file($logArq)) {
  foreach (file($logArq, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $ln) {
    $d = json_decode($ln, true);
    if (is_array($d)) $linhas[] = $d;
  }
}
$resumo  = resumo($linhas);

// ------------------------------------------- preencher forms (editar)
$editCarrinho = isset($_GET['editar_carrinho']) ? (string) $_GET['editar_carrinho'] : '';
if ($editCarrinho !== '' && isset($ofertas[$editCarrinho])) {
  $c = $ofertas[$editCarrinho];
  $formCarrinho = array(
    'chave'    => $editCarrinho,
    'rotulo'   => $c['rotulo'] ?? '',
    'checkout' => $c['checkout'] ?? '',
    'cheio'    => $c['cheio'] ?? '',
    'preco'    => $c['preco'] ?? '',
    'total'    => $c['total'] ?? '',
    'parcN'    => $c['parcN'] ?? '',
    'parcV'    => $c['parcV'] ?? '',
  );
} else {
  $formCarrinho = array();
  $editCarrinho = '';
}
// se houve erro de validação, o que a dona digitado tem voltar na tela
$formCarrinho = array_merge($formCarrinho, (array) ($_SESSION['painel_form'] ?? array()));
unset($_SESSION['painel_form']);
$links   = lerLinks($logDir . '/links.json');
$base    = baseUrl();

// CSV pronto pra Excel.
if ($logado && isset($_GET['csv'])) {
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="visitas.csv"');
  $out = fopen('php://output', 'w');
  fwrite($out, "\xEF\xBB\xBF"); // BOM p/ o Excel abrir com acentos
  fputcsv($out, ['data_hora', 'canal', 'oferta', 'origem_externa', 'dispositivo']);
  foreach ($linhas as $l) {
    fputcsv($out, [$l['ts'] ?? '', $l['canal'] ?? '', $l['o'] ?? '', $l['ref'] ?? '', $l['movel'] ?? '']);
  }
  exit;
}

$flash = $_SESSION['painel_flash'] ?? '';
unset($_SESSION['painel_flash']);
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>Painel de Links | Pilates em Casa</title>
<style>
:root{--rosa:#FF51C9;--roxo:#3F0142;--cta:#A80077;--tinta:#2B1226;--cinza:#6B5565;--fundo:#FFF7FC;--branco:#fff;--borda:#F0D4E6;--faixa:#FBE9F5;--sucesso:#0a7d3e}
*{box-sizing:border-box}
body{margin:0;font-family:system-ui,"Segoe UI",Arial,sans-serif;background:var(--fundo);color:var(--tinta);line-height:1.6}
.wrap{max-width:980px;margin:0 auto;padding:28px 20px 60px}
h1{font-size:1.5rem;margin:0 0 4px}
h2{font-size:1.1rem;margin:34px 0 12px}
.sub{color:var(--cinza);font-size:.92rem;margin:0 0 22px}
.card{background:var(--branco);border:1px solid var(--borda);border-radius:14px;padding:22px;margin-bottom:18px}
label{display:block;font-size:.85rem;font-weight:600;margin:12px 0 4px}
input,select{width:100%;padding:11px 12px;border:1px solid var(--borda);border-radius:9px;font-size:.95rem;font-family:inherit;background:#fff;color:var(--tinta)}
input:focus,select:focus{outline:2px solid var(--cta);outline-offset:1px}
button,.btn{background:var(--cta);color:#fff;border:0;border-radius:999px;padding:12px 22px;font-size:.95rem;font-weight:700;cursor:pointer;font-family:inherit;text-decoration:none;display:inline-block}
button.sec{background:#fff;color:var(--cta);border:1px solid var(--borda)}
button.perigo{background:#fff;color:#a11;border:1px solid #f0c4c4}
table{width:100%;border-collapse:collapse;font-size:.92rem}
th,td{text-align:left;padding:11px 10px;border-bottom:1px solid var(--faixa)}
th{font-size:.78rem;text-transform:uppercase;letter-spacing:.06em;color:var(--cinza);white-space:nowrap}
/* alinhar: numero no th e no td vao para a direita, com a mesma largura de
   digito (tabular-nums) para as colunas ficarem retas */
th.num,td.num{text-align:right;font-variant-numeric:tabular-nums}
td.num,th.num{padding-left:20px}
.nowrap{white-space:nowrap}
tfoot .totais td{font-weight:700;background:var(--faixa);border-bottom:0;border-top:2px solid var(--borda)}
.scroll{overflow-x:auto;-webkit-overflow-scrolling:touch}
.linkbox{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:.82rem;background:var(--faixa);border:1px solid var(--borda);border-radius:9px;padding:11px;word-break:break-all}
.flash{background:#e8f7ee;border:1px solid #b7e3c8;color:var(--sucesso);border-radius:10px;padding:11px 14px;margin-bottom:16px;font-size:.92rem}
.erro{background:#fdecec;border:1px solid #f2c2c2;color:#a11;border-radius:10px;padding:11px 14px;margin-bottom:16px;font-size:.92rem}
.muted{color:var(--cinza);font-size:.82rem}
.pill{display:inline-block;background:var(--faixa);border:1px solid var(--borda);border-radius:999px;padding:2px 11px;font-size:.78rem;font-weight:600}
.login{max-width:400px;margin:8vh auto}
.bar{display:flex;justify-content:space-between;align-items:center;gap:14px;flex-wrap:wrap}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.grid4{display:grid;grid-template-columns:repeat(4,1fr);gap:14px}
.chips{display:flex;flex-wrap:wrap;gap:10px;margin-top:14px}
.chips button{font-weight:600;font-size:.9rem;padding:10px 16px}
form[style*="display:inline"]{display:inline}
@media(max-width:640px){.grid2,.grid4{grid-template-columns:1fr}.wrap{padding:20px 14px 50px}}
</style>
</head>
<body>
<div class="wrap">
<?php if (!$logado): ?>
  <div class="login">
    <div class="card">
      <h1>Painel de Links</h1>
      <p class="sub">Pilates em Casa — área restrita da proprietária.</p>
      <?php if ($erro): ?><div class="erro"><?= e($erro) ?></div><?php endif; ?>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
        <input type="hidden" name="acao" value="entrar">
        <label for="senha">Senha</label>
        <input id="senha" name="senha" type="password" autocomplete="current-password" required autofocus>
        <div style="margin-top:18px"><button type="submit" style="width:100%">Entrar</button></div>
      </form>
    </div>
  </div>
<?php else: ?>
  <div class="bar">
    <div>
      <h1>Painel de Links</h1>
      <p class="sub">Visitas por canal. As <b>vendas</b> se confirmam no painel da Kiwify usando o mesmo código de canal.</p>
    </div>
    <div style="display:flex;gap:10px">
      <a class="btn sec" href="?csv=1">Baixar CSV</a>
      <a class="btn sec" href="?sair=1">Sair</a>
    </div>
  </div>

  <?php if ($flash): ?><div class="flash"><?= e($flash) ?></div><?php endif; ?>
  <?php if ($erro): ?><div class="erro"><?= e($erro) ?></div><?php endif; ?>

  <h2>Cliques por canal</h2>
  <?php if (!$resumo): ?>
    <div class="card"><p class="muted" style="margin:0">Nenhuma visita registrada ainda. Assim que alguém entrar por um link com <b>?src=</b>, aparece aqui.</p></div>
  <?php else: ?>
  <div class="card">
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
          <td><b><?= e($canal) ?></b><?= $lk && ($lk['rotulo'] ?? '') !== '' ? '<br><span class="muted" style="font-size:.82rem">' . e($lk['rotulo']) . '</span>' : '' ?></td>
          <td>
            <?php if ($carr !== null): ?>
              <?= e($carr) ?><?= $off !== null ? ' <span class="pill">R$ ' . $off . '</span>' : '' ?>
            <?php else: ?>
              <span class="muted">— sem link criado</span>
            <?php endif; ?>
          </td>
          <td class="num"><?= (int) $r['hoje'] ?></td>
          <td class="num"><?= (int) $r['semana'] ?></td>
          <td class="num"><?= (int) $r['total'] ?></td>
          <td class="num"><?= count($r['uid']) ?></td>
          <td class="muted nowrap"><?= e(brDataHora((string) $r['ultima'])) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
      <tfoot>
        <?php
          $tHoje = 0; $tSem = 0; $tTot = 0; $tUid = [];
          foreach ($resumo as $r) { $tHoje += $r['hoje']; $tSem += $r['semana']; $tTot += $r['total']; foreach ($r['uid'] as $u => $_) $tUid[$u] = 1; }
        ?>
        <tr class="totais">
          <td>Todos os canais</td>
          <td></td>
          <td class="num"><?= (int) $tHoje ?></td>
          <td class="num"><?= (int) $tSem ?></td>
          <td class="num"><?= (int) $tTot ?></td>
          <td class="num"><?= count($tUid) ?></td>
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

  <h2>Carrinhos (Kiwify)</h2>
  <div class="card scroll">
    <table>
      <thead>
        <tr>
          <th>Código</th><th>Nome</th><th class="num">De</th><th class="num">Por</th>
          <th class="num">Total</th><th>Parcelas</th><th>Origem do dado</th><th>Ações</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($ofertas as $k => $of): ?>
        <?php $editado = carrinhoFoiEditadoNoPainel($k); ?>
        <tr>
          <td><b><?= e($k) ?></b><?= $k === carrinhosChavePadrao() ? ' <span class="pill">padrão</span>' : '' ?></td>
          <td><?= e($of['rotulo'] ?? '') ?><br><span class="muted linkbox" style="display:inline-block;margin-top:4px;padding:4px 8px"><?= e($of['checkout'] ?? '') ?></span></td>
          <td class="num"><?= isset($of['cheio']) ? 'R$ ' . (int) $of['cheio'] : '—' ?></td>
          <td class="num"><b><?= isset($of['preco']) ? 'R$ ' . (int) $of['preco'] : '—' ?></b></td>
          <td class="num"><?= isset($of['total']) ? 'R$ ' . (int) $of['total'] : '—' ?></td>
          <td class="nowrap"><?= isset($of['parcN']) ? (int) $of['parcN'] . 'x de R$' . e($of['parcV'] ?? '') : '—' ?></td>
          <td><?= $editado ? '<span class="pill">editado no painel</span>' : '<span class="muted">do código</span>' ?></td>
          <td class="nowrap">
            <a class="btn sec" href="?editar_carrinho=<?= e(rawurlencode($k)) ?>#carrinho">Editar</a>
            <?php if ($editado): ?>
              <form method="post" style="display:inline" onsubmit="return confirm('Voltar <?= e($k) ?> ao valor original do código?')">
                <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="acao" value="restaurar_carrinho">
                <input type="hidden" name="chave" value="<?= e($k) ?>">
                <button class="sec" type="submit">Restaurar</button>
              </form>
            <?php endif; ?>
            <?php if ($k !== carrinhosChavePadrao()): ?>
              <form method="post" style="display:inline" onsubmit="return confirm('Apagar o carrinho <?= e($k) ?>? Links que apontam para ele passam a mostrar o padrão.')">
                <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
                <input type="hidden" name="acao" value="apagar_carrinho">
                <input type="hidden" name="chave" value="<?= e($k) ?>">
                <button class="perigo" type="submit">Apagar</button>
              </form>
            <?php else: ?>
              <span class="muted" style="font-size:.8rem">não pode apagar</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <p class="muted" style="margin-bottom:0">
      O que a Kiwify <b>cobra</b> é sempre o do checkout (1 checkout por preço).
      Estes valores controlam só o texto que aparece na landing — se divergirem
      da Kiwify, a pessoa vê um preço e paga outro.
      O carrinho marcado <b>padrão</b> é o que o site mostra para quem chega sem link de canal.
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
          <input id="chave" name="chave" placeholder="promo-wpp" pattern="[a-z0-9_-]+" maxlength="24"
                 value="<?= e($formCarrinho['chave'] ?? '') ?>" required>
          <p class="muted" style="margin:5px 0 0">Aparece na URL: <code>?o=promo-wpp</code>. Só minúsculas, números, <b>-</b> e <b>_</b>.</p>
        </div>
        <div>
          <label for="rotulo_c">Nome</label>
          <input id="rotulo_c" name="rotulo" placeholder="WhatsApp (R$ 97 por R$ 47)" maxlength="60"
                 value="<?= e($formCarrinho['rotulo'] ?? '') ?>" required>
        </div>
      </div>
      <label for="checkout">Checkout (URL da Kiwify)</label>
      <input id="checkout" name="checkout" placeholder="https://pay.kiwify.com.br/SEU_CODIGO" required
             value="<?= e($formCarrinho['checkout'] ?? '') ?>">
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

  <h2>Links por canal</h2>
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

  <div class="card">
    <b>Link personalizado</b>
    <p class="muted" style="margin:6px 0 0">Para um canal fora da lista (ex.: <code>ig-stories</code>, <code>parceiro-joao</code>).</p>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
      <input type="hidden" name="acao" value="salvar_link">
      <div class="grid2">
        <div>
          <label for="canal">Código do canal</label>
          <input id="canal" name="canal" placeholder="ig-bio" pattern="[a-z0-9_-]+" maxlength="40" required>
        </div>
        <div>
          <label for="rotulo">Nome para você reconhecer</label>
          <input id="rotulo" name="rotulo" placeholder="Bio do Instagram" maxlength="60">
          <label for="oferta">Carrinho</label>
          <select id="oferta" name="oferta">
            <?php foreach ($ofertas as $k => $of): ?>
              <option value="<?= e($k) ?>"><?= e($of['rotulo'] ?? $k) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div style="margin-top:18px"><button type="submit">Salvar link</button></div>
    </form>
  </div>

  <?php if ($links): ?>
  <h2>Links prontos para copiar</h2>
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
          <td><span class="pill"><?= e($canal) ?></span></td>
          <td><?= e($d['rotulo'] !== '' ? $d['rotulo'] : '—') ?></td>
          <td class="nowrap"><?= e($rotOferta) ?><?= $precoOferta !== '' ? ' <b>(' . e($precoOferta) . ')</b>' : '' ?></td>
          <td><span class="linkbox" style="display:inline-block"><?= e($url) ?></span></td>
          <td class="nowrap">
            <button class="sec" type="button" onclick="copiar(this, '<?= e(addslashes($url)) ?>')">Copiar</button>
            <a class="btn sec" href="?editar_link=<?= e(rawurlencode($canal)) ?>#links">Editar</a>
            <form method="post" style="display:inline" onsubmit="return confirm('Remover o link <?= e($canal) ?>?')">
              <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
              <input type="hidden" name="acao" value="apagar_link">
              <input type="hidden" name="canal" value="<?= e($canal) ?>">
              <button class="perigo" type="submit">Apagar</button>
            </form>
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
    <form method="post" onsubmit="return confirm('Apagar todo o histórico de visitas? As vendas na Kiwify não são afetadas.');">
      <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
      <input type="hidden" name="acao" value="limpar_log">
      <button class="perigo" type="submit">Apagar histórico de visitas</button>
    </form>
  </div>
<?php endif; ?>
</div>
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
</body>
</html>