<?php
/**
 * @package osTicky2 (osTicket 1.9.4 Bridge) for Joomla 3
 * @version 2.1: message.php
 * @author Alex Polonski
 * @copyright (C) 2012 - 2014 - Alex Polonski
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
**/
defined('JPATH_PLATFORM') or die;

jimport('joomla.database.table');

class osTicky2TableMessage extends JTable
{
	public function __construct(&$db)
	{
		parent::__construct('#__ticket_thread', 'id', $db);
	}

	public function check()
	{
		if(!$this->ticket_id)
		{
			$this->setError(JText::_('COM_OSTICKY_ERROR_TICKET_NOT_FOUND'));
			return false;
		}
		$this->body = trim($this->body);
		if(empty($this->body))
		{
			$this->setError(JText::_('COM_OSTICKY_ERROR_TICKET_MESSAGE_EMPTY'));
			return false;
		}
		return true;
	}

	public function store($updateNulls = false)
	{
		$this->ip_address = $_SERVER['REMOTE_ADDR'];
		$now = osTicky2Helper::getDbTime();
		$this->created = $now;
		$this->source = 'Web';
		$this->thread_type = 'M';
		
		return parent::store($updateNulls);
	}
	
	public function delete($pk = null)
	{
		if($pk == null)
		{
			$k = $this->_tbl_key;
			$pk = $this->$k;
		}
		
		$db = $this->getDbo();
		$query = 'SELECT attach_id FROM #__ticket_attachment WHERE ref_id = '.$pk;
		$db->setQuery($query);
		try
		{
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
