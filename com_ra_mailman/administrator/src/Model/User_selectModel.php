<?php

/**
 * @version    4.1.11
 * @package    com_ra_mailman
 * @author     Charlie Bigley <webmaster@bigley.me.uk>
 * @copyright  2023 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 *  11/12/23 CB wildcard selection if selecting for an Area
 * 28/10/24 CB select requireReset
 * 23/06/25 CB use sub_query
 */

namespace Ramblers\Component\Ra_mailman\Administrator\Model;

// No direct access.
defined('_JEXEC') or die;

use \Joomla\CMS\MVC\Model\ListModel;
use \Joomla\Component\Fields\Administrator\Helper\FieldsHelper;
use \Joomla\CMS\Factory;
use \Joomla\CMS\Language\Text;
use \Joomla\CMS\Helper\TagsHelper;
use \Joomla\Database\ParameterType;
use \Joomla\Utilities\ArrayHelper;
use \Ramblers\Component\Ra_mailman\Site\Helpers\Mailhelper;
use \Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

/**
 * Methods supporting a list of User_select records.
 *
 * @since  1.0.3
 */
class User_selectModel extends ListModel {

    protected $list_id;
    protected $search_columns;

    /**
     * Constructor.
     *
     * @param   array  $config  An optional associative array of configuration settings.
     *
     * @see        JController
     * @since      1.6
     */
    public function __construct($config = array()) {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = array(
                'p.home_group',
                'p.preferred_name',
                'u.email',
                'method_id',
            );

            $this->search_columns = array(
                'p.home_group',
                'p.preferred_name',
                'u.email',
            );
        }

        parent::__construct($config);
    }

    /**
     * Get an array of data items
     *
     * @return mixed Array of data items on success, false on failure.
     */
    public function getItems() {
        $items = parent::getItems();

        return $items;
    }

    /**
     * Build an SQL query to load the list data.
     *
     * @return  DatabaseQuery
     *
     * @since   1.0.3
     */
    protected function getListQuery() {
        // list_id will have been passed as a parameter
        // It identifies the mailing list being updated
        $this->list_id = Factory::getApplication()->input->getInt('list_id', 0);
        $toolsHelper = new ToolsHelper;
// Create a sub query to see if users are already subscribed to the list in question
        $sub_query = $this->_db->getQuery(true);
        $sub_query->select('s.id, s.record_type, s.state');
        $sub_query->select('s.list_id, s.user_id, s.method_id');
        $sub_query->select('m.name as `Method`, ma.name AS `Access`');
        $sub_query->from('#__ra_mail_subscriptions AS s');
        $sub_query->leftJoin('#__ra_mail_methods AS m ON m.id =  s.method_id');
        $sub_query->leftJoin('#__ra_mail_access AS ma ON ma.id =  s.record_type');
        $sub_query->where('s.list_id=' . $this->list_id);

        // Create a new query object.
        $query = $this->_db->getQuery(true);

        // Check if this list is "home group only" - if so, only members from that group
        // are eligible to subscribe
        $sql = 'SELECT group_code, home_group_only FROM `#__ra_mail_lists` WHERE id=' . $this->_db->q($this->list_id);

        $list = $toolsHelper->getItem($sql);
        if ($list->home_group_only == '1') {
            if (strlen($list->group_code) == 2) {
                $query->where($this->_db->qn('p.home_group') . 'like ' . $this->_db->q($list->group_code . '%'));
            } else {
                $query->where($this->_db->qn('p.home_group') . ' = ' . $this->_db->q($list->group_code));
            }
        }

        $query->select('u.id, u.name, u.email');
        $query->select('p.home_group,p.preferred_name');
        $query->select('u.username, u.requireReset');
        $query->select('subs.id as `sub_id`, subs.record_type, subs.state, subs.Method, subs.Access');
        $query->from('#__users AS u');
        $query->innerJoin('#__ra_profiles AS p ON p.id = u.id');
        $query->leftJoin('(' . $sub_query . ') AS subs ON subs.user_id=u.id');

        // Only look for active Users
        $query->where($this->_db->qn('u.block') . '= 0');
        // Filter by published state
        $published = $this->getState('filter.state');

        // Filter by subscription method
        $methodId = $this->getState('filter.method_id');

        if (is_numeric($methodId)) {
            $query->where('subs.method_id = ' . (int) $methodId);
        }

        // Filter by search term
        $search = $this->getState('filter.search');

        if (!empty($search)) {
            // Build the search query from the search word and search columns
            $query = ToolsHelper::buildSearchQuery($search, $this->search_columns, $query);
        }

        // Add the list ordering clause.
        $orderCol = $this->state->get('list.ordering', 'p.preferred_name');
        $orderDirn = $this->state->get('list.direction', 'ASC');

        if ($orderCol && $orderDirn) {
            $query->order($this->_db->escape($orderCol . ' ' . $orderDirn));
            if ($orderCol == 'home_group') {
                $query->order('p.preferred_name ASC');
            }
        }
        if (JDEBUG) {
            Factory::getApplication()->enqueueMessage('sql=' . $this->_db->replacePrefix($query), 'message');
        }
        return $query;
    }

    // Copied from J3 - is it used?
    public function getMessage() {
        return $this->message;
    }

    /**
     * Method to get a store id based on model configuration state.
     *
     * This is necessary because the model is used by the component and
     * different modules that might need different sets of data or different
     * ordering requirements.
     *
     * @param   string  $id  A prefix for the store id.
     *
     * @return  string A store id.
     *
     * @since   1.0.3
     */
    protected function getStoreId($id = '') {
        // Compile the store id.
        $id .= ':' . $this->getState('filter.search');
        $id .= ':' . $this->getState('filter.state');
        $id .= ':' . $this->getState('filter.method_id');

        return parent::getStoreId($id);
    }

    protected function lookupUser($id) {
        $objHelper = new ToolsHelper;
        $sql = 'SELECT username FROM `#__users` WHERE id=' . $id;
        return $objHelper->getValue($sql);
    }

    /**
     * Method to auto-populate the model state.
     *
     * Note. Calling getState in this method will result in recursion.
     *
     * @param   string  $ordering   Elements order
     * @param   string  $direction  Order direction
     *
     * @return void
     *
     * @throws Exception
     */
    protected function populateState($ordering = null, $direction = null) {
        // List state information.
        parent::populateState('home_group', 'ASC');

        $context = $this->getUserStateFromRequest($this->context . '.filter.search', 'filter_search');
        $this->setState('filter.search', $context);

        // Split context into component and optional section
        if (!empty($context)) {
            $parts = FieldsHelper::extract($context);

            if ($parts) {
                $this->setState('filter.component', $parts[0]);
                $this->setState('filter.section', $parts[1]);
            }
        }
    }

    public function subscribeAll($primary_keys) {
        $app = Factory::getApplication();
        $list_id = $app->getUserState('list_id');
        $record_type = $app->getUserState('record_type');

//        JFactory::getApplication()->enqueueMessage('Subscribe multiple:' . $list_id, 'comment');
        $objMailHelper = new Mailhelper;
        $message = '';
        $error = false;
        foreach ($primary_keys as $user_id) {
            // echo $user_id
            $sub = $objMailHelper->getSubscription($list_id, $user_id);
            if ($sub) {
                if ($sub->state == 1) {
                    Factory::getApplication()->enqueueMessage('id=' . $user_id, 'error');
                    if ($message == '') {
                        $message .= $this->lookupUser($user_id);
                    } else {
                        $message .= ', ' . $this->lookupUser($user_id);
                    }
//                    $message .= 'User ' . $user_id . $this->lookupUser($user_id) . ' already subscribed to list ' . $list_id;
                    $error = true;
                }
            }
        }
//        if ($error) {
        $this->message = $message . '  already subscribed';
//        }
        return false;

        foreach ($primary_keys as $user_id) {
            $result = $objMailHelper->subscribe($this->list_id, $user_id, $this->record_type, 2);
            $message .= $objMailHelper->message;
        }
        Factory::getApplication()->enqueueMessage($message, 'notice');
    }

}
