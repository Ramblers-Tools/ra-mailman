<?php
/**
 * Form model for manually creating a mail subscription.
 * 08/11/26 CB Created
 */

namespace Ramblers\Component\Ra_mailman\Administrator\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\FormModel;
use Joomla\Database\DatabaseInterface;

class SubscriptionModel extends FormModel {

    public function getForm($data = [], $loadData = true) {
        $form = $this->loadForm(
                        'com_ra_mailman.subscription',
                        'subscription',
                        [
                            'control' => 'jform',
                            'load_data' => $loadData,
                        ]
                );

        if (!$form) {
            return false;
        }

        $activeList = $this->getActiveList();
        if ($activeList) {
            $form->setFieldAttribute('user_id', 'group_code', $activeList->group_code);
            if ($loadData) {
                $form->setValue('list_id', null, (int) $activeList->id);
            }
        }

        return $form;
    }

    protected function loadFormData() {
        return Factory::getApplication()->getUserState('com_ra_mailman.edit.subscription.data', [
            'user_id' => 0,
            'list_id' => '',
            'record_type' => 1,
        ]);
    }

    public function getFilterSearch(): string {
        return (string) Factory::getApplication()->getUserState(
                        'com_ra_mailman.edit.subscription.filter_search',
                        ''
        );
    }

    public function getActiveList(): ?object {
        $listId = (int) Factory::getApplication()->getUserState(
                'com_ra_mailman.subscriptions.active_list_id',
                0
        );
        if ($listId < 1) {
            return null;
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
                ->select($db->quoteName(['id', 'group_code', 'name']))
                ->from($db->quoteName('#__ra_mail_lists'))
                ->where($db->quoteName('id') . ' = ' . $listId);
        $db->setQuery($query);

        return $db->loadObject() ?: null;
    }
}
