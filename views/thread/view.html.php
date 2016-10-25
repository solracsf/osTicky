<?php
/**
 * @package osTicky2 (osTicket 1.9.4 Bridge) for Joomla 3
 * @version 2.2.6: view.html.php
 * @author Alex Polonski
 * @copyright (C) 2012 - 2014 - Alex Polonski
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
**/
// No direct access
defined('_JEXEC') or die;

jimport('joomla.application.component.view');

class osTicky2ViewThread extends JViewLegacy
{
	protected $state;
	protected $params;
	protected $ticket;
	protected $messages;
	protected $form;
	protected $pagination;
	
	protected $thread_closed;
	protected $msgID;

	function display($tpl = null)
	{
		$app			= JFactory::getApplication();
		$params			= $app->getParams();
		
		$this->params 	= $params;

		// Get some data from the models
		$this->state		= $this->get('State');
		$this->ticket		= $this->get('Ticket');
		$this->messages		= $this->get('Items');
		$this->pagination	= $this->get('Pagination');

		$canDo = osTicky2Helper::getActions();
		
		// check if the therad is closed
		if($this->ticket->status == 'closed')
		{
			if(empty($this->ticket->status_properties->allowreopen))
			{
				// set closed flag, so that message form will not be rendered and
				// a notice will be displayed
				$this->thread_closed = true;
			}
		}
		
		if($canDo->get('osticky.reply') && !empty($this->ticket) && empty($this->thread_closed))
		{
			// get message form
			$messageModel = JModelLegacy::getInstance('Message', 'osTicky2Model', array('ignore_request' => true));
			$messageModel->setState('ticket_id', $this->ticket->ticket_id);
			$messageModel->setState('filter.email', JFactory::getUser()->get('email'));
			$this->setModel($messageModel);
			$this->form	= $this->get('Form', 'message');
		}
		
		// Check for errors.
		if (count($errors = $this->get('Errors'))) {
			JError::raiseError(500, implode("\n", $errors));
			return false;
		}
		// If the ticket was not found redirect to tickets list
		if(!$this->ticket)
		{
			$app->redirect(JRoute::_('index.php?option=com_osticky2&view=tickets', false), JText::_('COM_OSTICKY_ERROR_TICKET_NOT_FOUND'), 'warning');
			return false;
		}
				
		// Add script to force form submit on navigation links click
		// without this workaround unsaved message text would be lost
		$script = '
			window.addEvent("domready", function() {
				var page_links = $$("a.pagenav");
				page_links.each(function(el) {
					el.addEvent("click", function(event) {
						event.stop();
						// store page url for use in controller redirect
						// so we keep the same functionality of pagenav links
						document.forms["adminForm"].elements["nav_link"].value = el.href;
						// document.id("nav_link").value = el.href;
						document.id("adminForm").submit();
					});
				});
				// remove width and height properties from images in message html
				// rely on the original image size limited by 100% max-width in css
				$$("div.osticky-message-text img[data-cid!=\'\']").removeProperties("width", "height");
			});
			
		';
				
		$document = JFactory::getDocument();
		
		$document->addScriptDeclaration($script);
		
		// if msgID is set include script that scrolls to selected message
		if($this->state->get('msgID', ''))
		{
			$scrollScript = '
			window.addEvent("load", function() {
				window.location.href=document.location+"#highlight";
			})';
			$document->addScriptDeclaration($scrollScript);
		}
		
		JFactory::getDocument()->addStyleSheet(JURI::base(true).'/components/com_osticky2/css/styles30.css');
		$this->_prepareDocument();
		
		parent::display($tpl);
	}

	protected function _prepareDocument()
	{
		$app		= JFactory::getApplication();
		$menus		= $app->getMenu();
		
		$ticketID	= $this->ticket->ticketID;
		$title = JText::sprintf('COM_OSTICKY_THREAD_PAGE_TITLE', $ticketID);
		
		$menu = $menus->getActive();
		if ($menu)
		{
			$this->params->def('page_heading', $this->params->get('page_title', $menu->title));
		}
		else
		{
			$this->params->def('page_heading', JText::sprintf('COM_OSTICKY_THREAD_PAGE_TITLE', $ticketID));
		}
		
		if ($app->getCfg('sitename_pagetitles', 0) == 1)
		{
			$title = JText::sprintf('JPAGETITLE', $app->getCfg('sitename'), $title);
		}
		elseif ($app->getCfg('sitename_pagetitles', 0) == 2)
		{
			$title = JText::sprintf('JPAGETITLE', $title, $app->getCfg('sitename'));
		}
		$this->document->setTitle($title);
	}
}
