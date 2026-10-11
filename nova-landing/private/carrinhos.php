<?php
// Pilates em Casa — gestao dos carrinhos (Kiwify).
//
// POR QUE EXISTEM DOIS ARQUIVOS:
//   - private/ofertas.php  -> o "cardapio base", versionado no git. É o que a
//     Action publica a cada deploy. Fonte de verdade do que vem no codigo.
//   - private/dados/ofertas.json -> o que a DONA cria/edita pelo painel. NAO
//     esta no git, entao o proximo deploy NAO apaga o cadastro dela (vale o
//     mesmo para links.json e acessos.jsonl).
// Na leitura, o que esta no JSON SOBREPOE o base. Assim da para criar, editar
// e apagar carrinhos pela tela sem risco de perder no proximo push.
//
// REGRA DE OURO: o preco cobrado e SEMPRE o do checkout Kiwify (1 checkout por
// preco). Os valores aqui controlam so o que aparece escrito na landing — se
// divergirem da Kiwify, a pessoa ve um preco e paga outro.

if (!defined('OFERTAS_BOOT')) {
  http_response_code(403);
  exit('Negado.');
}

function carrinhosArquivo(): string { return __DIR__ . '/dados/ofertas.json'; }

// Carrinho padrao do site: sem ele a landing cai no fallback.
function carrinhosChavePadrao(): string { return 'padrao'; }

function carrinhosBase(): array {
  return require __DIR__ . '/ofertas.php';
}

function carrinhosExtra(): array {
  $f = carrinhosArquivo();
  if (!is_file($f)) return array();
  $d = json_decode((string) @file_get_contents($f), true);
  return is_array($d) ? $d : array();
}

function carrinhosSalvarExtra(array $extra): bool {
  $f = carrinhosArquivo();
  $d = dirname($f);
  if (!is_dir($d)) @mkdir($d, 0755, true);
  $json = json_encode($extra, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  if ($json === false) return false;
  return @file_put_contents($f, $json, LOCK_EX) !== false;
}

// Lista final = base + sobrescritas do painel - ocultados.
function carrinhosTodos(): array {
  $t       = carrinhosBase();
  $extra   = carrinhosExtra();
  $ocultos = array();
  if (isset($extra['_ocultos']) && is_array($extra['_ocultos'])) $ocultos = $extra['_ocultos'];
  unset($extra['_ocultos']);

  foreach (array_keys($t) as $k) {
    if (in_array($k, $ocultos, true)) unset($t[$k]);
  }
  foreach ($extra as $k => $o) {
    if (!is_array($o)) continue;
    $t[$k] = $o + (isset($t[$k]) && is_array($t[$k]) ? $t[$k] : array());
  }
  return $t;
}

// Canais sugeridos, para a dona criar os links rapido sem digitar o codigo.
function carrinhosCanaisSugeridos(): array {
  return array(
    'whatsapp'  => 'WhatsApp (status/conversa)',
    'ig-bio'    => 'Bio do Instagram',
    'tt-bio'    => 'Bio do TikTok',
    'kwai-bio'  => 'Bio do Kwai',
    'fb-ads'    => 'Anuncio pago (Facebook/Instagram)',
    'bio-site'  => 'Bio/rodape do site',
  );
}

// Valida e normaliza o formulario de carrinho. Devolve [dados, erros].
function carrinhoValidar(array $in): array {
  $erros = array();

  $chave = preg_replace('/[^a-z0-9_-]/', '', strtolower(trim((string) ($in['chave'] ?? ''))));
  if ($chave === '') {
    $erros[] = 'Informe um codigo para o carrinho.';
  } elseif (strlen($chave) > 24) {
    $erros[] = 'Codigo muito longo (maximo 24 caracteres).';
  }

  $rotulo = trim((string) ($in['rotulo'] ?? ''));
  if ($rotulo === '') $erros[] = 'Informe um nome para identificar o carrinho.';
  if (mb_strlen($rotulo) > 60) $rotulo = mb_substr($rotulo, 0, 60);

  $checkout = trim((string) ($in['checkout'] ?? ''));
  if ($checkout === '' || !filter_var($checkout, FILTER_VALIDATE_URL) || stripos($checkout, 'https://') !== 0) {
    $erros[] = 'O checkout precisa ser uma URL completa com https://';
  }

  $cheio = (int) preg_replace('/[^0-9]/', '', (string) ($in['cheio'] ?? ''));
  $preco = (int) preg_replace('/[^0-9]/', '', (string) ($in['preco'] ?? ''));
  $total = (int) preg_replace('/[^0-9]/', '', (string) ($in['total'] ?? ''));
  $parcN = (int) preg_replace('/[^0-9]/', '', (string) ($in['parcN'] ?? ''));

  if ($cheio <= 0) $erros[] = 'Preco "de" precisa ser maior que zero.';
  if ($preco <= 0) $erros[] = 'Preco "por" precisa ser maior que zero.';
  if ($preco > $cheio) $erros[] = 'O preco "por" nao pode ser maior que o preco "de".';
  if ($parcN < 1 || $parcN > 24) $erros[] = 'Parcelas deve ser entre 1 e 24.';

  // Valor da parcela: aceita "10,69" (BR) ou "10.69".
  $parcV = str_replace(array('.', ' '), ',', trim((string) ($in['parcV'] ?? '')));
  if (!preg_match('/^\d{1,4},\d{2}$/', $parcV)) {
    $erros[] = 'Valor da parcela invalido (use 10,69).';
    $parcV = '0,00';
  }

  $d = array(
    'chave'    => $chave,
    'checkout' => $checkout,
    'rotulo'   => $rotulo,
    'cheio'    => $cheio,
    'preco'    => $preco,
    'total'    => max(0, $total),
    'parcN'    => $parcN,
    'parcV'    => $parcV,
  );

  return array($d, $erros);
}

// Grava/edita um carrinho. Devolve array(ok, erros).
function carrinhoGravar(array $d): array {
  $chave = $d['chave'];
  unset($d['chave']);
  $extra = carrinhosExtra();

  if (isset($extra['_ocultos']) && is_array($extra['_ocultos'])) {
    $extra['_ocultos'] = array_values(array_filter($extra['_ocultos'], function ($k) use ($chave) {
      return $k !== $chave;
    }));
  }
  $extra[$chave] = $d;
  ksort($extra);

  if (!carrinhosSalvarExtra($extra)) {
    return array(false, array('Nao consegui gravar o carrinho (pasta private/dados sem escrita).'));
  }
  return array(true, array());
}

// Remove um carrinho. Base vai para a lista de ocultados; criado no painel e
// apagado de vez.
function carrinhoApagar(string $chave): array {
  if ($chave === carrinhosChavePadrao()) {
    return array(false, array('O carrinho padrao nao pode ser apagado — e o que o site mostra sem parametro.'));
  }
  $extra   = carrinhosExtra();
  $existe  = array_key_exists($chave, carrinhosBase());
  unset($extra[$chave]);

  if ($existe) {
    $oc = isset($extra['_ocultos']) && is_array($extra['_ocultos']) ? $extra['_ocultos'] : array();
    $oc[] = $chave;
    $extra['_ocultos'] = array_values(array_unique($oc));
  }
  if (!carrinhosSalvarExtra($extra)) {
    return array(false, array('Nao consegui gravar as alteracoes (pasta private/dados sem escrita).'));
  }
  return array(true, array());
}

// Desfaz a edicao feita no painel, voltando ao valor do cardapio base (git).
function carrinhoRestaurar(string $chave): array {
  $extra = carrinhosExtra();
  unset($extra[$chave]);
  if (isset($extra['_ocultos']) && is_array($extra['_ocultos'])) {
    $extra['_ocultos'] = array_values(array_filter($extra['_ocultos'], function ($k) use ($chave) {
      return $k !== $chave;
    }));
  }
  if (!carrinhosSalvarExtra($extra)) {
    return array(false, array('Nao consegui gravar as alteracoes (pasta private/dados sem escrita).'));
  }
  return array(true, array());
}

// Carrinho alterado no painel em relacao ao base?
function carrinhoFoiEditadoNoPainel(string $chave): bool {
  $extra = carrinhosExtra();
  return array_key_exists($chave, $extra);
}