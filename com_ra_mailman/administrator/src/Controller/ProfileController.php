<?php

/**
 * @version    4.1.14
 * @package    com_ra_mailman
 * @author     Charlie Bigley <webmaster@bigley.me.uk>
 * @copyright  2023 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 19/02/24 CB correction for creating Profile records - don't pass parameters
 * 25/03/24 CB don't delete from profiles_audit
 * 14/01/24 CB delete records from subscriptions_audit
 * 23/06/26 CB $this->toolsHelper
 */

namespace Ramblers\Component\Ra_mailman\Administrator\Controller;

\defined('_JEXEC') or die;

use \Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Ramblers\Component\Ra_mailman\Site\Helpers\Mailhelper;
use Ramblers\Component\Ra_mailman\Site\Helpers\UserHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

/**
 * Profile controller class.
 *
 * @since  4.0.0
 */
class ProfileController extends FormController {

    protected $view_list = 'profiles';
    private $toolsHelper;

    public function __construct() {
        parent::__construct();
        $this->toolsHelper = new ToolsHelper;
        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
        $wa->registerAndUseStyle('ramblers', 'com_ra_tools/ramblers.css');
    }

    public function cancel($key = null, $urlVar = null) {
        $this->setRedirect(Route::_('/administrator/index.php?option=com_ra_tools&view=dashboard', false));
    }

    public function create() {
        // Invoked when a User record exists but not a Profile record
        // Creates a record, allows it to be edited
        $id = Factory::getApplication()->input->getInt('id', '');
        echo 'Controller / id=' . $id . '<br>';
        $sql = 'SELECT name, email FROM `#__users` ';
        $sql .= 'WHERE id=' . $id;

        $item = $this->toolsHelper->getItem($sql);
        if ($item) {

            $objUserHelper = new UserHelper;
            $objUserHelper->preferred_name = $item->name;
            $objUserHelper->name = $item->name;
            $objUserHelper->email = $item->email;
            $objUserHelper->user_id = $id;
            $objUserHelper->createProfile();
            $this->setRedirect(Route::_('/administrator/index.php?option=com_ra_mailman&view=profile&layout=edit&id=' . $id, false));
        } else {
            throw new Exception('Can\'t find User record', 404);
        }
    }

    public function purgeProfile() {
        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
        $wa->registerAndUseStyle('ramblers', 'com_ra_tools/ramblers.css');
        $id = Factory::getApplication()->input->getInt('id', '0');
        echo 'Purging profile for ' . $id . '<br>';

        if ($id > 0) {
            $sql = 'DELETE FROM #__ra_profiles WHERE id=' . $id;
            $this->toolsHelper->executeCommand($sql);
            // delete details of any emails sent
            $sql = 'DELETE FROM #__ra_mail_recipients WHERE user_id=' . $id;
            echo $sql . '<br>';
            $this->toolsHelper->executeCommand($sql);

            // Delete any subscriptions
            $sql = 'SELECT id FROM #__ra_mail_subscriptions WHERE user_id>' . $id;
            $rows = $this->toolsHelper->getRows($sql);
            foreach ($rows as $row) {
                $sql = 'DELETE FROM  #__ra_mail_subscriptions_audit ';
                $sql .= 'WHERE object_id=' . $row->id;
                echo $sql . '<br>';
                $this->toolsHelper->executeCommand($sql);
                $sql = 'DELETE FROM #__ra_mail_subscriptions WHERE id=' . $id;
                echo $sql . '<br>';
                $this->toolsHelper->executeCommand($sql);
            }
//        echo $sql . '<br>';
//        $this->toolsHelper->executeCommand($sql);
            // delete profile audit records
//                $sql = 'DELETE FROM #__ra_profiles_audit WHERE object_id=' . $id;
//                echo $sql . '<br>';
//                $this->toolsHelper->executeCommand($sql);
//        }
        }

        $back = 'administrator/index.php?option=com_ra_mailman&task=reports.duffProfiles';
        echo $this->toolsHelper->backButton($back);
    }

    /*
     * Function handing the save for adding a new User and Profile record
     */

    public function save($key = null, $urlVar = null) {
        // Check for request forgeries.
        $this->checkToken();

        // find which layout is in use
        $app = Factory::getApplication();
        $layout = $app->input->getCmd('layout', 'new');

        // Initialise variables.
        $model = $this->getModel('Profile', 'Administrator');

        // Get the user data.
        $data = $this->input->get('jform', array(), 'array');
//        var_dump($data);
        if ($data['id'] == 0) {
            $message = 'Profile created';
        } else {
            $message = 'Profile updated';
        }
        // Validate the posted data.
        $form = $model->getForm();

        if (!$form) {
            throw new \Exception($model->getError(), 500);
        }
        $id = (int) $this->app->getUserState('com_ra_mailman.edit.profile.id');
        // Check for errors.
        if ($data === false) {
            // Get the validation messages.
            $errors = $model->getErrors();

            // Push up to three validation messages out to the user.
            for ($i = 0, $n = count($errors); $i < $n && $i < 3; $i++) {
                if ($errors[$i] instanceof \Exception) {
                    $this->app->enqueueMessage($errors[$i]->getMessage(), 'warning');
                } else {
                    $this->app->enqueueMessage($errors[$i], 'warning');
                }
            }

            $jform = $this->input->get('jform', array(), 'ARRAY');

            // Save the data in the session.
            $this->app->setUserState('com_ra_mailman.edit.profile.data', $jform);

            // Redirect back to the edit screen.
            $this->setRedirect(Route::_('administrator/index.php?option=com_ra_mailman&view=profile&layout=' . $layout . '&id=' . $id, false));
            $this->redirect();
        }

        // Attempt to save the data.
        $return = $model->save($data);
        // Check for errors.
        if ($return === false) {
            // Save the data in the session.
            $this->app->setUserState('com_ra_mailman.edit.profile.data', $data);
            // Redirect back to the edit screen.
            $this->setMessage('Save failed, layout=' . $layout . ',id= ' . $id, $model->getError(), 'warning');
            $this->setRedirect(Route::_('index.php?option=com_ra_mailman&view=profile&layout=' . $layout . '&id=' . $id, false));
            $this->redirect();
        }
        $this->setMessage(Text::sprintf('Saved OK', $model->getError()), 'info');
        // Check in the profile.
        if ($return) {
            $model->checkin($return);
        }

        // Clear the profile id from the session.
        $this->app->setUserState('com_ra_mailman.edit.profile.id', null);

        // Redirect to the list screen.
        if (!empty($return)) {
            $this->setMessage($message);
        }

        $menu = Factory::getApplication()->getMenu();
        $item = $menu->getActive();
        $url = (empty($item->link) ? 'index.php?option=com_ra_mailman&view=profiles' : $item->link);
        $this->setRedirect(Route::_($url, false));

        // Flush the data from the session.
        $this->app->setUserState('com_ra_mailman.edit.profile.data', null);
    }

    public function test() {
        echo __FILE__;
        if (!$this->toolsHelper->isSuperuser()) {
            return;
        }
        $objUserHelper = new UserHelper;

        $objUserHelper->test();
        $profileModel = parent::getModel('Profile', 'Administrator', array('ignore_request' => true));
    }

}
