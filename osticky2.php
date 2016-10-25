<?php
/**
 * @package osTicky2 (osTicket 1.9.4 Bridge) for Joomla 3 -3.0
 * @version 2.1: osticky2.php
 * @author Alex Polonski
 * @copyright (C) 2012 - 2014 - Alex Polonski
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
**/
// no direct access
defined('_JEXEC') or die;

// Include dependancies
jimport('joomla.application.component.controller');

$controller = JControllerLegacy::getInstance('osTicky2');
$controller->execute(JFactory::getApplication()->input->get('task'));
$controller->redirect();
