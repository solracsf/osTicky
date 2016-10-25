<?php
/**
 * @package osTicky2 (osTicket 1.9.4 Bridge) for Joomla 3
 * @version 2.1.6: osticky2behavior.php
 * @author Alex Polonski
 * @copyright (C) 2012 - 2015 - Alex Polonski
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 **/
defined('JPATH_PLATFORM') or die;

JHtml::_('jquery.framework');

abstract class JHtmlOsTicky2Behavior
{
	/**
	 * Array containing information for loaded files
	 *
	 * @var    array
	 * @since  2.5
	 */
	protected static $loaded = array();

	/**
	 * Method to load btngroup.js script
	 *
	*/
	public static function btngroup()
	{
		// Only load once
		if (!empty(static::$loaded[__METHOD__]))
		{
			return;
		}

		JFactory::getDocument()->addScript(JUri::base(true).'/components/com_osticky2/helpers/html/btngroup.js');
		
		
		// Set static array
		static::$loaded[__METHOD__] = true;

		return;
	}
}