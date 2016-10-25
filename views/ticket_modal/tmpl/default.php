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

JHtml::_('behavior.keepalive');
JHtml::_('behavior.formvalidation');
JHtml::_('behavior.tooltip');
JHtml::_('formbehavior.chosen', 'select');

// Add btn-group script missing in some templates
JHtml::addIncludePath(JPATH_COMPONENT_SITE . '/helpers/html');
JHtml::_('osticky2behavior.btngroup');

$canAttach = osTicky2Helper::getOstConfig('allow_attachments') &&
	osTicky2Helper::getOstConfig('allow_online_attachments') &&
	(JFactory::getUser()->get('id') || !osTicky2Helper::getOstConfig('allow_online_attachments_onlogin'));
$max_file_size = osTicky2Helper::getOstConfig('max_file_size');
	
$function = $this->state->get('function', 'plg_webtodo_annotationNew.close');
?>

<script type="text/javascript">
	Joomla.submitbutton = function(task)
	{
		if(task == 'ticket.cancel' || !document.formvalidator || document.formvalidator.isValid(document.getElementById('adminForm')))
		{
			<?php 
				$message_field = $this->form->getField('message', 'ticket.form_2');
				if($message_field->type == 'Editor')
				{
					// inject editor js for standard message field only
					// check what happens with custom rich-text fields... $$$
					echo $message_field->save(); 
				}
			?>
			if(task == 'ticket.cancel' && window.parent) {
				Joomla.submitform(task);
				window.parent.setTimeout(function() {
					this.<?php echo $function; ?>();
				}, 100);
			} else {
				Joomla.submitform(task);
			}
		}
	}
</script>
			
<div id="osticky_main">
	<div class="osticky-ticket-form">
		<form id="adminForm" action="<?php echo htmlspecialchars(JFactory::getURI()->toString()); ?>" method="post" class="form-validate form-horizontal" enctype="multipart/form-data">
			<?php $fieldsets = $this->form->getFieldsets(); ?>
			<?php foreach ($fieldsets as $fieldset) : ?>
				<?php if ($fieldset->name == 'topic'): ?>
					<div class="control-group">
						<div class="control-label"><?php echo $this->form->getLabel('topic_id', 'ticket'); ?></div>
						<div class="controls"><?php echo $this->form->getInput('topic_id', 'ticket'); ?></div>
					</div>
				<?php else : ?>
					<?php $fields = $this->form->getFieldset($fieldset->name);?>
					<?php if(count($fields) > 0) : ?>
						<?php if(!empty($fieldset->label)) : ?>
						<fieldset class="osticky-ticket-new">
							<legend>
								<?php echo $this->escape($fieldset->label); ?>
							</legend>
						<?php endif; ?>
						<?php foreach ($fields as $field) : ?>
							<div class="control-group">
								<?php if ($field->hidden) : ?>
									<div class="controls">
										<?php echo $field->input;?>
									</div>
								<?php else:?>
									<div class="control-label">
										<?php echo $field->label; ?>
										<?php if (!$field->required && $field->type != "Spacer") : ?>
											<span class="optional"></span>
										<?php endif; ?>
									</div>
									<?php if(($field->type == 'Editor')) : ?>
										<div class="clearboth"><?php echo $field->input;?></div>
									<?php else : ?>
										<div class="controls"><?php echo $field->input;?></div>
									<?php endif; ?>
								<?php endif;?>
							</div>
						<?php endforeach;?>
						<?php if(!empty($fieldset->label)) : ?>
						</fieldset>
						<?php endif; ?>
					<?php endif; ?>
				<?php endif ?>
			<?php endforeach;?>
			
			<?php // $$$ We need this info just at the top of send client info. Have to add it as a spacer field! ?>
			<!--div id="stciky_info" class="osticky-plugin-info hasTip" title="<?php //echo JText::_('COM_OSTICKY_TICKET_STICKY_INFO_SEND_NOTE_DETAILS'); ?>">
						<?php //echo JText::_('COM_OSTICKY_TICKET_STICKY_INFO_SEND_NOTE'); ?>
			</div-->
			
			<div class="form-actions">
				<button type="button" class="btn btn-primary validate" onclick="Joomla.submitbutton('ticket.submit')">
					<span class="icon-ok"></span>&#160;<?php echo JText::_('COM_OSTICKY_TICKET_TICKET_SEND') ?>
				</button>
				<button type="button" class="btn" onclick="Joomla.submitbutton('ticket.cancel')">
					<span class="icon-cancel"></span>&#160;<?php echo JText::_('COM_OSTICKY_TICKET_TICKET_CANCEL') ?>
				</button>
				
				<!-- button class="btn btn-primary validate" type="submit"><?php //echo JText::_('COM_OSTICKY_TICKET_TICKET_SEND'); ?></button-->
				<!-- input onclick="if(window.parent) window.parent.<?php //echo $function; ?>();" class="btn" type="reset" value="<?php //echo JText::_('COM_OSTICKY_TICKET_TICKET_CANCEL'); ?>" /-->
			</div>
			<input type="hidden" name="option" value="com_osticky2" />
			<input type="hidden" name="view" value="ticket_modal" />
			<input type="hidden" name="task" id="task" value="" />
			<input type="hidden" name="layout" value="modal" />
			<?php echo JHtml::_( 'form.token' ); ?>
		</form>
	</div>
</div>
