<?php

// no direct access
defined('_JEXEC') or die;

?>
<p>
<?php echo $this->name; ?>,
</p>
<p>
<?php echo JText::_('COM_OSTICKY_TICKETS_CONFIRMATION_THANKYOU'); ?>
</p>
<p>
<?php if($this->email_sent) : ?>
<?php echo JText::sprintf('COM_OSTICKY_TICKETS_CONFIRMATION_EMAIL', $this->email_sent); ?>

<?php endif; ?>
</p>
<p>
<?php echo JText::_('COM_OSTICKY_TICKETS_CONFIRMATION_FROM'); ?>
</p>
