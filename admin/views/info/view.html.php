<?php
/**
 * @package osTicky2 (osTicket 1.9.4 Bridge) for Joomla 3
 * @version 2.1: view.html.php
 * @author Alex Polonski
 * @copyright (C) 2012 - 2014 - Alex Polonski
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
**/
// No direct access
defined('_JEXEC') or die;

jimport('joomla.application.component.view');

class osTicky2ViewInfo extends JViewLegacy
{
	protected $ostInfo;
	
	public function display($tpl = null)
	{
		require_once JPATH_COMPONENT.'/helpers/osticky2.php';
		
		$this->ostInfo = osTicky2Helper::getOstInfo();
		$this->addToolbar();
		parent::display($tpl);
	}

	protected function addToolbar()
	{
		require_once JPATH_COMPONENT.'/helpers/osticky2.php';
		$canDo	= osTicky2Helper::getActions();
		JToolBarHelper::title(JText::_('COM_OSTICKY_INFO'), 'osticky.png');

		if ($canDo->get('core.admin')) {
			JToolBarHelper::preferences('com_osticky2');
		}
	}
}
