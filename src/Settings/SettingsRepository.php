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
 * (code "supertext_translation"), so the bundle needs no migration of its own.
 */
final class SettingsRepository
{
    public const CODE = 'supertext_translation';

    public function __construct(private readonly Connection $connection)
    {
    }

    /** @return array<string, mixed> */
    public function load(): array
    {
        $json = $this->connection->fetchOne('SELECT `values` FROM pim_configuration WHERE code = ?', [self::CODE]);
        $data = \is_string($json) ? json_decode($json, true) : null;

        return \is_array($data) ? $data : [];
    }

    /** @param array<string, mixed> $values */
    public function save(array $values): void
    {
        $this->connection->executeStatement(
            'INSERT INTO pim_configuration (`code`, `values`) VALUES (:code, :values) ON DUPLICATE KEY UPDATE `values` = :values',
            ['code' => self::CODE, 'values' => json_encode($values, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
        );
    }
}
