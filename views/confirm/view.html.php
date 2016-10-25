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

class osTicky2ViewConfirm extends JViewLegacy
{
	protected $email_sent;
	protected $name;
	protected $email;
	protected $error;
	protected $params;
	protected $autoresponded;
	protected $function;

	function display($tpl = null)
	{
		$app		= JFactory::getApplication();
		$input		= $app->input;
		$params		= $app->getParams();

		$this->params = $params;

		$conf_data = $input->getBase64('conf', '');
		$conf_data = base64_decode($conf_data);
		list($this->error, $this->name, $this->email, $this->ticketID) = explode(':', $conf_data);

		$this->autoresponded = osTicky2Helper::getOstConfig('ticket_autoresponder');
		
		$this->function = $input->getString('function', '');
		
		$user_email = JFactory::getUser()->get('email');
		if($user_email && $user_email != $this->email && !$this->error)
		{
			$this->error = 'COM_OSTICKY_TICKET_CONFIRMATION_EXPIRED';
		}

		JFactory::getDocument()->addStyleSheet(JURI::base(true).'/components/com_osticky2/css/styles30.css');
		$this->_prepareDocument();
		
		parent::display($tpl);
	}

	protected function _prepareDocument()
	{
		$app = JFactory::getApplication();
		
		$title = empty($this->error) ? JText::_('COM_OSTICKY_CONFIRM_PAGE_TITLE') : JText::_('COM_OSTICKY_CONFIRM_ERROR_PAGE_TITLE');		
		$this->params->def('page_heading', JText::_('COM_OSTICKY_CONFIRM_PAGE_TITLE'));
		
		if ($app->getCfg('sitename_pagetitles', 0) == 1)
		{
			$title = JText::sprintf('JPAGETITLE', $app->getCfg('sitename'), $title);
		}
		elseif ($app->getCfg('sitename_pagetitles', 0) == 2)
		{
			$title = JText::sprintf('JPAGETITLE', $title, $app->getCfg('sitename'));
		}
		$this->document->setTitle($title);
	}
}
