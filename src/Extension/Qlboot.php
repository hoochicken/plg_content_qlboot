<?php

/**
 * @package        plg_content_qlboot
 * @copyright      Copyright (C) 2026 ql.de All rights reserved.
 * @author         Mareike Riegel mareike.riegel@ql.de
 * @license        GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Hoochicken\Plugin\Content\Qlboot\Extension;

use Hoochicken\Plugin\Content\Qlboot\Helper\Shortcode;
use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Event\Content\ContentPrepareEvent;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\SubscriberInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Content plugin "qlboot": {row}{span6}...{/span}{span6}...{/span}{/row} => Bootstrap 5 grid.
 */
final class Qlboot extends CMSPlugin implements SubscriberInterface
{
    public const TEXT_ALIGN = ['justify', 'left', 'right', 'center', 'start', 'end'];

    /** load Bootstrap: no / grid only / complete bootstrap.css */
    public const BOOTSTRAP_NONE = '0';
    public const BOOTSTRAP_GRID = '1';
    public const BOOTSTRAP_FULL = 'full';

    private bool $assetsAdded = false;

    public static function getSubscribedEvents(): array
    {
        return [
            'onContentPrepare' => 'onContentPrepare',
        ];
    }

    public function onContentPrepare(ContentPrepareEvent $event): void
    {
        $item = $event->getItem();
        if (!isset($item->text) || !\is_string($item->text) || !Shortcode::hasTags($item->text)) {
            return;
        }

        $shortcode = $this->getShortcode();

        // don't put the tags into the search index; and the API must not get layout markup
        if ('com_finder.indexer' === $event->getContext() || $this->getApplication()->isClient('api')) {
            $item->text = $shortcode->strip($item->text);
            return;
        }

        $item->text = $shortcode->render($item->text);
        $this->addWebAssets();
    }

    public function getShortcode(): Shortcode
    {
        return new Shortcode(
            (string) $this->params->get('default', Shortcode::TYPE_ROW),
            (string) $this->params->get('breakpoint', 'md')
        );
    }

    private function addWebAssets(): void
    {
        if ($this->assetsAdded) {
            return;
        }
        $document = $this->getApplication()->getDocument();
        if (!$document instanceof HtmlDocument) {
            return;
        }
        $this->assetsAdded = true;

        $wa = $document->getWebAssetManager();
        $wa->registerAndUseStyle('plg_content_qlboot', 'plg_content_qlboot/qlboot.css');

        switch ((string) $this->params->get('bootstrap', self::BOOTSTRAP_NONE)) {
            case self::BOOTSTRAP_GRID:
                $wa->useStyle('bootstrap.css.grid');
                break;
            case self::BOOTSTRAP_FULL:
                $wa->useStyle('bootstrap.css');
                break;
        }

        if ($this->params->get('useStyles', 0)) {
            $wa->addInlineStyle($this->getStyles());
        }
    }

    /**
     * Inline styles from the plugin options; all values are whitelisted / cast, as they end up in CSS.
     */
    public function getStyles(): string
    {
        $rowPadding = max(0, (int) $this->params->get('rowPadding', 0));
        $flexPadding = max(0, (int) $this->params->get('flexPadding', 0));
        $flexSwitch = max(0, (int) $this->params->get('flexSwitch', 500));
        $rowAlign = $this->textAlign((string) $this->params->get('rowTextAlign', 'justify'));
        $flexAlign = $this->textAlign((string) $this->params->get('flexTextAlign', 'justify'));

        return implode("\n", [
            ".qlboot.flex>div{padding:{$flexPadding}px;text-align:{$flexAlign};}",
            "@media(max-width:{$flexSwitch}px){.qlboot.flex{flex-direction:column;}.qlboot.flex>div{padding:0;}}",
            // padding 0 must not override the Bootstrap gutters
            ".qlboot.row>div{" . ($rowPadding > 0 ? "padding:{$rowPadding}px;" : '') . "text-align:{$rowAlign};}",
        ]);
    }

    private function textAlign(string $value): string
    {
        return \in_array($value, self::TEXT_ALIGN, true) ? $value : 'justify';
    }
}
