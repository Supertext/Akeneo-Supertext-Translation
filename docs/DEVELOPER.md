# Developer guide — Supertext Translation for Akeneo PIM

Architecture, the Supertext API protocol, local development, tests, the demo, screenshots and releases.

## Architecture

A Symfony bundle for Akeneo PIM Community Edition 2026 (Symfony 5.4, PHP 8.3). Composer package `supertext/akeneo-supertext-translation`, namespace `Supertext\AkeneoTranslationBundle`, bundle `SupertextTranslationBundle` (so its assets are published under `public/bundles/supertexttranslation/`).

| Path | Purpose |
| --- | --- |
| `src/Api/SupertextClient.php` | Supertext AI file translation API v1: submit, poll, download, delete; key check; HTTP 429 retries. No Akeneo classes. |
| `src/Api/HtmlDocument.php` | Packs values into one HTML document (`<div data-st-id="N">` per value) and splits the translation apart; chunks below the API's size limit. |
| `src/Api/CurlTransport.php` | HTTP for the client with PHP's curl extension. |
| `src/Settings/Settings.php`, `SettingsRepository.php` | Settings from System → Supertext (one JSON row in `pim_configuration`, code `supertext_translation`), with `SUPERTEXT_API_KEY` and `SUPERTEXT_API_URL` taking precedence. |
| `src/Translation/Planner.php`, `TextUnit.php` | Which source values go into which target locale (channels, locale-specific attributes, existing text). No Akeneo classes. |
| `src/Translation/EntityTranslator.php` | Collects a product's or product model's text values, translates them per target locale and applies them with Akeneo's updater, validator and saver. |
| `src/Controller/TranslateController.php` | Internal API for the dialog: `GET /supertext/rest/{product\|product_model}/{id}` (languages, preview) and `POST …/translate`. |
| `src/Controller/SettingsController.php` | Internal API for System → Supertext: `GET`/`POST /supertext/rest/settings`, `POST …/settings/test`. Never returns the key. |
| `src/Command/TranslateCommand.php`, `CheckCommand.php` | `supertext:translate`, `supertext:check`. |
| `src/Resources/config/services.yml`, `routing.yml` | Services (explicit Akeneo service ids) and routes (exposed to FOSJsRouting for the front end). |
| `src/Resources/config/form_extensions/supertext.yml` | Akeneo form extensions: the entry in the "…" menu (`secondary-actions`) of the product and product model edit forms, and the System menu item. |
| `src/Resources/config/requirejs.yml` | Module aliases and the controller for the route `supertext_translation_settings_index`. |
| `src/Resources/public/js/` | Front end (TypeScript/React, built by Akeneo's webpack): `product/translate-action.tsx` (Backbone view for the menu entry), `product/TranslateModal.tsx`, `settings/` (System → Supertext), `api.ts`. |
| `src/Resources/translations/jsmessages.{en_US,de_DE}.yml` | UI strings (English and German). API and console messages are English. |

How Akeneo picks up the front end: `bin/console pim:installer:assets` copies `Resources/public` to `public/bundles/supertexttranslation/`, dumps the routes and translations; `yarn run webpack` compiles every `.ts`/`.tsx` under `public/bundles` (with Akeneo's `transpileOnly` TypeScript loader); `yarn run update-extensions` merges all bundles' `form_extensions/*.yml` into `public/js/extensions.json`. That's why installing or updating the bundle needs a front-end rebuild.

**Saving:** the translator calls `pim_catalog.updater.product` (or `…product_model`) with standard-format values, validates with `pim_catalog.validator.product` and saves with `pim_catalog.saver.product` (or `…product_model`), the same path Akeneo's imports use. Versioning (History tab), completeness and the Elasticsearch index follow from that. Violations that appear after applying one language put that language's previous values back, so the other languages can still be saved; violations that existed before are ignored.

**Permissions:** translating checks Akeneo's existing ACLs `pim_enrich_product_edit_attributes` / `pim_enrich_product_model_edit_attributes` (in the form extensions and in the controller); the settings check `oro_config_system`. The bundle adds no ACL of its own, because a new ACL is denied to every role until someone grants it.

### Field rules

Keep these in sync with *What is translated* in [USER_GUIDE.md](USER_GUIDE.md#what-is-translated) and `EntityTranslator`:

- Attribute types `pim_catalog_text` and `pim_catalog_textarea`, localizable only. Text areas with the rich-text editor (`wysiwyg_enabled`) go as HTML, all others as plain text (escaped, line breaks as `<br>`).
- Only the entity's own values (`getValuesForVariation()`): a variant's inherited values belong to its product model.
- Each value in the source locale is one `data-st-id` element, so a whole rich text (all its paragraphs and lists) is translated as one unit with its inline formatting.
- Scopable values go into the locales of their channel; non-scopable ones into any activated locale; locale-specific attributes only into their locales (`Planner`).
- A target value with text (after removing tags and whitespace) is kept unless *overwrite* is set.
- Plain-text translations longer than the attribute's *max characters* are skipped with a message; Akeneo's validator catches the rest.

## Supertext API protocol

Shared with the WordPress plugin and every other Supertext plugin:

1. `POST {base}translate/ai/file`: multipart with `file` (part `Content-Type` exactly `text/html`, no charset, or the API answers 415), `target_lang` (BCP-47, e.g. `de-CH`), optional `source_lang` (primary subtag only, e.g. `en`, or the pair is rejected), optional `politeness` (`more`/`less`). Returns `{file_id}`.
2. `GET …/{file_id}/status` until `done` (`error`, `limit_exceeded`, `deleted` are terminal).
3. `GET …/{file_id}/translation` returns the translated HTML.
4. `DELETE …/{file_id}` (files also expire after 24 h).

Auth header: `Authorization: Supertext-Auth-Key <key>`. The header name must be `Authorization` (`Authentication` gets 403). Supertext shows the key with the prefix, so the client strips a pasted `Supertext-Auth-Key ` and always sends exactly one. Base URLs: `https://api.supertext.com/v1/` (live), `https://api.staging.supertext.com/v1/`, `https://api.testing.supertext.com/v1/`. `GET features` is the cost-free key check (*Test connection*, `supertext:check`).

**Rate limit:** the API limits requests per second per key (HTTP 429). The client retries a 429 up to 4 times, waiting for `Retry-After` if sent, otherwise 1, 2, 4 and 8 seconds plus jitter. Target locales are translated one after the other.

Locale codes: an Akeneo locale (`de_CH`) is sent as `de-CH` unless System → Supertext sets another code. The source is sent as its primary subtag (`en`).

## Local development

The quickest setup is the demo image (below), which builds Akeneo with the bundle. To work on the bundle in your own Akeneo project, add this repository as a Composer path repository:

```bash
composer config repositories.supertext '{"type": "path", "url": "../Akeneo-Supertext-Translation", "options": {"symlink": true}}'
composer require supertext/akeneo-supertext-translation:@dev
# register the bundle and the routes (docs/INSTALLATION.md), then:
bin/console pim:installer:assets --symlink --clean && yarn run webpack-watch   # front end
yarn run update-extensions                                                     # after changing form_extensions/*.yml
```

PHP changes need `rm -rf var/cache`; translation changes need `bin/console pim:installer:assets`.

## Tests

```bash
phpunit                                                     # unit tests (src/Api, src/Translation/Planner)
find src tests demo/project/supertext-demo -name '*.php' -print0 | xargs -0 -n1 php -l
```

The unit tests need only PHPUnit 11 (no Akeneo install). `tests/demo-check.sh` is the end-to-end test: it starts the demo image twice against MySQL and Elasticsearch with the stand-in API, checks the demo accounts (created once, passwords never logged), and translates the sample product and product model with `supertext:translate`, checking the stored values.

**CI** (`.github/workflows/ci.yml`): lint and unit tests on every push; the `demo` job builds the demo image (about 20 minutes: Composer, Yarn and Akeneo's webpack build) and runs `tests/demo-check.sh` against MySQL 8.4 and Elasticsearch 8.17 service containers.

## Demo (Railway)

The demo is a container built from `demo/Dockerfile`: PHP 8.3 with Apache, Akeneo PIM Community Edition 2026.4 (minimal catalog) with this bundle, the locales English (`en_US`), German, French and Italian (Switzerland) (`de_CH`, `fr_CH`, `it_CH`) on the *E-commerce* channel, an English sample product and a sample product model with two variants. On Railway it needs three services: the demo (this repository, `railway.json` → `demo/Dockerfile`), MySQL (shared, the demo uses its own database `AKENEO_DB_NAME`) and Elasticsearch 8.

**Deploys:** Railway builds `main` of this repository. If a push doesn't start a deployment, check that Railway's GitHub app has access to the repository, or redeploy the service.

**What's in `demo/`:**

| Path | Purpose |
| --- | --- |
| `Dockerfile` | Stages: PHP 8.3 + Apache and Akeneo's extensions; Composer install of `demo/project/composer.json` (Akeneo 2026.4) and Yarn install of Akeneo's front-end packages; the bundle (Composer path repository), the demo's config and the front-end build; the runtime image. A change to the bundle only repeats the last build stage. |
| `docker/env.sh` | Turns `MYSQL_URL` into Akeneo's `APP_DATABASE_*` (database `AKENEO_DB_NAME`, default `akeneo`) and sets `APP_INDEX_HOSTS`, `AKENEO_PIM_URL` |
| `docker/entrypoint.sh` | Every start: database, Akeneo install with the minimal catalog on the first start, search index rebuild if Elasticsearch lost it, `supertext:demo-setup`, the job queue worker, Apache on `$PORT` |
| `docker/console.sh` | `demo-console …`: `bin/console` as `www-data` with the demo's settings (`docker exec demo demo-console supertext:check`) |
| `project/` | Overlay on the Akeneo standard project: `composer.json`, `config/bundles.php`, `config/routes/supertext_translation.yml`, `config/services/supertext_demo.yml` and `supertext-demo/DemoSetupCommand.php` |
| `.env.example` | The variables below |

**Demo setup** (`supertext:demo-setup`, every start, only adds what is missing): the four locales on the *E-commerce* channel, the attribute group *Marketing* with *Name* (text), *Short description* (text area), *Description* (rich text, per channel) and *Weight* (simple select), the family *Chocolate*, the family variant *Chocolate by weight*, the sample product `praline-box-16`, the product model `dark-chocolate-bar` with the variants `dark-chocolate-bar-100g` and `-400g`, and the demo accounts.

**Service variables:**

| Variable | |
| --- | --- |
| `MYSQL_URL` | `${{MySQL.MYSQL_URL}}`; the database `AKENEO_DB_NAME` (default `akeneo`) is created on that server if missing |
| `APP_INDEX_HOSTS` | Elasticsearch `host:port`, e.g. `${{Elasticsearch.RAILWAY_PRIVATE_DOMAIN}}:9200` |
| `APP_SECRET` | Random secret (keep it stable) |
| `DEMO_ADMIN_EMAIL`, `DEMO_ADMIN_PASSWORD` | Administrator (role *Administrator*, all user groups) |
| `DEMO_EDITOR_EMAIL`, `DEMO_EDITOR_PASSWORD` | Editor for automated tests and screenshots: role *Catalog manager*, group *Redactor*. Akeneo has no "editor" role; *Catalog manager* is the closest built-in one and may edit products and product models in every locale (the Community Edition has no locale permissions). |
| `SUPERTEXT_API_KEY` | Optional: without it, enter the key under System → Supertext |
| `SUPERTEXT_API_URL` | Optional, e.g. a stand-in API |
| `PORT` | Port Apache listens on (Railway sets it) |

**Elasticsearch service:** image `docker.elastic.co/elasticsearch/elasticsearch:8.17.0` with `discovery.type=single-node`, `xpack.security.enabled=false`, `indices.id_field_data.enabled=true` and `ES_JAVA_OPTS=-Xms512m -Xmx512m`. Without a volume the index is lost on restart; the entrypoint then recreates it and re-indexes the products.

**Demo accounts:** on every start the setup creates the `DEMO_ADMIN` and `DEMO_EDITOR` accounts if no account with that e-mail address exists. Existing accounts are never changed (change passwords in Akeneo). Log in with the e-mail address (the username is its part before the `@`). Akeneo requires passwords of 8 to 4096 characters; otherwise the account is skipped with a warning naming the variable, and the demo still starts. Passwords are never logged.

**No installer screen:** Akeneo has no web installer; the entrypoint installs the minimal catalog, which has no users, so the `DEMO_*` accounts are the only ways in.

**Run it locally:**

```bash
docker network create akeneo
docker run -d --name mysql --network akeneo -e MYSQL_ROOT_PASSWORD=root mysql:8.4
docker run -d --name elasticsearch --network akeneo -e discovery.type=single-node -e xpack.security.enabled=false \
  -e indices.id_field_data.enabled=true -e ES_JAVA_OPTS="-Xms512m -Xmx512m" docker.elastic.co/elasticsearch/elasticsearch:8.17.0
docker build -f demo/Dockerfile -t supertext-akeneo-demo .
docker run --rm --name demo --network akeneo -p 8080:8080 \
  -e MYSQL_URL=mysql://root:root@mysql:3306/railway -e APP_INDEX_HOSTS=elasticsearch:9200 -e APP_SECRET=dev \
  -e DEMO_ADMIN_EMAIL=you@example.com -e DEMO_ADMIN_PASSWORD='choose-one' supertext-akeneo-demo
# http://localhost:8080 (the first start installs Akeneo: a few minutes)
```

## Docs screenshots

The images in `docs/images/` are generated by `tests/docs/screenshots.mjs` (Playwright) from a freshly set-up demo (no translations yet) whose bundle talks to `tests/docs/stand-in.mjs`. The stand-in returns German, French and Italian for the sample product and product model (`samples.json`). Regenerate them whenever a screen they show changes:

```bash
cd tests/docs && npm install && npx playwright install chromium
npm run stand-in &
# a fresh demo with SUPERTEXT_API_URL=http://127.0.0.1:8765/v1/, no SUPERTEXT_API_KEY and DEMO_* set, served on :8090
BASE_URL=http://127.0.0.1:8090 DEMO_ADMIN_EMAIL=… DEMO_ADMIN_PASSWORD=… DEMO_EDITOR_EMAIL=… DEMO_EDITOR_PASSWORD=… \
  PRODUCT_UUID=<uuid of praline-box-16> npm run screenshots
```

The script saves a placeholder API key as the administrator first, and shows the live API address instead of the stand-in's on the settings page.

## Releasing

Releases are published by `.github/workflows/release.yml` when the version is officially bumped; nobody tags or creates releases by hand.

1. Move the *Unreleased* entries in `CHANGELOG.md` under a new `## X.Y.Z — YYYY-MM-DD` section, and keep an empty *Unreleased* above it.
2. There is no version field to change: Composer takes the version from the Git tag the workflow creates (don't add `version` to `composer.json`). System → Supertext and `supertext:check` read it at runtime with `Composer\InstalledVersions` (`Settings::version()`) and link X.Y.Z versions to their GitHub release.
3. Push to `main`. The workflow tags `vX.Y.Z` and creates the GitHub release with the CHANGELOG section as notes (0.x versions as pre-releases). A push that adds no new version does nothing, and a version that is already released is skipped. After fixing a failed run, start it again with *Run workflow* on the *Release* workflow.

Planned: submit the package to Packagist.

## Conventions

- PSR-12, PHP 8.3, strict types; keep `src/Api/` and `src/Translation/Planner.php` free of Akeneo classes (the unit tests run without Akeneo).
- New settings: `src/Settings/Settings.php` (`update()` validates), `SettingsController::payload()`, `settings/SettingsPage.tsx`, both `jsmessages` files **and** the settings table in [INSTALLATION.md](INSTALLATION.md#settings).
- UI strings in `jsmessages.en_US.yml` and `jsmessages.de_DE.yml`; quote YAML values that contain a colon followed by a space.
- The bundle name, the route names and the form extension codes are part of users' installations; renaming them is a breaking change.
- Keep the three docs in `docs/` current with every change (see `CLAUDE.md`).

## Known limitations / roadmap

- One product or product model at a time from the edit form, inside the editor's request (one locale after the other, up to *Timeout* each). Bulk translation from the product grid (a mass action running as a background job) is planned; until then, use `supertext:translate` with several identifiers.
- Only text and text area attributes. Not yet: table attributes, attribute option labels, asset and reference entity texts (Enterprise Edition), category and attribute labels.
- Community Edition only; Enterprise Edition's locale and category permissions are not checked yet.
- Not on Packagist yet.
- Human (professional) translation orders are not supported yet (the WordPress plugin has them).
