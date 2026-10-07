<?php

declare(strict_types=1);

/**
 * @package     Supertext Translation for Akeneo PIM
 * @copyright   (C) Supertext AG
 * @license     MIT
 */

namespace Supertext\AkeneoTranslationBundle\Translation;

/**
 * One translatable product value in the source locale: a localizable text or textarea
 * attribute, for one channel (scopable attributes) or for all channels (scope null).
 */
final class TextUnit
{
    /**
     * @param list<string> $availableLocales locale-specific attribute: the only locales it has; empty: all
     */
    public function __construct(
        public readonly string $attribute,
        public readonly ?string $scope,
        public readonly string $text,
        public readonly bool $html,
        public readonly array $availableLocales = [],
        public readonly ?int $maxCharacters = null,
    ) {
    }

    public function key(): string
    {
        return $this->attribute . '|' . ($this->scope ?? '');
    }
}
