<?php

/**
 * @version    4.6.6
 * @package    com_ra_mailman
 * @copyright   Copyright (C) 2005 - 2019 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * 21/05/25 CB dummyEmail, checkDatabase reports
 * 14/06/25 CB delete subscriptions without user records
 * 30/06/25 CB Purge blocked records
 * 02/77/25 CB breadcrumbs, Users set to receive system emails
 * 10/08/25 CB recentMailshots
 * 16/08/25 CB correct hex for Sunrise
 * 29/09/25 CB bookableEvents
 * 06/10/25 CB colour for event link
 * 11/10/25 CB display colour for event link, subscriptionsReport
 * 03/11/25 CB show count of blocked users
 * 28/03/26 CB Analysis of members by group
 * 13/04/26 CB Users with a Profile
 */

namespace Ramblers\Component\Ra_mailman\Administrator\Controller;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\MVC\Controller\FormController;
//use Joomla\CMS\Router\Route;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Ramblers\Component\Ra_mailman\Site\Helpers\Mailhelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsTable;
use Ramblers\Component\Ra_tools\Site\Helpers\UserHelper;

class ReportsController extends FormController {

    protected $back = 'administrator/index.php?option=com_ra_mailman&view=reports';
    protected $breadcrumbs;
    protected $db;
    protected $app;
    protected $prefix;
    protected $query;
    protected $scope;
    protected $subheading;
    protected $toolsHelper;

    public function __construct() {
        parent::__construct();
        $this->db = Factory::getDbo();
        $this->toolsHelper = new ToolsHelper;
        $this->mailHelper = new MailHelper;
        $this->app = Factory::getApplication();
        $this->prefix = 'Reports: ';
        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
        $wa->registerAndUseStyle('ramblers', 'com_ra_tools/ramblers.css');
        $this->breadcrumbs = $this->toolsHelper->buildLink('administrator/index.php', 'Dashboard');
        $this->breadcrumbs .= '>' . $this->toolsHelper->buildLink('administrator/index.php?option=com_ra_tools&view=dashboard', 'RA Dashboard');
        $this->breadcrumbs .= '>' . $this->toolsHelper->buildLink($this->back, 'MailMan Reports');
        $this->scope = $this->app->input->getWord('scope', '');  
        if ($this->scope == ''){ 
            $this->subheading = 'All records';
        } else {
            $code = $this->mailHelper->getDefaultGroup();
            if ($this->scope == 'A'){ 
                $code = substr($code,0,2);
            }
            
            $sql = 'SELECT id, name ';
            $sql .= 'FROM #__ra_organisations ';
            $sql .= 'WHERE code="' . $code . '"';
            $item = $this->toolsHelper->getItem($sql);  
            $this->subheading =  $code . ' ' . (!empty($item->name) ? htmlspecialchars($item->name) : 'N/A');    
        }              
    }

    public function analyseListMembership(){
        ToolBarHelper::title($this->prefix . 'Analysis of members by group');
        echo $this->breadcrumbs;
        $header = 'Status,Group,Count';

        
        $sql = 'SELECT l.`group_code`, l.`name`';
        $sql .= 'FROM `j5_ra_mail_lists` AS l'; 
        $sql .= 'WHERE l.`group_code` = l.`group_primary`';
        
        $lists = $this->toolsHelper->getRows($sql);
        foreach($lists as $list){
            echo '<h4>' . $list->group->code . ': ' . $list->name . '</h4>';
            $table = new ToolsTable();
            $table->add_header($header);    
                        
            $sql = 'SELECT l.state, p.home_group, COUNT(l.id) As `Cnt`'; 
            $sql .= 'FROM `j5_ra_mail_lists` AS l ';
            $sql .= 'INNER JOIN `j5_ra_mail_subscriptions` AS s ON s.list_id = l.id ';
            $sql .= 'INNER JOIN `j5_ra_profiles` AS p ON p.id = s.user_id ';
            $sql .= 'WHERE  l.`id`=' . $list->id;
            $sql .= ' GROUP BY l.state, l.`group_code`,p.home_group'; 
            $rows = $this->toolsHelper->getRows($sql);  
            foreach ($rows as $row){
                $table->add_item($row->state);
                $table->add_item($row->home_group);
                $table->add_item($row->Cnt);   
                $table->generate_line();  
            }
            $table->generate_table();
        }
        echo $this->toolsHelper->backButton($this->back);
    }

    public function blockedUsers() {
        ToolBarHelper::title($this->prefix . 'Blocked users');
        echo $this->breadcrumbs;
        $table = new ToolsTable();
        $table->add_header("Name,email,Lists,Audit,ID");

        $sql = "SELECT id, name as 'User', email  ";
        $sql .= 'FROM `#__users` ';
        $sql .= ' WHERE block=1';
        $sql .= ' ORDER BY id';
        $target = 'administrator/index.php?option=com_ra_mailman&task=system.purgeUser&id=';
        $rows = $this->toolsHelper->getRows($sql);
        foreach ($rows as $row) {
            $table->add_item($row->User);
            $table->add_item($row->email);
            $count = $this->countLists($row->id);
            $table->add_item($count);
            $count = $this->countAudit($row->id);
            $table->add_item($count);
            if ($this->toolsHelper->isSuperuser()) {
                $table->add_item($this->toolsHelper->buildButton($target . $row->id, 'Purge', false, 'orange'));
            } else {
                $table->add_item($row->id);
            }
            $table->generate_line();
        }
        $table->generate_table();
        echo count($rows) . ' rows<br>';
        if ((count($rows) > 1) AND ($this->toolsHelper->isSuperuser())) {
            $target = 'administrator/index.php?option=com_ra_mailman&task=system.purgeAllUsers';
            echo $this->toolsHelper->buildButton($target, 'Purge All', false, 'red');
        }

        echo $this->toolsHelper->backButton($this->back);
    }

       public function bookableEvents() {
        ToolBarHelper::title('Future bookable events' . $year);
        echo $this->breadcrumbs;
        $sql = 'SELECT COUNT(id) FROM `#__ra_events` ';
        $sql .= 'WHERE bookable=1 AND DATEDIFF(event_date, CURRENT_DATE)>0 ';
        $sql .= 'AND api_site_id IS NULL ';
        $count = $this->toolsHelper->getValue($sql);
        if ($count = 0) {
            echo 'No Events found<br>';
        } else {
            //        echo '<h4>Events that have been imported</h4>';
            $sql = 'SELECT e.id, e.event_date,e.title AS `event`,  ';
            $sql .= 'e.num_bookings, e.max_bookings, e.location, c.name, t.description ';
            $sql .= 'FROM `#__ra_events` AS e ';
            $sql .= 'INNER JOIN #__ra_event_types as t ON t.id = e.event_type_id ';
            $sql .= 'LEFT JOIN #__contact_details AS c ON c.id = e.contact_id ';
            $sql .= 'WHERE e.bookable=1 ';
            $sql .= 'AND e.api_site_id IS NULL ';
            $sql .= 'AND DATEDIFF(e.event_date, CURRENT_DATE) > 0 ';
            $sql .= 'ORDER BY e.event_date ASC';
            $rows = $this->toolsHelper->getRows($sql);
            $objTable = new ToolsTable();

            $objTable->add_header("Event date,Type,Title,Location,Bookings,Contact");
            foreach ($rows as $row) {
                $objTable->add_item(HTMLHelper::_('date', $row->event_date, 'd-M-y'));
                $objTable->add_item($row->description);
                $objTable->add_item($row->event);
                $objTable->add_item($row->location);
                $objTable->add_item($row->num_bookings . '/' . $row->max_bookings);
                $objTable->add_item($row->name);

                $objTable->generate_line();
            }
            $objTable->generate_table();
        }
        echo $count . ' Events<br>';
        $target = "administrator/index.php?option=com_ra_mailman&task=reports.showMailshotsByMonth";
        echo $this->toolsHelper->backButton($this->back);
    }

    private function breadcrumbsExtra($label, $report) {
        // generates a link to be added to the standard breadcrumbs
        $target = 'administrator/index.php?option=com_ra_mailman&task=reports.' . $report;
        return '>' . $this->toolsHelper->buildLink($target, $label);
    }

    private function buildCriterion($operator,$field_name, $code = ''){
    // If scope is blank, no additional criterion is required
        if ($this->scope == ''){ 
            $this->subheading = 'All records';
            return '';
        }
        if ($code == ''){
            $code = $this->mailHelper->getDefaultGroup();
        }
        $sql = $operator . ' (' . $field_name;
        if ($this->scope == 'A'){ 
            $area_code = substr($code,0,2);
            $sql .=  ' LIKE "' . $area_code . '%") ';
        } else { 
            $sql .= '="' . $code . '") ';
        }
        $sql_lookup = 'SELECT id, name ';
        $sql_lookup .= 'FROM #__ra_organisations ';
        $sql_lookup .= 'WHERE code="' . $code . '"';
        $item = $this->toolsHelper->getItem($sql_lookup);  
        $this->subheading =  $code . ' ' . (!empty($item->name) ? htmlspecialchars($item->name) : 'N/A');        
 //       echo $sql . '<br>';
        return $sql;
    }

    public function checkDatabase() {
        ToolBarHelper::title($this->prefix . 'Checking database');
        echo $this->breadcrumbs . '<br>';
//  See if any Subscription without a List
        $sql = "SELECT count(*) FROM #__ra_mail_subscriptions AS ms ";
        $sql .= "LEFT JOIN #__ra_mail_lists as ml ON ml.id = ms.list_id ";
        $sql .= "WHERE ml.id IS NULL ";
        $count = $this->toolsHelper->getValue($sql);
        if ($count == 0) {
            echo 'All subscriptions have a matching List<br>';
        } else {
//           echo 'Subscriptions found, no matching User<br>';
            $sql = 'SELECT ms.id, ms . list_id, ms . user_id, ms . record_type, ms . method_id, ms.created ';
            $sql .= 'FROM #__ra_mail_subscriptions AS ms ';
            $sql .= ' '; // LEFT JOIN #__users as u ON u.id = ms.user_id ';
            $sql .= 'LEFT JOIN #__ra_mail_lists as ml ON ml.id = ms.list_id ';
            $sql .= 'WHERE ml.id IS NULL ';
            $sql .= 'ORDER BY ms.user_id';

            $this->toolsHelper->showQuery($sql);
//            if ($this->toolsHelper->isSuperuser()) {
//                $target = 'administrator/index.php?option=com_ra_mailman&task=reports.duffProfiles&mode=P';
//                echo $this->toolsHelper->buildButton($target, 'Purge All', false, 'red');
//            }
        }
//  See if any Subscription without a User
        $sql = "SELECT count(*) FROM #__ra_mail_subscriptions AS ms ";
        $sql .= "LEFT JOIN #__users as u ON u.id = ms.user_id ";
        $sql .= "WHERE u.id IS NULL ";
        $count = $this->toolsHelper->getValue($sql);
        if ($count == 0) {
            echo 'All subscriptions have a matching User<br>';
        } else {
            echo '<h4>' . $count . ' Subscriptions found, no matching User</h4>';
            /*
              $sql = 'SELECT ml.name, ms.id, ms.user_id, ms.record_type, ms.method_id, ms.created ';
              $sql .= 'FROM #__ra_mail_subscriptions AS ms ';
              $sql .= 'LEFT JOIN #__users as u ON u.id = ms.user_id ';
              $sql .= 'LEFT JOIN #__ra_mail_lists as ml ON ml.id = ms.list_id ';
              $sql .= 'WHERE u.id IS NULL ';
              $sql .= 'ORDER BY ml.name,ms.user_id';
              $this->toolsHelper->showSql($sql);
             */
            if ($this->toolsHelper->isSuperuser()) {
                $sql = 'SELECT ms.id ';
                $sql .= 'FROM #__ra_mail_subscriptions AS ms ';
                $sql .= 'LEFT JOIN #__users as u ON u.id = ms.user_id ';
                $sql .= 'WHERE u.id IS NULL ';
                $this->toolsHelper->getRows($sql);
                $rows = $this->toolsHelper->getRows($sql);
                foreach ($rows as $row) {
                    $sql = 'DELETE FROM  #__ra_mail_subscriptions_audit ';
                    $sql .= 'WHERE object_id=' . $row->id;
                    //                   echo $sql . '<br>';
                    $this->toolsHelper->executeCommand($sql);
                    $sql = 'DELETE FROM #__ra_mail_subscriptions WHERE id=' . $row->id;
                    //                   echo $sql . '<br>';
                    $this->toolsHelper->executeCommand($sql);
                }
                echo '... records deleted<br>';
            }
        }

//  See if any Profiles without a User
        $sql = "SELECT count(*) FROM #__ra_profiles AS p ";
        $sql .= "LEFT JOIN `#__users` as u on u.id = p.id ";
        $sql .= "WHERE u.id IS NULL ";
        echo $sql . '<br>';
        $count = $this->toolsHelper->getValue($sql);
        if ($count == 0) {
            echo 'All profiles have a matching User<br>';
        } else {
            echo '<h4>Users not found, Profiles still present<b/h4>';
            $sql = "SELECT p.id, p.home_group, p.preferred_name, p.created ";
            $sql .= "FROM #__ra_profiles AS p ";
            $sql .= "LEFT JOIN `#__users` as u on u.id = p.id ";
            $sql .= "WHERE u.id IS NULL ";
            $sql .= "order by p.id";
            $rows = $this->toolsHelper->getRows($sql);
            $table = new ToolsTable();
            $table->add_header("ID,Group,Preferred Name,Created");

            foreach ($rows as $row) {
                $table->add_item($row->id);
                $table->add_item($row->home_group);
                $table->add_item($row->preferred_name);
                $table->add_item($row->created);
                $table->generate_line();
            }
            $table->generate_table();
            if ($this->toolsHelper->isSuperuser()) {
                $target = 'administrator/index.php?option=com_ra_mailman&task=reports.duffProfiles&mode=P';
                echo $this->toolsHelper->buildButton($target, 'Purge All', false, 'red');
            }
        }
//  See if any Users without a Profile  
        $sql = "SELECT count(*) FROM #__users AS u ";
        $sql .= "LEFT JOIN `#__ra_profiles` as p on p.id = u.id ";
        $sql .= "WHERE p.id IS NULL ";
        echo $sql . '<br>';
        $count = $this->toolsHelper->getValue($sql);
        if ($count == 0) {
            echo 'All users have a matching Profile<br>';
        } else {
            echo '<h4>Users without a Profile record</h4>';
            $sql = "SELECT u.id, u.name, u.email, u.registerDate, u.lastvisitDate ";
            $sql .= "FROM #__users AS u ";
            $sql .= "LEFT JOIN `#__ra_profiles` as p on p.id = u.id ";
            $sql .= "WHERE p.id IS NULL ";
            $sql .= "order by u.id";
            $rows = $this->toolsHelper->getRows($sql);
            $objTable = new ToolsTable();
            $objTable->add_header("ID,Name,Email,Registered,Last Visit");

            foreach ($rows as $row) {
                $table->add_item($row->id);
                $table->add_item($row->name);
                $table->add_item($row->email);
                $table->add_item($row->created);
                $table->generate_line();
            }
            $table->generate_table();
            if ($this->toolsHelper->isSuperuser()) {
                $target = 'administrator/index.php?option=com_ra_mailman&task=reports.duffProfiles&mode=P';
                echo $this->toolsHelper->buildButton($target, 'Purge All', false, 'red') . '<br>';
            }
        }        
//  See if any Profiles with user_id =0
        $sql = 'SELECT count(*) FROM #__ra_profiles ';
        $sql .= 'WHERE id =0 ';
        $count = $this->toolsHelper->getValue($sql);
        if ($count == 0) {
            echo 'No profiles with id=0<br>';
        } else {
            echo 'Profiles found without user id<br>';
            $target = 'administrator/index.php?option=com_ra_mailman&task=profile.purgeProfile&id=';
            $sql = "SELECT p.id, p.home_group, p.preferred_name ";
            $sql .= "FROM #__ra_profiles AS p ";
            $sql .= "WHERE p.id=0 ";
            $sql .= "ORDER BY p.id";
            $table = new ToolsTable();
            $table->add_header("ID,Group,Name");
            $rows = $this->toolsHelper->getRows($sql);
            foreach ($rows as $row) {
                $table->add_item($row->id);
                $table->add_item($row->home_group);
                $table->add_item($row->preferred_name);
            }
            $table->generate_table();
        }

        //Find any Users without a Preferred Name

        $sql = 'SELECT u.id, u.name, u.email, u.registerDate, u.lastvisitDate ';
        $sql .= 'FROM `#__users` as u ';
        $sql .= 'INNER JOIN #__ra_profiles AS p on p.id = u.id ';
        $sql .= 'WHERE (p.preferred_name IS NULL) ';
        $sql .= 'OR  (p.preferred_name= "") ';
        $sql .= 'ORDER BY u.id';
        $target_purge = 'administrator/index.php?option=com_users&view=users';
        $target_edit = 'administrator/index.php?option=com_ra_mailman&task=profile.create&id=';
        $rows = $this->toolsHelper->getRows($sql);
        if ($rows) {
            echo '<h4>Users without a Preferred Name</h4>';
            $table = new ToolsTable();
            $table->add_header("ID,Name,email,Registered,Last visit");
            foreach ($rows as $row) {
                $table->add_item($row->id);
                $table->add_item($row->name);
                $table->add_item($row->email);
                $table->add_item($row->registerDate);
                $table->add_item($row->lastvisitDate);
                $table->generate_line();
            }
            $table->generate_table();
        } else {
            echo 'All profile records have a value for Preferred Name<br>';
        }

        // Check for Profiles with duplicated name

        $sql = 'SELECT home_group, preferred_name, count(id) ';
        $sql .= 'FROM #__ra_profiles GROUP BY home_group, preferred_name ';
        $sql .= 'HAVING COUNT(id) > 1 ';
        $sql .= 'ORDER BY preferred_name';
//        echo "$sql<br>";
//        $this->toolsHelper->showSql($sql);
        $rows = $this->toolsHelper->getRows($sql);

        if (count($rows) == 0) {
            echo 'No duplicate names found for Profile records<br>';
        } else {
            echo '<h4>Profile records with duplicated names</h4>';
            $table = new ToolsTable();
            $table->add_header('id,Group,Preferred name,Real name,Email');
            foreach ($rows as $row) {

                $sql_user = 'SELECT p.id, u.name, u.email ';
                $sql_user .= 'FROM #__ra_profiles AS p ';
                $sql_user .= 'LEFT JOIN #__users AS u ON u.id = p.id ';
                $sql_user .= 'WHERE p.preferred_name="' . $row->preferred_name . '"';
//               echo "$sql_user<br>";
                $users = $this->toolsHelper->getRows($sql_user);
                foreach ($users as $user) {
                    $table->add_item($user->id);
                    $table->add_item($row->home_group);
                    $table->add_item($row->preferred_name);
                    $table->add_item($user->name);
                    $table->add_item($user->email);
                    $table->generate_line();
                }
            }
            $table->generate_table();
            echo '<p style="color:red">Please edit the User records and change the name</p>';
        }

        //Find any Users set to receive system emails

        $sql = 'SELECT u.id, u.name, u.email, u.registerDate, u.lastvisitDate ';
        $sql .= 'FROM `#__users` as u ';
        $sql .= 'INNER JOIN #__ra_profiles AS p on p.id = u.id ';
        $sql .= 'WHERE u.sendEmail =1 ';
        $sql .= 'ORDER BY u.id';
        $target_purge = 'administrator/index.php?option=com_users&view=users';
        $target_edit = 'administrator/index.php?option=com_ra_mailman&task=profile.create&id=';
        $rows = $this->toolsHelper->getRows($sql);
        if ($rows) {
            echo '<h4>Users receiving system emails</h4>';
            $table = new ToolsTable();
            $table->add_header("ID,Name,email,Registered,Last visit");
            foreach ($rows as $row) {
                $table->add_item($row->id);
                $table->add_item($row->name);
                $table->add_item($row->email);
                $table->add_item($row->registerDate);
                $table->add_item($row->lastvisitDate);
                $table->generate_line();
            }
            $table->generate_table();
        } else {
            echo 'No Users can receive system emails<br>';
        }

// see if any unlinked records for usergroup_map
        $sql = 'SELECT m.user_id, m.group_id, u.name, u.username ';
        $sql .= 'FROM #__user_usergroup_map as m ';
        $sql .= 'LEFT JOIN #__users as u ON u.id = m.user_id ';
        $sql .= 'WHERE u.id IS NULL ';
        $sql .= 'ORDER BY u.id ';

        $rows = $this->toolsHelper->getRows($sql);
        foreach ($rows as $row) {
            $this->toolsHelper->showQuery($sql);
            $sql = 'DELETE FROM  #__user_usergroup_map WHERE user_id=' . $row->user_id;
            $sql .= ' AND group_id=' . (int) $row->group_id;
            echo $sql . '<br>';
            $this->toolsHelper->executeCommand($sql);
        }

        echo $this->toolsHelper->backButton($this->back);
    }

    public function cancel($key = null, $urlVar = null) {
        $this->setRedirect('index.php?option=com_ra_tools&view=dashboard');
    }

    private function countAudit($user_id) {
        $sql = 'SELECT COUNT(a.id) ';
        $sql .= 'FROM `#__ra_mail_subscriptions_audit` AS a ';
        $sql .= 'INNER JOIN `#__ra_mail_subscriptions` AS s ON s.id = a.object_id ';
        $sql .= 'WHERE s.user_id=' . $user_id;
//        echo $sql . '<br>';
//        $count = $this->toolsHelper->getValue($sql);
        return $this->toolsHelper->getValue($sql);
    }

    private function countLists($user_id) {
        $sql = 'SELECT COUNT(id) ';
        $sql .= 'FROM `#__ra_mail_subscriptions` ';
        $sql .= 'WHERE user_id=' . $user_id;
//        echo $sql . '<br>';
//        $count = $this->toolsHelper->getValue($sql);
        return $this->toolsHelper->getValue($sql);
    }

    /*

      In Display mode, this shows any Profiles records for which no matching User record is present, usually because it has been deleted manually

      In Purge mode, it invokes a function in the UserHelper to actually delete them

     */

    public function duffProfiles() {
        ToolBarHelper::title($this->prefix . 'Profiles without a User');
        echo $this->breadcrumbs;
        $mode = $this->app->input->getWord('mode', 'D');
        if ($mode == 'P') {
            $userHelper = new UserHelper;
            $userHelper->purgeProfiles();
            echo $this->toolsHelper->backButton($this->back);
            return;
        }

//  See if any Profiles without a User
        $sql = "SELECT count(*) FROM #__ra_profiles AS p ";
        $sql .= "LEFT JOIN `#__users` as u on u.id = p.id ";
        $sql .= "WHERE u.id IS NULL ";
        $count = $this->toolsHelper->getValue($sql);
        if ($count == 0) {
            echo 'All profiles match<br>';
        } else {
//           echo 'Users not found, Profiles still present<br>';
            $sql = "SELECT p.id, p.home_group, p.preferred_name, p.created ";
            $sql .= "FROM #__ra_profiles AS p ";
            $sql .= "LEFT JOIN `#__users` as u on u.id = p.id ";
            $sql .= "WHERE u.id IS NULL ";
            $sql .= "order by p.id";
            $rows = $this->toolsHelper->getRows($sql);
            $objTable = new ToolsTable();
            $objTable->add_header("ID,Group,Preferred Name,Created");

            foreach ($rows as $row) {
                $objTable->add_item($row->id);
                $objTable->add_item($row->home_group);
                $objTable->add_item($row->preferred_name);
                $objTable->add_item($row->created);
                $objTable->generate_line();
            }
            $objTable->generate_table();
            if ($this->toolsHelper->isSuperuser()) {
                $target = 'administrator/index.php?option=com_ra_mailman&task=reports.duffProfiles&mode=P';
                echo $this->toolsHelper->buildButton($target, 'Purge All', false, 'red');
            }
        }
//  See if any Profiles with user_id =0
        $sql = 'SELECT count(*) FROM #__ra_profiles ';
        $sql .= 'WHERE id =0 ';
        $count = $this->toolsHelper->getValue($sql);
        if ($count > 0) {
            echo 'Profiles found without user id<br>';
            $target = 'administrator/index.php?option=com_ra_mailman&task=profile.purgeProfile&id=';
            $sql = "SELECT p.id, p.home_group, p.preferred_name ";
            $sql .= "FROM #__ra_profiles AS p ";
            $sql .= "WHERE p.id=0 ";
            $sql .= "ORDER BY p.id";
            $objTable = new ToolsTable();
            $objTable->add_header("ID,Group,Name");
            $rows = $this->toolsHelper->getRows($sql);
            foreach ($rows as $row) {
                $objTable->add_item($row->id);
                $objTable->add_item($row->home_group);
                $objTable->add_item($row->preferred_name);
            }
            $objTable->generate_table();
        }
        echo $this->toolsHelper->backButton($this->back);
    }

    public function duplicateRecipients() {
        ToolBarHelper::title('Duplicate Recipents');
        echo $this->breadcrumbs;
        $sql = 'SELECT MAX(ms.date_sent) as `date_sent`, ms.title, `user_id` ,COUNT(mr.id) AS `count` ';
        $sql .= 'FROM `#__ra_mail_recipients` AS mr ';
        $sql .= 'INNER JOIN `#__ra_mail_shots` AS ms on ms.id = mr.mailshot_id ';
        $sql .= 'GROUP BY `user_id`, ms.title  ';
        $sql .= 'HAVING COUNT(mr.id) > 1 ';
//        $sql .= 'ORDER BY preferred_name';
//        echo "$sql<br>";
        $rows = $this->toolsHelper->getRows($sql);
        if ($rows) {
            $objTable = new ToolsTable();
            $objTable->add_header("Date sent,Mailshot,User id, Count");
            foreach ($rows as $row) {
                $objTable->add_item($row->date_sent);
                $objTable->add_item($row->title);
                $objTable->add_item($row->user_id);
                $objTable->add_item($row->count);
                $objTable->generate_line();
            }
            $objTable->generate_table();
        } else {
            echo '<br>No duplicates found<br>';
        }
        echo $this->toolsHelper->backButton($this->back);
    }

    public function emailPreview() {
        ToolBarHelper::title('Email preview');
        echo $this->breadcrumbs;
        echo '<h4>Scope '  . $this->subheading . '</h4>';    
        $setup = $this->mailHelper->getEmailSetup();
        $params = ComponentHelper::getParams('com_ra_mailman');

        echo $this->mailHelper->buildEmailHeader($setup);

        $body = '<div style="background: ' . $setup->colour_body;
        $body .= '; padding-top: 10px;  padding-bottom: 10px; ">';

        // Lookup the most recent mailshot
        $sql = 'SELECT body FROM #__ra_mail_shots ';
        $sql .= 'ORDER BY created DESC ';
        $sql .= 'LIMIT 1';
        $body .= $this->toolsHelper->getItem($sql)->body;
        $body .= '</div>';
        echo $body;
// There may be a link to a shared Evemt
        $body = '<div style="background: ' . $params->get('colour_event', 'rgba(156, 200, 171, 0.2)');
        $body .= '; padding: 10px;  border-radius: 5%; ">';
        $body .= $this->toolsHelper->buildLink($this->back, 'Click here to book on the Event');
        $body .= '</div>';
        echo $body;
// Footer comprises the footer from the list, plus the owners email address, plus the component footer
    $footer = '<div style="background: ' . $setup->colour_footer;
        $footer .= '; border-radius: 5%; padding: 10px;">';
        // Find a list footer
        $sql = 'SELECT footer FROM #__ra_mail_lists ';
        $sql .= 'WHERE group_primary IS NOT NULL ';
        $sql .= 'ORDER BY id DESC LIMIT 1';
        $footer .= $this->toolsHelper->getItem($sql)->footer;
        $footer .= '<br>';
    $footer .= $setup->email_footer;
        $footer .= '</div>';
        echo $footer;
        echo '<br><br>';
        echo '<h2>Current settings are as follows</h2>';
        echo '<b>source:</b> ' . $setup->setup_source;
        if ($setup->setup_code !== '') {
            echo ' (' . $setup->setup_code . ')';
        }
        echo '<br>';
    echo '<b>header:</b> ' . $setup->colour_header . '<br>';
    echo '<b>body:</b> ' . $setup->colour_body . '<br>';
        echo '<b>event:</b> ' . $params->get('colour_event') . '<br>';
    echo '<b>footer:</b> ' . $setup->colour_footer . '<br>';

    echo '<b>logo:</b> ' . $setup->logo_file . ', height=' . $setup->height;
    echo ', width=' . $setup->width . '<br>';
    echo '<b>logo align:</b> ' . $setup->logo_align . '<br>';
        echo '<br>';
        $saturation = [];
        $saturation[] = '0.2';
        $saturation[] = '0.4';
        $saturation[] = '0.6';
        $saturation[] = '0.8';
        $saturation[] = '1';

        $names = [];
        $colours = [];
        $names[] = 'mint cake';
        $colours[] = '156,200,171';
        $names[] = 'sunset';
        $colours[] = '240,128,80';
        $names[] = 'granite';
        $colours[] = '64,64,65';
        $names[] = 'rosy cheeks';
        $colours[] = '246,176,157';
        $names[] = 'sunrise';
        $colours[] = '249,177,4';

        $names[] = 'Pantone 0110';
        $colours[] = '215,169,0';
        $names[] = 'Pantone 0159';
        $colours[] = '199,91,18';
        $names[] = 'Pantone 0186';
        $colours[] = '198,12,48';
        $names[] = 'Pantone 0555';
        $colours[] = '32,108,0';

        $names[] = 'Pantone 0583';
        $colours[] = '168,180,0';
        $names[] = 'Pantone 1815';
        $colours[] = '120,35,39';
        $names[] = 'Pantone 4485';
        $colours[] = '91,73,31';
        $names[] = 'Pantone 5565';
        $colours[] = '139,165,156';
        $names[] = 'Pantone 7474';
        $colours[] = '0,122,135';

        echo '<h2>Standard colours (rgba)</h2>';
        echo 'This shows the full range of standard colours in varying levels of saturation.<br>';
        echo 'Firstly the colours from the 2022 redesign, followed by the previous colour palette.<br>';
        $objTable = new ToolsTable;
        $header = 'Colour';
        for ($s = 0; $s < 5; $s++) {
            $header .= ',' . $saturation[$s];
        }
        $objTable->add_header($header);

        for ($i = 0; $i < count($names); $i++) {

            $objTable->add_item($names[$i]);
            //    echo $names[$i] . '<br>';
            for ($s = 0; $s < 5; $s++) {
                $rgba = '(' . $colours[$i] . ',' . $saturation[$s] . ')';
                $tag = '<div style="background-color: rgba' . $rgba . ';">';
                //        echo substr($tag, 1);
                $objTable->add_item($tag . $rgba . '</div>');
            }
            $objTable->generate_line();
        }
        $objTable->generate_table();
//        echo '<h4>This will appear on the mailshot like this:</h4><br>';
        $target = 'administrator/index.php?option=com_config&view=component&component=com_ra_mailman';
        echo $this->toolsHelper->buildButton($target, 'Configure', True, 'red');
        echo $this->toolsHelper->backButton($this->back);
    }


    public function resetUsers() {
        ToolBarHelper::title($this->prefix . 'Users awaiting password reset');
        echo $this->breadcrumbs;
        $objTable = new ToolsTable();
        $objTable->add_header("Name,email,Preferred name,Group,Lists,ID");

        $sql = "SELECT u.id, u.name as 'User', u.email,  ";
        $sql .= 'p.home_group, p.preferred_name ';
        $sql .= 'FROM  `#__users` AS u ';
        $sql .= 'LEFT JOIN `#__ra_profiles` AS p on p.id = u.id ';
        $sql .= ' WHERE u.requireReset=1';
        $sql .= ' ORDER BY id';
//        $target = 'administrator/index.php?option=com_users&view=users';
        $rows = $this->toolsHelper->getRows($sql);
        foreach ($rows as $row) {
            $objTable->add_item($row->User);
            $objTable->add_item($row->email);
            $objTable->add_item($row->preferred_name);
            $objTable->add_item($row->home_group);
            $count = $this->countLists($row->id);
            $objTable->add_item($count);
            $objTable->add_item($row->id);
            $objTable->generate_line();
        }
        $objTable->generate_table();
        echo $this->toolsHelper->backButton($this->back);
    }

    public function recentMailshots() {
        ToolBarHelper::title('Recent Mailshots');
        echo $this->breadcrumbs;
        $mailHelper = new MailHelper;
        echo '<h4>Scope '  . $this->subheading . '</h4>';    
        $objTable = new ToolsTable();
        $objTable->add_header("List,List id,Mailshots,Recent ID,Started,Sent,Subscribers,Outstanding,");

        $sql = 'SELECT id, group_code, name, emails_outstanding ';
        $sql .= 'FROM #__ra_mail_lists ';
        $sql .= $this->buildCriterion('WHERE','group_code');
        $sql .= 'ORDER BY group_code, name';
        $target = 'administrator/index.php?option=com_ra_mailman&task=system.sendEmail&id=';
        $found = false;
        $rows = $this->toolsHelper->getRows($sql);
        foreach ($rows as $row) {
            $count_subscribers = $mailHelper->countSubscribers($row->id);

            $details = $row->group_code . '/' . $row->name;
            $objTable->add_item($details);
            $objTable->add_item($row->id);
            $sql = 'SELECT COUNT(id) FROM #__ra_mail_shots WHERE mail_list_id=' . $row->id;
            $objTable->add_item($this->toolsHelper->getValue($sql));
            $last_mailshot = $mailHelper->lastMailshot($row->id);
            $objTable->add_item($last_mailshot->id);
            // Only get users who have not yet received their message
            $subscribers = $mailHelper->getSubscribers($last_mailshot->id, 'Y');
            $actually_outstanding = count($subscribers);
            if (is_null($last_mailshot->processing_started)) {

                $objTable->add_item('');
                $objTable->add_item('');
            } else {
                $objTable->add_item($last_mailshot->processing_started);
                $objTable->add_item($last_mailshot->date_sent);
            }
            $objTable->add_item($count_subscribers);
            if ($actually_outstanding == 0) {
                $objTable->add_item('');
            } else {
                $target = 'administrator/index.php?option=com_ra_mailman&task=mail_lst.showOutstanding&id=';
                if ($row->emails_outstanding !== $actually_outstanding) {
                    $details = '(' . $row->emails_outstanding . ')';
                }
                $details .= $this->toolsHelper->buildLink($target . $last_mailshot->id, $actually_outstanding);
                $objTable->add_item($details);
            }

            if ($actually_outstanding > 0) {
                if (is_null($last_mailshot->date_sent)) {
                    $target = 'administrator/index.php?option=com_ra_mailman&task=mailshot.send&mailshot_id=';
                    $target .= $row->id . '&total=' . $actually_outstanding;
                    $link = $this->toolsHelper->buildButton($target, 'Force Send');
                    $objTable->add_item($link);
                }
            }

            $objTable->generate_line();
        }
        $objTable->generate_table();
        if ($found) {
            echo 'Dispatch of the emails will take place on the next scheduled run of the off-line batch job<br>';
            echo 'You can force immediate displatch from here<br>';
        }
        echo $this->toolsHelper->backButton($this->back);
    }


    function showCreated() {
// Shows a matrix of the number of subscriptions created from Corporate feed
// Columns are months, with a row for each mailing list
        ToolBarHelper::title('Mailman report');
        echo $this->breadcrumbs;
        $start_year = date('Y') - 1; // strtotime('-1 year'));
        $current_month = date('m');
        echo "<h2>Subscriptions by Date</h2>";
        if ($current_month == '01') {
            $month_string = '1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 1';
        } else {
            $month_string = '';
            for ($i = $current_month;
                    $i < 13;
                    $i++) {
                $month_string .= $i . ', ';
            }
            for ($i = 1;
                    $i < $current_month;
                    $i++) {
                $month_string .= $i . ', ';
            }
            $month_string .= (int) $current_month;
        }
        $months = explode(', ', $month_string);
        $yyyy = $start_year;
        $sql = 'SELECT id, group_code, name from `#__ra_mail_lists` ';
        $sql .= 'ORDER BY group_code, name';
        $lists = $this->toolsHelper->getRows($sql);
        $objTable = new ToolsTable;
        $header = 'Group, List';
        $yyyy = $start_year;

//      we need a total for each column of the report
        $total = array();

//      we need arrays to hold the actual date for each column of the report
        $param_year = array();
        $param_month = array();

        $i = 0;
        foreach ($months as $month) {
            $header .= ', ' . $month . ' ' . $yyyy;
            if ($month == '12') {
                $yyyy++;
            }
            $total[] = 0;
            $param_year[] = $yyyy;
            $param_month[] = $month;
        }

        $objTable->add_header($header);
        $report_url = 'administrator/index.php?option=com_ra_mailman&task=reports.showCreatedMonth';
        foreach ($lists as $list) {
            $objTable->add_item($list->group_code);
            $objTable->add_item($list->name);
            $yyyy = $start_year;
            $col = 0;
            foreach ($months as $month) {
//                echo "$month<br>";

                $sql = 'SELECT COUNT(s.id) ';
                $sql .= 'FROM `#__ra_mail_subscriptions` AS s ';
                $sql .= 'INNER JOIN `#__ra_mail_methods` AS `m` ON m.id = s.method_id ';
                $sql .= 'LEFT JOIN `#__users` AS `u` ON u.id = s.user_id ';
                $sql .= 'LEFT JOIN `#__ra_mail_lists` AS `l` ON l.id = s.list_id ';
                $sql .= 'WHERE `s`.`state` = 1 ';
                $sql .= 'AND `u`.`block` = 0 ';
                $sql .= 'AND l.id = ' . $list->id . ' ';
//if ($month == $current_month) {
                if ($col == 0) {
// check for any that may have been missed
                    $sql .= 'AND (YEAR(s.created) <= "' . $yyyy . '" AND MONTH(s.created) <= "' . $month . '" ';
                    $sql .= 'OR s.created IS NULL)';
//                    echo "$sql<br>";
                } else {
                    $sql .= 'AND YEAR(s.created) = "' . $yyyy . '" AND MONTH(s.created) = "' . $month . '" ';
                }

//                echo "$sql<br>";
                $count = $this->toolsHelper->getValue($sql);
                if ($count == 0) {
                    $objTable->add_item('');
                } else {
                    $link = $report_url . '&list_id=' . $list->id . '&year=' . $yyyy . '&month=' . $month;
                    $objTable->add_item($this->toolsHelper->buildLink($link, $count));
                    $total[$col] = $total[$col] + $count;
                }

                $col++;
                if ($month == '12') {
                    $yyyy++;
                }
            }
            $objTable->generate_line();
        }

// Generate a final line with totals for each month
        $target = 'administrator/index.php?option=com_ra_mailman&task=reports.showCreatedMonth';

        $objTable->add_item('<b>Total</b>');
        $objTable->add_item('');

        for ($i = 0;
                $i < 12;
                $i++) {
//            echo "i=$i," . $total[$i] . '<br>';
            if ($total[$i] == 0) {
                $objTable->add_item('');
            } else {
                $link = $target . '&year=' . $param_year[$i] . '&month=' . $param_month[$i];
                $objTable->add_item($this->toolsHelper->buildLink($link, $total[$i]));
            }
        }
        $objTable->generate_line();
        $objTable->generate_table();

        $back = "administrator/index.php?option=com_ra_mailman&view=reports";
        echo $this->toolsHelper->backButton($back);
    }

    public function showCreatedMonth() {
// Shows subscription created for the given Year / Month
        ToolBarHelper::title('Mailman report');
        echo $this->breadcrumbs . $this->breadcrumbsExtra('Subscriptions by Date', 'showCreated');
        $start_year = date('Y') - 1;
        $current_month = (int) date('m');
//        echo "Date is $current_month $start_year< br>";
        $year = $this->app->input->getInt('year', $start_year);
        $month = $this->app->input->getInt('month', $current_month);
        $list_id = $this->app->input->getInt('list_id', '0');

        $sql = 'SELECT ';
        $sql .= 'p.preferred_name AS `Preferred Name`, ';
        $sql .= 'DATE(s.created) AS `Created`, datediff(CURRENT_DATE,s.created) AS `Days ago`, ';
        $sql .= 's.modified, s.expiry_date, s.reminder_sent, ';
// $sql .= 's.ip_address, ';
        if ($list_id == 0) {
            $sql .= 'l.group_code AS `group`, l.name AS `list`, ';
        }

        $sql .= 'm.name AS `Method`, ma.name as Access ';
        $sql .= 'FROM `#__ra_mail_subscriptions` AS s ';
        $sql .= 'INNER JOIN `#__ra_mail_methods` AS `m` ON m.id = s.method_id ';
        $sql .= 'LEFT JOIN `#__ra_profiles` AS `p` ON p.id = s.user_id ';
        $sql .= 'LEFT JOIN `#__ra_mail_lists` AS `l` ON l.id = s.list_id ';
        $sql .= 'LEFT JOIN #__ra_mail_access AS ma ON ma.id = s.record_type ';
        $sql .= 'WHERE s.state=1 ';
        if ($list_id == 0) {
            $title = '';
        } else {
            $item = $this->toolsHelper->getItem('SELECT group_code, name FROM `#__ra_mail_lists` WHERE id=' . $list_id);
            $sql .= 'AND l.id=' . $list_id . ' ';
            $title = ', List=' . $item->group_code . ' ' . $item->name;
        }
        if ($month == $current_month) {
            if ($year == $start_year) {
//              check for any that may have been missed
                echo '<h2>Subscriptions created on or before ' . $month . ' ' . $year;
                $sql .= 'AND (YEAR(s.created) <= "' . $year . '" AND MONTH(s.created) <="' . $month . '" ';
                echo $sql;
                $sql .= 'OR s.created IS NULL)';
            } else {
                echo '<h2>Subscriptions created on or after ' . $month . ' ' . $year;
                $sql .= 'AND (YEAR(s.created) >= "' . $year . '" AND MONTH(s.created) >="' . $month . '") ';
            }
        } else {
            echo '<h2>Subscriptions created in ' . $month . ' ' . $year;
            $sql .= 'AND YEAR(s.created)="' . $year . '" AND MONTH(s.created)="' . $month . '" ';
        }
        echo $title . '</h2>';
        $sql .= 'ORDER BY s.created, p.preferred_name ';
        $this->toolsHelper->showQuery($sql);
        $back = "administrator/index.php?option=com_ra_mailman&task=reports.showCreated";

        echo $this->toolsHelper->backButton($back);
    }

    function showDue() {
// Shows a matrix of the number of subscriptions due for renewal
// Columns are months, with a row for each mailing list
        ToolBarHelper::title('Mailman report');
        echo $this->breadcrumbs;
      
        $current_year = date('Y');
        $current_month = date('m');
        echo "<h2>Renewals by Date</h2>";
        echo '<h4>Scope '  . $this->subheading . '</h4>';
        if ($current_month == '01') {
            $month_string = '1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 1';
        } else {
            $month_string = '';
            for ($i = $current_month;
                    $i < 13;
                    $i++) {
                $month_string .= $i . ', ';
            }
            for ($i = 1;
                    $i < $current_month;
                    $i++) {
                $month_string .= $i . ', ';
            }
            $month_string .= (int) $current_month;
        }
        $months = explode(', ', $month_string);
        $yyyy = $current_year;
        $sql = 'SELECT id, group_code, name from `#__ra_mail_lists` ';
        $sql .= $this->buildCriterion('WHERE','group_code');
        $sql .= 'ORDER BY group_code, name';
        
        $lists = $this->toolsHelper->getRows($sql);
        $objTable = new ToolsTable;
        $header = 'Group, List';

//      we need a total for each column of the report
        $total = array();

//      we need arrays to hold the actual date for each column of the report
        $param_year = array();
        $param_month = array();

        $i = 0;
        foreach ($months as $month) {
            $header .= ', ' . $month . ' ' . $yyyy;
            if ($month == '12') {
                $yyyy++;
            }
            $total[] = 0;
            $param_year[] = $yyyy;
            $param_month[] = $month;
        }

        $objTable->add_header($header);
        $report_url = 'administrator/index.php?option=com_ra_mailman&task=reports.showSubscriptionsDue';
        foreach ($lists as $list) {
            $objTable->add_item($list->group_code);
            $objTable->add_item($list->name);
            $yyyy = $current_year;
            $col = 0;
            foreach ($months as $month) {
//                echo "$month<br>";

                $sql = 'SELECT COUNT(s.id) ';
                $sql .= 'FROM `#__ra_mail_subscriptions` AS s ';
                $sql .= 'INNER JOIN `#__ra_mail_methods` AS `m` ON m.id = s.method_id ';
                $sql .= 'LEFT JOIN `#__users` AS `u` ON u.id = s.user_id ';
                $sql .= 'LEFT JOIN `#__ra_mail_lists` AS `l` ON l.id = s.list_id ';
                $sql .= 'WHERE `s`.`state` = 1 ';
                $sql .= 'AND `u`.`block` = 0 ';
                $sql .= 'AND l.id = ' . $list->id . ' ';
//if ($month == $current_month) {
                if ($col == 0) {
// check for any that may have been missed
                    $sql .= 'AND (YEAR(s.expiry_date) <= "' . $yyyy . '" AND MONTH(s.expiry_date) <= "' . $month . '" ';
                    $sql .= 'OR s.expiry_date IS NULL)';
//                    echo "$sql<br>";
                } else {
                    $sql .= 'AND YEAR(s.expiry_date) = "' . $yyyy . '" AND MONTH(s.expiry_date) = "' . $month . '" ';
                }

//                echo "$sql<br>";
                $count = $this->toolsHelper->getValue($sql);
                if ($count == 0) {
                    $objTable->add_item('');
                } else {
                    $details = '';
                    $link = $report_url . '&list_id=' . $list->id . '&year=' . $yyyy . '&month=' . $month;
                    $details .= $this->toolsHelper->buildLink($link, $count);

                    if ($col == 0) {
                        $target_renewals = 'administrator/index.php?option=com_ra_mailman&task=system.checkRenewalsForList';
//echo $this->toolsHelper->buildlink($target_email . $item->user_id . '&list_id=' . $item->list_id, '<i class="fa-solid fa-calendar-days"></i>');
                        $details .= $this->toolsHelper->buildlink($target_renewals . '&list_id=' . $list->id, '<i class="icon-envelope"></i>');
                    }

                    $objTable->add_item($details);
                    $total[$col] = $total[$col] + $count;
                }

                $col++;
                if ($month == '12') {
                    $yyyy++;
                }
            }
            $objTable->generate_line();
        }

// Generate a final line with totals for each month
        $target = 'administrator/index.php?option=com_ra_mailman&task=reports.showSubscriptionsDue';

        $objTable->add_item('<b>Total</b>');
        $objTable->add_item('');

        for ($i = 0;
                $i < 12;
                $i++) {
//            echo "i=$i," . $total[$i] . '<br>';
            if ($total[$i] == 0) {
                $objTable->add_item('');
            } else {
                $link = $target . '&year = ' . $param_year[$i] . '&month = ' . $param_month[$i];
                $objTable->add_item($this->toolsHelper->buildLink($link, $total[$i]));
            }
        }
        $objTable->generate_line();
        $objTable->generate_table();

        $back = "administrator/index.php?option=com_ra_mailman&view=reports";
        echo $this->toolsHelper->backButton($back);
    }

    public function showLogfile() {
        echo $this->breadcrumbs;
        $offset = $this->app->input->getCmd('offset', '0');
        $next_offset = $offset - 1;
        $previous_offset = $offset + 1;
        $rs = "";

        $date_difference = (int) $offset;
        $today = date_create(date("Y-m-d 00:00:00"));
        if ($date_difference === 0) {
            $target = $today;
        } else {
            if ($date_difference > 0) { // positive number
                $target = date_add($today, date_interval_create_from_date_string("-" . $date_difference . " days"));
            } else {
                $target = date_add($today, date_interval_create_from_date_string($date_difference . " days"));
            }
        }
        ToolBarHelper::title($this->prefix . 'Logfile records for ' . date_format($target, "D d M"));

        $sql = "SELECT date_format(log_date, '%a %e-%m-%y') as Date, ";
        $sql .= "date_format(log_date, '%H:%i:%s.%u') as Time, ";
        $sql .= "record_type, ";
        $sql .= "ref, ";
        $sql .= "message ";
        $sql .= "FROM #__ra_logfile ";
        $sql .= "WHERE log_date >='" . date_format($target, "Y/m/d H:i:s") . "' ";
        $sql .= "AND log_date <'" . date_format($target, "Y/m/d 23:59:59") . "' ";
        $sql .= "ORDER BY log_date DESC, record_type ";
        if ($this->toolsHelper->showSql($sql)) {
            echo "<h5>End of logfile records for " . date_format($target, "D d M") . "</h5>";
        } else {
            echo 'Error: ' . $this->toolsHelper->error . '<br>';
        }

        echo $this->toolsHelper->buildLink("administrator/index.php?option=com_ra_tools&task=reports.showLogfile&offset=" . $previous_offset, "Previous day", False, 'btn btn-small button-new');
        if ($next_offset >= 0) {
            echo $this->toolsHelper->buildLink("administrator/index.php?option=com_ra_tools&task=reports.showLogfile&offset=" . $next_offset, "Next day", False, 'btn btn-small button-new');
        }
        $target = 'administrator/index.php?option=com_ra_tools&view=reports';
        echo $this->toolsHelper->backButton($target);
    }

    public function showMailshotsByMonth() {
        echo $this->breadcrumbs; // . $this->breadcrumbsExtra('
        echo '<h4>Scope '  . $this->subheading . '</h4>';
        $field = 'date_sent';
        $table = ' #__ra_mail_shots';
        $criteria = '';
        $title = 'Mailshots by month';
        $link = 'administrator/index.php?option=com_ra_mailman&task=reports.showMailshotsForMonth';
        $back = 'administrator/index.php?option=com_ra_mailman&view=reports';
        $this->toolsHelper->showDateMatrix($field, $table, $criteria, $title, $link, $back);
    }

    public function showMailshotsForMonth() {
        $year = $this->app->input->getInt('year', '2025');
        $month = $this->app->input->getInt('month', '5');
        ToolBarHelper::title('Mailshots for ' . $month . '/' . $year);
        echo $this->breadcrumbs . $this->breadcrumbsExtra('Mailshots by month', 'showMailshotsByMonth');
        echo '<h4>Scope '  . $this->subheading . '</h4>';
        $sql = 'SELECT ms.date_sent, ms.title, ml.created_by, ';
        $sql .= 'ml.group_code, ml.name,p.preferred_name ';
        $sql .= 'FROM `#__ra_mail_lists` AS ml ';
        $sql .= 'LEFT JOIN #__ra_mail_shots AS `ms` ON `ms`.mail_list_id = ml.`id` ';
        $sql .= 'LEFT JOIN #__ra_profiles AS `p` ON `p`.id = ml.created_by ';
        $sql .= 'WHERE YEAR(ms.date_sent)="' . $year . '" AND MONTH(ms.date_sent)="' . $month . '" ';
        $sql .= 'ORDER BY ms.date_sent ';
//       echo $sql . '<br>';
//        return;
        $rows = $this->toolsHelper->getRows($sql);
        $objTable = new ToolsTable;
        $objTable->add_header('Date sent,List,Group,Title,Author');
        foreach ($rows as $row) {
            $objTable->add_item(HTMLHelper::_('date', $row->date_sent, 'd M y'));

            $objTable->add_item($row->name);
            $objTable->add_item($row->group_code);
            $objTable->add_item($row->title);
            if (is_null($row->preferred_name)) {
                $contact = $row->created_by;
            } else {
                $contact = $row->preferred_name;
            }
            $objTable->add_item($contact);

            $objTable->add_item($row->bookable);

            $objTable->generate_line();
        }
        $objTable->generate_table();
        echo count($rows) . ' Mailshots for this month<br>';
        $target = "administrator/index.php?option=com_ra_mailman&task=reports.showMailshotsByMonth";
        echo $this->toolsHelper->backButton($target);
    }

   
    public function showSubscriptionsByStatus() {
        ToolBarHelper::title($this->prefix . 'Subscriptions by Status');
        echo $this->breadcrumbs;
        $table = new ToolsTable();
        $toolsHelper = new ToolsHelper;

        $sql = 'SELECT s.state, COUNT(s.id) ';
        $sql .= 'FROM #__ra_mail_subscriptions AS s ';
        $sql .= ' GROUP BY s.state ';
        $sql .= ' ORDER BY s.state';
        $toolsHelper->showSql($sql);

//        $table->add_header("Name,0,1");
//        $sql = 'SELECT id, name, state ';
//        $sql .= 'FROM #__ra_mail_lists ';
//        $sql .= 'ORDER BY name';
//        $lists = $toolsHelper->getRows($sql);
//        foreach ($lists as $list) {
//            $table->add_item($list->name);
//
//            $sql = 'SELECT s.state, COUNT(s.id) ';
//            $sql .= 'FROM #__ra_mail_subscriptions AS s ';
//            $sql .= 'INNER JOIN #__ra_mail_lists AS l  ON l.id = s.list_id ';
//
//            $sql .= 'WHERE s.list_id=' . $list->id;
//            $sql .= ' GROUP BY s.state ';
//            $sql .= ' ORDER BY s.state';
//            echo $sql . '<br>';
//            $rows = $toolsHelper->getRows($sql);
//            foreach ($rows as $row) {
//                $table->add_item($row->state);
//            }
//            $table->add_line;
//        }
//        $table->generate_table();
        echo $this->toolsHelper->backButton($this->back);
    }

    public function showSubscriptionsDue() {
// Shows subscription due for the given Year / Month
        ToolBarHelper::title('Mailman report');
        echo $this->breadcrumbs . $this->breadcrumbsExtra('Renewals by Date', 'showDue');
        $year = $this->app->input->getInt('year', $current_year);
        $month = $this->app->input->getInt('month', $current_month);
        $list_id = $this->app->input->getInt('list_id', '0');

        $table = new ToolsTable();
        $table->add_header("Preferred name,Method,Access,Due,Days to go,Created,Modified,Reminder sent,,");
        $current_year = date('Y');
        $current_month = (int) date('m');
//        echo "Date is $current_month $current_year< br>";

        $target_email = 'administrator/index.php?option=com_ra_mailman&task=subscription.sendRenewal&callback=2';
        $target_email .= '&year=' . $year . '&month=' . $month . '&user_id=';
        $target_info = 'administrator/index.php?option=com_ra_mailman&task=subscription.showDetails';
        $target_info .= '&callback=3&list_id=' . $list_id;
        $target_info .= '&year=' . $year . '&month=' . $month . '&id=';

        $sql = 'SELECT s.id, ';
        $sql .= 'DATE(s.expiry_date), datediff(s.expiry_date, CURRENT_DATE) AS `Days`, ';
        $sql .= 's.created, s.modified, s.reminder_sent, s.user_id, ';
// $sql .= 's.ip_address, ';
        if ($list_id == 0) {
            $sql .= 'l.group_code AS `group`, l.name AS `list`, ';
        }
        $sql .= 'p.preferred_name, ';
        $sql .= 'm.name AS `Method`, ma.name as Access ';
        $sql .= 'FROM `#__ra_mail_subscriptions` AS s ';
        $sql .= 'INNER JOIN `#__ra_mail_methods` AS `m` ON m.id = s.method_id ';
        $sql .= 'LEFT JOIN `#__users` AS `u` ON u.id = s.user_id ';
        $sql .= 'LEFT JOIN `#__ra_profiles` AS `p` ON p.id = s.user_id ';
        $sql .= 'LEFT JOIN `#__ra_mail_lists` AS `l` ON l.id = s.list_id ';
        $sql .= 'LEFT JOIN #__ra_mail_access AS ma ON ma.id = s.record_type ';
        $sql .= 'WHERE s.state=1 ';
        if ($list_id == 0) {
            $title = '';
        } else {
            $item = $this->toolsHelper->getItem('SELECT group_code, name FROM `#__ra_mail_lists` WHERE id=' . $list_id);
            $sql .= 'AND l.id=' . $list_id . ' ';
            $title = ', List=' . $item->group_code . ' ' . $item->name;
        }
        if ($month == $current_month) {
            if ($year == $current_year) {
//              check for any that may have been missed
                echo '<h2>Subscriptions due on or before ' . $month . ' ' . $year;
                $sql .= 'AND (YEAR(s.expiry_date) <= "' . $year . '" AND MONTH(s.expiry_date) <="' . $month . '" ';
                $sql .= 'OR s.expiry_date IS NULL)';
            } else {
                echo '<h2>Subscriptions due on or after ' . $month . ' ' . $year;
                $sql .= 'AND (YEAR(s.expiry_date) >= "' . $year . '" AND MONTH(s.expiry_date) >="' . $month . '") ';
            }
        } else {
            echo '<h2>Subscriptions due in ' . $month . ' ' . $year;
            $sql .= 'AND YEAR(s.expiry_date)="' . $year . '" AND MONTH(s.expiry_date)="' . $month . '" ';
        }
        echo $title . '</h2>';
        $sql .= 'ORDER BY s.expiry_date, u.name ';

//$this->toolsHelper->showQuery($sql);
        $rows = $this->toolsHelper->getRows($sql);
        foreach ($rows as $row) {
            $table->add_item($row->preferred_name);
            $table->add_item($row->Method);
            $table->add_item($row->Access);
            $table->add_item($row->expiry_date);
            $table->add_item($row->Days);
            $table->add_item($row->created);
            $table->add_item($row->modified);
            $table->add_item($row->reminder_sent);
            $details = $this->toolsHelper->buildlink($target_info . $row->id, '<i class="icon-info"></i>');
            $details .= $this->toolsHelper->buildlink($target_email . $row->user_id . '&list_id=' . $list_id, '<i class="icon-envelope"></i>');
            $table->add_item($details);
            $table->add_item($row->expiry_date);
//                       $table->add_item($row->id);
            $table->generate_line();
        }
        $table->generate_table();
        $back = "administrator/index.php?option=com_ra_mailman&task=reports.showDue";

        echo $this->toolsHelper->backButton($back);
    }

    public function subscriptionsReportDetail() {
        // shows all Users for the given mail-list
        $list_id = $this->app->input->getInt('id', '2');
        $sql = 'SELECT group_code, name, owner_id FROM #__ra_mail_lists ';
        $sql .= 'WHERE id=' . $list_id;
        $item = $this->toolsHelper->getItem($sql);
        ToolBarHelper::title('Subscriptions Report for ' . $item->group_code . ' ' . $item->name);
        echo $this->breadcrumbs . $this->breadcrumbsExtra('Subscriptions Report', 'subscriptionsReport');
        $table_headings = 'Group,User,';
        // Check whether to show email addresses
        if ($this->toolsHelper->isSuperuser()) {
            $showEmail = true;
            $table_headings .= 'email,';
        } else {
            $showEmail = false;
        }
        $table_headings .= 'Access,Expires,Status,';
        $sql = "SELECT ";
        $sql .= "p.preferred_name,g.name AS `group_name`, u.email,  ";
        $sql .= "p.home_group, ";
        $sql .= "m.name as 'Method', ma.name as Type,";
        $sql .= "s.user_id,  s.record_type, ";

        $sql .= "s.expiry_date, s.created, s.reminder_sent, s.state, ";
        $sql .= "CASE WHEN s.state =1 THEN 'Active' ELSE 'Inactive' END AS 'Status' ";
        $sql .= 'FROM `#__users` as u ';
        $sql .= 'INNER JOIN #__ra_mail_subscriptions AS s ON u.id = s.user_id ';
        $sql .= 'INNER JOIN #__ra_mail_methods AS m ON m.id = s.method_id ';
        $sql .= 'LEFT JOIN #__ra_profiles AS p ON p.id = s.user_id ';
        $sql .= 'LEFT JOIN #__ra_groups AS g ON g.code = p.home_group ';
        $sql .= 'LEFT JOIN #__ra_mail_access AS ma ON ma.id = s.record_type ';
        $sql .= 'WHERE u.block=0 ';
        $sql .= 'AND s.list_id=' . $list_id;
        $sql .= ' ORDER BY g.name, p.preferred_name';
        //       echo $sql;
        // return;
        $rows = $this->toolsHelper->getRows($sql);
        $table = new ToolsTable;
        $table->add_header($table_headings);
        foreach ($rows as $row) {

            $table->add_item($row->group_name);
            //        $table->add_item($row->group_code);
            //
            if (is_null($row->preferred_name)) {
                $contact = $row->created_by;
            } else {
                $contact = $row->preferred_name;
            }
            $table->add_item($contact);
            if ($showEmail) {
                $table->add_item($row->email);
            }
            if ($item->owner_id == $row->user_id) {
                $table->add_item('Owner');
            } else {
                if ($row->record_type == 2) {
                    $table->add_item('Author');
                } else {
                    $table->add_item('Subscriber');
                }
            }

            $table->add_item($row->expiry_date);
            $table->add_item($row->Status);
            $table->generate_line();
        }
        $table->generate_table();
        $target = "administrator/index.php?option=com_ra_mailman&task=reports.subscriptionsSummary";
        echo $this->toolsHelper->backButton($target);
    }

    public function subscriptionsSummary() {
        ToolBarHelper::title('Subscriptions Summary');
        echo $this->breadcrumbs; // . $this->breadcrumbsExtra('Mailshots by month', 'showMailshotsByMonth');
        echo '<h4>Scope '  . $this->subheading . '</h4>'; 
        $sql = 'SELECT l.id, l.group_code, l.name, l.state, p.preferred_name ';
        $sql .= 'FROM #__ra_mail_lists AS l ';
        $sql .= 'INNER JOIN #__ra_profiles AS p ON p.id=l.owner_id ';
        $sql .= $this->buildCriterion('WHERE','l.group_code');
        $rows = $this->toolsHelper->getRows($sql);

        $sql_lookup = 'SELECT COUNT(id) FROM #__ra_mail_subscriptions ';
        $sql_lookup .= 'WHERE list_id=';

        $target = 'administrator/index.php?option=com_ra_mailman&task=reports.subscriptionsReportDetail&id=';
        $table = new ToolsTable;
        $table->add_header('Name,Group,Owner,Active subscribers,Inactive subscribers');
        foreach ($rows as $row) {
            if ($row->state == 1) {
                $name = $row->name;
            } else {
                $name = '<div style="color:red">' . $row->name . '</div>';
            }
            $table->add_item($name);
            $table->add_item($row->group_code);

            $table->add_item($row->preferred_name);

            $count = $this->toolsHelper->getValue($sql_lookup . $row->id . ' AND state=1');
            $table->add_item($this->toolsHelper->buildLink($target . $row->id, $count));

            $count = $this->toolsHelper->getValue($sql_lookup . $row->id . ' AND state=0');
            $table->add_item($count);

            $table->generate_line();
        }
        $table->generate_table();
        $target = "administrator/index.php?option=com_ra_mailman&view=reports";
        echo $this->toolsHelper->backButton($target);
    }

}
