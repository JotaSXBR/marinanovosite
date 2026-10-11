<?php
// Pilates em Casa — painel da proprietária (controlador).
//
// RESPONSABILIDADE DESTE ARQUIVO (e só dele):
//   1. iniciar a sessão e validar o login;
//   2. despachar as ações do POST (carrinhos e links);
//   3. carregar os dados que as telas vão mostrar;
//   4. delegar o HTML para partes/*.php.
//
// O que NÃO mora aqui: helpers (painel/apoio.php), regras de carrinho
// (private/carrinhos.php), registro de visita (private/rastreio.php) e o
// HTML/CSS (painel/partes/). Um arquivo, uma responsabilidade.
//
// As partes são carregadas por require e recebem as variáveis deste arquivo.
// Nenhuma delas é acessível por URL: painel/.htaccess nega tudo, e o
// FilesMatch do .htaccess raiz cobre *.php dentro de painel/ por segurança.

define('OFERTAS_BOOT', true);
define('PAINEL_BOOT', true);

$raiz = dirname(__DIR__) . '/private';       // private/ fica na raiz do site
require $raiz . '/carrinhos.php';
require __DIR__ . '/apoio.php';
$config = require __DIR__ . '/config.php';

$logDir  = $raiz . '/dados';
$logArq  = $logDir . '/acessos.jsonl';
$linksFile = $logDir . '/links.json';

$ofertas = carrinhosTodos();

// ------------------------------------------------------------------ sessão
// Sessão endurecida (doc oficial do PHP + OWASP Session Management):
//   - cookie Secure + HttpOnly + SameSite=Strict + Path=/;
//   - prefixo __Host- (o navegador só aceita se vier por HTTPS, sem Domain e
//     com Path=/) — aplicado só quando é HTTPS, senão o browser rejeita e a
//     sessão quebraria;
//   - use_strict_mode: recusa um id de sessão inventado pelo atacante
//     (session fixation).
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

if (session_status() === PHP_SESSION_NONE) {
  session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => $https,
    'httponly' => true,
    'samesite' => 'Strict',
  ]);
  session_name($https ? '__Host-pepainel' : 'pepainel');
  session_start([
    'use_strict_mode' => 1,
    'sid_length'     => 48,
  ]);
}

// Expira por inatividade: deixar o painel aberto no celular não pode deixar a
// sessão viva para sempre (telonave esquecido, etc).
$IDLE = 1800; // 30 min
if (!empty($_SESSION['painel_ok'])) {
  $visto = (int) ($_SESSION['painel_visto'] ?? 0);
  if ($visto > 0 && (time() - $visto) > $IDLE) {
    $_SESSION = array();
    session_destroy();
    header('Location: ./?sessao=expirada');
    exit;
  }
}
$_SESSION['painel_visto'] = time();

$logado = !empty($_SESSION['painel_ok']);
$erro   = '';
if (isset($_GET['sessao']) && $_GET['sessao'] === 'expirada') {
  $erro = 'Sua sessão fechou por ficar muito tempo sem uso. Entre de novo.';
}

// ------------------------------------------------------------------- login
// Antibrute: bloquear ANTES de chamar password_verify (senão o atacante gasta
// CPU do servidor à vontade mesmo bloqueado).
$bloqueio = throttlingRestante();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'entrar') {
  if ($bloqueio > 0) {
    $erro = 'Muitas tentativas erradas. Tente de novo em ' . throttlingTexto($bloqueio) . '.';
  } elseif (!csrfValido()) {
    $erro = 'Sessão expirada. Tente de novo.';
  } elseif (password_verify((string) ($_POST['senha'] ?? ''), $config['senha_hash'])) {
    session_regenerate_id(true); // evita session fixation
    $_SESSION['painel_ok'] = true;
    $_SESSION['painel_visto'] = time();
    throttlingLimpar();           // acerto limpa o histórico do IP
    $logado = true;
  } else {
    throttlingFalhou();
    $erro = 'Senha incorreta.';
  }
}

// ------------------------------------------------------- despacho do POST
// Tudo que altera dados exige sessão ativa + token CSRF válido.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $logado && csrfValido()) {
  $acao = (string) ($_POST['acao'] ?? '');

  if ($acao === 'salvar_link') {
    $canal  = substr(preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) ($_POST['canal'] ?? ''))), 0, 40);
    $rotulo = substr(trim((string) ($_POST['rotulo'] ?? '')), 0, 60);
    $oferta = (string) ($_POST['oferta'] ?? carrinhosChavePadrao());

    // Link rápido: o canal veio de um botão sugerido e o rótulo ficou vazio.
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
      $links = lerJson($linksFile);
      $links[$canal] = array('rotulo' => $rotulo, 'oferta' => $oferta);
      ksort($links);
      $json = json_encode($links, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
      if (@file_put_contents($linksFile, $json) === false) {
        $erro = 'Não consegui gravar o link (a pasta private/dados está sem escrita?).';
      } else {
        $_SESSION['painel_flash'] = 'Link salvo: ' . $canal;
      }
    }
  }

  if ($acao === 'apagar_link') {
    $canal  = (string) ($_POST['canal'] ?? '');
    $links  = lerJson($linksFile);
    unset($links[$canal]);
    @file_put_contents($linksFile, json_encode($links, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
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
      // Renomear: apaga o registro antigo para não sobrar duplicata.
      if ($original !== '' && $original !== $d['chave']) {
        carrinhoApagar($original);
        foreach (lerJson($linksFile) as $c => $l) {
          if (($l['oferta'] ?? '') === $original) {
            $_SESSION['painel_flash'] = 'Carrinho renomeado. Revise os links que apontavam para ele.';
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
    @file_put_contents($logArq, '');
    $_SESSION['painel_flash'] = 'Histórico de visitas apagado.';
  }

  // POST -> GET: evita reenvio ao dar F5 (boa prática de formulário).
  header('Location: ./');
  exit;
}

if (isset($_GET['sair'])) {
  $_SESSION = array();
  session_destroy();
  header('Location: ./');
  exit;
}

// -------------------------------------------------- dados para as telas
$linhas = lerVisitas($logArq);
$resumo = resumo($linhas);
$links  = lerJson($linksFile);
$base   = baseUrl();

if ($logado && isset($_GET['csv'])) painelCsv($linhas);

// ------------------------------------------ preencher forms (editar)
$editCarrinho = isset($_GET['editar_carrinho']) ? (string) $_GET['editar_carrinho'] : '';
$formCarrinho = array();
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
  $editCarrinho = '';
}
// se houve erro de validação, o que a dona digitado tem que voltar na tela
$formCarrinho = array_merge($formCarrinho, (array) ($_SESSION['painel_form'] ?? array()));
unset($_SESSION['painel_form']);

// $links já foi carregado acima — por isso esta leitura vem DEPOIS dele.
$editLink = isset($_GET['editar_link']) ? (string) $_GET['editar_link'] : '';
$formLink  = array();
if ($editLink !== '' && isset($links[$editLink])) {
  $formLink = array(
    'canal'  => $editLink,
    'rotulo' => $links[$editLink]['rotulo'] ?? '',
    'oferta' => $links[$editLink]['oferta'] ?? carrinhosChavePadrao(),
  );
} else {
  $editLink = '';
}

$flash = $_SESSION['painel_flash'] ?? '';
unset($_SESSION['painel_flash']);

// ------------------------------------------------------------------ render
// Cada parte recebe as variáveis acima no escopo do require.
$p = __DIR__ . '/partes/';
require $p . 'cabeca.php';

if (!$logado) {
  require $p . 'login.php';
} else {
  require $p . 'topo.php';
  require $p . 'relatorio.php';
  require $p . 'carrinhos.php';
  require $p . 'links.php';
}

require $p . 'rodape.php';