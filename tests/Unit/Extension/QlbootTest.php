<?php

/**
 * @package        plg_content_qlboot
 * @copyright      Copyright (C) 2026 ql.de All rights reserved.
 * @license        GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Hoochicken\Plugin\Content\Qlboot\Tests\Unit\Extension;

use Hoochicken\Plugin\Content\Qlboot\Extension\Qlboot;
use Hoochicken\Plugin\Content\Qlboot\Helper\Shortcode;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Document\JsonDocument;
use Joomla\CMS\Event\Content\ContentPrepareEvent;
use Joomla\CMS\WebAsset\WebAssetManager;
use Joomla\Registry\Registry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(Qlboot::class)]
#[UsesClass(Shortcode::class)]
final class QlbootTest extends TestCase
{
    private CMSApplication&MockObject $app;
    private WebAssetManager&MockObject $wa;

    /** calls on the web asset manager: list of [method, args] */
    private array $assetCalls = [];
    private string $client = 'site';

    protected function setUp(): void
    {
        $this->assetCalls = [];
        // registerAndUseStyle(), useStyle(), addInlineStyle() are magic methods (__call)
        $this->wa = $this->getMockBuilder(WebAssetManager::class)->disableOriginalConstructor()->onlyMethods(['__call'])->getMock();
        $this->wa->method('__call')->willReturnCallback(function (string $method, array $args) {
            $this->assetCalls[] = [$method, $args];
            return $this->wa;
        });

        $document = $this->createMock(HtmlDocument::class);
        $document->method('getWebAssetManager')->willReturn($this->wa);

        $this->app = $this->createMock(CMSApplication::class);
        $this->app->method('getDocument')->willReturn($document);
        $this->app->method('isClient')->willReturnCallback(fn(string $client) => $client === $this->client);
    }

    private function plugin(array $params = []): Qlboot
    {
        $plugin = new Qlboot(['name' => 'qlboot', 'type' => 'content', 'params' => new Registry($params)]);
        $plugin->setApplication($this->app);

        return $plugin;
    }

    private function prepare(Qlboot $plugin, string $text, string $context = 'com_content.article'): object
    {
        $item = (object) ['text' => $text];
        $plugin->onContentPrepare(new ContentPrepareEvent('onContentPrepare', [
            'context' => $context, 'subject' => $item, 'params' => new Registry(), 'page' => 0,
        ]));

        return $item;
    }

    private function assetMethods(): array
    {
        return array_map(fn(array $call) => $call[0] . ':' . ($call[1][0] ?? ''), $this->assetCalls);
    }

    public function testSubscribesToContentPrepare(): void
    {
        $this->assertSame(['onContentPrepare' => 'onContentPrepare'], Qlboot::getSubscribedEvents());
    }

    public function testRendersTagsAndLoadsOwnCss(): void
    {
        $item = $this->prepare($this->plugin(), '<p>{row}</p><p>{span6}A{/span}{span6}B{/span}</p><p>{/row}</p>');

        $this->assertStringContainsString('<div class="qlboot row">', $item->text);
        $this->assertStringContainsString('<div class="col-12 col-md-6">A</div>', $item->text);
        $this->assertSame(['registerAndUseStyle:plg_content_qlboot'], $this->assetMethods());
        $this->assertSame('plg_content_qlboot/qlboot.css', $this->assetCalls[0][1][1]);
    }

    public function testTextWithoutTagsIsUntouchedAndLoadsNothing(): void
    {
        $item = $this->prepare($this->plugin(), '<p>Lorem</p>');

        $this->assertSame('<p>Lorem</p>', $item->text);
        $this->assertSame([], $this->assetCalls);
    }

    public function testItemWithoutTextIsIgnored(): void
    {
        $item = (object) ['title' => 'x'];
        $this->plugin()->onContentPrepare(new ContentPrepareEvent('onContentPrepare', [
            'context' => 'com_content.article', 'subject' => $item, 'params' => new Registry(), 'page' => 0,
        ]));

        $this->assertObjectNotHasProperty('text', $item);
    }

    public function testIndexerGetsTagsStripped(): void
    {
        $item = $this->prepare($this->plugin(), '{row}{span6}A{/span}{span6}B{/span}{/row}', 'com_finder.indexer');

        $this->assertStringNotContainsString('{', $item->text);
        $this->assertStringNotContainsString('<div', $item->text);
        $this->assertSame([], $this->assetCalls);
    }

    public function testApiGetsTagsStripped(): void
    {
        $this->client = 'api';
        $item = $this->prepare($this->plugin(), '{row}{span12}A{/span}{/row}');

        $this->assertSame('A', trim($item->text));
    }

    public function testCssIsLoadedOnlyOnce(): void
    {
        $plugin = $this->plugin();
        $this->prepare($plugin, '{row}{span12}A{/span}{/row}');
        $this->prepare($plugin, '{row}{span12}B{/span}{/row}');

        $this->assertCount(1, $this->assetCalls);
    }

    public function testNonHtmlDocumentGetsNoAssets(): void
    {
        $app = $this->createMock(CMSApplication::class);
        $app->method('getDocument')->willReturn($this->createMock(JsonDocument::class));
        $plugin = $this->plugin();
        $plugin->setApplication($app);

        $item = $this->prepare($plugin, '{row}{span12}A{/span}{/row}');

        $this->assertStringContainsString('qlboot row', $item->text);
    }

    /** @return array<string, array{string, list<string>}> */
    public static function bootstrapOptions(): array
    {
        return [
            'none' => ['0', ['registerAndUseStyle:plg_content_qlboot']],
            'grid (also the old "yes")' => ['1', ['registerAndUseStyle:plg_content_qlboot', 'useStyle:bootstrap.css.grid']],
            'full' => ['full', ['registerAndUseStyle:plg_content_qlboot', 'useStyle:bootstrap.css']],
        ];
    }

    #[DataProvider('bootstrapOptions')]
    public function testLoadBootstrap(string $option, array $expected): void
    {
        $this->prepare($this->plugin(['bootstrap' => $option]), '{row}{span12}A{/span}{/row}');

        $this->assertSame($expected, $this->assetMethods());
    }

    public function testParamsAreUsedForRendering(): void
    {
        $item = $this->prepare($this->plugin(['default' => 'flex']), '{row}{span6}A{/span}{/row}');
        $this->assertStringContainsString('qlboot flex', $item->text);

        $item = $this->prepare($this->plugin(['breakpoint' => 'lg']), '{row}{span6}A{/span}{/row}');
        $this->assertStringContainsString('col-12 col-lg-6', $item->text);
    }

    public function testInlineStyles(): void
    {
        $this->prepare($this->plugin([
            'useStyles' => 1, 'rowPadding' => 10, 'rowTextAlign' => 'center',
            'flexPadding' => 5, 'flexTextAlign' => 'left', 'flexSwitch' => 600,
        ]), '{row}{span12}A{/span}{/row}');

        $inline = array_values(array_filter($this->assetCalls, fn($call) => $call[0] === 'addInlineStyle'));
        $this->assertCount(1, $inline);
        $css = $inline[0][1][0];
        $this->assertStringContainsString('.qlboot.row>div{padding:10px;text-align:center;}', $css);
        $this->assertStringContainsString('.qlboot.flex>div{padding:5px;text-align:left;}', $css);
        $this->assertStringContainsString('@media(max-width:600px)', $css);
    }

    public function testInlineStylesKeepGuttersAndSanitizeValues(): void
    {
        $css = $this->plugin(['rowPadding' => 0, 'rowTextAlign' => 'left;}body{display:none', 'flexPadding' => '-5', 'flexSwitch' => 'x'])->getStyles();

        $this->assertStringContainsString('.qlboot.row>div{text-align:justify;}', $css, 'padding 0 must not kill the Bootstrap gutters');
        $this->assertStringNotContainsString('display:none', $css);
        $this->assertStringContainsString('.qlboot.flex>div{padding:0px;', $css);
    }
}
