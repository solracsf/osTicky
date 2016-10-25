<?php
/**
 * @package osTicky2 (osTicket 1.9.4 Bridge) for Joomla 3
 * @version 2.1.2: file.php
 * @author Alex Polonski
 * @copyright (C) 2012 - 2014 - Alex Polonski
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
**/
defined('JPATH_PLATFORM') or die;

require_once JPATH_COMPONENT_ADMINISTRATOR.'/helpers/osticky2.php';
jimport('joomla.filesystem.file');

class JFormRuleFile extends JFormRule
{
	public function test(SimpleXMLElement $element, $value, $group = null, JRegistry $input = null, JForm $form = null)
	{
		$allowed_types = trim(osTicky2Helper::getOstConfig('allowed_filetypes'));
		if(empty($allowed_types) || $allowed_types == '*')
		{
			// support .* for no file type check
			$types = array();
		}
		else 
		{
			$types = explode(',', $allowed_types);
			$types = array_map('trim', $types);
		}
		$max_file_size = osTicky2Helper::getOstConfig('max_file_size');
		
		$input = JFactory::getApplication()->input;
		$files = $input->files->get('jform');
		
		$files = !empty($files['filedata']) ? $files['filedata'] : array();
		foreach($files as $i => $file)
		{
			if(!empty($file['error']) || count($file) != 5)
			{
				unset($files[$i]);
			}
		}
		
		if(empty($files)) {
			return true;
		}
		
		foreach($files as $file)
		{
			$filename = $file['name'];
			$filesize = $file['size'];
			$ext = '.'.strtolower(JFile::getExt($filename));
			
			if($filename && !empty($types) && !in_array($ext, $types))
			{
				// invalid type - set error message
				$element['message'] = !empty($element['message_type']) ? (string)$element['message_type'] : 'Bad file type';
				return false;
			}
			if($filesize > $max_file_size )
			{
				// invalid size - set error message
				$element['message'] = !empty($element['message_size']) ? (string)$element['message_size'] : 'File too large';
				return false;
			}
		}
		$element['message'] = '';
		return true;
	}
}
