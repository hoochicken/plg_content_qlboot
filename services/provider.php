<?php

/**
 * @package        plg_content_qlboot
 * @copyright      Copyright (C) 2026 ql.de All rights reserved.
 * @author         Mareike Riegel mareike.riegel@ql.de
 * @license        GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Hoochicken\Plugin\Content\Qlboot\Extension\Qlboot;
use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

return new class implements ServiceProviderInterface {
    public function register(Container $container): void
    {
        $container->set(
            PluginInterface::class,
            $container->lazy(Qlboot::class, function (Container $container) {
                $plugin = new Qlboot((array) PluginHelper::getPlugin('content', 'qlboot'));
                $plugin->setApplication(Factory::getApplication());

                return $plugin;
            })
        );
    }
};
