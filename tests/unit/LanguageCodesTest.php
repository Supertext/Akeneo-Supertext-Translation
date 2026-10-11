<?php

namespace Supertext\AkeneoTranslationBundle\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Supertext\AkeneoTranslationBundle\Api\LanguageCodes;

final class LanguageCodesTest extends TestCase
{
    public function testBareSourceIsSentAsIs(): void
    {
        self::assertSame('en', LanguageCodes::source('en'));
        self::assertSame('de', LanguageCodes::source('DE'));
    }

    public function testRegionalSourceLosesItsRegion(): void
    {
        self::assertSame('en', LanguageCodes::source('en-US'));
        self::assertSame('en', LanguageCodes::source('en_GB'));
        self::assertSame('de', LanguageCodes::source('de-CH'));
        self::assertSame('zh', LanguageCodes::source('zh_Hant_TW'));
        self::assertSame('', LanguageCodes::source(''));
    }

    public function testBareTargetGetsTheDefaultRegion(): void
    {
        self::assertSame('de-DE', LanguageCodes::target('de'));
        self::assertSame('en-US', LanguageCodes::target('en'));
        self::assertSame('fr-FR', LanguageCodes::target('FR'));
        self::assertSame('sl-SI', LanguageCodes::target('sl'));
        self::assertSame('zh-CN', LanguageCodes::target('zh'));
    }

    public function testRegionalTargetIsKeptAndNormalized(): void
    {
        self::assertSame('de-CH', LanguageCodes::target('de_CH'));
        self::assertSame('de-CH', LanguageCodes::target('de-ch'));
        self::assertSame('en-GB', LanguageCodes::target('EN_gb'));
        self::assertSame('zh-Hant', LanguageCodes::target('zh_hant'));
        self::assertSame('es-419', LanguageCodes::target('es-419'));
    }

    public function testOverrideWins(): void
    {
        self::assertSame('de-AT', LanguageCodes::target('de', 'de_at'));
        self::assertSame('fr-CH', LanguageCodes::target('fr-FR', ' fr-CH '));
        self::assertSame('pt', LanguageCodes::source('en-US', 'pt-BR'));
        self::assertSame('de-DE', LanguageCodes::target('de', '  '));
    }

    public function testUnknownBareCodeIsSentUnchanged(): void
    {
        self::assertSame('xx', LanguageCodes::target('xx'));
        self::assertSame('gsw', LanguageCodes::target('gsw'));
    }

    public function testSameLanguageWithAnotherRegionIsAllowed(): void
    {
        self::assertSame('en', LanguageCodes::source('en-US'));
        self::assertSame('en-GB', LanguageCodes::target('en-GB'));
    }
}
