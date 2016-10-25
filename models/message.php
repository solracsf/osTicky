<?php
/**
 * @package osTicky2 (osTicket 1.9.4 Bridge) for Joomla 3
 * @version 2.1: message.php
 * @author Alex Polonski
 * @copyright (C) 2012 - 2014 - Alex Polonski
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
**/
// No direct access
defined('_JEXEC') or die;

jimport('joomla.application.component.modelform');

require_once JPATH_COMPONENT_ADMINISTRATOR.'/helpers/osticky2.php';
require_once JPATH_COMPONENT_ADMINISTRATOR.'/helpers/ticket.php';

class osTicky2ModelMessage extends JModelForm
{
	protected $view_item = 'thread';
	protected $view_list = 'thread';

	public function __construct($config = array())
	{
		parent::__construct($config);
		parent::setDbo(osTicky2Helper::getDbo());
	}
	
	public function getTable($type = 'Message', $prefix = 'osTicky2Table', $config = array())
	{
		JTable::addIncludePath(JPATH_ADMINISTRATOR.'components/osticky2/tables');
		$config['dbo'] = $this->getDbo();
		return JTable::getInstance($type, $prefix, $config);
	}
	
	public function getForm($data = array(), $loadData = true)
	{
		// Get the form.
		$form = $this->loadForm('com_osticky2.message', 'message', array('control' => 'jform', 'load_data' => $loadData));
		if(empty($form))
		{
			return false;
		}
		$canAttach = osTicky2Helper::getOstConfig('allow_attachments') &&
			osTicky2Helper::getOstConfig('allow_online_attachments') &&
			(JFactory::getUser()->get('id') || !osTicky2Helper::getOstConfig('allow_online_attachments_onlogin'));
		if(!$canAttach)
		{
			$form->removeField('filedata');
		}
		else 
		{
			$form->setFieldAttribute('filedata', 'description', JText::sprintf('COM_OSTICKY_FILE_UPLOAD_DESC', osTicky2Helper::getOstConfig('allowed_filetypes')));
			// do not set this attribute - compatibility issues...
			//$form->setFieldAttribute('filedata', 'accept', osTicky2Helper::getAcceptedMimeTypes());
		}
		
		$enableHtml = osTicky2Helper::getOstConfig('enable_html_thread');
		if(empty($enableHtml))
		{
			$form->setFieldAttribute('body', 'type', 'textarea');
			$form->setFieldAttribute('body', 'rows', '10');
			$form->setFieldAttribute('body', 'cols', '80');
			$form->setFieldAttribute('body', 'filter', 'safehtml');
		}
		else
		{
			// get preferred editor from the component's options, fallback
			// to global editor option
			$params = JComponentHelper::getParams('com_osticky2');
			$editor = $params->get('editor', '');
			if(empty($editor))
			{
				$editor = JFactory::getConfig()->get('editor', 'none');
			}
			$form->setFieldAttribute('body', 'editor', $editor.'|none');
		}
		
		return $form;
	}

	protected function loadFormData()
	{
		$app = JFactory::getApplication();
		$data = (array)$app->getUserState('com_osticky2.message.data', array());
		
		if(empty($data))
		{
			// this value was set by thread view when adding message model
			// to its models array. The view already checked that this ticket
			// belongs to user, so we can trust it
			$data['ticket_id'] = $this->getState('ticket_id', 0);
		}
		elseif($data['ticket_id'] != $this->getState('ticket_id', 0))
		{
			// make sure that data in session is not associated with another ticket
			// if so, clean data and preset correct ticket_id
			$data = array('ticket_id' => $this->getState('ticket_id', 0));
			$app->setUserState('com_osticky2.message.data', $data);
		}
		
		return $data;
	}
	
	public function submit($data, $files = null, $isReply = true)
	{		
		// if this message is a new ticket message we allow save
		// even if the user has no permission to reply in existing message thread
		$canCreate = osTicky2Helper::getActions()->get('osticky.reply', 0) || !$isReply;
		if(!$canCreate || empty($data['ticket_id']))
		{
			$this->setError(JText::_('JERROR_ALERTNOAUTHOR'));
			return false;
		}
		
		$params = JComponentHelper::getParams('com_osticky2');
		$app = JFactory::getApplication();
		
		jimport('joomla.filesystem.file');
		
		// Check if file attachments are allowed in osTicket config
		$canAttach = osTicky2Helper::getOstConfig('allow_attachments') &&
			osTicky2Helper::getOstConfig('allow_online_attachments') &&
			(JFactory::getUser()->get('id') || !osTicky2Helper::getOstConfig('allow_online_attachments_onlogin'));
		
		// get the ticket
		$ticket = TicketHelper::getTicketById($data['ticket_id']);
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
		
		$enableHtml = osTicky2Helper::getOstConfig('enable_html_thread');
		if(!$enableHtml)
		{
			// wrap plain text in <pre/> container
			$data['body'] = '<pre>'.$data['body'].'</pre>';
		}
		else
		{
			// if html is enabled, message body can contain images. Scan for <img/> tags
			// and add them as attachments to the ticket/message
			
			// do search
			$images = preg_match_all('#<img src=\"([^\"]*)\".*\/>#siU', $data['body'], $matches);
			
			if($images > 0)
			{
				// found
				foreach($matches[1] as $index => $match)
				{
					$path = JPATH_ROOT . '/' . $match;
					if(file_exists($path))
					{
						// if the file is found, add it to the files array, it will be
						// processed later, after the message is saved
						$file = array(
							'name' => JFile::getName($path),
							'tmp_name' => $path
						);
						// add key and signature to file data, so that they are generated only once - 
						// *** microtime is included as a prefix to the key, so the key will be different
						// for the same file even if the method below is called again in a few lines
						// of code
						list($file['key'], $file['signature']) = self::_getKeyAndHash($path, true);
						if(!isset($files))
						{
							$files = array();
						}
						$files[] = $file;
						// replace <img/> src attribute by attachment link (compatible with osTicket format)
						$data['body'] = str_replace($match, 'cid:'.strtolower($file['key']), $data['body']);
					}
				}
			}
		}
		
		// Construct client/sticky info
		$canSend = empty($data['hide_client_info']) && $params->get('send_client_info');
		
		$client_info = !empty($data['client_info']) && $canSend ? $data['client_info'] : '';
		$sticky_info = !empty($data['sticky_info']) ? $data['sticky_info'] : '';
		$info_string = $client_info.$sticky_info;
		
		if(!$isReply)
		{
			// append client/sticky info to message if this is the fisrt
			// message in thread (not reply)
			if(!empty($info_string))
			{
				$data['body'] .= '<pre>'.$info_string.'</pre>';
			}
			// collect additional data for use in file attachment
			$ticketID = $data['ticketID'];
			$email = $data['email'];

			// clean data before saving to table
			unset($data['client_info']);
			unset($data['sticky_info']);
			unset($data['ticketID']);
			unset($data['email']);
		}
		
		$data['poster'] = $ticket->name;
		$data['user_id'] = $ticket->user_id;
				
		$table = $this->getTable();
		
		if(!$table->save($data))
		{
			$this->setError($table->getError());
			return false;
		}
		
		// File attachment - after message save
		if($canAttach && !empty($files))
		{
			$config['dbo'] = $this->getDbo();
			$attach_table = JTable::getInstance('Attachment', 'osTicky2Table', $config);
			$file_table = JTable::getInstance('File', 'osTicky2Table', $config);
			
			foreach($files as $file)
			{
				$result = false;
				
				$attach_table->reset();
				$attach_table->attach_id = 0;
				$attach_table->ticket_id = $table->ticket_id;
				$attach_table->inline = 0;
				$attach_table->ref_id = $table->id;
				
				$file_table->reset();
				$file_table->id = 0;
				$file_table->name = osTicky2Helper::makeSafe($file['name']);
				$file_table->created = $table->created; // copy the value from the message table
				$file_table->size = @filesize($file['tmp_name']);
				$file_table->type = osTicky2Helper::getMimeTypeFromExt(JFile::getExt($file['name']));
				
				$file_table->filedata = JFile::read($file['tmp_name']);
				
				if(!isset($file['key']))
				{
					// add key and signature
					list($file_table->key, $file_table->signature) = self::_getKeyAndHash($file['tmp_name'], true);
				}
				else
				{
					// we already have the key and the signature generated (e.g. html images as attachments)
					$file_table->key = $file['key'];
					$file_table->signature = $file['signature'];
				}
				
				if($file_table->check() && $file_table->store())
				{
					$attach_table->created = $table->created;
					$attach_table->file_id = $file_table->id;
					if(!($result = $attach_table->check() && $attach_table->store()))
					{
						$file_table->delete();
					}
				}
				
				if(!$result)
				{
					$app->enqueueMessage(JText::sprintf('COM_OSTICKY_WARNING_ATTACHMENT_FAILED', $file['name']), 'warning');
				}
			}
			// File attachment errors are not fatal errors.
			// The message itself was saved ok at this point, 
			// so we enqueue a warning (if any) and continue
		}
		
		// Info attachment - after ticket save
		// Only attach client/sticky info if set in params and if
		// this message is the first ticket message (not a reply)
		if(!$isReply &&
			$params->get('system_info_attach', 1) == 2 &&
			$canAttach &&
			!empty($info_string)) // if info_string is empty - nothing to attach 
		{
			// prepend email and ticketID to info text as ticket reference
			$ticket_ref = "\nFor ticketID: ".$ticketID.", From email: ".$email."\n\n";
			$info_string = $ticket_ref.$info_string;
			
			// Create attachment entry in db
			$config['dbo'] = $this->getDbo();
			$attach_table = JTable::getInstance('Attachment', 'osTicky2Table', $config);
			$attach_table->ticket_id = $table->ticket_id;
			
			$attach_table->ref_id = $table->id;
			$result = false;
			$file_table = JTable::getInstance('File', 'osTicky2Table', $config);
			$file_table->name = $ticketID.'.txt';
			$file_table->created = $table->created; // this is redundant, as it is the same value as created from attachment table, but this is how osTicket 1.7 behaves...
			$file_table->size = strlen($info_string);
			$file_table->type = 'text/plain';
			
			$file_table->filedata = $info_string;
			list($file_table->key, $file_table->signature) = self::_getKeyAndHash($file_table->filedata);
			if($file_table->check() && $file_table->store())
			{
				$attach_table->created = $table->created;
				$attach_table->file_id = $file_table->id;
				if(!($result = $attach_table->check() && $attach_table->store())) {
					$file_table->delete();
				}
			}
		
			if(!$result)
			{
				$app->enqueueMessage(JText::_('COM_OSTICKY_WARNING_ATTACHMENT_FAILED'), 'warning');
			}
			// File attachment errors are not fatal errors.
			// The message itself was saved ok at this point, 
			// so we enqueue a warning (if any) and continue
		}
		
		// update ticket activity
		$ticket_id = $table->ticket_id;
		
		// reassigned flag, will be set inside updateTicketActivity() method
		$is_reassigned = false;
		
		$ticketModel = JModelLegacy::getInstance('Ticket', 'osTicky2Model', array('db' => osTicky2Helper::getDbo()));
		if(!$ticketModel->updateTicketActivity($ticket_id, $table->created, $isReply, $is_reassigned))
		{
			// something went wrong while updating ticket activity
			// (delete this message to ensure the database integrity - not used)
			$this->setError($ticketModel->getError());
			$table->delete(); //*** with transaction support this line is not needed, but just
			// in case (e.g. Joomla 2.5 with default table engine that doesn't
			// support transactions
			return false;
		}
		if(!$isReply)
		{
			// this is a message that goes with a new ticket
			// autoresponse and alerts should be issued for ticket
			$result = osTicky2Helper::autoEmailTicket($table->id);
		}
		else 
		{
			// this is a message that goes with existing ticket
			// autoresponse and alerts should be issued for message
			// we add a special flag for reopened tickets, so that assigned alert
			// can be sent
			$result = osTicky2Helper::autoEmailMessage($table->id, $is_reassigned);
		}
		if(!$result || $result instanceof Exception)
		{
			$app->enqueueMessage(!$result ? JText::_('COM_OSTICKY_WARNING_EMAIL_FAILED') : $result->getMessage(), 'warning');
		}
		// email error is not a fatal error. Message is saved correctly at this point,
		// so we enqueue a warning (if any) and continue
		
		return true;
	}
	
	/*
	 * Code from native osTicket 1.8.2
	 */
	private function _getKeyAndHash($data=false, $file=false) {
        if ($file) {
            $sha1 = base64_encode(sha1_file($data, true));
            $md5 = base64_encode(md5_file($data, true));
        }
        else {
            $sha1 = base64_encode(sha1($data, true));
            $md5 = base64_encode(md5($data, true));
        }

        // Use 5 chars from the microtime() prefix and 27 chars from the
        // sha1 hash. This should make a sufficiently strong unique key for
        // file content. In the event there is a sha1 collision for data, it
        // should be unlikely that there will be a collision for the
        // microtime hash coincidently.  Remove =, change + and / to chars
        // better suited for URLs and filesystem paths
        $prefix = base64_encode(sha1(microtime(), true));
        $key = str_replace(
            array('=','+','/'),
            array('','-','_'),
            substr($prefix, 0, 5) . $sha1);

        // The hash is a 32-char value where the first half is from the last
        // 16 chars from the SHA1 hash and the last 16 chars are the last 16
        // chars from the MD5 hash. This should provide for better
        // resiliance against hash collisions and attacks against any one
        // hash algorithm. Since we're using base64 encoding, with 6-bits
        // per char, we should have a total hash strength of 192 bits.
        $hash = str_replace(
            array('=','+','/'),
            array('','-','_'),
            substr($sha1, 0, 16) . substr($md5, 0, 16));

        return array($key, $hash);
    }
}
