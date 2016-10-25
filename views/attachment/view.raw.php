<?php
/**
 * @package osTicky2 (osTicket 1.9.4 Bridge) for Joomla 3
 * @version 2.2.7: view.html.php
 * @author Alex Polonski
 * @copyright (C) 2012 - 2014 - Alex Polonski
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
**/
// No direct access
defined('_JEXEC') or die;

jimport('joomla.application.component.view');

require_once JPATH_COMPONENT_ADMINISTRATOR.'/helpers/osticky2.php';

class osTicky2ViewAttachment extends JViewLegacy
{
	protected $attachment;
	protected $state;
	
	function display($tpl = null)
	{
		$this->state = $this->get('State');
		$this->attachment = $this->get('Item');
		
		if($this->attachment === false)
		{
			// an error occurred getting attachment data.
			echo '';
			return;
		}
		
		ob_start();
		
		$hash = md5(JFactory::getSession()->getId().$this->attachment->hash);
		if(strcmp($this->state->get('ref', ''), $hash))
		{
			die('Invalid file ref');
		}
		$filename = $this->attachment->file_name;
		$ctype = $this->attachment->file_type;
		
		$document = JFactory::getDocument();
		$document->setMimeEncoding($ctype);
		
		ob_end_clean();
		
		JResponse::setHeader('Pragma', 'public');
		JResponse::setHeader('Expires', '0');
		JResponse::setHeader('Cache-Control', 'must-revalidate, post-check=0, pre-check=0');
		JResponse::setHeader('Cache-Control', 'public');
		JResponse::setHeader('Content-Type', $ctype);
		
		JResponse::setHeader('Content-disposition', 'attachment; filename="'.basename($filename).'"; creation-date="'.$this->attachment->created.'"', true);
		
        JResponse::setHeader('Content-Transfer-Encoding', 'binary');
		JResponse::setHeader('Content-Length', $this->attachment->file_size);
		
		echo $this->attachment->filedata;
	}
}
