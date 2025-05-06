<?php
/*
 * @package    RadicalForm PDF Create Plugin
 * @version     0.0.3
 * @author      CaveDesign Studio - cavedesign.ru
 * @copyright   Copyright (c) 2009 - 2025 CaveDesign Studio. All Rights Reserved.
 * @license     GNU/GPL license: https://www.gnu.org/copyleft/gpl.html
 * @link        https://cavedesign.ru/
 */

namespace Joomla\Plugin\RadicalForm\CreatePDF\Field;

\defined('_JEXEC') or die;

use Joomla\CMS\Form\Field\FileField;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;
use Joomla\CMS\WebAsset\WebAssetManager;

class UploadField extends FileField
{
	protected $type = 'upload';

	protected function getInput()
	{
		// Устанавливаем параметры для input type="file"
		$this->accept = '.html';

		// Регистрируем JavaScript через WebAssetManager
		$this->registerScript();

		// Устанавливаем filter="raw", чтобы Joomla не пыталась очистить значение
		$this->filter = 'raw';

		// Важно: Убедимся, что значение поля пустое, т.к. мы не храним путь
		$this->value = '';


		return parent::getInput();
	}

	protected function registerScript()
	{
		/** @var WebAssetManager $wa */
		$wa = Factory::getApplication()->getDocument()->getWebAssetManager();

		// Добавляем перевод строки для JS
		Text::script('PLG_RADICALFORM_CREATEPDF_ONLY_HTML_FILES');

		$js = <<<JS
        document.addEventListener('DOMContentLoaded', function() {
            const input = document.getElementById('{$this->id}');
            if (input) {
                input.addEventListener('change', function(e) {
                    if (this.files.length) {
                        const file = this.files[0];
                        // Проверяем, что имя файла заканчивается на .html (без учета регистра)
                        if (!file.name.toLowerCase().endsWith('.html')) {
                            // Используем Joomla API для показа сообщений
                            Joomla.renderMessages({
                                error: [Joomla.Text._('PLG_RADICALFORM_CREATEPDF_ONLY_HTML_FILES')]
                            });
                            // Очищаем поле выбора файла
                            this.value = '';
                        }
                    }
                });
            }
        });
        JS;

		$wa->addInlineScript($js, ['name' => 'field.upload.' . $this->id]);
	}

}