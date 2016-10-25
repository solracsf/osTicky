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

$this->date_format = osTicky2Helper::getOstConfig('daydatetime_format');

$return = JFactory::getApplication()->input->getBase64('return');
if(!empty($return))
{
	$this->return = base64_decode($return);
}
else
{
	$this->return = JRoute::_('index.php?option=com_osticky2&view=tickets', false);
}
?>

<?php if(empty($this->ticket)) : ?>
	<p><?php echo JText::_('COM_OSTICKY_ERROR_TICKET_NOT_FOUND'); ?></p>
<?php else : ?>
<div id="osticky_main">
	<?php echo $this->loadTemplate('ticket'); ?>
	<?php echo $this->loadTemplate('thread'); ?>
</div>
<?php endif; ?>