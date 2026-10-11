# Ambiente PHP local — espelha o stack do cPanel (Apache + mod_php + .htaccess).
# Docker em vez de apt porque o WSL aqui exige senha de sudo, e assim o
# ambiente da máquina continua intocado.
FROM php:8.2-apache

# mod_rewrite: as regras do .htaccess dependem de mod_authz_core e
# mod_headers; o mod_rewrite cobre o resto dos RewriteRule do projeto.
RUN a2enmod rewrite headers

# O container precisa permitir .htaccess. Sem isso o PHP define
# AllowOverride None e TODAS as proteções de pasta (private/, painel/)
# deixam de existir localmente — o painel local ficaria aberto.
RUN sed -ri 's/AllowOverride None/AllowOverride All/g' \
      /etc/apache2/apache2.conf \
      /etc/apache2/conf-available/*.conf

# ini de desenvolvimento: erro aparece na hora em vez de virar página em branco.
RUN { \
      echo 'display_errors=On'; \
      echo 'error_reporting=E_ALL'; \
      echo 'log_errors=On'; \
    } > /usr/local/etc/php/conf-dev.ini