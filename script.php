<?php
/*
 * @package    RadicalForm PDF Create Plugin
 * @version     __DEPLOY_VERSION__
 * @author      CaveDesign Studio - cavedesign.ru
 * @copyright   Copyright (c) 2009 - 2025 CaveDesign Studio. All Rights Reserved.
 * @license     GNU/GPL license: https://www.gnu.org/copyleft/gpl.html
 * @link        https://cavedesign.ru/
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Application\AdministratorApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Installer\Installer;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScriptInterface;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Version;
use Joomla\Database\DatabaseDriver;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Filesystem\Folder;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Path;
use Joomla\Registry\Registry;

return new class () implements ServiceProviderInterface {
	public function register(Container $container)
	{
		$container->set(InstallerScriptInterface::class,
			new class ($container->get(AdministratorApplication::class)) implements InstallerScriptInterface {
				/**
				 * The application object
				 *
				 * @var  AdministratorApplication
				 *
				 * @since  __DEPLOY_VERSION__
				 */
				protected AdministratorApplication $app;

				/**
				 * The Database object.
				 *
				 * @var   DatabaseDriver
				 *
				 * @since  __DEPLOY_VERSION__
				 */
				protected DatabaseDriver $db;

				/**
				 * Minimum Joomla version required to install the extension.
				 *
				 * @var  string
				 *
				 * @since  __DEPLOY_VERSION__
				 */
				protected string $minimumJoomla = '5.0';

				/**
				 * Minimum PHP version required to install the extension.
				 *
				 * @var  string
				 *
				 * @since  __DEPLOY_VERSION__
				 */
				protected string $minimumPhp = '8.2';

				/**
				 * Minimum MySQL version required to install the extension.
				 *
				 * @var  string
				 *
				 * @since  __DEPLOY_VERSION__
				 */
				protected string $minimumMySQL = '8.0';

				/**
				 * Minimum MariaDb version required to install the extension.
				 *
				 * @var  string
				 *
				 * @since  __DEPLOY_VERSION__
				 */
				protected string $minimumMariaDb = '10.11';

				/**
				 * Language constant for errors.
				 *
				 * @var string
				 *
				 * @since __DEPLOY_VERSION__
				 */
				protected string $constant = "PLG_RADICALFORM_CREATEPDF";

				/**
				 * Extension params for check.
				 *
				 * @var  array
				 *
				 * @since  __DEPLOY_VERSION__
				 */
				protected array $extensionParams = [];

				/**
				 * Update methods.
				 *
				 * @var  array
				 *
				 * @since  __DEPLOY_VERSION__
				 */
				protected array $updateMethods = [];

				/**
				 * Constructor.
				 *
				 * @param   AdministratorApplication  $app  The application object.
				 *
				 * @since __DEPLOY_VERSION__
				 */
				public function __construct(AdministratorApplication $app)
				{
					$this->app = $app;
					$this->db  = Factory::getContainer()->get('DatabaseDriver');
				}

				/**
				 * Function called after the extension is installed.
				 *
				 * @param   InstallerAdapter  $adapter  The adapter calling this method
				 *
				 * @return  boolean  True on success
				 *
				 * @since   __DEPLOY_VERSION__
				 */
				public function install(InstallerAdapter $adapter): bool
				{
					$this->enablePlugin($adapter);

					return true;
				}

				/**
				 * Function called after the extension is updated.
				 *
				 * @param   InstallerAdapter  $adapter  The adapter calling this method
				 *
				 * @return  boolean  True on success
				 *
				 * @since   __DEPLOY_VERSION__
				 */
				public function update(InstallerAdapter $adapter): bool
				{
					// Refresh media version
					(new Version())->refreshMediaVersion();

					return true;
				}

				/**
				 * Function called after the extension is uninstalled.
				 *
				 * @param   InstallerAdapter  $adapter  The adapter calling this method
				 *
				 * @return  boolean  True on success
				 *
				 * @since   __DEPLOY_VERSION__
				 */
				public function uninstall(InstallerAdapter $adapter): bool
				{
					return true;
				}

				/**
				 * Function called before extension installation/update/removal procedure commences.
				 *
				 * @param   string            $type     The type of change (install or discover_install, update, uninstall)
				 * @param   InstallerAdapter  $adapter  The adapter calling this method
				 *
				 * @return  boolean  True on success
				 *
				 * @since   __DEPLOY_VERSION__
				 */
				public function preflight(string $type, InstallerAdapter $adapter): bool
				{
					// Check compatible
					if (!$this->checkCompatible())
					{
						return false;
					}

					return true;
				}

				/**
				 * Function called after extension installation/update/removal procedure commences.
				 *
				 * @param   string            $type     The type of change (install or discover_install, update, uninstall)
				 * @param   InstallerAdapter  $adapter  The adapter calling this method
				 *
				 * @return  boolean  True on success
				 *
				 * @since   __DEPLOY_VERSION__
				 */
				public function postflight(string $type, InstallerAdapter $adapter): bool
				{
					$installer = $adapter->getParent();
					if ($type !== 'uninstall')
					{
						// Check extension params
						$this->checkExtensionParams($adapter);

						// Run updates script
						if ($type === 'update')
						{
							foreach ($this->updateMethods as $method)
							{
								if (method_exists($this, $method))
								{
									$this->$method($adapter);
								}
							}
						}
					}

					return true;
				}

				/**
				 * Enable plugin after installation.
				 *
				 * @param   InstallerAdapter  $adapter  Parent object calling object.
				 *
				 * @since  __DEPLOY_VERSION__
				 */
				protected function enablePlugin(InstallerAdapter $adapter)
				{
					// Prepare plugin object
					$plugin          = new \stdClass();
					$plugin->type    = 'plugin';
					$plugin->element = $adapter->getElement();
					$plugin->folder  = (string) $adapter->getParent()->manifest->attributes()['group'];
					$plugin->enabled = 1;

					// Update record
					$this->db->updateObject('#__extensions', $plugin, ['type', 'element', 'folder']);
				}

				/**
				 * Method to check compatible.
				 *
				 * @throws  \Exception
				 *
				 * @return  bool True on success, False on failure.
				 *
				 * @since  __DEPLOY_VERSION__
				 */
				protected function checkCompatible(): bool
				{
					$app = Factory::getApplication();

					// Check joomla version
					if (!(new Version())->isCompatible($this->minimumJoomla))
					{
						$app->enqueueMessage(Text::sprintf($this->constant . '_ERROR_COMPATIBLE_JOOMLA', $this->minimumJoomla),
							'error');

						return false;
					}

					// Check PHP
					if (!(version_compare(PHP_VERSION, $this->minimumPhp) >= 0))
					{
						$app->enqueueMessage(Text::sprintf($this->constant . '_ERROR_COMPATIBLE_PHP', $this->minimumPhp),
							'error');

						return false;
					}

					// Check database version
					$db            = $this->db;
					$serverType    = $db->getServerType();
					$serverVersion = $db->getVersion();
					if ($serverType == 'mysql' && stripos($serverVersion, 'mariadb') !== false)
					{
						$serverVersion = preg_replace('/^5\.5\.5-/', '', $serverVersion);

						if (!(version_compare($serverVersion, $this->minimumMariaDb) >= 0))
						{
							$app->enqueueMessage(Text::sprintf($this->constant . '_ERROR_COMPATIBLE_DATABASE',
								$this->minimumMySQL, $this->minimumMariaDb), 'error');

							return false;
						}
					}

					if ($serverType == 'mysql' && !(version_compare($serverVersion, $this->minimumMySQL) >= 0))
					{
						$app->enqueueMessage(Text::sprintf($this->constant . '_ERROR_COMPATIBLE_DATABASE',
							$this->minimumMySQL, $this->minimumMariaDb), 'error');

						return false;
					}

					return true;
				}

				/**
				 * Method to check extension params and set if needed.
				 *
				 * @param   InstallerAdapter  $adapter  Parent object calling object.
				 *
				 * @since  __DEPLOY_VERSION__
				 */
				protected function checkExtensionParams(InstallerAdapter $adapter)
				{
					if (!empty($this->extensionParams))
					{
						$element = $adapter->getElement();
						$folder  = (string) $adapter->getParent()->manifest->attributes()['group'];

						// Get extension
						$db    = $this->db;
						$query = $db->getQuery(true)
							->select(['extension_id', 'params'])
							->from($db->quoteName('#__extensions'))
							->where($db->quoteName('element') . ' = :element')
							->bind(':element', $element);
						if (!empty($folder))
						{
							$query->where($db->quoteName('folder') . ' = :folder')
								->bind(':folder', $folder);
						}
						if ($extension = $db->setQuery($query)->loadObject())
						{
							$extension->params = new Registry($extension->params);

							// Check params
							$needUpdate = false;
							foreach ($this->extensionParams as $path => $value)
							{
								if (!$extension->params->exists($path))
								{
									$needUpdate = true;
									$extension->params->set($path, $value);
								}
							}

							// Update
							if ($needUpdate)
							{
								$extension->params = (string) $extension->params;
								$db->updateObject('#__extensions', $extension, 'extension_id');
							}
						}
					}
				}

			});
	}
};