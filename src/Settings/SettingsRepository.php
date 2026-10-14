<?php

declare(strict_types=1);

/**
 * @package     Supertext Translation for Akeneo PIM
 * @copyright   (C) Supertext AG
 * @license     MIT
 */

namespace Supertext\AkeneoTranslationBundle\Settings;

use Doctrine\DBAL\Connection;

/**
 * Stores the settings as one JSON row in Akeneo's own `pim_configuration` table
 * (code "supertext_translation"), so the bundle needs no migration of its own. The Commando
 * Center reporter's state (versions last reported, the latest API key check) is a second row,
 * "supertext_translation_reporter", so saving the settings never overwrites it.
 */
final class SettingsRepository implements SettingsStorage
{
    public const CODE = 'supertext_translation';
    public const REPORTER_CODE = 'supertext_translation_reporter';

    public function __construct(private readonly Connection $connection)
    {
    }

    /** @return array<string, mixed> */
    public function load(): array
    {
        $json = $this->fetch(self::CODE);
        $data = $json !== null ? json_decode($json, true) : null;

        return \is_array($data) ? $data : [];
    }

    /** @param array<string, mixed> $values */
    public function save(array $values): void
    {
        $this->store(self::CODE, json_encode($values, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function loadReporterState(): ?string
    {
        return $this->fetch(self::REPORTER_CODE);
    }

    public function saveReporterState(string $json): void
    {
        $this->store(self::REPORTER_CODE, $json);
    }

    private function fetch(string $code): ?string
    {
        $json = $this->connection->fetchOne('SELECT `values` FROM pim_configuration WHERE code = ?', [$code]);

        return \is_string($json) ? $json : null;
    }

    private function store(string $code, string $json): void
    {
        $this->connection->executeStatement(
            'INSERT INTO pim_configuration (`code`, `values`) VALUES (:code, :values) ON DUPLICATE KEY UPDATE `values` = :values',
            ['code' => $code, 'values' => $json],
        );
    }
}
