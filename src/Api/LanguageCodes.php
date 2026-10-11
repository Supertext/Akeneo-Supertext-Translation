<?php

declare(strict_types=1);

/**
 * @package     Supertext Translation for Akeneo PIM
 * @copyright   (C) Supertext AG
 * @license     MIT
 */

namespace Supertext\AkeneoTranslationBundle\Api;

/**
 * CMS locales → Supertext language codes.
 *
 * Supertext wants the source as a bare language ("en") and the target with a
 * region ("de-DE", "en-US"); a bare target such as "de" is rejected with
 * INVALID_LANGUAGE_PAIR. Locales without a region get the default region
 * below (same table as the other Supertext plugins); unknown bare codes are
 * sent unchanged, so Supertext's error names them.
 */
final class LanguageCodes
{
    /** The region Supertext uses when a locale has none. */
    public const DEFAULT_REGION = [
        'ar' => 'ar-SA',
        'bg' => 'bg-BG',
        'cs' => 'cs-CZ',
        'da' => 'da-DK',
        'de' => 'de-DE',
        'el' => 'el-GR',
        'en' => 'en-US',
        'es' => 'es-ES',
        'et' => 'et-EE',
        'fi' => 'fi-FI',
        'fr' => 'fr-FR',
        'hr' => 'hr-HR',
        'hu' => 'hu-HU',
        'it' => 'it-IT',
        'ja' => 'ja-JP',
        'ko' => 'ko-KR',
        'lt' => 'lt-LT',
        'lv' => 'lv-LV',
        'nb' => 'nb-NO',
        'nl' => 'nl-NL',
        'pl' => 'pl-PL',
        'pt' => 'pt-PT',
        'ro' => 'ro-RO',
        'ru' => 'ru-RU',
        'sk' => 'sk-SK',
        'sl' => 'sl-SI',
        'sr' => 'sr-RS',
        'sv' => 'sv-SE',
        'th' => 'th-TH',
        'tr' => 'tr-TR',
        'uk' => 'uk-UA',
        'vi' => 'vi-VN',
        'zh' => 'zh-CN',
    ];

    /** "de_ch" → "de-CH", "DE" → "de", "zh_hant" → "zh-Hant", "es-419" stays. */
    public static function normalize(string $code): string
    {
        $parts = array_values(array_filter(explode('-', str_replace('_', '-', trim($code))), static fn (string $p): bool => $p !== ''));
        if ($parts === []) {
            return '';
        }

        $out = [strtolower(array_shift($parts))];
        foreach ($parts as $part) {
            $out[] = match (\strlen($part)) {
                2       => strtoupper($part),
                4       => ucfirst(strtolower($part)),
                default => $part,
            };
        }

        return implode('-', $out);
    }

    /** Source code to send: the bare language of the override, else of the locale ("en-GB" → "en"). */
    public static function source(string $locale, string $override = ''): string
    {
        $code = self::normalize(trim($override) !== '' ? $override : $locale);

        return explode('-', $code)[0];
    }

    /** Target code to send: the override, else the locale; without a region it gets the default one ("de" → "de-DE"). */
    public static function target(string $locale, string $override = ''): string
    {
        $code = self::normalize(trim($override) !== '' ? $override : $locale);
        if ($code === '' || str_contains($code, '-')) {
            return $code;
        }

        return self::DEFAULT_REGION[$code] ?? $code;
    }
}
