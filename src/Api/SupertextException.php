<?php

/**
 * @package     Supertext Translation for Akeneo PIM
 * @copyright   (C) Supertext AG
 * @license     MIT
 */

namespace Supertext\AkeneoTranslationBundle\Api;

use Supertext\Sdk\SupertextException as SdkException;

/**
 * Any failure talking to Supertext. The message (English, for logs and the console) is safe to
 * show to editors. The UI shows the translation of `key` instead
 * (`supertext_translation.error.<key>` in the jsmessages files, with `params`), followed by
 * `detail` (what Supertext or cURL said, untranslated) in brackets.
 */
final class SupertextException extends \RuntimeException
{
    /** @param array<string, string|int> $params */
    public function __construct(
        string $message,
        int $code = 0,
        ?\Throwable $previous = null,
        public readonly string $key = '',
        public readonly array $params = [],
        public readonly string $detail = '',
    ) {
        parent::__construct($message, $code, $previous);
    }

    /** UI keys (`supertext_translation.error.<key>`) for the Supertext SDK's error codes. */
    public const SDK_KEYS = [
        SdkException::MISSING_API_KEY        => 'no_api_key',
        SdkException::AUTHENTICATION_FAILURE => 'auth_failed',
        SdkException::INVALID_LANGUAGE_PAIR  => 'invalid_language_pair',
        SdkException::NOT_FOUND              => 'not_found',
        SdkException::PAYLOAD_TOO_LARGE      => 'too_large',
        SdkException::TOO_MANY_REQUESTS      => 'rate_limited',
        SdkException::SERVICE_UNAVAILABLE    => 'unavailable',
        SdkException::UNEXPECTED_STATUS      => 'http_status',
        SdkException::TRANSPORT_ERROR        => 'unreachable',
        SdkException::NO_FILE_ID             => 'no_file_id',
        SdkException::TRANSLATION_ERROR      => 'failed',
        SdkException::QUOTA_EXCEEDED         => 'limit_exceeded',
        SdkException::FILE_DELETED           => 'deleted',
        SdkException::TIMEOUT                => 'timeout',
        SdkException::INCOMPLETE_RESPONSE    => 'empty',
    ];

    /** The SDK's error with the bundle's UI key; the English message (with the API key links where they help) is the SDK's. */
    public static function fromSdk(SdkException $e): self
    {
        $params = [];

        if ($e->status !== null) {
            $params['status'] = $e->status;
        }

        if ($e->errorCode === SdkException::INVALID_LANGUAGE_PAIR) {
            $params['source'] = (string) $e->sourceLang;
            $params['target'] = (string) $e->targetLang;
        }

        return new self($e->getMessage(), $e->status ?? 0, $e, self::SDK_KEYS[$e->errorCode] ?? 'failed', $params, (string) $e->detail);
    }

    /** @return array{message: string, key: string, params: array<string, string|int>, detail: string} */
    public function toArray(): array
    {
        return ['message' => $this->getMessage(), 'key' => $this->key, 'params' => $this->params, 'detail' => $this->detail];
    }
}
