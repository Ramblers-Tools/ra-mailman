<?php

/**
 * 22/06/23 added typealias
 * 14/11/23 CB remove reference to author_id
 * 21/11/23 CB only show mailshots where date_sent is not NULL
 * 23/12/23 CB default sort order to date_sent DESC
 * 14/10/24 CB return name of attachments in list query
 * 14/08/24 CB get all fields
 * 14/11/25 CB change __ra_mailshots to __ra_mail_shots
 * 16/03/26 CB filter by group if not full_version
 * 04/11/26 CB use container DatabaseInterface for J6 compatibility
 */

namespace Ramblers\Component\Ra_mailman\Site\Model;

// No direct access.
defined('_JEXEC') or die;

use \Joomla\CMS\Factory;
use \Joomla\CMS\Language\Text;
use \Joomla\CMS\MVC\Model\ListModel;
use \Joomla\Component\Fields\Administrator\Helper\FieldsHelper;
use Joomla\Database\DatabaseInterface;
use \Ramblers\Component\Ra_mailman\Site\Helpers\Mailhelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

/**
 * Methods supporting a list of Ra_mailman records.
 *
 * @since  1.0.2
 */
class MailshotsModel extends ListModel {

    public $typeAlias = 'com_ra_mailman.mailshots';

    /**
     * Constructor.
     *
     * @param   array  $config  An optional associative array of configuration settings.
     *
     * @see    JController
     * @since  1.0.2
     */
    public function __construct($config = array()) {
        if (empty($config['filter_fields'])) {
            // This determined which fields are used for sorting
            $config['filter_fields'] = array(
                'a.date_sent',
                'a.title',
                'mail_list', 'mail_list.name',
                'modified', 'a.modified',
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
     * @return  void
     *
     * @throws  Exception
     *
     * @since   1.0.2
     */
    protected function populateState($ordering = null, $direction = null) {
        // List state information.
        parent::populateState('a.date_sent', 'DESC');

        $app = Factory::getApplication();
        $list = $app->getUserState($this->context . '.list');

        $value = $app->getUserState($this->context . '.list.limit', $app->get('list_limit', 25));
        $list['limit'] = $value;

        $this->setState('list.limit', $value);

        $value = $app->input->get('limitstart', 0, 'uint');
        $this->setState('list.start', $value);

        $ordering = $this->getUserStateFromRequest($this->context . '.filter_order', 'filter_order', 'a.description');
        $direction = strtoupper($this->getUserStateFromRequest($this->context . '.filter_order_Dir', 'filter_order_Dir', 'ASC'));

        if (!empty($ordering) || !empty($direction)) {
            $list['fullordering'] = $ordering . ' ' . $direction;
        }

        $app->setUserState($this->context . '.list', $list);

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
     * @since   1.0.2
     */
    protected function getListQuery() {
        // list_id will have been passed as a parameter to identify the mailing list being queried
        $this->list_id = Factory::getApplication()->input->getInt('list_id', 0);
        // See if we are running the full version
        $toolsHelper = new ToolsHelper;
        $mailHelper = new MailHelper;
        $group = $mailHelper->getDefaultGroup();

        // Create a new query object.
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true);

        $query->select('a.*');
        $query->from('`#__ra_mail_shots` AS a');
        $query->select('mail_list.name AS `list_name`');
        $query->leftJoin($this->_db->qn('#__ra_mail_lists') . ' AS `mail_list` ON mail_list.id = a.mail_list_id');

        $query->where('a.date_sent IS NOT NULL');
        if ($this->list_id > '0') {
            $query->where($this->_db->qn('a.mail_list_id') . ' = ' . $this->_db->q($this->list_id));
        }

        if (($group !== 'N') AND ($toolsHelper->isSuperuser() === false)) {
            $query->where('mail_list.group_code=' . $db->quote($group));
        }

        // Search for this word
        $searchWord = $this->getState('filter.search');

        // Search in these columns
        $searchColumns = array(
            'a.title',
            'mail_list.name',
            'a.body',
        );

        if (!empty($searchWord)) {
            // Build the search query from the search word and search columns
            $query = ToolsHelper::buildSearchQuery($searchWord, $searchColumns, $query);
        }

        // Add the list ordering clause.
        $orderCol = $this->state->get('list.ordering', 'a.description');
        if ($orderCol == 'a.date_sent') {
            $orderDirn = 'DESC';
        } else {
            $orderDirn = $this->state->get('list.direction', 'ASC');
        }

        if ($orderCol && $orderDirn) {
            $query->order($db->escape($orderCol . ' ' . $orderDirn));
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

        foreach ($items as $item) {

            if (isset($item->event_type_id)) {

                $values = explode(',', $item->event_type_id);
                $textValue = array();

                foreach ($values as $value) {
                    $db = Factory::getContainer()->get(DatabaseInterface::class);
                    $query = $db->getQuery(true);
                    $query
                            ->select('`description`')
                            ->from($db->quoteName('#__ra_mail_shots'))
                            ->where($db->quoteName('id') . ' = ' . $db->quote($db->escape($value)));

                    $db->setQuery($query);
                    $results = $db->loadObject();

                    if ($results) {
                        $textValue[] = $results->description;
                    }
                }

                $item->event_type_id = !empty($textValue) ? implode(', ', $textValue) : $item->event_type_id;
            }
        }

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
