<?php

/**
 * @package    com_ra_mailman
 * @copyright  Copyright (C) East Cheshire Ramblers. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Ramblers\Component\Ra_mailman\Api\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\ListModel;

class ProfilesModel extends ListModel
{
	public function __construct($config = array())
	{
		if (empty($config['filter_fields'])) {
			$config['filter_fields'] = array(
				'id',
				'member_id',
				'user_id',
				'preferred_name',
				'full_name',
				'email',
				'home_group',
				'groupCode',
				'volunteer',
			);
		}

		parent::__construct($config);
	}

	protected function populateState($ordering = null, $direction = null)
	{
		parent::populateState('preferred_name', 'ASC');

		$app = Factory::getApplication();
		$filter = $app->input->get('filter', array(), 'array');
		$search = $app->input->getString('filter_search', '');

		if ($search === '' && isset($filter['search'])) {
			$search = (string) $filter['search'];
		}

		if ($search === '') {
			$search = $app->input->getString('name', '');
		}

		if ($search === '') {
			$search = $app->input->getString('q', '');
		}

		$this->setState('filter.search', trim($search));
		$this->setState('list.limit', min($app->input->getInt('limit', 10), 25));
		$this->setState('list.start', $app->input->getInt('start', 0));
	}

	protected function getListQuery()
	{
		$db = $this->getDbo();
		$query = $db->getQuery(true);

		$query->select(
			array(
				'p.member_id AS id',
				'p.member_id',
				'p.id AS user_id',
				'p.preferred_name',
				'TRIM(CONCAT(COALESCE(p.firstName, ' . $db->quote('') . '), ' . $db->quote(' ') . ', COALESCE(p.lastName, ' . $db->quote('') . '))) AS full_name',
				'COALESCE(NULLIF(u.email, ' . $db->quote('') . '), p.email) AS email',
				'p.home_group',
				'p.groupName',
				'p.groupCode',
				'p.memberStatus',
				'p.volunteer',
			)
		)
			->from($db->quoteName('#__ra_profiles') . ' AS p')
			->leftJoin($db->quoteName('#__users') . ' AS u ON u.id = p.id')
			->where('p.state = 1')
			->where('(u.block = 0 OR u.block IS NULL)')
			->where('COALESCE(NULLIF(u.email, ' . $db->quote('') . '), p.email) <> ' . $db->quote(''));

		$search = $this->getSearchTerm();

		if ($search === '') {
			$query->where('1 = 0');

			return $query;
		}

		$like = $db->quote('%' . $db->escape($search, true) . '%', false);
		$exact = $db->quote($search);

		$query->where(
			'(' .
			'p.preferred_name = ' . $exact .
			' OR u.name = ' . $exact .
			' OR TRIM(CONCAT(COALESCE(p.firstName, ' . $db->quote('') . '), ' . $db->quote(' ') . ', COALESCE(p.lastName, ' . $db->quote('') . '))) = ' . $exact .
			' OR p.preferred_name LIKE ' . $like .
			' OR u.name LIKE ' . $like .
			' OR TRIM(CONCAT(COALESCE(p.firstName, ' . $db->quote('') . '), ' . $db->quote(' ') . ', COALESCE(p.lastName, ' . $db->quote('') . '))) LIKE ' . $like .
			')'
		);

		$query->order($db->escape('p.preferred_name ASC'));

		return $query;
	}

	private function getSearchTerm(): string
	{
		$search = trim((string) $this->getState('filter.search', ''));

		if ($search !== '') {
			return $search;
		}

		$app = Factory::getApplication();
		$filter = $app->input->get('filter', array(), 'array');
		$search = trim($app->input->getString('filter_search', ''));

		if ($search === '' && isset($filter['search'])) {
			$search = trim((string) $filter['search']);
		}

		if ($search === '') {
			$search = trim($app->input->getString('name', ''));
		}

		if ($search === '') {
			$search = trim($app->input->getString('q', ''));
		}

		return $search;
	}
}
