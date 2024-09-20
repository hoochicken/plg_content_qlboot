<?php
/**
 * @package        plg_content_qlboot
 * @copyright    Copyright (C) 2024 ql.de All rights reserved.
 * @author        Mareike Riegel mareike.riegel@ql.de
 * @license        GNU General Public License version 2 or later; see LICENSE.txt
 */

//no direct access
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;

defined('_JEXEC') or die('Restricted Access');

jimport('joomla.plugin.plugin');

class plgContentQlboot extends CMSPlugin
{

    const BOOTSTRAP_VERSION_DEFAULT = 5;
    protected string $start_row = 'row';
    protected string $end_row = '/row';
    protected string $start_span = 'span';
    protected string $end_span = '/span';
    protected array $arr_attributes = ['style', 'class', 'id', 'type',];
    protected array $arr_replaces = [];
    protected array $attributes = [];
    protected $pluginName = [];
    public $params = [];
    public int $bootstrapVersion;

    public function onContentPrepare($context, &$article, &$params, $page = 0)
    {
        if ((string) $context === 'com_finder.indexer') {
            return true;
        }
        if ($this->checkTags((string) $article->text)) {
            return true;
        }
        $this->bootstrapVersion = (int)$this->params->get('bootstrapVersion', self::BOOTSTRAP_VERSION_DEFAULT);
        $this->addWebAssets();
        $article->text = $this->clearTags((string) $article->text);
        $article->text = $this->getMatches((string) $article->text);
    }

    private function addWebAssets()
    {
        $wam = Factory::getApplication()->getDocument()->getWebAssetManager();
        $wam->registerAndUseStyle('plg_content_qlboot_row', 'plg_content_qlboot/qlboot.css');
        $wam->registerAndUseStyle('plg_content_qlboot_flex', 'plg_content_qlboot/qlboot-flex.css');
        if ($this->params->get('bootstrap', false)) {
            $wam->useAsset('style', 'bootstrap.css');
        }
        if ($this->params->get('useStyles', false)) {
            $wam->addInlineStyle($this->getStyles());
        }
    }

    public function checkTags(string $str): bool
    {
        return
            false === strpos($str, '{' . $this->start_row)
            || false === strpos($str, '{' . $this->end_row);
    }

    public function getMatches(string $str): string
    {
        $regex = '!{' . $this->start_row . '(.*?)}(.*?){' . $this->end_row . '}!s';
        preg_match_all($regex, $str, $matches, PREG_SET_ORDER);
        if (empty($matches)) {
            return $str;
        }
        foreach ($matches as $k => $v) {
            $this->arr_replaces[$k] = [];
            $this->arr_replaces[$k]['str'] = $v[0] ?? '';
            $this->arr_replaces[$k] = array_merge($v, $this->getAttributes($v[1] ?? ''));
            $this->arr_replaces[$k]['content'] = $this->getContent($v[2] ?? '');
            $this->arr_replaces[$k]['html'] = $this->getHtml($this->arr_replaces[$k]);
            $str = str_replace($v[0] ?? '', $this->arr_replaces[$k]['html'], $str);
        }
        return $str;
    }

    public function getHtml($arr, $bootstrapVersion = 5)
    {
        $class = $this->params->get('default', 'row-fluid');
        if ('row-fluid' === $class) {
            $class = 'span';
        }
        if ('boot' === $arr['type']) {
            $arr['type'] .= ' row-fluid';
        }
        $wrapper_class = 5 === $bootstrapVersion ? 'row' : $arr['class'];
        $html = '';
        $html .= '<div class="qlboot ' . $wrapper_class . ' ' . $arr['type'] . '"';
        if ('' != $arr['style']) {
            $html .= ' style="' . $arr['style'] . '"';
        }
        $html .= '>';
        //echo '<pre>';print_R($arr);die;
        foreach ($arr['content'] as $k => $v) {
            //$class='span';
            $width = $v['width'];
            if ('flex' == $arr['type']) {
                $class = 'flex';
            } elseif ('row-fluid' == $arr['type']) {
                $class = 5 === $bootstrapVersion ? 'col-md-%s col-sm-12' : 'span%s';
                $class = sprintf($class, $width);
            }

            $html .= sprintf('<div class="%s %s"', $class, $v['class']);
            if ('' != $v['style']) {
                $html .= sprintf(' style="%s"', $v['style']);
            }
            if ('' != $v['id']) {
                $html .= sprintf(' id="%s"', $v['id']);
            }
            $html .= '>';
            $html .= $v['content'];
            $html .= '</div>' . "\n\r";
        }
        $html .= '</div>' . "\n\r";
        return $html;
    }

    public function getContent($str)
    {
        $regex = '!{' . $this->start_span . '([0-9]{1,2})(.*?)}(.*?){' . $this->end_span . '}!s';
        preg_match_all($regex, $str, $matches, PREG_SET_ORDER);
        if (0 >= count($matches)) {
            return [];
        }
        $arr_content = [];
        foreach ($matches as $k => $v) {
            $arr_content[$k] = [];
            $arr_content[$k]['width'] = $v[1];
            $arr_content[$k]['content'] = $v[3];
            $arr_content[$k] = array_merge($arr_content[$k], $this->getAttributes($v[2]));
        }
        //echo '<pre>';print_r($arr_content);die;
        return $arr_content;
    }

    public function getAttributes($str)
    {
        $attributes = [];
        $attributes['type'] = $this->params->get('default', 'row-fluid');
        $attributes['class'] = '';
        $attributes['id'] = '';
        $attributes['style'] = '';
        $selector = implode('|', $this->arr_attributes);
        $regex = '~(' . $selector . ')="(.*?)"~s';
        if (empty($matches) || empty($matches[0] ?? [])) {
            return $attributes;
        }
        preg_match_all($regex, $str, $matches);
        foreach ($matches[0] as $k => $v) {
            if (empty($matches[2][$k])) {
                continue;
            }
            $attributes[$matches[1][$k]] = $matches[2][$k];
        }
        return $attributes;
    }

    public function clearTags(string $str)
    {
        /*rows*/
        $str = str_replace('<p>{' . $this->end_row . '}', '{' . $this->end_row . '}', $str);
        $str = str_replace('{' . $this->end_row . '}</p>', '{' . $this->end_row . '}', $str);
        $str = str_replace('<p>{' . $this->start_row . '', '{' . $this->start_row . '', $str);
        $regex = '!{' . $this->start_row . '\s(.*?)}</p>!';
        preg_match_all($regex, $str, $matches, PREG_SET_ORDER);
        if (0 < count($matches)) {
            foreach ($matches as $k => $v) {
                $str = preg_replace('?' . $v[0] . '?', '{' . $this->start_row . ' ' . $v[1] . '}', $str);
            }
        }
        /*spans*/
        $str = str_replace('<p>{' . $this->end_span . '}', '{' . $this->end_span . '}', $str);
        $str = str_replace('{' . $this->end_span . '}</p>', '{' . $this->end_span . '}', $str);
        $str = str_replace('<p>{' . $this->start_span . '', '{' . $this->start_span . '', $str);
        $regex = '!{' . $this->start_span . '(.*?)}</p>!';
        preg_match_all($regex, $str, $matches, PREG_SET_ORDER);
        if (0 < count($matches)) {
            foreach ($matches as $k => $v) {
                $str = preg_replace('?' . $v[0] . '?', '{' . $this->start_span . $v[1] . '}', $str);
            }
        }
        return $str;
    }

    public function getStyles()
    {
        $styles = [];
        $styles[] = '.qlboot.flex>div';
        $styles[] = '{';
        $styles[] = 'padding:' . $this->params->get('flexPadding', 0) . 'px;';
        $styles[] = 'text-align:' . $this->params->get('flexTextAlign', 'justify') . ';';
        $styles[] = '}';
        $styles[] = '@media(max-width:' . $this->params->get('flexSwitch', 500) . 'px)';
        $styles[] = '{';
        $styles[] = '.qlboot.flex {flex-direction:column;}';
        $styles[] = '.qlboot.flex>div {padding:0;}';
        $styles[] = '}';
        $styles[] = '.qlboot.row-fluid>div';
        $styles[] = '{';
        $styles[] = 'padding:' . $this->params->get('rowPadding', 0) . 'px;';
        $styles[] = 'text-align:' . $this->params->get('rowTextAlign', 'justify') . ';';
        $styles[] = '}';
        return implode("\n", $styles);
    }
}
