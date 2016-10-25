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
JHtml::_('behavior.formvalidation');		// looks that the validation is done without this line? $$$
JHtml::_('behavior.tooltip');
JHtml::_('formbehavior.chosen', 'select');	// validation popup message displays wrong $$$

// Add btn-group script missing in some templates
JHtml::addIncludePath(JPATH_COMPONENT_SITE . '/helpers/html');
JHtml::_('osticky2behavior.btngroup');

$canAttach = osTicky2Helper::getOstConfig('allow_attachments') &&
	osTicky2Helper::getOstConfig('allow_online_attachments') &&
	(JFactory::getUser()->get('id') || !osTicky2Helper::getOstConfig('allow_online_attachments_onlogin'));
$max_file_size = osTicky2Helper::getOstConfig('max_file_size');
$sticky_plugin = osTicky2Helper::getStickyPlugin();
$groups = JFactory::getUser()->getAuthorisedGroups();
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
			Joomla.submitform(task);
		}
	}
</script>
			
<div id="osticky_main">
<?php if($sticky_plugin && $sticky_plugin['groups'] && array_intersect($groups, $sticky_plugin['groups'])) : ?>
	<div class="osticky-plugin-info">
	<?php echo JText::_('COM_OSTICKY_PLUGIN_SETTINGS_INFO'); ?>
		<ul>
			<li><?php echo JText::sprintf('COM_OSTICKY_PLUGIN_SETTINGS_MOUSE', strtoupper($sticky_plugin['activation'])); ?></li>
			<li><?php echo JText::sprintf('COM_OSTICKY_PLUGIN_SETTINGS_TOGGLE', strtoupper($sticky_plugin['view_toggle'])); ?></li>
		</ul>
	</div>
<?php endif;?>
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
							<?php if(!empty($fieldset->description)) : ?>
								<p class="fieldset-desc"><?php echo $this->escape($fieldset->description); ?></p>
							<?php endif; ?>
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
			
			<div class="form-actions">
				<button type="button" class="btn btn-primary validate" onclick="Joomla.submitbutton('ticket.submit')">
					<span class="icon-ok"></span>&#160;<?php echo JText::_('COM_OSTICKY_TICKET_TICKET_SEND') ?>
				</button>
				<button type="button" class="btn" onclick="Joomla.submitbutton('ticket.cancel')">
					<span class="icon-cancel"></span>&#160;<?php echo JText::_('COM_OSTICKY_TICKET_TICKET_CANCEL') ?>
				</button>
				
				<!-- button class="btn btn-primary validate" type="submit"><?php //echo JText::_('COM_OSTICKY_TICKET_TICKET_SEND'); ?></button-->
				<!-- a class="btn" href="<?php //echo JRoute::_(JUri::base(), false); ?>"><?php //echo JText::_('COM_OSTICKY_TICKET_TICKET_CANCEL'); ?></a-->
			</div>
			<input type="hidden" name="option" value="com_osticky2" />
			<input type="hidden" name="task" id="task" value="" />
			<?php echo JHtml::_( 'form.token' ); ?>
			
		</form>
	</div>
</div>
