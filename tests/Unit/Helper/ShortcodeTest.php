<?php

/**
 * @package        plg_content_qlboot
 * @copyright      Copyright (C) 2026 ql.de All rights reserved.
 * @license        GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Hoochicken\Plugin\Content\Qlboot\Tests\Unit\Helper;

use Hoochicken\Plugin\Content\Qlboot\Helper\Shortcode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Shortcode::class)]
final class ShortcodeTest extends TestCase
{
    /** collapses whitespace between tags for readable assertions */
    private static function html(string $html): string
    {
        return trim((string) preg_replace('/>\s+</', '><', $html));
    }

    public function testTextWithoutTagsIsUnchanged(): void
    {
        $text = '<p>Lorem {span6} ipsum</p>';
        $this->assertSame($text, (new Shortcode())->render($text));
        $this->assertFalse(Shortcode::hasTags('{row} without end'));
        $this->assertTrue(Shortcode::hasTags('{row}{/row}'));
    }

    public function testRendersBootstrap5Grid(): void
    {
        $html = (new Shortcode())->render('{row}{span4}A{/span}{span8}B{/span}{/row}');

        $this->assertSame(
            '<div class="qlboot row"><div class="col-12 col-md-4">A</div><div class="col-12 col-md-8">B</div></div>',
            self::html($html)
        );
    }

    public function testLegacyDefaultRowFluidMeansBootstrapRow(): void
    {
        $html = (new Shortcode('row-fluid'))->render('{row}{span6}A{/span}{/row}');

        $this->assertStringContainsString('<div class="qlboot row">', $html);
        $this->assertStringNotContainsString('row-fluid', $html, 'Bootstrap 2 classes are gone');
        $this->assertStringNotContainsString('span6', $html);
    }

    /** @return array<string, array{string, string}> */
    public static function breakpoints(): array
    {
        return [
            'sm' => ['sm', 'col-sm-6'],
            'lg' => ['lg', 'col-lg-6'],
            'xxl' => ['xxl', 'col-xxl-6'],
            'invalid falls back to md' => ['huge', 'col-md-6'],
        ];
    }

    #[DataProvider('breakpoints')]
    public function testBreakpoint(string $breakpoint, string $expected): void
    {
        $html = (new Shortcode('row', $breakpoint))->render('{row}{span6}A{/span}{/row}');

        $this->assertStringContainsString('class="col-12 ' . $expected . '"', $html);
    }

    public function testFlexType(): void
    {
        $html = (new Shortcode('flex'))->render('{row}{span4}A{/span}{span8}B{/span}{/row}');

        // the widths used to be lost (class "flex" only), the flexN classes of qlboot.css never matched
        $this->assertSame(
            '<div class="qlboot flex"><div class="flex4">A</div><div class="flex8">B</div></div>',
            self::html($html)
        );
    }

    public function testTypeAttributeOverridesDefault(): void
    {
        $shortcode = new Shortcode('row');
        $this->assertStringContainsString('qlboot flex', $shortcode->render('{row type="flex"}{span6}A{/span}{/row}'));

        $shortcode = new Shortcode('flex');
        $this->assertStringContainsString('qlboot row', $shortcode->render('{row type="row"}{span6}A{/span}{/row}'));
        $this->assertStringContainsString('qlboot row', $shortcode->render('{row type="row-fluid"}{span6}A{/span}{/row}'));
    }

    public function testAttributesAreApplied(): void
    {
        // attributes used to be ignored completely
        $html = (new Shortcode())->render(
            '{row class="g-4 mb-3" id="team" style="color:red"}{span6 class="text-center" id="left" style="margin:0"}A{/span}{span6}B{/span}{/row}'
        );

        $this->assertStringContainsString('<div class="qlboot row g-4 mb-3" id="team" style="color:red">', $html);
        $this->assertStringContainsString('<div class="col-12 col-md-6 text-center" id="left" style="margin:0">A</div>', $html);
        $this->assertStringContainsString('<div class="col-12 col-md-6">B</div>', $html);
    }

    public function testAttributesWithEditorEncodedQuotes(): void
    {
        $html = (new Shortcode())->render('{row class=&quot;g-5&quot;}{span12}A{/span}{/row}');

        $this->assertStringContainsString('<div class="qlboot row g-5">', $html);
    }

    public function testAttributeValuesAreEscaped(): void
    {
        $html = (new Shortcode())->render('{row class="a&lt;b"}{span12 style="content:\'<x>\'"}A{/span}{/row}');

        $this->assertStringContainsString('class="qlboot row a&lt;b"', $html);
        $this->assertStringContainsString('style="content:&#039;&lt;x&gt;&#039;"', $html);
    }

    public function testWidthIsClamped(): void
    {
        $html = (new Shortcode())->render('{row}{span0}A{/span}{span13}B{/span}{/row}');

        $this->assertStringContainsString('col-md-1"', $html);
        $this->assertStringContainsString('col-md-12"', $html);
    }

    public function testSeveralRowsAndSurroundingText(): void
    {
        $html = (new Shortcode())->render('<h2>Intro</h2>{row}{span6}A{/span}{span6}B{/span}{/row}<p>between</p>{row}{span12}C{/span}{/row}<p>end</p>');

        $this->assertSame(2, substr_count($html, 'qlboot row'));
        $this->assertStringStartsWith('<h2>Intro</h2>', $html);
        $this->assertStringContainsString('<p>between</p>', $html);
        $this->assertStringEndsWith("<p>end</p>", $html);
    }

    public function testContentWithHtmlAndBracesIsKept(): void
    {
        $html = (new Shortcode())->render("{row}{span6}<ul>\n<li>{not a tag}</li>\n</ul>{/span}{/row}");

        $this->assertStringContainsString("<ul>\n<li>{not a tag}</li>\n</ul>", $html);
    }

    /** @return array<string, array{string, string}> */
    public static function editorParagraphs(): array
    {
        return [
            'each tag in its own paragraph' => [
                '<p>{row}</p><p>{span6}</p><p>A</p><p>{/span}</p><p>{span6}</p><p>B</p><p>{/span}</p><p>{/row}</p>',
                '<div class="qlboot row"><div class="col-12 col-md-6"><p>A</p></div><div class="col-12 col-md-6"><p>B</p></div></div>',
            ],
            'tags at start and end of paragraphs' => [
                '<p>{row}{span6}A</p><p>A2{/span}{span6}B{/span}{/row}</p>',
                '<div class="qlboot row"><div class="col-12 col-md-6">A<p>A2</p></div><div class="col-12 col-md-6">B</div></div>',
            ],
            'whole row in one paragraph' => [
                '<p>{row}{span6}A{/span}{span6}B{/span}{/row}</p>',
                '<div class="qlboot row"><div class="col-12 col-md-6">A</div><div class="col-12 col-md-6">B</div></div>',
            ],
            'nbsp, newlines and attributes' => [
                "<p>&nbsp;{row class=\"x\"}&nbsp;</p>\n<p>{span12}A{/span}</p>\n<p>{/row}</p>",
                '<div class="qlboot row x"><div class="col-12 col-md-12">A</div></div>',
            ],
            'block content after tags stays out of <p> (e.g. {loadposition})' => [
                '<p>{row}{span6}</p><p>A</p><p>{/span}{span6}<a href="#">x</a>{loadposition y}{/span}{/row}</p><p>&nbsp;</p>',
                '<div class="qlboot row"><div class="col-12 col-md-6"><p>A</p></div><div class="col-12 col-md-6"><a href="#">x</a>{loadposition y}</div></div><p>&nbsp;</p>',
            ],
            'paragraph with attributes' => [
                '<p class="lead">{row}{span12}A{/span}{/row}</p>',
                '<div class="qlboot row"><div class="col-12 col-md-12">A</div></div>',
            ],
            'text before and after the row in the same paragraph' => [
                '<p>Intro{row}{span12}A{/span}{/row}Outro</p>',
                "<p>Intro</p><div class=\"qlboot row\"><div class=\"col-12 col-md-12\">A</div></div>\nOutro",
            ],
        ];
    }

    #[DataProvider('editorParagraphs')]
    public function testEditorParagraphsAreUnwrapped(string $input, string $expected): void
    {
        // the generated divs must not end up inside <p>, and no stray <p>/</p> may be left
        $this->assertSame($expected, self::html((new Shortcode())->render($input)));
    }

    public function testUnwrapLeavesOtherParagraphsAlone(): void
    {
        $text = '<p>Lorem</p><p></p><p>{notqlboot}</p>';
        $this->assertSame($text, (new Shortcode())->unwrapParagraphs($text));
    }

    public function testStripRemovesTagsForTheIndexer(): void
    {
        $text = (new Shortcode())->strip('<p>{row}</p><p>{span6 class="x"}Alpha{/span}{span6}Beta{/span}</p><p>{/row}</p>');

        $this->assertStringNotContainsString('{', $text);
        $this->assertStringContainsString('Alpha', $text);
        $this->assertStringContainsString('Beta', $text);
        $this->assertStringNotContainsString('AlphaBeta', $text, 'words of neighbouring columns must not be glued together');
    }

    public function testParseAttributes(): void
    {
        $this->assertSame(
            ['type' => 'flex', 'class' => 'a b', 'id' => '', 'style' => 'x:y'],
            (new Shortcode())->parseAttributes(' type="flex" class=" a b " id="" style="x:y" onclick="evil()"')
        );
    }
}
