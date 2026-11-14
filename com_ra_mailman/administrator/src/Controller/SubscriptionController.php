<?php

/**
 * @version    4.0.13
 * @package    com_ra_mailman
 * @author     Charlie Bigley <webmaster@bigley.me.uk>
 * @copyright  2023 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 09/06/23 CB added subscribe/unsubscribe
 * 06/01/24 CB cancelSubscription
 * 17/11/24 CB showDetails
 * 04/11/26 CB remove obsolete getDbo reference
 * 11/11/26 CB Implement persistence of a given list, Promote and demote subscription access levels
 */

namespace Ramblers\Component\Ra_mailman\Administrator\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\Database\DatabaseInterface;
use \Ramblers\Component\Ra_mailman\Site\Helpers\Mailhelper;
use Ramblers\Component\Ra_mailman\Site\Helpers\SubscriptionHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsTable;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

//use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

/**
 * Subscription controller class.
 *
 * @since  1.0.4
 */
class SubscriptionController extends FormController {

    protected $app;
    protected $objHelper;
    protected $view_list = 'subscriptions';

    public function __construct($config = array(), \Joomla\CMS\MVC\Factory\MVCFactoryInterface $factory = null) {
        parent::__construct($config, $factory);
        $this->objHelper = new ToolsHelper;
        $this->app = Factory::getApplication();
        $wa = $this->app->getDocument()->getWebAssetManager();
        $wa->registerAndUseStyle('ramblers', 'com_ra_tools/ramblers.css');
    }

    public function add($key = null, $urlVar = null): void {
        if (!ContentHelper::getActions('com_ra_mailman')->get('core.create')) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        // SubscriptionsModel stores this filter under its list-view state key.
        // The add task is dispatched through SubscriptionController, so its
        // request may not contain filter_search and its model state may not
        // be populated from the list request.
        $search = (string) $this->app->getUserState('com_ra_mailman.edit.subscription.filter_search', '');
        if ($search === '') {
            $search = (string) $this->app->getUserState('com_ra_mailman.subscriptions.filter.search', '');
        }
        if ($search === '') {
            $search = (string) $this->input->getString('filter_search', '');
        }
        $this->app->setUserState('com_ra_mailman.edit.subscription.filter_search', $search);

        $this->setRedirect(Route::_('index.php?option=com_ra_mailman&view=subscription&layout=edit', false));
    }

    public function cancel($key = null, $urlVar = null): void {
        $this->app->setUserState('com_ra_mailman.edit.subscription.data', null);
        $this->setRedirect(Route::_('index.php?option=com_ra_mailman&view=subscriptions', false));
    }

    public function cancelSubscription() {
        $id = $this->app->input->getInt('id', '1');
        $objSubscription = new SubscriptionHelper;
        if ($objSubscription->cancelSubscription($id)) {
            $this->app->enqueueMessage($objSubscription->message, 'info');
        }
        $this->setRedirect('/administrator/index.php?option=com_ra_mailman&view=subscriptions');
    }

    private function changeAccess(int $expectedType, int $newType): void {
        $this->checkToken('post');

        if (!ContentHelper::getActions('com_ra_mailman')->get('core.edit')) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $id = $this->input->post->getInt('id', 0);
        $helper = new ToolsHelper;
        $row = $id > 0
                ? $helper->getItem(
                        'SELECT id, list_id, user_id, record_type FROM #__ra_mail_subscriptions WHERE id = ' . $id
                )
                : null;

        if (!$row || (int) $row->record_type !== $expectedType) {
            $this->app->enqueueMessage('The subscription access level has changed or the record was not found.', 'warning');
            $this->setRedirect(Route::_('index.php?option=com_ra_mailman&view=subscriptions', false));

            return;
        }

        $subscription = new SubscriptionHelper;
        // Preserve the exact subscription row selected by the Access button.
        $subscription->id = (int) $row->id;
        $subscription->list_id = (int) $row->list_id;
        $subscription->user_id = (int) $row->user_id;

        if (!$subscription->getData()) {
            $this->app->enqueueMessage($subscription->message, 'error');
            $this->setRedirect(Route::_('index.php?option=com_ra_mailman&view=subscriptions', false));

            return;
        }

        $subscription->record_type = $newType;

        if ($subscription->update()) {
            $label = $newType === 2 ? 'Author' : 'Subscriber';
            $this->app->enqueueMessage('Subscription access changed to ' . $label . '.', 'message');
        } else {
            $this->app->enqueueMessage($subscription->message ?: 'Unable to update subscription access.', 'error');
        }

        $this->setRedirect(Route::_('index.php?option=com_ra_mailman&view=subscriptions', false));
    }

    public function demote(): void {
        $this->changeAccess(2, 1);
    }

    private function isValidRaContactSelection(int $userId, string $expectedGroupCode = ''): bool {
        $selections = $this->input->post->get('ra_contact', [], 'array');
        $secret = (string) Factory::getConfig()->get('secret', '');
        if ($secret === '' || !is_array($selections)) {
            return false;
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $currentUserId = (int) $this->app->getIdentity()->id;

        foreach ($selections as $fieldId => $selection) {
            if (!is_array($selection)) {
                continue;
            }

            $fieldId = (string) $fieldId;
            $groupCode = strtoupper(trim((string) ($selection['group_code'] ?? '')));
            $signature = (string) ($selection['signature'] ?? '');
            if (preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/', $fieldId) !== 1
                    || preg_match('/^[A-Z0-9]{2,4}$/', $groupCode) !== 1
                    || preg_match('/^[a-f0-9]{64}$/i', $signature) !== 1) {
                continue;
            }

            if ($expectedGroupCode !== '' && $groupCode !== strtoupper($expectedGroupCode)) {
                continue;
            }

            $expected = hash_hmac('sha256', $groupCode . '|' . $fieldId . '|' . $currentUserId, $secret);
            if (!hash_equals($expected, $signature)) {
                continue;
            }

            $query = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__users', 'u'))
                ->innerJoin($db->quoteName('#__ra_profiles', 'p') . ' ON ' . $db->quoteName('p.id') . ' = ' . $db->quoteName('u.id'))
                ->innerJoin($db->quoteName('#__ra_groups', 'g') . ' ON ' . $db->quoteName('g.code') . ' = ' . $db->quoteName('p.home_group'))
                ->where($db->quoteName('u.id') . ' = ' . $userId)
                ->where($db->quoteName('g.code') . ' = ' . $db->quote($groupCode));
            $db->setQuery($query);

            if ((int) $db->loadResult() > 0) {
                return true;
            }
        }

        return false;
    }

    public function premote(): void {
        $this->changeAccess(1, 2);
    }

    public function persist(){
        if (!ContentHelper::getActions('com_ra_mailman')->get('core.edit')) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $listId = $this->input->getInt('list_id', 0);
        $list = $listId > 0
                ? $this->objHelper->getItem('SELECT id FROM #__ra_mail_lists WHERE id = ' . $listId)
                : null;

        if (!$list) {
            $this->app->setUserState('com_ra_mailman.subscriptions.active_list_id', 0);
            $this->app->enqueueMessage('The selected mailing list could not be found.', 'warning');
        } else {
            $this->app->setUserState('com_ra_mailman.subscriptions.active_list_id', $listId);
            $this->app->setUserState('com_ra_mailman.subscriptions.filter.list_id', '');
        }

        $this->setRedirect(Route::_('index.php?option=com_ra_mailman&view=subscriptions', false));
    }

    public function clearList(): void {
        $this->checkToken('post');
        $this->app->setUserState('com_ra_mailman.subscriptions.active_list_id', 0);
        $this->setRedirect(Route::_('index.php?option=com_ra_mailman&view=subscriptions', false));
    }

    public function save($key = null, $urlVar = null): void {
        $this->checkToken();

        if (!ContentHelper::getActions('com_ra_mailman')->get('core.create')) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $data = $this->input->post->get('jform', [], 'array');
        $model = $this->getModel('Subscription', 'Administrator');
        $form = $model->getForm($data, false);

        if (!$form) {
            throw new \RuntimeException($model->getError() ?: 'Unable to load the subscription form.');
        }

        $data = $model->validate($form, $data);

        if ($data === false) {
            foreach ($model->getErrors() as $error) {
                $this->app->enqueueMessage($error instanceof \Throwable ? $error->getMessage() : (string) $error, 'warning');
            }
            $this->app->setUserState('com_ra_mailman.edit.subscription.data', $this->input->post->get('jform', [], 'array'));
            $this->setRedirect(Route::_('index.php?option=com_ra_mailman&view=subscription&layout=edit', false));

            return;
        }

        $activeGroupCode = '';
        $activeListId = (int) $this->app->getUserState('com_ra_mailman.subscriptions.active_list_id', 0);
        if ($activeListId > 0) {
            $activeList = $this->objHelper->getItem(
                    'SELECT id, group_code FROM #__ra_mail_lists WHERE id = ' . $activeListId
            );
            if (!$activeList) {
                $this->app->enqueueMessage('The selected mailing list ' . $activeListId . ' could not be found.', 'error');
                $this->setRedirect(Route::_('index.php?option=com_ra_mailman&view=subscription&layout=edit', false));

                return;
            }

            $data['list_id'] = $activeListId;
            $activeGroupCode = (string) $activeList->group_code;
        }

        $userId = (int) ($data['user_id'] ?? 0);
        $listId = (int) ($data['list_id'] ?? 0);
        $recordType = (int) ($data['record_type'] ?? 0);

        if ($userId < 1 || $listId < 1 || !in_array($recordType, [1, 2], true)) {
            $this->app->enqueueMessage('Please select a user, mailing list, and subscription type.', 'error');
            $this->app->setUserState('com_ra_mailman.edit.subscription.data', $data);
            $this->setRedirect(Route::_('index.php?option=com_ra_mailman&view=subscription&layout=edit', false));

            return;
        }

        if (!$this->isValidRaContactSelection($userId, $activeGroupCode)) {
            $this->app->enqueueMessage('Select a user from the configured group.', 'error');
            $this->app->setUserState('com_ra_mailman.edit.subscription.data', $data);
            $this->setRedirect(Route::_('index.php?option=com_ra_mailman&view=subscription&layout=edit', false));

            return;
        }

        $mailHelper = new Mailhelper;
        if ($mailHelper->getSubscription($listId, $userId)) {
            $this->app->enqueueMessage('This user already has a subscription record for that list.', 'warning');
            $this->app->setUserState('com_ra_mailman.edit.subscription.data', $data);
            $this->setRedirect(Route::_('index.php?option=com_ra_mailman&view=subscription&layout=edit', false));

            return;
        }

        $subscription = new SubscriptionHelper;
        $subscription->user_id = $userId;
        $subscription->list_id = $listId;
        $subscription->record_type = $recordType;
        $subscription->method_id = 2;
        $subscription->state = 1;

        try {
            if (!$subscription->add()) {
                throw new \RuntimeException($subscription->message ?: 'Unable to create the subscription.');
            }
        } catch (\Throwable $exception) {
            $this->app->enqueueMessage($exception->getMessage(), 'error');
            $this->app->setUserState('com_ra_mailman.edit.subscription.data', $data);
            $this->setRedirect(Route::_('index.php?option=com_ra_mailman&view=subscription&layout=edit', false));

            return;
        }

        $this->app->setUserState('com_ra_mailman.edit.subscription.data', null);
        $this->app->enqueueMessage('Subscription created.', 'message');
        $this->setRedirect(Route::_('index.php?option=com_ra_mailman&view=subscriptions', false));
    }

    public function sendRenewal() {
        $user_id = $this->app->input->getInt('user_id', '0');
        $list_id = $this->app->input->getInt('list_id', '0');
        $callback = $this->app->input->getWord('callback', '1');
        if ($callback == '1') {
            $target = 'view=subscriptions';
        } else {
            $list_id = $this->app->input->getInt('list_id', '1');
            $year = $this->app->input->getInt('year', '2024');
            $month = $this->app->input->getInt('month', '1');
            $target = 'task=reports.showSubscriptionsDue&list_id=' . $list_id;
            $target .= '&year=' . $year . '&month=' . $month;
        }

        $objMailHelper = new Mailhelper;
        if ($objMailHelper->sendRenewal($user_id, $list_id)) {
            $this->app->enqueueMessage($objMailHelper->message, 'info');
        }
        $this->setRedirect('/administrator/index.php?option=com_ra_mailman&' . $target);
    }

    public function showDetails() {
        // This is invoked from the Subscriptions view
        // OR from task=mail_lst.showSubscribers
        $id = $this->app->input->getInt('id', '0');
        $callback = $this->app->input->getWord('callback', '1');
        if ($callback == '1') {
            $target = 'view=subscriptions';
        } else {
            $callback = $this->app->input->getWord('callback', '1');
            $list_id = $this->app->input->getInt('list_id', '1');
            if ($callback == '2') {
                $target = 'task=mail_lst.showSubscribers&list_id=' . $list_id;
            } else {
                $year = $this->app->input->getInt('year', $current_year);
                $month = $this->app->input->getInt('month', $current_month);
                $target = 'task=reports.showSubscriptionsDue&list_id=' . $list_id;
                $target .= '&year=' . $year . '&month=' . $month;
            }
        }

        ToolBarHelper::title('Details for Subscription ' . $id);

//        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
//        $wa->registerAndUseStyle('ramblers', 'com_ra_tools/ramblers.css');
        $objMailHelper = new Mailhelper;
        $objMailHelper->showSubscriptionDetails($id);
        $back = '/administrator/index.php?option=com_ra_mailman&' . $target;
        echo $this->objHelper->backButton($back);
    }

}
