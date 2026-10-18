<?php
// Pilates em Casa — funções de apoio do painel.
//
// Helpers do painel, separados do index.php para que ele cuide só de sessão,
// rotas e carregamento de dados (uma responsabilidade por arquivo): escape,
// CSRF, data e relatório (puros) + o estado pequeno em disco do antibrute e do
// acesso único (tentativas.json, bloqueios.json e sessao.json em private/dados).
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

// Grava JSON com o mínimo de cuidado: cria a pasta, escreve com lock (duas
// tentativas simultâneas não se atropelam) e devolve false quando não deu.
function gravarJson(string $file, array $d): bool {
  $dir = dirname($file);
  if (!is_dir($dir)) @mkdir($dir, 0755, true);
  $json = json_encode($d, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
  return $json !== false && @file_put_contents($file, $json, LOCK_EX) !== false;
}

// "2026-10-11T15:10:00-03:00" -> "11/10 15:10"
//
// O servidor grava em UTC (o ts carrega o offset), mas a dona está em
// America/Sao_Paulo: TUDO que o painel exibe é convertido para cá. Assim
// linhas antigas (UTC) e futuras convertem igual — o instante não muda, só
// a representação (setTimezone, confirmado na doc do PHP).
function tzPainel(): DateTimeZone {
  return new DateTimeZone('America/Sao_Paulo');
}

function brDataHora(string $iso): string {
  if ($iso === '') return '—';
  try {
    $d = new DateTime($iso);
    $d->setTimezone(tzPainel());
    return $d->format('d/m H:i');
  } catch (Exception $e) {
    return substr($iso, 8, 2) . '/' . substr($iso, 5, 2) . ' ' . substr($iso, 11, 5);
  }
}

// Mesma conversão, mas a partir do timestamp Unix — o antibrute grava epoch,
// não ISO. O "@" da DateTime é SEMPRE UTC (a doc oficial diz que a timezone do
// construtor é ignorada nesse formato), então o setTimezone é o que traz para
// o fuso da dona — vale para linha antiga e futura igual.
function brDataHoraDe(int $epoch, string $formato = 'd/m H:i'): string {
  try {
    $d = new DateTime('@' . $epoch);
    $d->setTimezone(tzPainel());
    return $d->format($formato);
  } catch (Exception $e) {
    return '—';
  }
}

// --------------------------------------------------------------- relatório
// Agrega as linhas do acessos.jsonl por canal.
function resumo(array $linhas): array {
  $r = array();
  // "Hoje" e "semana" no fuso da dona: a visita às 22:00 de SP é 01:00 UTC do
  // dia SEGUINTE no log — sem converter, ela sairia do balde "hoje" errado.
  $tz = tzPainel();
  $agora = new DateTime('now', $tz);
  $hoje = $agora->format('Y-m-d');
  $inicioSemana = (clone $agora)->modify('-6 days')->format('Y-m-d');
  foreach ($linhas as $l) {
    $c = $l['canal'] ?? 'direto';
    if (!isset($r[$c])) {
      $r[$c] = array('total' => 0, 'hoje' => 0, 'semana' => 0, 'uid' => array(), 'ultima' => '', 'oferta' => '');
    }
    $r[$c]['total']++;
    $r[$c]['oferta'] = $l['o'] ?? '';
    $ts = $l['ts'] ?? '';
    $r[$c]['ultima'] = $ts;
    try {
      $d = new DateTime($ts);
      $d->setTimezone($tz);
      $dia = $d->format('Y-m-d');
    } catch (Exception $e) {
      $dia = substr($ts, 0, 10);
    }
    if ($dia === $hoje) {
      $r[$c]['hoje']++;
      $r[$c]['uid'][$l['uid'] ?? ''] = 1;
    }
    if ($dia >= $inicioSemana) $r[$c]['semana']++;
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
// -------------------------------------------------------------- regras
// Os números do antibrute e do acesso único, num lugar só (o painel e o login
// leem daqui; as mensagens e a seção de tentativas citam os mesmos valores).
const THROTTLE_JANELA   = 3600;   // janela de observação das falhas: 1 hora
const THROTTLE_LIMITE   = 5;      // falhas na janela que armam o bloqueio
const THROTTLE_BLOQUEIO = 86400;  // duração do bloqueio: 24 horas
const SESSAO_IDLE       = 1800;   // inatividade que fecha a sessão (e libera o acesso único)

// Chave = HMAC do IP (LGPD: o IP nunca é gravado em texto claro, igual ao
// identificador de visitante em private/rastreio.php). O IP só entra no
// cálculo do HMAC.
//
// A chave é ESTÁVEL (sem a data no sal, como era antes): um bloqueio de 24 h
// (e o permanente) precisa da MESMA chave amanhã — com o sal girando todo dia,
// o bloqueio evaporava à meia-noite e o 2º bloqueio nunca era reconhecido.
function throttlingChave(): string {
  return substr(hash_hmac('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? ''), 'pe-throttle'), 0, 16);
}

function throttlingArquivo(): string {
  return dirname(__DIR__) . '/private/dados/tentativas.json';
}

// Bloqueios em vigor, separados das falhas: a falha é podada depois de 1 dia,
// mas um bloqueio de 24 h (ou permanente) precisa sobreviver à poda.
function bloqueiosArquivo(): string {
  return dirname(__DIR__) . '/private/dados/bloqueios.json';
}

function throttlingLer(): array {
  return lerJson(throttlingArquivo());
}

function throttlingGravar(array $d): void {
  gravarJson(throttlingArquivo(), $d);
}

function bloqueiosLer(): array {
  return lerJson(bloqueiosArquivo());
}

function bloqueiosGravar(array $d): void {
  gravarJson(bloqueiosArquivo(), $d);
}

// Falhas que ainda contam: só as da última hora (a janela de observação).
function throttlingNaJanela(array $falhas): array {
  $agora = time();
  return array_values(array_filter(array_map('intval', $falhas), fn($t) => ($agora - $t) < THROTTLE_JANELA));
}

// Situação de um bloqueio gravado, calculada com o relógio que o chamador
// passa (PURO: o painel monta todas as linhas com o mesmo `agora`).
//   - permanente: não expira — só sai com "Apagar tentativas de login";
//   - temporario: vale até `libera` (24 h contadas de quando foi armado);
//   - livre: sem bloqueio (bloqueio inexistente, vencido ou já podado).
function bloqueioSituacao(array $b, int $agora): array {
  if (!empty($b['permanente'])) {
    return array('estado' => 'permanente', 'libera' => 0, 'resta' => 0, 'desde' => (int) ($b['desde'] ?? 0));
  }
  $libera = (int) ($b['libera'] ?? 0);
  if (($libera - $agora) <= 0) return array('estado' => 'livre', 'libera' => 0, 'resta' => 0, 'desde' => 0);
  return array('estado' => 'temporario', 'libera' => $libera, 'resta' => $libera - $agora, 'desde' => (int) ($b['desde'] ?? 0));
}

// Arma (ou renova) o bloqueio de uma chave e devolve a situação nova. `vezes`
// conta quantos bloqueios este aparelho já levou — é o que transforma a
// REINCIDÊNCIA (2º bloqueio) em permanente sem depender de ninguém lembrar.
function bloqueioArmar(string $chave, bool $permanente): array {
  $todos = bloqueiosLer();
  $agora = time();
  $atual = is_array($todos[$chave] ?? null) ? $todos[$chave] : array();
  $sit   = bloqueioSituacao($atual, $agora);
  $todos[$chave] = array(
    // `desde` = começo do bloqueio ATUAL: um bloqueio vencido há semanas não
    // pode fazer o novo parecer antigo na tela.
    'desde'      => $sit['estado'] === 'livre' ? $agora : (int) ($atual['desde'] ?? $agora),
    'libera'     => $permanente ? 0 : $agora + THROTTLE_BLOQUEIO,
    'permanente' => $permanente,
    'vezes'      => (int) ($atual['vezes'] ?? 0) + 1,
  );
  bloqueiosGravar($todos);
  return bloqueioSituacao($todos[$chave], $agora);
}

// Bloqueios vencidos não somem na hora: `vezes` é o histórico que reconhece a
// reincidência. A poda tira só o que venceu há mais de 30 dias — e nunca um
// permanente (esse não vence).
function bloqueiosPodar(array $todos, int $agora): array {
  foreach ($todos as $chave => $b) {
    if (!is_array($b)) { unset($todos[$chave]); continue; }
    if (!empty($b['permanente'])) continue;
    $libera = (int) ($b['libera'] ?? 0);
    if ($libera <= 0 || ($agora - $libera) > 2592000) unset($todos[$chave]);
  }
  return $todos;
}

// Estado do bloqueio da chave ATUAL (de quem está falando com o painel).
function throttlingEstado(): array {
  $bloqueios = bloqueiosLer();
  $chave = throttlingChave();
  return bloqueioSituacao(is_array($bloqueios[$chave] ?? null) ? $bloqueios[$chave] : array(), time());
}

// Registra uma tentativa errada e devolve quantas falhas há na janela.
function throttlingRegistrar(): int {
  $chave = throttlingChave();
  $todos = throttlingLer();
  $agora = time();
  $falhas = isset($todos[$chave]) && is_array($todos[$chave]) ? throttlingNaJanela($todos[$chave]) : array();
  $falhas[] = $agora;
  $todos[$chave] = $falhas;
  // poda: limpa IPs que não falham há mais de 1 dia (o arquivo não cresce sem parar)
  foreach ($todos as $k => $ts) {
    if (!is_array($ts) || !$ts || ($agora - (int) max($ts)) > 86400) unset($todos[$k]);
  }
  throttlingGravar($todos);
  return count($falhas);
}

// Senha errada. Ao bater o limite na janela, arma o bloqueio de 24 h — ou já
// permanente, se este aparelho já foi bloqueado antes (reincidência). Devolve
// o estado DEPOIS do registro: o login usa isso para responder "bloqueado por
// 24 horas; não insista" já na própria resposta do 5º erro, que é a última
// chance de avisar antes de um próximo POST virar permanente.
function throttlingFalhou(): array {
  $chave = throttlingChave();
  $qtd   = throttlingRegistrar();
  $agora = time();
  $b     = bloqueiosLer();
  $atual = is_array($b[$chave] ?? null) ? $b[$chave] : array();
  $sit   = bloqueioSituacao($atual, $agora);
  if ($sit['estado'] === 'livre' && $qtd >= THROTTLE_LIMITE) {
    return bloqueioArmar($chave, (int) ($atual['vezes'] ?? 0) >= 1);
  }
  return $sit;
}

// Insistiu DURANTE o bloqueio: registra a tentativa e torna o bloqueio
// permanente. A resposta anterior avisou ("não insista"), então quem insiste
// é robô ou quem ignorou o aviso.
function throttlingInsistiu(): array {
  throttlingRegistrar();
  return bloqueioArmar(throttlingChave(), true);
}

// Senha certa: limpa as falhas E o bloqueio de quem acertou (deixa o registro
// consistente; quem está bloqueado nem chega ao password_verify).
function throttlingLimpar(): void {
  $chave = throttlingChave();
  $todos = throttlingLer();
  unset($todos[$chave]);
  throttlingGravar($todos);
  $bloqueios = bloqueiosLer();
  unset($bloqueios[$chave]);
  bloqueiosGravar($bloqueios);
}

// O botão do painel: apaga TUDO — tentativas E bloqueios, inclusive os
// permanentes. É o caminho de volta de quem se bloqueou: entrar de outro
// aparelho/rede e limpar aqui. Não toca no log de visitas nem nos links.
function throttlingApagar(): void {
  throttlingGravar(array());
  bloqueiosGravar(array());
}

// A lista que a seção "Tentativas de login" mostra: uma linha por chave,
// juntando o registro de falhas com o de bloqueios. A junção é necessária
// porque um bloqueio sobrevive à poda das falhas (1 dia) — sem ela, um
// bloqueio permanente sumiria da tela depois de um dia sem novas tentativas.
// Ordem: permanentes, depois os bloqueados, depois o mais recente.
function throttlingTentativas(): array {
  $agora = time();
  $bloqueios = bloqueiosLer();
  $podados = bloqueiosPodar($bloqueios, $agora);
  if ($podados !== $bloqueios) bloqueiosGravar($podados);

  $chaves = array();
  $falhas = throttlingLer();
  foreach ($falhas as $chave => $ts) if (is_array($ts) && $ts) $chaves[(string) $chave] = true;
  foreach ($podados as $chave => $_) $chaves[(string) $chave] = true;

  $linhas = array();
  foreach (array_keys($chaves) as $chave) {
    $ts  = is_array($falhas[$chave] ?? null) ? array_map('intval', $falhas[$chave]) : array();
    $b   = is_array($podados[$chave] ?? null) ? $podados[$chave] : array();
    $sit = bloqueioSituacao($b, $agora);

    $linhas[] = array(
      'chave'  => (string) $chave,
      'total'  => count($ts),
      // Sem falhas registradas (poda), a "última tentativa" vira o começo do
      // bloqueio: é o que a linha ainda tem para mostrar.
      'ultima' => $ts ? max($ts) : (int) ($b['desde'] ?? 0),
      'estado' => $sit['estado'],
      'libera' => $sit['libera'],
      'vezes'  => (int) ($b['vezes'] ?? 0),
    );
  }

  $peso = array('permanente' => 0, 'temporario' => 1, 'livre' => 2);
  usort($linhas, function ($a, $b) use ($peso) {
    if ($peso[$a['estado']] !== $peso[$b['estado']]) return $peso[$a['estado']] <=> $peso[$b['estado']];
    return $b['ultima'] <=> $a['ultima'];
  });
  return $linhas;
}

// Somatórios da seção de tentativas (mesmo par ler/resumir do relatório).
function tentativasTotais(array $linhas): array {
  $total = 0; $bloqueados = 0; $permanentes = 0;
  foreach ($linhas as $l) {
    $total += (int) $l['total'];
    if ($l['estado'] !== 'livre') $bloqueados++;
    if ($l['estado'] === 'permanente') $permanentes++;
  }
  return array('total' => $total, 'bloqueados' => $bloqueados, 'permanentes' => $permanentes);
}

// "Quanto falta da espera" em português. Só existe bloqueio de 24 h, mas a
// função arredonda para cima e fala em horas (o texto sai certo em qualquer
// ajuste futuro do THROTTLE_BLOQUEIO).
function throttlingTexto(int $seg): string {
  $horas = max(1, (int) ceil($seg / 3600));
  return $horas === 1 ? '1 hora' : $horas . ' horas';
}

// ---------------------------------------------------- um acesso por vez
// O painel é de uma pessoa só: no máximo UM acesso aberto. Um segundo login,
// MESMO COM A SENHA CERTA, falha enquanto o primeiro estiver vivo (visto há
// menos de SESSAO_IDLE); ninguém é expulso — quem está dentro continua dentro.
// O carimbo é liberado no "Sair" e na expiração por inatividade, os dois
// caminhos que derrubam a sessão.
function sessaoArquivo(): string {
  return dirname(__DIR__) . '/private/dados/sessao.json';
}

function sessaoLer(): array {
  return lerJson(sessaoArquivo());
}

// Grava quem está com o acesso aberto. Chamar DEPOIS do session_regenerate_id:
// antes dele, session_id() ainda é o id velho.
function sessaoMarcar(): void {
  gravarJson(sessaoArquivo(), array('id' => session_id(), 'visto' => time()));
}

// Renova o carimbo a cada request logado: é isto que mantém o acesso "vivo"
// enquanto a dona usa o painel (e o que faz o próximo login esperar).
function sessaoToque(): void {
  $s = sessaoLer();
  if (($s['id'] ?? '') === session_id()) gravarJson(sessaoArquivo(), array('id' => session_id(), 'visto' => time()));
}

// Outro acesso aberto agora? Devolve o epoch do último uso dele (0 = livre).
// O MESMO id de sessão não conta: é este mesmo acesso recarregando a página.
function sessaoOutroAcesso(): int {
  $s = sessaoLer();
  $id = (string) ($s['id'] ?? '');
  $visto = (int) ($s['visto'] ?? 0);
  if ($id === '' || $id === session_id()) return 0;
  return ($visto > 0 && (time() - $visto) < SESSAO_IDLE) ? $visto : 0;
}

// Libera o carimbo (logout e expiração). Só apaga se o carimbo for DESTA
// sessão: assim um acesso antigo que se despede não derruba o acesso atual.
function sessaoLiberar(): void {
  $s = sessaoLer();
  if (($s['id'] ?? '') === session_id()) gravarJson(sessaoArquivo(), array());
}

// ------------------------------------------------------------------- CSV
function painelCsv(array $linhas): void {
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="visitas.csv"');
  $out = fopen('php://output', 'w');
  fwrite($out, "\xEF\xBB\xBF"); // BOM p/ o Excel abrir com acentos
  fputcsv($out, array('data_hora', 'canal', 'oferta', 'origem_externa', 'dispositivo'));
  foreach ($linhas as $l) {
    $ts = (string) ($l['ts'] ?? '');
    // Mesma regra da tabela: exibe no fuso da dona (o log é UTC).
    try { $ts = (new DateTime($ts))->setTimezone(tzPainel())->format('d/m/Y H:i'); } catch (Exception $e) {}
    fputcsv($out, array($ts, $l['canal'] ?? '', $l['o'] ?? '', $l['ref'] ?? '', $l['movel'] ?? ''));
  }
  exit;
}