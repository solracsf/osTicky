<?php
/**
 * @package osTicky2 (osTicket 1.9.4 Bridge) for Joomla 3
 * @version 2.1: telext.php
 * @author Alex Polonski
 * @copyright (C) 2012 - 2014 - Alex Polonski
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
**/
defined('JPATH_PLATFORM') or die;

class JFormRuleTelExt extends JFormRule
{
	public function test(&$element, $value, $group = null, &$input = null, &$form = null)
	{
		// If the field is empty and not required, the field is valid.
		$required = ((string) $element['required'] == 'true' || (string) $element['required'] == 'required');
		if (!$required && empty($value))
		{
			return true;
		}
		
		// up to 3 digits extension can be appended to the number, separated with x|X
		$cleanvalue = preg_replace('/[+. \-(\)]/', '', $value);
		$regex = '/^[0-9]{7,15}?(X[0-9]{1,3})?$/';
		if (preg_match($regex, $cleanvalue) == true)
		{
			return true;
		}
		else
		{
			return false;
		}
	}
}
