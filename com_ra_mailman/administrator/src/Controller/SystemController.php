<?php

/**
 * 05/01/24 CB Created
 * 08/01/24 CB use SubscriptionHelper
 * 14/11/24 CB duffRecords
 * 26/05/25 CB checkSchema / ra_reports
 * 11/08/25 CB allow forced send of emails
 * 03/11/25 CB delete bookings, return to reports menu after Purge All
 * 28/04/26 CB temp fix for updating groups table
 * 21/09/26 CB Functions for deleting orphaned records
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
use Joomla\CMS\User\UserFactoryInterface;
use Ramblers\Component\Ra_mailman\Site\Helpers\Mailhelper;
use Ramblers\Component\Ra_mailman\Site\Helpers\SubscriptionHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\SchemaHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\PersonHelper;

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
        $helper->checkColumn('ra_profiles', 'membershipStatus', 'A', 'VARCHAR(15) AFTER memberTerm; ');
        $helper->checkColumn('ra_profiles', 'membershipArrangement', 'A', 'VARCHAR(10) AFTER membershipStatus; ');
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
        $helper->checkColumn('ra_profiles', 'landline', 'A', 'VARCHAR(20) AFTER email; ');
        $helper->checkColumn('ra_profiles', 'mobile', 'A', 'VARCHAR(100) AFTER landline; ');
        $helper->checkColumn('ra_profiles', 'membershipExpiryDate', 'A', 'DATE NULL AFTER mobile; ');
//        $helper->checkColumn('ra_profiles', 'ramblersJoinedDate', 'A', 'DATE NULL AFTER membershipExpiryDate; ');
//        $helper->checkColumn('ra_profiles', 'areaJoinedDate', 'A', 'DATE NULL AFTER teamStatus; ');
        $helper->checkColumn('ra_profiles', 'teamRelationshipFrom', 'A', 'DATE NULL AFTER areaJoinedDate; ');
        $helper->checkColumn('ra_profiles', 'volunteer', 'A', 'CHAR(1) AFTER teamRelationshipFrom; ');
        $helper->checkColumn('ra_profiles', 'emailConsent', 'A', 'CHAR(1) AFTER volunteer; ');
//        $helper->checkColumn('ra_profiles', 'areaMarketingConsent', 'A', 'CHAR(1) AFTER emailConsent; ');
//        $helper->checkColumn('ra_profiles', 'groupMarketingConsent', 'A', 'CHAR(1) AFTER areaMarketingConsent; ');
//        $helper->checkColumn('ra_profiles', 'otherMarketingConsent', 'A', 'CHAR(1) AFTER groupMarketingConsent; ');
        $helper->checkColumn('ra_profiles', 'emailConsentLastUpdated', 'A', 'DATE NULL AFTER emailConsent; ');
        $helper->checkColumn('ra_profiles', 'postConsent', 'A', 'CHAR(1) AFTER emailConsentLastUpdated; ');
        $helper->checkColumn('ra_profiles', 'postConsentLastUpdated', 'A', 'DATE NULL AFTER postConsent; ');
        $helper->checkColumn('ra_profiles', 'phoneConsent', 'A', 'CHAR(1) AFTER postConsentLastUpdated; ');
        $helper->checkColumn('ra_profiles', 'phoneConsentLastUpdated', 'A', 'DATE NULL AFTER phoneConsent; ');
        $helper->checkColumn('ra_profiles', 'emailConsentWellbeingWalks', 'A', 'CHAR(1) AFTER phoneConsentLastUpdated; ');
        $helper->checkColumn('ra_profiles', 'nowalkProgramme', 'A', 'CHAR(1) AFTER emailConsentWellbeingWalks; ');
        $helper->checkColumn('ra_profiles', 'affiliateMemberPrimaryGroup', 'A', 'VARCHAR(50) AFTER nowalkProgramme; ');
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

        $target = 'administrator/index.php?option=com_ra_tools&view=dashboard';
        echo $toolsHelper->backButton($target);
    }

    public function deleteSubscriptionAuditNoSub() {
        if ($this->toolsHelper->isSuperuser()) {
            $sql = 'FROM #__ra_mail_subscriptions_audit AS a ';
            $sql .= 'LEFT JOIN #__ra_mail_subscriptions AS s on s.id = a.object_id ';
            $sql .= 'WHERE s.id IS NULL ';
            $count = $this->toolsHelper->getValue('SELECT COUNT(a.id) ' . $sql);

            $this->toolsHelper->executeCommand('DELETE a.* ' . $sql);
            $this->app->enqueueMessage($count . ' audit records deleted', 'message');
        } else {
            $this->app->enqueueMessage('Access only permitted for Superusers', 'error');
        }
        $this->setRedirect('index.php?option=com_ra_tools&task=reports.checkDatabase');
    }

    public function deleteSubscriptionsNoList() {
        if ($this->toolsHelper->isSuperuser()) {
            $sql = 'SELECT ms.id ';
            $sql .= 'FROM #__ra_mail_subscriptions AS ms ';
            $sql .= 'LEFT JOIN #__ra_mail_lists as m ON m.id = ms.list_id ';
            $sql .= 'WHERE m.id IS NULL ';

            $this->toolsHelper->getRows($sql);
            $rows = $this->toolsHelper->getRows($sql);
            $count_subs = count($rows);
            $count_audit = 0;
            foreach ($rows as $row) {
                $sql = ' FROM  #__ra_mail_subscriptions_audit ';
                $sql .= 'WHERE object_id=' . $row->id;
                $count = $this->toolsHelper->getValue('SELECT COUNT(*)' . $sql);
                $count_audit = $count_audit + $count;
                $this->toolsHelper->executeCommand('DELETE ' . $sql);
                $sql = 'DELETE FROM #__ra_mail_subscriptions WHERE id=' . $row->id;
                $this->toolsHelper->executeCommand($sql);
            }
            $this->app->enqueueMessage($count_subs . ' Subscriptions and ' . $count_audit . ' audit records deleted', 'message');
        } else {
            $this->app->enqueueMessage('Access only permitted for Superusers', 'error');
        }
        $this->setRedirect('index.php?option=com_ra_tools&task=reports.checkDatabase');
    }

    public function deleteSubscriptionsNoProfile() {
        if ($this->toolsHelper->isSuperuser()) {
            $sql = 'SELECT ms.id ';
            $sql .= 'FROM #__ra_mail_subscriptions AS ms ';
            $sql .= 'LEFT JOIN #__ra_profiles as p ON p.id = ms.user_id ';
            $sql .= 'WHERE p.id IS NULL ';

            $this->toolsHelper->getRows($sql);
            $rows = $this->toolsHelper->getRows($sql);
            $count_subs = count($rows);
            $count_audit = 0;
            foreach ($rows as $row) {
                $sql = ' FROM  #__ra_mail_subscriptions_audit ';
                $sql .= 'WHERE object_id=' . $row->id;
                $count = $this->toolsHelper->getValue('SELECT COUNT(*)' . $sql);
                $count_audit = $count_audit + $count;
                $this->toolsHelper->executeCommand('DELETE ' . $sql);
                $sql = 'DELETE FROM #__ra_mail_subscriptions WHERE id=' . $row->id;
                $this->toolsHelper->executeCommand($sql);
            }
            $this->app->enqueueMessage($count_subs . ' Subscriptions and ' . $count_audit . ' audit records deleted', 'message');
        } else {
            $this->app->enqueueMessage('Access only permitted for Superusers', 'error');
        }
        $this->setRedirect('index.php?option=com_ra_tools&task=reports.checkDatabase');
    }

    public function duffRecords() {
        // one-off clean up to tidy the database
        ToolBarHelper::title('System maintenance');
        $toolsHelper = new ToolsHelper;
        if (!$toolsHelper->isSuperuser()) {
            return;
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
            try {
                $this->purgeUserRecord((int) $row->id);
            } catch (\Throwable $exception) {
                $this->app->enqueueMessage($exception->getMessage(), 'error');
            }
        }
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
                try {
                    $this->purgeUserRecord($id);
                } catch (\Throwable $exception) {
                    $this->app->enqueueMessage($exception->getMessage(), 'error');
                }
            }
        }
        // Return directly to the report so the remaining blocked-user list is
        // immediately visible. Queued success/error messages are preserved by
        // the Joomla administrator redirect.
        $this->setRedirect('index.php?option=com_ra_mailman&task=reports.blockedUsers');
    }

    public function purgeUserRecord($id) {
        if ((int) $id < 1) {
            throw new \InvalidArgumentException('A valid Joomla user ID is required.');
        }

        $userFactory = Factory::getContainer()->get(UserFactoryInterface::class);
        $user = $userFactory->loadUserById((int) $id);

        if ((int) $user->id < 1) {
            throw new \RuntimeException('Joomla user ' . (int) $id . ' was not found.');
        }
        if (!(int) $user->block) {
            throw new \RuntimeException('Only blocked users may be removed by the MailMan purge task.');
        }

        if (!$user->delete()) {
            throw new \RuntimeException(
                            'Joomla was unable to delete user ' . (int) $id . ': ' . $user->getError()
            );
        }

        $this->app->enqueueMessage(
                'Deleted blocked Joomla user ' . (int) $id . ' through the Joomla User API.',
                'message'
        );
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
        $mailHelper = new MailHelper;
        $personHelper = new PersonHelper;
        $toolsHelper = new ToolsHelper;
/*        
//        $personHelper->createMissingPlaceholderProfiles();
        $userId = 2261;
        $name = 'mac@bigley.me.uk';
        $response = $personHelper->ensurePlaceholderProfile($userId, $name);
        echo 'Response: ' . $response . '<br>';
*/

        $target = 'index.php?option=com_ra_mailman&view=reports';
        echo $toolsHelper->backButton($target);
        return;

        $date = Factory::getDate();
        echo $date . '<br>';

        return;
// Only get users who have not yet received their message
//        $subscribers = $this->getSubscribers($mailshot_id, 'Y');
//        $count_subscribers = count($subscribers);
//        $message .= ', ' . $count_subscribers . ' users outstanding';
////////////////////////////////////////////////////////////////////////////////
//    $objSubscription->cancel();
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
