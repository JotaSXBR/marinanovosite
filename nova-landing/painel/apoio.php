<?php
// Pilates em Casa — funções de apoio do painel.
//
// Só helpers PUROS (sem estado, sem I/O de arquivo, sem HTML): escape, CSRF,
// data e o cálculo do relatório. Separados do index.php para que ele cuida só
// de sessão, rotas e carregamento de dados (uma responsabilidade por arquivo).
//
// Este arquivo NÃO é acessível por URL: painel/.htaccess e o FilesMatch do
// .htaccess raiz negam acesso a *.php dentro de painel/ que não seja o index.

if (!defined('PAINEL_BOOT')) {
  http_response_code(403);
  exit('Negado.');
}

// Escape para HTML. Todo valor que vem do disco ou do usuário passa por aqui.
function e($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }

// ------------------------------------------------------------------- CSRF
function csrfToken(): string {
  if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
  return $_SESSION['csrf'];
}

function csrfValido(): bool {
  return !empty($_POST['csrf'])
    && hash_equals((string) ($_SESSION['csrf'] ?? ''), (string) $_POST['csrf']);
}

// ------------------------------------------------------------------ base
// Host atual, sanitizado — o painel funciona na raiz e em /nova/ sem config.
function baseUrl(): string {
  $h = isset($_SERVER['HTTP_HOST']) ? preg_replace('/[^A-Za-z0-9.-]/', '', (string) $_SERVER['HTTP_HOST']) : '';
  if ($h === '') $h = 'maricoachfit.com';
  return 'https://' . $h;
}

// ------------------------------------------------------------------ dados
// Lê um JSON do painel (links.json / ofertas.json). Arquivo ausente = vazio.
function lerJson(string $file): array {
  if (!is_file($file)) return array();
  $d = json_decode((string) @file_get_contents($file), true);
  return is_array($d) ? $d : array();
}

function lerLinks(string $file): array { return lerJson($file); }

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

// --------------------------------------------------------------- relatório
// Agrega as linhas do acessos.jsonl por canal.
function resumo(array $linhas): array {
  $r = array();
  foreach ($linhas as $l) {
    $c = $l['canal'] ?? 'direto';
    if (!isset($r[$c])) {
      $r[$c] = array('total' => 0, 'hoje' => 0, 'semana' => 0, 'uid' => array(), 'ultima' => '', 'oferta' => '');
    }
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

// Somas da linha "Todos os canais".
function totais(array $resumo): array {
  $hoje = 0; $sem = 0; $tot = 0; $uid = array();
  foreach ($resumo as $r) {
    $hoje += $r['hoje'];
    $sem  += $r['semana'];
    $tot  += $r['total'];
    foreach ($r['uid'] as $u => $_) $uid[$u] = 1;
  }
  return array('hoje' => $hoje, 'semana' => $sem, 'total' => $tot, 'uid' => count($uid));
}

// Carrega o log de visitas inteiro (o painel roda por sessão, não por request
// anônimo: o arquivo é pequeno e ler de uma vez simplifica o relatório).
function lerVisitas(string $arq): array {
  if (!is_file($arq)) return array();
  $linhas = array();
  foreach (file($arq, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $ln) {
    $d = json_decode($ln, true);
    if (is_array($d)) $linhas[] = $d;
  }
  return $linhas;
}

// ------------------------------------------------------------- antibrute
// Porque o throttle é em PHP e não no Apache: no cPanel compartilhado não dá
// para instalar mod_evasive (exige root/WHM), e o mod_ratelimit NÃO serve —
// a doc oficial dele avisa que limita bandwidth, não nº de requisições, e que
// cada resposta limitada ocupa um worker do servidor durante toda a
// transferência. Contar tentativas aqui é o caminho que sempre existe.
//
// Chave = HMAC do IP (LGPD: o IP nunca é gravado em texto claro, igual ao
// identificador de visitante em private/rastreio.php). O IP só entra no
// cálculo do HMAC.
function throttlingArquivo(): string {
  return dirname(__DIR__) . '/private/dados/tentativas.json';
}

function throttlingChave(): string {
  $sal = 'pe-throttle|' . date('Y-m-d');
  return substr(hash_hmac('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? ''), $sal), 0, 16);
}

// Janela deobservation: 1 hora. Depois de uma hora, tudo zera.
function throttlingLer(): array {
  $f = throttlingArquivo();
  if (!is_file($f)) return array();
  $d = json_decode((string) @file_get_contents($f), true);
  return is_array($d) ? $d : array();
}

function throttlingGravar(array $d): void {
  $f = throttlingArquivo();
  $dir = dirname($f);
  if (!is_dir($dir)) @mkdir($dir, 0755, true);
  $json = json_encode($d, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
  if ($json !== false) @file_put_contents($f, $json, LOCK_EX);
}

// Quanto tempo ainda falta de bloqueio (0 = liberado).
function throttlingRestante(): int {
  $chave = throttlingChave();
  $todos  = throttlingLer();
  if (!isset($todos[$chave])) return 0;
  $agora = time();
  $falhas = array_values(array_filter($todos[$chave], fn($t) => ($agora - (int) $t) < 3600));
  if (!$falhas) {
    unset($todos[$chave]);
    throttlingGravar($todos);
    return 0;
  }
  $n = count($falhas);
  $limite = throttlingLimiteSegundos($n);
  if ($limite === 0) return 0;
  $ultima = max($falhas);
  $resta  = ($ultima + $limite) - $agora;
  return $resta > 0 ? $resta : 0;
}

// Backoff progressivo: quanto mais tentativa, maior a espera.
function throttlingLimiteSegundos(int $falhas): int {
  if ($falhas < 5) return 0;          // só registra, ainda não bloqueia
  if ($falhas <= 6) return 60;         // 1 min
  if ($falhas <= 9) return 300;        // 5 min
  return 1800;                         // 30 min
}

function throttlingFalhou(): void {
  $chave = throttlingChave();
  $todos = throttlingLer();
  $agora = time();
  $falhas = isset($todos[$chave]) ? $todos[$chave] : array();
  $falhas = array_values(array_filter($falhas, fn($t) => ($agora - (int) $t) < 3600));
  $falhas[] = $agora;
  $todos[$chave] = $falhas;
  // poda: limpa IPs que não falham há mais de 1 dia (o arquivo não cresce sem parar)
  foreach ($todos as $k => $ts) {
    if (!$ts || ($agora - (int) max($ts)) > 86400) unset($todos[$k]);
  }
  throttlingGravar($todos);
}

function throttlingLimpar(): void {
  $todos = throttlingLer();
  unset($todos[throttlingChave()]);
  throttlingGravar($todos);
}

function throttlingTexto(int $seg): string {
  if ($seg >= 1800) return '30 minutos';
  if ($seg >= 300) return '5 minutos';
  return '1 minuto';
}

// ------------------------------------------------------------------- CSV
function painelCsv(array $linhas): void {
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="visitas.csv"');
  $out = fopen('php://output', 'w');
  fwrite($out, "\xEF\xBB\xBF"); // BOM p/ o Excel abrir com acentos
  fputcsv($out, array('data_hora', 'canal', 'oferta', 'origem_externa', 'dispositivo'));
  foreach ($linhas as $l) {
    fputcsv($out, array($l['ts'] ?? '', $l['canal'] ?? '', $l['o'] ?? '', $l['ref'] ?? '', $l['movel'] ?? ''));
  }
  exit;
}