<?php
/**
 * @package osTicky2 (osTicket 1.9.4 Bridge) for Joomla 3
 * @version 2.2.4: ticket.php
 * @author Alex Polonski
 * @copyright (C) 2012 - 2014 - Alex Polonski
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
**/
defined('_JEXEC') or die;

jimport('joomla.application.component.controllerform');

class osTicky2ControllerTicket extends JControllerForm
{
	public function getModel($name = 'Ticket', $prefix = 'osTicky2Model', $config = array('ignore_request' => true))
	{
		return parent::getModel($name, $prefix, array('ignore_request' => false));
	}

	public function submit()
	{
		// Check for request forgeries.
		JSession::checkToken() or jexit(JText::_('JINVALID_TOKEN'));
				
		// Initialise variables.
		$app	= JFactory::getApplication();
		$input 	= $this->input;
		$model	= $this->getModel();
		$params = JComponentHelper::getParams('com_osticky2');

		// Get the data from POST
		$data = $input->post->get('jform', array(), 'array');
		$view = $input->get('view', 'ticket');
				
		// This controller is used for both normal and modal ticket views
		// so redirections are slighly different - for modal view
		// add the following 2 values to urls
		if($view == 'ticket_modal')
		{
			$viewSfx = '_modal';
			$query_append = '&tmpl=component&function=plg_osticky2_ticket.close&infoid=__plg_osticky_info';
		}
		else 
		{
			$viewSfx = '';
			$query_append = '';
		}
				
		require_once JPATH_COMPONENT_ADMINISTRATOR.'/helpers/osticky2.php';
		$canCreate = osTicky2Helper::getActions()->get('osticky.create', 0);
		if(!$canCreate)
		{
			$error_data = implode(':', array('COM_OSTICKY_ERROR_CANNOT_CREATE_DESC', '', '', ''));
			$error_data = base64_encode($error_data);
			$this->setRedirect(JRoute::_('index.php?option=com_osticky2&view=confirm&conf='.$error_data.$query_append, false), JText::_('JERROR_ALERTNOAUTHOR'), 'warning');
			
			return false;
		}
		
		// Check for a valid session cookie
		if($params->get('validate_session', 0)) {
			if(JFactory::getSession()->getState() != 'active'){
				JError::raiseWarning(403, JText::_('COM_OSTICKY_SESSION_INVALID'));

				// Save the data in the session.
				$app->setUserState('com_osticky2.ticket.data', $data);

				// Redirect back to the ticket form.
				$this->setRedirect(JRoute::_('index.php?option=com_osticky2&view=ticket'.$viewSfx.$query_append, false));
				return false;
			}
		}
        

		// Validate the posted data.
		$form = $model->getForm();
		if (!$form) {
			JError::raiseError(500, $model->getError());
			return false;
		}

		$validate = $model->validate($form, $data);

		if ($validate === false) {
			// Get the validation messages.
			$errors	= $model->getErrors();
			// Push up to three validation messages out to the user.
			for ($i = 0, $n = count($errors); $i < $n && $i < 3; $i++) {
				if ($errors[$i] instanceof Exception) {
					$app->enqueueMessage($errors[$i]->getMessage(), 'warning');
				} else {
					$app->enqueueMessage($errors[$i], 'warning');
				}
			}

			// Save the data in the session.
			$app->setUserState('com_osticky2.ticket.data', $data);
			
			$this->setRedirect(JRoute::_('index.php?option=com_osticky2&view=ticket'.$viewSfx.$query_append, false));
			return false;
		}
		
		// look for attached files
		$files = $input->files->get('jform', array(), 'raw');
		$files = !empty($files['filedata']) ? $files['filedata'] : array();
		foreach($files as $i => $file)
		{
			if(!empty($file['error']) || count($file) != 5)
			{
				unset($files[$i]);
			}
		}

		// Save data
		
		if(!$model->submit($validate, $files))
		{
			$app->setUserState('com_osticky2.ticket.data', $data);
			$this->setRedirect(JRoute::_('index.php?option=com_osticky2&view=ticket'.$viewSfx.$query_append, false), $model->getError(), 'warning');
			return false;
		}
		
		// Flush the data from the session
		$app->setUserState('com_osticky2.ticket.data', null);

		// get data from model for confirmation view
		// (empty string in array means "no error")
		$confirm_data = implode(':', array('', $model->getState('ticket.name', ''), $model->getState('ticket.email', ''), $model->getState('ticket.number', 0)));
		$confirm_data = base64_encode($confirm_data);
		
		$message = JText::_('COM_OSTICKY_TICKET_SUBMIT_SUCCESS');
		
		// Redirect
		if(JFactory::getUser()->get('id') && $view != 'ticket_modal') // for modal view always redirect to confirm!
		{
			// tickets view for logged in users
			$this->setRedirect(JRoute::_('index.php?option=com_osticky2&view=tickets', false), $message, 'message');
		}
		else 
		{
			// confirmation view for guests and modal tickets
			$this->setRedirect(JRoute::_('index.php?option=com_osticky2&view=confirm&conf='.$confirm_data.$query_append, false), $message, 'message');
		}
		
		// There is a problem in some installations: an empty error message is shown
		// after the ticket is submitted successfully. For compatibility with Joomla 3.0
		// we have to use a hack to empty application's message queue
		// (for J2.5 it was possible to use JObject::set method, but in J3.0 JApplication
		// does not extend JObject anymore, so a protected member cannot be changed with set)
		//$hack = new JMessageQueueEmptyHack();
		//$hack->emptyMessageQueue();
		
		return true;
	}
	
	public function cancel($key = null)
	{
		// no edit function is implemented for tickets, so no need to call
		// parent cancel in order to release edit id, etc...
		//parent::cancel($key);
		$app = JFactory::getApplication();
		// flush form data from session
		$app->setUserState('com_osticky2.ticket.data', null);
		
		$layout = $app->input->get('layout');
		if($layout == 'modal')
		{
			// redirect back to modal layout (if auto-close doesn't fire)
			$this->setRedirect(JRoute::_('index.php?option=com_osticky2&view=ticket_modal&tmpl=component', false), JText::_('COM_OSTICKY_TICKET_TICKET_CANCELLED'), 'message');
		}
		else
		{
			// redirect to home page
			$this->setRedirect(JRoute::_(Juri::base(), false), JText::_('COM_OSTICKY_TICKET_TICKET_CANCELLED'), 'message');
		}
		return true;
	}
}

/* not used
// Utility class for empty message queue hack
class JMessageQueueEmptyHack extends JApplication
{
	public function __construct($config = array())
	{
		parent::__construct($config);
	}
	public function emptyMessageQueue()
	{
		//$site = self::$instances['site'];
		//$site->_messageQueue = array();
	}
}
*/