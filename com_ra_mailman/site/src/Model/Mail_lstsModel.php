<?php

/**
 * @version    4.6.3
 * @package    com_ra_mailman
 * @author     Charlie Bigley <webmaster@bigley.me.uk>
 * @copyright  2023 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 21/11/23 CB replace prefix before display of sql
 * 30/01/24 CB include list owner in search
 * 14/10/24 CB exclude ordering from fields selected
 * 14/11/24 CB allow sort by preferred_name
 * 30/07/25 search for ID: using helper
 * 14/08/25 CB get all fields
 * 01/10/25 CB correct sort fields
 * 16/03/26 CB filter by group if not full_version
 */

namespace Ramblers\Component\Ra_mailman\Site\Model;

// No direct access.
defined('_JEXEC') or die;

use \Joomla\CMS\Factory;
use \Joomla\CMS\Language\Text;
use \Joomla\CMS\MVC\Model\ListModel;
use \Joomla\Component\Fields\Administrator\Helper\FieldsHelper;
use \Ramblers\Component\Ra_mailman\Site\Helpers\Mailhelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

/**
 * Methods supporting a list of Ra_mailman records.
 *
 * @since  1.0.6
 */
class Mail_lstsModel extends ListModel {

protected $app;
    /**
     * Constructor.
     *
     * @param   array  $config  An optional associative array of configuration settings.
     *
     * @see    JController
     * @since  1.0.6
     */
    public function __construct($config = array()) {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = array(
                'id', 'a.id',
                'created_by', 'a.created_by',
                'a.name',
                'a.group_code',
                'p.preferred_name',
                'a.record_type',
                'a.emails_outstanding',
                'a.home_group_only',
            );
        }
        $this->app = Factory::getApplication();
        parent::__construct($config);
    }

    /**
     * Method to auto-populate the model state.
     *
     * Note. Calling getState in this method will result in recursion.
     *
     * @param   string  $ordering   Elements order
     * @param   string  $direction  Order direction
     *
     * @return  void
     *
     * @throws  Exception
     *
     * @since   1.0.6
     */
    protected function populateState($ordering = null, $direction = null) {
        // List state information.
        parent::populateState('a.name', 'ASC');

        
        $list = $this->app->getUserState($this->context . '.list');

        $value = $this->app->getUserState($this->context . '.list.limit', $this->app->get('list_limit', 25));
        $list['limit'] = $value;

        $this->setState('list.limit', $value);

        $value = $this->app->input->get('limitstart', 0, 'uint');
        $this->setState('list.start', $value);

        $ordering = $this->getUserStateFromRequest($this->context . '.filter_order', 'filter_order', 'a.description');
        $direction = strtoupper($this->getUserStateFromRequest($this->context . '.filter_order_Dir', 'filter_order_Dir', 'ASC'));

        if (!empty($ordering) || !empty($direction)) {
            $list['fullordering'] = $ordering . ' ' . $direction;
        }

        $this->app->setUserState($this->context . '.list', $list);

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

    /**
     * Build an SQL query to load the list data.
     *
     * @return  DatabaseQuery
     *
     * @since   1.0.6
     */
    protected function getListQuery() {
        // See if we are running the full version
        $toolsHelper = new ToolsHelper;
        $mailHelper = new MailHelper;
        $group = $mailHelper->getDefaultGroup();
        // Create a new query object.
        $db = $this->getDbo();
        $query = $this->_db->getQuery(true);

        $query->select('a.*');
        $query->select("CASE WHEN a.record_type = 'O' THEN 'Open' ELSE 'Closed' END AS 'list_type'");
        $query->select("CASE WHEN a.home_group_only = 1 THEN 'Yes' ELSE 'No' END AS 'public'");

        $query->from('#__ra_mail_lists AS a');
        $query->select('p.preferred_name AS `owner`');
        $query->leftJoin($this->_db->qn('#__ra_profiles') . ' AS `p` ON p.id = a.owner_id');

        $query->where('a.state = 1');
        // For non full version, only show lists that the current User is allowed to use
        if (($group !== 'N') AND ($toolsHelper->isSuperuser() === false)) {
             $query->where('a.group_code=' . $this->_db->quote($group));
 //           $user = $this->app->getSession()->get('user');
 //           $query->leftJoin(' #__ra_mail_subscriptions AS s ON s.list_id = a.id ');            
 //           $query->where('s.record_type>1');
 //           $query->where('s.user_id=' . $user->id);
        }
        // Search for this word
        $searchWord = $this->getState('filter.search');

        // Search in these columns passed to frontend helper)
        $searchColumns = array(
            'a.name',
            'p.preferred_name',
            'a.group_code',
            'a.record_type',
            'a.home_group_only',
        );

        // Filter by search
        $search = $this->getState('filter.search');
        $filter_fields = array(
            'a.group_code',
            'a.name',
            'p.preferred_name',
        );

        if (!empty($search)) {
            $query = ToolsHelper::buildSearchQuery($search, $filter_fields, $query);
        }

        // Add the list ordering clause.
        $orderCol = $this->state->get('list.ordering', 'a.name');
        $orderDirn = $this->state->get('list.name', 'ASC');
        if ($orderCol && $orderDirn) {
            if ($orderCol && $orderDirn) {
                $query->order($this->_db->escape($orderCol . ' ' . $orderDirn));
                if ($orderCol == 'a.group_code') {
                    $query->order('a.name ASC');
                } elseif ($orderCol == 'a.name') {
                    $query->order('a.group_code ASC');
                }
            }
        }
        if (JDEBUG) {
            Factory::getApplication()->enqueueMessage($this->_db->replacePrefix($query), 'message');
        }
        return $query;
    }

    /**
     * Method to get an array of data items
     *
     * @return  mixed An array of data on success, false on failure.
     */
    public function getItems() {
        $items = parent::getItems();

        return $items;
    }

    /**
     * Overrides the default function to check Date fields format, identified by
     * "_dateformat" suffix, and erases the field if it's not correct.
     *
     * @return void
     */
    protected function loadFormData() {
        $app = Factory::getApplication();
        $filters = $app->getUserState($this->context . '.filter', array());
        $error_dateformat = false;

        foreach ($filters as $key => $value) {
            if (strpos($key, '_dateformat') && !empty($value) && $this->isValidDate($value) == null) {
                $filters[$key] = '';
                $error_dateformat = true;
            }
        }

        if ($error_dateformat) {
            $app->enqueueMessage(Text::_("Invalid date format"), "warning");
            $app->setUserState($this->context . '.filter', $filters);
        }

        return parent::loadFormData();
    }

    /**
     * Checks if a given date is valid and in a specified format (YYYY-MM-DD)
     *
     * @param   string  $date  Date to be checked
     *
     * @return bool
     */
    private function isValidDate($date) {
        $date = str_replace('/', '-', $date);
        return (date_create($date)) ? Factory::getDate($date)->format("Y-m-d") : null;
    }

}
