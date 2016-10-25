<?php
/**
 * @package osTicky2 (osTicket 1.9.4 Bridge) for Joomla 3
 * @version 2.1: install.script.php
 * @author Alex Polonski
 * @copyright (C) 2012 - 2014 - Alex Polonski
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
**/

// no direct access
defined('_JEXEC') or die('Restricted access');

jimport('joomla.installer.installer');

class Com_osTicky2InstallerScript
{
	protected $is_new_installation;
	
	function preflight($type, $parent)
	{
		$this->is_new_installation = !file_exists(JPATH_ADMINISTRATOR . '/components/com_osticky2/helpers/osticky2.php');
	}
	
    function postflight($type, $parent)
	{
		// Install subextensions
		$status = $this->_installSubextensions($parent);

        // Show the post-installation page
		$this->_renderPostInstallation($status, $parent);

    }

    function uninstall($parent)
	{
		// Uninstall subextensions
		$status = $this->_uninstallSubextensions($parent);

		// Show the post-uninstallation page
		$this->_renderPostUninstallation($status, $parent);
	}

    private function _installSubextensions($parent)
    {
        $db              = JFactory::getDBO();
        $status          = new JObject();
        $status->plugins = array();

        $plugins = array(
            array('pname' => 'osticky2', 'pgroup' => 'system'),
            array('pname' => 'osticket2', 'pgroup' => 'authentication'),
            array('pname' => 'osticky2', 'pgroup' => 'search'),
        	array('pname' => 'osticketprofile2', 'pgroup' => 'user')
        );
        $src = $parent->getParent()->getPath('source');

        // Plugins
        foreach($plugins as $plugin)
        {
	        $pname = $plugin['pname'];
	        $pgroup = $plugin['pgroup'];
	        $path = $src.'/plugins/'.$pgroup;
	        $installer = new JInstaller;
	        $result = $installer->install($path);
	        $status->plugins[] = array('name'=>$pname,'group'=>$pgroup, 'result'=>$result);
	        
	        if(!empty($this->is_new_installation))
	        {
		        $query = "UPDATE #__extensions SET enabled=1 WHERE type='plugin' AND element=".$db->Quote($pname)." AND folder=".$db->Quote($pgroup);
		        $db->setQuery($query);
		        $db->query();
		        if($pgroup == 'authentication') {
		        	// make sure that osticky auth plugin is the last one in order (for correct message display) - this is not the best solution, but it works
		        	$query = "SELECT MAX(ordering) FROM #__extensions WHERE type=".$db->Quote('plugin')." AND folder=".$db->Quote($pgroup)." AND element<>".$db->Quote($pname);
		        	$db->setQuery($query);
		        	$ordering = (int)$db->loadResult() + 1;
		        	$query = "UPDATE #__extensions SET ordering=$ordering WHERE type=".$db->Quote('plugin')." AND element=".$db->Quote($pname)." AND folder=".$db->Quote($pgroup);
		        	$db->setQuery($query);
		        	$db->query();
		        }
	        }
        }
        return $status;
    }

     private function _uninstallSubextensions($parent)
    {
        $status = new JObject();
        $status->plugins = array();

        $db = JFactory::getDBO();
        // -- UnInstall Plugins
        $where = array(
	        '(element = '.$db->Quote('osticky2').' AND folder = '.$db->Quote('system').')',
	        '(element = '.$db->Quote('osticket2').' AND folder = '.$db->Quote('authentication').')',
        	'(element = '.$db->Quote('osticketprofile2').' AND folder = '.$db->Quote('user').')',
	        '(element = '.$db->Quote('osticky2').' AND folder = '.$db->Quote('search').')'
        );
        $where = ' WHERE '.implode (' OR ', $where);

        $query   = 'SELECT `extension_id`, `element`, `folder` FROM #__extensions '.$where;
        $db->setQuery($query);
        $plugins = $db->loadObjectList();
        if (count($plugins)) {
	        foreach ($plugins as $plugin) {
	        	$installer = new JInstaller;
		        $result = $installer->uninstall('plugin', $plugin->extension_id, 0);
		        $status->plugins[] = array('name'=>$plugin->element,'group'=>$plugin->folder, 'result'=>$result);
	        }
        }
        return $status;
    }

    private function _renderPostInstallation($status, $parent)
    {
        $rows = 0;
        // Load language file
        $lang = JFactory::getLanguage();
        $lang->load('com_osticky2');
?>
<h2>
	<?php echo JText::_('COM_OSTICKY_INSTALLATION_STATUS'); ?>
</h2>
<table class="adminlist table table-striped" width="100%">
	<thead>
		<tr>
			<th class="title" colspan="2"><?php echo 'Extension'; ?></th>
			<th width="30%"><?php echo JText::_('JSTATUS'); ?></th>
		</tr>
	</thead>
	<tfoot>
		<tr>
			<td colspan="3"></td>
		</tr>
	</tfoot>
	<tbody>
		<tr class="row0">
			<td class="key" colspan="2"><?php echo JText::_('COM_OSTICKY2').' Component'; ?>
			</td>
			<td><strong><?php echo 'Installed'; ?> </strong></td>
		</tr>
		<?php if (count($status->plugins)) : ?>
		<tr>
			<th><?php echo JText::_('COM_INSTALLER_TYPE_PLUGIN'); ?></th>
			<th><?php echo 'Group'; ?></th>
			<th></th>
		</tr>
		<?php foreach ($status->plugins as $plugin) : ?>
		<tr class="row<?php echo (++ $rows % 2); ?>">
			<td class="key"><?php echo ucfirst($plugin['name']); ?></td>
			<td class="key"><?php echo ucfirst($plugin['group']); ?></td>
			<td><strong><?php echo ($plugin['result']) ? 'Installed' : 'Not installed'; ?>
			</strong></td>
		</tr>
		<?php endforeach; ?>
		<?php endif; ?>
	</tbody>
</table>

<?php
    }

    private function _renderPostUninstallation($status, $parent)
    {
        $rows = 0;
?>
<h2>
	<?php echo JText::_('COM_OSTICKY_UNINSTALLATION_STATUS'); ?>
</h2>
<table class="adminlist table table-striped" width="100%">
	<thead>
		<tr>
			<th class="title" colspan="2"><?php echo 'Extension'; ?></th>
			<th width="30%"><?php echo JText::_('JSTATUS'); ?></th>
		</tr>
	</thead>
	<tfoot>
		<tr>
			<td colspan="3"></td>
		</tr>
	</tfoot>
	<tbody>
		<tr class="row0">
			<td class="key" colspan="2"><?php echo JText::_('COM_OSTICKY2').' Component'; ?>
			</td>
			<td><strong><?php echo 'Removed'; ?> </strong></td>
		</tr>
		<?php if (count($status->plugins)) : ?>
		<tr>
			<th><?php echo JText::_('COM_INSTALLER_TYPE_PLUGIN'); ?></th>
			<th><?php echo 'Group'; ?></th>
			<th></th>
		</tr>
		<?php foreach ($status->plugins as $plugin) : ?>
		<tr class="row<?php echo (++ $rows % 2); ?>">
			<td class="key"><?php echo ucfirst($plugin['name']); ?></td>
			<td class="key"><?php echo ucfirst($plugin['group']); ?></td>
			<td><strong><?php echo ($plugin['result']) ? 'Removed' : 'Not removed'; ?>
			</strong></td>
		</tr>
		<?php endforeach; ?>
		<?php endif; ?>
	</tbody>
</table>
<?php
    }
}
