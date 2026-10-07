#!/usr/bin/env bash
# CI: starts the demo image twice against MySQL and Elasticsearch with the stand-in API, checks
# the demo accounts (created once, never duplicated, passwords never logged) and translates the
# sample product and product model. Needs: docker image supertext-akeneo-demo, MySQL at
# $MYSQL_URL (mysql client on the host, or MYSQL_CMD), Elasticsearch on 127.0.0.1:9200, the stand-in on 127.0.0.1:8765.
set -euo pipefail
# No pipes into grep -q: with pipefail they can fail on SIGPIPE. Capture the output, then grep a here-string.
PORT=8080
export DEMO_ADMIN_EMAIL=ci-admin@example.com DEMO_ADMIN_PASSWORD="ci-$(openssl rand -hex 8)"
export DEMO_EDITOR_EMAIL=ci-editor@example.com DEMO_EDITOR_PASSWORD="ci-$(openssl rand -hex 8)"
MYSQL_CMD="${MYSQL_CMD:-mysql -h127.0.0.1 -uroot -proot}"
sql() { $MYSQL_CMD --default-character-set=utf8mb4 akeneo -N -s -e "$1" 2>/dev/null; }
console() { docker exec demo demo-console "$@"; }

start() {
	docker rm -f demo >/dev/null 2>&1 || true
	docker run -d --name demo --network host -e PORT=$PORT -e MYSQL_URL="$MYSQL_URL" -e APP_INDEX_HOSTS=127.0.0.1:9200 \
		-e APP_SECRET=ci-secret-ci-secret-ci-secret -e DEMO_ADMIN_EMAIL -e DEMO_ADMIN_PASSWORD -e DEMO_EDITOR_EMAIL -e DEMO_EDITOR_PASSWORD \
		-e SUPERTEXT_API_KEY=anything -e SUPERTEXT_API_URL=http://127.0.0.1:8765/v1/ supertext-akeneo-demo >/dev/null
	for _ in $(seq 240); do curl -sf -o /dev/null http://127.0.0.1:$PORT/user/login && break; sleep 3; done
	curl -sf -o /dev/null http://127.0.0.1:$PORT/user/login
	docker logs demo 2>&1 | grep '\[demo\]' || true
}

start
start   # second start: nothing duplicated or changed
logs=$(docker logs demo 2>&1)
grep -qF 'DEMO_EDITOR: account exists, left unchanged' <<< "$logs"
if grep -qF -e "$DEMO_ADMIN_PASSWORD" -e "$DEMO_EDITOR_PASSWORD" <<< "$logs"; then echo "A password appeared in the log"; exit 1; fi

test "$(sql "select group_concat(email order by email) from oro_user where email like 'ci-%'")" = "ci-admin@example.com,ci-editor@example.com"
role() { sql "select r.role from oro_user u join oro_user_access_role ur on ur.user_id=u.id join oro_access_role r on r.id=ur.role_id where u.email='$1'"; }
grep -qx ROLE_ADMINISTRATOR <<< "$(role ci-admin@example.com)"
grep -qx ROLE_CATALOG_MANAGER <<< "$(role ci-editor@example.com)"
test "$(sql "select count(*) from pim_catalog_product where identifier='praline-box-16'")" = 1

console supertext:check
console supertext:translate praline-box-16 --from=en_US | tee /tmp/translate.log
test "$(grep -c ': translated (3 values)' /tmp/translate.log)" = 3
grep -qF 'fr_CH: translated (3 values)' <<< "$(console supertext:translate dark-chocolate-bar --model --from=en_US --to=fr_CH)"

value() { sql "select json_unquote(json_extract(raw_values, '$.$1.\"$2\".$3')) from $4"; }
test "$(value name '<all_channels>' de_CH "pim_catalog_product where identifier='praline-box-16'")" = "Handgemachte Pralinenschachtel, 16 Stück"
grep -qF '<strong>à la main</strong>' <<< "$(value description ecommerce fr_CH "pim_catalog_product where identifier='praline-box-16'")"
test "$(value name '<all_channels>' fr_CH "pim_catalog_product_model where code='dark-chocolate-bar'")" = "Tablette de chocolat noir, 72 % de cacao"

# A second run keeps the languages that now have their own text.
grep -qF 'skipped (already has text' <<< "$(console supertext:translate praline-box-16 --from=en_US --to=it_CH)"
echo "Demo check passed"
