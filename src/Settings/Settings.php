<?php

declare(strict_types=1);

/**
 * @package     Supertext Translation for Akeneo PIM
 * @copyright   (C) Supertext AG
 * @license     MIT
 */

namespace Supertext\AkeneoTranslationBundle\Settings;

use Supertext\AkeneoTranslationBundle\Api\CurlTransport;
use Supertext\AkeneoTranslationBundle\Api\SupertextClient;

/**
 * The bundle's settings: what an administrator saved under System → Supertext, with the
 * environment variables SUPERTEXT_API_KEY and SUPERTEXT_API_URL taking precedence.
 */
final class Settings
{
    public const SIGNUP_URL = 'https://www.supertext.com/person/en/account/signin';
    public const API_KEY_URL = 'https://www.supertext.com/en/integrations/api';

    public const PACKAGE = 'supertext/akeneo-supertext-translation';
    public const REPOSITORY_URL = 'https://github.com/Supertext/Akeneo-Supertext-Translation';

    public const ENVIRONMENTS = ['live', 'staging', 'testing', 'custom'];
    public const DEFAULT_TIMEOUT = 180;

    /** @var array<string, mixed>|null */
    private ?array $stored = null;

    public function __construct(private readonly SettingsRepository $repository)
    {
    }

    /** The installed version as Composer knows it (from the Git tag), e.g. "0.1.0" or "dev-main". */
    public static function version(): string
    {
        try {
            $version = class_exists(\Composer\InstalledVersions::class) && \Composer\InstalledVersions::isInstalled(self::PACKAGE)
                ? (string) \Composer\InstalledVersions::getPrettyVersion(self::PACKAGE)
                : '';
        } catch (\Throwable) {
            $version = '';
        }

        return ltrim($version, 'v');
    }

    /** The GitHub release page of an X.Y.Z version, else "". */
    public static function releaseUrl(string $version): string
    {
        return preg_match('/^\d+\.\d+\.\d+$/', $version) ? self::REPOSITORY_URL . '/releases/tag/v' . $version : '';
    }

    public function apiKey(): string
    {
        $fromEnvironment = self::environmentValue('SUPERTEXT_API_KEY');

        return SupertextClient::normalizeKey($fromEnvironment !== '' ? $fromEnvironment : (string) ($this->stored()['api_key'] ?? ''));
    }

    /** "environment", "settings" or "" (no key). */
    public function apiKeySource(): string
    {
        if (SupertextClient::normalizeKey(self::environmentValue('SUPERTEXT_API_KEY')) !== '') {
            return 'environment';
        }

        return $this->apiKey() !== '' ? 'settings' : '';
    }

    public function environment(): string
    {
        $value = (string) ($this->stored()['environment'] ?? 'live');

        return \in_array($value, self::ENVIRONMENTS, true) ? $value : 'live';
    }

    public function customUrl(): string
    {
        return trim((string) ($this->stored()['api_url'] ?? ''));
    }

    public function baseUrl(): string
    {
        $fromEnvironment = self::environmentValue('SUPERTEXT_API_URL');

        if ($fromEnvironment !== '') {
            return $fromEnvironment;
        }

        return SupertextClient::baseUrlFor($this->environment(), $this->environment() === 'custom' ? $this->customUrl() : '');
    }

    public function baseUrlFromEnvironment(): bool
    {
        return self::environmentValue('SUPERTEXT_API_URL') !== '';
    }

    public function timeout(): int
    {
        $value = (int) ($this->stored()['timeout'] ?? self::DEFAULT_TIMEOUT);

        return $value >= 30 && $value <= 1800 ? $value : self::DEFAULT_TIMEOUT;
    }

    /** Supertext language for an Akeneo locale: the configured code, else BCP-47 (de_CH -> de-CH). */
    public function languageCode(string $locale): string
    {
        $code = trim((string) ($this->languages()[$locale]['code'] ?? ''));

        return $code !== '' ? $code : self::defaultLanguageCode($locale);
    }

    public static function defaultLanguageCode(string $locale): string
    {
        return str_replace('_', '-', $locale);
    }

    /** "more" (formal), "less" (informal) or "" (Supertext's default). */
    public function politeness(string $locale): string
    {
        $value = (string) ($this->languages()[$locale]['politeness'] ?? '');

        return \in_array($value, ['more', 'less'], true) ? $value : '';
    }

    /** @return array<string, array{code?: string, politeness?: string}> */
    public function languages(): array
    {
        $languages = $this->stored()['languages'] ?? [];

        return \is_array($languages) ? $languages : [];
    }

    public function client(): SupertextClient
    {
        return new SupertextClient($this->apiKey(), $this->baseUrl(), new CurlTransport(), $this->timeout());
    }

    /**
     * Saves what the settings page sent. An empty api_key keeps the saved key; clear_api_key removes it.
     *
     * @param array<string, mixed> $input
     *
     * @return list<array{key: string, params: array<string, string>, message: string}> validation errors
     *         (English message, plus the UI key `supertext_translation.error.<key>`; nothing is saved when there are any)
     */
    public function update(array $input): array
    {
        $values = $this->stored();
        $errors = [];

        if (($input['clear_api_key'] ?? false) === true) {
            unset($values['api_key']);
        } elseif (\is_string($input['api_key'] ?? null) && trim($input['api_key']) !== '') {
            $values['api_key'] = SupertextClient::normalizeKey($input['api_key']);
        }

        if (\array_key_exists('environment', $input)) {
            if (!\in_array($input['environment'], self::ENVIRONMENTS, true)) {
                $errors[] = ['key' => 'unknown_environment', 'params' => [], 'message' => 'Unknown API environment.'];
            } else {
                $values['environment'] = $input['environment'];
            }
        }

        if (\array_key_exists('api_url', $input)) {
            $url = trim((string) $input['api_url']);

            if ($url !== '' && !preg_match('#^https?://[^\s]+$#i', $url)) {
                $errors[] = ['key' => 'invalid_url', 'params' => [], 'message' => 'The API address must start with https://.'];
            }

            $values['api_url'] = $url;
        }

        if (($values['environment'] ?? 'live') === 'custom' && trim((string) ($values['api_url'] ?? '')) === '') {
            $errors[] = ['key' => 'missing_url', 'params' => [], 'message' => 'Enter the API address for a custom environment.'];
        }

        if (\array_key_exists('timeout', $input)) {
            $timeout = (int) $input['timeout'];

            if ($timeout < 30 || $timeout > 1800) {
                $errors[] = ['key' => 'invalid_timeout', 'params' => [], 'message' => 'The timeout must be between 30 and 1800 seconds.'];
            } else {
                $values['timeout'] = $timeout;
            }
        }

        if (\is_array($input['languages'] ?? null)) {
            $languages = [];

            foreach ($input['languages'] as $locale => $language) {
                if (!\is_string($locale) || !preg_match('/^[a-z]{2,3}(_[A-Za-z0-9]+)*$/', $locale) || !\is_array($language)) {
                    continue;
                }

                $code       = trim((string) ($language['code'] ?? ''));
                $politeness = (string) ($language['politeness'] ?? '');

                if ($code !== '' && !preg_match('/^[A-Za-z]{2,3}(-[A-Za-z0-9]{2,8})*$/', $code)) {
                    $errors[] = ['key' => 'invalid_code', 'params' => ['code' => $code, 'locale' => $locale], 'message' => sprintf('"%s" is not a valid language code for %s (example: de-CH).', $code, $locale)];

                    continue;
                }

                $entry = [];

                if ($code !== '' && $code !== self::defaultLanguageCode($locale)) {
                    $entry['code'] = $code;
                }

                if (\in_array($politeness, ['more', 'less'], true)) {
                    $entry['politeness'] = $politeness;
                }

                if ($entry !== []) {
                    $languages[$locale] = $entry;
                }
            }

            $values['languages'] = $languages;
        }

        if ($errors !== []) {
            return $errors;
        }

        $this->repository->save($values);
        $this->stored = $values;

        return [];
    }

    /** @return array<string, mixed> */
    private function stored(): array
    {
        return $this->stored ??= $this->repository->load();
    }

    private static function environmentValue(string $name): string
    {
        $value = $_SERVER[$name] ?? $_ENV[$name] ?? getenv($name);

        return \is_string($value) ? trim($value) : '';
    }
}
