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
$ofertas = require $raiz . '/ofertas.php';
$config  = require __DIR__ . '/config.php';

$logDir = $raiz . '/dados';
$logArq = $logDir . '/acessos.jsonl';

// ---------------------------------------------------------------- sessão
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
    $canal = substr(preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) ($_POST['canal'] ?? ''))), 0, 40);
    $rotulo = substr(trim((string) ($_POST['rotulo'] ?? '')), 0, 60);
    $oferta = (string) ($_POST['oferta'] ?? 'padrao');
    if (!isset($ofertas[$oferta])) $oferta = 'padrao';

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
      @file_put_contents($linksFile, json_encode($links, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
      $_SESSION['painel_flash'] = 'Link salvo: ' . $canal;
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

  if ($acao === 'limpar_log') {
    @file_put_contents($logDir . '/acessos.jsonl', '');
    $_SESSION['painel_flash'] = 'Histórico de visitas apagado.';
  }

  header('Location: ./' . (isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : ''));
  exit;
}

if (isset($_GET['sair'])) { $_SESSION = []; session_destroy(); header('Location: ./'); exit; }

// -------------------------------------------------------------- relatórios
function lerLinks(string $file): array {
  if (!is_file($file)) return [];
  $d = json_decode((string) @file_get_contents($file), true);
  return is_array($d) ? $d : [];
}

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
th{font-size:.78rem;text-transform:uppercase;letter-spacing:.06em;color:var(--cinza)}
td.num{text-align:right;font-variant-numeric:tabular-nums}
.linkbox{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:.82rem;background:var(--faixa);border:1px solid var(--borda);border-radius:9px;padding:11px;word-break:break-all}
.flash{background:#e8f7ee;border:1px solid #b7e3c8;color:var(--sucesso);border-radius:10px;padding:11px 14px;margin-bottom:16px;font-size:.92rem}
.erro{background:#fdecec;border:1px solid #f2c2c2;color:#a11;border-radius:10px;padding:11px 14px;margin-bottom:16px;font-size:.92rem}
.muted{color:var(--cinza);font-size:.82rem}
.pill{display:inline-block;background:var(--faixa);border:1px solid var(--borda);border-radius:999px;padding:2px 11px;font-size:.78rem;font-weight:600}
.login{max-width:400px;margin:8vh auto}
.bar{display:flex;justify-content:space-between;align-items:center;gap:14px;flex-wrap:wrap}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:14px}
@media(max-width:640px){.grid2{grid-template-columns:1fr}.wrap{padding:20px 14px 50px}}
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
      <thead><tr><th>Canal</th><th class="num">Hoje</th><th class="num">7 dias</th><th class="num">Total</th><th class="num">Pessoas hoje</th><th>Última visita</th></tr></thead>
      <tbody>
      <?php foreach ($resumo as $canal => $r): ?>
        <tr>
          <td><b><?= e($canal) ?></b></td>
          <td class="num"><?= (int) $r['hoje'] ?></td>
          <td class="num"><?= (int) $r['semana'] ?></td>
          <td class="num"><?= (int) $r['total'] ?></td>
          <td class="num"><?= count($r['uid']) ?></td>
          <td class="muted"><?= e(substr((string) $r['ultima'], 0, 16)) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <p class="muted" style="margin-bottom:0">"Pessoas hoje" conta visitantes diferentes (identificador anônimo que gira todo dia, sem guardar IP).</p>
  </div>
  <?php endif; ?>

  <h2>Criar link por canal</h2>
  <div class="card">
    <form method="post">
      <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
      <input type="hidden" name="acao" value="salvar_link">
      <div class="grid2">
        <div>
          <label for="canal">Código do canal</label>
          <input id="canal" name="canal" placeholder="ig-bio" pattern="[a-z0-9_-]+" required>
          <p class="muted" style="margin:5px 0 0">Só letras minúsculas, números, <b>-</b> e <b>_</b>. Ex.: <code>whatsapp</code>, <code>ig-bio</code>, <code>tt-bio</code>, <code>kwai-bio</code>, <code>fb-ads</code>.</p>
        </div>
        <div>
          <label for="rotulo">Nome para você reconhecer</label>
          <input id="rotulo" name="rotulo" placeholder="Bio do Instagram" maxlength="60">
          <label for="oferta">Oferta do link</label>
          <select id="oferta" name="oferta">
            <?php foreach ($ofertas as $k => $of): ?>
              <option value="<?= e($k) ?>"><?= e($of['rotulo']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div style="margin-top:18px"><button type="submit">Salvar link</button></div>
    </form>
  </div>

  <?php if ($links): ?>
  <h2>Links prontos para copiar</h2>
  <?php foreach ($links as $canal => $d): ?>
    <?php $url = $base . '/?src=' . rawurlencode($canal); ?>
    <div class="card">
      <div class="bar">
        <div><b><?= e($d['rotulo'] !== '' ? $d['rotulo'] : $canal) ?></b> <span class="pill"><?= e($canal) ?></span></div>
        <form method="post" onsubmit="return confirm('Remover o link <?= e($canal) ?>?');">
          <input type="hidden" name="csrf" value="<?= e(csrfToken()) ?>">
          <input type="hidden" name="acao" value="apagar_link">
          <input type="hidden" name="canal" value="<?= e($canal) ?>">
          <button class="perigo" type="submit">Remover</button>
        </form>
      </div>
      <p class="linkbox" style="margin:12px 0 0"><?= e($url) ?></p>
      <p class="muted" style="margin:8px 0 0">A oferta <b><?= e($ofertas[$d['oferta'] ?? 'padrao']['rotulo'] ?? 'Padrão') ?></b> aparece para quem clicar. Para confirmar a venda: Kiwify → Vendas → Filtros → Parâmetros da URL → <b>src</b> = <?= e($canal) ?>.</p>
    </div>
  <?php endforeach; ?>
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
</body>
</html>