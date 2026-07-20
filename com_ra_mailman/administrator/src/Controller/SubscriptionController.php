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
 */

namespace Ramblers\Component\Ra_mailman\Administrator\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Toolbar\ToolbarHelper;
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
        //        $this->db = Factory::getDbo();
        $this->objHelper = new ToolsHelper;
        $this->app = Factory::getApplication();
        $wa = $this->app->getDocument()->getWebAssetManager();
        $wa->registerAndUseStyle('ramblers', 'com_ra_tools/ramblers.css');
    }

    public function cancelSubscription() {
        $id = $this->app->input->getInt('id', '1');
        $objSubscription = new SubscriptionHelper;
        if ($objSubscription->cancelSubscription($id)) {
            $this->app->enqueueMessage($objSubscription->message, 'info');
        }
        $this->setRedirect('/administrator/index.php?option=com_ra_mailman&view=subscriptions');
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
