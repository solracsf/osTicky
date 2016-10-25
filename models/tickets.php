<?php
/**
 * @package osTicky2 (osTicket 1.9.4 Bridge) for Joomla 3
 * @version 2.1: tickets.php
 * @author Alex Polonski
 * @copyright (C) 2012 - 2014 - Alex Polonski
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
**/
// No direct access
defined('_JEXEC') or die;

jimport('joomla.application.component.modellist');
jimport('joomla.application.component.helper');

class osTicky2ModelTickets extends JModelList
{
	public function __construct($config = array())
	{
		if (empty($config['filter_fields'])) {
			$config['filter_fields'] = array(
				'a.number', 'ticketID',
				'ts.state', 'status',
				'subject',
				'a.created',
				'b.dept_name'
			);
		}
		require_once JPATH_COMPONENT_ADMINISTRATOR.'/helpers/osticky2.php';
		$config['dbo'] = osTicky2Helper::getDbo();
		
		parent::__construct($config);
		
	}
	
	protected function getStoreId($id = '')
	{
		// Compile the store id.
		$id	.= ':'.$this->getState('filter.search', '');
		$id	.= ':'.$this->getState('filter.email', '');
		$id	.= ':'.$this->getState('filter.status', '');
		
		return parent::getStoreId($id);
	}

	protected function getListQuery()
	{
		// Create a new query object.
		$db		= $this->getDbo();
		$query	= $db->getQuery(true);
		$filter_search = $this->getState('filter.search', '');
		$filter_status = $this->getState('filter.status', '');
		
		$query->select('a.*, a.number AS ticketID');
		$query->from('#__ticket AS a');
		
		$query->select('b.dept_name');
		$query->join('LEFT', '#__department AS b ON b.dept_id = a.dept_id');
		
		$query->select('ht.topic AS help_topic');
		$query->innerJoin('#__help_topic AS ht ON ht.topic_id = a.topic_id');
		
		$query->select('ts.state AS status');
		$query->innerJoin('#__ticket_status AS ts ON ts.id = a.status_id');
		
		$query->select('fev.value AS subject');
		$query->join('LEFT', '#__form_entry AS fe ON fe.object_id = a.ticket_id AND fe.object_type = '.$db->Quote('T').' AND fe.form_id = 2');
		$query->join('LEFT', '#__form_entry_values AS fev ON fev.entry_id = fe.id');
		$query->join('LEFT', '#__form_field AS ff ON fev.field_id = ff.id AND ff.name = '.$db->Quote('subject'));
		
		$query->select('COUNT(DISTINCT r.id) AS count_responses');
		$query->join('LEFT OUTER', '#__ticket_thread AS r ON r.ticket_id = a.ticket_id AND r.thread_type = "R"');
	
		$query->select('COUNT(DISTINCT m.id) AS count_messages');
		$query->join('LEFT', '#__ticket_thread AS m ON m.ticket_id = a.ticket_id AND m.thread_type = "M"');
			
		$query->select('COUNT(DISTINCT att.attach_id) AS count_attachments');
		$query->join('LEFT OUTER', '#__ticket_attachment AS att ON att.ticket_id = a.ticket_id');
			
		$query->select('fm.id AS sticky2_id, fm.ticket_id AS fm_ticket_id, fm.body AS message, fm.sticky2_url');
		$subquery = '
			(SELECT m.id, m.ticket_id, m.body, 
			CASE WHEN LOCATE("{url}", m.body) > 0 
			THEN SUBSTRING_INDEX(SUBSTRING_INDEX(m.body, "{url}", -1), "{/url}", 1) ELSE "" 
			END AS sticky2_url 
			FROM #__ticket_thread AS m
			WHERE m.thread_type = "M"  
			ORDER BY m.created ASC)';
		$query->innerJoin($subquery.' AS fm ON fm.ticket_id = a.ticket_id');
			
		$query->join('LEFT', '#__user AS u ON u.id = a.user_id');
		$query->join('LEFT', '#__user_email AS ue ON ue.user_id = u.id');
		$query->where('ue.address = '.$db->Quote($this->getState('filter.email', '')));
		
		// only public statuses
		$query->where('(ts.state = ' . $db->Quote('open') . ' OR ts.state = ' . $db->Quote('closed') . ')');
		
		if($filter_search)
		{
			$query->where(
				// basic search: ticket number and subject fields only. Search in custom fields implemented in
				// osticky2 search plugin.
				'((fev.value LIKE '.$db->Quote('%'.$filter_search.'%').' AND ff.name = '.$db->Quote('subject').') OR '.
				'a.number LIKE '.$db->Quote('%'.$filter_search.'%').')');
		}
		if($filter_status)
		{
			$query->where('ts.state = '.$db->Quote($filter_status));
		}
		$query->group('a.ticket_id');
		
		$query->order($this->getState('list.ordering', 'a.created').' '.$this->getState('list.direction', 'ASC'));
		//$test = (string)$query;
		return $query;
	}
	
	public function getItems()
	{
		$items = parent::getItems();
		if(empty($items))
		{
			return $items;
		}
		
		foreach($items as $i => $item)
		{
			// add and clean some data
			$items[$i]->thread_link = (string)JRoute::_('index.php?option=com_osticky2&view=thread&id='.urlencode($item->ticketID));
			$items[$i]->sticky2_url = preg_replace('#[\s]#', '', $item->sticky2_url);
			$items[$i]->created_wf = osTicky2Helper::dateFromOSTwFormat($items[$i]->created, 'datetime_format');
		}
		
		// validate sticky links
		if($validated = osTicky2Helper::validateStickyLinks($items))
		{
			$items = $validated;
		}
		
		return $items;
	}
	
	protected function populateState($ordering = 'a.created', $direction = 'DESC')
	{
		parent::populateState($ordering, $direction);
		
		// Looks like parent populateState is not processing return to the list
		// start, so we override this feature here. Check if this is a bug in
		// Joomla and remove the code below when it is corrected
		$input = JFactory::getApplication()->input;
		$limitstart = $input->getUInt('limitstart', 0);
		$this->setState('list.start', $limitstart);
		
		$search = $this->getUserStateFromRequest($this->context.'.filter.search', 'filter_search');		
		$this->setState('filter.search', $search);
		
		$status = $this->getUserStateFromRequest($this->context.'.filter.status', 'filter_status');		
		$this->setState('filter.status', $status);
		
		$user = JFactory::getUser();
		$this->setState('filter.email', $user->get('email'));
	}
}
