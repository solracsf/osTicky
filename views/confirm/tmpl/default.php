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
<?php if(!empty($this->error)) : ?>
<p class="error">
	<?php echo JText::_($this->error); ?>
</p>
<?php else : ?>
<p>
<?php echo $this->escape($this->name); ?>,
</p>
<p>
<?php echo JText::sprintf('COM_OSTICKY_TICKET_CONFIRMATION_THANKYOU', $this->escape($this->ticketID)); ?>
</p>

<?php if(!empty($this->autoresponded)) : ?>
	<p>
	<?php echo JText::sprintf('COM_OSTICKY_TICKET_CONFIRMATION_EMAIL', $this->escape($this->email)); ?>
	</p>
<?php endif; ?>

<p>
<?php echo JText::_('COM_OSTICKY_TICKET_CONFIRMATION_FROM'); ?>
</p>
<?php endif; ?>
<?php if($this->function) : ?>
	<input class="btn btn-primary" onclick="if(window.parent) window.parent.<?php echo $this->function; ?>();" type="reset" value="<?php echo JText::_('COM_OSTICKY_TICKET_TICKET_CLOSE'); ?>" class="button" />
<?php elseif(empty($this->error)) : // class button has no effect here! $$$ ?>
	<a href="<?php echo JRoute::_('index.php?option=com_osticky2&view=tickets'); ?>" class="button"><?php echo JText::_('COM_OSTICKY_TICKET_TICKET_VIEW_TICKETS'); ?></a>
<?php endif; ?>
