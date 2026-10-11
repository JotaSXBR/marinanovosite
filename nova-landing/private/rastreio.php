<?php
// Pilates em Casa — registro de visita por canal.
//
// Entra no log (1 linha por visita) o canal de origem (src/UTM), a oferta
// mostrada, hora, origem externa e um identificador ANÔNIMO que só vale
// para o dia (contar visitantes únicos sem guardar IP).
//
// LGPD: NÃO gravamos IP em claro. O identificador é HMAC do IP+user-agent
// com sal do dia — gira todo dia, então não serve para rastrear a pessoa
// no tempo, só para "pessoas diferentes hoje" no relatório. Guardar IP
// aberto + origem exige aviso na política de privacidade.

if (!defined('OFERTAS_BOOT')) {
  http_response_code(403);
  exit('Negado.');
}

// Parâmetros repassados ao checkout da Kiwify (aceita src, utm_*).
// Whitelist: só estes entram no log e no link de checkout.
function rastreioParametros(): array {
  $ok = ['src', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];
  $out = [];
  foreach ($ok as $k) {
    if (!isset($_GET[$k])) continue;
    $v = preg_replace('/[^A-Za-z0-9_\.\-\+]/', '', (string) $_GET[$k]);
    $v = substr($v, 0, 60);
    if ($v !== '') $out[$k] = $v;
  }
  return $out;
}

// Grappa os parâmetros na URL do checkout (1 checkout por preço na Kiwify).
function rastreioComParametros(string $checkout, array $params): string {
  if (!$params) return $checkout;
  return $checkout . (strpos($checkout, '?') === false ? '?' : '&') . http_build_query($params);
}

function rastreioSalvar(string $chaveOferta, array $params): void {
  $dir = __DIR__ . '/dados';
  if (!is_dir($dir)) @mkdir($dir, 0755, true);
  if (!is_dir($dir)) return; // sem escrita = site continua no ar, só não conta

  $ref = '';
  if (!empty($_SERVER['HTTP_REFERER'])) {
    $host = parse_url((string) $_SERVER['HTTP_REFERER'], PHP_URL_HOST);
    if ($host) $ref = substr(preg_replace('/[^A-Za-z0-9.\-]/', '', (string) $host), 0, 90);
  }

  $ua = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 200);
  $movel = preg_match('/Mobile|Android|iPhone/i', $ua) ? 'movel' : 'desktop';

  // Único-do-dia: HMAC com sal que muda a cada dia.
  $sal = 'pe-visitante|' . date('Y-m-d');
  $ident = substr(hash_hmac('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . $ua, $sal), 0, 16);

  $canal = $params['src'] ?? ($params['utm_source'] ?? 'direto');

  $linha = [
    'ts'    => date('c'),
    'canal' => substr($canal, 0, 60),
    'o'     => substr($chaveOferta, 0, 32),
    'p'     => $params,
    'ref'   => $ref,
    'movel' => $movel,
    'uid'   => $ident,
  ];

  $json = json_encode($linha, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  if ($json === false) return;
  @file_put_contents($dir . '/acessos.jsonl', $json . "\n", FILE_APPEND | LOCK_EX);
}