<?php
/**
 * @package osTicky2 (osTicket 1.9.4 Bridge) for Joomla 3
 * @version 2.2.4: thread.php
 * @author Alex Polonski
 * @copyright (C) 2012 - 2014 - Alex Polonski
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
**/
defined('_JEXEC') or die;

jimport('joomla.application.component.controller');

class osTicky2ControllerThread extends JControllerLegacy
{
	public function getModel($name = 'Message', $prefix = 'osTicky2Model', $config = array('ignore_request' => true))
	{
		return parent::getModel($name, $prefix, array('ignore_request' => false));
	}

	public function submit()
	{
		// Check for request forgeries.
		JSession::checkToken() or jexit(JText::_('JINVALID_TOKEN'));
		
		$app = JFactory::getApplication();
		$input = $this->input;
		
		// Get the data from POST
		$data = $input->post->get('jform', array(), 'array');

		$ticketID = urldecode($input->get('id', 0, 'STRING'));
		
		// Check how the form was submitted
		// (workaround for JPagination links not submitting the form
		// and to ensure that we actually save message only if
		// "Submit" button was pressed, not search or table ordering)
		
		if($input->getString('submit_button', '') == '')
		{
			// "Submit" button was not pressed
			// save message text in session			
			$app->setUserState('com_osticky2.message.data', $data);
			// Check for hidden nav_link value
			// It is set in onclick js for all 'pagenav a' elements
			$page_link = $input->getString('nav_link', null, 'post');
			if(!empty($page_link))
			{
				// Form was submitted with pagenav link.
				// Just redirect
				
				// $$$ just an option... $this->setRedirect($page_link.'#jform_osticky_message');
				$this->setRedirect($page_link);
				return true;
			}
			// Form was submitted by other means (change sort order or
			// page display limit) - default processing is OK here
			
			$this->display();
			return true;
			
		}
		
		// Form was submitted pressing "Submit" button -
		// proceed to save the message
		
		$model	= $this->getModel();
		$params = JComponentHelper::getParams('com_osticky2');

		// Check for a valid session cookie
		if($params->get('validate_session', 0)) {
			if(JFactory::getSession()->getState() != 'active'){
				JError::raiseWarning(403, JText::_('COM_OSTICKY_SESSION_INVALID'));

				// Save the data in the session.
				$app->setUserState('com_osticky2.message.data', $data);

				$this->setRedirect(JRoute::_('index.php?option=com_osticky2&view=thread&id='.urlencode($ticketID), false));
				return false;
			}
		}

		// Validate the posted data.
		$form = $model->getForm();
		if (!$form) {
			JError::raiseError(500, $model->getError());
			return false;
		}

		// get return url from request
		$return = $input->getBase64('return', '');
		if(!empty($return))
		{
			$return = base64_decode($return);
		}
		else
		{
			$return = JRoute::_('index.php?option=com_osticky2&view=thread&id='.urlencode($ticketID), false);
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
			$app->setUserState('com_osticky2.message.data', $data);
			$this->setRedirect($return);
			
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
			$this->setRedirect($return, $model->getError(), 'warning');
			return false;
		}
		
		// Flush the data from the session
		$app->setUserState('com_osticky2.message.data', null);
		
		$message = JText::_('COM_OSTICKY_MESSAGE_SUBMIT_SUCCESS');
		
		$this->scrollToLastPage($message);
		
		// There is a problem in some installations: an empty error message is shown
		// after the message is submitted successfully. For compatibility with Joomla 3.0
		// we have to use a hack to empty application's message queue
		// (for J2.5 it was possible to use JObject::set method, but in J3.0 JApplication
		// does not extend JObject anymore, so a protected member cannot be changed with set)
		//$hack = new JMessageQueueEmptyHack();
		//$hack->emptyMessageQueue();
		
		return true;
		
	}
	
	public function scrollToLastPage($message = '', $type = 'message')
	{
		$model = JModelLegacy::getInstance('Thread', 'osTicky2Model', array('ignore_request' => false));
		$input = JFactory::getApplication()->input;
		
		$input->set('filter_search', '');
		$input->set('filter_date', '');		
		$start = $model->getLastPageStart();
		
		$ticketID = urldecode($input->get('id', 0, 'STRING'));
		$this->setRedirect(JRoute::_('index.php?option=com_osticky2&view=thread'.$start.'&id='.urlencode($ticketID), false), $message, $type);
	}
	
	public function scrollToPageWithMsg($message = '', $type = 'message')
	{
		$input = JFactory::getApplication()->input;
		
		$msgID = $input->getString('msgID', '');
		$model = JModelLegacy::getInstance('Thread', 'osTicky2Model', array('ignore_request' => false));
		$input->set('filter_search', '');
		$input->set('filter_date', '');
		$ticketID = urldecode($input->get('id', 0, 'STRING'));
		
		$start = $model->getPageWithMsgStart($msgID);
		if($start === false)
		{
			$start = '';
			$msgID = '';
			$message = JText::_('COM_OSTICKY_THREAD_ERROR_MESSAGE_NOT_FOUND');
			$type = 'warning';
		}
		else 
		{
			$msgID = '&msgID='.$msgID;
		}
		$this->setRedirect(JRoute::_('index.php?option=com_osticky2&view=thread'.$start.$msgID.'&id='.urlencode($ticketID), false), $message, $type);
		return $msgID != '';
	}
	
	public function login()
	{
		// get login data from request
		$input = JFactory::getApplication()->input;
		
		$key = $input->get->getBase64('key', '');
		$login_data = base64_decode($key);
		list($username, $password, $return) = explode(':', $login_data, 3);
		
		if(empty($return))
		{
			// if redirect after login is not set use default (tickets list)
			$return = 'index.php?option=com_osticky2&view=tickets';
		}
		
		$user = JFactory::getUser();
		$app = JFactory::getApplication();
		
		if($user->get('id') == 0)
		{
			// user is not logged in - store login data in session
			// to autocomplete login form
			$user_data = array();
			$user_data['username'] = $username;
			$user_data['password'] = $password;
			$user_data['return'] = $return;
				
			$user_data['remember'] = 0;
			$app->setUserState('users.login.form.data', $user_data);
			$this->setRedirect(JRoute::_('index.php?option=com_users&view=login'));
		}
		elseif($user->get('username') == $username)
		{
			// correct user is already logged in - redirect to ticket view
			$this->setRedirect(JRoute::_($return, false));
		}
		else 
		{
			// another user is logged in. Logout first and try again
			$app->logout();
			$this->setRedirect(JRoute::_('index.php?option=com_osticky2&task=thread.login&key='.$key, false));			
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
		$site = self::$instances['site'];
		$site->_messageQueue = array();
	}
}
*/
