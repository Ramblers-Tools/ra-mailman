<?php

/**
 * @version    4.5.3
 * @package    com_ra_mailman
 * @author     Charlie Bigley <webmaster@bigley.me.uk>
 * @copyright  2023 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 13/11/23 CB take name of website from uri
 * 14/11/23 CB correct redirection back to edit screen when saving but errors
 * 14/11/23 CB change welcome message if self registering
 * 04/11/24 CB show preferred name, flush form data if cancelling
 * 12/02/25 CB use table from Administrator, not Site
 *             replace getIdentity with getSession()->get('user')
 * 24/08/25 CB change welcome message
 * 12/09/26 CB deleted redundant edit and save functions
 */

namespace Ramblers\Component\Ra_mailman\Site\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Object\CMSObject;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
//use Ramblers\Component\Ra_mailmans\Site\Model\ProfileModel;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsTable;
use Ramblers\Component\Ra_mailman\Site\Helpers\Mailhelper;
use Ramblers\Component\Ra_mailman\Site\Helpers\UserHelper;

/**
 * Profile class.
 *
 * @since  4.1.0
 */
class ProfileController extends FormController {

    private $toolsHelper;

    public function __construct($config = array(), \Joomla\CMS\MVC\Factory\MVCFactoryInterface $factory = null) {
        parent::__construct($config, $factory);
        $this->toolsHelper = new ToolsHelper;
        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
        $wa->registerAndUseStyle('ramblers', 'com_ra_tools/ramblers.css');
    }

    /**
     * Method to abort creation of profile
     */
    public function cancel($key = NULL) {
        // Flush the data from the session.
        $this->app->setUserState('com_ra_mailman.edit.profile.data', null);
        $this->setRedirect(Route::_('index.php?option=com_ra_mailman&view=mail_lsts', false));
    }

    public function showSubscriptionDetails() {
        $id = Factory::getApplication()->input->getInt('id', '0');
        $menu_id = Factory::getApplication()->input->getInt('menu_id', '0');
        echo '<h2>Details for Subscription</h2>';
        $objMailHelper = new Mailhelper;
        $objMailHelper->showSubscriptionDetails($id);
//        $back = '/index.php?option=com_ra_mailman&view=profile&layout=subscriptions&Itemid=' . $menu_id;
        $back = 'index.php?option=com_ra_mailman&view=profile&layout=subscriptions&Itemid=' . $menu_id;
        echo $this->toolsHelper->backButton($back);
    }

    public function showWelcome() {
// Shows a welcome message after a User has self-registered
// (Would be better if displayed as a View)
        $user_id = Factory::getApplication()->input->getInt('user_id', '0');
        $params = ComponentHelper::getParams('com_ra_mailman');
        $welcome_message = $params->get('welcome_message');
        $sql = 'SELECT u.email, u.username, u.registerDate, ';
        $sql .= 'p.preferred_name, p.home_group ';
        $sql .= 'FROM #__users AS u ';
        $sql .= 'LEFT JOIN #__ra_profiles as p ON p.id = u.id ';
        $sql .= 'WHERE u.id=' . $user_id;
        $item = $this->toolsHelper->getItem($sql);

        echo '<h2>Welcome to MailMan ' . $item->preferred_name . '</h2>';
        echo '<p>' . $params->get('welcome_message') . '</p>';

        echo '<p>Please authenticate yourself by requesting a Password reset ';
        echo $this->toolsHelper->standardButton('Go', 'index.php?option=com_users&view=reset&Itemid=');
        echo '<p>';

        $sql = 'SELECT l.group_code, l.name, g.name AS group_name ';
        $sql .= 'FROM `#__ra_mail_lists` AS l ';
        $sql .= 'LEFT JOIN #__ra_groups as g ON g.code = l.group_code ';
        $sql .= 'WHERE group_primary="' . $item->home_group . '" ';
        $list = $this->toolsHelper->getItem($sql);

        if ($list->name > '') {
            echo 'Your local Ramblers group is ' . $list->group_code . ' <b>' . $list->group_name . '</b>. ';
            echo 'There may be other newsletters that interest you, you can manage  ';
            echo 'these after you have changed your password and successfully logged on</p>';
        }
        // Could show button fopr password reset
        // $target = 'index.php?option=com_users&view=reset';
        echo $this->toolsHelper->backButton('index.php');
    }

    public function submit($key = NULL, $urlVar = NULL) {
        die('controller/submit');
    }

    public function test() {
        if (!$this->toolsHelper->isSuperuser()) {
            echo 'Logon first<br>';
            return;
        }
        $userHelper = new UserHelper;
        echo __FILE__ . '<br>';
        $userHelper->test();
    }

}
