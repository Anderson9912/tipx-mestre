FROM php:8.2-cli

WORKDIR /app

COPY mestre_sintetico.php .
COPY health.html .

CMD php -S 0.0.0.0:8000 -t /app > /dev/null 2>&1 & php mestre_sintetico.php
