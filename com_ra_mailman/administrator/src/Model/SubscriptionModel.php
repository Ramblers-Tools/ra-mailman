<?php
/**
 * Form model for manually creating a mail subscription.
 * 08/11/26 CB Created
 */

namespace Ramblers\Component\Ra_mailman\Administrator\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\FormModel;

class SubscriptionModel extends FormModel {

    public function getForm($data = [], $loadData = true) {
        return $this->loadForm(
                        'com_ra_mailman.subscription',
                        'subscription',
                        [
                            'control' => 'jform',
                            'load_data' => $loadData,
                        ]
                ) ?: false;
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
}
