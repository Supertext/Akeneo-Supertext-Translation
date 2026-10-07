<?php

declare(strict_types=1);

namespace Supertext\AkeneoTranslationBundle\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Supertext\AkeneoTranslationBundle\Translation\Planner;
use Supertext\AkeneoTranslationBundle\Translation\TextUnit;

final class PlannerTest extends TestCase
{
    private const CHANNELS = [
        'ecommerce' => ['en_US', 'de_DE', 'fr_FR'],
        'print'     => ['en_US', 'de_DE'],
    ];

    public function testTranslatesValuesIntoLocalesOfTheirChannel(): void
    {
        $units = [
            new TextUnit('name', null, 'Chair', false),
            new TextUnit('description', 'ecommerce', '<p>Nice</p>', true),
            new TextUnit('description', 'print', '<p>Nice</p>', true),
        ];

        $plan = Planner::plan($units, 'fr_FR', self::CHANNELS, static fn (): bool => false, false);

        self::assertSame(['name|', 'description|ecommerce'], array_map(static fn (TextUnit $u): string => $u->key(), $plan['translate']));
        self::assertSame(1, $plan['unavailable']);
        self::assertSame(0, $plan['existing']);
    }

    public function testKeepsExistingTextUnlessOverwriting(): void
    {
        $units    = [new TextUnit('name', null, 'Chair', false), new TextUnit('short', null, 'Seat', false)];
        $existing = static fn (string $attribute, ?string $scope, string $locale): bool => $attribute === 'name' && $locale === 'de_DE';

        $kept = Planner::plan($units, 'de_DE', self::CHANNELS, $existing, false);
        self::assertCount(1, $kept['translate']);
        self::assertSame('short', $kept['translate'][0]->attribute);
        self::assertSame(1, $kept['existing']);

        $overwritten = Planner::plan($units, 'de_DE', self::CHANNELS, $existing, true);
        self::assertCount(2, $overwritten['translate']);
        self::assertSame(0, $overwritten['existing']);
    }

    public function testRespectsLocaleSpecificAttributes(): void
    {
        $units = [new TextUnit('legal_notice', null, 'Only in Germany', false, ['de_DE'])];

        self::assertCount(1, Planner::plan($units, 'de_DE', self::CHANNELS, static fn (): bool => false, false)['translate']);
        self::assertSame(1, Planner::plan($units, 'fr_FR', self::CHANNELS, static fn (): bool => false, false)['unavailable']);
    }

    public function testUnknownLocaleIsUnavailable(): void
    {
        $plan = Planner::plan([new TextUnit('name', null, 'Chair', false)], 'it_IT', self::CHANNELS, static fn (): bool => false, false);

        self::assertSame([], $plan['translate']);
        self::assertSame(1, $plan['unavailable']);
    }

    public function testHasText(): void
    {
        self::assertTrue(Planner::hasText('Chair'));
        self::assertTrue(Planner::hasText('<p>Chair</p>'));
        self::assertFalse(Planner::hasText(''));
        self::assertFalse(Planner::hasText('   '));
        self::assertFalse(Planner::hasText('<p></p>'));
        self::assertFalse(Planner::hasText('<p>&nbsp;</p>'));
        self::assertFalse(Planner::hasText(null));
        self::assertFalse(Planner::hasText(['not', 'text']));
    }
}
