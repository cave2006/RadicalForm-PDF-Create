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
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Folder;

class CreatePDF extends CMSPlugin implements SubscriberInterface
{
	/**
	 * Load the language file on instantiation.
	 *
	 * @var    bool
	 *
	 * @since  __DEPLOY_VERSION__
	 */
	protected $autoloadLanguage = true;

	/**
	 * Loads the application object.
	 *
	 * @var  \Joomla\CMS\Application\CMSApplication
	 *
	 * @since  __DEPLOY_VERSION__
	 */
	protected $app = null;

	/**
	 * Is libraries loaded.
	 *
	 * @var bool
	 *
	 * @since __DEPLOY_VERSION__
	 */
	protected bool $librariesLoad = false;

	/**
	 * Returns an array of events this subscriber will listen to.
	 *
	 * @return  array
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public static function getSubscribedEvents(): array
	{
		return [
			'onBeforeSendRadicalForm' => 'onBeforeSendRadicalForm',
			'onExtensionBeforeSave'   => 'onExtensionBeforeSave',
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
	 * @since __DEPLOY_VERSION__
	 */
	public function onBeforeSendRadicalForm(Event $event): void
	{
		// 1. Абсолютный baseDir
		$args = $event->getArguments();

		// Распаковываем их корректно
		$clearData = $args[0];
		$input     =& $args[1];
		$params    = $args[2];

		if (($input['rfTarget'] ?? '') !== 'createPDF')
		{
			return;
		}

		$this->loadLibraries();

		$baseDir  = $params['uploaddir'];
		$uniq     = (string) $input['uniq'];
		$field    = 'fileupload';
		$path     = $baseDir . "/fileupload";
		$filename = $uniq . '.pdf';

		Folder::create($path);

		$fullFilePath = $path . '/' . $filename;

		/**
		 * Method recursively turns any value (including a nested array) into a string.
		 *
		 * @param   mixed  $value
		 *
		 * @return string
		 *
		 * @since __DEPLOY_VERSION__
		 */
		function valueToString(mixed $value): string
		{
			if (is_array($value))
			{
				return htmlspecialchars(implode(', ', array_map(__FUNCTION__, $value)), ENT_QUOTES, 'UTF-8');
			}
			// Ensure boolean 'true'/'false' are represented nicely if needed, e.g., 'Да'/'Нет'
			if (is_bool($value))
			{
				return $value ? 'Да' : 'Нет';
			}

			return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
		}

		if (isset($clearData['f-Client-phone']))
		{
			preg_match('/(\+?\d[\d\s\(\)-]+)/', $clearData['f-Client-phone'], $matches);
			if (!empty($matches[1]))
			{
				$clearData['f-Client-phone'] = trim($matches[1]);
			}
			else
			{
				$clearData['f-Client-phone'] = '';
			}
		}

		// 1. Загрузка шаблона
		$filePath = JPATH_PLUGINS . '/radicalform/createpdf/templates/code.html';
		if (file_exists($filePath))
		{
			$templateHtml = file_get_contents($filePath);
		}
		else
		{
			throw new \Exception('Template file not found: ' . $filePath);
		}

		// 2. Подготовка данных для замены
		$replacements = [];
		foreach ($clearData as $key => $value)
		{
			$placeholder                = '{{ ' . $key . ' }}';
			$stringValue                = valueToString($value);
			$replacements[$placeholder] = $stringValue;
		}

		// 3. Замена плейсхолдеров в шаблоне
		$finalHtml = str_replace(array_keys($replacements), array_values($replacements), $templateHtml);

		// 4. Очистка незаполненных плейсхолдеров (на всякий случай)
		$finalHtml = preg_replace('/\{\{.*?\}\}/', '', $finalHtml); // Удаляет любые оставшиеся {{...}}


		// 5. Генерация PDF
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

	protected function loadLibraries()
	{
		if (!$this->librariesLoad)
		{
			\JLoader::registerNamespace('\\Joomla\\Libraries\\JMpdf', JPATH_LIBRARIES . '/mpdf/src');
			$this->librariesLoad = true;
		}
	}

	public function onExtensionBeforeSave($context, $table, $isNew)
	{
		// Убедимся, что это сохранение настроек плагина
		if ($context !== 'com_plugins.plugin' || $table->extension_id !== $this->_db->setQuery(
				'SELECT extension_id FROM #__extensions WHERE element = ' . $this->_db->quote($this->_name) .
				' AND folder = ' . $this->_db->quote($this->_type) .
				' AND type = ' . $this->_db->quote('plugin')
			)->loadResult()) {
			return true;
		}

		$app   = Factory::getApplication();
		$input = $app->getInput();
		$jform = $input->files->get('jform', [], 'array'); // Получаем массив jform из $_FILES

		// Имя поля из XML
		$fieldName = 'target_template_upload';

		// Проверяем наличие файла в jform[params]
		$file = $jform['params'][$fieldName] ?? null;

		// Если файла нет или он не был загружен через POST, ничего не делаем
		if (!$file || !is_uploaded_file($file['tmp_name'])) {
			// Если файл был выбран, но произошла ошибка при загрузке (кроме UPLOAD_ERR_NO_FILE)
			if (isset($file['error']) && $file['error'] !== UPLOAD_ERR_NO_FILE && $file['error'] !== UPLOAD_ERR_OK) {
				$app->enqueueMessage(Text::sprintf('JLIB_FORM_FIELD_MEDIA_UPLOAD_ERRNO', $file['error']), 'error');
				// Можно вернуть false, чтобы прервать сохранение, если загрузка обязательна
				// return false;
			}
			// Файл не был выбран или загружен, просто продолжаем сохранение остальных параметров
			return true;
		}

		// Проверяем ошибку загрузки
		if ($file['error'] !== UPLOAD_ERR_OK) {
			$app->enqueueMessage(Text::sprintf('JLIB_FORM_FIELD_MEDIA_UPLOAD_ERRNO', $file['error']), 'error');
			return false; // Прерываем сохранение при ошибке
		}

		// Проверяем расширение
		$extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
		if ($extension !== 'html') {
			$app->enqueueMessage(Text::_('PLG_RADICALFORM_CREATEPDF_ONLY_HTML_FILES'), 'error');
			return false; // Прерываем сохранение
		}

		// Определяем папку назначения
		$destDir = JPATH_PLUGINS . '/radicalform/createpdf/templates';

		// Создаем папку, если её нет (с проверкой прав)
		if (!Folder::exists($destDir)) {
			if (!Folder::create($destDir, 0755)) {
				$app->enqueueMessage(Text::sprintf('PLG_RADICALFORM_CREATEPDF_ERROR_CREATE_DIR', $destDir), 'error');
				return false; // Прерываем сохранение
			}
		}

		// Проверяем права на запись в папку
		if (!is_writable($destDir)) {
			$app->enqueueMessage(Text::sprintf('PLG_RADICALFORM_CREATEPDF_ERROR_DIR_NOT_WRITABLE', $destDir), 'error');
			return false; // Прерываем сохранение
		}


		// Формируем безопасное имя файла и полный путь
		$safeFileName = File::makeSafe($file['name']);
		$destPath     = $destDir . '/' . $safeFileName;

		// Проверяем, существует ли файл (можно добавить опцию перезаписи)
		if (File::exists($destPath)) {
			// Решите, что делать: перезаписать или выдать ошибку
			// Вариант 1: Ошибка
			$app->enqueueMessage(Text::sprintf('PLG_RADICALFORM_CREATEPDF_ERROR_FILE_EXISTS', $safeFileName), 'warning');
			// Не прерываем сохранение, просто не загружаем файл снова
			// Но очищаем значение поля в форме, чтобы оно не "висело" выбранным
			if (isset($table->params['target_template_upload'])) {
				$table->params['target_template_upload'] = ''; // Или null
			}
			return true;

			// Вариант 2: Перезапись (если нужно)
			// File::delete($destPath);
		}

		// Перемещаем загруженный файл
		if (!File::upload($file['tmp_name'], $destPath)) {
			$app->enqueueMessage(Text::_('PLG_RADICALFORM_CREATEPDF_ERROR_SAVING_FILE'), 'error');
			return false; // Прерываем сохранение
		} else {
			$app->enqueueMessage(Text::sprintf('PLG_RADICALFORM_CREATEPDF_FILE_UPLOAD_SUCCESS', $safeFileName), 'message');
			// Очищаем значение поля в форме после успешной загрузки
			if (isset($table->params['target_template_upload'])) {
				$table->params['target_template_upload'] = ''; // Или null
			}
		}

		// Не нужно сохранять имя файла в параметры для этого поля
		// unset($table->params[$fieldName]); // Можно явно удалить, если Joomla его туда добавляет

		return true; // Продолжаем сохранение остальных параметров
	}
}