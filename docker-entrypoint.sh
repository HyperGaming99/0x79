#!/bin/sh
set -eu

php /app/discord-worker.php &
exec php -S 0.0.0.0:8080 index.php
