<?php

/**
 * @package        plg_content_qlboot
 * @copyright      Copyright (C) 2026 ql.de All rights reserved.
 * @author         Mareike Riegel mareike.riegel@ql.de
 * @license        GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Hoochicken\Plugin\Content\Qlboot\Helper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Turns {row}{span6}...{/span}{span6}...{/span}{/row} into Bootstrap 5 grid (or flex) markup.
 *
 * Supported attributes: {row type="flex|row" class="" id="" style=""}, {span4 class="" id="" style=""}
 */
final class Shortcode
{
    public const TYPE_ROW = 'row';
    public const TYPE_FLEX = 'flex';

    public const BREAKPOINTS = ['sm', 'md', 'lg', 'xl', 'xxl'];

    private const ATTRIBUTES = ['type', 'class', 'id', 'style'];

    /** any qlboot tag: {row ...}, {/row}, {span6 ...}, {/span} */
    private const TAG = '\{(?:row(?:\s[^}]*)?|/row|span\d{1,2}(?:\s[^}]*)?|/span)\}';

    private string $defaultType;
    private string $breakpoint;

    /**
     * @param string $defaultType  'row' (Bootstrap grid) or 'flex'; legacy value 'row-fluid' means 'row'
     * @param string $breakpoint   Bootstrap breakpoint from which on the columns are side by side
     */
    public function __construct(string $defaultType = self::TYPE_ROW, string $breakpoint = 'md')
    {
        $this->defaultType = self::normalizeType($defaultType);
        $this->breakpoint = \in_array($breakpoint, self::BREAKPOINTS, true) ? $breakpoint : 'md';
    }

    public static function hasTags(string $text): bool
    {
        return str_contains($text, '{row') && str_contains($text, '{/row}');
    }

    public function render(string $text): string
    {
        if (!self::hasTags($text)) {
            return $text;
        }

        return (string) preg_replace_callback(
            '!\{row(\s[^}]*)?\}(.*?)\{/row\}!s',
            fn(array $match): string => $this->renderRow($match[1] ?? '', $match[2]),
            $this->unwrapParagraphs($text)
        );
    }

    /**
     * Removes all qlboot tags, e.g. for the search indexer.
     */
    public function strip(string $text): string
    {
        if (!self::hasTags($text)) {
            return $text;
        }

        return (string) preg_replace('!' . self::TAG . '!', ' ', $this->unwrapParagraphs($text));
    }

    /**
     * Editors wrap the tags into paragraphs: <p>{row}{span6}</p>, <p>{/span}{span6}Lorem{/span}{/row}</p>.
     * Moves the tags out of the paragraphs, so the generated divs are never nested in <p>:
     * - a paragraph opened right before tags is dropped, its closing </p> as well
     * - a paragraph with text before tags is closed in front of them
     * Text following tags inside such a paragraph stays without <p> (it may contain block
     * content, e.g. {loadposition}). Paragraphs without tags are not touched.
     */
    public function unwrapParagraphs(string $text): string
    {
        $run = '(?:' . self::TAG . ')(?:(?:\s|&nbsp;)*(?:' . self::TAG . '))*';
        $tokens = preg_split('!(<p(?:\s[^>]*)?>|</p>|' . $run . ')!i', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        $result = '';
        $pending = null;  // <p> (plus whitespace) not written yet
        $open = false;    // a <p> is open in $result
        $orphan = false;  // the next </p> belongs to a paragraph that was dropped or closed already

        foreach ($tokens as $token) {
            if (preg_match('!^<p(?:\s[^>]*)?>$!i', $token)) {
                $result .= $pending ?? '';
                $pending = $token;
                continue;
            }
            if (0 === strcasecmp('</p>', $token)) {
                if (null !== $pending) {
                    $result .= $pending . $token;
                } elseif ($open) {
                    $result .= $token;
                } elseif (!$orphan) {
                    $result .= $token;
                }
                $pending = null;
                $open = $orphan = false;
                continue;
            }
            if (preg_match('!^' . $run . '$!', $token)) {
                if ($pending !== null || $open) {
                    $orphan = true;
                }
                $result .= ($open ? '</p>' : '') . $token;
                $pending = null;
                $open = false;
                continue;
            }
            if (null !== $pending) {
                if (preg_match('!^(?:\s|&nbsp;)*$!', $token)) {
                    $pending .= $token;
                    continue;
                }
                $result .= $pending;
                $pending = null;
                $open = true;
            }
            $result .= $token;
        }

        return $result . ($pending ?? '');
    }

    /**
     * @return array{type: string, class: string, id: string, style: string}
     */
    public function parseAttributes(string $str): array
    {
        $attributes = ['type' => '', 'class' => '', 'id' => '', 'style' => ''];

        preg_match_all('~\b(' . implode('|', self::ATTRIBUTES) . ')\s*=\s*(?:"([^"]*)"|&quot;(.*?)&quot;)~s', $str, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $value = trim(html_entity_decode($match[2] !== '' ? $match[2] : ($match[3] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ('' !== $value) {
                $attributes[$match[1]] = $value;
            }
        }

        return $attributes;
    }

    /**
     * @return list<array{width: int, content: string, class: string, id: string, style: string}>
     */
    public function parseColumns(string $str): array
    {
        preg_match_all('!\{span(\d{1,2})(\s[^}]*)?\}(.*?)\{/span\}!s', $str, $matches, PREG_SET_ORDER);

        $columns = [];
        foreach ($matches as $match) {
            $attributes = $this->parseAttributes($match[2] ?? '');
            $columns[] = [
                'width' => max(1, min(12, (int) $match[1])),
                'content' => $match[3],
                'class' => $attributes['class'],
                'id' => $attributes['id'],
                'style' => $attributes['style'],
            ];
        }

        return $columns;
    }

    public function renderRow(string $attributeString, string $content): string
    {
        $attributes = $this->parseAttributes($attributeString);
        $type = '' === $attributes['type'] ? $this->defaultType : self::normalizeType($attributes['type']);

        $html = '<div' . $this->htmlAttributes(['qlboot', self::TYPE_FLEX === $type ? 'flex' : 'row', $attributes['class']], $attributes) . '>' . "\n";
        foreach ($this->parseColumns($content) as $column) {
            $class = self::TYPE_FLEX === $type
                ? 'flex' . $column['width']
                : sprintf('col-12 col-%s-%d', $this->breakpoint, $column['width']);
            $html .= '<div' . $this->htmlAttributes([$class, $column['class']], $column) . '>' . $column['content'] . '</div>' . "\n";
        }

        return $html . '</div>' . "\n";
    }

    public static function normalizeType(string $type): string
    {
        return self::TYPE_FLEX === strtolower(trim($type)) ? self::TYPE_FLEX : self::TYPE_ROW;
    }

    /**
     * @param string[] $classes
     * @param array{id: string, style: string} $attributes
     */
    private function htmlAttributes(array $classes, array $attributes): string
    {
        $html = ' class="' . $this->escape(implode(' ', array_filter(array_map('trim', $classes)))) . '"';
        if ('' !== $attributes['id']) {
            $html .= ' id="' . $this->escape($attributes['id']) . '"';
        }
        if ('' !== $attributes['style']) {
            $html .= ' style="' . $this->escape($attributes['style']) . '"';
        }

        return $html;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
