# Tutorial — carrinhos da Kiwify + painel do site

Documento prático para criar uma nova oferta ("carrinho") do zero: o que criar
na **Kiwify**, o que preencher no **painel** e como conferir antes de divulgar.
Fontes oficiais consultadas em 17/10/2026: ajuda.kiwify.com.br (artigos *Como
pegar o link de checkout*, *O que é e como criar uma oferta*, *Como passar
parâmetros de rastreamento*) + docs API Kiwify.

## Como o sistema funciona (visão de 1 minuto)

- A landing (`nova-landing/index.php`) lê `?o=CODIGO` e entrega **só a oferta
  ativa**; sem parâmetro (ou código inválido) cai na oferta `padrao`. As demais
  nunca aparecem no "Ver código-fonte".
- **1 checkout por preço:** a Kiwify dá a cada oferta (preço) um **link de checkout próprio** (até ~300 ofertas por produto). Cadastrar carrinho novo = 1 oferta na Kiwify = 1 link = 1 checkout.
- Os valores do painel controlam **só o texto da landing** — se divergirem da
  Kiwify, a pessoa vê um preço e paga outro. Sempre conferir a dupla.
- Links por canal levam `?src=...`; o site loga a visita
  (`private/rastreio.php`) e **repassa** o parâmetro ao checkout. A venda se
  confirma na Kiwify pelo mesmo valor.

## Parte 1 — Criar a oferta / preço na Kiwify

1. Entre no **dashboard da Kiwify** → menu **Produtos** → abra o produto
   (Pilates em Casa). Se ainda não existir, cadastre primeiro (ver artigo
   oficial *Como cadastrar o seu produto*: nome, tipo, conteúdo, suporte).
2. Na página do produto, localize a seção **Preços** (aba Geral) →
   **Adicionar oferta**.

   > A Kiwify aceita **até ~300 ofertas por produto** e **cada oferta ganha um
   > link de checkout próprio** — é por isso que a regra do projeto ("1 checkout
   > por preço") nasce naturalmente daqui: nunca reutilizar um link com preço
   > diferente.
3. Preencha o **valor da oferta** (ex.: R$ 47 para a campanha de WhatsApp). Se
   aparecer campo de preço cheio na Kiwify, preencha também. Atenção: o par
   "de → por" exibido na landing vem dos campos `cheio`/`preco` do **painel** —
   a oferta da Kiwify define só o preço **cobrado**. Portanto o valor da
   oferta na Kiwify tem que ser igual ao `preco` que você vai cadastrar.
4. **Salvar.**

Confira também, no mesmo produto, as **formas de pagamento** (Pix, boleto,
cartão) e o **parcelamento** — a landing exibe `parcN`x de `parcV`, então o
máximo de parcelas configurado lá precisa bater com o prometido aqui.

## Parte 2 — Pegar o link de checkout

1. Menu **Produtos** → no produto, ícone **[...]** no canto direito →
   **Ver Links**.
2. Encontre o link da oferta criada e copie. Formato oficial:
   `https://pay.kiwify.com.br/xxxxxxxx`.
3. **Confira na hora:** cole o link no navegador e veja se o preço do checkout
   é o certo **antes** de cadastrar no painel.

## Parte 3 — Cadastrar o carrinho no painel do site

1. Abra `https://maricoachfit.com/painel/` e faça login.
2. **Gestão de carrinhos** → **Cadastrar**. Campos (o painel valida tudo
   abaixo, ver `private/carrinhos.php → carrinhoValidar`):

   | Campo | O que é | Regra do painel | Exemplo (`padrao`) |
   |---|---|---|---|
   | `chave` | código secreto da URL `?o=...` | `[a-z0-9_-]`, máx. 24; **aleatório** (nunca `vip`/`promo`) | `a7X9k2` |
   | `checkout` | link da oferta Kiwify | URL completa com `https://` | `https://pay.kiwify.com.br/U4n0qN7` |
   | `cheio` | preço "de" (riscado) | inteiro ≥ `preco` | `97` |
   | `preco` | preço "por" (cobrança) | inteiro > 0 e ≤ `cheio` | `57` |
   | `total` | âncora de valor riscada | = `cheio` + **soma dos bônus riscados** da lista "O que você leva hoje" | `97+47+67+97+47+37+37+37+147` = `613` |
   | `parcN` | nº de parcelas exibidas | 1–24 | `6` |
   | `parcV` | valor da parcela | formato BR `10,69` (aceita `10.69`) | `10,69` |
   | `rotulo` | nome interno no painel | texto, máx. 60 chars | `Padrão (R$ 97 por R$ 57)` |

3. **Salvar** → a oferta já está ativa em `https://maricoachfit.com/?o=CHAVE`.
4. Cuidado: mudar `total` sem mudar os bônus (ou vice-versa) deixa a âncora
   mentindo — a lista de bônus está hardcoded no `index.php` (linhas ~704–713),
   então se mexer lá, atualize `total` de cada carrinho.

**Como o painel guarda:** o que a dona cadastra/edita vai para
`private/dados/ofertas.json` (fora do git, sobrevive ao deploy) e **sobrepõe**
o cardápio base `private/ofertas.php` na leitura. Portanto:

- **Pela dona (rotina):** sempre pelo painel — não precisa tocar código.
- **Dev (base versionada):** para alterar o base versionado, edite
  `private/ofertas.php` copiando o bloco de exemplo comentado, com **chave
  aleatória**, e `git push` na `main`.

**Editar / apagar / restaurar** (gestão de carrinhos):

- `padrao` **não pode ser apagado** (ele é o fallback).
- Apagar um carrinho que veio do base guarda a chave em `_ocultos` (a oferta
  deixa de ser servida, mas continua no código) — **Restaurar** desfaz.
- Editar só o texto (preço/parcelas) não exige refazer o checkout da Kiwify;
  trocar o valor cobrado exige nova oferta + novo link (Parte 1).

## Parte 4 — Links por canal e rastreio (`src`)

1. Painel → **Links prontos por canal**: geram de uma vez os links para
   **WhatsApp, IG, TikTok, Kwai, Meta** + **personalizado** (com botão copiar e
   exportação CSV). Cada um é `https://maricoachfit.com/?src=codigo-do-canal`.
2. Como circula: visita → `rastreio.php` loga 1 linha (canal, oferta, hora,
   origem externa, identificador anônimo; IP nunca em claro — HMAC com sal do
   dia) → o visitante recebe a oferta → o **checkout recebe `src`/UTM
   repassados** pelo site.
3. **Parâmetros que a Kiwify aceita** (doc oficial, atualizado 10/04/2025):
   `src`, `sck`, `utm_source`, `utm_medium`, `utm_campaign`, `utm_term`,
   `utm_content`, `s1`, `s2`, `s3`. O repasse do nosso site usa a whitelist
   `src` + `utm_source/medium/campaign/term/content` (ver `rastreio.php`).

### O código do link personalizado — como nasce e como se lê na Kiwify

**Importante: na Kiwify não se cadastra nada.** O código do canal é **inventado
no painel do site** e viaja na URL por conta própria; a Kiwify só **grava o que
chegar** e devolve na hora de filtrar.

1. **Criar o código no painel:** Links prontos por canal → link
   **personalizado** → campo **"canal"** (ex.: `maio-ig-stories`). Regras: só
   minúsculas, números, `-` e `_` (o painel limpa qualquer outra coisa), máx.
   40 caracteres. Pode vincular a uma oferta específica — aí o link ganha
   `&o=CHAVE-OFERTA` (e o código secreto da oferta fica visível na URL
   compartilhada; é esse o papel do link).
2. **O link público gerado:** `https://maricoachfit.com/?src=maio-ig-stories`
   (opcionalmente com `&o=...`). Divulgue **esse**.
3. **O que acontece na visita:** o `rastreio.php` loga o clique **por esse
   código** e, no momento de ir ao checkout, anexa `?src=maio-ig-stories` na
   URL da Kiwify — o comprador nem percebe.
4. **Onde ler a venda na Kiwify pelo código:**
   - **Vendas → Filtros → Parâmetros da URL** → digite o valor (ex.:
     `maio-ig-stories`);
   - ou o valor direto na **barra de busca** comum do menu Vendas;
   - pedido por pedido: abra o pedido → aba **Cliente** → mostra os parâmetros
     de rastreamento daquele pedido.
5. **Espelho no site:** o mesmo código aparece no relatório de **cliques por
   canal** do painel (com total). Divisão clara: **cliques** = painel do site;
   **vendas** = Kiwify; a chave que cruza os dois é o mesmo código `src`.
6. **Regras práticas para o código:** um código **por ação/campanha** (a bio da
   IG esta semana ≠ o post do TikTok de amanhã; reaproveitar código destrói a
   leitura). Nome curto e autoexplicativo: `ig-bio`, `whats-status-0612`,
   `tiktok-0612`.

## Parte 5 — Checklist antes de divulgar

1. Abra `https://maricoachfit.com/?o=CHAVE` numa janela **anônima**: só a
   oferta do código pode aparecer no "Ver código-fonte".
2. Abra **sem código**: cai na oferta `padrao`.
3. Clique em "Começar agora" e **confira o preço no checkout da Kiwify** = o
   `preco` do painel. É o erro mais caro do projeto (pessoa vê um preço, paga
   outro).
4. Confira o `?src=` no checkout (URL ou depois do pedido na aba Cliente).
5. Links antigos com `?oferta=CODIGO` continuam funcionando (alias).

## Teste de compra (sem queimar a verba)

- A Kiwify **não documenta** modo sandbox público. Se a sua conta tiver um
  interruptor "Modo de teste" no dashboard, use-o; senão, o teste prático é
  **compra real de valor baixo** e depois pedir reembolso no chat da Kiwify.
- Lembre que Pix e boleto são liberados com ~2 dias; para o teste, preferir
  cartão (aprova na hora).

## Erros que o painel devolve (validação)

| Mensagem do painel | Causa |
|---|---|
| `Informe um codigo para o carrinho.` | `chave` vazia (ou só caracteres proibidos — o painel limpa o que não é `[a-z0-9_-]`) |
| `Codigo muito longo (maximo 24 caracteres).` | `chave` > 24 |
| `Informe um nome para identificar o carrinho.` | `rotulo` vazio |
| `O checkout precisa ser uma URL completa com https://` | campo `checkout` vazio, não-URL ou sem `https://` |
| `Preco "de" precisa ser maior que zero.` | `cheio` = 0 |
| `Preco "por" precisa ser maior que zero.` | `preco` = 0 |
| `O preco "por" nao pode ser maior que o preco "de".` | `preco` > `cheio` |
| `Parcelas deve ser entre 1 e 24.` | `parcN` fora da faixa |
| `Valor da parcela invalido (use 10,69).` | `parcV` sem centavos no formato BR (`10,69` ou `10.69`) |

## Armadilhas do projeto (histórico pago)

- **1 checkout por preço** — nunca reaproveitar um link de checkout para outro
  preço: a pessoa vê um valor e a Kiwify cobra outro.
- A chave do carrinho é **secreta e aleatória** — é ela que esconde a oferta;
  códigos óbvios (`vip`, `promo`) são adivinháveis.
- O que muda o **texto** é o painel; o que muda o **cobrado** é a oferta na
  Kiwify. Os dois precisam ser conferidos juntos em toda alteração.
- Se criar carrinho no **base versionado** (`ofertas.php`) com a mesma chave
  já existente no JSON, o **JSON vence** (painel sobrepondo base).
- O `total` é só âncora visual: se mexer na lista de bônus do `index.php`,
  recalcular `total` de **todos** os carrinhos.
