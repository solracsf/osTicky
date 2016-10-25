<?php
/**
 * @package osTicky2 (osTicket 1.9.4 Bridge) for Joomla 3
 * @version 2.1: ostconfig.php
 * @author Alex Polonski
 * @copyright (C) 2012 - 2014 - Alex Polonski
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
**/
defined('JPATH_BASE') or die;

jimport('joomla.html.html');
jimport('joomla.form.formfield');
jimport('joomla.form.helper');
JFormHelper::loadFieldClass('list');

require_once JPATH_ROOT.'/administrator/components/com_osticky2/helpers/osticky2.php';

class JFormFieldOstconfig extends JFormFieldList
{
	public $type = 'ostconfig';
	
	public function getOptions()
	{
		$db = osTicky2Helper::getDbo();
		
		// check if we have ost_config table
		$db->setQuery('SELECT * FROM #__config');
		try
		{
			$items = $db->loadObjectlist();
			if(empty($items))
			{
				// Joomla 2.5 workaround
				$options = array(JHtml::_('select.option', '', JText::_('COM_OSTICKY_ERROR_DATABASE_NOT_FOUND')));
				return $options;
			}
		}
		catch(Exception $e)
		{
			$options = array(JHtml::_('select.option', '', JText::_('COM_OSTICKY_ERROR_DATABASE_NOT_FOUND')));
			return $options;
		}
		
		// ost_config table found, check compatible version
		try
		{
			// check for gte 1.8 (1.7 doesn't have ost_user table)
			$tables = $db->getTableList();
			if(!in_array($db->getPrefix() . 'user', $tables))
			{
				throw new Exception();
			}
			
			// check for namespace column - 1.6 doesn't have it
			$db->setQuery('SELECT DISTINCT(namespace) FROM #__config');
			$items = $db->loadColumn();
			
			if(empty($items))
			{
				// Joomla 2.5 workaround
				throw new Exception();
			}
				
			// read namespaces from DB - normal flow
			foreach ($items as $item) {
				$options[] = JHtml::_('select.option', $item, $item);
			}
			
			return $options;
		}
		catch(Exception $e)
		{
			$options = array(JHtml::_('select.option', '', JText::_('COM_OSTICKY_ERROR_INCOMPATIBLE_VERSION')));
			return $options;
		}
	}
}