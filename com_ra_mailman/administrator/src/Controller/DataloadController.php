<?php

/**
 * @version    4.3.4
 * @package    com_ra_mailman
 * @author     Charlie Bigley <webmaster@bigley.me.uk>
 * @copyright  2023 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 *
 * Processing to create the User records is done in the save function of the model
 *
 * 20/06/23 CB created from MailshotController
 * 01/01/24 CB comments added
 * 16/09/24 CB deleted function process (processing carried out in Model / save)
 * 25/09/24 CB copied function save from com_ra_tools
 * 04/05/25 CB deleted redundant code for processing
 */

namespace Ramblers\Component\Ra_mailman\Administrator\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Factory;
//use Joomla\CMS\Language\Multilanguage;
//use Joomla\CMS\Language\Text;
//use Joomla\CMS\MVC\Controller\AdminController;
use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use \Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

/**
 * Dataload controller class.
 *
 * @since  1.0.2
 */
class DataloadController extends FormController {

    protected $view_item = 'dataload';
// Ensure control returns to Dashboard, not dataloads
    protected $view_list = 'dashboard';

    public function cancel($key = null, $urlVar = null) {
        // Flush the data from the session..
        $this->app->setUserState('com_ra_mailman.edit.upload.data', null);
        $this->setRedirect('index.php?option=com_ra_tools&view=dashboard');
    }

    public function check($key = NULL, $urlVar = NULL) {
        $return = $this->saveRecord($key, $urlVar);
        // Check for errors (lack of authority)
        if ($return === false) {
            // Redirect back to the edit screen.
            $this->setMessage('Save failed', $model->getError(), 'warning');
            $this->setRedirect(Route::_('index.php?option=com_ra_mailman&view=dataload&layout=edit', false));
            $this->redirect();
        }
        // Actual processing of the data file is carried out by view process / check
        $this->setRedirect(Route::_('index.php?option=com_ra_mailman&view=process&layout=check', false));
        $this->redirect();
    }

    public function continue() {
        // Invoked from view process id processing is set to 0
        // sets flag to 1, returns to the same view
        $data = $this->app->getUserState('com_ra_mailman.edit.upload.data', array());
        $data['processing'] = '1';
        $this->app->setUserState('com_ra_mailman.edit.upload.data', $data);
        $this->setRedirect('index.php?option=com_ra_mailman&view=process');
    }

    /**
     * Method to save data.
     *
     * @return  void
     *
     * @throws  Exception
     * @since   1.0.4
     */
    public function save($key = NULL, $urlVar = NULL) {
        $return = $this->saveRecord($key, $urlVar);
        /*
          //       echo 'Controller: save<br>';
          // Check for request forgeries.
          $this->checkToken();

          // Initialise variables.
          $model = $this->getModel('Dataload', 'Administrator');

          // Get the user data.
          $data = $this->input->get('jform', array(), 'array');

          // Validate the posted data.
          $form = $model->getForm();

          if (!$form) {
          throw new \Exception($model->getError(), 500);
          }

          // Send an object which can be modified through the plugin event
          $objData = (object) $data;
          $this->app->triggerEvent(
          'onContentNormaliseRequestData',
          array($this->option . '.' . $this->context, $objData, $form)
          );

          $data = (array) $objData;

          // Validate the posted data.
          $data = $model->validate($form, $data);
          echo 'Error: dumping data<br>';
          var_dump($data);
          //       die('Controller after save');
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
          $this->app->setUserState('com_ra_mailman.edit.upload.data', $jform);

          // Redirect back to the edit screen.

          $this->setRedirect(Route::_('/administrator/index.php?option=com_ra_mailman&view=dataload', false));

          $this->redirect();
          }
          //        echo 'Controller save 2<br>';
          // Save the data in the session.
          $this->app->setUserState('com_ra_mailman.edit.upload.data', $data);

          // Attempt to save the data. This will carry out the file upload

          $return = $model->save($data);
         */
        //        echo 'Controller save 1<br>';
//        echo "<br>Return = $return<br>";
        // Check for errors (lack of authority)
        if ($return === false) {
            // Redirect back to the edit screen.
            $this->setMessage('Save failed', $model->getError(), 'warning');
            $this->setRedirect(Route::_('index.php?option=com_ra_mailman&view=dataload&layout=edit', false));
            $this->redirect();
        }
        // Actual processing of the data file is carried out by view Process
        $this->setRedirect(Route::_('index.php?option=com_ra_mailman&view=process', false));
        $this->redirect();
    }

    public function saveRecord($key = NULL, $urlVar = NULL) {
        //       echo 'Controller: save<br>';
        // Check for request forgeries.
        $this->checkToken();

        // Initialise variables.
        $model = $this->getModel('Dataload', 'Administrator');

        // Get the user data.
        $data = $this->input->get('jform', array(), 'array');

        // Validate the posted data.
        $form = $model->getForm();

        if (!$form) {
            throw new \Exception($model->getError(), 500);
        }

        // Send an object which can be modified through the plugin event
        $objData = (object) $data;
        $this->app->triggerEvent(
                'onContentNormaliseRequestData',
                array($this->option . '.' . $this->context, $objData, $form)
        );

        $data = (array) $objData;

        // Validate the posted data.
        $data = $model->validate($form, $data);
        echo 'Error: dumping data<br>';
        var_dump($data);
        //       die('Controller after save');
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
            $this->app->setUserState('com_ra_mailman.edit.upload.data', $jform);

            // Redirect back to the edit screen.

            $this->setRedirect(Route::_('/administrator/index.php?option=com_ra_mailman&view=dataload', false));

            $this->redirect();
        }
        //        echo 'Controller save 2<br>';
        // Save the data in the session.
        $this->app->setUserState('com_ra_mailman.edit.upload.data', $data);

        // Attempt to save the data. This will carry out the file upload

        return $model->save($data);
    }

}
