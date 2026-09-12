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
*/
\defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Filesystem\Folder;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;

class Com_Ra_mailmanInstallerScript {

    private $component;
    private $minimumJoomlaVersion = '4.0';
    private $minimumPHPVersion = JOOMLA_MINIMUM_PHP;
    private $reconfigure_message;
    private $required_version;
    private $current_version;
    private $original_version;
    private $version_required;
    private $dbPrefix;
    private $db;

    function buildButton($url, $text, $newWindow = 0, $colour = '') {
        if ($colour == '') {
            $colour = 'sunrise';
        }
        $class = 'link-button ' . $colour;
        //       echo "colour=$colour, code=$code, class=$class<br>";
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

        $count = $this->checkColumnExists($table, $column);
        $table_name = $this->dbPrefix . $table;
//        echo 'mode=' . $mode . ': Seeking ' . $table_name . '/' . $column . ', count=' . $count . "<br>";
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
        echo "$sql<br>";
        $response = $this->executeCommand($sql);
        if (!$response) {
            return $this->fail('Installer failed to alter database table ' . $table_name . '.');
        }

        echo 'Success for ' . $table_name . '<br>';
        return true;
    }

    private function checkColumnExists($table, $column) {
        $config = Factory::getApplication()->getConfig();
        $database = $config->get('db');
        $this->dbPrefix = $config->get('dbprefix');

        $table_name = $this->dbPrefix . $table;
        $sql = 'SELECT COUNT(COLUMN_NAME) ';
        $sql .= "FROM information_schema.COLUMNS ";
        $sql .= "WHERE TABLE_SCHEMA='" . $database . "' AND TABLE_NAME ='" . $this->dbPrefix . $table . "' ";
        $sql .= "AND COLUMN_NAME='" . $column . "'";
//    echo "$sql<br>";

        return $this->getValue($sql);
    }

    function checkTools() {
        echo 'Checking version of com_ra_tools<br>';
        if (ComponentHelper::isEnabled('com_ra_tools', true)) {
            $tools_versions = $this->getVersions('com_ra_tools');

            if ($tools_versions === false) {
                return false;
            }

            echo '<p>com_ra_tools is currently at version ' . $tools_versions->component;
            echo ', database version ' . $tools_versions->db_version . '</p>';
            if (version_compare($tools_versions->component, '4.0.0', 'ge')) {
                echo 'Version 4.0.0 or later, OK<br>';
                return true;
            } else {
                return $this->fail('This operation requires com_ra_tools version 4.0.0 or later.');
            }
        } else {
            return $this->fail('This operation requires the enabled component com_ra_tools.');
        }
        return true;
    }

    function checkTable($table, $details, $details2 = '') {

        $config = Factory::getApplication()->getConfig();
        $database = $config->get('db');
        $this->dbPrefix = $config->get('dbprefix');

        $table_name = $this->dbPrefix . $table;
        $sql = 'SELECT COUNT(COLUMN_NAME) ';
        $sql .= "FROM information_schema.COLUMNS ";
        $sql .= "WHERE TABLE_SCHEMA='" . $database . "' AND TABLE_NAME ='" . $table_name . "' ";
//        echo "$sql<br>";

        $count = $this->getValue($sql);
        echo 'Seeking ' . $table_name . ', count=' . $count . "<br>";
        if ($count > 0) {
            return $count;
        }
        $sql = 'CREATE TABLE ' . $table_name . ' ' . $details;
        echo "$sql<br>";
        $response = $this->executeCommand($sql);
        if ($response) {
            echo 'Table created OK<br>';
        } else {
            return $this->fail('Installer failed to create database table ' . $table_name . '.');
        }
        if ($details2 != '') {
            $sql = 'ALTER TABLE ' . $table_name . ' ' . $details2;
            $response = $this->executeCommand($sql);
            if ($response) {
                echo 'Table altered OK<br>';
            } else {
                return $this->fail('Installer failed to alter database table ' . $table_name . '.');
            }
        }

        return true;
    }

    private function deleteFile($target) {
// Not needed, could use a built in function (if details were known!)
        $file = JPATH_ROOT . $target;
        if (file_exists($file)) {
            echo 'File ' . $file . ' found,';
            File::delete($file);
            if (file_exists($file)) {
                echo ' but unable to delete<br>';
            } else {
                echo ' deleted<br>';
            }
        } else {
            echo "Unable to delete $file: file not found<br>";
        }
    }

    private function deleteFolder($target) {
// created 08/10/24 - does not seem to work
        $folder = JPATH_ROOT . $target;
        if (file_exists($folder)) {
            echo 'Folder ' . $folder . ' found,';
            $deleted = Folder::delete($folder);
            if (!$deleted || file_exists($folder)) {
                echo ' but unable to delete<br>';
            } else {
                echo ' deleted<br>';
            }
        } else {
            echo 'Unable to delete ' . $folder . ': folder not found<br>';
        }
    }

    public function deleteView($view, $application = '') {
// first character of View must be upper case
        $component = 'com_ra_mailman';
        echo 'Deleting ';
        if ($application == '') {
            echo 'Site ';
        } else {
            echo $application . ' ';
        }
        echo 'files<br>';

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
        Factory::getApplication()->enqueueMessage($message, 'error');
        Log::add($message, Log::ERROR, 'jerror');

        return false;
    }

    public function getDatabaseVersion($component = 'com_ra_mailman') {
        return $this->getDbVersion($component);
    }

    public function getDbVersion($component = 'com_ra_mailman') {
        $sql = 'SELECT s.version_id ';
        $sql .= 'FROM #__extensions as e ';
        $sql .= 'LEFT JOIN #__schemas AS s ON s.extension_id = e.extension_id ';
        $sql .= 'WHERE e.element="' . $component . '"';
        return $this->getValue($sql);
    }

    public function getVersion($component = 'com_ra_mailman') {
        // This retuns the version as display by System / Manage extensions
        $sql = 'SELECT manifest_cache ';
        $sql .= 'FROM  #__extensions  ';
        $sql .= 'WHERE element="' . $component . '"';
        $data = json_decode((string) $this->getValue($sql));

        return (is_object($data) && isset($data->version)) ? (string) $data->version : null;
    }

    /**
     *     returns details of the component version and the database version
     *
     * @return  CMSObject
     *
     */
    public function getVersions($component = 'com_ra_mailman') {
        // Returns an object with two values:
        //  ->component
        //  ->db_version
        $versions = new \stdClass;
        $sql = 'SELECT e.manifest_cache, s.version_id AS db_version ';
        $sql .= 'FROM #__extensions as e ';
        $sql .= 'LEFT JOIN #__schemas AS s ON s.extension_id = e.extension_id ';
        $sql .= 'WHERE element="' . $component . '"';

        $db = $this->getDatabase();
        $query = $db->getQuery(true);
        $db->setQuery($sql);
        $db->execute();
        $item = $db->loadObject();
        if ($item == false) {
            $this->fail('Installer could not find version information for ' . $component . '.');
            return false;
        } else {
            $values = json_decode((string) $item->manifest_cache);

            if (!is_object($values) || !isset($values->version)) {
                $this->fail('Installer found invalid version information for ' . $component . '.');
                return false;
            }

            $versions->component = $values->version;
            $versions->db_version = $item->db_version;
        }

        return $versions;
    }

    /**
     * Loads the ID of the extension from the database
     *
     * @return mixed
     */
    public function getExtensionId($component = 'com_ra_mailman') {
        $db = $this->getDatabase();

        $query = $db->getQuery(true);
        $query->select('extension_id')
                ->from('#__extensions')
                ->where($db->qn('element') . ' = ' . $db->q($component) . ' AND type=' . $db->q('component'));
        $db->setQuery($query);
        $eid = $db->loadResult();
//        echo $db->replacePrefix($query) . '<br>';
        return $eid;
    }

    private function getValue($sql) {
        $db = $this->getDatabase();
        $db->setQuery($sql);
        return $db->loadResult();
    }

    public function install($parent): bool {
        Factory::getApplication()->enqueueMessage('Installing RA MailMan (com_ra_mailman)', 'info');

        return true;
    }

    public function red($text) {
        echo '<p><span style="color: #ff0000;"><strong>';
        echo $text;
        echo '</strong></span></p>';
    }

    public function uninstall($parent): bool {
        echo '<p>Uninstalling RA MailMan (com_ra_mailman)<br>';
        $versions = $this->getVersions();

        if ($versions !== false) {
            echo '<p>Version ' . $versions->component;
            echo ', database version ' . $versions->db_version . '</p>';
        }

        return true;
    }

    public function update($parent): bool {
        Factory::getApplication()->enqueueMessage('Updating RA MailMan (com_ra_mailman)', 'info');

// Runs on every update regardless of current_version,
// checkColumn() checks information_schema first, so this is safe to run whether or
// not the column already exists
        if (!$this->checkColumn('ra_mail_shots', 'reply_to', 'A', 'VARCHAR(255) NULL AFTER date_sent; ')) {
            return false;
        }

// You can have the backend jump directly to the newly updated component configuration page
// $parent->getParent()->setRedirectURL('index.php?option=com_ra_mailman');
        return true;
    }

    public function postflight($type, $parent) {
        Factory::getApplication()->enqueueMessage('Postflight RA MailMan (com_ra_mailman)', 'info');

        if ($type == 'uninstall') {
            return true;
        }
        echo '<p>com_ra_mailman is now at ' . $this->getVersion() . '</p>';
        if ($this->reconfigure_message == true) {
            $this->red('Please review and update the configuration settings for com_ra_mailman.');
        }

        echo '<b>Useful links</b><br>';
        echo $this->buildButton('index.php?option=com_ra_tools&view=dashboard', 'Dashboard', false,'granite') . '<br>';
        echo $this->buildButton('index.php?option=com_config&view=component&component=com_ra_mailman', 'Configure');
        return true;
    }

    public function preflight($type, $parent): bool {
        Factory::getApplication()->enqueueMessage('Preflight RA MailMan (type=' . $type . ')', 'info');
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

        $tools_required = '4.0.0';
        $tools_version = $this->getVersion('com_ra_tools');
        Factory::getApplication()->enqueueMessage('Version ' . $tools_required . ' of com_ra_tools required', 'info');
        if (version_compare($tools_version, $tools_required, 'ge')) {
            Factory::getApplication()->enqueueMessage('Version ' . $tools_version . ' of com_ra_tools found', 'info');
        } else {
            return $this->fail('RA Mailman requires com_ra_tools version ' . $tools_required
                    . ' or later; found ' . ($tools_version ?: 'no readable version') . '.');
        }

        if ($type == 'install') {
            return true;
        }

        $this->current_version = $this->getVersion();

        if ($this->current_version === null) {
            return $this->fail('Unable to determine the currently installed com_ra_mailman version.');
        }

        Factory::getApplication()->enqueueMessage(
                'com_ra_mailman already present, version ' . $this->current_version
                . ', database version ' . ($this->getDbVersion() ?: 'not recorded'),
                'info'
        );

        $this->version_required = '5.0.16';
        if (version_compare($this->current_version, '5.0.14', 'le')) {
            if (!$this->checkColumn('ra_mail_shots', 'send_after', 'A', 'DATETIME NULL AFTER processing_started; ')
                    || !$this->checkColumn('ra_mail_shots', 'is_scheduled', 'A', 'TINYINT NULL AFTER send_after; ')) {
                Factory::getApplication()->enqueueMessage('Unable to update ra_mail_shots: 5.0.14','warning');
            }
        }
        if (version_compare($this->current_version, '4.7.8', 'le')) {
            if (!$this->checkColumn('ra_mail_shots', 'reply_to', 'A', 'VARCHAR(255) NULL AFTER date_sent; ')
                    || !$this->checkColumn('ra_mail_shots', 'contact_id', 'A', 'INT NULL AFTER attachment; ')) {
                Factory::getApplication()->enqueueMessage('Unable to update ra_mail_shots: 4.7.8','warning');
            }
        }

        if (version_compare($this->current_version, $this->version_required, 'ge')) {
            Factory::getApplication()->enqueueMessage('Current version is ' . $this->current_version . ', no additional processing required','info');
            return true;
        } else {
            Factory::getApplication()->enqueueMessage(
                'Version is currently ' . $this->current_version . ', '
                . 'Requires version >= ' . $this->version_required,
                'warning'
            );
        }
        if (version_compare($this->current_version, '4.7.0', 'le')) {
            if (!$this->checkColumn('ra_mail_lists', 'description', 'A', 'VARCHAR(512) DEFAULT "" AFTER name; ')) {
                return false;
            }
            if (!$this->checkColumn('ra_mail_shots', 'record_type', 'A', 'VARCHAR(1) DEFAULT "M" AFTER id; ')
                    || !$this->checkColumn('ra_mail_shots', 'mail_list_id', 'U', 'INT NULL; ')
                    || !$this->checkColumn('ra_mail_shots', 'event_id', 'A', 'INT NULL AFTER mail_list_id; ')) {
                Factory::getApplication()->enqueueMessage('Unable to update ra_mail_shots: 4.7.0','warning');
            }
            $sql = 'UPDATE `#__ra_mail_shots` SET `record_type`="M" ';
//          $this->executeCommand($sql);
        }
        if (version_compare($this->current_version, '4.6.0', 'le')) {
            echo 'Deleting redundant view profile<br>';
            $this->deleteView('Profile');
        }
        if (version_compare($this->current_version, '4.5.0', 'le')) {
            if (!$this->checkColumn('ra_mail_lists', 'emails_outstanding', 'A', 'INT DEFAULT "0" AFTER footer; ')) {
                return false;
            }
        }

        if (version_compare($this->current_version, '4.7.10', 'le')) {
            if (!$this->checkColumn('ra_mail_shots', 'reply_to', 'A', 'INT NULL DEFAULT NULL AFTER attachment; ')) {
                return false;
            }
        }
        return true;
    }

}
