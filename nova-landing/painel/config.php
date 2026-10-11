<?php
// Pilates em Casa — senha do painel (NÃO É O ARQUIVO DE DADOS).
//
// Troque a senha: gere um hash em qualquer servidor PHP com
//   php -r "echo password_hash('SUA_SENHA_NOVA', PASSWORD_DEFAULT), PHP_EOL;"
// e cole o resultado no lugar de $hash abaixo.
//
// Sem hash = painel bloqueado (não deixa entrar sem senha). Nunca
// guardamos a senha em texto puro, só o hash.
if (!defined('OFERTAS_BOOT')) {
  http_response_code(403);
  exit('Negado.');
}

$hash = '$2y$12$oMR2CvrhjFCEK3BbXBNys..tje2eu2ADiS0a/u9kg7HDbr6fBh5wi'; // password_hash(...)

return ['senha_hash' => $hash];