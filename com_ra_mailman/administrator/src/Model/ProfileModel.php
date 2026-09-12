<?php

/**
 * @version    4.6.0
 * @package    com_ra_mailman
 * @author     Charlie Bigley <webmaster@bigley.me.uk>
 * @copyright  2023 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 10/12/23 CB check username before creating a new user
 * 19/02/24 CB correction for creating Profile records - don't pass parameters
 * 05/11/24 CB lookup real name
 * 12/02/25 CB replace getIdentity with getCurrentUser
 * 07/07/25 CB use createUserDirect, not createUser
 * 25/11/25 CB save preferred_name
 * 06/12/25 CB update fields requireReset and block, delete comment
 */

namespace Ramblers\Component\Ra_mailman\Administrator\Model;

// No direct access.
defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use \Joomla\CMS\Table\Table;
use \Joomla\CMS\Factory;
use \Joomla\CMS\Language\Text;
use \Joomla\CMS\Plugin\PluginHelper;
use \Joomla\CMS\MVC\Model\AdminModel;
use \Joomla\CMS\Helper\TagsHelper;
use \Joomla\CMS\Filter\OutputFilter;
// use \Joomla\CMS\User\User
use Ramblers\Component\Ra_tools\Site\Helpers\PersonHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

/**
 * Profile model.
 *
 * @since  4.0.0
 */
class ProfileModel extends AdminModel {

    /**
     * @var    string  The prefix to use with controller messages.
     *
     * @since  4.0.0
     */
    protected $text_prefix = 'com_ra_mailman';

    /**
     * @var    string  Alias to manage history control
     *
     * @since  4.0.0
     */
    public $typeAlias = 'com_ra_mailman.profile';

    /**
     * @var    null  Item data
     *
     * @since  4.0.0
     */
    protected $item = null;

    /**
     * Method to get the record form.
     *
     * @param   array    $data      An optional array of data for the form to interrogate.
     * @param   boolean  $loadData  True if the form is to load its own data (default case), false if not.
     *
     * @return  \JForm|boolean  A \JForm object on success, false on failure
     *
     * @since   4.0.0
     */
    public function getForm($data = array(), $loadData = true) {
        // Initialise variables.
        $app = Factory::getApplication();
        $list_id = $app->input->getInt('list_id', '0');

        // Get the form.
        $form = $this->loadForm(
                'com_ra_mailman.profile',
                'profile',
                array(
                    'control' => 'jform',
                    'load_data' => $loadData
                )
        );

        if (empty($form)) {
            return false;
        }
        // set fields to read-only if not registering a new user
        $id = $form->getvalue('id');
//        echo 'id ' . $form->getvalue('id') . '<br>';
//        echo 'name ' . $form->getvalue('preferred_name') . '<br>';
        if ($id == 0) {
            $form->removeField('created');
            $form->removeField('created_by');
            $form->removeField('modified');
            $form->removeField('modified_by');
            // Set default group
            $params = ComponentHelper::getParams('com_ra_tools');
            $home_group = $params->get('default_group');
            $form->setFieldAttribute('home_group', 'default', $home_group);
        } else {
            $form->setFieldAttribute('real_name', 'readonly', "true");
            $form->setFieldAttribute('email', 'readonly', "true");
            // lookup details from the user record
            $toolsHelper = new ToolsHelper;
            $sql = 'SELECT name, email FROM `#__users` WHERE id=' . $id;
            $item = $toolsHelper->getItem($sql);
            if ($item) {
                $form->setFieldAttribute('preferred_name', 'default', $item->name);
                $form->setFieldAttribute('email', 'default', $item->email);
            }
        }
        return $form;
    }

    /**
     * Returns a reference to the a Table object, always creating it.
     *
     * @param   string  $type    The table type to instantiate
     * @param   string  $prefix  A prefix for the table class name. Optional.
     * @param   array   $config  Configuration array for model. Optional.
     *
     * @return  Table    A database object
     *
     * @since   4.0.0
     */
    public function getTable($type = 'Profile', $prefix = 'Administrator', $config = array()) {
        return parent::getTable($type, $prefix, $config);
    }

    /**
     * Method to get a single record.
     *
     * @param   integer  $pk  The id of the primary key.
     *
     * @return  mixed    Object on success, false on failure.
     *
     * @since   4.0.0
     */
    public function getItem($pk = null) {
        /*
          $db = $this->getDbo();
          $query = $db->getQuery(true)
          ->select('a.*')
          ->select('u.name AS real_name,u.email, u.requireReset,u.block')
          ->from($db->quoteName('#__ra_profiles', 'a'))
          ->leftjoin($db->quoteName('#__users', 'u') . ' ON ' . $db->quoteName('u.id') . ' = ' . $db->quoteName('a.id'))
          ->where($db->quoteName('a.id') . ' = ' . (int) $pk);
          echo $db->replacePrefix($query) . '<br>';
          $db->setQuery($query);

          $item = $db->loadObject;
          if ($item = parent::getItem($pk)) {
          if (isset($item->params)) {
          $item->params = json_encode($item->params);
          }
          }
          var_dump($item);
          die;
          return $item;
         */
        if ($item = parent::getItem($pk)) {
            if (isset($item->params)) {
                $item->params = json_encode($item->params);
            }

            // Do any processing on fields here if needed
            if ($item->id > 0) {
                $toolsHelper = new ToolsHelper;
                $sql = 'SELECT name, email, block, requireReset ';
                $sql .= 'FROM #__users WHERE id=' . $item->id;
                $user = $toolsHelper->getItem($sql);
                $item->real_name = $user->name;
                $item->email = $user->email;
                $item->requireReset = $user->requireReset;
                $item->block = $user->block;
            }
        }

        return $item;
    }

    /**
     * Method to get the data that should be injected in the form.
     *
     * @return  mixed  The data for the form.
     *
     * @since   4.0.0
     */
    protected function loadFormData() {
        // Check the session for previously entered form data.
        $data = Factory::getApplication()->getUserState('com_ra_mailman.edit.profile.data', array());

        if (empty($data)) {
            if ($this->item === null) {
                $this->item = $this->getItem();
            }

            $data = $this->item;
            /*
              // Support for multiple or not foreign key field: privacy_level
              $array = array();

              foreach ((array) $data->privacy_level as $value) {
              if (!is_array($value)) {
              $array[] = $value;
              }
              }
              if (!empty($array)) {

              $data->privacy_level = $array;
              }
             *
             */
        }

        return $data;
    }

    /**
     * Method to save the form data.
     *
     * @param   array $data The form data
     *
     * @return  bool
     *
     * @throws  Exception
     * @since   4.0.0
     */
    public function save($data) {
        // If id is given, it will be of the User record
        // A corresponding Profile record may or may not exist
        $app = Factory::getApplication();
        $user = $this->getCurrentUser();
        $personHelper = new PersonHelper;

        // change group code to upper case
        $data['home_group'] = strtoupper($data['home_group']);

        $id = (!empty($data['id'])) ? $data['id'] : (int) $this->getState('profile.id');
//        $state = (!empty($data['state'])) ? 1 : 0;
//        $id = (INT) $data['id'];
//        $app->enqueueMessage('Model invoked, id= ' . $id, 'info');
        if ($id) {
            // Check the user can edit this item
            $authorised = $user->authorise('core.edit', 'com_ra_mailman') || $authorised = $user->authorise('core.edit.own', 'com_ra_mailman');
        } else {
            // Check the user can create new items in this section
            $authorised = $user->authorise('core.create', 'com_ra_mailman');
        }

        if ($authorised !== true) {
            throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
        $email = $data['email'];
        $real_name = trim((string) ($data['real_name'] ?? ''));
        $requireReset = $data['requireReset'];
        $block = $data['block'];
        $toolsHelper = new ToolsHelper;
        if (empty($id)) {
            // check if email or real name already being used
            $conflicts = $personHelper->findUserIdentityConflicts($email, $real_name);
            $existingEmail = $conflicts['email'];
            $existingName = $conflicts['name'];

            if ($existingEmail && strcasecmp((string) $existingEmail->name, $real_name) !== 0) {
                Factory::getApplication()->enqueueMessage(
                        $real_name . '/' . $email . ' is invalid: email is already in use with name '
                        . $existingEmail->name,
                        'Error'
                );
                return false;
            }

            if ($existingName && strcasecmp((string) $existingName->email, $email) !== 0) {
                Factory::getApplication()->enqueueMessage(
                        'The new profile has been created',
                        'warning'
                );
            }
        }

//
//        echo "id=$id<br>";
//        var_dump($data);
        // If ID is present, we are just updating the Group code / preferred_name
        if (!empty($id)) {
            $warning = null;
            if ($real_name !== '') {
                $conflicts = $personHelper->findUserIdentityConflicts($email, $real_name, (int) $id);
                $existingName = $conflicts['name'];

                if ($existingName) {
                    $warning = 'Warning: the Real name "' . $real_name . '" is already used by '
                            . $existingName->name . ' (' . $existingName->email . '). The profile was still updated.';
                }
            }

            $table = $this->getTable();
            $table->load($id);

            try {
                if ($table->save($data) === true) {
                    $personHelper->setRequireReset((int) $id, (int) $requireReset === 1);
                    $personHelper->setBlocked((int) $id, (int) $block === 1);
                    if ($warning !== null) {
                        Factory::getApplication()->enqueueMessage($warning, 'warning');
                    }
                    return $table->id;
                } else {
                    Factory::getApplication()->enqueueMessage($table->getError(), 'error');
                    return false;
                }
            } catch (\Exception $e) {
                Factory::getApplication()->enqueueMessage($e->getMessage(), 'error');
                return false;
            }
        }
        // We are creating a new profile record
        // First create a Joomla User record
        try {
            $user_id = $personHelper->saveUser($real_name, $email, (int) $requireReset);
            $personHelper->saveProfileData($user_id, [
                'home_group' => $data['home_group'],
                'preferred_name' => $data['preferred_name'],
                'state' => !empty($data['state']) ? 1 : 0,
            ]);
        } catch (\Throwable $exception) {
            Factory::getApplication()->enqueueMessage($exception->getMessage(), 'error');
            return false;
        }

        $personHelper->setRequireReset($user_id, (int) $requireReset === 1);
        $personHelper->setBlocked($user_id, (int) $block === 1);
        $message = 'Created user ' . $real_name;
        $message .= ' (preferred name ' . $data['preferred_name'] . ')';
        Factory::getApplication()->enqueueMessage($message, 'info');
        return true;
    }

}
