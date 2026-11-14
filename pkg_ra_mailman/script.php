<?php

/*
 * Installation script
 * 18/10/26 CB Created from com_ra_mailman script
 *             Deleted schema updates
 * 21/10/26 CB Updated minimumToolsVersion to 4.0.14
 * 05/11/26 CB Added Schemahelper and ToolsHelper
 */
\defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Folder;
use Ramblers\Component\Ra_tools\Site\Helpers\SchemaHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

class Pkg_Ra_mailmanInstallerScript {

    private $db;   
    private $dbVersionCache = [];
    private $minimumJoomlaVersion = '5.6';
    private $minimumPHPVersion = JOOMLA_MINIMUM_PHP;
    private $minimumPackagePHPVersion = '8.3.36';
    private $minimumToolsVersion = '4.1.2';
    private $reconfigure_message;
    private $current_version;   
    private $schemaHelper; 
    private $toolsHelper;
    private $version_required;  
    private $versionCache = [];

    private function deleteFile($target) {
        $file = JPATH_ROOT . $target;
        if (file_exists($file)) {
            File::delete($file);
            if (file_exists($file)) {
                $this->message('File ' . $file . ' found, but unable to delete', 'warning');
            } else {
                $this->message('File ' . $file . ' deleted');
            }
        } else {
            $this->message('Unable to delete ' . $file . ': file not found','warning');
        }
    }

    private function deleteFolder($target) {
        $folder = JPATH_ROOT . $target;
        if (file_exists($folder)) {
            $deleted = Folder::delete($folder);
            if (!$deleted || file_exists($folder)) {
                $this->message('Folder ' . $folder . ' found, but unable to delete', 'warning');
            } else {
                $this->message('Folder ' . $folder . ' deleted');
            }
        } else {
            $this->message('Unable to delete ' . $folder . ': folder not found', 'warning');
        }
    }

    public function deleteView($view, $application = '') {
// first character of View must be upper case
        $component = 'com_ra_mailman';
        $this->message('Deleting ' . ($application == '' ? 'Site' : $application) . ' files for view ' . $view);

        $this->deleteFile($application . '/components/' . $component . '/forms/filter_' . strtolower($view) . '.xml');
        $this->deleteFile($application . '/components/' . $component . '/src/Controller/' . $view . 'Controller.php');
        $this->deleteFile($application . '/components/' . $component . '/src/Model/' . $view . 'Model.php');
        $this->deleteFile($application . '/components/' . $component . '/src/table/' . $view . 'Table.php');
        $this->deleteFolder($application . '/components/' . $component . '/src/View/' . $view);
        $this->deleteFolder($application . '/components/' . $component . '/tmpl/' . strtolower($view));
    }

    private function getDatabase(): DatabaseInterface {
        if ($this->db === null) {
            $this->db = Factory::getContainer()->get(DatabaseInterface::class);
        }

        return $this->db;
    }

    private function executeCommand($sql) {
        $db = $this->getDatabase();
        $db->setQuery($sql);
        return $db->execute();
    }

    private function fail(string $message): bool {
        $this->message($message, 'error');
        Log::add($message, Log::ERROR, 'jerror');

        return false;
    }

    public function getDbVersion($component = 'com_ra_mailman') {
        if (array_key_exists($component, $this->dbVersionCache)) {
            return $this->dbVersionCache[$component];
        }

        $sql = 'SELECT s.version_id ';
        $sql .= 'FROM #__extensions as e ';
        $sql .= 'LEFT JOIN #__schemas AS s ON s.extension_id = e.extension_id ';
        $sql .= 'WHERE e.element="' . $component . '"';
        $this->dbVersionCache[$component] = $this->getValue($sql);

        return $this->dbVersionCache[$component];
    }

    public function getVersion($component = 'com_ra_mailman') {
        if (array_key_exists($component, $this->versionCache)) {
            return $this->versionCache[$component];
        }

        // This returns the version as displayed by System / Manage extensions
        $sql = 'SELECT manifest_cache ';
        $sql .= 'FROM  #__extensions  ';
        $sql .= 'WHERE element="' . $component . '"';
        $data = json_decode((string) $this->getValue($sql));

        $this->versionCache[$component] = (is_object($data) && isset($data->version)) ? (string) $data->version : null;

        return $this->versionCache[$component];
    }

    private function getValue($sql) {
        $db = $this->getDatabase();
        $db->setQuery($sql);
        return $db->loadResult();
    }

    public function install($parent): bool {
        $this->message('Installing RA MailMan (pkg_ra_mailman)');

        return true;
    }

    private function message(string $message, string $type = 'message'): void {
        Factory::getApplication()->enqueueMessage($message, $type);
    }
    
    public function red($text) {
        echo '<p><span style="color: #ff0000;"><strong>';
        echo $text;
        echo '</strong></span></p>';
    }

    public function uninstall($parent): bool {
        $componentVersion = $this->getVersion('com_ra_mailman') ?? 'not recorded';
        $componentDbVersion = $this->getDbVersion('com_ra_mailman') ?: 'not recorded';
        $this->message('Uninstalling RA MailMan package (pkg_ra_mailman); '
            . 'component com_ra_mailman version ' . $componentVersion
            . ', component database version ' . $componentDbVersion);

        return true;
    }

    public function update($parent): bool {
        $this->message('Updating RA MailMan (pkg_ra_mailman)');

        return true;
    }

    public function postflight($type, $parent) {
        $this->message('Postflight RA MailMan (pkg_ra_mailman)');

        if ($type == 'uninstall') {
            return true;
        }

        // Runs on every install/update and is safe to re-run. The 5.1.2 update SQL also
        // adds contact_id, but this covers sites where that file did not run.
        if ($this->schemaHelper->checkColumn('ra_mail_shots', 'contact_id', 'A', 'INT NULL AFTER attachment; ')) {
            // Older versions stored the contact's email in reply_to; carry it across so
            // existing mailshots keep the contact that was chosen
            if ($this->schemaHelper->checkColumnExists('ra_mail_shots', 'reply_to')) {
                try {
                    $this->executeCommand('UPDATE `#__ra_mail_shots` AS s '
                            . 'INNER JOIN `#__users` AS u ON u.email = s.reply_to '
                            . "SET s.contact_id = u.id WHERE s.contact_id IS NULL AND s.reply_to <> ''");
                } catch (\RuntimeException $exception) {
                    $this->message('Unable to copy reply_to into contact_id: ' . $exception->getMessage(), 'warning');
                }
            }
        } else {
            $this->message('Mailshot Contact override will not be saved until ra_mail_shots.contact_id is added', 'warning');
        }
//        if (version_compare($this->original_version, '5.1.7', 'le')) {
//            $this->stoolsHelper->checkUsergroup( 'com_ra_members',);
//        }
        $componentVersion = $this->getVersion('com_ra_mailman') ?? 'not recorded';
        echo 'com_ra_mailman is now at ' . $componentVersion;
        if ($this->reconfigure_message == true) {
            $this->red('Please review and update the configuration settings for com_ra_mailman.');
        }

        echo '<b>Useful links</b><br>'
                . $this->toolsHelper->buildButton('index.php?option=com_ra_tools&view=dashboard', 'Dashboard', false, 'granite') . '<br>'
                . $this->toolsHelper->buildButton('index.php?option=com_config&view=component&component=com_ra_mailman', 'Configure');
        return true;
    }

    public function preflight($type, $parent): bool {
        $this->message('Preflight RA MailMan (type=' . $type . ')');
        if ($type == 'uninstall') {
            return true;
        }
        if (!empty($this->minimumPHPVersion) && version_compare(PHP_VERSION, $this->minimumPHPVersion, '<')) {
            return $this->fail(Text::sprintf('JLIB_INSTALLER_MINIMUM_PHP', $this->minimumPHPVersion));
        }
        if (version_compare(PHP_VERSION, $this->minimumPackagePHPVersion, '<')) {
            $this->message('RA MailMan is recommended for PHP ' . $this->minimumPackagePHPVersion . ' or later; this site is running PHP ' . PHP_VERSION . '.', 'warning');
        }
        if (!empty($this->minimumJoomlaVersion) && version_compare(JVERSION, $this->minimumJoomlaVersion, '<')) {
            return $this->fail(Text::sprintf('JLIB_INSTALLER_MINIMUM_JOOMLA', $this->minimumJoomlaVersion));
        }
        if (!ComponentHelper::isEnabled('com_ra_tools', true)) {
            return $this->fail('RA Mailman requires the enabled component com_ra_tools.');
        }
        $this->toolsHelper = new ToolsHelper();
        $this->schemaHelper = new SchemaHelper;
        $tools_version = $this->getVersion('com_ra_tools');
        if ($tools_version !== null && version_compare($tools_version, $this->minimumToolsVersion, 'ge')) {
            $this->message('Version ' . $this->minimumToolsVersion . ' of com_ra_tools required; version ' . $tools_version . ' found');
        } else {
            return $this->fail('RA Mailman requires com_ra_tools version ' . $this->minimumToolsVersion
                    . ' or later; found ' . ($tools_version ?: 'no readable version') . '.');
        }
       if (!PluginHelper::isEnabled('user', 'ra_profiles')) {
            $this->message('This version of RA MailMan requires the enabled plugin plg_user_ra_profiles.','warning');
//            return $this->fail('This version of RA MailMan requires the enabled plugin plg_user_ra_profiles.');
            
        }
        if (!PluginHelper::isEnabled('system', 'ra_tools', true)) {
            $this->message('This version of RA MailMan requires the enabled plugin plg_system_ra_tools.', 'warning');
//            return $this->fail('This version of RA MailMan requires the enabled plugin plg_system_ra_tools.');
        }
         
        if ($type == 'install') {
            return true;
        }

        $this->current_version = $this->getVersion('com_ra_mailman');
        $currentDbVersion = $this->getDbVersion('com_ra_mailman') ?: 'not recorded';

        if ($this->current_version === null) {
            return $this->fail('Unable to determine the currently installed com_ra_mailman version.');
        }

        $this->message('com_ra_mailman already present, version ' . $this->current_version
            . ', database version ' . $currentDbVersion);

        $this->version_required = '5.0.16';


        if (version_compare($this->current_version, $this->version_required, 'ge')) {
            $this->message('Current version is ' . $this->current_version . ', no additional processing required');
            return true;
        } else {
            $this->message('Version is currently ' . $this->current_version . ', '
                    . 'Requires version >= ' . $this->version_required, 'warning');
        }

        return true;
    }

}