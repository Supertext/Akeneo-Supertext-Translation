<?php

declare(strict_types=1);

namespace Supertext\AkeneoTranslationBundle\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Supertext\AkeneoTranslationBundle\Api\SupertextException;
use Supertext\AkeneoTranslationBundle\Settings\Settings;
use Supertext\AkeneoTranslationBundle\Tests\Support\MemoryStorage;

/** Language mapping (the SDK's no-guessing rules), the version reports setting and the site URL. */
final class SettingsTest extends TestCase
{
    private const ENV = ['SUPERTEXT_VERSION_REPORTS', 'AKENEO_PIM_URL', 'SUPERTEXT_API_KEY'];

    protected function tearDown(): void
    {
        foreach (self::ENV as $name) {
            unset($_SERVER[$name], $_ENV[$name]);
            putenv($name);
        }
    }

    public function testLocalesThatEqualASupertextCodeNeedNoMapping(): void
    {
        $settings = new Settings(new MemoryStorage());

        self::assertSame('de-CH', $settings->languageCode('de_CH'));
        self::assertSame('en-US', $settings->languageCode('en_US'));
        self::assertSame('en', $settings->sourceCode('en_US'));
        self::assertSame('es', $settings->sourceCode('es_ES'));
        self::assertSame('de-CH', Settings::exactLanguageCode('de_CH'));
        self::assertNull(Settings::exactLanguageCode('es_ES'));
    }

    public function testNoDefaultRegionsAndNoGuessing(): void
    {
        $settings = new Settings(new MemoryStorage());

        foreach (['es_ES', 'nl_NL', 'sl_SI', 'zh_CN'] as $locale) {
            self::assertNull($settings->languageCodeOrNull($locale), $locale);

            try {
                $settings->languageCode($locale);
                self::fail("No exception for $locale");
            } catch (SupertextException $e) {
                self::assertSame('unsupported_language', $e->key);
                self::assertSame($locale, $e->params['language']);
            }
        }
    }

    public function testTheMappingWinsForTargetAndSource(): void
    {
        $settings = new Settings(new MemoryStorage(['languages' => [
            'es_ES' => ['code' => 'es'],
            'de_DE' => ['code' => 'de-CH'],
            'en_US' => ['code' => 'en-GB', 'politeness' => 'more'],
            'zh_CN' => ['code' => 'zh-Hans'],
        ]]));

        self::assertSame('es', $settings->languageCode('es_ES'));
        self::assertSame('de-CH', $settings->languageCode('de_DE'));
        self::assertSame('zh-Hans', $settings->languageCode('zh_CN'));
        // One mapping for both directions: a mapped target is sent as source in its source form.
        self::assertSame('de', $settings->sourceCode('de_DE'));
        self::assertSame('en', $settings->sourceCode('en_US'));
        self::assertSame('zh', $settings->sourceCode('zh_CN'));
        // A politeness the target doesn't offer is kept (not silently dropped): the settings page warns.
        self::assertSame('more', $settings->politeness('en_US'));
    }

    public function testSavingTheMapping(): void
    {
        $storage  = new MemoryStorage();
        $settings = new Settings($storage);

        self::assertSame([], $settings->update(['languages' => [
            'de_CH' => ['code' => 'de-CH', 'politeness' => 'more'], // the exact match: not stored
            'es_ES' => ['code' => 'ES'],                            // normalized to Supertext's spelling
            'fr_FR' => ['code' => 'fr_ch'],
            'nl_NL' => ['code' => ''],                              // not mapped
            'xx_YY' => ['code' => 'xx-YY'],                         // a code the SDK doesn't list yet: kept as typed
        ]]));

        self::assertSame([
            'de_CH' => ['politeness' => 'more'],
            'es_ES' => ['code' => 'es'],
            'fr_FR' => ['code' => 'fr-CH'],
            'xx_YY' => ['code' => 'xx-YY'],
        ], $storage->values['languages']);
        self::assertSame(['es_ES' => 'es', 'fr_FR' => 'fr-CH', 'xx_YY' => 'xx-YY'], $settings->mapping());
        self::assertNull($settings->languageCodeOrNull('nl_NL'));

        $errors = $settings->update(['languages' => ['de_CH' => ['code' => 'not a code!']]]);
        self::assertSame('invalid_code', $errors[0]['key']);
        self::assertSame(['code' => 'not a code!', 'locale' => 'de_CH'], $errors[0]['params']);
    }

    public function testVersionReportsAreOnByDefaultAndTheEnvironmentWins(): void
    {
        $storage  = new MemoryStorage();
        $settings = new Settings($storage);

        self::assertNull($settings->versionReports());
        self::assertTrue($settings->versionReportsEnabled());
        self::assertFalse($settings->versionReportsFromEnvironment());

        self::assertSame([], $settings->update(['version_reports' => false]));
        self::assertFalse($storage->values['version_reports']);
        self::assertFalse($settings->versionReportsEnabled());

        $_SERVER['SUPERTEXT_VERSION_REPORTS'] = 'on';
        self::assertTrue($settings->versionReportsFromEnvironment());
        self::assertTrue($settings->versionReportsEnabled());

        $settings->update(['version_reports' => true]);
        $_SERVER['SUPERTEXT_VERSION_REPORTS'] = '0';
        self::assertFalse($settings->versionReportsEnabled());
    }

    public function testSiteUrl(): void
    {
        self::assertSame('https://pim.example.ch', Settings::siteUrl('https://pim.example.ch/'));
        self::assertNull(Settings::siteUrl(null));
        self::assertNull(Settings::siteUrl('pim.example.ch'));

        $_SERVER['AKENEO_PIM_URL'] = 'https://akeneo.example.com/pim/';
        self::assertSame('https://akeneo.example.com/pim', Settings::siteUrl('http://10.0.0.5:8080'));
    }
}
