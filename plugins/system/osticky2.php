<?php
/**
 * @package osTicky2 (osTicket 1.9.4 Bridge) for Joomla 3 - 3.0
 * @subpackage plg_system_osticky
 * @version 2.1: osticky2.php
 * @author Alex Polonski
 * @copyright (C) 2012 - 2014 - Alex Polonski
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
**/
// No direct access.
defined('_JEXEC') or die;

include_once JPATH_ROOT.'/administrator/components/com_osticky2/helpers/osticky2.php';

class plgSystemOsTicky2 extends JPlugin
{
	public function onBeforeRender()
	{
		if(!defined('OST_VERSION2_19'))
		{
			// component not installed
			return true;
		}
		$ost_version = osTicky2Helper::getOstConfig('ost_version');
		if(empty($ost_version))
		{
			return true;
		}
		
		$app = JFactory::getApplication();
		$input = $app->input;
		
		$option = $input->get('option', '');
		$view = $input->get('view', '');
		if($option == 'com_osticky2' && $view == 'ticket_modal')
		{
			// no ticket function over an osticky modal window
			 return true;
		}
		
		// Use this plugin only in site application
		if ($app->isSite())
		{
			$document = JFactory::getDocument();
			$user = JFactory::getUser();
			$authorised_groups = $this->params->get('user_groups_from', array());
			$user_groups = $user->getAuthorisedGroups();

			$sticky2_id = $input->getInt('sticky2_id', 0);
			if($sticky2_id)
			{
				$sticky2_key = $input->getString('sticky2_key', '');
				$lang = JFactory::getLanguage();
				$lang->load('plg_system_osticky2', JPATH_SITE.'/plugins/system/osticky2/');
				
				$db = osTicky2Helper::getDbo();
				$query = $db->getQuery(true);
				
				$query->select('th.ticket_id, th.body AS message');
				$query->from('#__ticket_thread AS th');
				$query->select('t.number AS ticketID, t.isanswered, t.lastmessage, t.lastresponse, t.created, t.closed, t.reopened')
					->innerJoin('#__ticket AS t ON t.ticket_id = th.ticket_id');
				$query->select('ts.state AS status')
					->innerJoin('#__ticket_status AS ts ON ts.id = t.status_id');
				$query->select('fev.value AS subject');
				$query->join('LEFT', '#__form_entry AS fe ON fe.object_id = t.ticket_id AND fe.object_type = '.$db->Quote('T').' AND fe.form_id = 2');
				$query->join('LEFT', '#__form_entry_values AS fev ON fev.entry_id = fe.id');
				$query->join('LEFT', '#__form_field AS ff ON fev.field_id = ff.id AND ff.name = '.$db->Quote('subject'));
				
				$query->select('ue.address AS email, u.name');
				$query->join('LEFT', '#__user AS u ON u.id = t.user_id');
				$query->join('LEFT', '#__user_email AS ue ON ue.user_id = u.id');
				
				$query->where('th.id = '.(int)$sticky2_id);
				
				$db->setQuery($query);
				try
				{
					$item = $db->loadObject();
				}
				catch(Exception $e)
				{
					$app->enqueueMessage($e->getMessage(), 'warning');
					return;
				}
				
				if(!empty($item))
				{
					$canView = false;
					$note = array();
					if($sticky2_key)
					{
						$secret = $app->getCfg('secret', '');
						$key = md5($secret.':'.$item->ticket_id.':'.$item->created);
						if($key == $sticky2_key)
						{
							$canView = true;
							$note['link'] = osTicky2Helper::getOstConfig('helpdesk_url').'scp/tickets.php?id='.$item->ticket_id;
						}
					}
					elseif($item->email == $user->get('email')) 
					{
						$canView = true;
						$note['link'] = JRoute::_('index.php?option=com_osticky2&view=thread&id='.urlencode($item->ticketID));
					}
					if($canView && preg_match('!{fingerPrint}([\S|\s]+){/fingerPrint}!', $item->message, $matches))
					{
						$fp = trim($matches[1]);
					}
					if(!empty($fp))
					{						
						$note['ticketID'] = $item->ticketID;
						$note['subject'] = $item->subject;
						
						$message = $item->message;
						$message = nl2br($message);

						$note['message'] = substr($message, 0, strpos($message, '#-----#')); 
						$note['status'] = $item->status;
						$note['lastmessage'] = osTicky2Helper::dateFromOSTwFormat($item->lastmessage);
						$note['lastresponse'] = empty($item->lastresponse) ?
							JText::_('PLG_OSTICKY_TICKET_LASTRESPONSE_NEVER') :
							osTicky2Helper::dateFromOSTwFormat($item->lastresponse);
						$note['isanswered'] = $item->isanswered;
						$note['created'] = osTicky2Helper::dateFromOSTwFormat($item->created);
						$note['closed'] = empty($item->closed) ? '' : osTicky2Helper::dateFromOSTwFormat($item->closed);
						$note['reopened'] = empty($item->reopened) ? '' : osTicky2Helper::dateFromOSTwFormat($item->reopened);
						
						$note['fp'] = json_decode($fp);
						
						$data = json_encode($note);
						$toggle = $this->params->get('toggle');
						$options = json_encode(array('toggle_visibility' => $toggle));
						
						$script_path = JURI::root(true).'/plugins/system/osticky2/note.js';
						$document->addScript($script_path, "text/javascript");
						
						JText::script('PLG_OSTICKY_TICKET_LASTMESSAGE');
						JText::script('PLG_OSTICKY_TICKET_LASTRESPONSE');
						JText::script('PLG_OSTICKY_TICKET_REOPENED');
						JText::script('PLG_OSTICKY_TICKET_CREATED');
						JText::script('PLG_OSTICKY_TICKET_CLOSED');
						
						$script = '
							window.addEvent("load", function() {
								// we user load event to position annotations correctly
								// as all page elements will have their real size at this moment
								plg_osticky2_note.init('. $data .', '. $options .');
								plg_osticky2_note.show();
							});
						';
						$document->addScriptDeclaration($script);
												
						$style_path = JURI::root(true).'/plugins/system/osticky2/style.css';
						$document->addStyleSheet($style_path);
					}
					else 
					{
						$app->enqueueMessage(JText::_('PLG_OSTICKY_ERROR_TICKET_NOAUTHOR'), 'warning');
					}
				}
				else 
				{
					$app->enqueueMessage(JText::_('PLG_OSTICKY_ERROR_TICKET_NOT_FOUND'), 'warning');
				}
			}
			
			$canCreate = count(array_intersect($authorised_groups, $user_groups)) > 0;
			if(!$canCreate)
			{
				// just out of here if the user has no rights
				return true;
			}
			
			// Get page uri
			$uri = JUri::getInstance();
			$query = $uri->getQuery(true);
			// if the page uri has 'sticky2_id' and/or 'sticky2_key' set in query remove them -
			// new ticket has nothing to do with the fact that the page is
			// showing a sticky note for another ticket
			unset($query['sticky2_id']);
			unset($query['sticky2_key']);
			$uri->setQuery($query);
			$uri = htmlspecialchars_decode((string)$uri); //get uri string unified to unsafe form
			
			JHtml::_('behavior.framework', true);
			JHtml::_('behavior.modal');

			$script_path = JURI::root(true).'/plugins/system/osticky2/create.js';
			$document->addScript($script_path, "text/javascript");
			
			$script = '
				plg_osticky2_ticket.init("'.$uri.'", "'.$this->params->get('mouse', 'control+click').'");
				//window.addEvent("domready", function() {
				window.addEvent("load", function() {
					$$("*").each(function(el,i) {
						el.addEvent("click", function(event) {
							plg_osticky2_ticket.create(el, event);
						});
						// Backup class and style values on mouseenter
						// so plg_osticky2_ticket.create() can access original
						// values (useful if style or class are changed by
						// another event handler on mouseenter)  
						el.addEvent("mouseenter", function(event) {
							if(event.target == this) {
								this.store("class_store", this.get("class") || "");
								this.store("style_store", this.get("style") || "");
							}
						});
					});
				});
			';
			$document->addScriptDeclaration($script);			
		}
		return true;
	}
}
