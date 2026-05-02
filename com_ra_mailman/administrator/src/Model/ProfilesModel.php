<?php

/**
 * @version    4.5.7
 * @package    com_ra_mailman
 * @author     Charlie Bigley <webmaster@bigley.me.uk>
 * @copyright  2023 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 27/08/23 CB Don't show blocked users
 * 28/10/24 CB select requireReset
 * 30/07/25 search for ID: using helper
 * 06/08/25 CB replace u. with a. (for compatibility with ToolsHelper:search
 * 19/10/25 CB search in email field
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
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

/**
 * Methods supporting a list of Profiles records.
 *
 * @since  4.0.0
 */
class ProfilesModel extends ListModel {

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
                'a.id',
                'a.email',
                'a.registerDate',
                'a.lastvisitDate',
                'a.block',
                'p.home_group',
                'p.preferred_name',
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
        parent::populateState('preferred_name', 'ASC');

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
     * @since   4.0.0
     */
    protected function getStoreId($id = '') {
        // Compile the store id.
        $id .= ':' . $this->getState('filter.search');
        $id .= ':' . $this->getState('filter.state');

        return parent::getStoreId($id);
    }

    /**
     * Build an SQL query to load the list data.
     *
     * @return  DatabaseQuery
     *
     * @since   4.0.0
     */
    protected function getListQuery() {
        // Create a new query object.
        $db = $this->getDbo();
        $query = $this->_db->getQuery(true);

        $query->select('p.id, p.state, p.home_group, p.preferred_name');
        //       $query->select('p.group_code');
        $query->select('a.id as user_id, a.name, a.email');
        $query->select(' a.block, a.requireReset, a.registerDate, a.lastvisitDate');
        $query->from('`#__users` AS a');

        $query->leftJoin($this->_db->qn('#__ra_profiles') . ' AS `p` ON p.id = a.id');
        $filter_state = $this->getState('filter.state');
        if ($filter_state == 1) {
            $query->where('p.state= 0');
        } elseif ($filter_state == 2) {
            $query->where('a.block= 1');
        }
//      Don't show blocked users
//
        // Search for this word
        $search = $this->getState('filter.search');

        // Search in these columns
        if (!empty($search)) {
            $search_fields = array(
                'a.id',
                'a.name',
                'p.preferred_name',
                'a.email',
                'p.home_group',
            );
            if (stripos($search, 'id:') === 0) {
                $query->where('a.id = ' . (int) substr($search, 3));
            } else {
                $query = ToolsHelper::buildSearchQuery($search, $search_fields, $query);
            }
        }

        // Add the list ordering clause
        $orderCol = $this->state->get('list.ordering');
        $orderDirn = $this->state->get('list.direction');
        if ($orderCol && $orderDirn) {
            $query->order($this->_db->escape($orderCol . ' ' . $orderDirn));
        }
        if (JDEBUG) {
            Factory::getApplication()->enqueueMessage('sql = ' . (string) $query, 'notice');
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

        foreach ($items as $oneItem) {
            $oneItem->privacy_level = !empty($oneItem->privacy_level) ? Text::_('COM_RA_PROFILE_PROFILES_PRIVACY_LEVEL_OPTION_' . strtoupper(str_replace(' ', '_', $oneItem->privacy_level))) : '';
        }

        return $items;
    }

}
