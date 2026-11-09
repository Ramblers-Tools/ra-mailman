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
 * 03/10/26 CB use PluginHelp for dependencies (not ComponentHelper)
 * 04/10/16 CB correct syntax for plugin checks
 * 21/10/26 CB remove dependecy checks (moved to package); remove echo statements
*/
\defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;

use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Folder;

class Com_Ra_mailmanInstallerScript {

    private $component;
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
        $this->message($sql);
        $response = $this->executeCommand($sql);
        if (!$response) {
            return $this->fail('Installer failed to alter database table ' . $table_name . '.');
        }

        $this->message('Success for ' . $table_name);
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
        $this->message('Seeking ' . $table_name . ', count=' . $count);
        if ($count > 0) {
            return $count;
        }
        $sql = 'CREATE TABLE ' . $table_name . ' ' . $details;
        $this->message($sql);
        $response = $this->executeCommand($sql);
        if ($response) {
            $this->message('Table created OK');
        } else {
            return $this->fail('Installer failed to create database table ' . $table_name . '.');
        }
        if ($details2 != '') {
            $sql = 'ALTER TABLE ' . $table_name . ' ' . $details2;
            $response = $this->executeCommand($sql);
            if ($response) {
                $this->message('Table altered OK');
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
            $this->message('File ' . $file . ' found,');
            File::delete($file);
            if (file_exists($file)) {
                $this->message(' but unable to delete', 'warning');
            } else {
                $this->message(' deleted');
            }
        } else {
            $this->message("Unable to delete $file: file not found", 'warning');
        }
    }

    private function deleteFolder($target) {
// created 08/10/24 - does not seem to work
        $folder = JPATH_ROOT . $target;
        if (file_exists($folder)) {
            $this->message('Folder ' . $folder . ' found,');
            $deleted = Folder::delete($folder);
            if (!$deleted || file_exists($folder)) {
                $this->message(' but unable to delete', 'warning');
            } else {
                $this->message(' deleted');
            }
        } else {
            $this->message('Unable to delete ' . $folder . ': folder not found', 'warning');
        }
    }

    public function deleteView($view, $application = '') {
// first character of View must be upper case
        $component = 'com_ra_mailman';
        $this->message('Deleting ');
        if ($application == '') {
            $this->message('Site ');
        } else {
            $this->message($application . ' ');
        }
        $this->message('files');

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

    private function message($message, $type='info'){
        Factory::getApplication()->enqueueMessage($message, $type);
    }

    public function postflight($type, $parent) {
        $this->message('Postflight RA MailMan (com_ra_mailman)');

        if ($type == 'uninstall') {
            return true;
        }
        return true;
    }

    public function preflight($type, $parent): bool {
        $this->message('Preflight RA MailMan (type=' . $type . ')');
        if ($type == 'uninstall') {
            return true;
        }
 
        if ($type == 'install') {
            return true;
        }

        $this->current_version = $this->getVersion();

        if ($this->current_version === null) {
            $this->message('Unable to determine the currently installed com_ra_mailman version.','warning');
        } else {
            $this->message('com_ra_mailman already present, version ' . $this->current_version
                . ', database version ' . ($this->getDbVersion() ?: 'not recorded'));
        }

        $this->version_required = '5.1.3';

        if (version_compare($this->current_version, '5.1.3', 'le')) {
            $this->checkColumn('ra_profiles', 'membershipNo', 'A', 'VARCHAR(64) NULL DEFAULT "" AFTER home_group; ');

        }       
        return true;
    }

}
