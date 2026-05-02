<?php

/**
 * @version     4.7.0
 * @package     com_ra_mailman
 * @author      charlie
 *
 * @copyright   Copyright (C) 2005 - 2019 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * 12/06/23 CB add refreshAreas
 * 14/06/23 show group location
 * 10/07/23 CB add function cancel
 * 17/07/23 CB remove diagnostics
 * 17/09/23 CB add SW Scotland
 * 30/11/23 CB use Factory::getContainer()->get('DatabaseDriver');
 * 24/10/24 CB set up maximum time of 10 minutes
 * 08/02/25 CB switch off debug in refresh
 * 22/02/25 CB update clusters
 * 13/03/25 CB don't user Route after refreshing Areas
 * 02/04/25 CB correct redirect after refreshAreas
 * 17/04/25 CB download/upload
 * 21/04/25 CB abortive attempt to use JsonHelper, get API key from config
 * 18/08/25 CB get API key from ra_api_sites
 * 08/04/26 Claude Refactored from com_ra_tools
 */

namespace Ramblers\Component\Ra_mailman\Administrator\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Joomla\CMS\MVC\Controller\AdminController;
use Joomla\Database\DatabaseInterface;
use Ramblers\Component\Ra_tools\Site\Helpers\JsonHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\Input\Input;

class OrganisationsController extends AdminController
{
    protected $app;
    protected $dbPrefix;
    protected $toolsHelper;

    public function __construct(
        $config = [],
        MVCFactoryInterface $factory = null,
        CMSApplication $app = null,
        Input $input = null
    ) {
        parent::__construct($config, $factory, $app, $input);

        $this->app = Factory::getApplication();
        $this->dbPrefix = Factory::getDbo()->getPrefix();
        $this->toolsHelper = new ToolsHelper; 
        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
        $wa->registerAndUseStyle('ramblers', 'com_ra_mailman/ramblers.css');
       
    }

    public function cancel($key = null, $urlVar = null) {
        $this->setRedirect('index.php?option=com_ra_tools&view=dashboard&layout=mailman');
    }

    /**
     * Proxy for getModel.
     *
     * @param   string  $name    The model name. Optional.
     * @param   string  $prefix  The class prefix. Optional.
     * @param   array   $config  The array of possible config values. Optional.
     *
     * @return  \Joomla\CMS\MVC\Model\BaseDatabaseModel
     *
     * @since   1.6
     */
    public function getModel($name = 'Organisations', $prefix = 'Administrator', $config = array('ignore_request' => true)) {
        return parent::getModel($name, $prefix, $config);
    }

    public function download() {
        $area = $this->app->input->getCmd('area', 'NS');
        $area = 'NS';
        $filename = JPATH_ROOT . '/images/com_ra_mailman/groups.json';

        $sql = 'SELECT "A" AS scope, code, name, details, website ';
        $sql .= 'FROM #__ra_areas ';
        $sql .= 'WHERE code ="' . $area . '" ';
        $sql .= 'UNION ';

        $sql .= 'SELECT "G" AS scope, code, name, details, website ';
        $sql .= 'FROM #__ra_groups ';
        $sql .= 'WHERE code LIKE "' . $area . '%"';

        $json_data = $this->toolsHelper->getJson($sql);
        echo $json_data;
        echo '<br>';
        if (file_exists($filename)) {
            unlink($filename);
            return;
        }
        if (file_put_contents($filename, $json_data)) {
            $this->app->enqueueMessage('File ' . $filename . ' created', 'notice');
            echo '<a href="' . $filename . '" class="link-button" target="_blank">Download JSON file</a>';
        } else {
            $this->app->enqueueMessage('Unable to create ' . $filename, 'error');
        }
        if (file_exists($filename)) {
            $this->app->enqueueMessage('File ' . $filename . ' found', 'notice');
            echo 'OK<br>';
        }
    }

    public function refreshAreas() {
        $this->refreshAreasOrig();
        return; //<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<


        $db = Factory::getContainer()->get(DatabaseInterface::class);
        // set to 1 for debugging
        $debug = 0;
        // Read the Organisation feed, update tables #__ra_areas and #__ra_groups as required
        $area_count = 0;
        $area_insert = 0;
        $update_count = 0;
        $api_key = $this->toolsHelper->lookupApiKey();
        if ($api_key == '') {
            $message = 'API key not found - please create a record in API sites';
            Factory::getApplication()->enqueueMessage($message, 'error');
        }
        $feedurl = 'https://walks-manager.ramblers.org.uk/api/volunteers/groups?api-key=' . $api_key;
        echo '___orig: ' . $feedurl . '<br>';
        $display = 0;
        $jsonHelper = new JsonHelper;
        //      echo 'setUrl=' . $jsonHelper->setUrl('organisation', 'groups=NS') . '<br>';

        $area_list = $jsonHelper->getJson('organisation', '');
        var_dump($area_list);
        die;

        $sql_area = "SELECT id,code,name,details, website,co_url, latitude, longitude "
                . "FROM #__ra_areas WHERE code='";

        $record_count = 0;
        foreach ($area_list as $item) {
            $record_count++;
            $display = 0;

            if ($item->scope == 'A') {
//                echo "$record_count $item->group_code $item->name<br>";
                $area_count++;
                $db = Factory::getContainer()->get(DatabaseInterface::class);
                $query = $db->getQuery(true);
                $query->set("code = " . $db->quote($item->group_code))
                        ->set("name = " . $db->quote($item->name))
                        ->set("details = " . $db->quote($item->description))
                        ->set("website = " . $db->quote($item->external_url))
                        ->set("co_url = " . $db->quote($item->url))
                        ->set("latitude = " . $db->quote((float) $item->latitude))
                        ->set("longitude = " . $db->quote((float) $item->longitude))
                ;

                $sql = $sql_area . $item->group_code . "'";

                if ($debug) {
//                echo $sql . '<br>';
                }
                $row = $this->toolsHelper->getItem($sql);
                if (is_null($row)) {
                    $query->insert('#__ra_areas');
                    $result = $db->setQuery($query)->execute();
//                    echo $query . '<br>';
                    $area_insert++;
                } else {
                    // Matching record has been found
                    $update = 0;
                    /*
                      echo 'Updating name for ' . $row->code . ' from ' . $row->name . ' to ' . $item->name . '<br>';
                      echo 'Updating details for ' . $row->code . ' from ' . $row->details . ' to ' . $item->description . '<br>';
                      echo 'Updating co_url for ' . $row->code . ' from ' . $row->co_url . ' to ' . $item->url . '<br>';
                      echo 'Updating website for ' . $row->code . ' from ' . $row->website . ' to ' . $item->external_url . '<br>';
                      echo 'Updating latitude for ' . $row->code . ' from ' . $row->latitude . ' to ' . $item->latitude . '<br>';    // 52.367166890772
                      echo 'Updating longitude for ' . $row->code . ' from ' . $row->longitude . ' to ' . $item->longitude . '<br>'; // 52.123456789 12
                     */
                    if ($row->name <> $item->name) {
                        echo 'Updating name for ' . $row->code . ' from ' . $row->name . ' to ' . $item->name . '<br>';
                        $update = 1;
                    }
                    if ($row->details <> $item->description) {
                        echo 'Updating description for ' . $row->code . ' from ' . $row->details . ' to ' . $item->description . '<br>';
                        $update = 1;
                    }
                    if ($row->co_url <> $item->url) {
                        echo 'Updating co_url for ' . $row->code . ' from ' . $row->co_url . ' to ' . $item->url . '<br>';
                        $update = 1;
                    }
                    if ($row->website <> $item->external_url) {
                        echo 'Updating website for ' . $row->code . ' from ' . $row->website . ' to ' . $item->external_url . '<br>';
                        $update = 1;
                    }
                    if ($row->latitude <> $item->latitude) {
                        echo 'Updating latitude for ' . $row->code . ' from ' . $row->latitude . ' to ' . $item->latitude . '<br>';
                        $update = 1;
                    }
                    if ($row->longitude <> $item->longitude) {
//                        echo $item->group_code . ': ' . 'Updating longitude for row ' . $row->id . ' from ' . $row->longitude . ' to ' . $item->longitude . '<br>';
                        echo 'Updating longitude for ' . $row->code . ' from ' . $row->longitude . ' to ' . $item->longitude . '<br>';
                        $update = 1;
                    }
                    if ($update) {
                        $update_count++;
                        $query->update('#__ra_areas')
                                ->where('id=' . $row->id);
//                        echo $query . '<br>';
                        $result = $db->setQuery($query)->execute();
                    }
                }
            }
        }
        $message = $area_count . ' Areas,  ';
        if ($area_insert > 0) {
            $message .= $group_insert . ' records inserted ';
        }
        if ($update_count == 0) {
            $message .= 'No updates necessary';
        } else {
            $message .= $update_count . ' records updated';
        }
        echo '<br>' . $record_count . ' records read<br>';

        $this->updateClusters();
        $update_sql = "UPDATE `#__ra_areas` set nation_id = 2 WHERE cluster = 'SC';";
        $this->toolsHelper->executeCmd($update_sql);
        $update_sql = "UPDATE `#__ra_areas` set nation_id = 3 WHERE cluster = 'WA';";
        $this->toolsHelper->executeCmd($update_sql);

        if ($debug) {
            echo $message . '<br>';
            $target = 'index.php?option=com_ra_mailman&view=dashboard';
            $this->toolsHelper->backButton($target);
        } else {
            Factory::getApplication()->enqueueMessage($message, 'notice');
            $this->setRedirect('index.php?option=com_ra_mailman&view=organisations', false);
        }
    }

    public function refreshAreasOrig() {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
//      set up maximum time of 10 minutes
        $max = 10 * 60;
        set_time_limit($max);
        // set to 1 for debugging
        $debug = 0;
        // Read the Organisation feed, update tables #__ra_areas and #__ra_groups as required
        $area_count = 0;
        $area_insert = 0;
        $update_count = 0;
        $display = 0;

        $this->toolsHelper->executeCmd('DELETE FROM #__ra_areas WHERE id<10');
//       See https://app.swaggerhub.com/apis-docs/abateman/Ramblers-third-parties/1.0.0#/default/get_api_volunteers_groups
        $api_key = $this->toolsHelper->lookupApiKey();
        if ($api_key == '') {
            $message = 'API key not found - please create a record in API sites';
            Factory::getApplication()->enqueueMessage($message, 'error');
        }
        $feedurl = 'https://walks-manager.ramblers.org.uk/api/volunteers/groups?api-key=' . $api_key;

//        $jsonHelper = new JsonHelper;
//        echo '___orig: ' . $feedurl . '<br>';
//        echo 'setSql=' . $jsonHelper->setUrl('organisation', 'groups=NS') . '<br>';
//        die;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $feedurl);
        curl_setopt($ch, CURLOPT_HEADER, false); // do not include header in output
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false); // do not follow redirects
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // do not output result
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $max);  // allow xx seconds for timeout
        curl_setopt($ch, CURLOPT_TIMEOUT, $max);  // allow xx seconds for timeout
//	curl_setopt($ch, CURLOPT_REFERER, JURI::base()); // say who wants the feed

        curl_setopt($ch, CURLOPT_REFERER, "com_ra_mailman"); // say who wants the feed

        $data = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            print('Error code: ' . $error . "\n");
            print('Http return: ' . $httpCode . "\n");
            echo 'Access failed';
            return;
        }
        $area_list = json_decode($data);
//        var_dump($area_list);
//        die;
        // $area_list = $JsonHelper->getJson('group-event', 'groups=' . $group_code););


        $sql_area = "SELECT id,code,name,details, website,co_url, latitude, longitude "
                . "FROM #__ra_areas WHERE code='";

        $record_count = 0;
        foreach ($area_list as $item) {
            $record_count++;
            $display = 0;

            if ($item->scope == 'A') {
//                echo "$record_count $item->group_code $item->name<br>";
                $area_count++;
                $db = Factory::getContainer()->get(DatabaseInterface::class);
                $query = $db->getQuery(true);
                $query->set("code = " . $db->quote($item->group_code))
                        ->set("name = " . $db->quote($item->name))
                        ->set("details = " . $db->quote($item->description))
                        ->set("website = " . $db->quote($item->external_url))
                        ->set("co_url = " . $db->quote($item->url))
                        ->set("latitude = " . $db->quote((float) $item->latitude))
                        ->set("longitude = " . $db->quote((float) $item->longitude))
                ;

                $sql = $sql_area . $item->group_code . "'";

                if ($debug) {
//                echo $sql . '<br>';
                }
                $row = $this->toolsHelper->getItem($sql);
                if (is_null($row)) {
                    $query->insert('#__ra_areas');
                    $result = $db->setQuery($query)->execute();
//                    echo $query . '<br>';
                    $area_insert++;
                } else {
                    // Matching record has been found
                    $update = 0;
                    /*
                      echo 'Updating name for ' . $row->code . ' from ' . $row->name . ' to ' . $item->name . '<br>';
                      echo 'Updating details for ' . $row->code . ' from ' . $row->details . ' to ' . $item->description . '<br>';
                      echo 'Updating co_url for ' . $row->code . ' from ' . $row->co_url . ' to ' . $item->url . '<br>';
                      echo 'Updating website for ' . $row->code . ' from ' . $row->website . ' to ' . $item->external_url . '<br>';
                      echo 'Updating latitude for ' . $row->code . ' from ' . $row->latitude . ' to ' . $item->latitude . '<br>';    // 52.367166890772
                      echo 'Updating longitude for ' . $row->code . ' from ' . $row->longitude . ' to ' . $item->longitude . '<br>'; // 52.123456789 12
                     */
                    if ($row->name <> $item->name) {
                        echo 'Updating name for ' . $row->code . ' from ' . $row->name . ' to ' . $item->name . '<br>';
                        $update = 1;
                    }
                    if ($row->details <> $item->description) {
                        echo 'Updating description for ' . $row->code . ' from ' . $row->details . ' to ' . $item->description . '<br>';
                        $update = 1;
                    }
                    if ($row->co_url <> $item->url) {
                        echo 'Updating co_url for ' . $row->code . ' from ' . $row->co_url . ' to ' . $item->url . '<br>';
                        $update = 1;
                    }
                    if ($row->website <> $item->external_url) {
                        echo 'Updating website for ' . $row->code . ' from ' . $row->website . ' to ' . $item->external_url . '<br>';
                        $update = 1;
                    }
                    if ($row->latitude <> $item->latitude) {
                        echo 'Updating latitude for ' . $row->code . ' from ' . $row->latitude . ' to ' . $item->latitude . '<br>';
                        $update = 1;
                    }
                    if ($row->longitude <> $item->longitude) {
//                        echo $item->group_code . ': ' . 'Updating longitude for row ' . $row->id . ' from ' . $row->longitude . ' to ' . $item->longitude . '<br>';
                        echo 'Updating longitude for ' . $row->code . ' from ' . $row->longitude . ' to ' . $item->longitude . '<br>';
                        $update = 1;
                    }
                    if ($update) {
                        $update_count++;
                        $query->update('#__ra_areas')
                                ->where('id=' . $row->id);
//                        echo $query . '<br>';
                        $result = $db->setQuery($query)->execute();
                    }
                }
            }
        }
        $message = $area_count . ' Areas,  ';
        if ($area_insert > 0) {
            $message .= $group_insert . ' records inserted ';
        }
        if ($update_count == 0) {
            $message .= 'No updates necessary';
        } else {
            $message .= $update_count . ' records updated';
        }
        echo '<br>' . $record_count . ' records read<br>';

        $this->updateClusters();
        $update_sql = "UPDATE `#__ra_areas` set nation_id = 2 WHERE cluster = 'SC';";
        $this->toolsHelper->executeCmd($update_sql);
        $update_sql = "UPDATE `#__ra_areas` set nation_id = 3 WHERE cluster = 'WA';";
        $this->toolsHelper->executeCmd($update_sql);

        if ($debug) {
            echo $message . '<br>';
            $target = 'index.php?option=com_ra_mailman&view=dashboard';
            $this->toolsHelper->backButton($target);
        } else {
            Factory::getApplication()->enqueueMessage($message, 'notice');
            $this->setRedirect('index.php?option=com_ra_mailman&view=organisations', false);
        }
    }

    public function updateClusters() { // localhost/administrator/index.php?option=com_ra_mailman&task=organisations.updateCluster
        $this->checkClusters();
        $sql = 'SELECT * FROM #__ra_clusters';
        $clusters = $this->toolsHelper->getRows($sql);
        echo "$sql<br>";
        //      echo $this->toolsHelper . showQuery($sql);
        foreach ($clusters as $cluster) {
            $update_sql = 'UPDATE `#__ra_areas` SET cluster = "' . $cluster->code . '" ';
            $update_sql .= 'WHERE code in (';
            $update_sql .= '"' . str_replace(',', '","', $cluster->area_list) . '")';
            echo "$update_sql<br>";
            $this->toolsHelper->executeCmd($update_sql);
        }

        $sql = 'SELECT code FROM #__ra_areas WHERE cluster IS NULL ORDER BY code';
        $rows = $this->toolsHelper->getRows($sql);
        echo "Seeking areas without cluster code<br>";
        //      echo $this->toolsHelper . showQuery($sql);
        foreach ($rows as $row) {
            echo $row->code . ',';
        }
    }

    public function checkClusters() {

        $this->checkClusterTable();
        $sql = 'DELETE FROM #__ra_clusters';
        $this->toolsHelper->executeCmd($sql);

        $sql = 'SELECT COUNT(code) FROM #__ra_clusters';
        $count = $this->toolsHelper->getValue($sql);
        echo "$sql=$count<br>";
        if ($count == 0) {
            $sql = 'INSERT INTO `#__ra_clusters`(code, name,area_list) values ';
            $sql .= "('ME','Midlands and East','BF,LI,NP,NR,NE,SS,NS,WO,CH,DE'),";
            $sql .= "('N','North and North West','ER,HF,HW,LD,LE,LL,LN,MC,ML,MR,MW,NN,NS,NY,SD,SS,WK,WR'),";
            $sql .= "('SE','South East','BU,CB,ES,WX,KT,IL,IW,NO,OX,SK,SR,SX'),";
            $sql .= "('SSW','South and South West','AV,BK,CL,DN,DT,GR,IW,OX,SO,WE'),";
            $sql .= "('WA','Wales','CA,CE,SW,GG,LW,PE'),";
            $sql .= "('SC','Scotland','CY,CF,GP,SC,LB,SL,RB,WS');";

            echo "$sql<br>";
            $this->toolsHelper->executeCmd($sql);
        }
    }

    public function checkClusterTable() {
        $details = '(`code` VARCHAR(3) NOT NULL, '
                . '`name` VARCHAR(20) NOT NULL, '
                . '`contact_id` INT NULL, '
                . '`area_list` VARCHAR(255) NULL, '
                . ' PRIMARY KEY(`code`)'
                . ') ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 DEFAULT COLLATE = utf8mb4_unicode_ci;
                ';

        $this->checkTable('ra_clusters', $details, '');
        $this->checkColumn('ra_clusters', 'areas', 'D', "");
        $this->checkColumn('ra_clusters', 'area_list', 'A', "VARCHAR(255) NOT NULL DEFAULT '' AFTER contact_id; ");
        $this->checkColumn('ra_areas', 'cluster', 'A', "VARCHAR(3) NOT NULL DEFAULT '' AFTER co_url; ");
    }

    function checkColumn($table, $column, $mode, $details = '') {
//  $mode = A: add the field, using data suppied in $details
//  $mode = U: update the field (keeping name the same), using $details
//  $mode = D: delete the field

        $count = $this->checkColumnExists($table, $column);
        $table_name = $this->dbPrefix . $table;
        //       echo 'mode=' . $mode . ': Seeking ' . $table_name . '/' . $column . ', count=' . $count . "<br>";
        if (($mode == 'A') AND ($count == 1)
                OR ($mode == 'D') AND ($count == 0)) {
            return true;
        }
        if (($mode == 'U') AND ($count == 0)) {
            echo 'Field ' . $column . ' not found in ' . $table_name . '<br>';
            return false;
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
        $response = $this->toolsHelper->executeCommand($sql);
        if ($response) {
            echo 'Success';
        } else {
            echo 'Failure';
        }
        echo ' for ' . $table_name . '<br>';
        return $count;
    }

    private function checkColumnExists($table, $column) {
        $config = Factory::getConfig();
        $database = $config->get('db');
        $this->dbPrefix = $config->get('dbprefix');

        $table_name = $this->dbPrefix . $table;
        $sql = 'SELECT COUNT(COLUMN_NAME) ';
        $sql .= "FROM information_schema.COLUMNS ";
        $sql .= "WHERE TABLE_SCHEMA='" . $database . "' AND TABLE_NAME ='" . $this->dbPrefix . $table . "' ";
        $sql .= "AND COLUMN_NAME='" . $column . "'";
//    echo "$sql<br>";

        return $this->toolsHelper->getValue($sql);
    }

    function checkTable($table, $details, $details2 = '') {

        $config = Factory::getConfig();
        $database = $config->get('db');
        $this->dbPrefix = $config->get('dbprefix');

        $table_name = $this->dbPrefix . $table;
        $sql = 'SELECT COUNT(COLUMN_NAME) ';
        $sql .= "FROM information_schema.COLUMNS ";
        $sql .= "WHERE TABLE_SCHEMA='" . $database . "' AND TABLE_NAME ='" . $table_name . "' ";
//        echo "$sql<br>";

        $count = $this->toolsHelper->getValue($sql);
        echo 'Seeking ' . $table_name . ', count = ' . $count . " fields<br>";
        if ($count > 0) {
            return $count;
        }
        $sql = 'CREATE TABLE ' . $table_name . ' ' . $details;
        echo "$sql<br>";
        $response = $this->toolsHelper->executeCommand($sql);
        if ($response) {
            echo 'Table created OK<br>';
        } else {
            echo 'Failure<br>';
            return false;
        }
        if ($details2 != '') {
            $sql = 'ALTER TABLE ' . $table_name . ' ' . $details2;
            $response = $this->executeCommand($sql);
            if ($response) {
                echo 'Table altered OK<br>';
            } else {
                echo 'Failure<br>';
                return false;
            }
        }
    }

    public function upload() {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $filename = JPATH_ROOT . '/images/com_ra_mailman/groups.json';

// scope, code, name, details
        if (file_exists($filename)) {
            Factory::getApplication()->enqueueMessage('File ' . $filename . ' found', 'notice');
            $data = file_get_contents($filename);
            //          var_dump($data);
            //          echo $data . '<br>';
            $rows = json_decode($data);
            $sql_area = 'SELECT id,code,name,details, website '
                    . 'FROM #__ra_areas WHERE code="';
            $sql_group = 'SELECT id,code,name,details, website '
                    . 'FROM #__ra_groups WHERE code="';
            foreach ($rows as $row) {
                if ($row->scope == 'A') {
                    $table = '#__ra_areas';
//                echo "$record_count $item->group_code $item->name<br>";
                    $area_count++;
                    $sql = $sql_area;
                } else {
                    $table = '#__ra_groups';
                    $sql = $sql_group;
                    $group_count++;
                }
                echo $sql . $row->code . '"<br>';
                $item = $this->toolsHelper->getItem($sql . $row->code . '"');
                if (is_null($item)) {
                    Factory::getApplication()->enqueueMessage('No record found for ' . $row->scope . ' /' . $row->code, 'notice');
                } else {
                    $update = 0;
                    $sql_update = '';

                    if ($row->name <> $item->name) {
                        echo 'Updating name for ' . $item->code . ' from ' . $item->name . ' to ' . $row->name . '<br>';
                        $sql_update = 'name="' . $db->escape($row->name) . '"';
                    }
                    if ($row->details <> $item->details) {
                        echo 'Updating description for ' . $item->code . ' from ' . $item->details . ' to ' . $row->details . '<br>';
                        if ($sql_update !== '') {
                            $sql_update .= ', ';
                        }
                        $sql_update .= 'details="' . $db->escape($row->details) . '"';
                    }
                    if ($row->website <> $item->website) {
                        echo 'Updating website for ' . $item->code . ' from ' . $item->website . ' to ' . $row->website . '<br>';
                        if ($sql_update !== '') {
                            $sql_update .= ', ';
                        }
                        $sql_update .= 'website="' . $db->escape($row->website) . '"';
                    }
                    if ($sql_update !== '') {
                        //                    echo ' updating ' . $row->name . '<br>';
                        $sql2 = ' WHERE code="' . $db->escape($row->code) . '"';
//                        echo 'UPDATE ' . $table . ' SET ' . $sql_update . $sql2;
                        $this->toolsHelper->executeCommand('UPDATE ' . $table . ' SET ' . $sql_update . $sql2);
                    }
                }
            }
        } else {
            Factory::getApplication()->enqueueMessage($filename . ' not found', 'error');
        }
    }

}
