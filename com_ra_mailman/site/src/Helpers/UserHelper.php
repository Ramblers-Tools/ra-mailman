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
 * 16/11/24 CB blockUser and purgeUser
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
use Joomla\CMS\Table\Table;
use \Joomla\CMS\User\User;
use Joomla\Database\DatabaseInterface;
use Ramblers\Component\Ra_mailman\Site\Helpers\Mailhelper;
use Ramblers\Component\Ra_mailman\Site\Helpers\Userhelper;
use Ramblers\Component\Ra_mailman\Site\Helpers\SubscriptionHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

/**
 * Ra_mailman helper class
 */
class UserHelper {

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
    protected $objMailHelper;
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
// These constants refer to to column numbers on the Insight file
    protected $cEmail;
    protected $cForename;
    protected $cGroup;
    protected $cSurname;

    public function __construct() {
// When subscribing, always subscribe as User (rather than an Author)
        $this->record_type = 1;
        $this->objMailHelper = new Mailhelper;
        $this->toolshelper = new ToolsHelper;
        $this->abbreviate_name = ComponentHelper::getParams('com_ra_mailman')->get('abbreviate_name', 'Y');
        $this->current_userid = Factory::getApplication()->getSession()->get('user')->id;
    }

    protected function addJoomlaUser() {
        $password = self::randomkey(8);
        $data = array(
            "name" => $this->name,
            "username" => $this->email,
            "password" => $password,
            "password2" => $password,
            "email" => $this->email,
            "reset" => 1
        );

        $user = new User();
//Write to database
        if (!$user->bind($data)) {
            throw new Exception("Could not bind data. Error: " . $user->getError());
        }
        if (!$user->save()) {
            throw new Exception("Could not save user. Error: " . $user->getError());
        }

        return $user->id;
    }

    public function blockUser($user_id) {
// Blocks and renames User record
        $sql = 'SELECT u.name, p.preferred_name ';
        $sql .= 'FROM `#__users` AS u ';
        $sql .= 'LEFT JOIN #__ra_profiles AS p on p.id = u.id ';
        $sql .= 'WHERE u.id=' . $user_id;
        $item = $this->toolshelper->getItem($sql);
        $message = 'Renamed ' . $item->name . '/' . $item->preferred_name . ' to User' . $user_id;

        $email_domain = ComponentHelper::getParams('com_ra_mailman')->get('email_domain');

        $user = 'User' . $user_id;
        $email = 'user' . $user_id . '@' . $email_domain;
        $sql = 'UPDATE `#__users` SET ';
        $sql .= 'block=1, ';
        $sql .= 'name="' . $user . '", ';
        $sql .= 'username="' . $email . '", ';
        $sql .= 'email= "' . $email . '" ';
        $sql .= 'WHERE id=' . $user_id;
//        echo $sql . '<br>';
        $this->toolshelper->executeCommand($sql);

        $sql = 'UPDATE `#__ra_profiles` SET ';
        $sql .= 'preferred_name="' . $user . '" ';
        $sql .= 'WHERE id=' . $user_id;
        echo $sql . '<br>';
        $this->toolshelper->executeCommand($sql);
// Cancel any subscriptions
        $sql = 'SELECT id, list_id ';
        $sql .= "FROM `#__ra_mail_subscriptions` ";
        $sql .= "WHERE user_id=" . $user_id;
//        echo $sql . '<br>';
        $objSubscription = new SubscriptionHelper;
        foreach ($rows as $row) {
            echo "id=$row->id, expires $row->expiry_date<br>";
            $objSubscription->list_id = $row->list_id;
            $objSubscription->user_id = $user_id;
            $objSubscription->cancel();
        }
        echo $message . '<br>';
    }

    public function checkEmail($email, $username, $group_code) {
// 20/10/2025 This does not seem to be used
// Returns True or an error message
        $sql = 'SELECT u.id, u.name, u.registerDate, p.home_group ';
        $sql .= 'FROM #__users AS u ';
        $sql .= 'LEFT JOIN #__ra_profiles as p ON p.id = u.id ';
        $sql .= 'WHERE u.email="' . $email . '"';
        $item = $this->toolshelper->getItem($sql);
        if (!is_null($item)) {
            if ($item->id > 0) {
                $message = $email . '/' . $item->name . '/' . $item->home_group . ' was registered ' . $item->registerDate . '.';
                $message .= ' You can just logon to update your subscriptions.';
                return $message;
            }
        }

        $sql = 'SELECT u.id, u.name, u.registerDate, p.home_group ';
        $sql .= 'FROM #__users AS u ';
        $sql .= 'LEFT JOIN #__ra_profiles as p ON p.id = u.id ';
        $sql .= 'WHERE u.name="' . $username . '" ';
        $sql .= 'AND p.home_group="' . $group_code . '" ';
//        echo $sql . '<br>';
//        die($sql);
        $item = $this->toolshelper->getItem($sql);
        if (!is_null($item)) {
            if ($item->id > 0) {
                return 'This Name is already in use for ' . $item->email . '/' . $item->home_group . ' registered ' . $item->registerDate;
            }
        }
        return True;
    }

    public function checkExistingUser($email, $username, $group_code) {
// Invoked from the front end if administrator is trying to register a new user
// Returns ID of existing user, if one found
        $sql = 'SELECT u.id ';
        $sql .= 'FROM #__users AS u ';
        $sql .= 'LEFT JOIN #__ra_profiles as p ON p.id = u.id ';
        $sql .= 'WHERE u.email="' . $email . '"';
        $sql .= 'AND u.name = "' . $username . '"';
        $sql .= 'AND p.home_group = "' . $group_code . '"';
        return $this->toolshelper->getValue($sql);
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

    public function createProfile() {
//    Create a record in ra_profiles
//      Check that record not already present
//      should not be an existing record, but if there is, update it anyway


        $sql = 'SELECT id FROM #__ra_profiles WHERE id=' . $this->user_id;
        $record_exists = $this->toolshelper->getValue($sql);
        $date = Factory::getDate('now', Factory::getConfig()->get('offset'))->toSql(true);
        $user = Factory::getApplication()->getSession()->get('user');
        if ($record_exists) {
            $sql = 'UPDATE #__ra_profiles SET ';
            $sql .= 'home_group=' . $db->quote($this->group_code) . ', ';
            $sql .= 'preferred_name=' . $db->quote($this->preferred_name) . ', ';
            $sql .= 'modified=' . $db->quote($date) . ', ';
            $sql .= 'modified_by=' . $db->quote($user->id) . ' ';
            $sql .= 'WHERE id=' . $this->user_id;
            $this->toolshelper->executeCommand($sql);
        } else {
// See if user is logged in (i.e not self registering)
            if ($user->id == 0) {
                $created = $this->user_id;
            } else {
                $created = $this->current_userid;
            }
// Prepare the insert query.
            $db = Factory::getDbo();
            $query = $db->getQuery(true);
            $query->set('member_id =' . $db->quote($this->user_id))
                    ->set('id =' . $db->quote($this->user_id))
                    ->set('home_group =' . $db->quote($this->group_code))
//                    ->set('groups_to_follow  =' . $db->quote($group_code))
                    ->set('preferred_name =' . $db->quote($this->preferred_name))
                    ->set('created =' . $db->quote($date))
                    ->set('created_by =' . $db->quote($created))
                    ->insert($db->quoteName('#__ra_profiles'));
//           echo $db->replacePrefix($query) . '<br>';
//           die;
            $db->setQuery($query);
            $result = $db->execute();
            if (!$result) {
                $this->error = 'Unable to create Profile record for ' . $this->group_code . ' ' . $this->preferred_name;
            }
            return $result;
        }
    }

    public function createProfile_1() {
//    Create a record in ra_profiles
        $db = Factory::getDbo();
        $user = Factory::getApplication()->getSession()->get('user');
        $query = $db->getQuery(true);
        $date = Factory::getDate('now', Factory::getConfig()->get('offset'))->toSql(true);
        $query->insert($db->quoteName('#__ra_profiles'))
                ->set('id =' . $db->quote($this->user_id))
                ->set('home_group =' . $db->quote($this->group_code))
                ->set('groups_to_follow  =' . $db->quote($this->group_code))
                ->set('preferred_name =' . $db->quote($this->preferred_name))
                ->set('created =' . $db->quote($date))
                ->set('created_by =' . $db->quote($user->id))
        ;
//        echo $db->replacePrefix($query) . '<br>';
        $db->setQuery($query);
        return $db->execute();
    }

    public function createProfile_3($user_id, $group_code) {
// Fails to find Instance of table UPDATED 22/06/26 with coirrect reference to the table
        $data = array(
            'id' => $user_id,
            'home_group' => $db->quote($group_code),
            'groups_to_follow' => $db->quote($group_code),
            'preferred_name' => $db->quote($this->preferred_name),
        );
        $table = $this->getTable('Profile', 'Ramblers\\Component\\Ra_mailman\\Administrator\\Table\\');
        if (!$table->bind($data)) {
            echo 'could not bind<br>';
            return false;
        }
        if (!$table->check()) {
            echo 'could not validate<br>';
            return false;
        }
        if (!$table->store(true)) {
            echo 'could not store<br>';
            return false;
        }
    }

    public function createUser() {
        /*
         * This uses Joomla objects to create a User record (and send them a message about the new password)
         * It is used from the front-end (controllers/profile) from view profiles
         * However, if used from the back end it seems only to work the first time it is invoked
         * 23/10/23 add field sendEmail, pass array of groups rather than call linkUser
         * 07/07/25 This does not link the new user to groups Public and Registered as expected

         */

        if ($this->name == 'Email Address') {
// this is the first line of a MailChimp export
            return;
        }
        $this->user_id = 0;

        $password = '$2y$10$PCUXW4xpLTsLGmdJJ4NqUuuNSnpq7fBkZxB4XiqUNFq8tP1Ha3FHa'; // unspecifiedpassword
// This code only seems to work for the first user
        $user = new User();   // Write to database
        $data = array(
            "name" => $this->name,
            "username" => $this->email,
            "password" => $password,
            "password2" => $password,
            "sendEmail" => '0', // Receive system emails
            "group" => array('1', '2'), // Public & Registered
            "require_reset" => 1,
            "email" => $this->email
        );
        if (!$user->bind($data)) {
            $this->error = 'Could not validate data - Error: ' . $user->getError();
            return false;
        }

        if (!$user->save()) {
// throw new Exception("Could not save user. Error: " . $user->getError());
            $this->error = 'Could not create user - Error: ' . $user->getError();
            return false;
        }
        $this->user_id = $user->id;
        $this->linkUser(1);
        $this->linkUser(6);
        Factory::getSession()->clear('user', "default");
        return true;
    }

    public function createUserDirect($front_end = '0') {
// writes a record to the users table
// If invoked from the front-end, $front_end will have value of 1:
//    User will require a reset
//    Notification message generated
//    Notification email will be send
// and the user will need to activate themselves by resetting their password
// From the back end, requireReset = 0
        if ($this->name == 'Email Address') {
// this is the first line of a MailChimp export
            return;
        }
        $this->user_id = 0;

        $date = Factory::getDate();
        $params = '{"admin_style":"","admin_language":"","language":"","editor":"","timezone":""}';
        $password = '$2y$10$PCUXW4xpLTsLGmdJJ4NqUuuNSnpq7fBkZxB4XiqUNFq8tP1Ha3FHa'; // unspecifiedpassword
        $db = Factory::getDbo();
        $query = $db->getQuery(true);

// Prepare the insert query.
        $query
                ->insert($db->quoteName('#__users'))
                ->set('name =' . $db->quote($this->name))
                ->set('username =' . $db->quote($this->email))
                ->set('email =' . $db->quote($this->email))
                ->set('password =' . $db->quote($password))
                ->set('registerDate =' . $db->quote($date->toSQL()))
                ->set("activation =''")
                ->set('params =' . $db->quote($params))
                ->set("otpKey =''")
                ->set("otep =''")
                ->set('requireReset=' . $db->quote($front_end))
        ;
//        echo $query . '<br>';
//      Set the query using our newly populated query object and execute it.
        $db->setQuery($query);
        $db->execute();
// $db_insertid can be flakey
//        $this->user_id = $db->insertid();
// Factory::getApplication()->enqueueMessage('Unable to create User record for ' . $this->group_code . ' ' . $this->name, 'Error');
        $user_id = $this->lookupUser();
        if ($user_id > 0) {
            $this->user_id = $user_id;
            $this->linkUser(1);  // Public
            $this->linkUser(2);  // Registered
            if ($front_end == '1') {
//                Factory::getApplication()->enqueueMessage('Created MailMan user record ' . $user_id . ' for ' . $this->group_code . ' ' . $this->name, 'Info');
                $this->sendEmail();
            }
            return true;
        }
        $this->error = 'Unable to create User record for ' . $this->group_code . ' ' . $this->name;
//        die;
        return false;
    }

//protected function linkUser($group_id) {
    public function linkUser($group_id) {
//  Links User to given group
        $return == true;
        $db = Factory::getDbo();
// Check for existing record added 28/10/24 - should not be necessary
        $sql = 'SELECT COUNT(user_id) FROM ' . $db->quoteName('#__user_usergroup_map');
        $sql .= ' WHERE user_id=' . $db->quote($this->user_id) . ' AND group_id=' . $db->quote($group_id);
        $count = (int) $this->toolshelper->getValue($sql);
//        $db = Factory::getDbo();
        if ($count == 0) {
            $query = $db->getQuery(true);
            $query
                    ->insert($db->quoteName('#__user_usergroup_map'))
                    ->set('user_id =' . $db->quote($this->user_id))
                    ->set('group_id=' . $db->quote($group_id));
            $db->setQuery($query);
//        echo $query . '<br>';
            $return = $db->execute();
            if ($return == false) {
                $this->error = 'Unable to link ' . $this->user_id . ' to ' . $group_id;
                Factory::getApplication()->enqueueMessage('Unable to link MailMan user ' . $group_id, 'Warning');
            }
        }
        return $return;
    }

    private function lookupColumns($fields) {

        if (count($fields) == 1) {
            echo '... ' . "Array has only one entry" . '<br>';
            echo '... ' . 'Data is not comma delimited' . '<br>';
            $this->success = false;
//               return $this->success;
        }
        $pointer = 0;
        $this->cForename = '';
        $this->cSurname = '';
        $this->cEmail = '';
        $this->cGroup = '';
        foreach ($fields as $field) {
            if ($field == 'Forenames') {
                $this->cForename = $pointer;
            } elseif ($field == 'Last Name') {
                $this->cSurname = $pointer;
            } elseif ($field == 'Email Address') {
                $this->cEmail = $pointer;
            } elseif ($field == 'Group Code') {
                $this->cGroup = $pointer;
            }
            $pointer++;
        }

        $error = false;
        if ($this->cForename == '') {
            $this->cForename = 'not found';
            $error = true;
        }
        if ($this->cSurname == '') {
            $this->cSurname = 'not found';
            $error = true;
        }
        if ($this->cEmail == '') {
            $this->cEmail = 'not found';
            $error = true;
        }
        if ($this->cGroup == '') {
            $this->cGroup = 'not found';
            $error = true;
        }
        echo 'Forename<b> ' . $this->cForename . '</b>, ';
        echo 'Surname<b> ' . $this->cSurname . '</b>, ';
        echo 'Email<b> ' . $this->cEmail . '</b>, ';
        echo 'Group<b> ' . $this->cGroup . '</b><br>';
        if ($error == true) {
            die;
        }
    }

    protected function lookupUser() {
        $this->user_id = 0;
        $sql = 'SELECT id, name FROM #__users WHERE email="' . $this->email . '"';
//        echo $sql . '<br>';
        $item = $this->toolshelper->getItem($sql);
        $user_id = (int) $item->id;
        if ($user_id > 0) {
            $this->name = $item->name;
            $this->user_id = $item->id;
        }
        return $user_id;
    }

    protected function parseLine($data) {
        /*
         * Sets up the internal fields this->name, this->email etc
         * The format of the line depends on the type of data being loaded
         */

        $validation_message = '';
        switch ($this->method_id) {
            case 3:     // Download from Insight Hub <<<<<<<<<<<<<<<<<<<<<<<<<<<
// First record is just column headings
                if ($this->record_count == 1) {
                    return 0;
                } else {
                    if ($this->record_count == 2) {
                        echo '<b>First record:</b><br>';
                        echo 'Forename=' . $data[$this->cForename] . '<br>';
                        echo 'Surname=' . $data[$this->cSurname] . '<br>';
                        echo 'Email=' . $data[$this->cEmail] . '<br>';
                        echo 'Group=' . $data[$this->cGroup] . '<br>';
                    }
                    $response = true;
                    $validation_message = '';
                    $this->name = trim($data[$this->cForename]) . ' ' . trim($data[$this->cSurname]);
                    if ($this->name == '') {
                        $this->error_count++;
                        $validation_message = '<b>Record has no name. ' . "</b><br>";
                        $response = false;
                    }
                    $this->group_code = trim($data[$this->cGroup]);
                    if ($this->group_code == '') {
                        $this->error_count++;
                        $validation_message = '<b>Record has no group_code. ' . "</b>";
                        $response = false;
                    }
//                    if (!$this->group_code = $data[$this->cGroup]) {
//                        $this->error_count++;
//                        echo '<b>' . $this->name . ' is in ' . $data[$this->cGroup] . ', not in Group ' . $this->group_code . ". </b><br>";
//                        $response = false;
//                    }
                    $this->email = trim($data[$this->cEmail]);
                    if ($this->email == '') {
                        $this->error_count++;
                        $validation_message .= '<b>Record  has no email</b>, name=' . $this->name . '<br>';
                        $response = false;
                    } else {
                        if (!$this->validEmailFormat($this->email)) {
                            $this->error_count++;
                            echo "User $this->name: email address '$this->email' is considered invalid. <br>";
                            $response = false;
                        }
                    }
                    if ($validation_message !== '') {
                        $this->error_report .= $this->record_count . ': ' . implode(',', $data) . '<br>';
                        $this->error_report .= $this->error_count . ': ' . $validation_message . '<br>';
                    }
                    return $response;
                }

            case 4:  // Mailchimp  <<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<
// First record is just column headings
                if ($this->record_count == 1) {
                    echo $this->record_count . ': Ignoring header row<br>';
                    return 0;
                } else {
                    $response = true;

                    $this->name = trim($data[1]) . ' ';
                    $this->email = trim($data[0]);
                    if ($data[2] == '') {
                        $this->name .= $data[4];
                    } else {
                        $this->name .= $data[2];
                    }

                    if (trim($this->name) == '') {
                        $this->error_count++;
                        $validation_message .= '<b>Record ' . $this->record_count . '</b> has no name. ';
                        $response = false;
                    }
                    $this->email = trim($data[0]);
                    if ($this->email == '') {
                        $this->error_count++;
                        $validation_message .= '<b>Third column (email) is blank. ';
                        $response = false;
                    } else {
                        if (!$this->validEmailFormat($this->email)) {
                            $this->error_count++;
                            $validation_message .= $this->email . ' is considered invalid. ';
                            $response = false;
                        }
                    }
                    if ($validation_message !== '') {
                        $this->error_report .= $this->record_count . ': ' . implode(',', $data) . '<br>';
                        $this->error_report .= $validation_message . '<br>';
                    }
                    return $response;
                }
            case 5:    // simple csv file <<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<
                if ($this->record_count == 1) {
                    echo 'Ignoring header row<br>';
                    return false;
                } else {
                    $this->group_code = trim($data[0]);
                    $this->name = trim($data[1]);
                    $this->email = trim($data[2]);
                    if ($this->group_code == '') {
                        $this->error_count++;
                        $validation_message .= 'First column (Group code) is blank. ';
//                       echo '<b>First column (Group code) is blank' . "</b><br>";
                    } else {
// needs tools version 3.2.3
                        if ($this->toolshelper->validGroupcode($this->group_code) === false) {
                            $this->error_count++;
                            $validation_message .= 'Invalid Group code ' . $this->toolshelper->error;
//                            echo '<b>Invalid Group code</b> ' . $this->group_code . ' ';
                        }
                    }

                    if ($this->name == '') {
                        $this->error_count++;
                        $validation_message .= ' Second column (name) is blank. ';
                    }

                    if ($this->email == '') {
                        $this->error_count++;
                        $validation_message .= ' Third column (email) is blank. ';
                    } else {
                        if (!$this->validEmailFormat($this->email)) {
                            $this->error_count++;
                            $validation_message .= ' Invalid email format. ';
                        }
                    }
// Check the email and user name
                    if (($this->email !== '') AND ( $this->name !== '')) {
                        $message = $this->userExists($this->email, $this->name);
                        if ($message !== '') {
                            $this->error_count++;
                            $validation_message .= $message;
                        }
                    }
// Check for the correct group code
                    if ($validation_message !== '') {
                        $this->error_report .= $this->record_count . ': ' . implode(',', $data) . '<br>';
                        $this->error_report .= 'Error : ' . $validation_message . '<br>';
                        echo $this->error_report . '<br><br>';
                        return false;
                    }
                    return true;
                }
        }
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
        $objSubscription = new SubscriptionHelper;
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
//                $this->blockUser($row->user_id);
            } else {
//                $this->purgeUser($row->user_id);
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

                if ($this->method_id == 3) {
                    echo 'Calculating column referenced from header row<br>';
                    $this->lookupColumns($data);
                } else {
                    echo 'Ignoring header row<br>';
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
                            $response = $this->createUserDirect();
                            if ($response) {
                                $user_id = $this->user_id;  // As just created
                                $message .= ', User created';
                                if (JDEBUG) {
                                    $message .= ', id=' . $user_id;
                                }
                                $this->createPreferredName();
                                $this->createProfile();
                                $this->users_created++;
                                $subscription_required = true;
                            } else {
                                $subscription_required = false;
                                $message .= ', Error creating User ' . $this->name . '/' . $this->email;
                            }
                        }
//                        echo $message . '<br>';
                    } else {
//                        $message = '';
                        $message .= 'User ' . $this->name . ' exists for ' . $this->email;
                        $method = $this->objMailHelper->isSubscriber($this->list_id, $user_id);
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
                        if ($this->objMailHelper->subscribe($this->list_id, $user_id, $this->record_type, $this->method_id)) {
                            $message .= ', Subscription created';
                            $this->new_subs[] = $this->name . ',' . $this->email;
                            $this->subscription_count++;
                        } else {
                            $message .= ' ' . $this->objMailHelper->message;
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

    public function purgeUser($user_id) {
//    Delete Subscriptions, Subscriptions Audit, Recipients, Profile and User record itself
        $sql = 'SELECT u.name, p.preferred_name ';
        $sql .= 'FROM `#__users` AS u ';
        $sql .= 'LEFT JOIN #__ra_profiles AS p on p.id = u.id ';
        $sql .= 'WHERE u.id=' . $user_id;
        $item = $this->toolshelper->getItem($sql);
        $message = 'Purged all records for ' . $item->name . '/' . $item->preferred_name;
        if (JDEBUG) {
            $message .= ' (' . $user_id . ')';
        }
        if ($user_id > 0) {
// delete details of any emails sent
            $sql = 'DELETE FROM #__ra_mail_recipients WHERE user_id=' . $user_id;
//            echo $sql . '<br>';
            $this->toolshelper->executeCommand($sql);

// Delete any subscriptions
            $sql = 'SELECT id FROM #__ra_mail_subscriptions WHERE user_id=' . $user_id;
            $rows = $this->toolshelper->getRows($sql);
            foreach ($rows as $row) {
                $sql = 'DELETE FROM  #__ra_mail_subscriptions_audit ';
                $sql .= 'WHERE object_id=' . $row->id;
//                echo $sql . '<br>';
                $this->toolshelper->executeCommand($sql);
                $sql = 'DELETE FROM #__ra_mail_subscriptions WHERE id=' . $user_id;
//                echo $sql . '<br>';
                $this->toolshelper->executeCommand($sql);
            }
            $sql = 'DELETE FROM #__ra_profiles WHERE id=' . $user_id;
            $this->toolshelper->executeCommand($sql);

// delete profile audit records
//                $sql = 'DELETE FROM #__ra_profiles_audit WHERE object_id=' . $user_id;
//                echo $sql . '<br>';
//                $this->toolshelper->executeCommand($sql);

            $sql = 'DELETE FROM #__user_usergroup_map WHERE user_id=' . $user_id;
            $this->toolshelper->executeCommand($sql);
            $sql = 'DELETE FROM #__user_notes WHERE id=' . $user_id;
            $this->toolshelper->executeCommand($sql);
            $sql = 'DELETE FROM #__user_profiles WHERE user_id=' . $user_id;
            $this->toolshelper->executeCommand($sql);
            $sql = 'DELETE FROM #__users WHERE id=' . $user_id;
            $this->toolshelper->executeCommand($sql);
        }
        echo $message . '<br>';
    }

    public function sendEmail() {
// send email to the administrator
        $params = ComponentHelper::getParams('com_ra_mailman');
        $notify_id = $params->get('email_new_user', '0');

        if ($notify_id > 0) {
            $sql = 'SELECT email FROM #__users WHERE id=' . $notify_id;
            $to = $this->toolshelper->getValue($sql);
            if ($to == '') {
                Factory::getApplication()->enqueueMessage('Unable to find email address to notify user ' . $notify_id, 'Warning');
                return;
            }
            $title = 'A new user has been registered to MailMan';
            $body = 'New user registration:' . '<br>';
            $today = Factory::getDate('now', Factory::getConfig()->get('offset'));
            $body .= 'Date <b>' . HTMLHelper::_('date', $today, 'd M y') . '</b><br>';
            $body .= 'Time <b>' . HTMLHelper::_('date', $today, 'h.i') . '</b><br>';
            $body .= 'Name <b>' . $this->name . '</b><br>';
            $body .= 'Group <b>' . $this->group_code . '</b><br>';
            $body .= 'Email <b>' . $this->email . '</b><br>';
            $response = $this->toolshelper->sendEmail($to, $to, $title, $body);
            if ($response) {
                Factory::getApplication()->enqueueMessage('Notification sent to ' . $to, 'Info');
            }
        }
    }

    public function test($name, $email) {
        $message = $this->userExists($email, $name);
        if ($message == '') {
            echo "$email $name: valid<br>";
        } else {
            echo $message . '<br>';
        }
        echo '<br>';
        return false;

        $this->list_id = 1;
        $this->processLapsers();

//       $userTable = Table::getInstance('User', 'Table', array());
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
        $message = '';
        $sql = 'SELECT name, username, email FROM #__users WHERE email="' . $email . '"';
//       echo "$sql<br>";
        $user1 = $this->toolshelper->getItem($sql);
// Check if real name is already present
        $sql = 'SELECT name, username, email FROM #__users WHERE name="' . $real_name . '"';
        $user2 = $this->toolshelper->getItem($sql);

        if (is_null($user1)) {
            if (is_null($user2)) {
//              echo 'email not found<br>';
                if (is_null($user2)) {
                    return '';
                } else {

                }
            } else {
                $message = $real_name . '/' . $email . ' is invalid: name ' . $real_name . ' already in use with email ' . $user2->email;
            }
        } else {
//           echo 'email found for ' . $user1->name . '<br>';
            if ($user1->name == $real_name) {
                return '';
            } else {
                $message = $real_name . '/' . $email . ' is invalid: email ' . $email . ' is already in use with name  ' . $user1->name;
            }
        }
        return $message;
    }

    private function validEmailFormat($value) {
        return str_contains($value, '@');

// Following code always gives false
        if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
            echo("$value is a valid email address");
            return true;
        } else {
            echo("$value is not a valid email address");
            return false;
        }

        if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return true;
        }
        echo "Validating Email address: '$value' is considered invalid.\n";
        return false;
    }

    /**
     *   Random Key
     *
     *   @returns a string
     * */
    public static function randomKey($size) {
// Created 26/04/22 from https://stackoverflow.com/questions/1904809/how-can-i-create-a-new-joomla-user-account-from-within-a-script
        $bag = "abcefghijknopqrstuwxyzABCDDEFGHIJKLLMMNOPQRSTUVVWXYZabcddefghijkllmmnopqrstuvvwxyzABCEFGHIJKNOPQRSTUWXYZ";
        $key = array();
        $bagsize = strlen($bag) - 1;
        for ($i = 0;
                $i < $size;
                $i++) {
            $get = rand(0, $bagsize);
            $key[] = $bag[$get];
        }
        return implode($key);
    }

}
