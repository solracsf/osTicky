<?php
/**
 * @package osTicky2 (osTicket 1.9.4 Bridge) for Joomla 3
 * @version 2.2.3: view.html.php
 * @author Alex Polonski
 * @copyright (C) 2012 - 2014 - Alex Polonski
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
**/
// No direct access
defined('_JEXEC') or die;

jimport('joomla.application.component.view');

class osTicky2ViewTickets extends JViewLegacy
{
	protected $state;
	protected $params;
	protected $items;
	protected $pagination;

	function display($tpl = null)
	{
		$app		= JFactory::getApplication();
		$params		= $app->getParams();

		$active	= $app->getMenu()->getActive();
		if($active)
		{
			$params->merge($active->params);
		}
		$this->params = $params;

		// Get some data from the models
		$this->state		= $this->get('State');
		$this->items		= $this->get('Items');
		$this->pagination	= $this->get('Pagination');

		// Check for errors.
		if (count($errors = $this->get('Errors'))) {
			JError::raiseError(500, implode("\n", $errors));
			return false;
		}
		
// add document title START
		// Because the application sets a default page title,
		// we need to get it from the menu item itself
		$title = !is_null($active) ? $active->title : '';
		
		if (empty($title))
		{
			$title = $app->get('sitename');
		}
		elseif ($app->get('sitename_pagetitles', 0) == 1)
		{
			$title = JText::sprintf('JPAGETITLE', $app->get('sitename'), $title);
		}
		elseif ($app->get('sitename_pagetitles', 0) == 2)
		{
			$title = JText::sprintf('JPAGETITLE', $title, $app->get('sitename'));
		}
		
		$this->document->setTitle($title);
// add document title END

		JFactory::getDocument()->addStyleSheet(JURI::base(true).'/components/com_osticky2/css/styles30.css');
		parent::display($tpl);
	}

}
