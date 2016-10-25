<?php
/**
 * @package osTicky2 (osTicket 1.9.4 Bridge) for Joomla 3
 * @version 2.1: controller.php
 * @author Alex Polonski
 * @copyright (C) 2012 - 2014 - Alex Polonski
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
**/
// no direct access
defined('_JEXEC') or die;

jimport('joomla.application.component.controller');

class osTicky2Controller extends JControllerLegacy
{
	protected $default_view = 'info';

	public function display($cachable = false, $urlparams = false)
	{
		require_once JPATH_COMPONENT.'/helpers/osticky2.php';

		// Load the submenu.
		osTicky2Helper::addSubmenu(JFactory::getApplication()->input->get('view', 'info'));

		parent::display();

		return $this;
	}
}
