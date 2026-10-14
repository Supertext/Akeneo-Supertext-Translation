<?php

declare(strict_types=1);

namespace Supertext\AkeneoTranslationBundle\Tests\Support;

use Supertext\AkeneoTranslationBundle\Settings\SettingsStorage;

/** Settings storage in memory (instead of Akeneo's pim_configuration table). */
final class MemoryStorage implements SettingsStorage
{
    /** @param array<string, mixed> $values */
    public function __construct(public array $values = [], public ?string $reporterState = null, public bool $broken = false)
    {
    }

    public function load(): array
    {
        return $this->values;
    }

    public function save(array $values): void
    {
        $this->values = $values;
    }

    public function loadReporterState(): ?string
    {
        if ($this->broken) {
            throw new \RuntimeException('database down');
        }

        return $this->reporterState;
    }

    public function saveReporterState(string $json): void
    {
        if ($this->broken) {
            throw new \RuntimeException('database down');
        }

        $this->reporterState = $json;
    }
}
