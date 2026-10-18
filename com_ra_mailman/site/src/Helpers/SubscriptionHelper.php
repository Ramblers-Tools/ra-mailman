<?php

/**
 * Contains functions used in the back end and the front end
 * @version    4.2.0
 * @package    com_ra_mailman
 * @author charles
 * Created Feb 2022
 * This contains generic code to Add/Delete/Get data or Update an Entity
 * (in this case a Subscription).
 * 13/12/21 CB Created using a code generator
 * 15/01/22 CB changed comparison of fields: for some unknown reason needed "else" when comparing old and new values
 * 22/04/22 CB correction for current_user
 * 19/05/22 CB Don't user SERVER for ip_address
 * 03/06/22 CB include JFactory
 * 20/07/22 CB use current_ip_address when updating records
 * 21/06/23 CB always create expiry date
 * 03/09/23 CB always update expiry date when renewing subscription
 * 04/01/24 CB createAudit in camelCase
 * 30/01/24 CB correction for expiry date
 * 25/10/24 CB update $this->action for renewed / cancelled
 * 30/10/24 CB new function block
 * 04/11/24 CB use getIdentity instead of getUser
 * 05/11/24 CB if self registering, user new user id instead of current user
 * 26/11/24 CB correct reset of reminder
 * 12/02/25 CB replace getIdentity with Factory::getApplication()->getSession()->get('user')
 */

namespace Ramblers\Component\Ra_mailman\Site\Helpers;

use Joomla\CMS\Factory;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

class SubscriptionHelper {

    protected $current_user;
    public $fields_modified;
    public $action;
    protected $current_ip_address;
    public $message;
// database fields
    public $id;
    public $list_id;
    public $user_id;
    public $record_type;
    public $method_id;
    public $state;
    public $ip_address;
    public $created;
    public $created_by;
    public $modified;
    public $modified_by;
    public $expiry_date;
    public $reminder_sent;

    function __construct() {
        $this->id = 0;
        $this->list_id = 0;
        $this->user_id = 0;
        $this->record_type = 0;
        $this->method_id = 0;
        $this->state = 0;
        $this->modified = 0;
        $this->created_by = 0;
        $this->modified_by = 0;
        $this->reminder_sent = 0;
        $this->action = 'Failed';
        $this->message = '';
        $this->current_ip_address = Factory::getApplication()->input->server->get('REMOTE_ADDR', '');
        $this->current_user = Factory::getApplication()->getSession()->get('user')->id;
    }

    function add() {
//      Always assume subscription will be for 12 months
        $date = Factory::getDate();
//        $date = Factory::getDate('now', Factory::getConfig()->get('offset'));
        $date->modify('+1 year');
        $db = Factory::getDbo();
        if ($this->current_user == 0) {  // self registering
            $user = $this->user_id;
        } else {
            $user = $this->current_user;
        }
// Create a new query object.
        $query = $db->getQuery(true);
// Prepare the insert query.
        $query
                ->insert($db->quoteName('#__ra_mail_subscriptions'))
                ->set('list_id =' . $db->quote($this->list_id))
                ->set('user_id =' . $db->quote($this->user_id))
                ->set('record_type =' . $db->quote($this->record_type))
                ->set('method_id =' . $db->quote($this->method_id))
                ->set('state =' . $db->quote($this->state))
                ->set('ip_address =' . $db->quote($this->current_ip_address))
                ->set('created =' . $db->quote(Factory::getDate()->toSQL()))
                ->set('created_by =' . $db->quote($user))
                ->set('expiry_date=' . $db->quote(substr($date->toSql(), 0, 10)));
//        }
//        die($date->toSql(true));
// Set the query using our newly populated query object and execute it.
        $db->setQuery($query);
        $db->execute();
//       if (JDEBUG) {
//            Factory::getApplication()->enqueueMessage($query, 'notice');
//        }
        $this->id = $db->insertid();
        if ($this->id == 0) {
            return false;
        }
        $this->createAudit('Record', '', 'created');
        $this->createAudit('list_id', '', $this->list_id);
        $this->createAudit('user_id', '', $this->user_id);
        $this->createAudit('record_type', '', $this->record_type);
        $this->createAudit('method_id', '', $this->method_id);
        $this->createAudit('expiry_date', '', $date);
        $this->createAudit('state', '', $this->state);
        $this->createAudit('ip_address', '', $this->current_ip_address);
        $this->message = "Record created";
        $this->action = 'subscribed to';
        return true;
    }

    public function block($list_id, $user_id) {
// this is invoked if a user is 'blocked' because they have ceased to appear
// on the Corporate feed.
// state is set to -2 (purged)
// this ensures an audit record is created
        $this->list_id = $list_id;
        $this->user_id = $user_id;
        $this->getData();
        $this->resetExpiry();
        $this->resetReminder();
        $this->state = -2;
// set expiry date will be set to today by update
        if ($this->update()) {
            $this->message = 'Cancelled';
        }
    }

    public function bumpAll($user_id) {
        /*
         * Renews any active subscriptions for the specified User
         * Invoked from an email generated by a CLI programme, via Mail_lstController
         */
        $sql = 'SELECT s.id, s.list_id, l.name, s.expiry_date, s.reminder_sent ';
        $sql .= 'FROM #__ra_mail_subscriptions AS s ';
        $sql .= 'INNER JOIN `#__ra_mail_lists` AS l ON l.id = s.list_id ';
        $sql .= 'WHERE s.user_id=' . $user_id;
        $sql .= ' AND s.state=1';
        $this->objHelper->showQuery($sql);
        $rows = $this->objHelper->getRows($sql);
        foreach ($rows as $row) {
            $this->bumpSubscription($row->list_id, $user_id);
        }
    }

    public function bumpExpiry() {
// sets expiry_date to 12 months time
        $this->expiry_date = date('Y-m-d', strtotime('+1 year'));

//$date = Factory::getDate('now',Factory::getConfig()->get('offset'));
//$this->expiry_date = substr($date->toSql(true),0,10);}
    }

    public function bumpSubscription($list_id, $user_id) {
        /*
         * Renews given subscriptions for the specified User
         * Invoked internally, or from an email generated by a CLI programme, via Mail_lstController
         */
        $this->list_id = $list_id;
        $this->user_id = $user_id;
        $this->getData();
        $this->resetReminder();
        $this->bumpExpiry();
        $this->update();
    }

    public function cancel() {
// Invoked when processing leavers
// this->list_id and $this->user id will have been set before this call
        $this->getData();
        $this->state = 0;
        $this->update();
    }

    public function cancelSubscription($id) {

        $objHelper = new ToolsHelper;
        $sql = 'SELECT s.list_id, s.user_id, s.expiry_date, ';
        $sql .= 'p.preferred_name, l.name as `list` ';
        $sql .= 'FROM `#__ra_mail_subscriptions` AS s ';
        $sql .= 'LEFT JOIN #__ra_profiles AS p ON p.id = s.user_id ';
        $sql .= 'LEFT JOIN #__ra_mail_lists AS l ON l.id = s.list_id ';
        $sql .= 'WHERE s.id=' . $id;
        $sql .= " AND p.state = 1 AND p.home_group <> 'ZZ99'";
        $item = $objHelper->getItem($sql);
        $this->list_id = $item->list_id;
        $this->user_id = $item->user_id;
        $this->getData();
        $this->state = 0;
        $this->update();
        $this->action = 'Cancelled from';
        $this->message = 'User ' . $item->preferred_name . ' cancelled from list ' . $item->list;
        Factory::getApplication()->enqueueMessage('Subscription has been cancelled', 'info');
    }

    function createAudit($field_name, $old_value, $new_value) {
        $this->message .= ", updating " . $field_name;
        $this->fields_updated++;
//            $this->createAuditRecord($field_name, $old_value, $new_value, $this->id, "#__ra_mail_subscriptions");
        $db = Factory::getDbo();
        if ($this->current_user == 0) {
            $user = $this->user_id;
        } else {
            $user = $this->current_user;
        }
// Create a new query object.
        $query = $db->getQuery(true);
// Prepare the insert query.
        $query
                ->insert($db->quoteName('#__ra_mail_subscriptions_audit'))
                ->set('object_id =' . $db->quote($this->id))
                ->set('field_name =' . $db->quote($field_name))
                ->set('old_value =' . $db->quote($old_value))
                ->set('new_value =' . $db->quote($new_value))
                ->set('ip_address =' . $db->quote($this->current_ip_address))
//                ->set('created =' . $db->quote($date->toSQL()))
                ->set('created_by =' . $db->quote($user));
//             Factory::getApplication()->enqueueMessage($this->_db->replacePrefix($query), 'message');
//        echo $db->replacePrefix($query) . '<br>';

        $db->setQuery($query);
        $db->execute();
    }

    function delete($list_id, $user_id) {
        $db = Factory::getDbo();
        $query = $db->getQuery(true);
        $query->delete($db->quoteName('#__ra_mail_subscriptions_audit'));
        $query->where('object_id=' . $this->id);
        $db->setQuery($query);
        if ($db->execute()) {
            $query->delete($db->quoteName('#__ra_mail_subscriptions'));
            $query->where('id=' . $this->id);
            try {
                $db->setQuery($query);
                $result = $db->execute();
            } catch (Exception $ex) {
                $this->error = $ex->getCode() . ' ' . $ex->getMessage();
            }
        }
        return $result;
    }

    function getData() {
        if ($this->list_id == 0) {
            $this->message = "clssubscribersGetData - list_id is zero";
            return 0;
        }
        if ($this->user_id == 0) {
            $this->message = "clssubscribersGetData - user_id is zero";
            return 0;
        }
        $this->fields_updated = 0;
        $db = Factory::getDbo();

        $query = $db->getQuery(true);

        $query->select('id, list_id, user_id, record_type, method_id');
        $query->select('state, ip_address');
        $query->select('created, created_by, modified, modified_by,expiry_date, reminder_sent');
        $query->from('`#__ra_mail_subscriptions`');
        $query->where($db->qn('list_id') . ' = ' . $db->q($this->list_id));
        $query->where($db->qn('user_id') . ' = ' . $db->q($this->user_id));
        $db->setQuery($query);
//        echo $query;
        $row = $db->loadObject();
        if ($row) {
            $this->id = $row->id;
            $this->user_id = $row->user_id;
            $this->record_type = $row->record_type;
            $this->method_id = $row->method_id;
            $this->state = $row->state;
            $this->ip_address = $row->ip_address;
            $this->created = $row->created;
            $this->created_by = $row->created_by;
            $this->modified = $row->modified;
            $this->modified_by = $row->modified_by;
            $this->reminder_sent = $row->reminder_sent;
            $this->expiry_date = $row->expiry_date;
            $this->message = 'id=' . $this->id;
            return 1;
        } else {
            $this->message = 'Database error on get';
            return 0;
        }
    }

    public function forceRenewal($list_id, $user_id) {
        $date = Factory::getDate();
        $db = Factory::getDbo();
//        $objHelper = new ToolsHelper;
//        $sql = 'SELECT list_id, user_id,expiry_date FROM `#__ra_mail_subscriptions` WHERE id=' . $id;
//        $item = $objHelper->getItem($sql);
        $this->list_id = $list_id;
        $this->user_id = $user_id;
        $this->getData();
// remember existing expiry date
        $expiry_date = $this->expiry_date;
// set expiry date to today
        $date = Factory::getDate('now', Factory::getConfig()->get('offset'));
        $this->expiry_date = $date->toSql(true);
        $this->action = 'renewed';
        $this->update();
        Factory::getApplication()->enqueueMessage('Expiry date has been reset from ' . $expiry_date, 'info');
    }

    public function resetExpiry() {
        // Sets expiry_date to today
        $this->expiry_date = $this->today;
    }

    public function resetReminder() {
        $this->reminder_sent = '';
    }

    public function setReminder() {
        // Sets $reminder_sent to today
        $this->reminder_sent = $this->today;
    }

    public function today() {
        // Returns current date, formatted correctly
        $date = Factory::getDate('now', Factory::getConfig()->get('offset'));
        return substr($date->toSql(true), 0, 10);
    }

    function update() {
// If subscription is being cancelled, expiry date is set to today
//
// If record was created by the Corporate feed:
//    If being updated from the feed, expiry_date is rolled forward 12 months
// All other updates,
//    expiry_date date is rolled forward 12 months
//
        $fields = array();    // List of fields requiring update
        $this->fields_updated = 0;
        $db = Factory::getDbo();
        $query = $db->getQuery(true);

// find existing field values in the database
        $query->select('*');
        $query->from('`#__ra_mail_subscriptions`');
        $query->where($db->qn('list_id') . ' = ' . $db->q($this->list_id));
        $query->where($db->qn('user_id') . ' = ' . $db->q($this->user_id));
        $db->setQuery($query);
        $row = $db->loadObject();
        if (!$row) {
            $this->message = "Can't find record for " . $this->list_id . '/' . $this->user_id;
            return 0;
        }

///////////////////////////////////////////////////
// Must create audit records before record itself is updated,
// otherwise original value will be lost
// values in $row come from the existing data, $this->xx are the new values

        if ($row->record_type != $this->record_type) {
            $fields[] = $db->quoteName('record_type') . '=' . $db->quote($this->record_type);
            $this->createAudit("record_type", $row->record_type, $this->record_type);
            if ($this->record_type == 2) {
                $this->action = 'granted Authorship';
            } elseif ($this->record_type == 1) {
                $this->action = 'made subscriber to';
            }
        }
        if ($row->method_id != $this->method_id) {
            $fields[] = $db->quoteName('method_id') . '=' . $db->quote($this->method_id);
            $this->createAudit("method_id", $row->method_id, $this->method_id);
        }
        if ($row->state != $this->state) {
            $fields[] = $db->quoteName('state') . '=' . $db->quote($this->state);
            $this->createAudit("state", $row->state, $this->state);
            if (($row->state == 0) AND ($this->state == 1)) {    // i.e changing state to 1
                $this->action = 're-subscribed';
            } else {                   // i.e currently state = 1, changing to 0 or -2
                $this->action = 'cancelled from';
            }
        }
        if ($row->ip_address != $this->current_ip_address) {
            $fields[] = $db->quoteName('ip_address') . ' = ' . $db->quote($this->current_ip_address);
            $this->createAudit("ip_address", $row->ip_address, $this->current_ip_address);
        }
        if ($row->modified_by != $this->current_user) {
            $fields[] = $db->quoteName('modified_by') . ' = ' . $db->quote($this->current_user);
            $this->createAudit("modified_by", $row->modified_by, $this->current_user);
        }
        if ($row->expiry_date != $this->expiry_date) {
            if ($this->expiry_date == '') {
                $fields[] = $db->quoteName('expiry_date') . '= NULL';
            } else {
                $fields[] = $db->quoteName('expiry_date') . ' = ' . $db->quote($this->expiry_date);
            }
            $this->createAudit("expiry_date", $row->expiry_date, $this->expiry_date);
        }
        if ($row->reminder_sent != $this->reminder_sent) {
            if ($this->reminder_sent == '') {
                $fields[] = $db->quoteName('reminder_sent') . '= NULL';
            } else {
                $fields[] = $db->quoteName('reminder_sent') . ' = ' . $db->quote($this->expiry_date);
            }
            $this->createAudit("reminder_sent", $row->reminder_sent, $this->reminder_sent);
        }

// Most of the fields to update will by now have been set
        $today = Factory::getDate('now', Factory::getConfig()->get('offset'));
        $fields[] = $db->quoteName('modified') . ' = ' . $db->quote($today->toSql(true));
        $fields[] = $db->quoteName('modified_by') . ' = ' . $db->quote($this->current_user);

// Conditions for which records should be updated.
        $conditions = array(
            $db->quoteName('id') . ' = ' . $db->quote($this->id)
        );
        $query = $db->getQuery(true);
        $query->update($db->quoteName('#__ra_mail_subscriptions'))->set($fields)->where($conditions);
        $db->setQuery($query);
//        die($query);
//        $this->message .= 'sql=' . (string) $query;
        if ($db->execute()) {
            $this->message .= "Record updated";
            return 1;
        } else {
            $this->message .= $query->toSql();
            $this->action = 'update failed';
        }
        return 0;
    }

// These public functions commented our 17 November 2014
//    function set_list_id($newValue) {
//        $this->list_id = (int) $newValue; // ensure value is numeric
//    }
//    function set_user_id($newValue) {
//        $this->user_id = (int) $newValue; // ensure value is numeric
//    }
//    function set_reminder_sent($newValue) {
//        $this->reminder_sent = $newValue;
//    }
//    function set_record_type($newValue) {
//        $this->record_type = (int) $newValue; // ensure value is numeric
//   }
//    function set_method_id($newValue) {
//        $this->method_id = (int) $newValue; // ensure value is numeric
//    }
//    function set_state($newValue) {
//        $this->state = (int) $newValue; // ensure value is numeric
//    }
}
