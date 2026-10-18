<?php

/**
 * @version     4.7.0
 * @package     com_ra_mailman
 * @copyright   Copyright (C) 2020. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @author      Charlie Bigley <webmaster@bigley.me.uk> - https://www.developer-url.com
 * Invoked from controllers/dataload to import Users, will be passed 4 parameters:
 *  method_id, list_id, processing and filename
 * Data Type: 3 Download from Insight Hub
 *            4 Export from MailChimp
 *            5 Simple csv file
 * Processing: 0 = report only
 *             1 = Update database
 *
 * 12/02/25 CB replace getIdentity with Factory::getApplication()->getSession()->get('user')
 * 14/04/25 CB trim spaces from beginning and end of input fields
 * 18/05/25 CB correct columns for names and email address
 * 26/05/25 CB import report
 * 19/06/25 CB comment out actual removal, don't show message if user present
 * 07/07/25 CB Receive system emails
 * 14/07/25 CB derive reference columns for Insight Hub from column headings
 * 27/07/25 CB abbreviate_name, check if subscription created OK
 * 16/08/25 CB use ToolsHelper to send emila, not mailHelper
 * 28/04/26 CB don't use groups_to_follow
 */

namespace Ramblers\Component\Ra_mailman\Site\Helpers;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\Database\DatabaseInterface;
use Ramblers\Component\Ra_mailman\Site\Helpers\Mailhelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\PersonHelper;

/**
 * Ra_mailman helper class
 */
class DataLoadProcessor {

// These six variable are defined by the calling program
    public $method_id;
    public $group_code;
    public $list_id;
    public $processing;
    public $filename;
    public $report_id;
// This are available after processing
    public $error;
    public $success;
// These variables are used internally
    public $email;
    public $name;
    public $preferred_name;
    public $user_id;
//    protected $open;
    protected $abbreviate_name;
    protected $current_userid;
    protected $home_group;
    protected $toolshelper;
    protected $personHelper;
    protected $loadHelper;
    protected $headerMap = [];
    protected $mailHelper;
    protected $error_count = 0;
    protected $error_report;
    protected $new_users = array();
    protected $new_subs = array();
    protected $lapsed_count = 0;
    protected $lapsed_members = array();
    protected $record_count = 0;
    protected $record_type;
    protected $subscription_count = 0;
    protected $users_created = 0;
    protected $users_required = 0;
    protected $missing_email_count = 0;
    public function __construct() {
// When subscribing, always subscribe as User (rather than an Author)
        $this->record_type = 1;
        $this->mailHelper = new Mailhelper;
        $this->toolshelper = new ToolsHelper;
        $this->personHelper = new PersonHelper;
        $this->loadHelper = new LoadHelper($this->toolshelper);
        $this->abbreviate_name = ComponentHelper::getParams('com_ra_mailman')->get('abbreviate_name', 'Y');
        $this->current_userid = Factory::getApplication()->getSession()->get('user')->id;
    }

    private function createPreferredName() {
        if ($this->abbreviate_name == 'N') {
            $this->preferred_name = $this->name;
        } else {
// Created a default preferred_name as First name + first characters of Surname
            $parts = explode(' ', $this->name);
            $last = count($parts) - 1;  // in case more than 2 names given
            $this->preferred_name = $parts[0] . ' ' . substr($parts[$last], 0, 1);
        }
    }

    private function lookupColumns($fields) {
        try {
            $this->headerMap = $this->loadHelper->resolveHeaders(
                    (int) $this->method_id,
                    $fields,
                    (int) $this->list_id
            );
            echo 'CSV headings recognised<br>';
            return true;
        } catch (\Throwable $exception) {
            $this->success = false;
            Factory::getApplication()->enqueueMessage($exception->getMessage(), 'Error');
            echo '<b>' . htmlspecialchars($exception->getMessage(), ENT_QUOTES, 'UTF-8') . '</b><br>';
            return false;
        }
    }

    protected function lookupUser() {
        $this->user_id = 0;
        $item = $this->personHelper->findUserByEmail($this->email);
        $user_id = $item ? (int) $item->id : 0;
        if ($user_id > 0) {
            $this->name = $item->name;
            $this->user_id = $item->id;
        }
        return $user_id;
    }

    protected function parseLine($data) {
        $mapped = $this->loadHelper->mapRow((int) $this->method_id, $data, $this->headerMap);
        $this->group_code = trim((string) ($mapped['group_code'] ?? ''));
        $this->name = trim((string) ($mapped['name'] ?? ''));
        $this->email = trim((string) ($mapped['email'] ?? ''));

        if ($this->email === '') {
            $this->missing_email_count++;
            return false;
        }

        $messages = $this->loadHelper->validateRow($mapped, (int) $this->method_id);

        if ($this->email !== '' && $this->name !== '') {
            $identityMessage = $this->userExists($this->email, $this->name);
            if ($identityMessage !== '') {
                $messages[] = $identityMessage;
            }
        }

        if ($messages === []) {
            return true;
        }

        $this->error_count += count($messages);
        $this->error_report .= $this->record_count . ': ' . implode(',', $data) . '<br>';
        $this->error_report .= 'Error: ' . implode(' ', $messages) . '<br>';
        return false;
    }

    public function processFile() {
// Entry point for processing import file
        $this->success = true;
//        die(' processing = ' . $this->processing . ', filename = ' . $this->filename);
        if (JDEBUG) {
            $diagnostic = ' processing = ' . $this->processing . ', filename = ' . $this->filename;
            Factory::getApplication()->enqueueMessage("Helper: " . $diagnostic, 'Message');
        }
        $params = ComponentHelper::getParams('com_ra_mailman');
        $this->max_errors = $params->get('max_errors');
        if (!file_exists($this->filename)) {
            echo $this->filename . ' not found';
            Factory::getApplication()->enqueueMessage("Helper: " . $this->filename . ' not found', 'Error');
            $this->success = false;
            return 0;
        }

        $sql = "Select group_code, name, record_type, home_group_only from `#__ra_mail_lists` "
                . "WHERE id='" . $this->list_id . "'";
        $item = $this->toolshelper->getItem($sql);

        if ($item->home_group_only == 1) {
            $this->home_group = $item->group_code;
        }
        $title = $item->group_code . ' ' . $item->name;
        if ($item->record_type == 'O') {
            $this->open = true;
        } else {
            $this->open = false;
            $title .= ' (Closed list)';
        }

        if ($this->processing == 1) {
            echo '<h2>Processing ';
        } else {
            echo '<h2>Validating ';
        }
        if ($this->method_id == 3) {
            echo 'Members from corporate feed';
        } elseif ($this->method_id == 4) {
            echo 'MailChimp export';
        } elseif ($this->method_id == 5) {
            echo 'CSV';
        } else {
            echo 'Type = ' . $this->method_id . 'Not recognised';
        }

        echo '<h4>List = ' . $title . '<br>';
        echo 'File = ' . $this->filename . '</h4>';
        $this->processRecords();
        if ($this->missing_email_count > 0) {
            $this->error_report .= 'Records without an email address: ' . $this->missing_email_count . '<br>';
        }
        echo '<br>' . $this->record_count . ' records read<br>';
        if ($this->error_count > 0) {
            echo "<b>$this->error_count errors</b><br>";
            echo '<div style = "padding-left: 19px;">'; // create div with offset left margin
            echo $this->error_report . '<br>';
//           $target = 'administrator/index.php?option = com_ra_mailman&task = import_reports.showErrors&id = ' . $this->report_id;
//           echo $this->toolshelper->buildLink($target, 'Report', true);
            echo '</div>';
            echo '<br>';
        }
        echo $this->users_required . ' Users required<br>';
        if ($this->processing == 1) {
            echo $this->users_created . ' Users created<br>';
            echo $this->subscription_count . ' Subscriptions created<br>';
        } else {
            echo ($this->subscription_count + $this->users_required) . ' Subscriptions required<br>';
        }
        if (($this->processing == 1) AND ($this->method_id == 3)) {
            $this->processLapsers();
        }
        if ($this->lapsed_count > 0) {
            echo $this->lapsed_count;
            if ($members_leave == 'B') {
                echo ' Users Blocked<br>';
            } else {
                echo ' Users Purged<br>';
            }
        }
        $this->updateReport();
        return $this->success;
    }

    protected function processLapsers() {
        $app = Factory::getApplication();

// Lookup whether Users are to be blocked or deleted
        $members_leave = ComponentHelper::getParams('com_ra_mailman')->get('members_leave');
// Find any members on previous files, but not present on this one
// Set up the date to which current members have been renewed
        $today = date('Y-m-d');
        $bounce_date = date('Y-m-d', strtotime($today . ' + 1 year'));
        echo '<h4>Seeking lapsed members</h4>';
        if ($members_leave == 'B') {
            echo 'Blocking ';
        } else {
            echo 'Purging ';
        }
        echo ' Members registered to this list via Corporate feed<br>';
        echo 'Expiry date before ' . $bounce_date . '<br>';

// Find subscriptions with renewal date before this
        $sql = "SELECT s.id AS subscription_id, s.expiry_date, l.id AS list_id, ";
        $sql .= "u.id as user_id, p.preferred_name, u.email ";
        $sql .= 'FROM `#__ra_mail_lists` AS l ';
        $sql .= 'INNER JOIN #__ra_mail_subscriptions AS s ON s.list_id = l.id ';
        $sql .= 'INNER JOIN #__users AS u ON u.id = s.user_id ';
        $sql .= 'LEFT JOIN #__ra_profiles AS p ON p.id = s.user_id ';
        $sql .= 'WHERE l.id=' . $this->list_id . ' ';
        $sql .= 'AND (datediff("' . $bounce_date . '",s.expiry_date) > 0)  ';
//        $sql .= ' AND s.state=1';  // don't care if they have already unsubscribed
        $sql .= ' AND s.method_id=3';
        $sql .= ' ORDER BY u.id';
//        if (JDEBUG) {
        echo $sql . '<br>';
        $this->toolshelper->showQuery($sql);
//       }

        $rows = $this->toolshelper->getRows($sql);
        $this->lapsed_count = $this->toolshelper->rows;
        foreach ($rows as $row) {
            $this->lapsed_members[] = $row->preferred_name . ',' . $row->email;
            if ($members_leave == 'B') {
            } else {
            }
        }
        $count = $this->toolshelper->getValue('SELECT COUNT(id) FROM #__users');
        echo 'Total number of Users now =' . $count . '<br>';
        $app->enqueueMessage('Total number of Users now =' . $count, 'info');
    }

    protected function processRecords() {
        $this->record_count = 0;
        $this->users_required = 0;
        $this->subscription_count = 0;
        $this->new_users = [];
        $this->new_subs = [];
        $this->error_report = '';
        $handle = fopen($this->filename, "r");
        if ($handle == 0) {
            echo 'Unable to open ' . $this->filename . '<br>';
            $this->success = false;
            return $this->success;
        }
//        $this->test('Michael Mouse', 'mick@mouse.com');
//        $this->test('Alpha Bigley', 'alpha@bigley.me.uk');
//        $this->test('Alpha Bigley', 'al@bigley.me.uk');
//        $this->test('Al Bigley', 'alpha@bigley.me.uk');
//        $this->test('Betty Bigley', 'beta@bigley.me.uk');
//        die('File ' . $this->filename . ' opened OK');
        $sql_lookup = 'SELECT id FROM #__users WHERE email="';
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            $this->record_count++;
//            if (JDEBUG) {
//                echo $this->record_count . ': ';
//            }
            if ($this->record_count == 1) {
                if ((is_array($data)) and (count($data) == 1)) {
                    Factory::getApplication()->enqueueMessage("Array has only one entry", 'Error');
                    Factory::getApplication()->enqueueMessage('Data is not comma delimited', 'Error');
                    $this->success = false;
                    return $this->success;
                }
                if (count($data) == 1) {
                    Factory::getApplication()->enqueueMessage("Data does not seem to be an array: ", 'Error');
                    $this->success = false;
                    return $this->success;
                }
//                var_dump($data);
//                echo '<br><br>';

                echo 'Calculating column references from header row<br>';
                if (!$this->lookupColumns($data)) {
                    return $this->success;
                }
            } elseif (substr($data[0], 0, 1) == '#') {
                echo 'Ignoring comment ' . $data[0] . ',' . $data[1], '<br>';
            } elseif (trim(implode('', $data)) == '') {
                echo 'Ignoring blank line ' . $data[0] . ',' . $data[1], '<br>';
            } else {
                /*
                 * After $this->parseLine, the following variables will have been set up:
                 *     $this->group_code
                 *     $this->name
                 *     $this->email
                 */
                if (($this->parseLine($data))) {
                    if (JDEBUG) {
                        echo $this->record_count . ', group=' . $this->group_code . ', name=' . $this->name . ', email=' . $this->email . "<br>";
                    }
                    $subscription_required = false;
                    $message = '';
                    $user_id = (int) $this->lookupUser();
                    if ($user_id == 0) {
                        $this->users_required++;
                        $this->new_users[] = $this->name . ',' . $this->email;
                        $message .= 'User ' . $this->name . ' <b>not present</b> (' . $this->email . ')';
                        if ($this->processing == 1) {
                            try {
                                $this->user_id = $this->personHelper->saveUser(
                                        $this->name,
                                        $this->email,
                                        0,
                                        PersonHelper::USER_MODE_IMPORT
                                );
                                $user_id = $this->user_id;  // As just created
                                $this->createPreferredName();
                                // Complete the placeholder created by the
                                // Joomla user plugin; never insert a second
                                // profile row for the new user.
                                $this->personHelper->ensurePlaceholderProfile($user_id, $this->name);
                                $this->personHelper->saveProfileData($user_id, [
                                    'home_group' => strtoupper($this->group_code),
                                    'preferred_name' => $this->preferred_name,
                                    'state' => 1,
                                ]);
                                $message .= ', User created';
                                if (JDEBUG) {
                                    $message .= ', id=' . $user_id;
                                }
                                $this->users_created++;
                                $subscription_required = true;
                            } catch (\Throwable $exception) {
                                $subscription_required = false;
                                $message .= ', Error creating User ' . $this->name . '/' . $this->email
                                        . ': ' . $exception->getMessage();
                            }
                        }
//                        echo $message . '<br>';
                    } else {
//                        $message = '';
                        $message .= 'User ' . $this->name . ' exists for ' . $this->email;
                        if ($this->processing == 1) {
                            // Presence in a validated import confirms a
                            // previously self-registered blocked account.
                            $this->personHelper->setBlocked($user_id, false);
                        }
                        $method = $this->mailHelper->isSubscriber($this->list_id, $user_id);
                        if ($method == '') {
                            $message .= ', Subscription <b>not present</b>';
                            $subscription_required = true;
                        } else {
//                            $message .= ', subscription exists, method=<b>' . $method . '</b>';
                            $subscription_required = false;
                        }
                    }
                    if (($subscription_required) AND ($this->processing == 0)) {
                        $this->subscription_count++;
                    }
                    if (($subscription_required) AND ($this->processing == 1)) {
                        if ($this->mailHelper->subscribe($this->list_id, $user_id, $this->record_type, $this->method_id, false)) {
                            $message .= ', Subscription created';
                            $this->new_subs[] = $this->name . ',' . $this->email;
                            $this->subscription_count++;
                        } else {
                            $message .= ' ' . $this->mailHelper->message;
                        }
                    }
                    if ($message !== '') {
//echo 'User ' . $this->name . ' ' . $message;
                        echo $message . '<br>';
                    }
                }
            }
            if ($this->error_count > $this->max_errors) {
                Factory::getApplication()->enqueueMessage('Max error count of ' . $this->max_errors . ' exceeded', 'Error');
                $this->success = false;
            }
        }
        fclose($handle);
    }

    private function updateReport() {
        $new_users = implode('<br>', $this->new_users);
        $new_subs = implode('<br>', $this->new_subs);
        $lapsed_members = implode('<br>', $this->lapsed_members);

        /*
         * echo '<br>';
          var_dump($new_users);
          echo '<br>';
          var_dump($new_subs);
          echo '<br>';
          var_dump($this->lapsed_members);
          echo '<br>';
          var_dump($lapsed_members);
          echo '<br>';
         */
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true);
        $query->update('#__ra_import_reports')
                ->set("num_records = " . $db->quote($this->record_count))
                ->set("num_errors = " . $db->quote($this->error_count))
                ->set("num_users = " . $db->quote($this->users_required))
                ->set("num_subs = " . $db->quote($this->subscription_count))
                ->set("num_lapsed = " . $db->quote($this->lapsed_count))
                ->set("error_report = " . $db->quote($this->error_report))
                ->set("new_users = " . $db->quote($new_users))
                ->set("new_subs = " . $db->quote($new_subs))
                ->set("lapsed_members = " . $db->quote($lapsed_members))
                ->set("state=1")
                ->where('id=' . $this->report_id);
        if ($this->processing == 1) {
            $date = Factory::getDate('now', Factory::getConfig()->get('offset'))->toSql(true);
            $query->set("date_completed = " . $db->quote($date));
        }
        $result = $db->setQuery($query)->execute();
    }

    public function userExists($email, $real_name) {
        $conflicts = $this->personHelper->findUserIdentityConflicts($email, $real_name);
        $emailUser = $conflicts['email'];
        $nameUser = $conflicts['name'];

        // Preserve the existing import validation precedence: an email conflict
        // is reported before a name conflict.
        if ($emailUser !== null) {
            if (strcasecmp(trim((string) $emailUser->name), trim((string) $real_name)) === 0) {
                return '';
            }

            return $real_name . '/' . $email . ' is invalid: email ' . $email
                    . ' is already in use with name  ' . $emailUser->name;
        }

        if ($nameUser !== null) {
            return $real_name . '/' . $email . ' is invalid: name ' . $real_name
                    . ' already in use with email ' . $nameUser->email;
        }

        return '';
    }

}
