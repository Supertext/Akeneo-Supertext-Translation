# Changelog

All notable changes to Supertext Translation for Akeneo PIM.

## Unreleased

### Added
- French and Italian interface (and German where it was missing): the dialog, System → Supertext and all messages from the server (Supertext errors, validation, permissions) follow the user's UI locale.
- First version for Akeneo PIM Community Edition 2026 (tested with 2026.4).
- *Translate with Supertext* in the "…" menu of the product and product model edit forms: translate from one locale into the others, with a preview per locale ("values to translate", "has its own text") and an explicit *Overwrite existing translations* option with a warning.
- Translates localizable text and text area attributes, rich text with its formatting, every channel's value of scopable attributes into that channel's locales, and only a variant's own values.
- Saves through Akeneo's updater, validator and saver (history, completeness and search index stay right); a locale whose translation Akeneo rejects is left unchanged.
- System → Supertext: API key (with or without the `Supertext-Auth-Key` prefix; the page links to the Supertext signup and the API key page, *supertext.com → Integrations → API*, Admin role required), Live/Staging/Testing API or a custom address, timeout, Supertext language code and form of address per locale, *Test connection*. `SUPERTEXT_API_KEY` and `SUPERTEXT_API_URL` take precedence.
- Uses Akeneo's permissions: *Edit attributes of a product / product model* to translate, *System configuration* for the settings.
- Console commands `supertext:translate` and `supertext:check`.
- The installed version on System → Supertext (linked to its GitHub release) and in `supertext:check`.
- Retries when the Supertext API answers HTTP 429 (rate limit).
- English and German interface.
- Demo for Railway (`demo/`) with demo accounts, four locales, a sample product and a sample product model created on every start.
