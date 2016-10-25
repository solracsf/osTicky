<?php
/**
 * @package osTicky2 (osTicket 1.9.4 Bridge) for Joomla 3
 * @version 2.1: ticket.php
 * @author Alex Polonski
 * @copyright (C) 2012 - 2014 - Alex Polonski
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
**/
defined('JPATH_PLATFORM') or die;

jimport('joomla.database.table');

class osTicky2TableTicket extends JTable
{
	public function __construct(&$db)
	{
		parent::__construct('#__ticket', 'ticket_id', $db);
	}

	public function load($keys = null, $reset = true)
	{
		$result = parent::load($keys, $reset);
		if($result)
		{
			// get generic status (open/closed) and inject it into table data
			$db = $this->getDbo();
			$db->setQuery('SELECT state FROM #__ticket_status WHERE id = ' . (int)$this->status_id);
			try
			{
				$status = $db->loadResult();
			}
			catch(RuntimeException $e)
			{
				$status = 'open';
			}
			$this->status = $status;
		}
		return $result;
	}
	
	public function check()
	{
		if(empty($this->user_id))
		{
			$this->setError(JText::_('COM_OSTICKY_ERROR_TICKET_USER_ID'));
			return false;
		}
		return true;

	}

	public function store($updateNulls = false)
	{
		if(!$this->ticket_id)
		{
			// Only set this for a new ticket
			$this->ip_address = $_SERVER['REMOTE_ADDR'];
			$now = osTicky2Helper::getDbTime();
			$this->created = $now;
			$this->source = 'Web';
		}
		
		// status property is not in ticket table any more
		unset($this->status);
		
		return parent::store($updateNulls);
	}

	public function delete($pk = null)
	{
		if($pk == null)
		{
			$k = $this->_tbl_key;
			$pk = $this->$k;
		}
		// Error handling for rollback in ticket/message saving
		$db = $this->getDbo();
		
		try
		{
			$query = 'DELETE FROM #__ticket_thread WHERE ticket_id = '.$pk;
			$db->setQuery($query);
			$db->query();
			
			$query = 'SELECT attach_id FROM #__ticket_attachment WHERE ticket_id = '.$pk;
			$db->setQuery($query);
			$attach_ids = $db->loadColumn();
			if($attach_ids)
			{
				$config['dbo'] = $this->getDbo();
				$attach_table = JTable::getInstance('Attachment', 'osTicky2Table', $config);
				foreach($attach_ids as $attach_id)
				{
					$attach_table->delete($attach_id);
				}
			}
		}
		catch(Exception $e)
		{
			$this->setError($e->getMessage());
		}
		return parent::delete($pk);
	}
}
