<?php
/**
 * @package osTicky2 (osTicket 1.9.4 Bridge) for Joomla 3
 * @version 2.1.2: default.php
 * @author Alex Polonski
 * @copyright (C) 2012 - 2014 - Alex Polonski
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
**/
// no direct access
defined('_JEXEC') or die;
JHtml::_('behavior.tooltip');

$listOrder	= $this->escape($this->state->get('list.ordering'));
$listDirn	= $this->escape($this->state->get('list.direction'));
$filter_status = $this->escape($this->state->get('filter.status'));
$stickyPlugin = osTicky2Helper::getStickyPlugin();
$user = JFactory::getUser();
$user_name = $user->get('name');
$user_email = $user->get('email');

$uri = JUri::getInstance();

?>
<div id="osticky_main">
	<!--div class="tickets-list-heading">
	<h2><a class="icon-link tickets-refresh" href="<?php /* echo htmlspecialchars(JFactory::getURI()->toString()); ?>">
		<?php if($user_name == $user_email) : ?>
			<?php echo JText::sprintf('COM_OSTICKY_TICKETS_LIST_HEADING_EMAIL', $user_email); ?>
		<?php else: ?>
			<?php echo JText::sprintf('COM_OSTICKY_TICKETS_LIST_HEADING_NAME_EMAIL', $user_name, $user_email); ?>
		<?php endif; */ ?>
	</a></h2>
	</div-->
	<form action="<?php echo htmlspecialchars(JFactory::getURI()->toString()); ?>" method="post" name="adminForm" id="adminForm" class="form-inline">
		<fieldset class="filters btn-toolbar">
		<div class="filter">
			<label class="filter-status-lbl" for="filter_status"><?php echo JText::_('COM_OSTICKY_TICKETS_STATUS_FILTER_LABEL'); ?></label>
			<select name="filter_status" id="filter_status" class="inputbox" onchange="this.form.submit()">
				<option <?php echo $filter_status == '' ? ' selected ': ''; ?>value=""><?php echo JText::_('COM_OSTICKY_TICKETS_FILTER_STATUS_ALL');?></option>
				<option <?php echo $filter_status == 'open' ? ' selected ': ''; ?>value="open"><?php echo JText::_('COM_OSTICKY_TICKETS_FILTER_STATUS_OPEN');?></option>
				<option <?php echo $filter_status == 'closed' ? ' selected ': ''; ?>value="closed"><?php echo JText::_('COM_OSTICKY_TICKETS_FILTER_STATUS_CLOSED');?></option>
			</select>&nbsp;
			<label class="filter-search-lbl" for="filter_search"><?php echo JText::_('JSEARCH_FILTER_LABEL'); ?></label>
			<input type="text" name="filter_search" id="filter_search" value="<?php echo $this->escape($this->state->get('filter.search')); ?>" title="<?php echo JText::_('COM_OSTICKY_TICKETS_SEARCH_IN_TICKETS'); ?>" />
			<button class="btn" type="submit"><?php echo JText::_('JSEARCH_FILTER_SUBMIT'); ?></button>
			<button class="btn "type="button" onclick="document.id('filter_search').value='';this.form.submit();"><?php echo JText::_('JSEARCH_FILTER_CLEAR'); ?></button>
		</div>
		
		<input type="hidden" name="filter_order" value="<?php echo $listOrder; ?>" />
		<input type="hidden" name="filter_order_Dir" value="<?php echo $listDirn; ?>" />
		<input type="hidden" name="limitstart" value="" />
		<input type="hidden" name="task" value="" />
		<div class="clearfix"></div>
		</fieldset>
		<table class="table table-striped">
			<?php if ($this->params->get('show_headings', 1)) : ?>
			<thead>
				<tr>
					<th colspan="5" class="center">
						<a class="tickets-refresh" href="<?php echo htmlspecialchars(JFactory::getURI()->toString()); ?>">
				<?php if($user_name == $user_email) : ?>
					<?php echo JText::sprintf('COM_OSTICKY_TICKETS_LIST_HEADING_EMAIL', $user_email); ?>
				<?php else: ?>
					<?php echo JText::sprintf('COM_OSTICKY_TICKETS_LIST_HEADING_NAME_EMAIL', $user_name, $user_email); ?>
				<?php endif; ?>
						</a>
					</th>
				</tr>
				<tr>
					<th width="12%" class="item-ticketID ticket-icon" id="tableOrdering1">
						<?php echo JHtml::_('grid.sort', 'COM_OSTICKY_TICKETS_TICKET_ID', 'a.ticketID', $listDirn, $listOrder); ?>
					</th>
					<th width="15%" class="item-created nowrap" id="tableOrdering2">
						<?php echo JHtml::_('grid.sort', 'COM_OSTICKY_TICKETS_CREATED', 'a.created', $listDirn, $listOrder); ?>
					</th>
					<th width="5%" class="item-status" id="tableOrdering3">
						<?php echo JHtml::_('grid.sort', 'COM_OSTICKY_TICKETS_STATUS', 'ts.state', $listDirn, $listOrder); ?>
					</th>
					<th class="item-subject" id="tableOrdering4">
						<?php echo JHtml::_('grid.sort', 'COM_OSTICKY_TICKETS_SUBJECT', 'a.subject', $listDirn, $listOrder); ?>
					</th>				
					<th width="20%" class="item-dept_name" id="tableOrdering5">
						<?php echo JHtml::_('grid.sort', 'COM_OSTICKY_TICKETS_DEPARTMENT', 'b.dept_name', $listDirn, $listOrder); ?>
					</th>				
				</tr>
			</thead>
			<?php endif; ?>
			<tbody>
				<?php if(count($this->items) == 0) : ?>
					<tr><td colspan="5" align="center"><?php echo JText::_('COM_OSTICKY_TICKETS_SEARCH_EMPTY'); ?></td></tr>
				<?php else :?>
				<?php foreach ($this->items as $i => $item) : ?>
					<tr class="cat-list-row<?php echo $i % 2; ?>" >
						<td class="nowrap item-ticketID<?php echo ' ticket-'.strtolower($item->source).'-icon'; ?>">
						<?php $url = 'index.php?option=com_osticky2&view=thread&return=' . base64_encode($uri) . '&id='.urlencode($item->ticketID); ?>
						<a href="<?php echo JRoute::_($url); ?>">
							<?php if($item->isanswered) : ?>
								<strong><span class="hasTip" title="<?php echo JText::_('COM_OSTICKY_TICKETS_ISANSWERED');?>"><?php echo $this->escape($item->ticketID); ?></span></strong>
							<?php else : ?>
								<?php echo $this->escape($item->ticketID); ?>
							<?php endif; ?>
						</a>
						</td>
						<td class="item-created nowrap">
							<?php echo osTicky2Helper::dateFromOSTwFormat($item->created, 'datetime_format'); ?>
						</td>
						<td class="item-status">
							<?php echo JText::_('COM_OSTICKY_TICKET_STATUS_'.strtoupper($item->status)); ?>
						</td>
						<td class="item-subject<?php echo $item->count_attachments ? ' attachment-icon' : ''; ?>">
							<a href="<?php echo JRoute::_($url); ?>">
								<span class="hasTip" title="<?php echo JText::plural('COM_OSTICKY_TICKETS_N_MESSAGES', $item->count_messages); ?>
								<br /><?php	echo JText::plural('COM_OSTICKY_TICKETS_N_RESPONSES', $item->count_responses);?>">
									<?php echo $this->escape($item->subject); ?>
								</span>
							</a>
							<?php if($item->sticky2_url) : ?>
								<?php if(!empty($stickyPlugin)) : ?>
									<a title="<?php echo JText::_('COM_OSTICKY_TICKET_VIEW_STICKY_NOTE'); 
										?>" class="icon-link ticket-sticky no-text" href="<?php echo $item->sticky2_url.(strpos($item->sticky2_url, '?') > 0 ? '&' : '?').'sticky2_id='.$item->sticky2_id; 
										?>"><?php echo JText::_('COM_OSTICKY_VIEW_PAGE'); ?></a>
								<?php else : ?>
									<a title="<?php echo JText::_('COM_OSTICKY_TICKET_VIEW_SOURCE_PAGE'); 
										?>" class="icon-link ticket-sticky no-text" href="<?php echo $item->sticky2_url; 
										?>"><?php echo JText::_('COM_OSTICKY_VIEW_PAGE'); ?></a>
								<?php endif; ?>
							<?php endif;?>
						</td>
						<td class="item-dept_name">
							<span class="hasTip" title="<?php echo $this->escape(JText::sprintf('COM_OSTICKY_TICKETS_HELP_TOPIC', $item->help_topic)); ?>"><?php echo $this->escape($item->dept_name); ?></span>
						</td>
					</tr>
				<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	
		<?php if ($this->params->get('show_pagination')) : ?>
		<?php if ($this->params->get('show_pagination_limit')) : ?>
		<div class="pagination">
			<div class="display-limit">
				<?php echo JText::_('JGLOBAL_DISPLAY_NUM'); ?>&#160;
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
		</div>
		<?php endif; ?>
	</form>
</div>