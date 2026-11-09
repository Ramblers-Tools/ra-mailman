<?php

/**
 * @version    4.2.2
 * @package    com_ra_mailman
 * @author     Charlie Bigley <webmaster@bigley.me.uk>
 * @copyright  2023 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 09/03/25 CB move code for Subscribe from Subscriptions Controller
 */

namespace Ramblers\Component\Ra_mailman\Administrator\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Multilanguage;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\AdminController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\Utilities\ArrayHelper;
use Ramblers\Component\Ra_mailman\Site\Helpers\Mailhelper;
use Ramblers\Component\Ra_mailman\Site\Helpers\SubscriptionHelper;
//use Ramblers\Component\Ra_tools\Site\Helpers\ToolsTable;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

/**
 * User_select list controller class.
 *
 * @since  1.0.3
 */
class User_selectController extends AdminController {

    protected $view_item = 'profile';
    protected $view_list = 'mail_lsts';

    function __construct($config = array(), \Joomla\CMS\MVC\Factory\MVCFactoryInterface $factory = null) {
        parent::__construct($config, $factory);
        $this->toolsHelper = new ToolsHelper;
        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
        $wa->registerAndUseStyle('ramblers', 'com_ra_tools/ramblers.css');
    }

    public function cancel($key = null, $urlVar = null) {
        $this->setRedirect('index.php?option=com_ra_mailman&view=mail_lsts');
    }

    /**
     * Method to clone existing User_select
     *
     * @return  void
     *
     * @throws  Exception
     */
    public function duplicate() {
        // Check for request forgeries
        $this->checkToken();

        // Get id(s)
        $pks = $this->input->post->get('cid', array(), 'array');

        try {
            if (empty($pks)) {
                throw new \Exception(Text::_('COM_RA_MAILMAN_NO_ELEMENT_SELECTED'));
            }

            ArrayHelper::toInteger($pks);
            $model = $this->getModel();
            $model->duplicate($pks);
            $this->setMessage(Text::_('COM_RA_MAILMAN_ITEMS_SUCCESS_DUPLICATED'));
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage($e->getMessage(), 'warning');
        }

        $this->setRedirect('index.php?option=com_ra_mailman&view=user_select');
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
     * @since   1.0.3
     */
    public function getModel($name = 'Userselect', $prefix = 'Administrator', $config = array()) {
        return parent::getModel($name, $prefix, array('ignore_request' => true));
    }

    private function lookupUser($user_id) {
        $sql = 'SELECT preferred_name FROM #__ra_profiles WHERE id=' . $user_id;
        return $this->toolsHelper->getValue($sql);
    }

    public function subscribe() {

        // Get the input
        $input = Factory::getApplication()->input;
        $primary_keys = $input->post->get('cid', array(), 'array');

        // Sanitize the input
        ArrayHelper::toInteger($primary_keys);
        // Retrieve the list id saved by the View
        $list_id = Factory::getApplication()->getUserState('com_ra_mailman.user_select.user_id', 0);
        // Retrieve the type of access saved by the View
        $access = Factory::getApplication()->getUserState('com_ra_mailman.user_select.record_type', 0);
        echo 'List id= ' . $list_id . '<br>';
        $mailHelper = new Mailhelper;
        foreach ($primary_keys as $user_id) {
            echo 'subscribing ' . $user_id . '<br>';

            $sql = 'SELECT s.id, s.state, p.preferred_name FROM #__ra_profiles AS p ';
            $sql .= 'LEFT JOIN  #__ra_mail_subscriptions AS s ON s.user_id = p.id ';
            $sql .= 'WHERE s.user_id=' . $user_id;
            $sql .= ' AND s.list_id=' . $list_id;
            echo $sql . '<br>';
            $item = $this->toolsHelper->getItem($sql);
            var_dump($item);
            echo '<br>';
            if (is_null($item)) {
                $new = $this->lookupUser($user_id);
                $mailHelper->subscribe($list_id, $user_id, $access, 2);
                $message = $new . ' has been subscribed';
                $this->app->enqueueMessage($message, 'info');
            } elseif ($item->state == 0) {
//               $mailHelper->subscribe($list_id, $user_id, $access, 2);
                $message = $item->preferred_name . ' has been subscribed';
                $this->app->enqueueMessage($message, 'info');
            } elseif ($item->state == 1) {
                $message = $item->preferred_name . ' is already subscribed';
                $this->app->enqueueMessage($message, 'error');
//            } elseif ($item->state == -2) {
//                  $mailHelper->confirmBooking($item->id, $event_id);
//                $message = $item->preferred_name . ' has been reinstated';
//                $this->app->enqueueMessage($message, 'info');
            }
        }
//        die;


        $this->setRedirect('index.php?option=com_ra_mailman&view=user_select&list_id=' . $list_id);
    }

}
