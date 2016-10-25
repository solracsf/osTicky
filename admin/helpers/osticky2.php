<?php
/**
 * @package osTicky2 (osTicket 1.9.4 Bridge) for Joomla 3
 * @version 2.2.6: osticky2.php
 * @author Alex Polonski
 * @copyright (C) 2012 - 2014 - Alex Polonski
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 **/
// No direct access
defined('_JEXEC') or die;

define('OST_VERSION2_18', '1.8.x');
define('OST_VERSION2_19', '1.9.x');

class osTicky2Helper
{
	protected static $ostConfig;
	protected static $stickyPlugin;

	public static function addSubmenu($vName)
	{
		JSubMenuHelper::addEntry(
			JText::_('COM_OSTICKY_SUBMENU_INFO'),
				'index.php?option=com_osticky2&view=info',
			$vName == 'info'
		);
	}

	public static function getActions()
	{
		$user	= JFactory::getUser();
		$result	= new JObject;

		$assetName = 'com_osticky2';
		$actions = array(
			'core.admin', 'core.manage', 'osticky.create', 'osticky.reply'
			);

			foreach ($actions as $action) {
				$result->set($action, $user->authorise($action, $assetName));
			}

			return $result;
	}

	public static function getDbo()
	{
		$option = array();
		$params = JComponentHelper::getParams('com_osticky2');
		if($params->get('database_select', 1) == 1)
		{
			// internal db
			$global_params = JFactory::getConfig();
				
			$option ['driver'] = $global_params->get('driver', 'mysqli');
			$option ['host'] = $global_params->get('host', 'localhsot');
			$option ['user'] = $global_params->get('user', '');
			$option ['password'] = $global_params->get('password', '');
			$option ['database'] = $global_params->get('db', '');
			$option ['prefix'] = $params->get('prefix', 'ost_');
		}
		else
		{
			// external db
			$option ['driver'] = $params->get('driver', 'mysqli');
			$option ['host'] = $params->get('host', 'localhsot');
			$option ['user'] = $params->get('user', '');
			$option ['password'] = $params->get('password', '');
			$option ['database'] = $params->get('database', '');
			$option ['prefix'] = $params->get('prefix', 'ost_');
		}
		try
		{
			$db = JDatabase::getInstance($option);
			return $db;
		}
		catch(Exception $e)
		{
			// fallback to core $db object if connection cannot be established
			return JFactory::getDbo();
		}

	}

	public static function getOstInfo()
	{
		$db = self::getDbo();
		$info = new stdClass();
		$info->error = '';
		
		$params = JComponentHelper::getParams('com_osticky2');
		$options = array('database' => $params->get('database', ''), 'user' => $params->get('user', ''), 'password' => $params->get('password', ''));
		
		try
		{
			if($db->getInstance($options))
			{
				$prefix = $db->getPrefix();
				$tables = $db->getTableList();
				if(empty($tables))
				{
					$info->error = JText::_('JLIB_DATABASE_ERROR_DATABASE_CONNECT');
				}
				else
				{
					$ostTables = preg_grep('/^'.$prefix.'?+/', $tables);
					$info->tables_count = count($ostTables);
					if($info->tables_count > 0)
					{
						if($result = self::getOstConfig('helpdesk_title', 'helpdesk_url', 'ost_version'))
						{
							$result['ostversion'] = $result['ost_version'] == '19' ? OST_VERSION2_19 : OST_VERSION2_18;
							$info->config = JArrayHelper::toObject($result);
						}
						else
						{
							$info->error = JText::_('COM_OSTICKY_OST_NOT_FOUND');
						}
					}
					else
					{
						$info->error = JText::_('COM_OSTICKY_OST_NOT_FOUND');
					}
				}
			}
			else
			{
				$info->error = JText::_('JLIB_DATABASE_ERROR_DATABASE_CONNECT');
			}
		}
		catch(Exception $e)
		{
			// normally - bad external db configuration
			$info->error = JText::_('JLIB_DATABASE_ERROR_DATABASE_CONNECT');
		}
		
		return $info;
	}

	public static function getOstConfig($keys = '')
	{
		if(!isset(self::$ostConfig))
		{
			$params = JComponentHelper::getParams('com_osticky2');
			$namespace = $params->get('cfg_namespace', 'core');
				
			$db = self::getDbo();
			
			$query = $db->getQuery(true);

			try {
				$db->setQuery('SELECT ' . $db->quoteName('key') . ', ' . $db->quoteName('value') . ' FROM #__config WHERE namespace = ' . $db->Quote($namespace));
				$result = $db->loadObjectList('key');
			}
			catch(Exception $e) {
				return array();
			}
			if(empty($result)) {
				// Joomla 2.5 workaround
				return array();
			}
			
			// inject more keys
			$db->setQuery('SELECT (TIME_TO_SEC(TIMEDIFF(NOW(), UTC_TIMESTAMP()))/3600) as sql_tz_diff');
			$tzdiff = $db->loadResult();
			
			// sql tz diff
			$obj = new stdClass;
			$obj->key = 'sql_tz_diff';
			$obj->value = $tzdiff;
			$result['sql_tz_diff'] = $obj;
			
			// global timezone offset
			
			// get default timezone
			$default_tz = $result['default_timezone_id']->value;
			$db->setQuery('SELECT offset FROM #__timezone WHERE id = '.(int)$default_tz);
			$offset = $db->loadResult();
			
			$obj = new stdClass;
			$obj->key = 'tz_offset';
			$obj->value = $offset;
			$result['tz_offset'] = $obj;
			
			/* no support for earlier versions
			// ost version - look for new 1.9 fields in ost_user table
			$prefix = $db->getPrefix();
			$user_columns = $db->getTableColumns($prefix.'user');
			$version = in_array('status', array_keys($user_columns)) ? '19' : '18';
			*/
			$version = '19';
			$obj = new stdClass;
			$obj->key = 'ost_version';
			$obj->value = $version;
			$result['ost_version'] = $obj;
			
			$defaults = array(
					'allow_pw_reset' =>     true,
					'pw_reset_window' =>    30,
					'enable_html_thread' => true,
					'allow_attachments' =>  true,
					'allow_online_attachments' => true, // not used
					'allow_online_attachments_onlogin' => false, // not used
					'name_format' =>        'full', # First Last
					'auto_claim_tickets'=>  true,
					'system_language' =>    'en_US',
					'default_storage_bk' => 'D',
					'allow_client_updates' => false,
					'message_autoresponder_collabs' => true,
					'add_email_collabs' => true,
					'clients_only' => false,
					'client_registration' => 'closed',
					'accept_unregistered_email' => true,
					'default_help_topic' => 0,
					'default_ticket_status_id' => 1,
					'help_topic_sort_mode' => 'a',
					'client_verify_email' => 1
			);
			
			// special case - attachments settings - WORKAROUND ONLY!
			$db->setQuery('SELECT configuration FROM #__form_field WHERE type = ' . $db->Quote('thread') . ' ORDER BY id LIMIT 1');
			$config = $db->loadResult();
			$config = json_decode($config);
			
			$obj = new stdClass;
			$obj->key = 'allow_attachments';
			$obj->value = !empty($config->attachments) ? 1 : 0;
			$result['allow_attachments'] = $obj;
			
			$obj = new stdClass;
			$obj->key = 'allowed_filetypes';
			$obj->value = !empty($config->extensions) ? $config->extensions : '';
			$result['allowed_filetypes'] = $obj;
			
			$obj = new stdClass;
			$obj->key = 'max_file_size';
			$obj->value = !empty($config->size) ? $config->size : 2097152;
			$result['max_file_size'] = $obj;
			
			$obj = new stdClass;
			$obj->key = 'max_user_file_uploads';
			$obj->value = !empty($config->max) ? $config->max : 20;
			$result['max_user_file_uploads'] = $obj;
			
			// TBC... add mimetypes support? ETC. $$$
			// END WORKAROUND
			
			foreach($defaults as $key => $value)
			{
				if(!isset($result[$key]))
				{
					$obj = new stdClass;
					$obj->key = $key;
					$obj->value = $value;
					$result[$key] = $obj;
				}
			}
			
			self::$ostConfig = $result;
		}

		$keys = func_get_args();

		$result = array();
		
		foreach($keys as $k)
		{
			$result[$k] = isset(self::$ostConfig[$k]) ? self::$ostConfig[$k]->value : null;
		}
		if(count($result) == 1)
		{
			return array_shift($result);
		}
		else
		{
			return $result;
		}
	
	}
	
	protected static $osTicketUsers = array();
	
	public static function getOsTicketUserFromEmail($email)
	{
		if(empty($email))
		{
			return 0;
		}
		if(!isset(self::$osTicketUsers[$email]))
		{
			$db = self::getDbo();
			$db->setQuery('SELECT user_id FROM #__user_email WHERE address = '.$db->Quote($email));
			$user_id = (int)$db->loadResult();
			self::$osTicketUsers[$email] = $user_id;
		}
		return self::$osTicketUsers[$email];
	}

	public static function getStickyPlugin()
	{
		if(!isset(self::$stickyPlugin))
		{
			$plugin = JPluginHelper::getPlugin('system', 'osticky2');
			if(empty($plugin))
			{
				return false;
			}
			$params = new JRegistry();
			$params->loadString($plugin->params);
				
			self::$stickyPlugin['groups'] = $params->get('user_groups_from');
			self::$stickyPlugin['activation'] = $params->get('mouse');
			self::$stickyPlugin['view_toggle'] = $params->get('toggle');
		}
		return self::$stickyPlugin;
	}

	public static function autoEmailTicket($msg_id = 0)
	{
		if(!$msg_id)
		{
			return false;
		}
		
		$actions = osTicky2Helper::getOstConfig(
			'ticket_autoresponder',
			'ticket_alert_active',
			'ticket_alert_admin',
			'ticket_alert_dept_manager',
			'ticket_alert_dept_members',
			'assigned_alert_active',
			'assigned_alert_staff',
			'assigned_alert_team_lead',
			'assigned_alert_team_members'
		);
		if(!$actions['ticket_autoresponder'] && !$actions['ticket_alert_active'])
		{
			return true;
		}

		$result = self::queryData($msg_id);

		if(!$result)
		{
			return false;
		}

		$mail_ok = true;
		$mail = JFactory::getMailer();
		$mail->ClearAllRecipients();
		
		$app = JFactory::getApplication();

		// Auto-respond

		if($actions['ticket_autoresponder'] && !$result->noautoresp && !$result->topic_noautoresp && $result->ticket_auto_response)
		{
			$result->ticket_autoresp_body .= '%jurl';
				
			try
			{
				$result->recipient_name = $result->name;
				$subject = self::fillEmailTemplate($result->ticket_autoresp_subj, $result);
				$body = self::fillEmailTemplate($result->ticket_autoresp_body, $result);
				$email_from = array($result->email_from, $result->name_from);
				$mail->addRecipient($result->email, $result->name);
				$mail->setSender($email_from);
				$mail->setSubject($subject);
				$mail->setBody($body);
				$mail->isHtml(true);
				$mail->Encoding = 'base64';
				if(!$mail->Send())
				{
					$app->enqueueMessage(JText::_('COM_OSTICKY_WARNING_EMAIL_TICKET_AUTORESP'), 'warning');
					$mail_ok = false;
				}
			}
			catch(Exception $e)
			{
				$app->enqueueMessage(JText::_('COM_OSTICKY_WARNING_EMAIL_TICKET_AUTORESP'), 'warning');
				return false;
			}
		}
		
		$db = self::getDbo();
		
		// Assigned alerts (if the ticket is not assigned, this method will
		// do nothing and return true)
		$mail_ok &= self::sendAssignedAlerts($result);
		
		// Alerts

		$ids = array(); // Staff ids to recieve an alert

		try {
			/* This feature is replaced by "send alert to admin" option - 
			 * alert is set to "Admin's Email Address" global email ONLY
			 * Do not add this recipient here as this email may have no ID
			 * This recipient will be added in sendAlerts() method (if applies)
			// Admin
			if($actions['ticket_alert_admin'])
			{
				$query = $db->getQuery(true);
				$query->select('a.staff_id');
				$query->from('#__staff AS a');
				$query->where('a.group_id = 1');
				$db->setQuery($query);
				$rows = $db->loadColumn();
				if(!empty($rows))
				{
					foreach($rows as $id)
					{
						$ids[] = $id;
					}
				}
			}
			*/
				
			// Department manager (if any)
			if($actions['ticket_alert_dept_manager'])
			{
				$query = $db->getQuery(true);
				$query->select('a.staff_id');
				$query->from('#__staff AS a');
				$query->where('a.staff_id = '.$result->manager_id);
				$db->setQuery($query);
				$id = $db->loadResult();
				if($id)
				{
					$ids[] = $id;
				}
			}

			// Department members (if any)
			if($actions['ticket_alert_dept_members'])
			{
				$query = $db->getQuery(true);
				$query->select('a.staff_id');
				$query->from('#__staff AS a');
				$query->where('a.dept_id = '.$result->dept_id);
				$db->setQuery($query);
				$rows = $db->loadColumn();
				if(!empty($rows))
				{
					foreach($rows as $id)
					{
						$ids[] = $id;
					}
				}
			}
		}
		catch(Exception $e)
		{
			// something went wrong while querying recipients
			return false;
		}
		// Clean ids to avoid email duplicates
		$ids = array_unique($ids);

		// last paramter "true" means that this is a ticket alert, so helpdesk admin will be added to recipients if so set in options
		return $mail_ok && self::sendAlerts($ids, $result, $result->ticket_alert_subj, $result->ticket_alert_body, true);
	}

	public static function autoEmailMessage($msg_id = 0, $is_reassigned = false)
	{
		if(!$msg_id)
		{
			return false;
		}
		$actions = osTicky2Helper::getOstConfig(
			'message_autoresponder',
			'message_alert_active',
			'message_alert_assigned',
			'message_alert_laststaff',
			'message_alert_dept_manager',
			'assigned_alert_active',
			'assigned_alert_staff',
			'assigned_alert_team_lead',
			'assigned_alert_team_members'
		);
		if(!$actions['message_autoresponder'] && !$actions['message_alert_active'])
		{
			return true;
		}

		$result = self::queryData($msg_id);

		if(!$result)
		{
			return false;
		}

		$mail_ok = true;
		$mail = JFactory::getMailer();
		$mail->ClearAllRecipients();
		
		$app = JFactory::getApplication();

		// Auto-respond

		if($actions['message_autoresponder'] && !$result->noautoresp && !$result->topic_noautoresp && $result->message_auto_response)
		{
			$result->message_autoresp_body .= '%jurl';
			
			try
			{
				$result->recipient_name = $result->name;
				$subject = self::fillEmailTemplate($result->message_autoresp_subj, $result);
				$body = self::fillEmailTemplate($result->message_autoresp_body, $result);
				$email_from = array($result->email_from, $result->name_from);	
				$mail->addRecipient($result->email, $result->name);
				$mail->setSender($email_from);
				$mail->setSubject($subject);
				$mail->setBody($body);
				$mail->isHtml(true);
				$mail->Encoding = 'base64';
				if(!$mail->Send())
				{
					$app->enqueueMessage(JText::_('COM_OSTICKY_WARNING_EMAIL_MESSAGE_AUTORESP'), 'warning');
					$mail_ok = false;
				}
			}
			catch(Exception $e)
			{
				$app->enqueueMessage(JText::_('COM_OSTICKY_WARNING_EMAIL_MESSAGE_AUTORESP'), 'warning');
				return false;
			}
		}

		$db = self::getDbo();
		
		if($is_reassigned)
		{
			// Assigned alerts
			$mail_ok &= self::sendAssignedAlerts($result);
		}
		
		// Alerts
		
		$ids = array(); // Staff ids to recieve an alert

		try
		{
			// Assigned staff member (if any)
			if($actions['message_alert_assigned'])
			{
				$query = $db->getQuery(true);
				$query->select('a.staff_id');
				$query->from('#__staff AS a');
				$query->innerJoin('#__ticket AS b ON b.staff_id = a.staff_id');
				$query->where('b.ticket_id = '.$result->id);
				$db->setQuery($query);
				$id = $db->loadResult();
				if($id)
				{
					$ids[] = $id;
				}
			}

			// Last respondent in thread (if any)
			if($actions['message_alert_laststaff'])
			{
				$query = $db->getQuery(true);
				$query->select('a.staff_id');
				$query->from('#__staff AS a');
				
				$query->innerJoin('#__ticket_thread AS b ON b.staff_id = a.staff_id');
				$query->where('b.ticket_id = '.$result->id);
				$query->where('b.thread_type = '.$db->Quote('R'));
				
				$query->order('b.created DESC');
				$query->limit(1);
				$db->setQuery($query);
				$id = $db->loadResult();
				if($id) {
					$ids[] = $id;
				}
			}

			// Department manager (if any)
			if($actions['message_alert_dept_manager'])
			{
				$query = $db->getQuery(true);
				$query->select('a.staff_id');
				$query->from('#__staff AS a');
				$query->where('a.staff_id = '.$result->manager_id);
				$db->setQuery($query);
				$id = $db->loadResult();
				if($id)
				{
					$ids[] = $id;
				}
			}
		}
		catch(Exception $e)
		{
			// something went wrong while querying recipients
			return false;
		}
		// Clean ids to avoid email duplicates
		$ids = array_unique($ids);

		return $mail_ok && self::sendAlerts($ids, $result, $result->message_alert_subj, $result->message_alert_body);
	}
	
	// Query data for auto-responses and alerts
	// if ticket_id is passed it is used to identify the ticket,
	// otherwise, msg_id is used. *** currently only queryData
	// by msg_id is implemented
	private static function queryData($msg_id, $ticket_id = null)
	{
		$db = self::getDbo();
		$query = $db->getQuery(true);
		
		if(empty($ticket_id))
		{
			$query->select('
				tm.body AS message,
				tm.ticket_id AS id,
				tm.id AS msg_id
			');
			$query->from('#__ticket_thread AS tm');
	
			$query->select('
				t.number,
				t.staff_id AS assigned_staff,
				t.team_id AS assigned_team,
				t.created AS createdate,
				t.duedate,
				t.closed AS closedate,
				t.reopened AS reopendate
			');
			$query->innerJoin('#__ticket AS t ON t.ticket_id = tm.ticket_id');
		}
		else
		{
			// when looking up by ticket_id, no message body will be returned
			$query->select('
				t.ticket_id AS id,
				t.number,
				t.staff_id AS assigned_staff,
				t.team_id AS assigned_team,
				t.created AS createdate,
				t.duedate,
				t.closed AS closedate,
				t.reopened AS reopendate,
				NULL as message
			');
			$query->from('#__ticket AS t');
		}
		
		$query->select('fev.value AS subject');
		$query->join('LEFT', '#__form_entry AS fe ON fe.object_id = t.ticket_id AND fe.object_type = '.$db->Quote('T').' AND fe.form_id = 2');
		$query->join('LEFT', '#__form_entry_values AS fev ON fev.entry_id = fe.id');
		$query->join('LEFT', '#__form_field AS ff ON fev.field_id = ff.id AND ff.name = '.$db->Quote('subject'));
		
		$query->select('ue.address AS email, u.name, u.id AS user_id');
		$query->join('LEFT', '#__user AS u ON u.id = t.user_id');
		$query->join('LEFT', '#__user_email AS ue ON ue.user_id = u.id');
		
		$query->select('ts.state AS status');
		$query->join('LEFT', '#__ticket_status AS ts ON ts.id = t.status_id');
		
		/* Reserved for development: get organization account manager here
		 * to send alerts (if applies). There is a lot of new coding needed
		 * to provide support for 1.9 "organizations" feature (auto-assign
		 * tickets, collaborators management, domain check, etc.), 
		 * so better have it implemented all at once
		$ost_version = osTicky2Helper::getOstConfig('ost_version');
		if($ost_version == '19')
		{
			
		}
		*/
		
		$query->select('
			d.dept_id,
			d.dept_name AS dept,
			d.ticket_auto_response,
			d.message_auto_response,
			d.manager_id,
			d.dept_signature AS signature,
			d.tpl_id
		');
		$query->innerJoin('#__department AS d ON d.dept_id = t.dept_id');

		$query->select('
			e.email AS email_from,
			e.noautoresp,
			e.name AS name_from
		');
		// *** we use LEFT JOIN here because osTicket allows to
		// configure a department without email_id (although this
		// field is marked as required on the department form).
		// For osTicket it means that system default email should
		// be used instead. By relaxing the join we may get empty
		// email_from object, we will check this and fix if nescessary
		// later in this method (otherwise the query would become too
		// complex)  
		$query->leftJoin('#__email AS e ON
			CASE 
				WHEN d.autoresp_email_id = 0 
				THEN e.email_id = d.email_id 
				ELSE e.email_id = d.autoresp_email_id
			END
		');

		$query->select('
			ht.topic, ht.staff_id, ht.team_id
		');
		$query->innerJoin('#__help_topic AS ht ON ht.topic_id = t.topic_id');
		
		$query->select('
			htp.noautoresp AS topic_noautoresp
		');
		$query->innerJoin('#__help_topic AS htp ON
			CASE
				WHEN ht.noautoresp = 0 AND ht.topic_pid > 0 
				THEN htp.topic_id = ht.topic_pid 
				ELSE htp.topic_id = t.topic_id 
			END
		');

		if(empty($ticket_id))
		{
			$query->where('tm.id = '.(int)$msg_id);
		}
		else
		{
			$query->where('t.ticket_id = '.(int)$ticket_id);
		}
		
		$query->group('tm.id');

		$db->setQuery($query);
		try
		{
			$result = $db->loadObject();
		}
		catch(Exception $e)
		{
			return false;
		}
		
		if($result)
		{
			if(empty($result->email_from) || empty($result->name_from))
			{
				// *** empty email_from fix
				// departments allow empty email_id in settings. We have to inject
				// ticket system default email data here for these cases
				$default_email_id = osTicky2Helper::getOstConfig('default_email_id');
				$db->setQuery('SELECT e.email AS email_from, e.name AS name_from FROM #__email AS e WHERE e.email_id = ' . (int)$default_email_id);
				$email = $db->loadObject();
				if(!empty($email))
				{
					$result->email_from	= $email->email_from;
					$result->name_from	= $email->name_from;
				}
			}
			
			// inject form data to result, so that we can use more fields in email
			// template replaces
			$result->form_data = TicketHelper::getTicketFormData($result->id, $result->user_id);
			$result->company_data = TicketHelper::getCompanyData();

			// add template rows (email patterns)
			$tpl_id = empty($result->tpl_id) ? osTicky2Helper::getOstConfig('default_template_id') : $result->tpl_id;
			$db->setQuery('SELECT code_name, subject, body FROM #__email_template WHERE tpl_id = ' . (int)$tpl_id);
			$values = $db->loadObjectList('code_name');
			
			$result->ticket_autoresp_subj		= $values['ticket.autoresp']->subject;
			$result->ticket_autoresp_body		= $values['ticket.autoresp']->body;
			$result->ticket_alert_subj			= $values['ticket.alert']->subject;
			$result->ticket_alert_body			= $values['ticket.alert']->body;
			$result->message_autoresp_subj		= $values['message.autoresp']->subject;
			$result->message_autoresp_body		= $values['message.autoresp']->body;
			$result->message_alert_subj			= $values['message.alert']->subject;
			$result->message_alert_body			= $values['message.alert']->body;
			$result->ticket_assigned_alert_subj	= $values['assigned.alert']->subject;
			$result->ticket_assigned_alert_body	= $values['assigned.alert']->body;
			
			// check if this is a sticky ticket
			$matches = array();
			$url = '';
			$result->sticky2_url = '';
			if(preg_match('!{url}([\S|\s]+){/url}!', $result->message, $matches))
			{
				$url = trim($matches[1]);
			}
			if($url)
			{
				// construct link for quick view
				// use 'secret' from configuration just to make sure
				// that the key genertied will be valid for current
				// joomla installation only. Not sure if it is OK...
				// with new installation of Joomla and osTicky2 using
				// the same existing osTicket database links from
				// old ticket alerts will not work...
				$app = JFactory::getApplication();
				$secret = $app->getCfg('secret', '');
				
				$key = md5($secret.':'.$result->id.':'.$result->createdate);
				$result->sticky2_url = $url.(strpos($url, '?') > 0 ? '&' : '?').'sticky2_id='.$result->msg_id.'&sticky2_key='.$key;
				$result->ticket_alert_body .= '%sticky2_url';
			}
		}
		
		return $result;
	}

	private static function sendAlerts($staff_ids = array(), $data = null, $subject_tpl = '', $body_tpl = '', $is_ticket_alert = false)
	{
		$app = JFactory::getApplication();
		
		$db = self::getDbo();
		$mail_ok = true;
		$mail = JFactory::getMailer();
		$recipients = array(); // Email recipients
		
		$alert_email_id = osTicky2Helper::getOstConfig('alert_email_id');
		if(empty($alert_email_id))
		{
			// fallback to system default email
			$alert_email_id = osTicky2Helper::getOstConfig('default_email_id');
		}
		
		// Get addresses and names
		if(!empty($staff_ids))
		{
			$query = $db->getQuery(true);
			$query->select('a.email, CONCAT_WS(" ", a.firstname, a.lastname) AS name');
			$query->from('#__staff AS a');
			$query->where('a.staff_id IN ('.implode(',', $staff_ids).')');
			$db->setQuery($query);
			try
			{
				// do not allow duplicate emails
				$recipients = $db->loadObjectList('email');
			}
			catch(Exception $e)
			{
				return false;
			}
		}
		
		// Add system admin to recipients if set in options
		$alert_admin = osTicky2Helper::getOstConfig('ticket_alert_admin', 'admin_email');
		if($alert_admin['ticket_alert_admin'] && $is_ticket_alert)
		{
			$admin = new stdClass();
			$admin->email = $alert_admin['admin_email'];
			// use a language string for Admin name
			$admin->name = JText::_('COM_OSTICKY_ALERTS_ADMIN_NAME');
			// only add email if not already in recipients array
			if(!isset($recipients[$admin->email]))
			{
				$recipients[$admin->email] = $admin;
			}
		}
		
		// Send mail
		if(!empty($recipients))
		{
			// All alerts have the same sender
			$query = $db->getQuery(true);
			$query->select('email, name');
			$query->from('#__email');
			$query->where('email_id = '.(int)$alert_email_id);
			$db->setQuery($query);
			try
			{
				$email_from = $db->loadRow();
				if(empty($email_from))
				{
					return false;
				}
			}
			catch(Exception $e)
			{
				return false;
			}
			foreach($recipients as $recipient)
			{
				try
				{
					$mail->ClearAllRecipients();
					$data->recipient_name = $recipient->name;
					$subject = self::fillEmailTemplate($subject_tpl, $data);
					$body = self::fillEmailTemplate($body_tpl, $data);
					$mail->addRecipient($recipient->email, $recipient->name);
					$mail->setSender($email_from);
					$mail->setSubject($subject);
					$mail->setBody($body);
					$mail->isHtml(true);
					$mail->Encoding = 'base64';
					if(!$mail->Send())
					{
						$app->enqueueMessage(JText::_('COM_OSTICKY_WARNING_EMAIL_ALERT'), 'warning');
						$mail_ok = false;
					}
				}
				catch(Exception $e)
				{
					$app->enqueueMessage(JText::_('COM_OSTICKY_WARNING_EMAIL_ALERT'), 'warning');
					return false;
				}
			}
		}

		return $mail_ok;
	}
	
	private static function sendAssignedAlerts($result)
	{
		$db = self::getDbo();
		
		// Assigned alerts
		$actions = osTicky2Helper::getOstConfig(
				'assigned_alert_active',
				'assigned_alert_staff',
				'assigned_alert_team_lead',
				'assigned_alert_team_members'
		);
		
		if($actions['assigned_alert_active'])
		{
			$ids = array(); // Staff ids to recieve an alert
			if(!empty($result->assigned_staff) && $actions['assigned_alert_staff'])
			{
				$ids[] = $result->assigned_staff;
			}
			if(!empty($result->assigned_team))
			{
				$db->setQuery('SELECT t.* FROM #__team AS t WHERE team_id = ' . (int)$result->assigned_team);
				try
				{
					$team = $db->loadObject();
				}
				catch(RuntimeException $e)
				{
					// something went wrong while querying team properties
					return false;
				}
				if(!empty($team->lead_id) && $actions['assigned_alert_team_lead'] && empty($team->noalerts))
				{
					$ids[] = $team->lead_id;
				}
				if($actions['assigned_alert_team_members'] && empty($team->noalerts))
				{
					$db->setQuery('SELECT tm.staff_id FROM #__team_member AS tm WHERE tm.team_id = ' . $result->assigned_team);
					try
					{
						$members = $db->loadColumn();
					}
					catch(RuntimeException $e)
					{
						// something went wrong while querying team members
						return false;
					}
					$ids = array_merge($ids, $members);
				}
			}
				
			$ids = array_unique($ids);
			if(!empty($ids))
			{
				// this is an automatic assignment - use generic assigner name
				$result->assigner_name = JText::_('COM_OSTICKY_TICKET_SYSTEM_ASSIGNER');
				return self::sendAlerts($ids, $result, $result->ticket_assigned_alert_subj, $result->ticket_assigned_alert_body);
			}
		}
		return true;
	}

	/** reserved, needs refactoring
	public static function sendOverLimitNotice($data)
	{
		if(empty($data['email']) || empty($data['name']))
		{
			return false;
		}

		// Always send alert to Admin
		// ... $$$ check how it is done in native osTicket

		// Send notice to user (only once per session) if set in configuration
		if(!self::getOstConfig('overlimit_notice_active'))
		{
			return true;
		}
		$data = JArrayHelper::toObject($data);

		$app = JFactory::getApplication();
		$notice_sent = $app->getUserState('com_osticky2.overlimit_notice', 0);
		if($notice_sent)
		{
			return true;
		}

		$db = self::getDbo();
		$query = $db->getQuery(true);

		$query->select('tpl.ticket_overlimit_subj, tpl.ticket_overlimit_body');
		$query->from('#__help_topic AS h');
		$query->innerJoin('#__department AS d ON d.dept_id = h.dept_id');

		$query->innerJoin('#__email_template AS tpl ON
			CASE
				WHEN d.tpl_id = 0
				THEN tpl.tpl_id = '.osTicky2Helper::getOstConfig('default_template_id').'
				ELSE tpl.tpl_id = d.tpl_id
			END
		');
		$query->where('h.topic_id = '.$data->topic_id);
		$db->setQuery($query);
		try
		{
			$result = $db->loadObject();
		}
		catch(Exception $e)
		{
			return false;
		}
		if(!$result) {
			return false;
		}

		$mail = JFactory::getMailer();
		$mail->ClearAllRecipients();

		// add %jurl to body template and set ticketID empty in data
		$result->ticket_overlimit_body .= '%jurl';
		$data->ticket = '';

		$recipient = array($data->email, $data->name);
		$subject = self::fillEmailTemplate($result->ticket_overlimit_subj, $data);
		$body = self::fillEmailTemplate($result->ticket_overlimit_body, $data);
		$email_from = array(self::getOstConfig('admin_email'), self::getOstConfig('helpdesk_title'));
		$mail->addRecipient($recipient);
		$mail->setSender($email_from);
		$mail->setSubject($subject);
		$mail->setBody($body);
		$mail->isHtml(true);
		$mail->Encoding = 'base64';
		if($mail->Send()) {
			$app->setUserState('com_osticky2.overlimit_notice', 1);
			return true;
		}
		return false;
	}
	*/

	private static function fillEmailTemplate($tpl = '', $data = null)
	{
		if(empty($data))
		{
			return $tpl;
		}
		
		// when a %{...} placeholder is used inside a link in osTicket email template, it is replaced by
		// URL-encoded version of curly braces chars. We must restore original braces in order to get
		// data injection to work. 
		$tpl = str_replace(array('%7B', '%7D'), array('{', '}'), $tpl);
		
		$jlink = '';
		$config = JFactory::getConfig();
		$sitename = $config->get('sitename', 'Joomla');
		
		if(strpos($tpl, '%jurl') !== false)
		{
			// only run this if %jurl token is present in the template
			$uri = JFactory::getURI();
			$parts = new JURI();
			$parts->setScheme($uri->getScheme());
			$parts->setHost($uri->getHost());
				
			$user = JFactory::getUser();
			if($user->get('password'))
			{
				// joomla user

				// do not use SEF links here, we only need to hit the controller's task,
				// and it will redirect using actual SEF settings
				$return = 'index.php?option=com_osticky2&view=thread&id='.urlencode($data->number);
				$key = base64_encode($user->username.'::'.$return);
				$parts->setPath(JURI::base(true).'/index.php?option=com_osticky2&task=thread.login&key='.$key);

				$jurl = $parts->toString();
				$jlink = JText::sprintf('COM_OSTICKY_TICKETS_JOOMLA_LOGIN_LINK', $sitename);
				$jlink .= $jurl;
			}
			elseif(JPluginHelper::getPlugin('authentication', 'osticket2'))
			{
				// guest or osTicket user

				// do not use SEF links here, we only need to hit the controller's task,
				// and it will redirect using actual SEF settings
				$return = 'index.php?option=com_osticky2&view=thread&id='.urlencode($data->number);
				$key = base64_encode($data->email.':'.$data->number.':'.$return);
				$parts->setPath(JURI::base(true).'/index.php?option=com_osticky2&task=thread.login&key='.$key);

				$jurl = $parts->toString();
				$jlink = JText::sprintf('COM_OSTICKY_TICKETS_LOGIN_LINK', $sitename);
				$jlink .= $jurl;
			}
			else
			{
				// osticket2 authentication plugin not found
				// leave jlink empty
			}
		}

		$search = array(
			'/%\{ticket\.id\}/',
			'/%\{ticket\.number\}/',
			'/%\{ticket\.email\}/',
			'/%\{ticket\.name\}/',
			'/%\{recipient\.name\.first\}/',
			'/%\{recipient\.name\}/',
			'/%\{ticket\.subject\}/',
			'/%\{message\}/',
			'/%\{response\}/',
			'/%\{ticket\.topic\}/',
			'/%\{ticket\.topic\.name\}/',
			'/%\{ticket\.status\}/',
			'/\%{ticket\.dept\.name\}/',
			'/%\{signature\}/',
			'/%\{recipient\.name\}/',
			'/%\{ticket\.create_date\}/',
			'/%\{ticket\.due_date\}/',
			'/%\{ticket\.close_date\}/',
			'/%\{assignee\.name\}/',
			'/%\{assignee\.name\.first\}/',
			'/%\{assigner\.name\}/',
			'/%\{assigner\.name\.short\}/',
			'/%\{url\}/',
			'/%jurl/',
			'/%sticky2_url/',
			'/%\{ticket\.client_link\}/',
			'/%\{recipient\.ticket_link\}/',
			'/%\{ticket\.staff_link\}/'
		);
		
		$replace = array(
			$data->id,
			$data->number,
			$data->email,
			$data->name,
			$data->recipient_name,
			$data->recipient_name,
			$data->subject,
			$data->message,
			$data->message,
			$data->topic,
			$data->topic,
			$data->status,
			$data->dept,
			$data->signature,
			isset($data->staff) ? $data->staff : '',
			$data->createdate,
			$data->duedate,
			$data->closedate,
			$data->recipient_name,
			$data->recipient_name,
			isset($data->assigner_name) ? $data->assigner_name : '',
			isset($data->assigner_name) ? $data->assigner_name : '',
			osTicky2Helper::getOstConfig('helpdesk_url'),
			$jlink,
			JText::sprintf('COM_OSTICKY_TICKET_STICKY_VIEW_LINK', $sitename).$data->sticky2_url,
			osTicky2Helper::getOstConfig('helpdesk_url').'tickets.php?e='.$data->email.'&t='.urlencode($data->number),
			osTicky2Helper::getOstConfig('helpdesk_url').'tickets.php?e='.$data->email.'&t='.urlencode($data->number),
			osTicky2Helper::getOstConfig('helpdesk_url').'scp/tickets.php?id='.$data->id
		);
		
		// add more search_replace from form_data
		foreach($data->form_data as $form_id => $fields)
		{
			$pattern = '/%%\{ticket\.%s\}/';

			foreach($fields['fields'] as $field)
			{
				$key = sprintf($pattern, $field->name);
				// prevent keys duplicates
				if(!in_array($key, $search))
				{
					$search[] = $key;
					$replace[] = $field->value;
				}
				// look if list item properties are set for this field	// ***1.9***
				if(!empty($field->properties))
				{
					foreach($field->properties as $prop_name => $prop_val)
					{
						// construct the template replace key as field_name.property_name
						$key = sprintf($pattern, $field->name . '.' . $prop_name);
						// prevent keys duplicates
						if(!in_array($key, $search))
						{
							$search[] = $key;
							$replace[] = $prop_val;
						}
					}
				}
			}
		}
		
		// add more search_replace from company data
		foreach($data->company_data as $field)
		{
			$pattern = '/%%\{company\.%s\}/';

			$key = sprintf($pattern, $field->name);
			// prevent keys duplicates
			if(!in_array($key, $search))
			{
				$search[] = $key;
				$replace[] = $field->value;
			}
		}
		
		$result = preg_replace($search, $replace, $tpl);
		
		// clean result from %{...} keys left unreplaced
		$result = preg_replace('#%\{[^\}]*\}#', '', $result);
		
		/** reserved, needs refactoring
		 * 
		$matches = array();
		$images = preg_match_all('/"cid:([\w._-]{32})"/', $result, $matches);
		if($images)
		{
			$file_table = JTable::getInstance('File', 'osTicky2Table', array('dbo' => self::getDbo()));
			foreach($matches[1] as $i => $match)
			{
				if($file_table->load(array('key' => $match)))
				{
					// $$$ here we have a problem: session_id will NOT work for security check in case when
					// attachment is open from email program (the session id will not be the same, giving
					// 'invalid ref' fatal error in attachment raw view
					
					// we will have to find another method for security check, at least for the cases
					// when the image link is included into email body, but still secure enough...
					// we will decide later...
					
					$hash = md5(session_id().$match);
					
					$url = JUri::root().'index.php?option=com_osticky2&view=attachment&format=raw&file_id='.$file_table->id;
					$result = str_replace($matches[0][$i], sprintf('"%s&ref=%s"', $url, $hash), $result);
				}
			}
		}
		* end reserved */
		
		// strip images from emailed alerts - radical solution...
		$result = JFilterOutput::stripImages($result);
		if(trim($result) == '')
		{
			// ensure non-empty body
			$result = '-';
		}
		
		return $result;
	}

	/*
	 * Native osTicket way of displaying dates (used in all tickets/thread views
	 * to display created/closed/reopened etc. dates in the same way they appear
	 * in osTicket.
	 */
	
	public static function dateFromOSTwFormat($date, $format = 'datetime_format')
	{
		$sql_tz_diff = osTicky2Helper::getOstConfig('sql_tz_diff');
		$date_ts = is_int($date) ? $date : strtotime($date);
		$gmtime = $date_ts - ($sql_tz_diff * 3600);
		
		$offset = JFactory::getUser()->getParam('timezone', JFactory::getConfig()->get('offset', 'UTC'));
		
		$tmp = new JDate();
		$tmp->setTimezone(new DateTimeZone($offset));
		$offset_sec = $tmp->getOffset();
		
		$format = osTicky2Helper::getOstConfig($format);
		
		return date($format, $gmtime + $offset_sec);
	}

	/** $$$ looks like it is not used for security... check this
	 * 
	 * IMPORTANT! compatibility with replies from osTicket Backend!!!
	 * 
	 * Copy of osTicket native clickableUrls function
	 * @param string $text
	 * @return html link if found in text
	 */
	public static function clickableUrls($text = '')
	{
		$text=preg_replace('/(((f|ht){1}tp(s?):\/\/)[-a-zA-Z0-9@:%_\+.~#?&;\/\/=]+)/','<a href="\\1" target="_blank">\\1</a>', $text);
		$text=preg_replace("/(^|[ \\n\\r\\t])(www\.([a-zA-Z0-9_-]+(\.[a-zA-Z0-9_-]+)+)(\/[^\/ \\n\\r]*)*)/",
			'\\1<a href="http://\\2" target="_blank">\\2</a>', $text);
		$text=preg_replace("/(^|[ \\n\\r\\t])([_\.0-9a-z-]+@([0-9a-z][0-9a-z-]+\.)+[a-z]{2,4})/",'\\1<a href="mailto:\\2" target="_blank">\\2</a>', $text);

		return $text;
	}

	public static function getMimeTypeFromExt($ext = null)
	{
		static $ctypes = array(
			"pdf" => "application/pdf",
			"exe" => "application/octet-stream",
			"zip" => "application/zip",
			"doc" => "application/msword",
			"xls" => "application/vnd.ms-excel",
			"ppt" => "application/vnd.ms-powerpoint",
			"gif" => "image/gif",
			"png" => "image/png",
			"jpg" => "image/jpg",
			"txt" => "text/plain",
			"ppsx" => "application/vnd.openxmlformats-officedocument.presentationml.slideshow",
			"xlsx" => "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
			"docx" => "application/vnd.openxmlformats-officedocument.wordprocessingml.document"
		);
		if(empty($ext))
		{
			return $ctypes;
		}
		else 
		{
			return isset($ctypes[$ext]) ? $ctypes[$ext] : "application/force-download";
		}
	}

	public static function getAcceptedMimeTypes()
	{
		$allowed_types = self::getOstConfig('allowed_filetypes');
		if(trim($allowed_types) == '.*')
		{
			$ctypes = array_values(self::getMimeTypeFromExt());
		}
		else 
		{
			$types = explode(',', $allowed_types);
			$types = array_map('trim', $types);
			$ctypes = array();
			foreach($types as $type)
			{
				$ctypes[] = self::getMimeTypeFromExt(ltrim($type, '.'));
			}
		}
		$accept = implode(',', $ctypes);
		return $accept;
	}
	
	public static function makeSafe($file)
	{
		$regex = array('#(\.){2,}#', '#[^A-Za-z0-9а-яА-ЯёЁ\.\_\- ]#u', '#^\.#');
		return preg_replace($regex, '', $file);
	}
	

	public static function getDbTime()
	{
		$db = self::getDbo();
		$db->setQuery('SELECT NOW()');
		return $db->loadResult();
	}

	/**
	 *
	 * Validate tickets' sticky links
	 * @param
	 * 		array $items - tickets to validate (must have sticky2_id and sticky2_url properties set)
	 * @return
	 * 		array -  modified $items (with broken links reset to empty strings)
	 * 		false if curl not intalled
	 */

	public static function validateStickyLinks($items = array())
	{
		if (!function_exists('curl_init') || !is_callable('curl_init'))
		{
			// if no curl found do not check, just return
			return false;
		}

		// load existing check results from session
		$app = JFactory::getApplication();
		$linksChecked = $app->getUserState('com_osticky2.sticky2_urls', array());

		// init curl
		$ch = curl_init();
		$ok = curl_setopt($ch, CURLOPT_HEADER, 1);
		$ok = curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

		foreach($items as $i => $item)
		{
			if(empty($item->sticky2_url))
			{
				// only validate items with links
				continue;
			}
			if(!array_key_exists($item->sticky2_id, $linksChecked))
			{
				// this link wes not checked yet
				curl_setopt($ch, CURLOPT_URL, $items[$i]->sticky2_url);
				$data = curl_exec($ch);
				$headers = curl_getinfo($ch);
				$status = $headers['http_code'];
				if(!$linksChecked[$item->sticky2_id] = ($status != '404'))
				{
					// broken link (404)
					$items[$i]->sticky2_url = '';
				}
			}
			elseif(!$linksChecked[$item->sticky2_id])
			{
				// this link was already checked and is broken:
				// erase sticky2_url property
				$items[$i]->sticky2_url = '';
			}
			else
			{
				// this link was already checked and is valid:
				// do nothing
			}
		}

		curl_close($ch);

		// save check results to session
		$app->setUserState('com_osticky2.sticky2_urls', $linksChecked);
		return $items;
	}
	
	/*
	 * Basic implementation of ticket filtersy". Only reject filters are implemented,
	 * excluding those which have match_all_rules = 1 (for reject feature this option
	 * is very unlikely to be ever used). Error messages are generated as custom texts
	 * for standard check fields: email, name, subject and message. For all other 
	 * form fields that do not pass the filter a generic error message is generated.
	 */
	
	public static function filterTicket($ticket_email, $ticket_name, $ticket_message, $ticket)
	{
		$db = self::getDbo();
		$query = 'SELECT fr.* FROM #__filter_rule AS fr INNER JOIN #__filter AS f ON f.id = fr.filter_id AND f.isactive = 1 AND f.reject_ticket = 1 AND (f.target = "Any" OR f.target = "Web")';
		$query .= ' WHERE fr.isactive = 1 AND f.match_all_rules = 0 GROUP BY fr.id ORDER BY f.execorder ASC';
		$db->setQuery($query);
		try
		{
			$filters = $db->loadObjectList();
		}
		catch(Exception $e)
		{
			return false;
		}
		if(empty($filters)) {
			// if no filters found just return
			return false;
		}
		
		// get ticket details form fields data
		$ticket_form = $ticket['form_2'];
		// add message body
		$ticket_form['message'] = $ticket_message;
		
		// get field names, so that we can get field ids in order to contruct
		// filter "what" in the same format, native osTicket does: "field.<field_id>"
		$ticket_field_names = $db->Quote(array_keys($ticket_form));
		
		$query = 'SELECT id, name FROM #__form_field WHERE form_id = 2 AND name IN('.implode(',',$ticket_field_names).')';
		$db->setQuery($query);
		$fields = $db->loadObjectList('id');
		
		// construct "whats" and error codes to use with Joomla language string keys
		$ticket_whats = array();
		$error_codes = array();
		foreach($fields as $id => $field)
		{
			$ticket_whats['field.'.$id] = $ticket_form[$field->name];
			$error_codes['field.'.$id] = $field->name;
		}
		
		// add obligatory "whats"
		$ticket_whats['email'] = $ticket_email;
		$ticket_whats['name'] = $ticket_name;
		// add obligatory error codes
		$error_codes['email'] = 'email';
		$error_codes['name'] = 'name';
		
		$how = array(
            # how => array(function, null or === this, null or !== this)
			'equal'     => array('strcasecmp', 0),
			'not_equal' => array('strcasecmp', null, 0),
			'contains'  => array('stripos', null, false),
			'dn_contain'=> array('stripos', false),
			'starts'    => array('stripos', 0),
			'ends'      => array(array('osTicky2Helper', 'iendsWith'), true),
			'match'     => array(array('osTicky2Helper', 'pregMatchB'), 1),
			'not_match' => array(array('osTicky2Helper', 'pregMatchB'), null, 0)
        );
        
		foreach($filters as $filter)
		{
			if(!isset($how[$filter->how]))
			{
				continue;
			}
			if(isset($ticket_whats[$filter->what]))
			{
				$testVal = $ticket_whats[$filter->what];
				@list($func, $pos, $neg) = $how[$filter->how];
				
				$result = call_user_func($func, $testVal, $filter->val);
				if(($pos === null && $result !== $neg) || ($result === $pos))
				{
					$error_code = in_array($error_codes[$filter->what], array('email', 'name', 'subject', 'message')) ? $error_codes[$filter->what] : 'MISC';
					return JText::_('COM_OSTICKY_TICKET_FILTER_'.$error_code.'_FAILED').htmlspecialchars($testVal, ENT_COMPAT, 'UTF-8');
				}
			}
		}
		
		return false;
	}
	
	// filter helpers (copy from class.filter.php if the native osTicket)
	public static function iendsWith($haystack, $needle)
	{
		$length = mb_strlen($needle);
		if ($length == 0) {
			return true;
		}

		return (strcasecmp(mb_substr($haystack, -$length), $needle) === 0);
	}
	
	public static function pregMatchB($subject, $pattern)
	{
		return preg_match($pattern, $subject);
	}
}
