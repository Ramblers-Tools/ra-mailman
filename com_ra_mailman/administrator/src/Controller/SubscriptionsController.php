<?php

/**
 * @version    4.2.2
 * @package    com_ra_mailman
 * @author     Charlie Bigley <webmaster@bigley.me.uk>
 * @copyright  2023 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 08/01/24 CB use standard css
 * 30/07/24 CB show email in showAudit
 * 18/11/24 CB deleted showAudit - replaced by subscription.showDetails
 * 09/03/25 CB move code for Subscribe to user_select Controller
 */

namespace Ramblers\Component\Ra_mailman\Administrator\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\Language\Multilanguage;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\AdminController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\Utilities\ArrayHelper;
use Ramblers\Component\Ra_mailman\Site\Helpers\Mailhelper;
use Ramblers\Component\Ra_mailman\Site\Helpers\SubscriptionHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsTable;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

/**
 * Subscriptions list controller class.
 *
 * @since  1.0.4
 */
class SubscriptionsController extends AdminController {

    function __construct($config = array(), \Joomla\CMS\MVC\Factory\MVCFactoryInterface $factory = null) {
        parent::__construct($config, $factory);
        $this->toolsHelper = new ToolsHelper;
        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
        $wa->registerAndUseStyle('ramblers', 'com_ra_tools/ramblers.css');
    }

    public function cancel($key = null, $urlVar = null) {
        $this->setRedirect('index.php?option=com_ra_tools&view=dashboard');
    }

    public function cancel2($key = null, $urlVar = null) {
        // temp test from button on list_select
        die('cancel2');
        $this->setRedirect('index.php?option=com_ra_tools&view=dashboard');
    }

    /**
     * Method to publish existing Subscriptions
     *
     * @return  void
     *
     * @throws  Exception
     */
    public function publish() {
        $this->checkToken();
        // AdminController aliases unpublish to publish, so inspect the
        // submitted task rather than the dispatched method name.
        $task = $this->input->getCmd('task', '');
        $state = preg_match('/(?:^|\.)unpublish$/', $task) === 1 ? 0 : 1;
        $this->changeSubscriptionState($state);
    }

    /**
     * Unpublish all selected subscriptions.
     */
    public function unpublish() {
        $this->checkToken();
        $this->changeSubscriptionState(0);
    }

    /**
     * Set the state of every selected subscription and record the change
     * through MailHelper so the subscription audit trail is maintained.
     */
    private function changeSubscriptionState(int $state): void {
        $app = Factory::getApplication();
        $canDo = ContentHelper::getActions('com_ra_mailman');

        if (!$canDo->get('core.edit.state')) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $ids = array_values(array_unique(array_filter(
                        array_map('intval', (array) $this->input->post->get('cid', [], 'array')),
                        static function (int $id): bool {
                            return $id > 0;
                        }
        )));

        if (!$ids) {
            $app->enqueueMessage(Text::_('JLIB_HTML_PLEASE_MAKE_A_SELECTION_FROM_THE_LIST'), 'warning');
            $this->setRedirect('index.php?option=com_ra_mailman&view=subscriptions');
            return;
        }

        $mailHelper = new MailHelper;
        $changed = 0;
        $unchanged = 0;

        foreach ($ids as $id) {
            $subscription = $this->toolsHelper->getItem(
                            'SELECT id, list_id, user_id, record_type, state '
                            . 'FROM #__ra_mail_subscriptions WHERE id = ' . $id
            );

            if (!$subscription) {
                $unchanged++;
                continue;
            }

            if ((int) $subscription->state === $state) {
                $unchanged++;
                continue;
            }

            if ($mailHelper->updateSubscription(
                            (int) $subscription->list_id,
                            (int) $subscription->user_id,
                            (int) $subscription->record_type,
                            2,
                            $state
                    )) {
                $changed++;
            } else {
                $unchanged++;
            }
        }

        $stateLabel = $state === 1 ? 'published' : 'unpublished';
        $app->enqueueMessage(
                sprintf('%d subscription(s) %s; %d unchanged or not found.', $changed, $stateLabel, $unchanged),
                $changed > 0 ? 'message' : 'warning'
        );

        $this->setRedirect('index.php?option=com_ra_mailman&view=subscriptions');
    }

    /**
     * Proxy for getModel.
     *
     * @param   string  $name    Optional. Model name
     * @param   string  $prefix  Optional. Class prefix
     * @param   array   $config  Optional. Configuration array for model
     *
     * @return  object	The Model
     *
     * @since   1.0.4
     */
    public function getModel($name = 'Subscriptions', $prefix = 'Administrator', $config = array()) {
        return parent::getModel($name, $prefix, array('ignore_request' => true));
    }

    public function forceRenewal() {
        // Invoked from list of subscriptions, reset renewal date to force user to renew
        $list_id = Factory::getApplication()->input->getInt('list_id', '0');
        $user_id = Factory::getApplication()->input->getInt('user_id', '0');
        $objSubscription = new SubscriptionHelper;
        $objSubscription->forceRenewal($list_id, $user_id);
        $this->setRedirect('/administrator/index.php?option=com_ra_mailman&view=subscriptions');
    }

}
