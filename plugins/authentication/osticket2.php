<?php
/**
 * @package osTicky2 (osTicket 1.9.4 Bridge) for Joomla 3 - 3.0
 * @subpackage plg_authentication_osticket2
 * @version 2.1.1: osticket2.php
 * @author Alex Polonski
 * @copyright (C) 2012 - 2014 - Alex Polonski
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
**/
// No direct access
defined('_JEXEC') or die;

class plgAuthenticationOsTicket2 extends JPlugin {

	function onUserAuthenticate($credentials, $options, & $response) {
		$app = JFactory::getApplication();
		$message = '';
		$success = 0;
		$helper = JPATH_ADMINISTRATOR.'/components/com_osticky2/helpers/osticky2.php';
		if(file_exists($helper))
		{
			require_once($helper);
			$db = osTicky2Helper::getDbo();
			if(strlen($credentials['username']) && strlen($credentials['password']))
			{
				$query = $db->getQuery(true);
				try
				{
					// we include query setup in a try block to catch an exception if
					// osTicket database is not found
					$query->select('a.number, u.name, ue.address AS email')
						->from('#__ticket AS a')
						->join('LEFT', '#__user AS u ON u.id = a.user_id')
						->join('LEFT', '#__user_email AS ue ON ue.user_id = u.id')
						->where('ue.address = '.$db->Quote($credentials['username']))
						->where('a.number = '.$db->Quote($credentials['password']));

					$db->setQuery($query);
					$result = $db->loadObject();
				}
				catch(Exception $e)
				{
					$result = false;
				}
				if($result)
				{
					// Check if given username is an email that is already registered for a Joomla user
					// Empty password means that the user exists but is used for external logins - it's OK
					$db = JFactory::getDbo();
					$query = 'SELECT id FROM #__users WHERE NOT password = "" AND email = '.$db->Quote($credentials['username']);
					$db->setQuery($query);
					$user_id = $db->loadResult();
					if(empty($user_id))
					{
						// email not found - allow login via osTicket
						$success = 1;
					}
					else 
					{
						// The user must login using his Joomla credentials
						// *** for this message to be displayed correctly. this plugin must be
						// the last in authentication plugins ordering
						$lang = JFactory::getLanguage();
						$lang->load('plg_authentication_osticket2', JPATH_ROOT . '/plugins/authentication/osticket2');
						$message = JText::sprintf('PLG_AUTHENTICATION_OSTICKET2_MUST_LOGIN_JOOMLA', 
							JFactory::getConfig()->get('sitename'));
					}
				}
				else
				{
					$message = JText::_('JGLOBAL_AUTH_ACCESS_DENIED');
				}
			}
			else 
			{
				$message = JText::_('JGLOBAL_AUTH_USER_BLACKLISTED');
			}
		}
		else 
		{
			$message = JText::_('JGLOBAL_AUTH_UNKNOWN_ACCESS_DENIED');
		}
		$response->type = 'osTicket';
		if($success)
		{
			$response->status = JAuthentication::STATUS_SUCCESS;
			$response->error_message = '';
			$response->email = $result->email;
			$response->username = $result->email;
			$response->fullname = $this->params->get('use_full_name', 1) ? 
				$result->name : $result->email;
			// needs user auto-create to work
			
			// save ticket number used for authentication is session, so that osticky2 models
			// can filter the tickets (if 1-ticket access set in osTickets options)
			$app->setUserState('com_osticky2.auth.ticket_number', $credentials['password']);
		}
		else 
		{
			$response->error_message = JText::sprintf('JGLOBAL_AUTH_FAILED', $message);
			
			// reset ticket number in session. This doesn't mean general tickets access
			// deny, as the use may be authenticated by other plugins. The only thing we
			// know for sure at this point is that the user has not to be limited by 1
			// ticket only
			$app->setUserState('com_osticky2.auth.ticket_number', null);
		}
	}
}
