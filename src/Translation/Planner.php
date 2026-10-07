<?php

declare(strict_types=1);

/**
 * @package     Supertext Translation for Akeneo PIM
 * @copyright   (C) Supertext AG
 * @license     MIT
 */

namespace Supertext\AkeneoTranslationBundle\Translation;

/**
 * Decides which source values are translated into a target locale. No Akeneo dependencies.
 *
 * - A scopable value goes only into a locale its channel has; a value for all channels into
 *   any locale that one of the channels has.
 * - A locale-specific attribute only into the locales it is available in.
 * - A target value that already has text is kept unless $overwrite is set.
 */
final class Planner
{
    /**
     * @param list<TextUnit>                                             $units
     * @param array<string, list<string>>                                $channelLocales channel code => locale codes
     * @param callable(string $attribute, ?string $scope, string $locale): bool $hasTargetText
     *
     * @return array{translate: list<TextUnit>, existing: int, unavailable: int}
     */
    public static function plan(array $units, string $target, array $channelLocales, callable $hasTargetText, bool $overwrite): array
    {
        $allLocales = array_values(array_unique(array_merge([], ...array_values($channelLocales))));
        $result     = ['translate' => [], 'existing' => 0, 'unavailable' => 0];

        foreach ($units as $unit) {
            $locales = $unit->scope === null ? $allLocales : ($channelLocales[$unit->scope] ?? []);

            if (!\in_array($target, $locales, true) || ($unit->availableLocales !== [] && !\in_array($target, $unit->availableLocales, true))) {
                $result['unavailable']++;

                continue;
            }

            if (!$overwrite && $hasTargetText($unit->attribute, $unit->scope, $target)) {
                $result['existing']++;

                continue;
            }

            $result['translate'][] = $unit;
        }

        return $result;
    }

    /**
     * Whether a stored value counts as "has text": non-empty after removing tags and whitespace
     * (an empty rich-text editor saves "<p></p>").
     */
    public static function hasText(mixed $data): bool
    {
        if (!\is_string($data)) {
            return false;
        }

        $text = html_entity_decode(strip_tags($data), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(str_replace("\u{A0}", ' ', $text)) !== '';
    }
}
