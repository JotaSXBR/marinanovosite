#!/usr/bin/env python3
"""Sobe a pasta `deploy/` na hospedagem por FTPS, sem passar pela Action.

POR QUE ISTO EXISTE: a Action `deploy-cpanel.yml` faz exatamente a mesma
montagem e usa a mesma biblioteca (lftp/FTP-Deploy-Action). Ela é o caminho
normal. Este script existe para quando o GitHub Actions não está disponível —
os créditos da conta acabaram em 14/10/2026 e o deploy ficou travado.

NÃO É MENOS SEGURO: TLS obrigatório (`AUTH TLS` + `PROT P`, ou seja, a senha
nunca vai em texto claro) e nenhum segredo fica no repositório — as credenciais
vem do ambiente.

USO (nunca com a senha no comando, que ela ficaria no histórico do shell):

    export FTP_SERVER=ftp.maricoachfit.com
    export FTP_USERNAME=deploy@maricoachfit.com
    read -rs -p "senha FTP: " FTP_PASSWORD; export FTP_PASSWORD; echo

    ./ferramentas/deploy-ftp.py

O QUE ELE NÃO FAZ (de propósito):
  - não apaga nada do servidor. Arquivo que existe lá e não existe aqui fica.
    Remover exige decisão consciente, não efeito colateral de deploy.
  - não sobe para `/nova/`. O login cai na raiz do site (`public_html`), que é o
    destino atual. `/nova/` é cópia congelada e não é alvo de deploy.
"""

import ftplib
import os
import posixpath
import sys

# Itens do servidor que jamais devem ser tocados por um deploy.
IGNORAR = {
    "nova",                  # cópia congelada, não é alvo
    ".well-known",
    "cpanel-state-2026-10.txt",   # estado incremental da Action
    "cpanel-state.txt",
    "error_log",
    ".ftpquota",
    ".user.ini",
    "cgi-bin.zip",           # backup do WordPress, só na máquina local
}

LOCAL = os.path.join(os.path.dirname(os.path.abspath(__file__)), os.pardir, "deploy")


def falha(msg):
    print("ERRO: %s" % msg, file=sys.stderr)
    sys.exit(1)


def conexao():
    server = os.environ.get("FTP_SERVER")
    usuario = os.environ.get("FTP_USERNAME")
    senha = os.environ.get("FTP_PASSWORD")
    if not (server and usuario and senha):
        falha("defina FTP_SERVER, FTP_USERNAME e FTP_PASSWORD no ambiente")

    f = ftplib.FTP_TLS(server, timeout=120)
    f.connect()
    f.auth()          # AUTH TLS
    f.prot_p()        # canal privado: a senha não trafega em claro
    f.login(usuario, senha)
    print("conectado em %s | TLS %s | cwd %s" % (server, f.sock.cipher()[0], f.pwd()))
    return f


def garantir_dir(f, caminho):
    """Cria os diretorios do caminho, um por um (FTP nao tem mkdir -p)."""
    atual = ""
    for parte in caminho.strip("/").split("/"):
        novo = posixpath.join(atual, parte) if atual else parte
        try:
            f.cwd("/" + novo)
        except ftplib.error_perm:
            f.mkd(novo)
            f.cwd("/" + novo)
        atual = novo
    f.cwd("/")


def arquivos():
    raiz = os.path.abspath(LOCAL)
    if not os.path.isdir(raiz):
        falha("a pasta %s nao existe; monte o pacote antes (ver README, secao Deploy)" % raiz)
    achados = []
    for base, _, nomes in os.walk(raiz):
        rel = os.path.relpath(base, raiz)
        for nome in sorted(nomes):
            remoto = nome if rel == "." else posixpath.join(rel.replace(os.sep, "/"), nome)
            if remoto in IGNORAR or remoto.split("/")[0] in IGNORAR:
                continue
            achados.append((remoto, os.path.join(base, nome)))
    return sorted(achados)


def main():
    f = conexao()
    lista = arquivos()
    total = sum(os.path.getsize(local) for _, local in lista)
    print("a enviar: %d arquivos (%.1f MB)" % (len(lista), total / 1048576))

    enviados = iguais = falhas = 0
    problemas = []
    for remoto, local in lista:
        pasta = posixpath.dirname(remoto)
        try:
            if pasta:
                garantir_dir(f, pasta)
            if f.size(remoto) == os.path.getsize(local):
                iguais += 1          # mesmo tamanho = ja esta no ar
                continue
            with open(local, "rb") as fh:
                f.storbinary("STOR " + remoto, fh)
            enviados += 1
        except Exception as e:        # noqa: BLE001 - um arquivo ruim nao derruba o resto
            falhas += 1
            problemas.append("%s: %s" % (remoto, e))

    f.quit()
    print("enviados: %d | ja no ar (mesmo tamanho): %d | falhas: %d"
          % (enviados, iguais, falhas))
    for p in problemas[:10]:
        print("  ERRO:", p)
    sys.exit(1 if falhas else 0)


if __name__ == "__main__":
    main()