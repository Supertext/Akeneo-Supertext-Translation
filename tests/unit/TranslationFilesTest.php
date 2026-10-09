<?php

declare(strict_types=1);

namespace Supertext\AkeneoTranslationBundle\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** The UI strings exist in English, German, French and Italian, with the same keys and placeholders. */
final class TranslationFilesTest extends TestCase
{
    private const DIR = __DIR__ . '/../../src/Resources/translations/';

    /** @return iterable<string, array{string}> */
    public static function locales(): iterable
    {
        foreach (['de_DE', 'fr_FR', 'it_IT'] as $locale) {
            yield $locale => [$locale];
        }
    }

    #[DataProvider('locales')]
    public function testSameKeysAndPlaceholdersAsEnglish(string $locale): void
    {
        $english = self::strings('en_US');
        $strings = self::strings($locale);

        self::assertSame(array_keys($english), array_keys($strings), "jsmessages.$locale.yml has other keys than English");

        foreach ($english as $key => $text) {
            self::assertSame(self::placeholders($text), self::placeholders($strings[$key]), "$locale: placeholders of $key");
            self::assertNotSame('', trim($strings[$key]), "$locale: $key is empty");
        }
    }

    /** Every message key the server sends (SupertextException key, 'key' => …) has an English text. */
    public function testServerMessageKeysExist(): void
    {
        $english = self::strings('en_US');
        $keys    = [];
        $files   = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(__DIR__ . '/../../src', \FilesystemIterator::SKIP_DOTS));

        foreach ($files as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                preg_match_all("/(?:key: |'key'\\s*=> )'([a-z_]+)'(?=\\s*[,\\])])/", (string) file_get_contents($file->getPathname()), $matches);
                $keys = array_merge($keys, $matches[1]);
            }
        }

        // Keys built in expressions (ternaries, the HTTP status match, 'forbidden_' . $type)
        $keys = array_merge(array_unique($keys), [
            'forbidden_product', 'forbidden_product_model', 'too_long', 'no_text', 'partly_too_long', 'rejected',
            'auth_failed', 'not_found', 'too_large', 'rate_limited', 'unavailable', 'http_status',
        ]);

        self::assertGreaterThan(20, \count($keys));

        foreach ($keys as $key) {
            self::assertArrayHasKey('supertext_translation.error.' . $key, $english, "missing English text for server key $key");
        }
    }

    /** @return array<string, string> flattened keys (sorted) => text */
    private static function strings(string $locale): array
    {
        $path    = self::DIR . "jsmessages.$locale.yml";
        $strings = [];
        $stack   = [];

        self::assertFileExists($path);

        foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            if (trim($line) === '' || str_starts_with(ltrim($line), '#')) {
                continue;
            }

            self::assertMatchesRegularExpression('/^( *)([A-Za-z0-9_]+):(?: (.*))?$/', $line, "$locale: unexpected line");
            preg_match('/^( *)([A-Za-z0-9_]+):(?: (.*))?$/', $line, $m);
            $indent = \strlen($m[1]);

            while ($stack !== [] && array_key_last($stack) >= $indent) {
                array_pop($stack);
            }

            $value = $m[3] ?? '';

            if ($value === '') {
                $stack[$indent] = $m[2];

                continue;
            }

            if (preg_match("/^'(.*)'$/", $value, $quoted)) {
                $value = str_replace("''", "'", $quoted[1]);
            } elseif (preg_match('/^"(.*)"$/', $value, $quoted)) {
                $value = stripcslashes($quoted[1]);
            } else {
                self::assertStringNotContainsString(': ', $value, "$locale: quote values that contain ': ' ($line)");
            }

            $strings[implode('.', [...array_values($stack), $m[2]])] = $value;
        }

        ksort($strings);

        return $strings;
    }

    /** @return list<string> */
    private static function placeholders(string $text): array
    {
        preg_match_all('/\{\{\s*\w+\s*\}\}/', $text, $matches);
        $list = array_map(static fn (string $p): string => preg_replace('/\s+/', '', $p), $matches[0]);
        sort($list);

        return $list;
    }
}
