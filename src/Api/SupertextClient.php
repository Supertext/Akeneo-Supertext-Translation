<?php

declare(strict_types=1);

/**
 * @package     Supertext Translation for Akeneo PIM
 * @copyright   (C) Supertext AG
 * @license     MIT
 */

namespace Supertext\AkeneoTranslationBundle\Api;

use Supertext\Sdk\Http\HttpTransport;
use Supertext\Sdk\V1\Client;
use Supertext\Sdk\V1\Segment;
use Supertext\Sdk\V1\SupertextException as SdkException;

/**
 * The bundle's access to Supertext: a thin layer over the Supertext SDK for PHP
 * (lib/supertext-sdk, https://github.com/Supertext/Supertext-API-SDK-and-Guide), which does the
 * protocol (upload, polling, download, delete), HTML packing and the HTTP 429 retries. Language
 * codes are resolved before (Settings::languageCode(), the SDK's Languages): this class sends
 * them as they are. This class adds the bundle's environments and turns SDK errors into the bundle's
 * SupertextException with a UI message key.
 *
 * Has no Akeneo dependencies (unit-tested with a fake transport).
 */
final class SupertextClient
{
    public const LIVE = 'https://api.supertext.com/v1/';
    public const STAGING = 'https://api.staging.supertext.com/v1/';
    public const TESTING = 'https://api.testing.supertext.com/v1/';

    private readonly string $apiKey;
    private readonly Client $client;

    /**
     * @param int                          $timeout        seconds to wait for one translation
     * @param string                       $userAgent      e.g. "Akeneo-Supertext/1.0.0"
     * @param HttpTransport|null           $transport      the SDK's curl transport by default
     * @param (callable(float): void)|null $sleep          for tests
     */
    public function __construct(
        #[\SensitiveParameter] string $apiKey,
        string $baseUrl,
        int $timeout = 180,
        string $userAgent = '',
        ?HttpTransport $transport = null,
        float $pollInterval = 2.0,
        ?callable $sleep = null,
    ) {
        $this->apiKey = self::normalizeKey($apiKey);
        $this->client = new Client(
            $this->apiKey,
            $baseUrl !== '' ? $baseUrl : self::LIVE,
            pollInterval: $pollInterval,
            timeout: (float) $timeout,
            requestTimeout: 60.0,
            userAgent: $userAgent !== '' ? $userAgent : null,
            transport: $transport,
            sleep: $sleep,
        );
    }

    /** Supertext shows the key as "Supertext-Auth-Key <key>"; accept it with or without that prefix. */
    public static function normalizeKey(#[\SensitiveParameter] string $key): string
    {
        return trim((string) preg_replace('/^\s*Supertext-Auth-Key(?:\s+|$)/i', '', $key));
    }

    public static function baseUrlFor(string $environment, string $customEndpoint = ''): string
    {
        if (trim($customEndpoint) !== '') {
            return trim($customEndpoint);
        }

        return match ($environment) {
            'staging' => self::STAGING,
            'testing' => self::TESTING,
            default   => self::LIVE,
        };
    }

    public function hasApiKey(): bool
    {
        return $this->apiKey !== '';
    }

    /**
     * Cost-free check that the key is accepted.
     *
     * @throws SupertextException
     */
    public function validateApiKey(): void
    {
        try {
            $this->client->validateApiKey();
        } catch (SdkException $e) {
            throw SupertextException::fromSdk($e);
        }
    }

    /**
     * Translates keyed texts (plain text or HTML) and returns them under the same keys.
     *
     * @template K of array-key
     *
     * @param array<K, Segment> $segments
     * @param string            $targetLanguage Supertext target code, e.g. "de-CH" or "es" (sent as it is)
     * @param string            $sourceLanguage Supertext source code, e.g. "en" (sent as it is). Empty: auto-detect.
     * @param string            $politeness     "default", "more" (formal) or "less" (informal)
     *
     * @return array<K, string>
     *
     * @throws SupertextException
     */
    public function translateTexts(array $segments, string $targetLanguage, string $sourceLanguage = '', string $politeness = 'default'): array
    {
        try {
            return $this->client->translateTexts($segments, $targetLanguage, $sourceLanguage !== '' ? $sourceLanguage : null, $politeness);
        } catch (SdkException $e) {
            throw SupertextException::fromSdk($e);
        }
    }
}
