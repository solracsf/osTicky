<?php
/**
 * @package osTicky2 (osTicket 1.9.4 Bridge) for Joomla 3
 * @version 2.1: default.php
 * @author Alex Polonski
 * @copyright (C) 2012 - 2014 - Alex Polonski
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
**/
// no direct access
defined('_JEXEC') or die;
?>
<fieldset>
	<legend><?php echo JText::_('COM_OSTICKY_DATABASE'); ?></legend>
	<dl>
		<dt><?php echo JText::_('COM_OSTICKY_DATABASE_CONNECTION'); ?></dt>
		<?php if($this->ostInfo && empty($this->ostInfo->error)): ?>
			<dd><?php echo JText::_('COM_OSTICKY_DATABASE_CONNECTION_OK'); ?></dd>
		<?php else: ?>
			<dd><?php echo JText::_('COM_OSTICKY_DATABASE_CONNECTION_ERROR'); ?></dd>
		<?php endif; ?>
	</dl>
</fieldset>
<fieldset>
	<legend><?php echo JText::_('COM_OSTICKY2_CONFIGURATION'); ?></legend>
	<dl>
		<?php if($this->ostInfo && !empty($this->ostInfo->tables_count) && !$this->ostInfo->error): ?>
			<dt><?php echo JText::_('COM_OSTICKY_CONFIGURATION_VERSION'); ?></dt>
			<dd><?php echo $this->escape($this->ostInfo->config->ostversion); ?></dd>
			<dt><?php echo JText::_('COM_OSTICKY_CONFIGURATION_HELPDESK_TITLE'); ?></dt>
			<dd><?php echo $this->escape($this->ostInfo->config->helpdesk_title); ?></dd>
			<dt><?php echo JText::_('COM_OSTICKY_CONFIGURATION_HELPDESK_URL'); ?></dt>
			<dd><?php echo $this->escape($this->ostInfo->config->helpdesk_url); ?></dd>
		<?php else: ?>
			<dt><?php echo JText::_('COM_OSTICKY_CONFIGURATION_ERROR'); ?></dt>
			<dd><?php echo $this->ostInfo && $this->ostInfo->error ?
				$this->escape($this->ostInfo->error) :
				JText::_('COM_OSTICKY_OST_NOT_FOUND'); ?></dd>
		<?php endif; ?>
	</dl>
</fieldset>

