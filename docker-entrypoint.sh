#!/bin/sh
set -eu

php /app/discord-worker.php &
php /app/discord-socket.php &
exec php -S 0.0.0.0:8080 index.php
