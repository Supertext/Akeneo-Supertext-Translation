#!/bin/bash
# Runs bin/console as www-data with the demo's settings, e.g.:
#   docker exec demo demo-console supertext:translate praline-box-16 --from=en_US
set -euo pipefail
source /usr/local/lib/demo-env.sh
cd /srv/pim
exec runuser -u www-data -- env DEMO_ADMIN_EMAIL="${DEMO_ADMIN_EMAIL:-}" DEMO_ADMIN_PASSWORD="${DEMO_ADMIN_PASSWORD:-}" \
  DEMO_EDITOR_EMAIL="${DEMO_EDITOR_EMAIL:-}" DEMO_EDITOR_PASSWORD="${DEMO_EDITOR_PASSWORD:-}" php -d memory_limit=2G bin/console "$@"
