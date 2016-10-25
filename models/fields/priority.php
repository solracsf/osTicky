<?php
/**
 * @package osTicky2 (osTicket 1.9.4 Bridge) for Joomla 3
 * @version 2.1: priority.php
 * @author Alex Polonski
 * @copyright (C) 2012 - 2014 - Alex Polonski
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
**/
defined('JPATH_BASE') or die;

jimport('joomla.html.html');
jimport('joomla.form.formfield');
jimport('joomla.form.helper');
JFormHelper::loadFieldClass('list');
require_once JPATH_COMPONENT_ADMINISTRATOR.'/helpers/osticky2.php';

class JFormFieldPriority extends JFormFieldList
{
	public $type = 'priority';
	
	public function getOptions()
	{
		$query	= 'SELECT priority_id AS value, priority_desc AS text FROM #__ticket_priority WHERE ispublic = 1';
		
		$db = osTicky2Helper::getDbo();
		$db->setQuery($query);
		try
		{
			$items = $db->loadObjectList();
		}
		catch(Exception $e)
		{
			$items = array();
		}
		
		$options = array();

		// Build the field options.
		if (!empty($items))
		{
			foreach ($items as $item)
			{
				$options[] = JHtml::_('select.option', $item->value, $item->text);
			}
		}

		array_unshift($options,  JHtml::_('select.option', '',  JText::_('COM_OSTICKY_TICKET_OPTION_PRIORITY_DEFAULT')));
		
		return $options;
	}
}