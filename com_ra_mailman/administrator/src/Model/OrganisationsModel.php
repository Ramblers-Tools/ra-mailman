<?php

/**
 * @version     4.7.0
 * @component   com_ra_mailman
 *
 * @copyright   Copyright (C) 2005 - 2019 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * 18/07/23 CB left join on nations
 * 21/11/23 CB correct spelling of search_fields
 * 22/02/25 CB includ cluster in field list
 * 08/04/26 Claude Refactored from com_ra_tools
 * 21/04/26 GPT Corrected issue where filter fields collapsed after search
 */

namespace Ramblers\Component\Ra_mailman\Administrator\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
//use Joomla\CMS\Form\Form;
//use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Utilities\ArrayHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

/**
 * Item Model for a list of organisations.
 *
 * @since  1.6
 */
class OrganisationsModel extends ListModel {

    protected $search_fields;

    public function __construct($config = []) {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = array(
                'a.code',
                'a.name',
                'a.cluster',
                'n.name',
                'a.email_header',
                'a.logo',
                'a.website',
                'a.co_url',
                'record_type',
                'cluster',
            );

            $this->search_fields = array(
                'a.code',
                'a.name',
                'a.cluster',
                'n.name',
                'a.email_header',
                'a.logo',
                'a.website',
                'a.co_url',
            );
        }
        parent::__construct($config);
    }

    protected function getListQuery() {
        $db = $this->getDbo();
        $query = $db->getQuery(true);

        $query->select('a.*');
        $query->select('n.name as nation');
        $query->select('c.name as cluster_name');

        $query->from($db->quoteName('#__ra_organisations', 'a'));
        $query->LeftJoin('#__ra_nations AS n ON n.id = a.nation_id');
        $query->LeftJoin('#__ra_clusters AS c ON c.code = a.cluster');
        // Filter by search
        $search = $this->getState('filter.search');
        $recordType = $this->getState('filter.record_type');
        $cluster = $this->getState('filter.cluster');

        if (!empty($recordType)) {
            $query->where('a.record_type = ' . $db->quote($recordType));
        }

        if (!empty($cluster)) {
            $query->where('a.cluster = ' . $db->quote($cluster));
        }

        if (!empty($search)) {
            if (stripos($search, 'id:') === 0) {
                $query->where('a.id = ' . (int) substr($search, 3));
            } else {
                $query = ToolsHelper::buildSearchQuery($search, $this->search_fields, $query);
            }
        }

        // Add the list ordering clause, defaut to name ASC
        $orderCol = $this->state->get('list.ordering', 'a.name');
        $orderDirn = $this->state->get('list.direction', 'asc');

        if ($orderCol == 'n.name') {
            $orderCol = $db->quoteName('n.name') . ' ' . $orderDirn . ', ' . $db->quoteName('a.name');
        }

        $query->order($db->escape($orderCol . ' ' . $orderDirn));
        if (JDEBUG) {
            Factory::getApplication()->enqueueMessage('sql = ' . (string) $query, 'notice');
        }
        return $query;
    }

    protected function populateState($ordering = 'a.name', $direction = 'asc') {
        // List state information.
        parent::populateState($ordering, $direction);

        $recordType = $this->getUserStateFromRequest($this->context . '.filter.record_type', 'filter_record_type');
        $this->setState('filter.record_type', $recordType);

        $cluster = $this->getUserStateFromRequest($this->context . '.filter.cluster', 'filter_cluster');
        $this->setState('filter.cluster', $cluster);
    }

    protected function getStoreId($id = '') {
        $id .= ':' . $this->getState('filter.search');
        $id .= ':' . $this->getState('filter.record_type');
        $id .= ':' . $this->getState('filter.cluster');

        return parent::getStoreId($id);
    }

}
