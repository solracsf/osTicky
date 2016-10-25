<?php
/**
 * @package osTicky2 (osTicket 1.9.4 Bridge) for Joomla 3
 * @version 2.1: datetimepicker.php
 * @author Alex Polonski
 * @copyright (C) 2012 - 2014 - Alex Polonski
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 **/
defined('_JEXEC') or die;

JFormHelper::loadFieldClass('calendar');
class JFormFieldDateTimePicker extends JFormFieldCalendar
{
	public $type = 'datetimepicker';
	
	protected function getInput()
	{
		$show_time = $this->element['show_time'] ? ((string)$this->element['show_time'] == 'true' ? 1 : 0) : 0;
		$min_date = $this->element['min_date'] ? (string)$this->element['min_date'] : '';
		$max_date = $this->element['max_date'] ? (string)$this->element['max_date'] : '';
		$allow_future = $this->element['allow_future'] ? ((string)$this->element['allow_future'] == 'true' ? 1 : 0) : 0;
		
		// format following the datetimepicker pattern is set as the element's attribute
		$format = $this->element['format'] ? (string)$this->element['format'] : 'D dd-mm-yyyy';
		
		// apply "allow future dates" rule (if any) *** doesn't work in native osTicket
		$date = new JDate();
		$offset = JFactory::getUser()->getParam('timezone', JFactory::getConfig()->get('offset', 'UTC'));
		$date->setTimezone(new DateTimeZone($offset));
		$now = $date->toSql(true);
		if(!$allow_future && (empty($max_date) || $max_date > $now))
		{
			$max_date = $now;
		}
		
		// ensure that the date(time) selected fits into the control
		$width = $show_time ? '12em' : '9em';
		
		$html = array();
		
		$html[] = '<div class="input-append date datetimepicker" id="'.$this->id.'_picker">';
		$html[] = 	'<input class="text-center" style="width:'.$width.';" size="16" id="'.$this->id.'" name="'.$this->name.'" type="text" placeholder="' . JText::_('COM_OSTICKY_DATETIMEPICKER_PROMPT') . '" value="' . $this->value . '" readonly />';
		$html[] = 	'<span class="add-on"><i class="icon-calendar"></i></span>';
		$html[] = '</div>';
		
		$doc = JFactory::getDocument();
		
		$doc->addScript(JURI::root(true) . '/components/com_osticky2/models/fields/datetimepicker/bootstrap-datetimepicker.js');
		
		$script = '
			window.addEvent("domready", function() {
					jQuery("#'.$this->id.'_picker").datetimepicker({
						format: "'.$format.'",
						autoclose: "true",
						todayBtn: true,
						startDate: "'.$min_date.'",
						endDate: "'.$max_date.'",
						minuteStep: 15,
						weekStart: 1,
						minView: "'.($show_time ? 'hour' : 'month').'",
						maxView: "decade",
						todayHighlight: true
					});
			});
		';
		$doc->addScriptDeclaration($script);
		
		$doc->addStyleSheet(JURI::root(true) . '/components/com_osticky2/models/fields/datetimepicker/datetimepicker.css');
		
		return implode("\n", $html);
	}
}
