<?php
/**
 * @package osTicky2 (osTicket 1.9.4 Bridge) for Joomla 3 - 3.0
 * @subpackage plg_authentication_osticket2
 * @version 2.1: osticket2.php
 * @author Alex Polonski
 * @copyright (C) 2012 - 2014 - Alex Polonski
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 **/
defined('JPATH_BASE') or die;

include_once(JPATH_ADMINISTRATOR . '/components/com_osticky2/helpers/ticket.php');
include_once(JPATH_ADMINISTRATOR . '/components/com_osticky2/helpers/osticky2.php');

class PlgUserOsTicketProfile2 extends JPlugin
{
	/**
	 * Load the language file on instantiation.
	 *
	 * @var    boolean
	 * @since  3.1
	 */
	protected $autoloadLanguage = true;

	/**
	 * Constructor
	 *
	 * @param   object  $subject  The object to observe
	 * @param   array   $config   An array that holds the plugin configuration
	 *
	 * @since   1.5 pic_scope
	 */
	public function __construct(& $subject, $config)
	{
		parent::__construct($subject, $config);
	}

	/**
	 * @param   string     $context  The context for the data
	 * @param   JUser      $data     The user object
	 *
	 * @return  boolean
	 *
	 * @since   1.6
	 */
	public function onContentPrepareData($context, $data)
	{
		// Check we are manipulating a valid form.
		if (!in_array($context, array('com_users.profile', 'com_users.registration')))
		{
			return true;
		}
		if(!file_exists(JPATH_ADMINISTRATOR . '/components/com_osticky2/helpers/osticky2.php'))
		{
			return true;
		}
		$ost_version = osTicky2Helper::getOstConfig('ost_version');
		if(empty($ost_version))
		{
			return true;
		}

		if (is_object($data))
		{
			$userId = isset($data->id) ? $data->id : 0;
			
			if ($userId > 0)
			{
				$ost_user_id = osTicky2Helper::getOsTicketUserFromEmail($data->get('email'));
				$user_data = TicketHelper::getTicketFormData(null, $ost_user_id);
				
				if(empty($user_data))
				{
					return true;
				}
				
				$fields = $user_data[1]['fields'];
				$data->set('form_1', array());
				foreach($fields as $field)
				{
					// set form fields values. For text fields use $field->value, for list and choices fields use $field->value_id, for checkboxes use
					// the special $field->bool_value (only set for this type of fields) *** this is a workaround: $field->value will contain JNO/JYES
					// at this point
					$data->form_1[$field->name] = !empty($field->value_id) 
						? $field->value_id				// value_id always wins (lists and choices)
						: (isset($field->bool_value) 
								? $field->bool_value	// checkbox (radio) value
								: $field->value);		// plain text value
				}
			}
		}

		return true;
	}

	/**
	 * @param   JForm    $form    The form to be altered.
	 * @param   array    $data    The associated data for the form.
	 *
	 * @return  boolean
	 * @since   1.6
	 */
	public function onContentPrepareForm($form, $data)
	{
		if (!($form instanceof JForm))
		{
			$this->_subject->setError('JERROR_NOT_A_FORM');
			return false;
		}

		// Check we are manipulating a valid form.
		$name = $form->getName();
		
		// always show in edit profile view
		$forms = array('com_users.profile');
		if($this->params->get('show_on_registration', 0))
		{
			// show in registration if set in options
			$forms[] = 'com_users.registration';
		}
		if (!in_array($name, $forms))
		{
			return true;
		}
		if(!file_exists(JPATH_ADMINISTRATOR . '/components/com_osticky2/helpers/osticky2.php'))
		{
			// component not installed
			return true;
		}
		$ost_version = osTicky2Helper::getOstConfig('ost_version');
		if(empty($ost_version))
		{
			return true;
		}
		
		// add fields
		$form->addRulePath(dirname(__FILE__).'/rules');
		// add language file
		JFactory::getLanguage()->load('com_osticky2', JPATH_SITE . '/components/com_osticky2');
		
		$xml = TicketHelper::getTicketEditForm(null, 1, array(
				'contact_fields' => 'extra',
				'contact_fieldset_label' => JText::_('PLG_USER_OSTICKETPROFILE2_FIELDSET_LABEL'),
				'contact_fieldset_desc' => JText::_('PLG_USER_OSTICKETPROFILE2_FIELDSET_DESC'),
			)
		);
		if(!empty($xml))
		{
			if(!$form->load('<form>'.$xml.'</form>', true))
			{
				$this->_subject->setError('JERROR_NOT_A_FORM');
				return false;
			}
		}
		return true;
	}

	public function onUserAfterSave($data, $isNew, $result, $error)
	{
		if(!file_exists(JPATH_ADMINISTRATOR . '/components/com_osticky2/helpers/osticky2.php'))
		{
			// component not installed
			return true;
		}
		$ost_version = osTicky2Helper::getOstConfig('ost_version');
		if(empty($ost_version))
		{
			return true;
		}
		
		$userId = JArrayHelper::getValue($data, 'id', 0, 'int');

		if ($userId && $result && isset($data['form_1']) && (count($data['form_1'])))
		{
			/*
			 * $$$ Check what happens if the user changes her email!!!???
			 */
			$email = $data['email'];
			$ost_user_id = osTicky2Helper::getOsTicketUserFromEmail($email);
			$is_new_ost_user = empty($ost_user_id);
			
			$db = osTicky2Helper::getDbo();
			$db->transactionStart(true);
			
			$now = osTicky2Helper::getDbTime();
			try 
			{
				if(!$is_new_ost_user)
				{
					// existing user - update
					$db->setQuery('UPDATE #__user_email SET address = ' . $db->Quote($email) . ' WHERE user_id = ' . (int)$ost_user_id);
					$db->execute();
					$db->setQuery('UPDATE #__user SET name = ' . $db->Quote($data['name']) . ', updated = ' . $db->Quote($now) . ' WHERE id = ' . (int)$ost_user_id);
					$db->execute();
					
					$db->setQuery('SELECT id FROM #__form_entry WHERE object_id = ' . (int)$ost_user_id . ' AND object_type = ' . $db->Quote('U') . ' AND form_id = 1');
					$entry_id = (int)$db->loadResult();
				}
				else
				{
					if($ost_version == '18')
					{
						// new user - insert version 1.8
						$db->setQuery('INSERT INTO #__user VALUES(NULL, 0, ' . $db->Quote($data['name']) . ', ' . $db->Quote($now) . ', ' . $db->Quote($now) . ')');
						$db->execute();
						$ost_user_id = $db->insertid();
					}
					else 
					{
						// new user - insert version 1.9
						$db->setQuery('INSERT INTO #__user VALUES(NULL, 0, 0, 0, ' . $db->Quote($data['name']) . ', ' . $db->Quote($now) . ', ' . $db->Quote($now) . ')');
						$db->execute();
						$ost_user_id = $db->insertid();
					}
					
					$db->setQuery('INSERT INTO #__user_email VALUES(NULL,' . $ost_user_id . ', ' . $db->Quote($email) . ')');
					$db->execute();
					$ost_email_id = $db->insertid();
					
					$db->setQuery('UPDATE #__user SET default_email_id = ' . (int)$ost_email_id . ' WHERE id = ' . (int)$ost_user_id);
					$db->execute();
					
					// prepare entry for new user's values
					$db->setQuery('INSERT INTO #__form_entry VALUES(NULL, 1, ' . $ost_user_id . ', ' . $db->Quote('U') . ', 1, ' . $db->Quote($now) . ', ' . $db->Quote($now) . ')');
					$db->execute();
					$entry_id = $db->insertid();
				}
			}
			catch(RuntimeException $e)
			{
				$db->transactionRollback(true);
				throw($e);
			}
			
			foreach($data['form_1'] as $name => $value)
			{
				$entry = TicketHelper::getEntryValue(1, $name, $value);
				if($entry instanceof Exception)
				{
					$db->transactionRollback(true);
					throw($entry);
				}
				
				// convert empty values to NULL (native osTicket behavior), leave explicit "0" as is for bool values
				$entry_value = !empty($entry->value) || $entry->value === 0 || trim($entry->value) === '0' ? $db->Quote($entry->value) : 'NULL';
				// empty value_id must be always NULL
				$entry_value_id = !empty($entry->value_id) ? $db->Quote($entry->value_id) : 'NULL';
				
				try 
				{
					// insert/update entry values - if a contact from field was added after the profile was created we make sure that we add
					// a record to form_entry_values table, as a simple UPDATE will have no effect 
					$db->setQuery('INSERT INTO #__form_entry_values VALUES(' . $entry_id . ', ' . $entry->field_id . ', ' . $entry_value . ', ' . $entry_value_id . ') ON DUPLICATE KEY UPDATE value = ' . $entry_value . ', value_id = ' . $entry_value_id);
					$db->execute();
				}
				catch(RuntimeException $e)
				{
					$db->transactionRollback(true);
					throw($e);
				}
			}
			$db->transactionCommit(true);
		}

		return true;
	}
}
