<?php
/**
 * @package osTicky2 (osTicket 1.9.4 Bridge) for Joomla 3
 * @version 2.1: ip.php
 * @author Alex Polonski
 * @copyright (C) 2012 - 2014 - Alex Polonski
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
**/
defined('JPATH_PLATFORM') or die;

class JFormRuleIP extends JFormRule
{
	public function test(SimpleXMLElement $element, $value, $group = null, JRegistry $input = null, JForm $form = null)
	{
		// If the field is empty and not required, the field is valid.
		$required = ((string) $element['required'] == 'true' || (string) $element['required'] == 'required');
		if (!$required && empty($value))
		{
			return true;
		}
		$ip=trim($value);
		# Thanks to http://stackoverflow.com/a/1934546
		if (function_exists('inet_pton'))
		{ # PHP 5.1.0
			# Let the built-in library parse the IP address
			return @inet_pton($ip) !== false;
		}
		elseif(preg_match(
				'/^(?>(?>([a-f0-9]{1,4})(?>:(?1)){7}|(?!(?:.*[a-f0-9](?>:|$)){7,})'
				.'((?1)(?>:(?1)){0,5})?::(?2)?)|(?>(?>(?1)(?>:(?1)){5}:|(?!(?:.*[a-f0-9]:){5,})'
				.'(?3)?::(?>((?1)(?>:(?1)){0,3}):)?)?(25[0-5]|2[0-4][0-9]|1[0-9]{2}|[1-9]?[0-9])'
				.'(?>\.(?4)){3}))$/iD', $ip))
		{
			return true;
		}
		return false;
	}
}
