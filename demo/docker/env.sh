# Sourced by the entrypoint and by `demo-console`: Akeneo's settings from the container variables
# (see demo/.env.example). Prints nothing.

# Database: MYSQL_URL (Railway's MySQL service) or DATABASE_URL, own database AKENEO_DB_NAME.
DB_SOURCE="${DATABASE_URL:-${MYSQL_URL:-}}"
if [ -z "$DB_SOURCE" ]; then echo "[demo] Set MYSQL_URL or DATABASE_URL (see demo/.env.example)." >&2; exit 1; fi
eval "$(DB_SOURCE="$DB_SOURCE" php -r '
  $u = parse_url(getenv("DB_SOURCE"));
  $name = getenv("AKENEO_DB_NAME") ?: "akeneo";
  if (!preg_match("/^[A-Za-z0-9_]+$/", $name)) { fwrite(STDERR, "[demo] AKENEO_DB_NAME may only contain letters, digits and _\n"); exit(1); }
  foreach (["HOST" => $u["host"] ?? "127.0.0.1", "PORT" => (string) ($u["port"] ?? 3306), "USER" => urldecode($u["user"] ?? "root"),
            "PASSWORD" => urldecode($u["pass"] ?? ""), "NAME" => $name] as $k => $v) {
    echo "export APP_DATABASE_$k=" . escapeshellarg($v) . "\n";
  }
')"
unset DB_SOURCE

export APP_INDEX_HOSTS="${APP_INDEX_HOSTS:-${ELASTICSEARCH_HOST:-elasticsearch.railway.internal:9200}}"
PUBLIC_URL="${PUBLIC_URL:-${RAILWAY_PUBLIC_DOMAIN:+https://${RAILWAY_PUBLIC_DOMAIN}}}"
export AKENEO_PIM_URL="${PUBLIC_URL:-http://localhost:${PORT:-8080}}"
export APP_ENV=prod APP_DEBUG=0
