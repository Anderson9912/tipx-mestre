FROM php:8.2-cli

WORKDIR /app

# Copiar os arquivos
COPY mestre_sintetico.php .
COPY health.html .

# Inicia o servidor web na porta 8080 para health check
# E executa o script principal em background
CMD php -S 0.0.0.0:8080 -t /app > /dev/null 2>&1 & php mestre_sintetico.php
