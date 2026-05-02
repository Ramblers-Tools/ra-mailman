<?php

/**
 * @version    4.6.3
 * @package    com_ra_mailman
 * @author     Charlie Bigley <webmaster@bigley.me.uk>
 * @copyright  2023 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 11/11/2025 CB created
 * 15/11/25 CB correct filter by mail list
 * 16/03/26 CB filter by group if not full_version
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
 * Methods supporting a list of Recipient records.
 *
 * @since  1.0.2
 */
class RecipientsModel extends ListModel {

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
            // This determined which fields are used for sorting
            $config['filter_fields'] = array(
                'm.date_sent',
                'm.title',
                'mail_list', 'mail_list.name',
                'p.preferred_name',
                'a.email',
                'a.id',
                'list_id',
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
        parent::populateState('date_sent', 'DESC');

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
     * @since   1.0.2
     */
    protected function getStoreId($id = '') {
        // Compile the store id.
        $id .= ':' . $this->getState('filter.search');
        $id .= ':' . $this->getState('filter.list_id');

        return parent::getStoreId($id);
    }

    public function getTable($type = 'recipients', $prefix = 'Ra_mailmanTable', $config = array()) {
        return Table::getInstance($type, $prefix, $config);
    }

    /**
     * Build an SQL query to load the list data.
     *
     * @return  DatabaseQuery
     *
     * @since   1.0.2
     */
    protected function getListQuery() {
        // See if we are running the full version
        $toolsHelper = new ToolsHelper;
        $mailHelper = new MailHelper;
        $group = $mailHelper->getDefaultGroup();

        // list_id may have been passed as a parameter to identify the mailing list being queried
        $this->list_id = Factory::getApplication()->input->getInt('list_id', 0);

        $query = $this->_db->getQuery(true);

        $query->select('a.id, a.email, a.mailshot_id, m.date_sent, m.title, p.preferred_name');
        $query->select('mail_list.name AS `list_name`');

        $query->from('`#__ra_mail_recipients` AS a');
        $query->innerJoin($this->_db->qn('#__ra_profiles') . ' AS `p` ON p.id = a.user_id');
        $query->innerJoin($this->_db->qn('#__ra_mail_shots') . ' AS `m` ON m.id = a.mailshot_id');
        $query->leftJoin($this->_db->qn('#__ra_mail_lists') . ' AS `mail_list` ON mail_list.id = m.mail_list_id');

        // Filter by list
        $app = Factory::getApplication();
        $list_id = $app->input->getInt('list_id', '0');
        if ($this->list_id == '0') {
            $this->list_id = $this->getState('filter.list_id');
        }
        if ($this->list_id == '') {
            $this->list_id = '0';
        }
        if ($this->list_id !== '0') {
            $query->where('m.mail_list_id = ' . $this->list_id);
        }
        if (($group !== 'N') AND ($toolsHelper->isSuperuser() === false)) {
            $query->where('mail_list.group_code=' . $this->_db->quote($group));
        }
        // Search for this word
        $searchWord = $this->getState('filter.search');

        // Search in these columns
        $searchColumns = array(
            'm.title',
            'a.email',
            'mail_list.name',
            'p.preferred_name',
        );

        if (!empty($searchWord)) {
            // Build the search query from the search word and search columns
            $query = ToolsHelper::buildSearchQuery($searchWord, $searchColumns, $query);
        }

        // Add the list ordering clause
        $orderCol = $this->state->get('list.ordering');
        $orderDirn = $this->state->get('list.direction');
        /*
          if ($orderCol && $orderDirn) {
          $query->order($this->_db->escape($orderCol . ' ' . $orderDirn));
          if ($orderCol == 'a.group_code') {
          $query->order('a.name ASC');
          } elseif ($orderCol == 'a.name') {
          $query->order('a.group_code ASC');
          }
          }

         */
        if ($orderCol && $orderDirn) {
            $query->order($this->_db->escape($orderCol . ' ' . $orderDirn));
        } else {
            $query->order($this->_db->escape('m.date_sent DESC'));
        }
        if (JDEBUG) {
            Factory::getApplication()->enqueueMessage('sql=' . (string) $query, 'message');
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
