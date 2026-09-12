<?php

/**
 * @version    4.5.7
 * @package    com_ra_mailman
 * @author     Charlie Bigley <charlie@bigley.me.uk>
 * @copyright  2025 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Ramblers\Component\Ra_mailman\Site\Model;

// No direct access.
defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use \Joomla\CMS\Factory;
use \Joomla\Utilities\ArrayHelper;
use \Joomla\CMS\Language\Text;
use \Joomla\CMS\Table\Table;
use \Joomla\CMS\MVC\Model\FormModel;
use \Joomla\CMS\Object\CMSObject;
use \Joomla\CMS\Helper\TagsHelper;
use \Joomla\CMS\User\CurrentUserInterface;
use Ramblers\Component\Ra_mailman\Site\Helpers\MailHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\PersonHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

/**
 * Profile model.
 *
 * @since  4.5.7
 */
class ProfileformModel extends FormModel {

    private $item = null;

    /**
     * Method to auto-populate the model state.
     *
     * Note. Calling getState in this method will result in recursion.
     *
     * @return  void
     *
     * @since   4.5.7
     *
     * @throws  Exception
     */
    protected function populateState() {
        $app = Factory::getApplication('com_ra_mailman');

        // Load state from the request userState on edit or from the passed variable on default
        if (Factory::getApplication()->input->get('layout') == 'edit') {
            $id = Factory::getApplication()->getUserState('com_ra_mailman.edit.profile.id');
        } else {
            $id = Factory::getApplication()->input->get('id');
            Factory::getApplication()->setUserState('com_ra_mailman.edit.profile.id', $id);
        }

        $this->setState('profile.id', $id);

        // Load the parameters.
        $params = $app->getParams();
        $params_array = $params->toArray();

        if (isset($params_array['item_id'])) {
            $this->setState('profile.id', $params_array['item_id']);
        }

        $this->setState('params', $params);
    }

    /**
     * Method to get an ojbect.
     *
     * @param   integer $id The id of the object to get.
     *
     * @return  Object|boolean Object on success, false on failure.
     *
     * @throws  Exception
     */
    public function getItem($id = null) {
        if ($this->item === null) {
            $this->item = false;

            if (empty($id)) {
                $id = $this->getState('profile.id');
            }

            // Get a level row instance.
            $table = $this->getTable();
            $properties = $table->getProperties();
            $this->item = ArrayHelper::toObject($properties, CMSObject::class);

            if ($table !== false && $table->load($id) && !empty($table->id)) {
                $user = Factory::getApplication()->getIdentity();
                $id = $table->id;

                $canEdit = ($user->id == 0) || $user->authorise('core.edit', 'com_ra_mailman') || $user->authorise('core.create', 'com_ra_mailman');

                if (!$canEdit && $user->authorise('core.edit.own', 'com_ra_mailman')) {
                    $canEdit = $user->id == $table->created_by;
                }

                if (!$canEdit) {
                    throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
                }

                // Check published state.
                if ($published = $this->getState('filter.published')) {
                    if (isset($table->state) && $table->state != $published) {
                        return $this->item;
                    }
                }

                // Convert the Table to a clean CMSObject.
                $properties = $table->getProperties(1);
                $this->item = ArrayHelper::toObject($properties, CMSObject::class);
            }
        }

        return $this->item;
    }

    /**
     * Method to get the table
     *
     * @param   string $type   Name of the Table class
     * @param   string $prefix Optional prefix for the table class name
     * @param   array  $config Optional configuration array for Table object
     *
     * @return  Table|boolean Table if found, boolean false on failure
     */
    public function getTable($type = 'Profile', $prefix = 'Administrator', $config = array()) {
        return parent::getTable($type, $prefix, $config);
    }

    /**
     * Get an item by alias
     *
     * @param   string $alias Alias string
     *
     * @return int Element id
     */
    public function getItemIdByAlias($alias) {
        $table = $this->getTable();
        $properties = $table->getProperties();

        if (!in_array('alias', $properties)) {
            return null;
        }

        $table->load(array('alias' => $alias));
        $id = $table->id;

        return $id;
    }

    /**
     * Method to check in an item.
     *
     * @param   integer $id The id of the row to check out.
     *
     * @return  boolean True on success, false on failure.
     *
     * @since   4.5.7
     */
    public function checkin($id = null) {
        // Get the id.
        $id = (!empty($id)) ? $id : (int) $this->getState('profile.id');

        if ($id) {
            // Initialise the table
            $table = $this->getTable();

            // Attempt to check the row in.
            if (method_exists($table, 'checkin')) {
                if (!$table->checkin($id)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Method to check out an item for editing.
     *
     * @param   integer $id The id of the row to check out.
     *
     * @return  boolean True on success, false on failure.
     *
     * @since   4.5.7
     */
    public function checkout($id = null) {
        // Get the user id.
        $id = (!empty($id)) ? $id : (int) $this->getState('profile.id');

        if ($id) {
            // Initialise the table
            $table = $this->getTable();

            // Get the current user object.
            $user = Factory::getApplication()->getIdentity();

            // Attempt to check the row out.
            if (method_exists($table, 'checkout')) {
                if (!$table->checkout($user->get('id'), $id)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Method to get the profile form.
     *
     * The base form is loaded from XML
     *
     * @param   array   $data     An optional array of data for the form to interogate.
     * @param   boolean $loadData True if the form is to load its own data (default case), false if not.
     *
     * @return  Form    A Form object on success, false on failure
     *
     * @since   4.5.7
     */
    public function getForm($data = array(), $loadData = true) {
        // Get the form.
        $form = $this->loadForm('com_ra_mailman.profile', 'profileform', array(
            'control' => 'jform',
            'load_data' => $loadData
                )
        );

        if (empty($form)) {
            return false;
        }
//     Set value of home group from component default
        $params = ComponentHelper::getParams('com_ra_tools');
        $home_group = $params->get('default_group');
        $form->setFieldAttribute('home_group', 'default', $home_group);

        // If admin registration, upload field labels
        $current_user = $this->getCurrentUser()->id;
        if ($current_user > 0) {
            $form->setFieldAttribute('real_name', 'label', 'Real name');
            $form->setFieldAttribute('real_name', 'description', 'The Users actual name (will not be shown publicly)');
            $form->setFieldAttribute('preferred_name', 'description', 'Name by which the user prefers to be known');
        }
        return $form;
    }

    /**
     * Method to get the data that should be injected in the form.
     *
     * @return  array  The default data is an empty array.
     * @since   4.5.7
     */
    protected function loadFormData() {
        $data = Factory::getApplication()->getUserState('com_ra_mailman.edit.profile.data', array());

        if (empty($data)) {
            $data = $this->getItem();
        }

        if ($data) {


            return $data;
        }

        return array();
    }

    /**
     * Method to save the form data.
     *
     * @param   array $data The form data
     *
     * @return  bool
     *
     * @throws  Exception
     * @since   4.5.7
     */
    public function save($data) {
        $id = (!empty($data['id'])) ? $data['id'] : (int) $this->getState('profile.id');
        $state = (!empty($data['state'])) ? 1 : 0;
        $app = Factory::getApplication();
        $user = $this->getCurrentUser();
        $personHelper = new PersonHelper;

        if ($id) {
            // Check the user can edit this item
            $authorised = $user->authorise('core.edit', 'com_ra_mailman') || $authorised = $user->authorise('core.edit.own', 'com_ra_mailman');
//        } else {
//            // Check the user can create new items in this section
//            $authorised = $user->authorise('core.create', 'com_ra_mailman');
        }
// change group code to upper case
        $home_group = strtoupper($data['home_group']);
        $email = $data['email'];
        $real_name = $data['real_name'];
        $preferred_name = $data['preferred_name'];
        // Self-registration is permitted. An administrator must authorise the
        // resulting profile before it is published.
        // The former userExists() rejection is intentionally disabled.

// if user is logged in, and  Group / Name / Email match an existing user, allow selection of further lists
        if ($user->id > 0) {
            //      see if this email is already in use
            $user_id = $personHelper->findUserByEmailAndProfile($email, $preferred_name, $home_group);
            //           die($user_id);
            if ($user_id > 0) {
                Factory::getApplication()->enqueueMessage('This user already exists', 'Info');
                return $user_id;
            }
        }

//      first create a user
        try {
            // Keep the password-reset requirement for self-registration. The
            // profile remains unpublished until an administrator approves it.
            $user_id = $personHelper->saveUser($real_name, $email, 1);
            $personHelper->saveProfileData($user_id, [
                'home_group' => $home_group,
                'preferred_name' => $preferred_name,
                'state' => 0,
            ]);
            Factory::getApplication()->enqueueMessage('Created profile record for ' . $preferred_name . ' in group ' . $home_group, 'info');
        } catch (\Throwable $exception) {
            Factory::getApplication()->enqueueMessage($exception->getMessage(), 'error');
            return false;
        }

        if ($user_id > 0) {

            // Save the data in the session for the Controller to use
            Factory::getApplication()->setUserState('com_ra_mailman.edit.profile.id', $user_id);
            $this->subscribeDefault($user_id);
        }
        return $user_id;
    }

    /**
     * Method to delete data
     *
     * @param   int $pk Item primary key
     *
     * @return  int  The id of the deleted item
     *
     * @throws  Exception
     *
     * @since   4.5.7
     */
    public function delete($id) {
        $user = Factory::getApplication()->getIdentity();

        if (empty($id)) {
            $id = (int) $this->getState('profile.id');
        }

        if ($id == 0 || $this->getItem($id) == null) {
            throw new \Exception(Text::_('COM_RA_MAILMAN_ITEM_DOESNT_EXIST'), 404);
        }

        if ($user->authorise('core.delete', 'com_ra_mailman') !== true) {
            throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $table = $this->getTable();

        if ($table->delete($id) !== true) {
            throw new \Exception(Text::_('JERROR_FAILED'), 501);
        }

        return $id;
    }

    /**
     * Check if data can be saved
     *
     * @return bool
     */
    public function getCanSave() {
        $table = $this->getTable();

        return $table !== false;
    }

    private function subscribeDefault($user_id) {
        // Sets a subscription to the Group's "Primary" list for a new User
        $toolsHelper = new ToolsHelper;
        $sql = 'SELECT u.email, u.username, u.registerDate, p.home_group ';
        $sql .= 'FROM #__users AS u ';
        $sql .= 'LEFT JOIN #__ra_profiles as p ON p.id = u.id ';
        $sql .= 'WHERE u.id=' . $user_id;
        $item = $toolsHelper->getItem($sql);

        $sql = 'SELECT id, group_code, name, record_type FROM `#__ra_mail_lists` ';
        $sql .= 'WHERE group_primary="' . $item->home_group . '" ';
        $list = $toolsHelper->getItem($sql);

        if ($list->id == 0) {
            $message = 'Sorry, there is no default Newsletter for Group ' . $item->home_group;
            $message .= '/' . $toolsHelper->lookupGroup($item->home_group);
            Factory::getApplication()->enqueueMessage($message, 'info');
            return;
        }

//          Check that the list is open to subscription by this user
        if ($list->record_type != 'O') {
            $message = 'You cannot subscribe youself to ' . $item->home_group . ' ' . $list->name . ' because it is a Closed list. ';
            $message .= 'Please contact a member of the committee';
            Factory::getApplication()->enqueueMessage($message, 'error');
            return false;
        }

//        Factory::getApplication()->enqueueMessage('Model: subscribing user ' . $user_id . ' to ' . $list_id, 'info');
        $mailHelper = new Mailhelper;
        $record_type = 1;  // subscriber
        $method = 1;       // Self registered
        $response = $mailHelper->subscribe($list->id, $user_id, $record_type, $method);
        Factory::getApplication()->enqueueMessage($mailHelper->message, 'info');
    }

}
