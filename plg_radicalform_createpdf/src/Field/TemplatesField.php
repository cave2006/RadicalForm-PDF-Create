<?php
/*
 * @package    RadicalForm PDF Create Plugin
 * @version     __DEPLOY_VERSION__
 * @author      CaveDesign Studio - cavedesign.ru
 * @copyright   Copyright (c) 2009 - 2025 CaveDesign Studio. All Rights Reserved.
 * @license     GNU/GPL license: https://www.gnu.org/copyleft/gpl.html
 * @link        https://cavedesign.ru/
 */

namespace Joomla\Plugin\RadicalForm\CreatePDF\Field;

\defined('_JEXEC') or die;

use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\Language\Text;
use Joomla\Filesystem\Folder;

class TemplatesField extends ListField
{
	/**
	 * The form field type.
	 *
	 * @var  string Tne field type name.
	 *
	 * @since 0.0.3
	 */
	protected $type = 'templates';

	/**
	 * Field options array.
	 *
	 * @var  array
	 *
	 * @since  0.0.3
	 */
	protected $_options = null;

	/**
	 * Method to get the list of options.
	 *
	 * @return  array The field option objects.
	 *
	 * @since 0.0.3
	 */
	protected function getOptions(): array
	{
		$options = [];

		$folderPath = JPATH_PLUGINS . '/radicalform/createpdf/templates';

		if (is_dir($folderPath))
		{
			$files = Folder::files($folderPath, '\.html$', false, false); // только HTML-файлы

			foreach ($files as $file)
			{
				$options[] = (object) [
					'value' => $file,
					'text'  => $file,
				];
			}
		}

		array_unshift($options, (object) [
			'value' => '',
			'text'  => Text::_('JSELECT'),
		]);

		return array_merge(parent::getOptions(), $options);
	}
}
