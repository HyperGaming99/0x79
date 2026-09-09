#!/bin/sh
set -eu

# The built-in PHP web server is single-threaded by default: one slow request
# (e.g. a Supabase round-trip on redirect) would block every other visitor.
# Workers keep concurrent requests from piling up behind each other.
: "${PHP_CLI_SERVER_WORKERS:=8}"
export PHP_CLI_SERVER_WORKERS

exec php -S 0.0.0.0:8080 index.php
