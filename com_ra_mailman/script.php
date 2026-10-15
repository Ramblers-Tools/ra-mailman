<?php

/*
 * Installation script
 * 29/04/25 CB getDbVersion and getVersion
 * 14/06/25 CB add link to dashboard
 * 31/07/25 CB this->version_required
 * 09/08/25 CB ra_mail_lists / emails_outstanding
 * 06/04/26 CB add mail_list/description
 * 15/08/26 CB add send_after and is_scheduled to ra_mail_shots
 * 05/09/26 CB remove JFactory, obtain DatabaseInterface from Joomla, fix deleteFolder
 *             add mail_shots / reply_to
 * 22/08/26 CB depencies on tool plugins
 * 15/10/26 RH reinstate in manifest (moved back to component root so Joomla can copy it),
 *             report progress via enqueueMessage rather than echo,
 *             remove plugin checks (the package installs them),
 *             postflight ensures ra_mail_shots.contact_id exists on every install/update
 *             and back-fills it from the legacy reply_to column if present
 */
\defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Filesystem\Folder;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\Database\DatabaseInterface;

class Com_Ra_mailmanInstallerScript {

    private $minimumJoomlaVersion = '4.2';
    private $minimumPHPVersion = JOOMLA_MINIMUM_PHP;
    private $minimumToolsVersion = '4.0.0';
    private $reconfigure_message;
    private $current_version;
    private $version_required;
    private $dbPrefix;
    private $db;

    private function message(string $message, string $type = 'message'): void {
        Factory::getApplication()->enqueueMessage($message, $type);
    }

    function buildButton($url, $text, $newWindow = 0, $colour = '') {
        if ($colour == '') {
            $colour = 'sunrise';
        }
        $class = 'link-button ' . $colour;
        $q = chr(34);
        $out = "<a class=" . $q . $class . $q;
        $out .= " href=" . $q . $url . $q;
        $out .= " target =" . $q . "_self" . $q;
        $out .= ">";
        $out .= $text;
        $out .= "</a>";
        return $out;
    }

    function checkColumn($table, $column, $mode, $details = '') {
//  $mode = A: add the field, using data supplied in $details
//  $mode = U: update the field (keeping name the same), using $details
//  $mode = D: delete the field
//  Safe to re-run: checks information_schema first

        $count = $this->checkColumnExists($table, $column);
        $table_name = $this->dbPrefix . $table;
        if (($mode == 'A') AND ($count == 1)
                OR ($mode == 'D') AND ($count == 0)) {
            return true;
        }
        if (($mode == 'U') AND ($count == 0)) {
            return $this->fail('Installer could not update missing field ' . $table_name . '.' . $column . '.');
        }

        $sql = 'ALTER TABLE ' . $table_name . ' ';
        if ($mode == 'A') {
            $sql .= 'ADD ' . $column . ' ';
            $sql .= $details;
        } elseif ($mode == 'D') {
            $sql .= 'DROP ' . $column;
        } elseif ($mode == 'U') {
            $sql .= 'CHANGE ' . $column . ' ' . $column . ' ';
            $sql .= $details;
        }
        $this->message($sql);
        try {
            $this->executeCommand($sql);
        } catch (\RuntimeException $exception) {
            return $this->fail('Installer failed to alter database table ' . $table_name . ': ' . $exception->getMessage());
        }

        $this->message('Success for ' . $table_name . '.' . $column);
        return true;
    }

    private function checkColumnExists($table, $column) {
        $config = Factory::getApplication()->getConfig();
        $database = $config->get('db');
        $this->dbPrefix = $config->get('dbprefix');

        $sql = 'SELECT COUNT(COLUMN_NAME) ';
        $sql .= "FROM information_schema.COLUMNS ";
        $sql .= "WHERE TABLE_SCHEMA='" . $database . "' AND TABLE_NAME ='" . $this->dbPrefix . $table . "' ";
        $sql .= "AND COLUMN_NAME='" . $column . "'";

        return (int) $this->getValue($sql);
    }

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
            $this->message('Unable to delete ' . $file . ': file not found');
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
            $this->message('Unable to delete ' . $folder . ': folder not found');
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
        $sql = 'SELECT s.version_id ';
        $sql .= 'FROM #__extensions as e ';
        $sql .= 'LEFT JOIN #__schemas AS s ON s.extension_id = e.extension_id ';
        $sql .= 'WHERE e.element="' . $component . '"';
        return $this->getValue($sql);
    }

    public function getVersion($component = 'com_ra_mailman') {
        // This returns the version as displayed by System / Manage extensions
        $sql = 'SELECT manifest_cache ';
        $sql .= 'FROM  #__extensions  ';
        $sql .= 'WHERE element="' . $component . '"';
        $data = json_decode((string) $this->getValue($sql));

        return (is_object($data) && isset($data->version)) ? (string) $data->version : null;
    }

    private function getValue($sql) {
        $db = $this->getDatabase();
        $db->setQuery($sql);
        return $db->loadResult();
    }

    public function install($parent): bool {
        $this->message('Installing RA MailMan (com_ra_mailman)');

        return true;
    }

    public function red($text) {
        $this->message($text, 'warning');
    }

    public function uninstall($parent): bool {
        $this->message('Uninstalling RA MailMan (com_ra_mailman), version '
                . ($this->getVersion() ?? 'not recorded')
                . ', database version ' . ($this->getDbVersion() ?: 'not recorded'));

        return true;
    }

    public function update($parent): bool {
        $this->message('Updating RA MailMan (com_ra_mailman)');

        return true;
    }

    public function postflight($type, $parent) {
        $this->message('Postflight RA MailMan (com_ra_mailman)');

        if ($type == 'uninstall') {
            return true;
        }

        // Runs on every install/update and is safe to re-run. The 5.1.2 update SQL also
        // adds contact_id, but this covers sites where that file did not run.
        if ($this->checkColumn('ra_mail_shots', 'contact_id', 'A', 'INT NULL AFTER attachment; ')) {
            // Older versions stored the contact's email in reply_to; carry it across so
            // existing mailshots keep the contact that was chosen
            if ($this->checkColumnExists('ra_mail_shots', 'reply_to')) {
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

        $this->message('com_ra_mailman is now at ' . $this->getVersion());
        if ($this->reconfigure_message == true) {
            $this->red('Please review and update the configuration settings for com_ra_mailman.');
        }

        $this->message('<b>Useful links</b><br>'
                . $this->buildButton('index.php?option=com_ra_tools&view=dashboard', 'Dashboard', false, 'granite') . '<br>'
                . $this->buildButton('index.php?option=com_config&view=component&component=com_ra_mailman', 'Configure'));
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
        if (!empty($this->minimumJoomlaVersion) && version_compare(JVERSION, $this->minimumJoomlaVersion, '<')) {
            return $this->fail(Text::sprintf('JLIB_INSTALLER_MINIMUM_JOOMLA', $this->minimumJoomlaVersion));
        }
        if (!ComponentHelper::isEnabled('com_ra_tools', true)) {
            return $this->fail('RA Mailman requires the enabled component com_ra_tools.');
        }

        $tools_version = $this->getVersion('com_ra_tools');
        if ($tools_version !== null && version_compare($tools_version, $this->minimumToolsVersion, 'ge')) {
            $this->message('Version ' . $this->minimumToolsVersion . ' of com_ra_tools required; version ' . $tools_version . ' found');
        } else {
            return $this->fail('RA Mailman requires com_ra_tools version ' . $this->minimumToolsVersion
                    . ' or later; found ' . ($tools_version ?: 'no readable version') . '.');
        }
        if ($type == 'install') {
            return true;
        }

        $this->current_version = $this->getVersion();

        if ($this->current_version === null) {
            return $this->fail('Unable to determine the currently installed com_ra_mailman version.');
        }

        $this->message('com_ra_mailman already present, version ' . $this->current_version
                . ', database version ' . ($this->getDbVersion() ?: 'not recorded'));

        $this->version_required = '5.0.16';
        if (version_compare($this->current_version, '5.0.14', 'le')) {
            if (!$this->checkColumn('ra_mail_shots', 'send_after', 'A', 'DATETIME NULL AFTER processing_started; ')
                    || !$this->checkColumn('ra_mail_shots', 'is_scheduled', 'A', 'TINYINT NULL AFTER send_after; ')) {
                $this->message('Unable to update ra_mail_shots: 5.0.14', 'warning');
            }
        }

        if (version_compare($this->current_version, $this->version_required, 'ge')) {
            $this->message('Current version is ' . $this->current_version . ', no additional processing required');
            return true;
        } else {
            $this->message('Version is currently ' . $this->current_version . ', '
                    . 'Requires version >= ' . $this->version_required, 'warning');
        }
        if (version_compare($this->current_version, '4.7.0', 'le')) {
            if (!$this->checkColumn('ra_mail_lists', 'description', 'A', 'VARCHAR(512) DEFAULT "" AFTER name; ')) {
                return false;
            }
            if (!$this->checkColumn('ra_mail_shots', 'record_type', 'A', 'VARCHAR(1) DEFAULT "M" AFTER id; ')
                    || !$this->checkColumn('ra_mail_shots', 'mail_list_id', 'U', 'INT NULL; ')
                    || !$this->checkColumn('ra_mail_shots', 'event_id', 'A', 'INT NULL AFTER mail_list_id; ')) {
                $this->message('Unable to update ra_mail_shots: 4.7.0', 'warning');
            }
        }
        if (version_compare($this->current_version, '4.6.0', 'le')) {
            $this->message('Deleting redundant view profile');
            $this->deleteView('Profile');
        }
        if (version_compare($this->current_version, '4.5.0', 'le')) {
            if (!$this->checkColumn('ra_mail_lists', 'emails_outstanding', 'A', 'INT DEFAULT "0" AFTER footer; ')) {
                return false;
            }
        }
        return true;
    }

}
