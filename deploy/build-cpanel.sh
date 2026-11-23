#!/usr/bin/env bash
# Builds deploy/dist/margin-api-cpanel.zip: the Laravel API with its
# production PHP packages (vendor/) already installed, ready to upload with
# cPanel File Manager. Needs Docker only; no PHP or Composer on your PC.
#
#   bash deploy/build-cpanel.sh
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
IMAGE="ai-saas-starter-api"
OUT="margin-api-cpanel.zip"

# Docker Desktop on Windows (Git Bash) needs Windows-style paths.
to_docker_path() { if command -v cygpath >/dev/null 2>&1; then cygpath -m "$1"; else echo "$1"; fi; }
export MSYS_NO_PATHCONV=1

if ! docker image inspect "$IMAGE" >/dev/null 2>&1; then
  echo "Building the PHP image first (one time)..."
  (cd "$ROOT" && docker compose build api)
fi

mkdir -p "$ROOT/deploy/dist"

docker run --rm --entrypoint sh \
  -v "$(to_docker_path "$ROOT/backend"):/src:ro" \
  -v "$(to_docker_path "$ROOT/deploy"):/deploy" \
  "$IMAGE" -c "
    set -e
    rm -rf /tmp/app && cp -a /src /tmp/app && cd /tmp/app
    rm -rf vendor .env bootstrap/cache/*.php
    composer install --no-dev --optimize-autoloader --classmap-authoritative --no-interaction --no-progress --prefer-dist
    php /deploy/make-zip.php /tmp/app /deploy/dist/$OUT
  "

echo
echo "Upload deploy/dist/$OUT to your cPanel account and follow DEPLOY.md."
