<?php
/**
 * @package osTicky2 (osTicket 1.9.4 Bridge) for Joomla 3
 * @version 2.1.9: regex.php
 * @author Alex Polonski
 * @copyright (C) 2012 - 2014 - Alex Polonski
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
**/
defined('JPATH_PLATFORM') or die;

class JFormRuleRegex extends JFormRule
{
	public function test(SimpleXMLElement $element, $value, $group = null, JRegistry $input = null, JForm $form = null)
	{
		$regex = $element['regex'] ? htmlspecialchars_decode((string) $element['regex']) : '';
		if (empty($regex) || empty($value))
		{
			return true;
		}
		
		if (preg_match($regex, $value) == true)
		{
			return true;
		}
		else
		{
			return false;
		}
	}
}
