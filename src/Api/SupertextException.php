<?php

/**
 * @package     Supertext Translation for Akeneo PIM
 * @copyright   (C) Supertext AG
 * @license     MIT
 */

namespace Supertext\AkeneoTranslationBundle\Api;

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

    /** @return array{message: string, key: string, params: array<string, string|int>, detail: string} */
    public function toArray(): array
    {
        return ['message' => $this->getMessage(), 'key' => $this->key, 'params' => $this->params, 'detail' => $this->detail];
    }
}
