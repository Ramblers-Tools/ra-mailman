<?php

/**
 * @version     4.7.0
 * @package     com_ra_mailman
 *
 * @copyright   Copyright (C) 2005 - 2019 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * 05/01/24 CB Created
 * 08/01/24 CB use SubscriptionHelper
 * 14/11/24 CB duffRecords
 * 26/05/25 CB checkSchema / ra_reports
 * 29/06/25 CB use UserHelper from Tools, not Mailman; purgeBlockedUsers
 * 11/08/25 CB allow forced send of emails
 * 03/11/25 CB delete bookings, return to reports menu after Purge All
 * 28/04/26 CB temp fix for updating groups table
 */

namespace Ramblers\Component\Ra_mailman\Administrator\Controller;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\Input\Input;
use Ramblers\Component\Ra_mailman\Site\Helpers\Mailhelper;
use Ramblers\Component\Ra_mailman\Site\Helpers\SubscriptionHelper;
//use Ramblers\Component\Ra_mailman\Site\Helpers\UserHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\SchemaHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\UserHelper;

class SystemController extends FormController {

    protected $back;
    protected $app;
    protected $toolsHelper;

    public function __construct(
            $config = [],
            MVCFactoryInterface $factory = null,
            CMSApplication $app = null,
            Input $input = null
    ) {
        parent::__construct($config, $factory, $app, $input);

        $this->toolsHelper = new ToolsHelper;
        $this->app = Factory::getApplication();
        $this->back = 'administrator/index.php?option=com_ra_tools&view=dashboard';

        $wa = $this->app->getDocument()->getWebAssetManager();
        $wa->registerAndUseStyle('ramblers', 'com_ra_tools/ramblers.css');
    }

    // echo $this->toolsHelper->showQuery($sql);

    public function checkRenewals() {
        // invoked from dashboard
// initialise the Helper classes
        $Mailhelper = new Mailhelper();
        $toolsHelper = new ToolsHelper;
        $back = 'administrator/index.php?option=com_ra_mailman&view=subscriptions';

        /*
         *
         * UPDATE sta_ra_mail_subscriptions SET expiry_date = DATE_ADD(expiry_date,INTERVAL 12 MONTH) WHERE id=1
         * UPDATE sta_ra_mail_subscriptions SET expiry_date = DATE_ADD(created,INTERVAL 12 MONTH) WHERE state=1 and method_id=4
         * UPDATE sta_ra_mail_subscriptions SET expiry_date = DATE_ADD(created,INTERVAL 12 MONTH) WHERE state=1 and list_id=1
         * UPDATE sta_ra_mail_subscriptions SET expiry_date = NULL WHERE expiry_date='0000-00-00 00:00:00'
         */
//==============================================================================
// find Subscription records close to their expiry date
//==============================================================================
// 08/01/24 - next few lines are temporary
        $sql = 'UPDATE `#__ra_mail_subscriptions` SET expiry_date = current_date() WHERE expiry_date IS NULL';
        $toolsHelper->executeCommand($sql);
        $sql = 'UPDATE `#__ra_mail_subscriptions` SET reminder_sent = NULL';
        $toolsHelper->executeCommand($sql);

        $notify_interval = ComponentHelper::getParams('com_ra_mailman')->get('notify_interval');

//        $this->logMessage("R2", 2, "reminders.php: Seeking Subscriptions in " . $notify_interval) . ' days time';
        echo "Seeking Subscriptions in " . $notify_interval . ' days time<br>' . PHP_EOL;

        $sql = "SELECT s.user_id, MIN(datediff(expiry_date, CURRENT_DATE)) ";
        $sql .= "FROM `#__ra_mail_subscriptions` AS s ";
        $sql .= "WHERE (s.state =1) ";
        $sql .= "AND ((datediff(expiry_date, CURRENT_DATE) < " . $notify_interval . ') ';
        $sql .= " AND (s.reminder_sent IS NULL)) ";
        $sql .= "GROUP BY s.user_id ";
        $sql .= "ORDER BY s.user_id ";
        $sql .= 'LIMIT 5';
        //       echo $sql . PHP_EOL;
        $rows = $this->toolsHelper->getRows($sql);
        if ($this->toolsHelper->rows == 0) {
            echo 'None found<br>';
            echo $toolsHelper->backButton($back);
            return;
        }
        echo $this->toolsHelper->rows . ' records found <br>';
//        $toolsHelper->showQuery($sql);
//        $this->logMessage("R3", 3, "Number of Subscriptions due=" . $toolsHelper->rows);
        $sql = 'UPDATE `#__ra_mail_subscriptions` SET reminder_sent=CURRENT_DATE WHERE id=';

        foreach ($rows as $row) {
            echo "id=$row->user_id<br>";
//                $this->logMessage("R4", $row->user_id, "id:" . $row->id . "," . $row->expiry_date);
            if ($Mailhelper->sendRenewal($row->user_id)) {
                echo $row->user_id . ' renewed ' . '<br>';
            } else {
                echo $row->user_id . ' failed ' . '<br>';
            }
        }

        echo '<br>';
        die;
        echo $toolsHelper->backButton($back);
    }

    public function checkRenewalsForList() {

        $sql = 'UPDATE #__ra_mail_subscriptions SET expiry_date = created WHERE state=1 and list_id=2';
        $this->toolsHelper->executeCommand($sql);
        // Sends email renewalks for a single list
        // invoked from the report "Subscription due"
        $back = 'administrator/index.php?option=com_ra_mailman&view=reports';
        $Mailhelper = new Mailhelper();
        $list_id = $this->app->input->getInt('list_id', '1');
        $list_name = $Mailhelper->lookupList($list_id);
        $this->app->enqueueMessage('Renewal emails sent for list ' . $list_name, 'message');
//        $objSubscription = new SubscriptionHelper;
        $sql = 'SELECT user_id, list_id ';
        $sql .= 'FROM `#__ra_mail_subscriptions`  ';
        $sql .= 'WHERE (state =1) ';
        $sql .= 'AND (datediff(expiry_date, CURRENT_DATE) < 0) ';
//        $sql .= ' AND (reminder_sent IS NULL) ';
        $sql .= 'AND (list_id="' . $list_id . '") ';
        $sql .= "ORDER BY user_id ";

//        echo $sql . PHP_EOL;
//        die;
        $rows = $this->toolsHelper->getRows($sql);
        if ($this->toolsHelper->rows == 0) {
            echo 'No renewals due for list ' . $list_name . '<br>';
            echo $sql . '<br>';
            echo $this->toolsHelper->backButton($back);
            return;
        }
        echo $this->toolsHelper->rows . ' records found <br>';
//        $toolsHelper->showQuery($sql);

        $objMailhelper = new Mailhelper;
//        $objSubscription = new SubscriptionHelper;

        foreach ($rows as $row) {
            echo "user_id=$row->user_id<br>";

            $objMailhelper->sendRenewal($row->user_id, $list_id);
//                $this->logMessage("R4", $row->user_id, "id:" . $row->id . "," . $row->expiry_date);
//            if ($Mailhelper->sendRenewal($row->user_id, $list_id)) {
//                echo $row->user_id . ' renewed ' . '<br>';
//            } else {
//                echo $row->user_id . ' failed ' . '<br>';
//            }
        }

        echo 'Renewals for' . $list_id . '<br>';
        die;
        $this->setRedirect('/administrator/index.php?option=com_ra_mailman&task=reports.showDue');
    }

    public function checkSchema() { //administrator/index.php?option=com_ra_mailman&task=system.checkSchema
        $toolsHelper = new ToolsHelper;
        if (!$toolsHelper->isSuperuser()) {
            return;
        }
        $helper = New SchemaHelper;
        /*
          // table ra_import_reports
          $details = '(
          `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
          `date_phase1` DATETIME NOT NULL ,
          `date_completed` DATETIME NULL ,
          `method_id` int(11) NOT NULL,
          `list_id` int(11) NOT NULL,
          `user_id` int(11) NOT NULL,
          `num_records` INT  NOT NULL DEFAULT "0",
          `num_errors` INT  NOT NULL DEFAULT "0",
          `num_users` INT  NOT NULL DEFAULT "0",
          `num_subs` INT  NOT NULL DEFAULT "0",
          `num_lapsed` INT  NOT NULL DEFAULT "0",
          `ip_address` VARCHAR(255)  NULL  DEFAULT "",
          `error_report` MEDIUMTEXT  DEFAULT NULL,
          `new_users` MEDIUMTEXT DEFAULT NULL,
          `new_subs` MEDIUMTEXT DEFAULT NULL,
          `lapsed_members` MEDIUMTEXT DEFAULT NULL,
          `input_file` VARCHAR(255) NOT NULL,
          `created` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `created_by` INT NULL DEFAULT "0",
          `modified` DATETIME NULL DEFAULT NULL,
          `modified_by` INT NULL DEFAULT "0",
          `checked_out_time` DATETIME NULL  DEFAULT NULL ,
          `checked_out` INT NULL,
          `state` TINYINT(1)  NULL  DEFAULT 1,
          PRIMARY KEY (`id`)
          ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;';
          $helper->checkTable('ra_import_reports', $details);
         */
        // UPDATE `j5_ra_profiles` set checked_out_time = NULL WHERE `checked_out_time`IS NOT NULL
        // ALTER TABLE `j5_ra_profiles` DROP PRIMARY KEY
        // ALTER TABLE `j5_ra_profiles` CHANGE `member_id` `member_id` INT NULL DEFAULT NULL AUTO_INCREMENT, add PRIMARY KEY (`member_id`);        
        // UPDATE `j5_ra_profiles` set checked_out_time = NULL WHERE `checked_out_time`IS NOT NULL
        // ALTER TABLE `j5_ra_profiles` DROP PRIMARY KEY
        // ALTER TABLE `j5_ra_profiles` CHANGE `member_id` `member_id` INT NULL DEFAULT NULL AUTO_INCREMENT, add PRIMARY KEY (`member_id`);     
         
        $helper->checkColumn('ra_profiles', 'member_id', 'A', 'INT NULL AFTER id; ');
        $helper->checkColumn('ra_profiles', 'salesforceId', 'A', 'VARCHAR(20) AFTER member_id; ');
        $helper->checkColumn('ra_profiles', 'membershipNumber', 'A', 'INT NULL AFTER preferred_name; ');
        $helper->checkColumn('ra_profiles', 'memberType', 'A', 'VARCHAR(9) AFTER membershipNumber; ');
        $helper->checkColumn('ra_profiles', 'memberTerm', 'A', 'VARCHAR(5) AFTER memberType; ');
        $helper->checkColumn('ra_profiles', 'memberStatus', 'A', 'VARCHAR(15) AFTER memberTerm; ');
        $helper->checkColumn('ra_profiles', 'membershipArrangement', 'A', 'VARCHAR(10) AFTER memberStatus; ');
        $helper->checkColumn('ra_profiles', 'jointWith', 'A', 'INT NULL AFTER membershipArrangement; ');
        $helper->checkColumn('ra_profiles', 'title', 'A', 'VARCHAR(6) AFTER jointWith; ');
        $helper->checkColumn('ra_profiles', 'initials', 'A', 'VARCHAR(6) AFTER title; ');
        $helper->checkColumn('ra_profiles', 'firstName', 'A', 'VARCHAR(100) AFTER initials; ');
        $helper->checkColumn('ra_profiles', 'lastName', 'A', 'VARCHAR(100) AFTER firstName; ');
        $helper->checkColumn('ra_profiles', 'address1', 'A', 'VARCHAR(100) AFTER lastName; ');
        $helper->checkColumn('ra_profiles', 'address2', 'A', 'VARCHAR(100) AFTER address1; ');
        $helper->checkColumn('ra_profiles', 'address3', 'A', 'VARCHAR(100) AFTER address2; ');
        $helper->checkColumn('ra_profiles', 'town', 'A', 'VARCHAR(100) AFTER address3; ');
        $helper->checkColumn('ra_profiles', 'county', 'A', 'VARCHAR(100) AFTER town; ');
        $helper->checkColumn('ra_profiles', 'country', 'A', 'VARCHAR(100) AFTER county; ');
        $helper->checkColumn('ra_profiles', 'postcode', 'A', 'VARCHAR(8) AFTER country; ');
        $helper->checkColumn('ra_profiles', 'email', 'A', 'VARCHAR(150) AFTER postcode; ');
        $helper->checkColumn('ra_profiles', 'landlineTelephone', 'A', 'VARCHAR(20) AFTER email; ');
        $helper->checkColumn('ra_profiles', 'mobileNumber', 'A', 'VARCHAR(100) AFTER landlineTelephone; ');
        $helper->checkColumn('ra_profiles', 'membershipExpiryDate', 'A', 'DATE NULL AFTER mobileNumber; ');
        $helper->checkColumn('ra_profiles', 'ramblersJoinedDate', 'A', 'DATE NULL AFTER membershipExpiryDate; ');
        $helper->checkColumn('ra_profiles', 'areaJoinedDate', 'A', 'DATE NULL AFTER ramblersJoinedDate; ');
        $helper->checkColumn('ra_profiles', 'groupJoinedDate', 'A', 'DATE NULL AFTER areaJoinedDate; ');
        $helper->checkColumn('ra_profiles', 'volunteer', 'A', 'CHAR(1) AFTER groupJoinedDate; ');
        $helper->checkColumn('ra_profiles', 'emailMarketingConsent', 'A', 'CHAR(1) AFTER volunteer; ');
        $helper->checkColumn('ra_profiles', 'areaMarketingConsent', 'A', 'CHAR(1) AFTER emailMarketingConsent; ');
        $helper->checkColumn('ra_profiles', 'groupMarketingConsent', 'A', 'CHAR(1) AFTER areaMarketingConsent; ');
        $helper->checkColumn('ra_profiles', 'otherMarketingConsent', 'A', 'CHAR(1) AFTER groupMarketingConsent; ');
        $helper->checkColumn('ra_profiles', 'emailPermissionLastUpdated', 'A', 'DATE NULL AFTER otherMarketingConsent; ');
        $helper->checkColumn('ra_profiles', 'postDirectMarketing', 'A', 'CHAR(1) AFTER emailPermissionLastUpdated; ');
        $helper->checkColumn('ra_profiles', 'postPermissionLastUpdated', 'A', 'DATE NULL AFTER postDirectMarketing; ');
        $helper->checkColumn('ra_profiles', 'telephoneDirectMarketing', 'A', 'CHAR(1) AFTER postPermissionLastUpdated; ');
        $helper->checkColumn('ra_profiles', 'telephonePermissionLastUpdated', 'A', 'DATE NULL AFTER telephoneDirectMarketing; ');
        $helper->checkColumn('ra_profiles', 'walkProgrammeOptOut', 'A', 'CHAR(1) AFTER telephonePermissionLastUpdated; ');
        $helper->checkColumn('ra_profiles', 'affiliateMemberPrimaryGroup', 'A', 'VARCHAR(50) AFTER walkProgrammeOptOut; ');
        $helper->checkColumn('ra_profiles', 'security_token', 'D');
        $helper->checkColumn('ra_profiles', 'subscribe', 'D');
        $helper->checkColumn('ra_profiles', 'software_version', 'D');
        $helper->checkColumn('ra_profiles', 'acknowledge_follow', 'D');
        $helper->checkColumn('ra_profiles', 'privacy_level', 'D');
        $helper->checkColumn('ra_profiles', 'mobile', 'D');
        $helper->checkColumn('ra_profiles', 'contactviatextmessage', 'D');
        $helper->checkColumn('ra_profiles', 'contactviaemail', 'D');
        $helper->checkColumn('ra_profiles', 'min_miles', 'D');
        $helper->checkColumn('ra_profiles', 'max_miles', 'D');
        $helper->checkColumn('ra_profiles', 'max_radius', 'D');
        $helper->checkColumn('ra_profiles', 'contactviatextmessage', 'D');
        $helper->checkColumn('ra_profiles', 'max_radius', 'D');
        $helper->checkColumn('ra_profiles', 'notify_joiners', 'D');
        $helper->checkColumn('ra_profiles', 'areaName', 'D');
        $helper->checkColumn('ra_profiles', 'ordering', 'D');

        $helper->checkColumn('ra_profiles', 'home_group', 'U', 'VARCHAR(4); ');
        $helper->checkColumn('ra_profiles', 'preferred_name', 'U', 'VARCHAR(100); ');
        $helper->checkColumn('ra_profiles', 'email', 'U', 'VARCHAR(100); ');
        $helper->checkColumn('ra_profiles', 'jointWith', 'U', 'INT NULL; ');
        $helper->checkColumn('ra_profiles', 'town', 'U', 'VARCHAR(100); ');
        $helper->checkColumn('ra_profiles', 'county', 'U', 'VARCHAR(100); ');
        $helper->checkColumn('ra_profiles', 'country', 'U', 'VARCHAR(100); ');
        $helper->checkColumn('ra_profiles', 'email', 'U', 'VARCHAR(100); ');
        $helper->checkColumn('ra_profiles', 'volunteer', 'U', 'CHAR(1); ');
        $helper->checkColumn('ra_profiles', 'volunteer', 'U', 'CHAR(1); ');
        $helper->checkColumn('ra_profiles', 'emailMarketingConsent', 'U', 'CHAR(1); ');
        $helper->checkColumn('ra_profiles', 'areaMarketingConsent', 'U', 'CHAR(1); ');
        $helper->checkColumn('ra_profiles', 'groupMarketingConsent', 'U', 'CHAR(1); ');
        $helper->checkColumn('ra_profiles', 'otherMarketingConsent', 'U', 'CHAR(1); ');
        $helper->checkColumn('ra_profiles', 'postDirectMarketing', 'U', 'CHAR(1); ');
        $helper->checkColumn('ra_profiles', 'telephoneDirectMarketing', 'U', 'CHAR(1); ');
        $helper->checkColumn('ra_profiles', 'walkProgrammeOptOut', 'U', 'CHAR(1); ');
        


        $target = 'administrator/index.php?option=com_ra_tools&view=dashboard';
        echo $toolsHelper->backButton($target);
    }

    public function duffRecords() {
        // one-off clean up to tidy the database
        ToolBarHelper::title('System maintenance');
        $toolsHelper = new ToolsHelper;
        if (!$toolsHelper->isSuperuser()) {
            return;
        }

        $sql = 'SELECT s.id, ';
        $sql .= 'u.name AS `Subscriber`, ';
        $sql .= 'DATE(s.created) AS `Created`, ';
        $sql .= 's.modified, s.expiry_date, s.reminder_sent,';
        if ($list_id == 0) {
            $sql .= 'l.group_code AS `group`, l.name AS `list`, ';
        }
        $sql .= 'm.name AS `Method`, ma.name as Access ';
        $sql .= 'FROM `#__ra_mail_subscriptions` AS s ';
        $sql .= 'INNER JOIN `#__ra_mail_methods` AS `m` ON m.id = s.method_id ';
        $sql .= 'LEFT JOIN `#__users` AS `u` ON u.id = s.user_id ';
        $sql .= 'LEFT JOIN `#__ra_mail_lists` AS `l` ON l.id = s.list_id ';
        $sql .= 'LEFT JOIN #__ra_mail_access AS ma ON ma.id = s.record_type ';
        $sql .= 'LEFT JOIN #__ra_profiles as p ON p.id = s.user_id ';
        $sql .= 'WHERE u.id IS NULL ';
        $sql .= 'OR l.id IS NULL ';
        $sql .= 'OR m.id IS NULL ';
        $sql .= 'OR ma.id IS NULL ';
        $sql .= 'OR p.id IS NULL ';
        $rows = $toolsHelper->getRows($sql);
        if ($toolsHelper->rows == 0) {
            echo 'No unmatched subscriptions ' . '<br>';
        } else {
            echo 'Deleting unmatched subscriptions ' . '<br>';
            $toolsHelper->showQuery($sql);
            foreach ($rows as $row) {
                $sql_audit = 'SELECT id FROM #__ra_mail_subscriptions_audit ';
                $sql_audit .= 'WHERE object_id=' . $row->id;
                $audit_rows = $toolsHelper->getRows($sql_audit);
                foreach ($audit_rows as $audit_row) {
                    $sql = 'DELETE FROM #__ra_mail_subscriptions_audit ';
                    $sql .= 'WHERE object_id=' . $audit_row->id;
                    echo $sql . '<br>';
                    $toolsHelper->executeCommand($sql);
                }

                $sql = 'DELETE FROM  #__ra_mail_subscriptions ';
                $sql .= 'WHERE id=' . $row->id;
                echo $sql . '<br>';
                $toolsHelper->executeCommand($sql);
            }
        }

        // see if any unlinked audit records for subscriptions
        $sql = 'SELECT a.id, a.object_id, a.created ';
        $sql .= 'FROM #__ra_mail_subscriptions_audit AS a ';
        $sql .= 'LEFT JOIN `#__ra_mail_subscriptions` AS `s` ON s.id = a.object_id ';
        $sql .= 'WHERE s.id IS NULL ';
        $sql .= 'ORDER BY a.id ';
        echo $sql . '<br>';
        $rows = $toolsHelper->getRows($sql);
        if ($toolsHelper->rows == 0) {
            echo 'No unmatched mapping records ' . '<br>';
        } else {
            $toolsHelper->showQuery($sql);
            foreach ($rows as $row) {
                $sql = 'DELETE FROM #__ra_mail_subscriptions_audit ';
                $sql .= 'WHERE id=' . $row->id;
                echo $sql . '<br>';
                $toolsHelper->executeCommand($sql);
            }
        }

        // see if any unlinked records for usergroup_map
        $sql = 'SELECT m.user_id, m.group_id FROM #__user_usergroup_map as m ';
        $sql .= 'LEFT JOIN #__users as u ON u.id = m.user_id ';
        $sql .= 'WHERE u.id IS NULL ';
        $sql .= 'ORDER BY m.user_id ';
        $rows = $toolsHelper->getRows($sql);
        if ($toolsHelper->rows == 0) {
            echo 'No unmatched mapping records ' . '<br>';
        } else {
            $toolsHelper->showQuery($sql);
            foreach ($rows as $row) {
                $sql = 'DELETE FROM  #__user_usergroup_map ';
                $sql .= 'WHERE user_id=' . $row->user_id;
                echo $sql . '<br>';
                $toolsHelper->executeCommand($sql);
            }
        }

        echo '<br>';
        $target = 'administrator/index.php?option=com_ra_tools&view=dashboard';
        echo $toolsHelper->backButton($target);
    }

    function logMessage($record_type, $ref, $message) {
        $db = Factory::getDbo();

// Create a new query object.
        $query = $db->getQuery(true);
// Prepare the insert query.
        $query
                ->insert($db->quoteName('#__ra_logfile'))
                ->set('record_type =' . $db->quote($record_type))
                ->set('ref = ' . $db->quote($record_type))
                ->set('message =' . $db->quote($message));

// Set the query using our newly populated query object and execute it.
        $db->setQuery($query);
        $db->execute();
    }

    public function fixGroups() {
        $sql = 'ALTER TABLE `#__ra_groups` CHANGE `website` `website` VARCHAR(250)';
        echo $sql . '<br>';
        $this->toolsHelper->executeCommand($sql);
    }

    public function purgeAllUsers() {
        ToolBarHelper::title($this->prefix . 'Purging Blocked users');
        if (!$this->toolsHelper->isSuperuser()) {
            echo 'Invalid access<br>';
            return;
        }
        $sql = "SELECT id, name as 'User', email  ";
        $sql .= 'FROM `#__users` ';
        $sql .= ' WHERE block=1';
        $sql .= ' ORDER BY id';
        $target = 'administrator/index.php?option=com_ra_mailman&task=system.purgeUser&id=';
        $rows = $this->toolsHelper->getRows($sql);
        foreach ($rows as $row) {
            $this->purgeUserRecord($row->id);
        }
        $userHelper = new UserHelper;
        $userHelper->purgeProfiles();
        $back = 'administrator/index.php?option=com_ra_mailman&view=reports';
        echo $this->toolsHelper->backButton($back);
    }

    public function purgeUser() {
        $id = $this->app->input->getInt('id', '0');
        ToolBarHelper::title($this->prefix . 'Purging Blocked user');
        if (!$this->toolsHelper->isSuperuser()) {
            echo 'Invalid access<br>';
        } else {
            if ($id > 0) {
                $this->purgeUserRecord($id);
            }
        }
        $back = 'administrator/index.php?option=com_ra_mailman&task=reports.blockedUsers';
        echo $this->toolsHelper->backButton($back);
    }

    public function purgeUserRecord($id) {
        echo 'Purging User ' . $id . '<br>';
        $sql = 'DELETE FROM  #__user_usergroup_map ';
        $sql .= 'WHERE user_id=' . $id;
        echo $sql . '<br>';
        $this->toolsHelper->executeCommand($sql);
        $sql = 'DELETE FROM  #__ra_profiles ';
        $sql .= 'WHERE id=' . $id;
        echo $sql . '<br>';
        $this->toolsHelper->executeCommand($sql);
        $sql = 'DELETE FROM  #__users ';
        $sql .= 'WHERE id=' . $id;
        echo $sql . '<br>';
        $this->toolsHelper->executeCommand($sql);
        if (ToolsHelper::isInstalled('com_ra_events')) {
            $sql = 'DELETE FROM  #__ra_bookings ';
            $sql .= 'WHERE user_id=' . $id;
            echo $sql . '<br>';
            $this->toolsHelper->executeCommand($sql);
        }
    }

    public function sendEmail() {
        // Invoked from report recentMailshots to force resend on-line
        // this is the same processing as is carried out by the cron job
        $mail_list_id = $this->app->input->getInt('id', '0');

        if ($mail_list_id == 0) {
            Factory::getApplication()->enqueueMessage('mailshot id is zero', 'notice');
        } else {
            $this->toolsHelper->createLog('RA Mailman', 1, $mail_list_id, 'Sending of mailshot initiated');
            $mailHelper = new MailHelper;
            $last_mailshot = $mailHelper->lastMailshot($mail_list_id); //
//          Factory::getApplication()->enqueueMessage('mailshot id is ' . $last_mailshot->id, 'notice');
            $mailHelper->sendEmails($last_mailshot->id);
            foreach ($mailHelper->messages as $message) {
                Factory::getApplication()->enqueueMessage($message, 'info');
                $this->toolsHelper->createLog('RA Mailman', 1, $mail_list_id, $message);
            }
        }
        $back = 'index.php?option=com_ra_mailman&task=reports.recentMailshots';
        $this->setRedirect($back);
    }

    function test() {
        $toolsHelper = new ToolsHelper;
        $mailHelper = new MailHelper;
        $helper = New SchemaHelper;
        $helper->checkColumn('ra_logfile', 'sub_system', 'U', 'VARCHAR(10) NOT NULL; ');
        $target = 'administrator/index.php?option=com_ra_tools&view=dashboard';
        echo $toolsHelper->backButton($target);
//        return;

        $date = Factory::getDate();
        echo $date . '<br>';

        $sql = 'SELECT id, group_code, name, emails_outstanding ';
        $sql .= 'FROM #__ra_mail_lists ';
        $sql .= 'WHERE emails_outstanding>0 ORDER BY group_code, name';
        $rows = $toolsHelper->getRows($sql);
        $toolsHelper->showQuery($sql);
        $id = 0;
        foreach ($rows as $row) {
            if ($id == 0) {
                $id = $row->id;
                $name = $row->group_code . '/' . $row->name;
            }
            $message .= 'Group ' . $row->group_code . ', List ' . $row->name;
            $message .= ',' . $row->emails_outstanding . ' emails to be sent<br>';
        }
        if ($id > 0) {
            $message .= 'Sending emails for ' . $name . '<br>';
            echo $message;
        }
///////////////////////////////////////////////////////////////////////////////////////////////
        $sql = 'SELECT l.id, COUNT(u.id)  ';
        $sql .= 'FROM #__ra_mail_shots AS m ';
        $sql .= 'INNER JOIN `#__ra_mail_lists` AS l ON l.id = m.mail_list_id ';
        $sql .= 'INNER JOIN #__ra_mail_subscriptions AS s ON s.list_id = l.id ';
        $sql .= 'INNER JOIN #__users AS u ON u.id = s.user_id ';
        $sql .= 'LEFT JOIN #__ra_mail_recipients AS mr ON mr.mailshot_id =m.id ';
        $sql .= 'AND u.id = mr.user_id ';
        $sql .= 'WHERE mr.id IS NULL ';
        $sql .= 'AND l.id=' . $id;
        $sql .= ' AND s.state=1';
        $sql .= ' AND u.block=0 AND u.requireReset=0';
        //      echo $sql;

        $item = $this->toolsHelper->getItem($sql);
        echo'List is ' . $item->id . '<br>';

        $last_mailshot = $mailHelper->lastMailshot($item->id); //
        //      var_dump($last_mailshot);
        echo 'mailshot is ' . $last_mailshot->id . '<br>';
        //       return;
        $mail_shot_id = $last_mailshot->id;
        $subscribers = $mailHelper->getSubscribers($mail_shot_id);
        $count_subscribers = count($subscribers);
        $count = 1;
        $outstanding = $count_subscribers;
        $current_email = '';
        foreach ($subscribers as $subscriber) {
            echo $subscriber->email . '<br>';
        }
        return;
// Only get users who have not yet received their message
//        $subscribers = $this->getSubscribers($mailshot_id, 'Y');
//        $count_subscribers = count($subscribers);
//        $message .= ', ' . $count_subscribers . ' users outstanding';
////////////////////////////////////////////////////////////////////////////////
//    $objSubscription->cancel();
//        $objSubscription = new SubscriptionHelper;
//        $objUserHelper = new UserHelper;
//        $objUserHelper->blockUser(934);   // Webbie
    }

    private function testRenewals() {
        $body = 'Date <b>' . HTMLHelper::_('date', $today, 'd M y') . '</b><br>';
        $body .= 'Time <b>' . HTMLHelper::_('date', $today, 'h.i') . '</b><br>';
        $objSubscription = new SubscriptionHelper;
        echo $body . '<br>';

        $objSubscription->list_id = 2;  // test
        $objSubscription->user_id = 965; // Samsung
        if (!$objSubscription->getData()) {
            echo $objSubscription->message;
            return;
        }

        echo "1 before $objSubscription->expiry_date<br>";

        $objSubscription->resetExpiry();
        if ($objSubscription->update()) {
            if (!$objSubscription->getData()) {
                echo $objSubscription->message;
                return;
            }
            echo "2 after reset $objSubscription->expiry_date<br>";
        } else {
            echo $objSubscription->message;
            return;
        }


        $objSubscription->bumpExpiry();
        echo "3 after bump $objSubscription->expiry_date<br>";
        if ($objSubscription->update()) {
            if (!$objSubscription->getData()) {
                echo $objSubscription->message;
                return;
            }
            echo "4 after update $objSubscription->expiry_date<br>";
        } else {
            echo $objSubscription->message;
            return;
        }

        echo "Renewal<br>";
        echo "1 before $objSubscription->reminder_sent<br>";
        $objSubscription->setReminder();
        echo "1 before update, $objSubscription->reminder_sent<br>";
        if ($objSubscription->update()) {
            if (!$objSubscription->getData()) {
                echo $objSubscription->message;
                return;
            }
            echo "2 after set $objSubscription->reminder_sent<br>";
        } else {
            echo $objSubscription->message;
            return;
        }

        $objSubscription->setReminder();
        echo "2 after reset $objSubscription->reminder_sent<br>";
    }

}
