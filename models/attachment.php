<?php
/**
 * @package osTicky2 (osTicket 1.9.4 Bridge) for Joomla 3
 * @version 2.2.7: attachment.php
 * @author Alex Polonski
 * @copyright (C) 2012 - 2014 - Alex Polonski
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
**/
// No direct access
defined('_JEXEC') or die;

jimport('joomla.application.component.modelitem');

require_once JPATH_COMPONENT_ADMINISTRATOR.'/helpers/osticky2.php';

class osTicky2ModelAttachment extends JModelItem
{
	protected $view_item = 'attachment';

	public function __construct($config = array())
	{
		parent::__construct($config);
		parent::setDbo(osTicky2Helper::getDbo());
	}
	
	public function getTable($type = 'Attachment', $prefix = 'osTicky2Table', $config = array())
	{
		JTable::addIncludePath(JPATH_ADMINISTRATOR.'components/osticky2/tables');
		$config['dbo'] = $this->getDbo();
		return JTable::getInstance($type, $prefix, $config);
	}
	
	protected function populateState()
	{
		$app = JFactory::getApplication('site');
		$input = $app->input;
		
		// Load the parameters.
		$params = $app->getParams();
		$this->setState('params', $params);
		
		$id = $input->getInt('id', 0);
		$ref = $input->getString('ref', '');
		$this->setState('id', $id);
		$this->setState('ref', $ref);
		
		// look for file_id request param, it will be used if
		// id is not set
		$file_id = $input->getInt('file_id', 0);
		$this->setState('file_id', $file_id);
	}
	
	/*
	 * Get attached file data by attachment id ('id' request param) or by file_id ('file_id' request param)
	 */
	public function getItem($id = null)
	{
		if(empty($id))
		{
			$id = $this->getState('id', 0);
		}
		$table = $this->getTable();
		
		if($table->load($id) && !empty($table->file_id))
		{
			// get file_id from ticket attachments table *** this will not find
			// canned attachments!
			$file_id = $table->file_id;
		}
		else
		{
			// read file id from request
			$file_id = $this->getState('file_id');
			if(empty($file_id))
			{
				// fatal error - file_is missing in both ticket table and request
				return false;
			}
			$table = new stdClass();
		}
		
		$db = $this->getDbo();
		
		try
		{
			$query = 'SELECT f.name as file_name, f.size as file_size, f.type as file_type, f.`key` AS hash, f.created FROM #__file AS f WHERE f.id = '.(int)$file_id;
			$db->setQuery($query);
			$file_info = $db->loadAssoc();
			foreach($file_info as $k => $v) {
				$table->$k = $v;
			}
			$query = 'SELECT chunk.filedata FROM #__file_chunk AS chunk WHERE file_id = '.(int)$file_id.' ORDER BY chunk.chunk_id ASC';
			$db->setQuery($query);
		
			$chunks = $db->loadColumn();
			$table->filedata = '';
			foreach($chunks as $chunk) {
				$table->filedata .= $chunk;
			}
		}
		catch(Exception $e)
		{
			return false;
		}
		
		return $table;
	}
}
