<?php

namespace Supertext\AkeneoTranslationBundle\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Supertext\AkeneoTranslationBundle\Api\HtmlDocument;

final class HtmlDocumentTest extends TestCase
{
    public function testRoundTrip(): void
    {
        $segments = [
            ['text' => 'Fish & chips <3', 'html' => false],
            ['text' => "Line one\nLine two", 'html' => false],
            ['text' => '<p>Every praline is made in <strong>Bern</strong>. <a href="https://example.com">More</a></p>', 'html' => true],
            ['text' => 'Grüezi', 'html' => false],
        ];

        $html = HtmlDocument::build($segments);
        self::assertStringContainsString('<div data-st-id="0">Fish &amp; chips &lt;3</div>', $html);
        self::assertStringContainsString('<div data-st-id="1">Line one<br>Line two</div>', $html);

        $parsed = HtmlDocument::parse($html, [false, false, true, false]);
        self::assertSame(array_column($segments, 'text'), $parsed);
    }

    public function testCollapsesWhitespaceInPlainText(): void
    {
        $parsed = HtmlDocument::parse("<div data-st-id=\"0\">\n  Bonjour\n  le monde </div>", [false]);

        self::assertSame('Bonjour le monde', $parsed[0]);
    }
}
