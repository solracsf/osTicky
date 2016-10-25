<?php
/**
 * @package osTicky2 (osTicket 1.9.4 Bridge) for Joomla 3
 * @version 2.1: attachment.php
 * @author Alex Polonski
 * @copyright (C) 2012 - 2014 - Alex Polonski
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
**/
defined('JPATH_PLATFORM') or die;

jimport('joomla.database.table');

require_once JPATH_COMPONENT_ADMINISTRATOR.'/helpers/osticky2.php';

class osTicky2TableAttachment extends JTable
{
	public function __construct(&$db)
	{
		parent::__construct('#__ticket_attachment', 'attach_id', $db);
	}

	public function check()
	{
		if(!$this->ticket_id || !$this->ref_id)
		{
			return false;
		}
		return true;
	}

	public function store($updateNulls = false)
	{

		return parent::store($updateNulls);
	}
	
	public function delete($pk = null)
	{
		if($pk == null)
		{
			$k = $this->_tbl_key;
			$pk = $this->$k;
		}

		return parent::delete($pk);
	}
}
