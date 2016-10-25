<?php
/**
 * @package osTicky2 (osTicket 1.9.4 Bridge) for Joomla 3
 * @version 2.1: attachments.php
 * @author Alex Polonski
 * @copyright (C) 2012 - 2014 - Alex Polonski
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 **/
defined('JPATH_PLATFORM') or die;

class JFormFieldAttachments extends JFormField
{
	public $type = 'Attachments';

	protected function getInput()
	{
		$canAttach = osTicky2Helper::getOstConfig('allow_attachments') &&
			osTicky2Helper::getOstConfig('allow_online_attachments') &&
			(JFactory::getUser()->get('id') || !osTicky2Helper::getOstConfig('allow_online_attachments_onlogin'));
		
		if(!$canAttach)
		{
			return '';	
		}
		
		$allowed_types = trim(osTicky2Helper::getOstConfig('allowed_filetypes'));
		
		if(empty($allowed_types) || $allowed_types == '*')
		{
			$allowed_types = '.*';
		}
		$files_limit = osTicky2Helper::getOstConfig('max_user_file_uploads');
		$max_file_size = osTicky2Helper::getOstConfig('max_file_size');
		
		$doc = JFactory::getDocument();
		
		$root = JURI::root().'components/com_osticky2/models/fields/attachments/';
		
		$doc->addScript($root.'js/vendor/jquery.multifile.js');
		$doc->addScript($root.'js/attachments.js');
		
		$doc->addStyleSheet($root.'css/style.css');
		
		$options = array(
			'files_limit' => $files_limit,
			'file_types' => $allowed_types,
			'max_file_size' => $max_file_size
		);
		
		// *** JSON_NUMERIC_CHECK needed for 'max_file_size' property!
		$script = '
			jQuery.noConflict();
			window.addEvent("domready", function () {
				attachments.init(' . json_encode($options, JSON_NUMERIC_CHECK) . ');
			});
		';
		
		$doc->addScriptDeclaration($script);
		
		$html = array();
		
		//$html[] = '<div style="clear:both;"></div>';
		
		
        $html[] = '<input type="file" class="multi" name="' . $this->name . '[]" />';
        
		return implode("\n", $html);
	}
}
