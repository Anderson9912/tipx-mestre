FROM php:8.2-cli

WORKDIR /app

COPY mestre_sintetico.php .
COPY health.html .

# Mudamos para porta 8000 (a que o Koyeb espera)
CMD php -S 0.0.0.0:8000 -t /app > /dev/null 2>&1 & php .php
