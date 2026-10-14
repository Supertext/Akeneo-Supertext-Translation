<?php

declare(strict_types=1);

/**
 * @package     Supertext Translation for Akeneo PIM
 * @copyright   (C) Supertext AG
 * @license     MIT
 */

namespace Supertext\AkeneoTranslationBundle\Settings;

/**
 * Where the settings and the Commando Center reporter's state are kept (SettingsRepository:
 * Akeneo's pim_configuration table; the unit tests use an in-memory one).
 */
interface SettingsStorage
{
    /** @return array<string, mixed> */
    public function load(): array;

    /** @param array<string, mixed> $values */
    public function save(array $values): void;

    /** The reporter's state as the JSON it was saved as, or null. */
    public function loadReporterState(): ?string;

    public function saveReporterState(string $json): void;
}
