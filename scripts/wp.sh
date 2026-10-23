#!/bin/sh
# Scorciatoia per WP-CLI dentro Docker.
#   ./scripts/wp.sh setup          prima installazione (WordPress, WooCommerce, tema, plugin)
#   ./scripts/wp.sh plugin list    qualsiasi altro comando wp
set -eu
cd "$(dirname "$0")/.."

if [ "${1:-}" = "setup" ]; then
  exec docker compose run --rm -T -v "$PWD/scripts/setup.sh:/setup.sh:ro" wpcli sh /setup.sh
fi
exec docker compose run --rm wpcli wp "$@"
