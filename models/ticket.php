<?php
/**
 * @package osTicky2 (osTicket 1.9.4 Bridge) for Joomla 3
 * @version 2.2.3: ticket.php
 * @author Alex Polonski
 * @copyright (C) 2012 - 2014 - Alex Polonski
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
**/
// No direct access
defined('_JEXEC') or die;

jimport('joomla.application.component.modelform');

require_once JPATH_COMPONENT_ADMINISTRATOR.'/helpers/osticky2.php';
require_once JPATH_COMPONENT_ADMINISTRATOR.'/helpers/ticket.php';

class osTicky2ModelTicket extends JModelForm
{
	protected $view_item = 'ticket';
	protected $view_list = 'tickets';

	public function __construct($config = array())
	{
		parent::__construct($config);
		parent::setDbo(osTicky2Helper::getDbo());
	}
	
	public function getTable($type = 'Ticket', $prefix = 'osTicky2Table', $config = array())
	{
		JTable::addIncludePath(JPATH_ADMINISTRATOR.'components/osticky2/tables');
		$config['dbo'] = $this->getDbo();
		return JTable::getInstance($type, $prefix, $config);
	}
	
	protected function populateState()
	{
		$app = JFactory::getApplication('site');
		
		// Load the parameters.
		$params = $app->getParams();
		
		$active	= $app->getMenu()->getActive();
		if($active)
		{
			$params->merge($active->params);
		}
		$this->setState('params', $params);
		
		$nView = $app->input->get('view', 'ticket');
		$this->setState('view', $nView);
		
		$form_data = $app->input->post->get('jform', array(), 'array');
		if(empty($form_data))
		{
			$form_data = $app->getUserState('com_osticky2.ticket.data', array());
		}
		
		$force_topic_id = ($nView != 'ticket_modal' ? $params->get('preselected_topic_id', '') : $params->get('sticky_topic_id', 0));
		$this->setState('force_topic_id', $force_topic_id);
		
		$topic_id = isset($form_data['ticket']['topic_id']) ? $form_data['ticket']['topic_id'] : (int)$force_topic_id;
		$this->setState('topic_id', $topic_id);
	}

	public function getForm($data = array(), $loadData = true)
	{
		/*
		$app		= JFactory::getApplication();
		$params		= $app->getParams();
		
		$active	= $app->getMenu()->getActive();
		if($active)
		{
			$params->merge($active->params);
		}
		*/
		
		$params = $this->getState('params');
		
		// Get the form.
		$nView = $this->getState('view', 'ticket');
		
		$user = JFactory::getUser();
		
		try 
		{
			$user_form = TicketHelper::getTicketEditForm(null, 1, array('contact_fields' => $user->get('id', 0) > 0 ? 'core' : 'all'));
			$ticket_form = TicketHelper::getTicketEditForm(null, 2);
			$custom_form = TicketHelper::getTicketEditForm($this->getState('topic_id', 0));
		}
		catch(Exception $e)
		{
			$this->setError($e->getMessage());
			return false;
		}
		
		$xml = '
			<form>
				<fields name="ticket">
					<fieldset
						addrulepath="/components/com_osticky2/models/rules"
						name="topic">
						<field name="topic_id"
							type="helptopic"
							description="COM_OSTICKY_TICKET_HELPTOPIC_DESC"
							label="COM_OSTICKY_TICKET_HELPTOPIC_LABEL"
							required="true"
							class="required"
							default=""
							translate="true"
							hide_private="0"
							key_field="topic_id"
							value_field="topic"
							onchange="document.id(\'task\').set(\'value\', \'\');this.form.submit();"
						>
							<option value="">COM_OSTICKY_TICKET_OPTION_SELECT_HELPTOPIC</option>
						</field>
					</fieldset>
					'
					// all dynamic forms here
					.$user_form
					.$custom_form
					.$ticket_form.'
				</fields>
				<fields name="message">
					<fieldset 
						addrulepath="/components/com_osticky2/models/rules"
						name="message">
						<field name="hide_client_info"
							type="checkbox"
							description="COM_OSTICKY_MESSAGE_NO_SERVICE_INFO_DESC"
							label="COM_OSTICKY_MESSAGE_NO_SERVICE_INFO_LABEL"
							default="0"
							value="1"
							onclick="var cb=document.id(\'jform_message_hide_client_info\');var toHide=$$(\'.client_info_field\');toHide.each(function(el){el.style.display=(cb.checked?\'none\':\'block\');el.setAttribute(\'disabled\',cb.checked?\'true\':\'false\');});"
						/>
						<field name="sticky_info"
							type="hidden"
							filter="raw"
						/>
						<field name="client_info"
							type="textarea"
							cols="80"
							rows="4"
							readonly="true"
							class="client_info_field span-9 readonly"
							labelclass="client_info_field"
							description="COM_OSTICKY_MESSAGE_ENVIRONMENT_DESC"
							label="COM_OSTICKY_MESSAGE_ENVIRONMENT_LABEL"
						/>	
					</fieldset>
					<fieldset name="captcha">
						<field
							name="captcha"
							type="captcha"
							label="COM_OSTICKY_CAPTCHA_LABEL"
							description="COM_OSTICKY_CAPTCHA_DESC"
							validate="captcha"
							namespace="ticket"
						/>
					</fieldset>	
				</fields>
				<fieldset 
					addrulepath="/components/com_osticky2/models/rules"
					name="file_upload">
					
					<field name="filedata"
						label="COM_OSTICKY_FILE_UPLOAD_LABEL"
						description="COM_OSTICKY_FILE_UPLOAD_DESC"
						type="attachments"
						validate="file"
						message_size="COM_OSTICKY_FILE_UPLOAD_ERROR_SIZE"
						message_type="COM_OSTICKY_FILE_UPLOAD_ERROR_TYPE"
					/>
				</fieldset>		
			</form>';
		
		$form = $this->loadForm('com_osticky2.ticket', $xml, array('control' => 'jform', 'load_data' => $loadData));
		if(empty($form))
		{
			return false;
		}
		
		if($user->get('id'))
		{
			$form->setFieldAttribute('email', 'readonly', 'true', 'ticket.form_1');
			$form->setFieldAttribute('email', 'class', 'readonly', 'ticket.form_1');
			$form->setFieldAttribute('name', 'readonly', 'true', 'ticket.form_1');
			$form->setFieldAttribute('name', 'class', 'readonly', 'ticket.form_1');
			
			// remove captcha for logged-in users
			$form->removeField('captcha', 'message');
		}
		if(!osTicky2Helper::getOstConfig('enable_captcha'))
		{
			$form->removeField('captcha', 'message');
		}
		if(!osTicky2Helper::getOstConfig('allow_priority_change'))
		{
			$form->setFieldAttribute('priority_id', 'type', 'hidden', 'ticket.form_2');
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
		}
		
		if(!$params->get('send_client_info'))
		{
			$form->removeField('hide_client_info', 'message');
			$form->removeField('client_info', 'message');
		}
		else 
		{
			jimport('joomla.environment.browser');
			$env = "\n\n#-----#\nClient's environment info: ".JBrowser::getInstance()->getAgentString().
					"\nJoomla Platform: ".JPlatform::getShortVersion()."\n#-----#\n";
			$form->setValue('client_info', 'message', $env);
		}
		
		if($nView != 'ticket_modal')
		{
			$form->removeField('sticky_info', 'message');
		}
		
		// limit help topic if set in menu parameters or component settings for sticky tickets
		$topic_id = $this->getState('force_topic_id', 0);
		if($topic_id)
		{
			$form->setFieldAttribute('topic_id', 'type', 'hidden', 'ticket');
		}
		
		return $form;
	}

	protected function loadFormData()
	{
		// Look at post for form data (for onchange="this.form.submit();")
		$data = JRequest::getVar('jform', array(), 'post', 'array');
		if(empty($data)) {
			// Check the session for previously entered form data.
			$data = (array)JFactory::getApplication()->getUserState('com_osticky2.ticket.data', array());
		}
		if(empty($data))
		{
			$user = JFactory::getUser();
			if($user->get('id'))
			{
				$data['ticket']['form_1']['name'] = $user->name;
				$data['ticket']['form_1']['email'] = $user->email;
				
				/* $$$ deactivated for now
				// check user profiles for phone and phone_ext
				$profile_plugin = JPluginHelper::getPlugin('user', 'profile');
				// do not fetch any data if plugin is not enabled
				if($profile_plugin) {
					$db = JFactory::getDbo();
					$db->setQuery('SELECT profile_key, profile_value FROM #__user_profiles WHERE user_id = '.$user->get('id', 0));
					try
					{
						$profile = $db->loadAssocList('profile_key');
					}
					catch(Exception $e)
					{
						return $data;
					}
					if(isset($profile['profile.phone'])) {
						// phone found - auto-populate field
						$data['ticket']['form_1']['phone'] = trim($profile['profile.phone']['profile_value'], " \"\'");
					}
					if(isset($profile['profile.phone_ext'])) {
						// phone_ext found - auto-populate field
						$data['ticket']['form_1']['phone_ext'] = trim($profile['profile.phone_ext']['profile_value'], " \"\'");
					}
				}
				*/
			}
		}
		
		// limit help topic if set in menu parameters or component settings for sticky tickets
		$topic_id = $this->getState('force_topic_id', 0);
		if($topic_id)
		{
			$data['ticket']['topic_id'] = $topic_id;
		}
		
		return $data;
	}
	
	public function submit($data, $files = null)
	{
		$canCreate = osTicky2Helper::getActions()->get('osticky.create', 0);
		if(!$canCreate)
		{
			$this->setError(JText::_('JERROR_ALERTNOAUTHOR'));
			return false;
		}
		
		$ticket = $data['ticket'];
		$message = !empty($data['message']) ? $data['message'] : array();
		
		// get and check requied data
		
		// get the data the will not go to ticket or form
		// entries data
		$ticket_email = $ticket['form_1']['email'];
		$ticket_name = $ticket['form_1']['name'];
		$ticket_message = $ticket['form_2']['message'];
		
		$ticket_subject = $ticket['form_2']['subject'];
		
		// delete this data from ticket forms
		unset($ticket['form_1']['name']);
		unset($ticket['form_1']['email']);
		unset($ticket['form_2']['message']);
		
		// get the form object to look for the field types
		$form = $this->getForm(null, false);
		
		$db = $this->getDbo();
		
		if(($limit = osTicky2Helper::getOstConfig('max_open_tickets')) > 0)
		{
			$query = 'SELECT COUNT(t.ticket_id) FROM #__ticket AS t INNER JOIN #__user_email AS ue ON ue.user_id = t.user_id INNER JOIN #__ticket_status AS ts ON ts.id = t.status_id WHERE ue.address = '.$db->Quote($ticket_email).' AND ts.state = '. $db->Quote('open') .' GROUP BY ue.user_id';
			$db->setQuery($query);
			try
			{
				if((int)$db->loadResult() >= $limit)
				{
					// reserved, needs refactoring
					// osTicky2Helper::sendOverLimitNotice($ticket);
					$this->setError(JText::_('COM_OSTICKY_TICKET_TOO_MANY_OPEN_TICKETS'));
					return false;
				}
			}
			catch(Exception $e)
			{
				$this->setError($e->getMessage());
				return false;
			}
		}
		
		// Populate ticket fields
		
		$query = '
			SELECT t.topic,
			t.priority_id AS default_priority,
			t.dept_id,
			t.staff_id,
			t.team_id, '.
			//e.email,			// *** not used, but check it in all scenarios ***
			'd.dept_name,
			d.sla_id
			FROM #__help_topic AS t 
			LEFT JOIN #__department AS d ON t.dept_id = d.dept_id '.
			//LEFT JOIN #__email AS e ON e.email_id = d.email_id  
			'WHERE t.topic_id = '.$ticket['topic_id'] . ' 
			GROUP BY t.topic_id';
		
		$db->setQuery($query);
		
		try
		{
			$topic = $db->loadObject();
		}
		catch(Exception $e)
		{
			$this->setError($e->getMessage());
			return false;
		}
		if(empty($topic))
		{
			$this->setError(JText::_('COM_OSTICKY_ERROR_INVALID_HELPTOPIC'));
			return false;
		}
		// if a topic is linked with a "default system" department we have to get the right
		// department for osTicket configuration
		if(empty($topic->dept_id))
		{
			$topic->dept_id = osTicky2Helper::getOstConfig('default_dept_id');
		}
		$ticket['dept_id'] = $topic->dept_id;
		
		// If custom priority set in form - use it, otherwise use default for topic or global default
		$default_system_priority = (int)osTicky2Helper::getOstConfig('default_priority_id');
		$ticket['form_2']['priority'] = !empty($ticket['form_2']['priority']) ? $ticket['form_2']['priority'] : (!empty($topic->default_priority) ? $topic->default_priority : $default_system_priority);
		
		$ticket['staff_id'] = $topic->staff_id;
		$ticket['team_id'] = $topic->team_id;
		
		// Set SLA plan
		$ticket['sla_id'] = !empty($topic->sla_id) ? $topic->sla_id : osTicky2Helper::getOstConfig('default_sla_id');
	
		// proceed with ticket save
		
		$app		= JFactory::getApplication();
		$params		= $app->getParams();
		
		// check if the user already exists
		$db = osTicky2Helper::getDbo();
		$query = $db->getQuery(true);
		$query->select('ue.user_id')
			->from('#__user_email AS ue')
			->select('u.name')
			->join('LEFT', '#__user AS u ON u.default_email_id = ue.id')
			->where('ue.address = '.$db->Quote($ticket_email));
		$db->setQuery($query);
		try 
		{
			$user_data = $db->loadObject();
		}
		catch(RuntimeException $e)
		{
			$this->setError($e->getMessage());
			return false;
		}
		
		$db->transactionStart();
		
		// we place ticketID creation inside transaction, because for sequential numbers
		// we have to advance next id after getting an id for this ticket. If ticket creation
		// fails, we have to roll back to the old next id. Check it for concurrent connections $$$
		$ticket['number'] = $this->generateNewTicketID($ticket['topic_id']);
		if($ticket['number'] === false)
		{
			// error set in generateNewTicketID
			return false;
		}
		
		// set initial status_id. Status can be updated in updateTicketActivity() when called
		// from message model submit().
		$status_id = osTicky2Helper::getOstConfig('default_ticket_status_id');
		$ticket['status_id'] = $status_id;
		
		$now = osTicky2Helper::getDbTime();
		
		if(!empty($user_data))
		{
			// overwrite name with saved if the user's email found
			$ticket_name = $user_data->name;
			$ticket['user_id'] = $user_data->user_id;
			$is_new_user = false;
		}
		else
		{
			// create user
			try 
			{
				$query = $db->getQuery(true);
				
				$query->insert('#__user', 'id')
					->set('default_email_id = 0') // later we update it with the email record id created
					->set('name = '.$db->Quote($ticket_name))
					->set('created = '.$db->Quote($now))
					->set('updated = '.$db->Quote($now));
				$db->setQuery($query);
				$db->execute();
				
				$user_id = $db->insertid();
				
				// insert record into user_email table
				$query = $db->getQuery(true);
				$query->insert('#__user_email', 'id')
					->set('user_id = '.(int)$user_id)
					->set('address = '.$db->Quote($ticket_email));
				$db->setQuery($query);
				$db->execute();
				
				$email_id = $db->insertid();
				
				$query = $db->getQuery(true);
				$query->update('#__user')
					->set('default_email_id = '.(int)$email_id)
					->where('id = '.(int)$user_id);
				$db->setQuery($query);
				$db->execute();
				
				$ticket['user_id'] = $user_id;
				$is_new_user = true;
			}
			catch(RuntimeException $e)
			{
				$db->transactionRollback();
				$this->setError($e->getMessage());
				return false;
			}
		}
		
		// save this data in model (for confirmation view)
		$this->setState('ticket.name', $ticket_name);
		$this->setState('ticket.email', $ticket_email);
		
		// Check for valid email
		$user = JFactory::getUser();
		if($user->get('email') != $ticket_email)
		{
			// user is not logged in - check that he doesn't use email
			// of an existing Joomla user (i.e. with non-empty password)
			$jdb = JFactory::getDbo();
			$query = 'SELECT id FROM #__users WHERE NOT password = "" AND email = '.$jdb->Quote($ticket_email);
			$jdb->setQuery($query);
			try
			{
				if($jdb->loadResult())
				{
					$this->setError(JText::_('COM_OSTICKY_TICKET_EMAIL_IN_USE'));
					$db->transactionRollback();
					return false;
				}
			}
			catch(Exception $e)
			{
				$this->setError($e->getMessage());
				$db->transactionRollback();
				return false;
			}
		}
		
		/*
		 * very basic ticket filter. Only reject filters are implemented. See comments
		 * for the method in helper
		 */
		if($error = osTicky2Helper::filterTicket($ticket_email, $ticket_name, $ticket_message, $ticket)) {
			$this->setError($error);
			$db->transactionRollback();
			return false;
		}

		// Save ticket
		$table = $this->getTable();
		
		if(!$table->save($ticket))
		{
			$this->setError($table->getError());
			$db->transactionRollback();
			return false;
		}
		
		// save ticket number in state (for confirmation view)
		$this->setState('ticket.number', $table->number);
		
		// save ticket id (for form entries generation)
		$this->setState('ticket_id', $table->ticket_id);
		
		// add form entries
		
		$form_entries = array();
		
		foreach($ticket as $group => $fields)
		{
			if(strpos($group, 'form_') !== 0)
			{
				continue;
			}
			$form_id = (int)substr($group, 5);
			if(!$is_new_user && $form_id == 1)
			{
				// we do not update user contact data (form_id = 1) for existing
				// users.
				continue;
			}
			foreach($fields as $name => $value)
			{
				if($form_id == 2 && $name == 'priority')
				{
					$db->setQuery('SELECT priority_desc FROM #__ticket_priority WHERE priority_id = '.(int)$value);
					$priority_desc = $db->loadResult();
					$db->setQuery('SELECT id FROM #__form_field WHERE form_id = 2 AND `type` = '.$db->Quote('priority').' AND name = '.$db->Quote('priority'));
					$priority_field_id = $db->loadResult();
					
					$form_entry = new stdClass();
					$form_entry->value = $priority_desc;
					$form_entry->value_id = $value;
					$form_entry->field_id = $priority_field_id;
					$form_entry->field_name = 'priority';
					$form_entry->form_id = 2;
				}
				else
				{
					$field_data = $form->getField($name, 'ticket.'.$group);

					$form_entry = TicketHelper::getEntryValue($form_id, $field_data->fieldname, $value, $field_data->type);
					if($form_entry instanceof Exception)
					{
						$this->setError($form_entry->getMessage());
						$db->transactionRollback();
						return false;
					}
				}
				if(!isset($form_entries[$form_id]))
				{
					$form_entries[$form_id] = array();
				}
				$form_entries[$form_id][] = $form_entry;
			}
		}
		
		// insert form entry values
		try
		{
			foreach($form_entries as $form_id => $entries)
			{
				$object_type = $form_id == 1 ? 'U' : 'T';
				$object_id = $object_type == 'U' ? $table->user_id : $table->ticket_id; 
				$query = 'INSERT INTO #__form_entry VALUES(NULL,';
				$query .= $form_id.','.$object_id.','.$db->Quote($object_type).',1,'.$db->Quote($now).','.$db->Quote($now);
				$query .= ')';
				
				$db->setQuery($query);
				$db->execute();
				
				$entry_id = $db->insertid();
				
				foreach($entries as $entry)
				{
					// $$$ should we trim entry values?
					
					// convert empty values to NULL (native osTicket behavior), leave explicit "0" as is for bool values
					if(!empty($entry->value) || $entry->value === 0 || trim($entry->value) === '0')
					{
						$value = $db->Quote($entry->value);
					}
					else
					{
						$value = 'NULL';
					}
					if(!empty($entry->value_id))
					{
						$value_id = $db->Quote($entry->value_id);
					}
					else
					{
						$value_id = 'NULL';
					}
					$query = 'INSERT INTO #__form_entry_values VALUES(';
					$query .= $entry_id.','.$entry->field_id.','.$value.','.$value_id;
					$query .= ')';
					
					$db->setQuery($query);
					$db->execute();
				}
			}
		}
		catch(Exception $e)
		{
			$this->setError($e->getMessage());
			$db->transactionRollback();
			return false;
		}
		
		// Save message
		
		$message['ticket_id'] = $table->ticket_id;
		$message['body'] = $ticket_message;
		$message['ticketID'] = $table->number; // used for service info attachment filename
		$message['email'] = $ticket_email; // used for service info attachment text
		
		$messageModel = JModelLegacy::getInstance('Message', 'osTicky2Model', array('db' => osTicky2Helper::getDbo()));
		if(!$messageModel->submit($message, $files, false)) // last argument 'false' means that it is not a reply
		{
			$this->setError($messageModel->getError());
			$db->transactionRollback();
			return false;
		}

		$db->transactionCommit();
		
		// drop #__ticket_cdata table, to make sure that it will be recreated and 
		// filled with rows in native osTicket "tickets list view"
		$db->setQuery('DROP TABLE IF EXISTS `#__ticket__cdata`');
		try 
		{
			$db->execute();
		}
		catch(RuntimeException $e)
		{
			// this happens if current database user doen't have privileges for DROP TABLE
			// the ticket can be submitted OK, but ticket list view in original osTicket will
			// not be displayed correctly.
			$app->enqueueMessage(JText::_('COM_OSTICKY_WARNING_OST_DATABASE_PRIVILEGES'), 'warning');
		}
		
		// if the user was authenticated by a ticket number, but have successfully raised a new ticket
		// change ticket number in session so that the use is redirected to the recently created ticket
		// thread
		$auth_ticket_number = $app->getUserState('com_osticky2.auth.ticket_number', null);
		if(!empty($auth_ticket_number))
		{
			$app->setUserState('com_osticky2.auth.ticket_number', $this->getState('ticket.number', null));
		}
		
		return true;
	}
	
	/**
	 * Method to be called after a message is saved. The message can be the first in thread
	 * (new ticket) or a reply in ticket thread (existing ticket).
	 * 
	 * If this method is called on a closed ticket, the ticket will be reopened.
	 * 
	 * Also calls addTicketEvent() helper method to insert record(s) into ticket_event table
	 * 
	 * @param int $ticket_id			- ticket id
	 * @param string $created			- message creation date
	 * @param boolean $isReply			- false: new tiket (first message in thread)
	 * 									- true: existing ticket (a message in thread)
	 * @param boolean $is_reassigned	- return value - will be set to true if the ticket has
	 * 									to be reassigned (assigned to staff or change assigned
	 * 									staff) - used in caller method to send alerts
	 * @return boolean					- true if success, false if error
	 */
	public function updateTicketActivity($ticket_id, $created, $isReply, &$is_reassigned)
	{
		if(!$ticket_id)
		{
			$ticket_id = $this->getState('ticket_id', 0);
		}
		if($ticket_id)
		{
			$table = $this->getTable();
			if($table->load($ticket_id))
			{
				// Check if we have to reopen ticket
				if($table->status == 'closed')
				{
					// normally we never get at this point if ticket thread is explicitly closed,
					// but we add additional check here. Also we have to get reopen status.
					$ticket = TicketHelper::getTicketById($ticket_id);
					if(empty($ticket->status_properties->allowreopen))
					{
						$this->setError(JText::_('COM_OSTICKY_ERROR_TICKET_REOPEN_NOT_ALLOWED'));
						return false;
					}
					if(!empty($table->lastresponse))
					{
						// Check if we have last respondent available and assign the reopened ticket
						if($staff_id = $this->getRespondentToAssign($table->ticket_id))
						{
							$is_reassigned = empty($table->staff_id) || $table->staff_id != $staff_id;
							$table->staff_id = $staff_id;
						}
					}
					// Set status to reopenstatus defined in status_properties
					$table->status_id = !empty($ticket->status_properties->reopenstatus) ? $ticket->status_properties->reopenstatus : 1;
					$table->reopened = $created;
					// We leave closed date intact here - it will still
					// hold the date when the ticket was closed
					
					// add reopened flag - we use it to add ticket events
					$is_reopened = true;
				}
				// update last message
				$table->lastmessage = $created;
				// this is the last message, so actually ticket is not answered
				$table->isanswered = 0;
				
				if(!$table->store())
				{
					$this->setError($table->getError());
					return false;
				}
				
				// only add ticket events on new tickets
				if(!$isReply)
				{
					if(!$this->addTicketEvent($table, 'created'))
					{
						// error set in method
						return false;
					}
					if(!empty($table->staff_id) || !empty($table->team_id))
					{
						if(!$this->addTicketEvent($table, 'assigned'))
						{
							// error set in method
							return false;
						}
					}
				}
				
				if(!empty($is_reopened))
				{
					// add reopen event
					if(!$this->addTicketEvent($table, 'reopened'))
					{
						// error set in method
						return false;
					}
						
					// add assigned event if the ticket was reassigned
					if(!empty($is_reassigned) && !$this->addTicketEvent($table, 'reassigned'))
					{
						// error set in method
						return false;
					}
				}
				
				return true;
			}
		}
		$this->setError(JText::_('COM_OSTICKY_ERROR_TICKET_NOT_FOUND'));
		return false;
	}
	
	/**
	 * Add record to ost_ticket_event table and in case of automatic ticket
	 * assignment also a "note" to ost_ticket_thread table
	 * @param table object $ticket	- current ticket data
	 * @param string $action		- the action that creates an event
	 * 					'created'	- new ticket created
	 * 					'reopened'	- existing ticket reopened
	 * 					'assigned'	- new ticket assigned
	 * 					'reassigned'- ** a special action **, will be mapped
	 * 								to 'assigned', but taking reopened data as
	 * 								event timestamp
	 * @return boolean				- true if success, false if error. Error
	 * 								message saved in model
	 */
	private function addTicketEvent($ticket, $action = 'created')
	{
		if($action == 'assigned' || $action == 'created')
		{
			// event date = ticket creation date
			$date = $ticket->created;
		}
		elseif($action == 'reopened')
		{
			// event data = ticket reopen date
			$date = $ticket->reopened;
		}
		elseif($action == 'reassigned')
		{
			// map to assigned state to be saved in event record
			$action = 'assigned';
			// event data = ticket reopen date
			$date = $ticket->reopened;
		}
		else 
		{
			$this->setError('Unknown event');
			return false;
		}
		
		$db = $this->getDbo();
		try
		{
			// Add event record
			
			$event = array(
				'id'		=> $ticket->ticket_id,
				'staff_id'	=> (int)$ticket->staff_id,
				'team_id'	=> (int)$ticket->team_id,
				'dept_id'	=> (int)$ticket->dept_id,
				'topicid'	=> (int)$ticket->topic_id,
				'state'		=> $db->Quote($action),
				'staff'		=> $db->Quote('SYSTEM'),
				'annulled'	=> 0,
				'timestamp'	=> $db->Quote($date)
			);

			$db->setQuery('INSERT INTO #__ticket_event VALUES('. implode(',', $event) . ')');
			$db->execute();
			
			if($action == 'assigned' && (!empty($ticket->staff_id) || !empty($ticket->team_id)))
			{
				// Add assignment note to ticket thread
				
				// get assignee name
				if(!empty($ticket->staff_id))
				{
					// staff
					$db->setQuery('SELECT CONCAT_WS(" ", firstname, lastname) AS name FROM #__staff WHERE staff_id = ' . (int)$ticket->staff_id);
				}
				else
				{
					// team
					$db->setQuery('SELECT name FROM #__team WHERE team_id = ' . (int)$ticket->team_id);
				}
				$assignee = $db->loadResult();
				
				$note = array(
						'id'			=> 'NULL',				// auto-increment field
						'pid'			=> 0,
						'ticket_id'		=> $ticket->ticket_id,
						'staff_id'		=> 0,
						'user_id'		=> 0,
						'thread_type'	=> $db->Quote('N'),
						'poster'		=> $db->Quote(JText::_('COM_OSTICKY_TICKET_SYSTEM_ASSIGNED_POSTER')),
						'source'		=> $db->Quote(''),
						'title'			=> $db->Quote(JText::sprintf('COM_OSTICKY_TICKET_SYSTEM_ASSIGNED_TITLE', $assignee)),
						'body'			=> $db->Quote(JText::_('COM_OSTICKY_TICKET_SYSTEM_ASSIGNED_BODY')),
						'format'		=> $db->Quote('html'),
						'ip_address'	=> $db->Quote(''),
						'created'		=> $db->Quote($date),
						'updated'		=> $db->Quote($db->getNullDate())
				);
				
				$db->setQuery('INSERT INTO #__ticket_thread VALUES('. implode(',', $note) . ')');
				$db->execute();
			}
			
			return true;
		}
		
		catch(RuntimeException $e)
		{
			$this->setError($e->getMessage());
			return false;
		}
	}
	
	private function getRespondentToAssign($ticket_id = 0)
	{
		// find the lateset reply from currently available staff
		$db = $this->getDbo();
		$query = 'SELECT a.staff_id FROM #__ticket_thread AS a INNER JOIN #__staff AS b ON a.staff_id = b.staff_id WHERE a.ticket_id = '.
			$ticket_id.' AND a.thread_type = '.$db->Quote('R').' AND b.onvacation = 0 AND b.isactive = 1 ORDER BY a.created DESC LIMIT 0, 1';
		$db->setQuery($query);
		try
		{
			$staff_id = $db->loadResult();
		}
		catch(Exception $e)
		{
			$this->setError($e->getMessage());
			return false;
		}

		return $staff_id;
	}
	
	/*
	 * need complete refactoring
	 * there are 2 properties to look in:
	 * 		sequence_id
	 * 		number format
	 * 
	 * both properties can be defined globally (system setting) and on per-topic basis
	 * 
	 * number format is used both for random and sequential variants
	 * do not forget padding (left) char - a property of a sequence
	 * 
	 * TBC... $$$
	 */
	private function generateNewTicketID($topic_id = null, $attempts = 0, $min_digits = 1)
	{
		if(empty($topic_id))
		{
			$this->setError(JText::_('COM_OSTICKY_ERROR_INVALID_HELPTOPIC'));
			return false;
		}
		
		$db = $this->getDbo();
		$query = $db->getQuery(true);
		
		$query->select('t.number_format');
		$query->from('#__help_topic AS t');
		$query->select('s.id AS sequence_id, s.next, s.increment, s.padding');
		$query->join('LEFT OUTER', '#__sequence AS s ON t.sequence_id = s.id');
		$query->where('t.topic_id = ' . (int)$topic_id);
		
		$db->setQuery($query);
		try
		{
			$format = $db->loadObject();
		}
		catch(RuntimeException $e)
		{
			$this->setError($e->getMessage());
			return false;
		}
		
		if(empty($format))
		{
			$this->setError(JText::_('COM_OSTICKY_ERROR_INVALID_HELPTOPIC'));
			return false;
		}
		if(trim($format->number_format) == '')
		{
			$number_format = osTicky2Helper::getOstConfig('number_format');
		}
		else
		{
			$number_format = $format->number_format;
		}
		
		if(empty($format->sequence_id))
		{
			$sequence_id = osTicky2Helper::getOstConfig('sequence_id');
			if(!empty($sequence_id))
			{
				$query = $db->getQuery(true);
				
				$query->select('s.id, s.next, s.increment, s.padding');
				$query->from('#__sequence AS s');
				$query->where('s.id = ' . (int)$sequence_id);
				
				$db->setQuery($query);
				try
				{
					$format = $db->loadObject();
				}
				catch(RuntimeException $e)
				{
					$this->setError($e->getMessage());
					return false;
				}
				$sequence_id = $format->id;
			}
		}
		else
		{
			$sequence_id = $format->sequence_id;
		}
		
		$digits_part_start = strpos($number_format, '#');
		$digits_part_end = strrpos($number_format, '#') + 1;
		$digits = $digits_part_end - $digits_part_start;
		if($digits < $min_digits)
		{
			$digits = $min_digits;
		}

		if(empty($sequence_id))
		{
			// random
			mt_srand((double) microtime() * 1000000);
			
			$random = mt_rand(pow(10, $digits - 1), pow(10, $digits) - 1);
			 
			$id = substr($number_format, 0, $digits_part_start) . $random . substr($number_format, $digits_part_end);
			$query = 'SELECT ticket_id FROM #__ticket WHERE number = '.$db->Quote($id);
			$db->setQuery($query);
			try
			{
				$result = $db->loadResult();
			}
			catch(Exception $e)
			{
				$this->setError($e->getMessage());
				return false;
			}
			if($result)
			{
				$attempts++;
				if($attempts > 10)
				{
					$attempts = 0;
					$min_digits++;
				}
				return $this->generateNewTicketID($topic_id, $attempts, $min_digits);
			}
			return $id;
		}
		else
		{
			// sequential
			$next = str_pad((string)$format->next, $digits, $format->padding, STR_PAD_LEFT);
			$id = substr($number_format, 0, $digits_part_start) . $next . substr($number_format, $digits_part_end);
			try
			{
				// advance next in sequence
				$query = 'UPDATE #__sequence SET next = next + ' . $format->increment . ' WHERE id = ' . (int)$sequence_id;
				$db->setQuery($query);
				$db->execute();
			}
			catch(Exception $e)
			{
				$this->setError($e->getMessage());
				return false;
			}
			return $id;
		}
	}
}
