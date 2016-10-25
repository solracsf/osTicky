<?php
/**
 * @package osTicky2 (osTicket 1.9.4 Bridge) for Joomla 3
 * @version 2.1.1: default_thread.php
 * @author Alex Polonski
 * @copyright (C) 2012 - 2014 - Alex Polonski
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
**/
// no direct access
defined('_JEXEC') or die;

$listDirn	= $this->escape($this->state->get('list.direction'));
$listOrder	= $this->escape($this->state->get('list.ordering'));

$canCreate = osTicky2Helper::getActions()->get('osticky.reply', 0);
$canAttach = osTicky2Helper::getOstConfig('allow_attachments') &&
	osTicky2Helper::getOstConfig('allow_online_attachments') &&
	(JFactory::getUser()->get('id') || !osTicky2Helper::getOstConfig('allow_online_attachments_onlogin'));

$hide_staff_name = osTicky2Helper::getOstConfig('hide_staff_name');
//$clickable_urls = osTicky2Helper::getOstConfig('clickable_urls'); // *** looks that in 1.8 this is default "true"
$max_file_size = osTicky2Helper::getOstConfig('max_file_size');

$marked_msg = $this->escape($this->state->get('msgID', ''));

$uri = JUri::getInstance();
?>

<?php if(empty($this->thread_closed)) : // if thread is closed, there will be no form on it ?>
<script type="text/javascript">
	Joomla.submitbutton = function(task)
	{
		if(!document.formvalidator || document.formvalidator.isValid(document.getElementById('adminForm')))
		{
			<?php 
			if($canCreate)
			{
				$message_field = $this->form->getField('body');
				if($message_field->type == 'Editor')
				{
					// inject editor js for standard message field only
					// check what happens with custom rich-text fields... $$$
					echo $message_field->save();
				}
			}
			?>
			if(task == 'thread.submit') {
				document.getElementById('submit_button').value = '1';
			}
			Joomla.submitform(task);
		}
	}
</script>
<?php endif; ?>

<fieldset class="osticky-thread">
	<legend>
		<?php echo JText::_('COM_OSTICKY_THREAD_TICKET_MESSAGES'); ?>
		<?php echo JHtml::_('grid.sort', '', 'created', $listDirn, $listOrder); ?>	
	</legend>
	
<form
	action="<?php echo htmlspecialchars(JFactory::getURI()->toString()); ?>" method="post" name="adminForm" id="adminForm" enctype="multipart/form-data" class="form-inline">
	<fieldset class="filters btn-toolbar">
		<div class="filter">
			<label class="filter-search-lbl" for="filter_search"><?php echo JText::_('JSEARCH_FILTER_LABEL'); ?></label>
			<input type="text" name="filter_search" id="filter_search"
				value="<?php echo $this->escape($this->state->get('filter.search')); ?>"
				title="<?php echo JText::_('COM_OSTICKY_THREAD_SEARCH_IN_MESSAGES'); ?>" />
			<button type="submit">
			<?php echo JText::_('JSEARCH_FILTER_SUBMIT'); ?>
			</button>
			<button type="button"
				onclick="document.id('filter_search').value='';this.form.submit();">
				<?php echo JText::_('JSEARCH_FILTER_CLEAR'); ?>
			</button>	
		</div>
		<input type="hidden" name="filter_order" value="<?php echo $listOrder; ?>" />
		<input type="hidden" name="filter_order_Dir" value="<?php echo $listDirn; ?>" />
		<input type="hidden" name="id" value="<?php echo $this->state->get('ticketID'); ?>" />
	</fieldset>
	<?php if(count($this->messages) == 0) : ?>
		<?php echo JText::_('COM_OSTICKY_THREAD_SEARCH_EMPTY'); ?>
	<?php else :?>
	<?php foreach ($this->messages as $i => $item) : ?>
	<?php $msg_key = $item->type.':'.$item->id; ?>
	<div <?php echo $marked_msg == $msg_key ? 'id="highlight" name="highlight"' : ''; ?>" class="osticky-message-info<?php echo $marked_msg == $msg_key ? ' msg-highlight' : ''; ?>">
		<div class="<?php echo ($item->type == 'M' ? 'osticky-message' : 'osticky-response'); ?>">
			<?php echo JText::_('COM_OSTICKY_THREAD_MESSAGE_CREATED'); ?>
			<?php echo osTicky2Helper::dateFromOSTwFormat($item->created, 'daydatetime_format'); ?>
			<?php if(!empty($item->staff_name)) : ?>
			&nbsp;-&nbsp;
			<?php echo ($hide_staff_name ?
				JText::_('COM_OSTICKY_THREAD_STAFF_NAME_HIDDEN') :
				$this->escape($item->staff_name)); ?>
			<?php endif; ?>
		</div>
		<?php if($item->attachments) : ?>
		<div class="osticky-message-attachment">
		<?php foreach($item->attachments as $attach) : ?>
			<a class="message-attachment" target="_blank" href="index.php?option=com_osticky2&view=attachment&format=raw&id=<?php echo (int)$attach['attach_id']; ?>&ref=<?php echo $this->escape($attach['ref']); ?>">
			<?php echo $this->escape($attach['file_name']); ?></a>
			&nbsp;(<?php echo $this->escape($attach['file_size']); ?>&nbsp;bytes)&nbsp;	
		<?php endforeach; ?>
		</div>
		<?php endif; ?>
		<div class="osticky-message-text">
			<?php 
				// we rely on the data that comes from the db - non-html message
				// will be wrapped in <pre/> on ticket/message save 
				$msg_html = $item->msg_text;
				
				//if($clickable_urls) {
					//$msg_html = osTicky2Helper::clickableUrls($msg_html);
				//}
				echo $msg_html;
			?>
		</div>
	</div>
	<div class="clear"></div>
	<?php endforeach; ?>
	<?php endif; ?>
	<?php if ($this->params->get('show_pagination')) : ?>
		<?php if ($this->params->get('show_pagination_limit')) : ?>
		<div class="pagination">
			<div class="display-limit">
			<?php echo JText::_('JGLOBAL_DISPLAY_NUM'); ?>
				&#160;
				<?php echo $this->pagination->getLimitBox(); ?>
			</div>
		</div>
		<?php endif; ?>
		<?php if ($this->params->def('show_pagination_results', 1)) : ?>
			<p class="counter">
				<?php echo $this->pagination->getPagesCounter(); ?>
			</p>
		<?php endif; ?>
		<div class="pagination">
			<?php echo $this->pagination->getPagesLinks(); ?>
			<input type="hidden" id="nav_link" name="nav_link" value="" />
		</div>
	<?php endif; ?>
	<?php if($canCreate && empty($this->thread_closed)): // if thread is closed, do not show message form ?>
		<div class="clearboth"></div>
		<?php if($this->ticket->status == 'closed') : ?>
			<?php echo JText::_('COM_OSTICKY_THREAD_TICKET_REOPEN_NOTE'); ?>
		<?php endif; ?>
		<!-- fieldset class="osticky-message-reply"-->
			<div class="control-group">
				<div class="control-label"><?php echo $this->form->getLabel('body'); ?></div>
				<div class="clearboth"><?php echo $this->form->getInput('body'); ?></div>
			</div>
			<?php if($canAttach): ?>
			<div class="control-group">
				<div class="control-label"><?php echo $this->form->getLabel('filedata'); ?></div>
				<div class="controls"><?php echo $this->form->getInput('filedata').JText::sprintf('COM_OSTICKY_FILE_UPLOAD_MAX_SIZE', $max_file_size); ?></div>
			</div>
			<?php endif; ?>
			<?php echo $this->form->getInput('ticket_id'); ?>
				<a class="btn pull-left" href="<?php echo $this->return; ?>"><?php echo JText::_('JCANCEL'); ?></a> 
				<button type="button" class="btn btn-primary validate pull-right" onclick="Joomla.submitbutton('thread.submit')">
					<span class="icon-ok"></span>&#160;<?php echo JText::_('COM_OSTICKY_THREAD_MESSAGE_SEND') ?>
				</button>
				<!--div class="form-actions"-->
					<!-- button class="btn btn-primary validate pull-right" type="submit" name="submit_button" value="1"><?php //echo JText::_('COM_OSTICKY_THREAD_MESSAGE_SEND'); ?></button-->
				<!--/div-->
		<!-- /fieldset-->
	<?php else : ?>
		<div class="clearboth"></div>
		<?php echo JText::_('COM_OSTICKY_THREAD_CLOSED_NOTE'); ?>
	<?php endif; ?>
	<?php echo JHtml::_( 'form.token' ); ?>
	<input type="hidden" name="option" value="com_osticky2" />
	<input type="hidden" name="task" value="thread.submit" />
	
	<input type="hidden" id="submit_button" name="submit_button" value="" />
	<input type="hidden" id="return" name="return" value="<?php echo base64_encode($uri); ?>" />
</form>	
	
</fieldset>

