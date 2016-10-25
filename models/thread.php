<?php
/**
 * @package osTicky2 (osTicket 1.9.4 Bridge) for Joomla 3
 * @version 2.2.6: thread.php
 * @author Alex Polonski
 * @copyright (C) 2012 - 2014 - Alex Polonski
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
**/
// No direct access
defined('_JEXEC') or die;

jimport('joomla.application.component.modellist');
jimport('joomla.application.component.helper');

require_once JPATH_COMPONENT_ADMINISTRATOR.'/helpers/ticket.php';

class osTicky2ModelThread extends JModelList
{
	public function __construct($config = array())
	{
		if (empty($config['filter_fields'])) {
			$config['filter_fields'] = array(
				'created'
			);
		}
		require_once JPATH_COMPONENT_ADMINISTRATOR.'/helpers/osticky2.php';
		parent::__construct($config);
		parent::setDbo(osTicky2Helper::getDbo());
	}
	
	protected function getStoreId($id = '')
	{
		// Compile the store id.
		$id	.= ':'.$this->getState('filter.search', '');
		$id	.= ':'.$this->getState('ticketID', 0);
		$id	.= ':'.$this->getState('filter.email', '');
		
		return parent::getStoreId($id);
	}

	protected function getListQuery()
	{
		$ticketID = $this->getState('ticketID', 0);
		
		// Create a new query object.
		$db		= $this->getDbo();
		$filter_search = $this->getState('filter.search', '');
		$filter_date = $this->getState('filter.date', '');
		
		// we do not check current user's permissions to view messages:
		// the check was already done in getTicket()
		// so, no need to fitler selection by user's email
		
		
		// get all messages related to ticketID from thread table
		$query	= $db->getQuery(true);
		$query->select('a.id AS id, a.created AS created, a.body AS msg_text, a.poster AS staff_name, a.thread_type AS type');
		$query->from('#__ticket_thread AS a');
		$query->select('t.created AS ticket_created, t.number as ticketID');
		$query->innerJoin('#__ticket AS t ON a.ticket_id = t.ticket_id');
		
		$query->select('fev.value AS subject');
		$query->join('LEFT', '#__form_entry AS fe ON fe.object_id = t.ticket_id AND fe.object_type = '.$db->Quote('T').' AND fe.form_id = 2');
		$query->join('LEFT', '#__form_entry_values AS fev ON fev.entry_id = fe.id');
		$query->join('LEFT', '#__form_field AS ff ON fev.field_id = ff.id AND ff.name = '.$db->Quote('subject'));
		
		$query->select('COUNT(att.attach_id) as has_attachments');
		$query->join('LEFT OUTER', '#__ticket_attachment AS att ON a.id = att.ref_id');
		
		$query->where('t.number = '.$db->Quote($ticketID));
		$query->where('a.thread_type <> "N"');
		if($filter_search)
		{
			$query->where('a.body LIKE '.$db->Quote('%'.$filter_search.'%'));
		}
		if($filter_date)
		{
			$query->where('a.created > '.$db->Quote($filter_date));
		}
		$query->group('a.id');
	
		$query .= ' ORDER BY '
			.$this->getState('list.ordering', 'sort').' '.$this->getState('list.direction', 'ASC')
			.', type ASC, id ASC';
		
		return $query;
	}

	/**
	 * Get ticket with ticketID set in model's state and user's email
	 * @param	$ticket_id (optional) - if not empty, lookup the ticket by ticket_id
	 * 			(not ticket number - old ticketID) - takes precedence over ticketID
	 * 			from model state
	 * @return 	ticket object if found or 
	 * 			empty object if ticket not exists or user has no permission to view it
	 * 
	 */
	public function getTicket()
	{
		$ticketID = $this->getState('ticketID', 0);
		$ticket = TicketHelper::getTicketByNumber($this->getState('filter.email', ''), $ticketID);
		
		if($ticket instanceof Exception)
		{
			$this->setError($ticket->getMessage());
			return false;
		}
		if(!$ticket)
		{
			$this->setError(JText::_('COM_OSTICKY_ERROR_TICKET_NOT_FOUND'));
			return false;
		}
		
		return $ticket;
	}

	public function getItems()
	{
		if(!$items = parent::getItems())
		{
			return $items;
		}
		
		$tmp = array();
		
		foreach($items as $item)
		{
			if($item->has_attachments)
			{
				$db = osTicky2Helper::getDbo();
				$query = $db->getQuery(true);
				
				$query->select('att.attach_id, att.ref_id, att.file_id');
				$query->from('#__ticket_attachment AS att');
				$query->select('f.name AS file_name, f.size AS file_size, f.type AS file_type, f.`key` AS hash, f.signature');
				$query->innerJoin('#__file AS f ON f.id = att.file_id');
				$query->where('att.ref_id = '.$item->id);
				
				$db->setQuery($query);
				try
				{
					$attachments = $db->loadObjectList();
				}
				catch(Exception $e)
				{
					$this->setError($e->getMessage());
					return false;
				}
				foreach($attachments as $att) {
					$download_key = md5(session_id().$att->hash);
					$att_data = array(
						'attach_id'	=> $att->attach_id,
						'ref_id'	=> $att->ref_id,
						'file_id'	=> $att->file_id,
						'file_name'	=> $att->file_name,
						'file_size'	=> $att->file_size,
						'file_type'	=> $att->file_type,
						'hash'		=> $att->hash,
						'ref'		=> $download_key
					);
					if(isset($tmp[$item->id]))
					{
						$tmp[$item->id]->attachments[] = $att_data;
					}
					else 
					{
						$tmp[$item->id] = $item;
						$tmp[$item->id]->attachments = array();
						$tmp[$item->id]->attachments[] = $att_data;
					}
				}
			}
			else 
			{
				$tmp[$item->id] = $item;
				$tmp[$item->id]->attachments = array();
			}
			// Clean columns we don't need any more
			unset($tmp[$item->id]->has_attachments);
			
			$message_text = $tmp[$item->id]->msg_text;
			$images = preg_match_all('/"cid:([\w._-]{32})"/', $message_text, $matches);
			if($images)
			{
				foreach($matches[1] as $i => $match)
				{
					$attachment = array_filter($tmp[$item->id]->attachments, function ($att) use($match) {
						// ignore case for unique key match to provide compatibility with email
						// replies fetched by osTicket
						return strtolower($att['hash']) == strtolower($match);
					});
					if(!empty($attachment))
					{
						$attachment = array_pop($attachment);
						$hash = $attachment['ref'];
						$url = 'index.php?option=com_osticky2&view=attachment&format=raw&id='.$attachment['attach_id'];
						$message_text = str_replace($matches[0][$i], sprintf('"%s&ref=%s" data-cid="%s"', $url, $hash, $match), $message_text);
					}
				}
				$tmp[$item->id]->msg_text = $message_text;
			}
		}
		
		// Revert to indexed array
		$items = array_values($tmp);
	

		return $items;
	}
	
	public function getLastPageStart()
	{	
		$total = $this->getTotal();
		$limit = $this->getState('list.limit');
		
		$start = strtoupper($this->getState('list.direction', 'ASC')) == 'DESC' ? 0 : floor(($total - 1) / $limit) * $limit;
			
		$start = $total > $limit ? '&limitstart='.$start : '';
		return $start;
	}
		
	public function getPageWithMsgStart($msgID = null)
	{	
		if(empty($msgID))
		{
			$msgID = $this->getState('msgID');
		}
		list($type, $id) = explode(':', $msgID);
		$ticketID = $this->getState('ticketID', 0);
		if(empty($id) || !$ticketID)
		{
			return false;
		}
		// start special query with ordering
		$db = $this->getDbo();
		$query = $db->getQuery(true);
		
		$query->select('a.id');
		$query->from('#__ticket_thread AS a');
		$query->innerJoin('#__ticket AS t ON t.ticket_id = a.ticket_id');
		$query->where('t.number = '.$db->Quote($ticketID));
		$query->where('a.thread_type <> "N"');
		$db->setQuery($query);
		
		try
		{
			$result = $db->loadColumn();
		}
		catch(Exception $e)
		{
			$this->setError($e->getMessage());
			return false;
		}
		if(!$result || $num_row = array_search($id, $result) === false)
		{
			return false;
		}
		
		$total = count($result);
		$limit = $this->getState('list.limit');
		$num_row = array_search($id, $result);
		$start = floor($num_row / $limit) * $limit;
		
		$start = $total > $limit ? '&limitstart='.$start : '';
		return $start;
	}
	
	protected function populateState($ordering = 'created', $direction = 'ASC')
	{
		parent::populateState($ordering, $direction);
		
		// Looks like parent populateState is not processing return to the list
		// start, so we override this feature here. Check if this is a bug in
		// Joomla and remove the code below when it is corrected 
		$input = JFactory::getApplication()->input;
		$limitstart = $input->getUInt('limitstart', 0);
		$this->setState('list.start', $limitstart);
		
		$search = $this->getUserStateFromRequest($this->context.'.filter.search', 'filter_search', '');		
		$this->setState('filter.search', $search);
		
		// Date filter - for advanced search (not implemented) $$$
		$filter_date = $this->getUserStateFromRequest($this->context.'.filter.date', 'filter_date', '');		
		$this->setState('filter.date', $filter_date);

		$ticketID = urldecode($input->get('id', 0, 'STRING'));
		$this->setState('ticketID', $ticketID);
		$user = JFactory::getUser();
		$this->setState('filter.email', $user->get('email'));
		
		// used to highlight a particular message in thread
		$msgID = $input->get('msgID', '', 'string');
		$this->setState('msgID', $msgID);
	}
}
