<?php

/*
 * @package   RadicalForm
 * @version   __DEPLOY_VERSION__
 * @author    Vladimir Eliseev aka Progreccor - https://progreccor.ru
 * @copyright Copyright (c) 2025 Progreccor. All rights reserved.
 * @license   GNU/GPL license: http://www.gnu.org/copyleft/gpl.html
 * @link      https://progreccor.ru
 */

defined('_JEXEC') or die;

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;
use Joomla\Plugin\RadicalForm\CreatePDF\Extension\CreatePDF;

return new class implements ServiceProviderInterface {

	/**
	 * Registers the service provider with a DI container.
	 *
	 * @param   Container  $container  The DI container.
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function register(Container $container)
	{
		$container->set(PluginInterface::class,
			function (Container $container) {
				$plugin  = \Joomla\CMS\Plugin\PluginHelper::getPlugin('radicalform', 'createpdf');
				$subject = $container->get(DispatcherInterface::class);

				$plugin = new CreatePDF($subject, (array) $plugin);
				$plugin->setApplication(Factory::getApplication());

				return $plugin;
			}
		);
	}
};
