<?php
/**
 * @package osTicky2 (osTicket 1.9.4 Bridge) for Joomla 3 - 3.0
 * @subpackage plg_search_osticky
 * @version 2.1: osticky2.php
 * @author Alex Polonski
 * @copyright (C) 2012 - 2014 - Alex Polonski
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
**/
// no direct access
defined('_JEXEC') or die;

include_once JPATH_SITE.'/components/com_osticky2/router.php';
include_once JPATH_ADMINISTRATOR.'/components/com_osticky2/helpers/osticky2.php';

class plgSearchOsticky2 extends JPlugin
{
	public function __construct(& $subject, $config)
	{
		parent::__construct($subject, $config);
		$this->loadLanguage();
	}
	
	/**
	 * @return array An array of search areas
	 */
	function onContentSearchAreas()
	{
		static $areas = array(
			'osticky' => 'PLG_SEARCH_OSTICKY2_OSTICKY'
			);
			return $areas;
	}

	/**
	 * Content Search method
	 * The sql must return the following fields that are used in a common display
	 * routine: href, title, section, created, text, browsernav
	 * @param string Target search string
	 * @param string mathcing option, exact|any|all
	 * @param string ordering option, newest|oldest|popular|alpha|category
	 * @param mixed An array if the search it to be restricted to areas, null if search all
	 */
	function onContentSearch($text, $phrase='', $ordering='', $areas=null)
	{
		if(!file_exists(JPATH_ADMINISTRATOR . '/components/com_osticky2/helpers/osticky2.php'))
		{
			return array();
		}
		$ost_version = osTicky2Helper::getOstConfig('ost_version');
		if(empty($ost_version))
		{
			return array();
		}
		
		$db		= osTicky2Helper::getDbo();
		$app	= JFactory::getApplication();
		$email	= JFactory::getUser()->get('email');
		
		if(empty($email))
		{
			return array();
		}

		$searchText = $text;
		if (is_array($areas)) {
			if (!array_intersect($areas, array_keys($this->onContentSearchAreas()))) {
				return array();
			}
		}

		$sTickets		= $this->params->get('search_tickets',		1);
		$sMessages		= $this->params->get('search_messages',		1);
		$limit			= $this->params->def('search_limit',		50);

		$text = trim($text);
		if ($text == '') {
			return array();
		}

		$rows = array();
		$query	= $db->getQuery(true);
		
		if ($sTickets && $limit > 0)
		{			
			switch ($ordering) {
				case 'oldest':
					$order = 't.created ASC';
					break;
	
				case 'popular':
					$order = 'num_messages DESC';
					break;
	
				case 'alpha':
					$order = 'fev.value ASC';
					break;
	
				case 'category':
				case 'newest':
				default:
					$order = 't.created DESC';
					break;
			}
			
			switch ($phrase) {
				case 'exact':
					$text		= $db->Quote('%'.$db->escape($searchText, true).'%', false);
					$where		= 'fev.value LIKE '.$text;
					break;
	
				case 'all':
				case 'any':
				default:
					$words = explode(' ', $searchText);
					$wheres = array();
					foreach ($words as $word) {
						$word		= $db->Quote('%'.$db->escape($word, true).'%', false);
						$wheres[]	= 'fev.value LIKE '.$word;
					}
					$where = '(' . implode(($phrase == 'all' ? ') AND (' : ') OR ('), $wheres) . ')';
					break;
			}
			
			$query->clear();
			
			$query->select('t.number AS ticketID, t.created AS created');
			$query->from('#__ticket AS t');
			
			$query->select('fev.value AS text')
				//->join('LEFT', '#__form_entry AS fe ON fe.object_id = t.ticket_id AND fe.object_type = '.$db->Quote('T').' AND fe.form_id = 2')
				->join('LEFT', '#__form_entry AS fe ON fe.object_id = t.ticket_id AND fe.object_type = '.$db->Quote('T'))
				->join('LEFT', '#__form_entry_values AS fev ON fev.entry_id = fe.id')
				->join('LEFT', '#__form_field AS ff ON fev.field_id = ff.id AND ff.name = '.$db->Quote('subject'));
			
			$query->select('COUNT(DISTINCT(m.id)) AS num_messages, \'2\' AS browsernav, "Tickets" AS section');
			$query->join('LEFT', '#__ticket_thread AS m ON m.ticket_id = t.ticket_id AND m.thread_type <> "N"');
			
			$query->join('LEFT', '#__user AS u ON u.id = t.user_id');
			$query->join('LEFT', '#__user_email AS ue ON ue.user_id = u.id');
			$query->where('ue.address = '.$db->Quote($email));
			
			$query->where($where);
			$query->group('t.number');
			$query->order($order);
			
			$db->setQuery($query, 0, $limit);
			try
			{
				$list = $db->loadObjectList();
			}
			catch(Exception $e)
			{
				$app->enqueueMessage($e->getMessage(), 'warning');
				return array();
			}
			$limit -= count($list);

			if (isset($list))
			{
				foreach($list as $key => $item)
				{
					$list[$key]->title = JText::sprintf('PLG_SEARCH_OSTICKY2_TICKET_TITLE', $item->text, $item->ticketID, $item->num_messages);
					$list[$key]->href = JRoute::_('index.php?option=com_osticky2&view=thread&id='.urlencode($item->ticketID));
				}
			}
			$rows[] = $list;
		}
		
		if ($sMessages && $limit > 0)
		{			
			switch ($ordering) {
				case 'oldest':
					$order = 'created ASC';
					break;
	
				case 'alpha':
					$order = 'text ASC';
					break;
	
				case 'category':
					$order = 'type ASC';
					break;
					
				case 'newest':
				case 'popular':
				default:
					$order = 'created DESC';
					break;
			}
			
			switch ($phrase) {
				case 'exact':
					$text		= $db->Quote('%'.$db->escape($searchText, true).'%', false);
					$where		= 'a.body LIKE '.$text;
					break;
	
				case 'all':
				case 'any':
				default:
					$words = explode(' ', $searchText);
					$wheres = array();
					foreach ($words as $word) {
						$word		= $db->Quote('%'.$db->escape($word, true).'%', false);
						$wheres[]	= 'a.body LIKE '.$word;
					}
					$where = '(' . implode(($phrase == 'all' ? ') AND (' : ') OR ('), $wheres) . ')';
					break;
			}
			$query	= $db->getQuery(true);
			$query->select('a.id, a.created AS created, a.body AS text, NULL AS staff_name, a.thread_type AS type');
			$query->from('#__ticket_thread AS a');
			$query->select('t.created AS ticket_created, t.number AS ticketID');
			
			$query->select('fev.value AS subject')
				//->join('LEFT', '#__form_entry AS fe ON fe.object_id = a.ticket_id AND fe.object_type = '.$db->Quote('T').' AND fe.form_id = 2')
				->join('LEFT', '#__form_entry AS fe ON fe.object_id = a.ticket_id AND fe.object_type = '.$db->Quote('T'))
				->join('LEFT', '#__form_entry_values AS fev ON fev.entry_id = fe.id')
				->join('LEFT', '#__form_field AS ff ON fev.field_id = ff.id AND ff.name = '.$db->Quote('subject'));
			
			$query->select('\'2\' AS browsernav, "Ticket Messages" AS section');
			$query->innerJoin('#__ticket AS t ON a.ticket_id = t.ticket_id');
			
			$query->join('LEFT', '#__user AS u ON u.id = t.user_id');
			$query->join('LEFT', '#__user_email AS ue ON ue.user_id = u.id');
			$query->where('ue.address = '.$db->Quote($email));
			
			$query->where('a.thread_type <> "N"');
			$query->where($where);
			$query->group('a.id');
		
			$db->setQuery($query, 0, $limit);
			try
			{
				$list = $db->loadObjectList();
			}
			catch(Exception $e)
			{
				$app->enqueueMessage($e->getMessage(), 'warning');
				return array();
			}
			if (isset($list))
			{
				foreach($list as $key => $item)
				{
					$msg_key = $item->type.':'.$item->id;
					$list[$key]->href = (string)JRoute::_('index.php?option=com_osticky2&task=thread.scrolltopagewithmsg&msgID='.$msg_key.'&id='.urlencode($item->ticketID));
					$list[$key]->title = JText::sprintf('PLG_SEARCH_OSTICKY2_MESSAGE_TITLE', $item->subject, $item->ticketID);
				}
			}
			$rows[] = $list;
		}
		
		$results = array();
		if (count($rows))
		{
			foreach($rows as $row)
			{
				$new_row = array();
				foreach($row as $key => $item) {
					if (searchHelper::checkNoHTML($item, $searchText, array('text', 'subject')))
					{
						$new_row[] = $item;
					}
				}
				$results = array_merge($results, (array) $new_row);
			}
		}

		return $results;
	}
}
