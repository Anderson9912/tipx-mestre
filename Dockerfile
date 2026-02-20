FROM php:8.2-cli

WORKDIR /app

COPY mestre_sintetico.php .

CMD ["php", "mestre_sintetico.php"]
