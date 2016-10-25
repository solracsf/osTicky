<?php
/**
 * @package osTicky2 (osTicket 1.9.4 Bridge) for Joomla 3
 * @version 2.1.6: default_ticket.php
 * @author Alex Polonski
 * @copyright (C) 2012 - 2014 - Alex Polonski
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
**/
// no direct access
defined('_JEXEC') or die;
$stickyPlugin = osTicky2Helper::getStickyPlugin();
// *** Layout note: we use data lists for ticket data display, we
// have to add clearfix class to all <dd /> elements, so that the
// next pair of dt-dd starts exactly on new line even if one of
// the values wraps in 2 or more lines
?>
<fieldset class="osticky-ticket">
	<legend>
		<a class="tickets-refresh" href="<?php echo htmlspecialchars(JFactory::getURI()->toString()); ?>"><?php echo JText::sprintf('COM_OSTICKY_THREAD_TICKET_INFO', $this->ticket->ticketID); ?></a>
	</legend>
	<div class="osticky-ticket-info-main">
		<dl>
			<dt>
			<?php echo JText::_('COM_OSTICKY_THREAD_TICKET_SUBJECT'); ?>
			</dt>
			<dd class="clearfix">
			<?php echo $this->escape($this->ticket->subject); ?>
			</dd>			
		</dl>
	</div>
	<div class="osticky-ticket-info-details">
		<dl>
			<dt>
			<?php echo JText::_('COM_OSTICKY_THREAD_TICKET_ID'); ?>
			</dt>
			<dd class="clearfix">
			<?php echo $this->escape($this->ticket->ticketID); ?>
			<?php if($this->ticket->sticky2_url) : ?>
				<?php if(!empty($stickyPlugin)) : ?>
					<a title="<?php echo JText::_('COM_OSTICKY_TICKET_VIEW_STICKY_NOTE');
						?>" class="icon-link ticket-sticky no-text" href="<?php echo $this->ticket->sticky2_url.(strpos($this->ticket->sticky2_url, '?') > 0 ? '&' : '?').'sticky2_id='.$this->ticket->sticky2_id; 
						?>"><?php echo JText::_('COM_OSTICKY_VIEW_PAGE'); ?></a>
				<?php else : ?>
					<a title="<?php echo JText::_('COM_OSTICKY_TICKET_VIEW_SOURCE_PAGE'); 
						?>" class="icon-link ticket-sticky" href="<?php echo $this->ticket->sticky2_url; 
						?>"><?php echo JText::_('COM_OSTICKY_VIEW_PAGE'); ?></a>
				<?php endif; ?>
			<?php endif;?>
			</dd>
			<dt>
			<?php echo JText::_('COM_OSTICKY_THREAD_TICKET_NAME'); ?>
			</dt>
			<dd class="clearfix">
			<?php echo $this->escape($this->ticket->name); ?>
			</dd>
			<dt>
			<?php echo JText::_('COM_OSTICKY_THREAD_EMAIL'); ?>
			</dt>
			<dd class="clearfix">
			<?php echo $this->escape($this->ticket->email); ?>
			</dd>
			<dt>
			<?php echo JText::_('COM_OSTICKY_THREAD_SOURCE'); ?>
			</dt>
			<dd class="clearfix">
			<?php echo $this->escape($this->ticket->source); ?>
			</dd>

			<dt>
			<?php echo JText::_('COM_OSTICKY_THREAD_TICKET_STATUS'); ?>
			</dt>
			<dd class="clearfix">
			<?php echo JText::_('COM_OSTICKY_TICKET_STATUS_'.strtoupper($this->ticket->status)); ?>
			</dd>

			<dt>
			<?php echo JText::_('COM_OSTICKY_THREAD_TICKET_TOPIC'); ?>
			</dt>
			<dd class="clearfix">
			<?php echo $this->escape($this->ticket->help_topic); ?>
			</dd>
			
			<dt>
			<?php echo JText::_('COM_OSTICKY_THREAD_TICKET_DEPARTMENT'); ?>
			</dt>
			<dd class="clearfix">
			<?php echo $this->escape($this->ticket->dept_name); ?>
			</dd>
		</dl>
	</div>
	<div class="osticky-ticket-info-dates">
		<dl>
			<dt>
			<?php echo JText::_('COM_OSTICKY_THREAD_TICKET_CREATED'); ?>
			</dt>
			<dd class="clearfix">
			<?php echo osTicky2Helper::dateFromOSTwFormat($this->ticket->created, 'datetime_format'); ?>
			</dd>				
			<?php if($this->ticket->closed) : ?>
				<dt>
				<?php echo JText::_('COM_OSTICKY_THREAD_TICKET_CLOSED'); ?>
				</dt>
				<dd class="clearfix">
				<?php echo osTicky2Helper::dateFromOSTwFormat($this->ticket->closed, 'datetime_format'); ?>
				</dd>			
			<?php endif; ?>
			<?php if($this->ticket->reopened) : ?>
				<dt>
				<?php echo JText::_('COM_OSTICKY_THREAD_TICKET_REOPENED'); ?>
				</dt>
				<dd class="clearfix">
				<?php echo osTicky2Helper::dateFromOSTwFormat($this->ticket->reopened, 'datetime_format'); ?>
				</dd>					
			<?php endif; ?>
			
			<dt>
			<?php echo JText::_('COM_OSTICKY_THREAD_TICKET_LASTMESSAGE'); ?>
			</dt>
			<dd class="clearfix">
			<?php echo osTicky2Helper::dateFromOSTwFormat($this->ticket->lastmessage, 'datetime_format'); ?>
			</dd>

			<dt>
			<?php echo JText::_('COM_OSTICKY_THREAD_TICKET_LASTRESPONSE'); ?>
			</dt>
			<dd class="clearfix">
			<?php echo (!empty($this->ticket->lastresponse) ?
				osTicky2Helper::dateFromOSTwFormat($this->ticket->lastresponse, 'datetime_format') :
				' --- '); ?>
			</dd>
		</dl>
	</div>
	<div class="osticky-ticket-info-forms">
		<?php foreach($this->ticket->forms as $form) : ?>
			<?php if(!empty($form['fields'])) : // do not show fieldset if it contains no fields ?>
			<fieldset>
				<legend><?php echo $this->escape($form['title']); ?></legend>
				<dl>
				<?php foreach($form['fields'] as $field) : ?>
					<dt><?php echo $this->escape($field->label); ?></dt>
					<dd class="clearfix"><?php echo $field->value; ?></dd>
				<?php endforeach; ?>
				</dl>
			</fieldset>
			<?php endif; ?>
		<?php endforeach; ?>
	</div>
</fieldset>
