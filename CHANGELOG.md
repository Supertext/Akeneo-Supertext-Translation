# Changelog

All notable changes to Supertext Translation for Akeneo PIM.

## Unreleased

### Changed
- Talks to Supertext through the [Supertext SDK for PHP](https://github.com/Supertext/Supertext-API-SDK-and-Guide) (bundled in `lib/supertext-sdk/` until it is on Packagist) instead of the bundle's own client. Same behaviour; error messages about the API key now include the links to the Supertext signup and API key pages, and an unsupported language pair gets its own message naming both codes.

### Fixed
- A target language Supertext doesn't know is now reported as an unsupported language pair, naming both codes (was: not found).
- A Supertext language code with a variant under System → Supertext → Languages (e.g. `de_DE_formal`) is sent to Supertext as `de-DE`.

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

### Fixed
- A *Supertext language code* without a region (e.g. `de`) is now sent as `de-DE` (`fr` → `fr-FR`, `en` → `en-US`, …) and codes such as `de-ch` are normalized to `de-CH`; Supertext rejects target languages without a region. The source language is sent without region, as before.
