<?php

declare(strict_types=1);

/**
 * @package     Supertext Translation for Akeneo PIM
 * @copyright   (C) Supertext AG
 * @license     MIT
 */

namespace Supertext\AkeneoTranslationBundle\Api;

/**
 * HTTP for SupertextClient with PHP's curl extension (which Akeneo requires anyway).
 * Honours the usual HTTPS_PROXY environment variable through curl itself.
 */
final class CurlTransport
{
    public function __construct(private readonly int $timeout = 60)
    {
    }

    /**
     * @param array<string, string> $headers
     *
     * @return array{status: int, body: string, headers: array<string, string>}
     */
    public function __invoke(string $method, string $url, array $headers, ?string $body): array
    {
        $handle = curl_init($url);

        if ($handle === false) {
            throw new SupertextException('The Supertext service could not be reached.');
        }

        $responseHeaders = [];
        $lines           = [];

        foreach ($headers as $name => $value) {
            $lines[] = $name . ': ' . $value;
        }

        curl_setopt_array($handle, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $lines,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$responseHeaders): int {
                $parts = explode(':', $line, 2);

                if (\count($parts) === 2) {
                    $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                }

                return \strlen($line);
            },
        ]);

        if ($body !== null) {
            curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
        }

        $content = curl_exec($handle);

        if ($content === false) {
            $error = curl_error($handle);
            curl_close($handle);

            throw new SupertextException('The Supertext service could not be reached. ' . $error);
        }

        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);

        return ['status' => $status, 'body' => (string) $content, 'headers' => $responseHeaders];
    }
}
