<?php
/**
 * @package osTicky2 (osTicket 1.9.4 Bridge) for Joomla 3
 * @version 2.1: helptopic.php
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

class JFormFieldHelpTopic extends JFormFieldList
{
	public $type = 'helptopic';
	
	public function getOptions()
	{
		$key	= $this->element['key_field'] ? (string) $this->element['key_field'] : 'value';
		$value	= $this->element['value_field'] ? (string) $this->element['value_field'] : (string) $this->element['name'];
		$translate = $this->element['translate'] ? (string) $this->element['translate'] : false;
		$hide_private = $this->element['hide_private'] ? (int) $this->element['hide_private'] : 1;
		
		$db = osTicky2Helper::getDbo();		
		
		$user_groups = JFactory::getUser()->getAuthorisedGroups();
		$private_topics_groups = JComponentHelper::getParams('com_osticky2')->get('show_private_topics', array());
		$can_view_private = count(array_intersect($user_groups, $private_topics_groups)) > 0;
		
		$query = $db->getQuery(true);
		$query->select('ht.topic_id, IF(ht.topic_pid, CONCAT(htp.topic, " / ", ht.topic), ht.topic) AS topic');
		$query->from('#__help_topic AS ht');
		$query->join('LEFT', '#__help_topic AS htp ON htp.topic_id = ht.topic_pid');
		$query->where('ht.isactive = 1');
		if(!$can_view_private || $hide_private) {
			// private topic available for the users in groups
			// (if set in options)
			$query->where('ht.ispublic = 1');
		}
		$query->order('topic ASC');
	
		$db->setQuery($query);
		try
		{
			$items = $db->loadObjectlist();
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
				if ($translate == true)
				{
					$options[] = JHtml::_('select.option', $item->$key, JText::_($item->$value));
				}
				else
				{
					$options[] = JHtml::_('select.option', $item->$key, $item->$value);
				}
			}
		}

		// Merge any additional options in the XML definition.
		$options = array_merge(parent::getOptions(), $options);

		return $options;
		
	}
}