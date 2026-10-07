<?php

namespace Supertext\AkeneoTranslationBundle\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Supertext\AkeneoTranslationBundle\Api\HtmlDocument;

class ChunksTest extends TestCase
{
    public function testKeepsSegmentIdsAndSplitsBelowTheLimit(): void
    {
        $segments = [
            0 => ['text' => str_repeat('a', 6), 'html' => false],
            1 => ['text' => str_repeat('b', 6), 'html' => true],
            2 => ['text' => str_repeat('c', 3), 'html' => false],
        ];
        $chunks = HtmlDocument::chunks($segments, 10);

        self::assertSame([[0], [1, 2]], array_map('array_keys', $chunks));
        self::assertTrue($chunks[1][1]['html']);
    }

    public function testOversizedSegmentStillGoesAlone(): void
    {
        $chunks = HtmlDocument::chunks([5 => ['text' => str_repeat('x', 20), 'html' => false]], 10);

        self::assertSame([[5]], array_map('array_keys', $chunks));
    }
}
