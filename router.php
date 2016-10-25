<?php
/**
 * @package osTicky2 (osTicket 1.9.4 Bridge) for Joomla 3 - 3.0
 * @version 2.1: router.php
 * @author Alex Polonski
 * @copyright (C) 2012 - 2014 - Alex Polonski
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
**/
defined('_JEXEC') or die;

jimport('joomla.application.categories');

class osTicky2Router extends JComponentRouterBase
{
	public function parse(&$segments)
	{
		$vars = array();

		//Get the active menu item.
		$app	= JFactory::getApplication();
		$menu	= $app->getMenu();
		$item	= $menu->getActive();
	
		// Count route segments
		$count = count($segments);
	
		if (!isset($item))
		{
			$vars['view'] = $segments[0];
			
			if($segments[0] == 'thread')
			{
				$vars['id']	= $segments[$count - 1];
			}
			return $vars;
		}
		else 
		{
			$vars['view'] = $item->query['view'];
		}
		return $vars;
	}

	public function build(&$query)
	{
		$segments = array();
		
		// get a menu item based on Itemid or currently active
		$app	= JFactory::getApplication();
		$menu	= $app->getMenu();
		if(empty($query['Itemid']))
		{
			$menuItem = $menu->getActive();
		}
		else
		{
			$menuItem = $menu->getItem($query['Itemid']);
		}
		$mView	= (empty($menuItem->query['view'])) ? null : $menuItem->query['view'];
		if(isset($query['view']) && $mView != $query['view'])
		{
			$segments[] = $query['view'];
			if($query['view'] == 'thread')
			{
				$segments[] = $query['id'];
				unset($query['id']);
			}
			unset($query['view']);
			unset($query['Itemid']);
		}
		return $segments;
	}
		
}

function osTicky2BuildRoute(&$query)
{
	$router = new osTicky2Router;

	return $router->build($query);
}

function osTicky2ParseRoute($segments)
{
	$router = new osTicky2Router;

	return $router->parse($segments);
}

