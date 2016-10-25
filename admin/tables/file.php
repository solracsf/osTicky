<?php
/**
 * @package osTicky2 (osTicket 1.9.4 Bridge) for Joomla 3
 * @version 2.1: file.php
 * @author Alex Polonski
 * @copyright (C) 2012 - 2014 - Alex Polonski
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
**/
defined('JPATH_PLATFORM') or die;

jimport('joomla.database.table');

require_once JPATH_COMPONENT_ADMINISTRATOR.'/helpers/osticky2.php';

class osTicky2TableFile extends JTable
{
	public function __construct(&$db)
	{
		parent::__construct('#__file', 'id', $db);
	}

	public function check()
	{
		$this->name = osTicky2Helper::makeSafe($this->name);
		if(empty($this->name))
		{
			return false;
		}
		return true;
	}

	public function store($updateNulls = false)
	{
		// filedata should be stored in "file_chunk" table, so keep it for
		// future use but erase it from "file" table data
		$filedata = $this->filedata;
		unset($this->filedata);
		
		// Store file info
		if(parent::store($updateNulls)) {
			// Store file data in chuncks in file_chunks table (new in 1.7RC3)
			foreach(str_split($filedata, 500 * 1024) as $i => $chunk) {
				$query = 'INSERT INTO #__file_chunk (file_id, chunk_id, filedata) VALUES('.$this->id.', '.$i.', '.$this->_db->quote($chunk).')';
				$this->_db->setQuery($query);
				try
				{
					$result = $this->_db->execute();
				}
				catch(Exception $e)
				{
					$this->setError($e->getMessage());
					$this->delete();
					return false;
				}
				if(!$result) {
					$this->delete();
					return false;
				}
			}
			
			return true;
		}
		return false;
	}
	
	public function delete($pk = null)
	{
		if(parent::delete($pk)) {
			// delete chunks also
			$query = 'DELETE FROM #__file_chunk WHERE file_id = '.(int)$pk;
			$this->_db->setQuery($query);
			try
			{
				$result = $this->_db->execute();
			}
			catch(Exception $e)
			{
				$this->setError($e->getMessage());
				return false;
			}
			return $result;
		}
		return false;
	}
}
