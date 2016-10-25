<?php
/**
 * @package osTicky2 (osTicket 1.9.4 Bridge) for Joomla 3
 * @version 2.1.7: ticket.php
 * @author Alex Polonski
 * @copyright (C) 2012 - 2014 - Alex Polonski
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 **/
// No direct access
defined('_JEXEC') or die;

class TicketHelper
{
	public static function getTicketByNumber($email, $number)
	{
		return self::getTicket($email, $number, null);
	}
	
	public static function getTicketById($ticket_id)
	{
		return self::getTicket(null, null, $ticket_id);
	}
	
	protected static $tickets = array();
	
	protected static function getTicket($email, $number, $ticket_id)
	{
		$param_type = !empty($number) ? 'number' : (!empty($ticket_id) ? 'ticket_id' : null);
		$param_value = $param_type == 'number' ? $number : $ticket_id;
		if(is_null($param_type))
		{
			return false;
		}
		
		$hash = $param_type.':'.$param_value;
		
		if(!isset(self::$tickets[$hash]))
		{
			// Create a new query object.
			$db	= osTicky2Helper::getDbo();
			$query = $db->getQuery(true);
			$query->select('a.*, a.number AS ticketID');
			$query->from('#__ticket AS a');
			
			$query->select('fev.value AS subject');
			$query->join('LEFT', '#__form_entry AS fe ON fe.object_id = a.ticket_id AND fe.object_type = '.$db->Quote('T').' AND fe.form_id = 2');
			$query->join('LEFT', '#__form_entry_values AS fev ON fev.entry_id = fe.id');
			$query->join('LEFT', '#__form_field AS ff ON fev.field_id = ff.id AND ff.name = '.$db->Quote('subject'));
			
			$query->select('b.dept_name');
			$query->innerJoin('#__department AS b ON b.dept_id = a.dept_id');
			$query->select('ht.topic AS help_topic');
			$query->innerJoin('#__help_topic AS ht ON a.topic_id = ht.topic_id');
			$query->select('ts.state AS status, ts.properties AS status_properties, ts.name AS status_title');
			$query->innerJoin('#__ticket_status AS ts ON ts.id = a.status_id');
				
			// join with message table to find sticky url (if any)
			$query->select('fm.id AS sticky2_id, fm.ticket_id AS fm_ticket_id, fm.message, fm.sticky2_url');
			$subquery = '
				(SELECT m.id, m.ticket_id, m.body as message,
				CASE WHEN LOCATE("{url}", m.body) > 0
				THEN SUBSTRING_INDEX(SUBSTRING_INDEX(m.body, "{url}", -1), "{/url}", 1) ELSE ""
				END AS sticky2_url
				FROM #__ticket_thread AS m
				ORDER BY m.created ASC)';
			$query->leftJoin($subquery.' AS fm ON fm.ticket_id = a.ticket_id');
			
			$query->where('a.'.$param_type.' = '.$db->Quote($param_value));
				
			$query->select('ue.address AS email, u.name');
			$query->join('LEFT', '#__user AS u ON u.id = a.user_id');
			$query->join('LEFT', '#__user_email AS ue ON ue.user_id = u.id');
			
			if(!empty($email))
			{
				$query->where('ue.address = '.$db->Quote($email));
			}
		
			$db->setQuery($query);
			try
			{
				$result = $db->loadObject();
			}
			catch(Exception $e)
			{
				return $e;
			}
			if(!$result)
			{
				return false;
			}
			
			$result->status_properties = json_decode($result->status_properties);
			
			$forms = self::getTicketFormData($result->ticket_id, $result->user_id);
			if($forms instanceof Exception)
			{
				return $forms;
			}
			
			$result->forms = $forms;
			
			$user = self::getUserData($result->user_id);
			if($user instanceof Exception)
			{
				return $user;
			}
				
			$result->user = $user;
			
			// clean sticky url
			$result->sticky2_url = preg_replace('#[\s]#', '', $result->sticky2_url);
				
			// validate sticky link (if any)
			if($validated = osTicky2Helper::validateStickyLinks(array($result)))
			{
				$result = array_shift($validated);
			}
			self::$tickets[$hash] = $result;
		}
		return self::$tickets[$hash];
	}
	
	protected static $form_data = array();
	
	public static function getTicketFormData($ticket_id, $user_id = 0)
	{
		$hash = serialize(array($ticket_id, $user_id));
		
		if(!isset(self::$form_data[$hash]))
		{
			$db	= osTicky2Helper::getDbo();
			$query = $db->getQuery(true);
			
			$query->select('fev.value, fev.value_id');
			$query->from('#__form_entry_values AS fev');
			// allow empty field names (if configured so in native osTicket), select field__<field_name> for those cases
			$query->select('ff.id AS field_id, ff.label, ff.`type`, ff.configuration, IF(ff.name <> '.$db->Quote('').', ff.name, CONCAT("field__", ff.id)) AS name')
				->innerJoin('#__form_field AS ff ON ff.id = fev.field_id AND ff.private=0');
			$query->join('LEFT', '#__form_entry AS fe ON fe.id = fev.entry_id');
			$query->select('f.title, f.id AS form_id')
				->innerJoin('#__form AS f on f.id = fe.form_id');
			
			$query->where('((fe.object_id = ' . (int)$ticket_id . ' AND fe.object_type = ' . $db->Quote('T') . ') OR (fe.object_id = ' . (int)$user_id . ' AND fe.object_type = ' . $db->Quote('U') . '))');
			
			$query->order('fe.sort ASC, f.id ASC, ff.sort ASC');
			
			$db->setQuery($query);
			
			try
			{
				$forms_data = $db->loadObjectList();
			}
			catch(Exception $e)
			{
				return $e;
			}
			
			$forms = array();
			foreach($forms_data as $entry)
			{
				if(!isset($forms[$entry->form_id]))
				{
					$forms[$entry->form_id] = array('title' => $entry->title, 'fields' => array());
				}
				$field = new stdClass();
				$field->label = $entry->label;
				$field->name = $entry->name;
				
				// "choices" field type needs special processing
				if($entry->type == 'choices')
				{
					$configuration = new JRegistry();
					$configuration->loadString($entry->configuration);
					$choices = $configuration->get('choices', '');
					
					$choices = preg_split('/\n|\r/', $choices, null, PREG_SPLIT_NO_EMPTY);
					foreach($choices as $i => $choice)
					{
						$choice_parts = explode(':', $choice);
						if(count($choice_parts) == 1)
						{
							// fallback to plain index if a choice is not well-formed
							$choice_parts = array($i, $choice_parts[0]);
						}
						if($entry->value == trim($choice_parts[0]))
						{
							$entry->value_id = $entry->value;
							$entry->value = trim($choice_parts[1]);
							break;
						}
					}
				}
				// "datetime" field type needs special processing
				if($entry->type == 'datetime')
				{
					if(!empty($entry->value))
					{
						$formats = osTicky2Helper::getOstConfig('date_format', 'datetime_format');
						$configuration = new JRegistry();
						$configuration->loadString($entry->configuration);
						$format = $configuration->get('time', false) ? $formats['datetime_format'] : $formats['date_format'];
						
						$date = new JDate($entry->value);
						
						$offset = JFactory::getUser()->getParam('timezone', JFactory::getConfig()->get('offset', 'UTC'));
						$date->setTimezone(new DateTimeZone($offset));
						/* $$$ for now just ignore the "gmt" setting, have to investigate native osTicket code,
						 * looks like it is buggy...
						if($configuration->get('gmt'))
						{
							$offset = JFactory::getUser()->getParam('timezone', JFactory::getConfig()->get('offset', 'UTC'));
							$date->setTimezone(new DateTimeZone($offset));
						}
						*/
						
						$entry->value = $date->format($format, true);
					}
					else
					{
						$entry->value = '';
					}
				}
				// "bool" type - checkboxes
				if($entry->type == 'bool')
				{
					// add "bool_value" property for escenarios when form data is used to set form
					// fields values (e.g. in osTicket profile plugin form)
					$field->bool_value = $entry->value;
					// plain display text value, to be shown with the ticket information
					$entry->value = !empty($entry->value) ? JText::_('JYES') : JText::_('JNO');
				}
				
				// "list" type

				if(strpos($entry->type, 'list-') === 0)
				{
					// this is a 1.9 compatibility workaround, generally there will never exist
					// too many entries on the ticket form, so for list type entries we
					// launch a MySQL query for each entry
					try
					{
						$query = $db->getQuery(true);
						$query->select('li.*')
							->from('#__list_items AS li')
							->where('li.id = ' . (int)$entry->value_id);
						$db->setQuery($query);
						
						$li = $db->loadObject();
					
						if(!empty($li->properties))
						{
							$properties = json_decode($li->properties, true);
							// we get list item properties as an array:
							// form_field_id => property_value
							if(!empty($properties))
							{
								$properties = array_filter($properties, function($v) {
									// sanitize properties: we allow '' or 0, but
									// filter out nulls
									return !is_null($v);
								});
								
								$ids = array_keys($properties);
								$query = $db->getQuery(true);
								$query->select('ff.id, ff.name, ff.`type`, ff.configuration')
									->from('#__form_field AS ff')
									->where('ff.id IN('.implode(',', $ids).')');
								$db->setQuery($query);
								$prop_fields = $db->loadObjectList('id');
								
								// a limited support for list item properties - only text and datetime properties
								$field->properties = array();
								foreach($prop_fields as $prop_id => $prop_field)
								{
									$configuration = new JRegistry();
									$configuration->loadString($prop_field->configuration);
									
									if($prop_field->type == 'text' || $prop_field->type == 'memo' || $prop_field->type == 'phone')
									{
										// text and phone fields - use value as is
										// add property to properties array
										$field->properties[$prop_field->name] = $properties[$prop_id];
									}
									elseif($prop_field->type == 'datetime' && !empty($properties[$prop_id]))
									{
										// date field
										$formats = osTicky2Helper::getOstConfig('date_format', 'datetime_format');
										$format = $configuration->get('time', false) ? $formats['datetime_format'] : $formats['date_format'];
					
										$date = new JDate($properties[$prop_id]);
					
										$offset = JFactory::getUser()->getParam('timezone', JFactory::getConfig()->get('offset', 'UTC'));
										$date->setTimezone(new DateTimeZone($offset));
										
										// add formatted date to properties array
										$field->properties[$prop_field->name] = $date->format($format);
									}
									elseif(strpos($prop_field->type, 'list-') === 0)
									{
										// list field - this is a special case: when a property
										// is defined for a list item, and the property type is
										// "list" we have to lookup the value in this "related"
										// list's items.
										// Get property value directly from list_items
										// table by id (defined in main list item's properties)
										$query = $db->getQuery(true);
										$query->select('pli.value');
										$query->from('#__list_items AS pli');
										$query->where('pli.id = ' . $properties[$prop_id]);
										$db->setQuery($query);
										
										$field->properties[$prop_field->name] = $db->loadResult();
									}
									elseif($prop_field->type == 'choices')
									{
										// choices field
										$choices = $configuration->get('choices', '');
					
										$choices = preg_split('/\n|\r/', $choices, null, PREG_SPLIT_NO_EMPTY);
										foreach($choices as $i => $choice)
										{
											$choice_parts = explode(':', $choice);
											if(count($choice_parts) == 1)
											{
												// fallback to plain index if a choice is not well-formed
												$choice_parts = array($i, $choice_parts[0]);
											}
											if($properties[$prop_id] == trim($choice_parts[0]))
											{
												$field->properties[$prop_field->name] = trim($choice_parts[1]);
												break;
											}
										}
									}
									elseif($prop_field->type == 'bool')
									{
										// checkbox field - use Yes/No as value
										$field->properties[$prop_field->name] = JText::_(empty($properties[$prop_id]) ? 'JNO' : 'JYES');
									}
								}
							}
						}
					}
					catch(RuntimeException $e)
					{
						return $e;
					}
				}
				
				// Support multiple choices and lists (read-only)
				/*
				 * $$$ this is a very limited support for multi-select lists. E.g. if a list
				 * is configured as multi-selection in osTicket, the option that will appear
				 * in osTicket Agent interface will not show correct value for tickets created
				 * in osTicky! We have to strongly advice not to use multi-selects in osTicket
				 */
				$configuration = new JRegistry();
				$configuration->loadString($entry->configuration);
				if($configuration->get('multiselect', false))
				{
					$tmp = json_decode($entry->value, true);
					$field->value = is_null($tmp) || !is_array($tmp) ? $entry->value : implode(', ', $tmp);
				}
				else
				{
					$field->value = $entry->value;
				}
				$field->value_id = $entry->value_id;
				
				// do not show properties with empty text (bool false fields have already
				// JNO text assigned)
				if(!empty($entry->value))
				{
					$forms[$entry->form_id]['fields'][] = $field;
				}
			}
			
			self::$form_data[$hash] = $forms;
		}
		
		return self::$form_data[$hash];
	}
	
	protected static $user_data = array();
	
	public static function getUserData($user_id)
	{
		if(!isset(self::$user_data[$user_id]))
		{
			$db	= osTicky2Helper::getDbo();
			$query = $db->getQuery(true);
				
			$query->select('fev.value');
			$query->from('#__form_entry_values AS fev');
			$query->select('ff.label, ff.`type`, ff.configuration')
				->innerJoin('#__form_field AS ff ON ff.id = fev.field_id AND ff.private=0');
			$query->join('LEFT', '#__form_entry AS fe ON fe.id = fev.entry_id');
			$query->select('f.title, f.id AS form_id')
				->innerJoin('#__form AS f on f.id = fe.form_id');
				
			$query->where('(fe.object_id = ' . $user_id . ' AND fe.object_type = ' . $db->Quote('U') . ')');
				
			$query->order('fe.sort ASC, f.id ASC, ff.sort ASC');
				
			$db->setQuery($query);
				
			try
			{
				$forms_data = $db->loadObjectList();
			}
			catch(Exception $e)
			{
				return $e;
			}
				
			$forms = array();
			foreach($forms_data as $entry)
			{
				if(!isset($forms[$entry->form_id]))
				{
					$forms[$entry->form_id] = array('title' => $entry->title, 'fields' => array());
				}
				$field = new stdClass();
				$field->label = $entry->label;
				
				// "choices" field type needs special processing
				if($entry->type == 'choices')
				{
					$configuration = new JRegistry();
					$configuration->loadString($entry->configuration);
					$choices = $configuration->get('choices', '');
						
					$choices = preg_split('/\n|\r/', $choices, null, PREG_SPLIT_NO_EMPTY);
					foreach($choices as $i => $choice)
					{
						$choice_parts = explode(':', $choice);
						if(count($choice_parts) == 1)
						{
							// fallback to plain index if a choice is not well-formed
							$choice_parts = array($i, $choice_parts[0]);
						}
						if($entry->value == trim($choice_parts[0]))
						{
							$entry->value = trim($choice_parts[1]);
							break;
						}
					}
				}
				$field->value = $entry->value;
				$forms[$entry->form_id]['fields'][] = $field;
			}
				
			self::$user_data[$user_id] = $forms;
		}
		
		return self::$user_data[$user_id];
	}
	
	protected static $company_data = null;
	
	public static function getCompanyData()
	{
		if(!isset(self::$company_data))
		{
			$db	= osTicky2Helper::getDbo();
			$query = $db->getQuery(true);
				
			$query->select('fev.value');
			$query->from('#__form_entry_values AS fev');
			// allow empty field names (if configured so in native osTicket), select field__<field_name> for those cases
			$query->select('IF(ff.name <> '.$db->Quote('').', ff.name, CONCAT("field__", ff.id)) AS name')
				->innerJoin('#__form_field AS ff ON ff.id = fev.field_id AND ff.private=0');
			$query->join('LEFT', '#__form_entry AS fe ON fe.id = fev.entry_id');
			
			$query->where('ff.form_id = 3');
			$query->where('fe.object_id IS NULL');
			$query->where('fe.object_type = ' . $db->Quote('C'));
				
			$query->order('fe.sort ASC, ff.sort ASC');
				
			$db->setQuery($query);
				
			try
			{
				$forms_data = $db->loadObjectList();
			}
			catch(Exception $e)
			{
				return $e;
			}

			self::$company_data = $forms_data;
		}
	
		return self::$company_data;
	}
	
	public static function getTicketEditForm($topic_id = 0, $form_id = 0, $options = array('contact_fields' => 'all'))
	{
		$db = osTicky2Helper::getDbo();
		$query = $db->getQuery(true);
		// allow empty field names (if configured so in native osTicket), select field__<field_name> for those cases
		$query->select('ff.form_id, ff.`type`, IF(ff.name <> '.$db->Quote('').', ff.name, CONCAT("field__", ff.id)) AS name, ff.label, ff.hint, ff.required, ff.configuration, ff.sort, f.title, f.instructions')
			->from('#__form_field AS ff')
			->innerJoin('#__form AS f ON f.id = ff.form_id')
			->where('ff.private=0');
		
		if(!empty($topic_id))
		{
			$query->join('LEFT', '#__help_topic AS ht ON ht.form_id = f.id')
				->where('ht.topic_id = '.(int)$topic_id);
		}
		else 
		{
			$query->where('f.id = '.(int)$form_id);
		}
		
		// manage contact fields
		if($options['contact_fields'] == 'core' && $form_id == 1)
		{
			$query->where('(ff.name = '.$db->Quote('name').' OR ff.name = '.$db->Quote('email').')');
		}
		elseif($options['contact_fields'] == 'extra' && $form_id == 1)
		{
			$query->where('(ff.name <> '.$db->Quote('name').' AND ff.name <> '.$db->Quote('email').')');
		}
			
		$query->order('ff.form_id ASC, ff.sort ASC');
		
		$db->setQuery($query);
		try
		{
			$fields = $db->loadObjectList();
		}
		catch(RuntimeException $e)
		{
			throw $e;
		}
		
		if(count($fields) == 0)
		{
			// this is not an error, just no fields to show
			return '';
		}
		
		// joomla types conversion rules
		$enableHtml = osTicky2Helper::getOstConfig('enable_html_thread');
		$joomla_types = array(
				'text'		=> array('type' => 'text'),
				'memo_text'	=> array('type' => 'textarea'),
				'memo_html'	=> array('type' => 'editor'),
				'phone'		=> array('type' => 'text', 'validation' => 'telext'),
				'thread'	=> array('type' => $enableHtml ? 'editor' : 'textarea'),
				'datetime'	=> array('type' => 'datetimepicker'), // store format - timestamp 
				'choices'	=> array('type' => 'list'),
				'list'		=> array('type' => 'list'),
				'bool'		=> array('type' => 'radio'),
				'priority'	=> array('type' => 'priority'),
				'break'		=> array('type' => 'spacer')
		);
		
		foreach($fields as $i => &$field)
		{
			$configuration = new JRegistry();
			$configuration->loadString($field->configuration);
			$field->configuration = $configuration;
			if(strpos($field->type, 'list-') === 0)
			{
				// this is a list field. Get list id from field type
				$list_id	= (int)substr($field->type, strlen('list-'));
				
				$field->list_id = $list_id;
				
				try
				{
					// get list sort setting
					$query = $db->getQuery(true);
					$query->select('l.sort_mode, l.type')
						->from('#__list AS l')
						->where('l.id = '.$list_id);
					$db->setQuery($query);
					$list = $db->loadObject();
					
					if($list->type == 'ticket-status')
					{
						// no ticket status list in frontend!
						unset($fields[$i]);
						continue;
					}
					
					$sort = $list->sort_mode;
					if($sort == 'Alpha')
					{
						// Alphabetical
						$order = 'li.value ASC';
					}
					elseif($sort == '-Alpha')
					{
						// Alphabetical reverse
						$order = 'li.value DESC';
					}
					else
					{
						// 'SortCol' - manual sort
						$order = 'li.sort ASC';
					}
					
					// get list items sorted according to the list configuration
					$query = $db->getQuery(true);
					
					$query->select('li.*')							// ***1.9***
						->from('#__list_items AS li')
						->where('li.list_id = '.(int)$list_id)
						->order($order);
					$db->setQuery($query);
					$field->list_items = $db->loadObjectList('id');
					
					// filter out disabled list items				// ***1.9***
					foreach($field->list_items as $id => $li)
					{
						if(isset($li->status) && $li->status == 0)
						{
							unset($field->list_items[$id]);
						}
					}
				}
				catch(RuntimeException $e)
				{
					throw $e;
				}

				$field->type = 'list';
			}
			if($field->type == 'choices')
			{
				$field->list_items = array();
				$choices = $field->configuration->get('choices', '');
				
				$choices = preg_split('/\n|\r/', $choices, null, PREG_SPLIT_NO_EMPTY);
				foreach($choices as $i => $choice)
				{
					$choice_parts = explode(':', $choice);
					if(count($choice_parts) == 1)
					{
						// fallback to plain index if a choice is not well-formed
						$choice_parts = array($i, $choice_parts[0]);
					}
					$item = new stdClass();
					$item->value = trim($choice_parts[1]);
					$field->list_items[trim($choice_parts[0])] = $item; // store format - value: index, value_id: NULL
				}
				
				$field->type = 'list';
			}
			
			if($field->type == 'datetime')
			{
				$formats = osTicky2Helper::getOstConfig('date_format', 'datetime_format');
				$format = $configuration->get('time', false) ? $formats['datetime_format'] : $formats['date_format'];
				
				$field->show_time = $field->configuration->get('time', false);
				
				$min = $field->configuration->get('min', '');
				$max = $field->configuration->get('max', '');
				
				$field->min_date = $min ? JDate::getInstance($min)->toSql() : '';
				$field->max_date = $max ? JDate::getInstance($max)->toSql() : '';
				$field->allow_future = $field->configuration->get('future', true);
				
				// make a very approximate guess on date format settings. They will define how
				// datetimepicker field looks. At the moment we support only 3 systems:
				// year-month-day, day-month-year and month/day/year. We also presume that the
				// month/day/year will likely display time in 12-hours format.
				
				// field display in the ticket details view is not affected - only the calendar
				// widget is
				
				// get the order of date parts
				$format_compressed = preg_replace('/[^Ydm]/', '', $format);
				
				// propose datetimepicker format
				if($format_compressed == 'dmY')
				{
					$field->format = $field->show_time ? 'dd-mm-yyyy hh:ii' : 'dd-mm-yyyy';
				}
				elseif($format_compressed == 'mdY')
				{
					$field->format = $field->show_time ? 'mm/dd/yyyy H:ii p' : 'mm/dd/yyyy';
				}
				else
				{
					// fallback to standard sql format
					$field->format = $field->show_time ? 'yyyy-mm-dd hh:ii' : 'yyyy-mm-dd';
				}
			}
			
			// set Joomla field type and validation
			if($field->type == 'memo')
			{
				$html = $field->configuration->get('html', true);
				$field->type = $html ? 'memo_html' : 'memo_text';
			}
			if(isset($joomla_types[$field->type]))
			{
				$field->validation = !empty($joomla_types[$field->type]['validation']) ? $joomla_types[$field->type]['validation'] : $field->configuration->get('validator', null);
				if($field->validation == 'regex')
				{
					// get regex from configuration for custom validation
					$field->regex = $field->configuration->get('regex', '');
				}
				$field->type = $joomla_types[$field->type]['type'];
			}
			else
			{
				// do not display unknown fields
				unset($fields[$i]);
			}
		}
		
		$fieldset = new stdClass();
		$common = reset($fields);
		$fieldset->label = $common->title;
		
		if($form_id == 1)
		{
			// contact info
			$fieldset->name = 'contact';
		}
		elseif($form_id == 2)
		{
			// ticket details
			$fieldset->name = 'ticket';
		}
		else
		{
			$fieldset->name = 'custom';
			$form_id = $common->form_id;
		}
		
		$fieldset->description = $common->instructions;
		
		$xml = self::getFieldsetXml($form_id, $fields, $fieldset, $options);
		return $xml;
	}
	
	protected static function getFieldsetXml($form_id, $fields, $fieldset, $options)
	{
		$params = JComponentHelper::getParams('com_osticky2');
		
		$xml = '
			<fields name="form_'.$form_id.'">
				<fieldset 
					addrulepath="/components/com_osticky2/models/rules"
					addfieldpath="/components/com_osticky2/models/fields"
					name="'.$fieldset->name.'"
					label="'.htmlspecialchars(empty($options['contact_fieldset_label']) ? $fieldset->label : $options['contact_fieldset_label']).'"
					description="'.htmlspecialchars(empty($options['contact_fieldset_desc']) ? $fieldset->description : $options['contact_fieldset_desc']).'">
		';
		
		foreach($fields as $field)
		{
			$size = $field->type == 'text' ? $field->configuration->get('size', 0) : 0;
			$max_length = $field->type == 'text' ? $field->configuration->get('length', 0) : 0;
			$validation = $field->validation ? $field->validation : 'nonempty';
			// get validation settings
			if($validation == 'phone')
			{
				// osTicket 'phone' validation is implemented as 'telext'
				$validation = 'telext';
			}
			$validator_error = $field->configuration->get('validator-error', '');
			
			// construct class attribute
			if($field->type == 'radio')
			{
				// for 'radio' - always use this class
				$class = 'class="radio btn-group"';
			}
			elseif($field->type == 'spacer')
			{
				$class = 'class="spacer"';
			}
			elseif($field->type == 'text' && $size)
			{
				// for 'text', look in field configuration for 'size' property
				$class = $field->required ? 'class="required inputbox %s"' : 'class="inputbox %s"';
				$sizes = array(
					50 => 'span',
					40 => 'input-xlarge',
					25 => 'input-large',
					15 => 'input-medium',
					 5 => 'input-small',
					 0 => 'input-mini'
				);
				foreach($sizes as $sz => $cls)
				{
					if($size >= $sz)
					{
						$class = sprintf($class, $cls);
						break;
					}
				}
			}
			elseif($field->type != 'editor') 
			{
				// 'inputbox' for all other elements except 'editor'
				$class = $field->required ? 'class="required inputbox"' : 'class="inputbox"';
			}
			else
			{
				// default
				$class = $field->required ? 'class="required"' : '';
			}
			
			// construct maxlength attribute if applies
			if($max_length)
			{
				$max_length = '
					maxlength="'.$max_length.'"';
			}
			else
			{
				$max_length = '';
			}

			// placeholder for text fields (*** for HTML enabled texts we do not support placeholders)
			if($field->type == 'text' || $field->type == 'textarea')
			{
				$placeholder = $field->configuration->get('placeholder', '');
			}
			
			if($field->type == 'spacer')
			{
				// for spacer fields (section break in osTicket) we add
				// a "hr" spacer before
				$xml .= '
				<field name="'.$field->name.'_hr"
					type="spacer" hr="true" />';
			}
			
			$xml .= '
				<field name="'.$field->name.'"
					type="'.$field->type.'"
					label="'.htmlspecialchars($field->label).'"
					description="'.htmlspecialchars($field->hint ? $field->hint : '').'"
					required="'.($field->required ? 'true' : 'false').'"
					validate="'.$validation.'"
					'.(!empty($validator_error) ? 'message="'.htmlspecialchars($validator_error).'"'	: '').'
					'.(!empty($placeholder) 	? 'hint="'.htmlspecialchars($placeholder).'"'			: '').'
					'.$class.$max_length;
			
			if($field->type == 'editor')
			{
				// get preferred editor from the component's options, fallback
				// to global editor option
				$editor = $params->get('editor', '');
				if(empty($editor))
				{
					$editor = JFactory::getConfig()->get('editor', 'none');
				}
				$xml .= '
					editor="'.$editor.'|none"
					hide="readmore,pagebreak,image,article"
					buttons="no"
					filter="JComponentHelper::filterText"
				';
			}
			if($field->type == 'textarea')
			{
				$xml .= '
					rows="10"
					cols="60"
					filter="safehtml"
				';
			}
			if($field->type == 'datetimepicker')
			{
				$xml .= '
					show_time="'.($field->show_time ? 'true' : 'false').'"
					min_date="'.$field->min_date.'"
					max_date="'.$field->max_date.'"
					allow_future="'.($field->allow_future ? 'true' : 'false').'"
					format="'.$field->format.'"
				';
			}
			if(!empty($field->regex))
			{
				// add regex attribute for custom validation
				$xml .= '
					regex="' . htmlspecialchars($field->regex) .'"
				';
			}
			if($field->type != 'list' && $field->type != 'radio')
			{
				$xml .= '/>';
			}
			else 
			{
				if($field->type == 'list')
				{
					$prompt = htmlspecialchars($field->configuration->get('prompt', ''));
					if(empty($prompt))
					{
						$prompt = 'COM_OSTICKY_SELECT_PROMPT';
					}
					$is_multiple = $field->configuration->get('multiselect', false);
					$default = $field->configuration->get('default');
					// lists store default as array[0] (no default) or stdClass (key, value)
					// choices always store default as plain choice key (int)
					// *** anyway, only 1 value can be set as default even for multiselect
					// controls (this is osTicket 1.9.5 confirmed behavior)
					if(!empty($default))
					{
						if(is_object($default))
						{
							$defaults = array_keys((array)$default);
							$default = !empty($defaults) ? reset($defaults) : '';
						}
					}
					else
					{
						$default = '';
					}
					
					if($is_multiple)
					{
						$xml .= '
							multiple="true"';
						// Add prompt for multiple chosen
						JHtml::_('formbehavior.chosen', '#jform_ticket_form_'.$field->form_id.'_'.$field->name, null, array('placeholder_text_multiple' => $field->configuration->get('prompt', '')));
					}
					$xml .= '
						default="' . $default . '"';
					$xml .= '>';
					
					if(!$is_multiple)
					{
						// empty option with prompt for single selection - standard Joomla way
						$xml .= '
							<option value="">'.htmlspecialchars($prompt).'</option>';
					}
					foreach($field->list_items as $id => $list_item)
					{
						$xml .= '
							<option value="'.$id.'">'.htmlspecialchars($list_item->value).'</option>';
					}	
				}
				else
				{
					$xml .= '
						default="0"'; // checkbox cannot be checked by default?
					$xml .= '>';
					$xml .= '
							<option value="0">JNO</option>
							<option value="1">JYES</option>';
				}
				$xml .= '
					</field>';
			}
		}
		
		$xml .= '
				</fieldset>
			</fields>
		';
		return $xml;
	}
	
	/*
	 * Now with multiple selection lists and choices support
	 */
	public static function getEntryValue($form_id, $field_name, $value, $jtype = '')
	{
		$db = osTicky2Helper::getDbo();
		$query = $db->getQuery(true);
	
		if(!is_array($value) || empty($value))
		{
			// allow empty field names (if configured so in native osTicket), select field__<field_name> for those cases
			$query->select('ff.id AS field_id, ff.`type` AS field_type, IF(ff.name <> '.$db->Quote('').', ff.name, CONCAT("field__", ff.id)) AS field_name, ff.form_id, ff.configuration AS field_configuration')
				->from('#__form_field AS ff')
				->select('IF(LOCATE("list-", ff.`type`) = 1, li.id, NULL) AS list_item_id, li.value AS list_item_value')
				->join('LEFT OUTER', '#__list AS l ON l.id = SUBSTRING(ff.`type`, 6)')
				->join('LEFT', '#__list_items AS li ON li.list_id = l.id AND li.id = '.$db->Quote($value))
				->where('ff.private=0')
				->where('ff.form_id = '.(int)$form_id)
				// for auto field names (field__<field_id>) we add OR clause here
				->where('(ff.name = '.$db->Quote($field_name).' OR CONCAT('.$db->Quote('field__').', ff.id) = '.$db->Quote($field_name).')');
		
			$db->setQuery($query);
			try
			{
				$entry_value = $db->loadObject();
			}
			catch(RuntimeException $e)
			{
				return $e;
			}
		
			if(empty($entry_value))
			{
				return new Exception(JText::_('COM_OSTICKY_ERROR_INVALID_FORM_FIELD'));
			}
		
			if($entry_value->field_type == 'choices')
			{
				$entry_value->value = $value;
				$entry_value->value_id = null;
			}
			elseif(!empty($entry_value->list_item_id))
			{
				$entry_value->value = $entry_value->list_item_value;
				$entry_value->value_id = $entry_value->list_item_id;
			}
			elseif($entry_value->field_type == 'datetime')
			{
				if(!empty($value))
				{
					$date = new JDate($value);
					
					$offset = JFactory::getUser()->getParam('timezone', JFactory::getConfig()->get('offset', 'UTC'));
					$date->setTimezone(new DateTimeZone($offset));
					
					$offset_sec = $date->getOffset();
					$entry_value->value = $date->getTimestamp() - $offset_sec;
				}
				else
				{
					$entry_value->value = null;
				}
			}
			else
			{
				$entry_value->value = $value;
				$entry_value->value_id = null;
			}
		}
		else
		{
			// multiple selection value, we have already checked that the array is not empty
			$values = $db->Quote($value);
			
			$query->select('ff.id AS field_id, ff.`type` AS field_type, IF(ff.name <> '.$db->Quote('').', ff.name, CONCAT("field__", ff.id)) AS field_name, ff.form_id, ff.configuration AS field_configuration')
				->from('#__form_field AS ff')
				->select('IF(LOCATE("list-", ff.`type`) = 1, li.id, NULL) AS list_item_id, li.value AS list_item_value')
				->join('LEFT OUTER', '#__list AS l ON l.id = SUBSTRING(ff.`type`, 6)')
				->join('LEFT', '#__list_items AS li ON li.list_id = l.id AND li.id IN ('.implode(',', $values) . ')')
				->where('ff.private=0')
				->where('ff.form_id = '.(int)$form_id)
				// for auto field names (field__<field_id>) we add OR clause here
				->where('(ff.name = '.$db->Quote($field_name).' OR CONCAT('.$db->Quote('field__').', ff.id) = '.$db->Quote($field_name).')');
			
			$db->setQuery($query);
			try
			{
				$entry_values = $db->loadObjectList();
			}
			catch(RuntimeException $e)
			{
				return $e;
			}
			if(empty($entry_values))
			{
				return new Exception(JText::_('COM_OSTICKY_ERROR_INVALID_FORM_FIELD'));
			}
			
			$entry_value = reset($entry_values);
			$value_json = array();
			
			if($entry_value->field_type == 'choices')
			{
				// choices - really tricky. We have to extract options from configuration
				// and add currently selected ones (both indices and values) to the array,
				// so that is can be json encoded
				$configuration = new JRegistry();
				$configuration->loadString($entry_value->field_configuration);
				$choices = $configuration->get('choices', '');
					
				$choices = preg_split('/\n|\r/', $choices, null, PREG_SPLIT_NO_EMPTY);
				$values = array();
				foreach($choices as $i => $choice)
				{
					$choice_parts = explode(':', $choice);
					if(count($choice_parts) == 1)
					{
						// fallback to plain index if a choice is not well-formed
						$choice_parts = array($i, $choice_parts[0]);
					}
					if(in_array(intval($choice_parts[0]), $value))
					{
						$value_json[intval($choice_parts[0])] = trim($choice_parts[1]);
					}
				}
			}
			else
			{
				// lists - easy case
				foreach($entry_values as $v)
				{
					$value_json[intval($v->list_item_id)] = $v->list_item_value;
				}
			}
			
			// osTicket format for multiple selections: the value is json encoded 
			// array, indexed by list item ids. Value ID must be empty.
			$entry_value->value = json_encode($value_json);
			$entry_value->value_id = null;
		}
	
		return $entry_value;
	}
}
