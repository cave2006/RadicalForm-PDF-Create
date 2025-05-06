<?php
/*
 * @package     RadicalForm PDF Create Package
 * @version     0.0.3
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
				 * @since  0.0.3
				 */
				protected AdministratorApplication $app;

				/**
				 * The Database object.
				 *
				 * @var   DatabaseDriver
				 *
				 * @since  0.0.3
				 */
				protected DatabaseDriver $db;

				/**
				 * Minimum Joomla version required to install the extension.
				 *
				 * @var  string
				 *
				 * @since  0.0.3
				 */
				protected string $minimumJoomla = '5.0';

				/**
				 * Minimum PHP version required to install the extension.
				 *
				 * @var  string
				 *
				 * @since  0.0.3
				 */
				protected string $minimumPhp = '8.0';

				/**
				 * Minimum MySQL version required to install the extension.
				 *
				 * @var  string
				 *
				 * @since  0.0.3
				 */
				protected string $minimumMySQL = '8.0';

				/**
				 * Minimum MariaDb version required to install the extension.
				 *
				 * @var  string
				 *
				 * @since  0.0.3
				 */
				protected string $minimumMariaDb = '10.0';

				/**
				 * Language constant for errors.
				 *
				 * @var string
				 *
				 * @since 0.0.3
				 */
				protected string $constant = "PKG_RADICALFORM_CREATEPDF";

				/**
				 * Update methods.
				 *
				 * @var  array
				 *
				 * @since  0.0.3
				 */
				protected array $updateMethods = [];

				/**
				 * Constructor.
				 *
				 * @param   AdministratorApplication  $app  The application object.
				 *
				 * @since 0.0.3
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
				 * @since   0.0.3
				 */
				public function install(InstallerAdapter $adapter): bool
				{
					return true;
				}

				/**
				 * Function called after the extension is updated.
				 *
				 * @param   InstallerAdapter  $adapter  The adapter calling this method
				 *
				 * @return  boolean  True on success
				 *
				 * @since   0.0.3
				 */
				public function update(InstallerAdapter $adapter): bool
				{
					return true;
				}

				/**
				 * Function called after the extension is uninstalled.
				 *
				 * @param   InstallerAdapter  $adapter  The adapter calling this method
				 *
				 * @return  boolean  True on success
				 *
				 * @since   0.0.3
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
				 * @since   0.0.3
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
				 * @since   0.0.3
				 */
				public function postflight(string $type, InstallerAdapter $adapter): bool
				{
					$installer = $adapter->getParent();
					if ($type !== 'uninstall')
					{
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
				 * Method to check compatible.
				 *
				 * @throws  \Exception
				 *
				 * @return  bool True on success, False on failure.
				 *
				 * @since  0.0.3
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

			});
	}
};