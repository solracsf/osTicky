<?php
/**
 * @package osTicky2 (osTicket 1.9.4 Bridge) for Joomla 3
 * @version 2.1: view.html.php
 * @author Alex Polonski
 * @copyright (C) 2012 - 2014 - Alex Polonski
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
**/
// No direct access
defined('_JEXEC') or die;

jimport('joomla.application.component.view');

class osTicky2ViewTicket_Modal extends JViewLegacy
{
	protected $state;
	protected $params;
	protected $form;
	
	function display($tpl = null)
	{
		$app		= JFactory::getApplication();
		$input		= $app->input;
		
		$info_field_id = $input->getString('infoid');
		$function = $input->getString('function');
		
		$params		= $app->getParams();

		$this->params = $params;
		$this->state = $this->get('State');
		$this->state->set('infoid', $info_field_id);
		$this->state->set('function', $function);
		
		$this->form	= $this->get('Form');
	
		// Check for errors.
		if (count($errors = $this->get('Errors'))) {
			JError::raiseError(500, implode("\n", $errors));
			return false;
		}
		
		// check if the user has permissions to open a ticket
		require_once JPATH_COMPONENT_ADMINISTRATOR.'/helpers/osticky2.php';
		$canCreate = osTicky2Helper::getActions()->get('osticky.create', 0);
		if(!$canCreate)
		{
			$error_data = implode(':', array('COM_OSTICKY_ERROR_CANNOT_CREATE_DESC', '', '', ''));
			$error_data = base64_encode($error_data);
			$app->redirect(JRoute::_('index.php?option=com_osticky2&view=confirm&tmpl=component&function=plg_osticky2_ticket.close&infoid=__plg_osticky_info&conf='.$error_data, false), JText::_('JERROR_ALERTNOAUTHOR'), 'warning');
			return false;
		}
		
		$document = JFactory::getDocument();
		$control = $this->form->getFormControl();

		$script =
		'
			window.addEvent("domready", function() {
				
				radioBtnGroupSetup.init(jQuery);
				
				var parent = window.parent;
				var info = parent.document.id("'.$info_field_id.'");
				if(info != null) {
					var info_obj = JSON.decode(info.value);
					var text = "";
					Object.each(info_obj, (function(item, index) {
						text += "\n" + "{" + index + "}" + "\n" + item + "\n";
						text += "{/" + index + "}" + "\n";
					}));
					document.id("'.$control.'_message_sticky_info").value = "\n#Details:#\n" + text;
					/*
					text = text.replace(/\n/g, "<br />");
					document.id("stciky_info").set("title", text);
					*/
					var clientInfo = document.id("'.$control.'_message_client_info");
					if(clientInfo) {
						clientInfo.set("readonly", "readonly");
					}
				}	
			});
		';
		
		$document->addScriptDeclaration($script);
		
		$document->addScript(JUri::base() . 'components/com_osticky2/views/ticket_modal/tmpl/template.js');
		
		JFactory::getDocument()->addStyleSheet(JURI::base(true).'/components/com_osticky2/css/styles30.css');
		parent::display($tpl);
	}

}
