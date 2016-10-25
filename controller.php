<?php
/**
 * @package osTicky2 (osTicket 1.9.4 Bridge) for Joomla 3 - 3.0
 * @version 2.2.8: controller.php
 * @author Alex Polonski
 * @copyright (C) 2012 - 2014 - Alex Polonski
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
**/
// No direct access
defined('_JEXEC') or die;

jimport('joomla.application.component.controller');

require_once JPATH_COMPONENT_ADMINISTRATOR.'/helpers/osticky2.php';

class osTicky2Controller extends JControllerLegacy
{
	public function display($cachable = false, $urlparams = false)
	{
		// Get the document object.
		$document	= JFactory::getDocument();
		
		$cachable = false;
		$app	= JFactory::getApplication();
		$input	= $app->input;
		
		$vName	= $input->get('view', 'tickets');
		$input->set('view', $vName);
		
		$type	= $input->getWord('format', 'html');
		
		$view = $this->getView($vName, $type, 'osTicky2View');
		if($model = $this->getModel($vName))
		{
			$view->setModel($model, true);
		}
		
		$ost_info = osTicky2Helper::getOstInfo();
		
		if(!empty($ost_info->error))
		{
			if($vName != 'confirm')
			{
				// if we get a fatal "osTicket not found" error at this point, redirect to confirm view
				// if not already there
				$query_append = ($vName == 'ticket_modal' ?
					'&tmpl=component&function=plg_osticky2_ticket.close&infoid=__plg_osticky_info' :
					'');
				$error_data = implode(':', array('COM_OSTICKY_OST_NOT_FOUND_DESC', '', '', ''));
				$error_data = base64_encode($error_data);
				$this->setRedirect(JRoute::_('index.php?option=com_osticky2&view=confirm&conf='.$error_data.$query_append, false), $ost_info->error, 'error');
				return false;
			}
			else
			{
				// already in confirm view, just go to display
				
				// Push document object into the view.
				$view->document = $document;
				$view->display();
				return;
			}
		}
		
		$user = JFactory::getUser();
		$menu = JFactory::getApplication()->getMenu();
		
		// check if we have explicit itemid in query
		$Itemid = $input->getInt('Itemid');
		
		if(empty($Itemid))
		{
			// no menu itemid. We have to get "tickets list" menu item id for correct redirection and also
			// to set active menu for thread view.
			// ***IMPORTANT***: if there are more than one menus containing "tickets list" item, the first one
			// will be selected and set as active in thread view. If you wish to activate a specific
			// copy of "tickets list" menu item you have to include "Itemid" in the URL
			$ticket_list_menu = $menu->getItems('link', 'index.php?option=com_osticky2&view=tickets', true);
			
			if(!empty($ticket_list_menu))
			{
				$Itemid = $ticket_list_menu->id;
			}
		}
		
		// Prepare Itemid parameter to be appended to URL for login redirects
		if(!empty($Itemid))
		{
			$append_menu = '&Itemid='.$Itemid;
			// In both thread and tickets list view we have
			// to set "tickets list" menu as active
			if($vName == 'thread' || $vName == 'tickets')
			{
				$menu->setActive($Itemid);
			}
		}
		else
		{
			$append_menu = '';
		}
		
		if(!$user->get('email') && ($vName == 'tickets' || $vName == 'thread'))
		{
			// guests must login before they can view ticket list or a ticket thread
			$ticket_id = $input->getString('id', '');

			if($vName == 'tickets' || empty($ticket_id))
			{
				$return = base64_encode('index.php?option=com_osticky2&view=tickets'.$append_menu);
			}
			else 
			{
				// thread view with ticket id
				$return = base64_encode('index.php?option=com_osticky2&view=thread&id='.urlencode($ticket_id)).$append_menu;
			}
			
			// setup login redirect
			$this->setRedirect(JRoute::_('index.php?option=com_users&view=login&return='.$return, false),
					JText::_('COM_OSTICKY_TICKET_LOGIN_TO_VIEW_TICKET'), 'message');
			return;
		}
		
		if($vName == 'ticket_modal')
		{
			// we use the same model for ticket_modal view - so set it here as default
			$model = JModelLegacy::getInstance('Ticket', 'osTicky2Model', array('ignore_request' => false));
			$view->setModel($model, true);
			
		}
		
		// check and manage "show_related_tickets" redirection (if applies - see osTicket config)
		$show_related_tickets = osTicky2Helper::getOstConfig('show_related_tickets');
		$auth_ticket_number = $app->getUserState('com_osticky2.auth.ticket_number', null);
		if(!$show_related_tickets && !empty($auth_ticket_number))
		{
			$ticket_number = $app->input->get('id', '');
			if($vName == 'tickets')
			{
				// for tickets view just inform users that they are limited to 1-ticket thread view
				$message = JText::_('COM_OSTICKY_THREAD_AUTH_BY_TICKETID');
			}
			else 
			{
				// for thread view - no message (including silent redirect if ticket number in url is
				// not correct)
				$message = '';
			}
			if($vName == 'tickets' || ($vName == 'thread' && !empty($ticket_number) && $ticket_number != $auth_ticket_number))
			{
				$this->setRedirect(JRoute::_('index.php?option=com_osticky2&view=thread&id='.urlencode($auth_ticket_number).$append_menu, false), $message, 'message');
				return;
			}
		}
		
		// Push document object into the view.
		$view->document = $document;
		
		$view->display();
	}
}
