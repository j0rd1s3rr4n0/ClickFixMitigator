#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"

PHP_BIN="${PHP_BIN:-php}"
DOMAIN="clickfix.jordiserrano.me"
BASE_PATH="/"
WEB_USER="www-data"
WEB_GROUP="www-data"
TIER="premium"
RPM="600"
SKIP_CHOWN="0"

usage() {
  cat <<'USAGE'
Usage:
  bash scripts/server_install.sh [options]

Options:
  --domain <domain>         Domain used by extension endpoints (default: clickfix.jordiserrano.me)
  --path <base_path>        Base path for web deployment (default: /)
  --web-user <user>         Apache/PHP user for ownership (default: www-data)
  --web-group <group>       Apache/PHP group for ownership (default: www-data)
  --tier <tier>             License tier for generated key (default: premium)
  --rpm <number>            Max requests per minute (default: 600)
  --php <binary>            PHP binary path (default: php from PATH)
  --skip-chown              Do not run chown
  --help                    Show this help
USAGE
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --domain) DOMAIN="${2:-}"; shift 2 ;;
    --path) BASE_PATH="${2:-}"; shift 2 ;;
    --web-user) WEB_USER="${2:-}"; shift 2 ;;
    --web-group) WEB_GROUP="${2:-}"; shift 2 ;;
    --tier) TIER="${2:-}"; shift 2 ;;
    --rpm) RPM="${2:-}"; shift 2 ;;
    --php) PHP_BIN="${2:-}"; shift 2 ;;
    --skip-chown) SKIP_CHOWN="1"; shift ;;
    --help|-h) usage; exit 0 ;;
    *) echo "[error] Unknown option: $1" >&2; usage; exit 1 ;;
  esac
done

cd "${APP_DIR}"

echo "[1/8] Checking PHP runtime"
"${PHP_BIN}" -v >/dev/null
"${PHP_BIN}" -r 'foreach(["pdo_sqlite","openssl","json"] as $e){if(!extension_loaded($e)){fwrite(STDERR,"Missing PHP extension: ".$e.PHP_EOL);exit(1);}}'

echo "[2/8] Preparing directories and list files"
mkdir -p data data/backups data/sessions data/logs keys
touch clickfixlist clickfixallowlist alertsites
if [[ ! -f data/intel-cache.json ]]; then
  printf '{}\n' > data/intel-cache.json
fi

echo "[3/8] Generating/refreshing keys and .env.security"
"${PHP_BIN}" scripts/generate_keys.php \
  --domain="${DOMAIN}" \
  --path="${BASE_PATH}" \
  --tier="${TIER}" \
  --rpm="${RPM}" \
  --no-embed-license

echo "[4/8] Running database migration"
"${PHP_BIN}" scripts/migrate.php

echo "[5/8] Applying file permissions"
chmod 640 .env.security keys/clickfix_sign_private.pem 2>/dev/null || true
chmod 644 keys/clickfix_sign_public.pem clickfixlist clickfixallowlist alertsites 2>/dev/null || true
find data -type d -exec chmod 775 {} + 2>/dev/null || true
find data -type f -name '*.sqlite' -exec chmod 664 {} + 2>/dev/null || true
find data -type f -name '*.log' -exec chmod 664 {} + 2>/dev/null || true
chmod 664 clickfix.sqlite 2>/dev/null || true

if [[ "${SKIP_CHOWN}" == "0" ]]; then
  echo "[6/8] Applying ownership to ${WEB_USER}:${WEB_GROUP}"
  CHOWN_TARGETS=(data keys clickfixlist clickfixallowlist alertsites .env.security)
  if [[ -e clickfix.sqlite ]]; then
    CHOWN_TARGETS+=(clickfix.sqlite)
  fi
  if chown -R "${WEB_USER}:${WEB_GROUP}" "${CHOWN_TARGETS[@]}" 2>/dev/null; then
    echo "[ok] Ownership updated"
  else
    echo "[warn] Could not chown automatically. Run with sudo or use --skip-chown."
  fi
else
  echo "[6/8] Skipping chown (--skip-chown)"
fi

echo "[7/8] Running strict preflight checks"
"${PHP_BIN}" scripts/preflight.php --strict

FINAL_PATH="${BASE_PATH%/}"
if [[ -z "${FINAL_PATH}" ]]; then
  FINAL_PATH="/"
fi
echo "[8/8] Done"
echo "[ok] Deployment prepared for https://${DOMAIN}${FINAL_PATH}"
