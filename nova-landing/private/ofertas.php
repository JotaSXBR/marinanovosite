<?php
// Pilates em Casa — cadastro local das ofertas (EDITÁVEL).
//
// Como adicionar um carrinho novo com preço diferente:
//   1. Crie o checkout na Kiwify (1 checkout por preço — o preço real é
//      cobrado lá, nunca nesta landing).
//   2. Copie o bloco de exemplo abaixo, troque a chave por um código
//      aleatório (ex.: 'a7X9k2' — nunca 'vip' ou 'promo', que são adivináveis).
//   3. Preencha checkout/cheio/preco/total/parcN/parcV e suba via push na main
//      (o deploy FTP publica sozinho).
//   4. Divulgue o link secreto: https://SEU-DOMINIO/?o=a7X9k2
//      (sem parâmetro, o site sempre mostra a oferta 'padrao').
//
// Este arquivo SOBE para o servidor mas é INACESSÍVEL por URL:
//   - nova-landing/private/.htaccess nega todo acesso HTTP à pasta;
//   - o guarda abaixo responde 403 se alguém abrir o arquivo direto
//     (caso o Apache ignore o .htaccess);
//   - a landing (index.php) lê por caminho interno (require), nunca por URL;
//   - o HTML entregue ao visitante contém SÓ a oferta resolvida — as demais
//     nunca aparecem no Ver código-fonte.

if (!defined('OFERTAS_BOOT')) {
  http_response_code(403);
  exit('Negado.');
}

return [
  // Oferta padrão: exibida sem parâmetro e como fallback de código inválido.
  'padrao' => [
    'checkout' => 'https://pay.kiwify.com.br/U4n0qN7',
    'cheio'    => 97,
    'preco'    => 57,
    'total'    => 613,
    'parcN'    => 6,
    'parcV'    => '10,69',
    'rotulo'   => 'Padrão (R$ 97 por R$ 57)',
  ],

  // EXEMPLO de oferta secreta (descomente e ajuste quando existir de verdade):
  // 'a7X9k2' => [
  //   'checkout' => 'https://pay.kiwify.com.br/TROCAR_PELO_CHECKOUT_REAL',
  //   'cheio'    => 97,
  //   'preco'    => 47,
  //   'total'    => 613,
  //   'parcN'    => 6,
  //   'parcV'    => '8,89',
  //   'rotulo'   => 'WhatsApp (R$ 97 por R$ 47)',
  // ],
];
