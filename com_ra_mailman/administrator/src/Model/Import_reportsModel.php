<?php

/**
 * @version    4.4.5
 * @package    com_ra_mailman
 * @author     Charlie Bigley <webmaster@bigley.me.uk>
 * @copyright  2023 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 02/06/25 CB Created
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
use Ramblers\Component\Ra_mailman\Site\Helpers\Mailhelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

/**
 * Methods supporting a list of import_reports records.
 *
 * @since  1.0.4
 */
class Import_reportsModel extends ListModel {

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
                'l.group_code',
                'l.name',
                'u.name',
                'p.preferred_name',
                'm.name',
                'a.id',
                'a.num_errors',
                'a.num_users',
                'a.num_subs',
                'a.num_lapsed',
                'a.ip_address',
                'list_id',
                'method_id',
                'state',
            );
        }

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
     * @return void
     *
     * @throws Exception
     */
    protected function populateState($ordering = null, $direction = null) {
        // List state information.
        parent::populateState('date_phase1', 'DESC');

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
     * @since   1.0.4
     */
    protected function getStoreId($id = '') {
        // Compile the store id.
        $id .= ':' . $this->getState('filter.search');
        $id .= ':' . $this->getState('filter.list_id');
        $id .= ':' . $this->getState('filter.method_id');
        $id .= ':' . $this->getState('filter.state');

        return parent::getStoreId($id);
    }

    /**
     * Build an SQL query to load the list data.
     *
     * @return  DatabaseQuery
     *
     * @since   1.0.4
     */
    protected function getListQuery() {
        // Create a new query object.
        $query = $this->_db->getQuery(true);

        $query->select('a.id, a.state, a.ip_address, a.user_id, a.state');
        $query->select('a.date_phase1,a.date_completed');
        $query->select("CASE WHEN a.state = 0 THEN 'Inactive' ELSE 'Active' END AS 'Status'");
        $query->select('a.method_id, a.input_file, a.created, a.modified');
        $query->select('a.num_errors,a.num_users,a.num_subs, a.num_lapsed');
        $query->select('l.name AS `list`, l.id as list_id');
        $query->select('l.group_code AS `group`');
        $query->select('m.name AS `Method`');
        $query->select("p.preferred_name");
        $query->from('`#__ra_import_reports` AS a');
        $query->innerJoin($this->_db->qn('#__ra_mail_methods') . ' AS `m` ON m.id = a.method_id');
        $query->leftJoin($this->_db->qn('#__ra_profiles') . ' AS `p` ON p.id = a.user_id');
        $query->leftJoin($this->_db->qn('#__ra_mail_lists') . ' AS `l` ON l.id = a.list_id');

        // Filter by list
        $list_id = $this->getState('filter.list_id');
        if ($list_id != '') {
            $query->where('l.id = ' . $list_id);
        }

        // Filter by method
        $method_id = $this->getState('filter.method_id');
        if ($method_id != '') {
            $query->where('a.method_id = ' . $method_id);
        }

        // Filter by published state
        $published = $this->getState('filter.state');

        if (is_numeric($published)) {
            $query->where('a.state = ' . (int) $published);
        } elseif (empty($published)) {
            $query->where('(a.state IN (0, 1))');
        }
        $search_fields = array(
            'a.date_phase1',
            'l.group_code',
            'l.name',
            'p.preferred_name',
            'm.name',
            'a.input_file',
        );
        // Filter by search
        $search = $this->getState('filter.search');
        if (!empty($search)) {
            $query = ToolsHelper::buildSearchQuery($search, $search_fields, $query);
        }


        // Add the list ordering clause.
        $orderCol = $this->state->get('list.ordering', 'l.group_code');
        $orderDirn = $this->state->get('list.direction', 'ASC');

        if ($orderCol && $orderDirn) {
            $query->order($this->_db->escape($orderCol . ' ' . $orderDirn));
            if ($orderCol == 'l.group_code') {
                $query->order('l.name ASC');
                $query->order('a.date_phase1 DESC');
                //               } elseif ($orderCol == 'a.name') {
                //                   $query->order('a.group_code ASC');
            }
        }
        if (JDEBUG) {
            Factory::getApplication()->enqueueMessage($this->_db->replacePrefix($query), 'message');
        }
        return $query;
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

}
