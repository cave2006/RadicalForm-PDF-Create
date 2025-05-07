<?php
/*
 * @package    RadicalForm PDF Create Plugin
 * @version     __DEPLOY_VERSION__
 * @author      CaveDesign Studio - cavedesign.ru
 * @copyright   Copyright (c) 2009 - 2025 CaveDesign Studio. All Rights Reserved.
 * @license     GNU/GPL license: https://www.gnu.org/copyleft/gpl.html
 * @link        https://cavedesign.ru/
 */

namespace Joomla\Plugin\RadicalForm\CreatePDF\Extension;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Database\DatabaseInterface;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;
use Joomla\Filesystem\Folder;
use Joomla\Registry\Registry;

class CreatePDF extends CMSPlugin implements SubscriberInterface
{
	/**
	 * Load the language file on instantiation.
	 *
	 * @var    bool
	 *
	 * @since  0.0.3
	 */
	protected $autoloadLanguage = true;

	/**
	 * Loads the application object.
	 *
	 * @var  \Joomla\CMS\Application\CMSApplication
	 *
	 * @since  0.0.3
	 */
	protected $app = null;

	/**
	 * Is libraries loaded.
	 *
	 * @var bool
	 *
	 * @since 0.0.3
	 */
	protected bool $librariesLoad = false;

	/**
	 * Returns an array of events this subscriber will listen to.
	 *
	 * @return  array
	 *
	 * @since   0.0.3
	 */
	public static function getSubscribedEvents(): array
	{
		return [
			'onBeforeSendRadicalForm' => 'onBeforeSendRadicalForm',
		];
	}

	/**
	 * Method to create PDF file
	 *
	 * @param   Event  $event
	 *
	 * @throws \Exception
	 * @return void
	 *
	 * @since 0.0.3
	 */
	public function onBeforeSendRadicalForm(Event $event): void
	{
		$targetsData = $this->params->get('targets');

		if ($targetsData instanceof Registry)
		{
			$targetsData = $targetsData->toArray();
		}

		if (empty($targetsData))
		{
			return;
		}

		$args = $event->getArguments();
		$clearData = $args[0];
		$input     =& $args[1];
		$params    = $args[2];

		$inputTarget = $input['rfTarget'] ?? '';
		$activeTarget = null;
		$activeKey = null;

		// Поиск подходящего target
		foreach ($targetsData as $key => $target)
		{
			$title = $target->target_title ?? '';

			if ($title === $inputTarget)
			{
				$activeTarget = $target;
				$activeKey = $key;
				break;
			}
		}

		if (!$activeTarget)
		{
			return;
		}

		$template = $activeTarget->target_template ?? '';
		$countEnabled = (int)($activeTarget->count_enable ?? 0);
		$countTemp = '';

		if ($countEnabled === 1)
		{
			$countStartNumber = $activeTarget->count_start ?? '0001';
			$countTitle       = $activeTarget->count_title ?? 'П-{count}';
			$countLastNumber  = $activeTarget->count_last ?? '';

			$numberLength = strlen($countStartNumber);
			$lastNumber = !empty($countLastNumber) ? $countLastNumber : $countStartNumber;
			$nextNumber = str_pad((int)$lastNumber + 1, $numberLength, '0', STR_PAD_LEFT);

			$countTemp = str_replace(
				['{count}', '{{date}:{DD.MM.YYYY}}'],
				[$nextNumber, date('d.m.Y')],
				$countTitle
			);

			$clearData['count'] = $countTemp;
			$this->params->set("targets.$activeKey.count_last", $nextNumber);
			$this->savePluginParams();
		}

		$this->loadLibraries();

		$baseDir  = $params['uploaddir'];
		$uniq     = (string) $input['uniq'];
		$path     = $baseDir . "/fileupload";
		$filename = $uniq . '.pdf';

		Folder::create($path);
		$fullFilePath = $path . '/' . $filename;

		$phoneMask = '+7-%s-%s-%s';

		foreach ($clearData as $ph => $value)
		{
			if (stripos($ph, 'phone') !== false && is_string($value))
			{
				$digits = preg_replace('/\D+/', '', $value);

				if (strlen($digits) === 11 && $digits[0] === '8')
				{
					$digits = '7' . substr($digits, 1);
				}
				elseif (strlen($digits) === 10)
				{
					$digits = '7' . $digits;
				}

				if (strlen($digits) === 11)
				{
					$clearData[$ph] = sprintf(
						$phoneMask,
						substr($digits, 1, 3),
						substr($digits, 4, 3),
						substr($digits, 7, 4)
					);
				}
				else
				{
					$clearData[$ph] = '';
				}
			}
		}

		$filePath = JPATH_PLUGINS . '/radicalform/createpdf/templates/' . $template;

		if (!file_exists($filePath))
		{
			throw new \Exception('Template file not found: ' . $filePath);
		}

		$templateHtml = file_get_contents($filePath);
		$replacements = ['{{ count }}' => $countTemp];

		foreach ($clearData as $k => $value)
		{
			$placeholder = '{{ ' . $k . ' }}';
			$replacements[$placeholder] = $this->valueToString($value);
		}

		$finalHtml = str_replace(array_keys($replacements), array_values($replacements), $templateHtml);
		$finalHtml = preg_replace('/\{\{.*?\}\}/', '', $finalHtml);

		try
		{
			$mpdf = new \Joomla\Libraries\JMpdf\JMpdf($finalHtml);
			$mpdf->save($fullFilePath);
		}
		catch (\JMpdf\JMpdfException $e)
		{
			die ('mPDF error: ' . $e->getMessage());
		}
		catch (\Exception $e)
		{
			die ('Error: ' . $e->getMessage());
		}

		$input['needToSendFiles'] = true;
	}

	/**
	 * Load mPDF Library
	 *
	 * @return void
	 *
	 * @since __DEPLOY_VERSION__
	 */
	protected function loadLibraries()
	{
		if (!$this->librariesLoad)
		{
			\JLoader::registerNamespace('\\Joomla\\Libraries\\JMpdf', JPATH_LIBRARIES . '/mpdf/src');
			$this->librariesLoad = true;
		}
	}

	/**
	 * Saves plugin parameters
	 *
	 * @return bool
	 *
	 * @since __DEPLOY_VERSION__
	 */
	protected function savePluginParams(): bool
	{
		$db = Factory::getContainer()->get(DatabaseInterface::class);
		$query = $db->getQuery(true)
			->update($db->quoteName('#__extensions'))
			->set($db->quoteName('params') . ' = ' . $db->quote($this->params->toString()))
			->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
			->where($db->quoteName('folder') . ' = ' . $db->quote('radicalform'))
			->where($db->quoteName('element') . ' = ' . $db->quote('createpdf'));

		$db->setQuery($query);

		try {
			return $db->execute();
		} catch (\RuntimeException $e) {
			$this->app->enqueueMessage('Error saving plugin parameters: ' . $e->getMessage(), 'error');
			return false;
		}
	}

	/**
	 * Recursively converts a value (including nested arrays) to a string.
	 *
	 * @param   mixed  $value
	 *
	 * @return  string
	 *
	 * @since   0.0.3
	 */
	private function valueToString(mixed $value): string
	{
		if (is_array($value))
		{
			return htmlspecialchars(implode(', ', array_map([$this, 'valueToString'], $value)),
				ENT_QUOTES, 'UTF-8');
		}

		if (is_bool($value))
		{
			return $value ? 'Да' : 'Нет';
		}

		return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
	}


}