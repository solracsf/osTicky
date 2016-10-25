<?php
/**
 * @package osTicky2 (osTicket Bridge) for Joomla 2.5
 * @version 1.0: nonempty.php
 * @author Alex Polonski
 * @copyright (C) 2012 - Alex Polonski
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
**/
defined('JPATH_PLATFORM') or die;

class JFormRuleNonEmpty extends JFormRule
{
	public function test(&$element, $value, $group = null, &$input = null, &$form = null)
	{
		// If the field is empty and not required, the field is valid.
		$required = ((string) $element['required'] == 'true' || (string) $element['required'] == 'required');
		if($required && trim($value) == '')
		{
			return false;
		}
		return true;
	}
}
