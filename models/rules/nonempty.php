<?php
/**
 * @package osTicky2 (osTicket 1.9.4 Bridge) for Joomla 3
 * @version 2.1: nonempty.php
 * @author Alex Polonski
 * @copyright (C) 2012 - 2014 - Alex Polonski
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
**/
defined('JPATH_PLATFORM') or die;

class JFormRuleNonEmpty extends JFormRule
{
	public function test(SimpleXMLElement $element, $value, $group = null, JRegistry $input = null, JForm $form = null)
	{
		// If the field is empty and not required, the field is valid.
		$required = ((string) $element['required'] == 'true' || (string) $element['required'] == 'required');
		if(!$required)
		{
			return true;
		}
		if(!is_array($value))
		{
			return (trim($value) != '');
		}
		else
		{
			if(count($value) == 0)
			{
				return false;
			}
		}
		return true;
	}
}
