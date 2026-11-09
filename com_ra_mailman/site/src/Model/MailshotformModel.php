<?php

/**
 * @version    4.2.0
 * @package    com_ra_mailman
 * @author     Charlie Bigley <webmaster@bigley.me.uk>
 * @copyright  2024 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 11/09/24 CB special code when loading form
 * 07/10/24 CB don't change attributes of field mail_list_id
 * 12/02/25 CB replace getIdentity with getCurrentUser
 * 13/02/25 CB implement CurrentUserInterface
 * 16/02/26 CB grant owner right to Save and Edit
 */

namespace Ramblers\Component\Ra_mailman\Site\Model;

// No direct access.
defined('_JEXEC') or die;

use \Joomla\CMS\Factory;
use \Joomla\Utilities\ArrayHelper;
use \Joomla\CMS\Language\Text;
use \Joomla\CMS\Table\Table;
use \Joomla\CMS\MVC\Model\FormModel;
use \Joomla\CMS\Object\CMSObject;
use \Joomla\CMS\Helper\TagsHelper;
use \Joomla\CMS\User\CurrentUserInterface;
use Ramblers\Component\Ra_mailman\Site\Helpers\Mailhelper;

/**
 * Ra_mailman model.
 *
 * @since  1.0.2
 */
class MailshotformModel extends FormModel implements CurrentUserInterface {

    private $item = null;

    /**
     * Method to check in an item.
     *
     * @param   integer $id The id of the row to check out.
     *
     * @return  boolean True on success, false on failure.
     *
     * @since   1.0.2
     */
    public function checkin($id = null) {
        // Get the id.
        $id = (!empty($id)) ? $id : (int) $this->getState('mailshot.id');

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
     * @since   1.0.2
     */
    public function checkout($id = null) {
        // Get the user id.
        $id = (!empty($id)) ? $id : (int) $this->getState('mailshot.id');

        if ($id) {
            // Initialise the table
            $table = $this->getTable();

            // Get the current user object.
            $user = $this->getCurrentUser();

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
     * Method to delete data
     *
     * @param   int $pk Item primary key
     *
     * @return  int  The id of the deleted item
     *
     * @throws  Exception
     *
     * @since   1.0.2
     */
    public function delete($id) {
        $user = $this->getCurrentUser();

        if (empty($id)) {
            $id = (int) $this->getState('mailshot.id');
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
     * Method to get an object.
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
                $id = $this->getState('mailshot.id');
            }

            // Get a level row instance.
            $table = $this->getTable();
            $properties = $table->getProperties();
            $this->item = ArrayHelper::toObject($properties, CMSObject::class);

            if ($table !== false && $table->load($id) && !empty($table->id)) {
                $user = $this->getCurrentUser();
                $id = $table->id;
                $app = Factory::getApplication();
                $list_id = $app->input->getInt('list_id', '0');
                $mailHelper = new Mailhelper;
                if ($mailHelper->isAuthor($list_id)) {
                    $canEdit = true;
                } else {
                    $canEdit = $user->authorise('core.edit', 'com_ra_mailman') || $user->authorise('core.create', 'com_ra_mailman');

                    if (!$canEdit && $user->authorise('core.edit.own', 'com_ra_mailman')) {
                        $canEdit = $user->id == $table->created_by;
                    }
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

                if (!empty($this->item->attachment) && is_string($this->item->attachment)) {
                    $this->item->attachment = array_values(array_filter(array_map('trim', explode(',', $this->item->attachment))));
                }
            }
        }

        return $this->item;
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
     * Method to get the profile form.
     *
     * The base form is loaded from XML
     *
     * @param   array   $data     An optional array of data for the form to interogate.
     * @param   boolean $loadData True if the form is to load its own data (default case), false if not.
     *
     * @return  Form    A Form object on success, false on failure
     *
     * @since   1.0.2
     */
    public function getForm($data = array(), $loadData = true) {
        // Initialise variables.
        $app = Factory::getApplication();
        $list_id = $app->input->getInt('list_id', '0');
//        die('list id = ' . $list_id);
        // Get the form.
        $form = $this->loadForm('com_ra_mailman.mailshot', 'mailshotform', array(
            'control' => 'jform',
            'load_data' => $loadData
                )
        );

        if (empty($form)) {
            return false;
        }
        // Set value of list_id from input for use in save
        $form->setFieldAttribute('mail_list_id', 'default', $list_id);

        // invitation_preamble is not stored separately; an event_id means the
        // mailshot has an invitation and should therefore display Yes.
        if ((int) $form->getValue('event_id') > 0) {
            $form->setValue('invitation_preamble', null, '1');
        }

        // Hide audit fields for new record
        $id = $form->getvalue('id');
        if ($id == 0) {
            $form->removeField('created');
            $form->removeField('created_by');
            $form->removeField('modified');
            $form->removeField('modified_by');
        }

        // set fields  to read-only if the mailshot has been sent
        if ($this->read_only) {
            $form->setFieldAttribute('title', 'readonly', "true");
            $form->setFieldAttribute('body', 'type', "textarea");
            $form->setFieldAttribute('body', 'readonly', "true");
            $form->setFieldAttribute('state', 'readonly', "true");
        }
        return $form;
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
    public function getTable($type = 'Mailshot', $prefix = 'Administrator', $config = array()) {
        return parent::getTable($type, $prefix, $config);
    }

    /**
     * Method to get the data that should be injected in the form.
     *
     * @return  array  The default data is an empty array.
     * @since   1.0.2
     */
    protected function loadFormData() {
        $data = Factory::getApplication()->getUserState('com_ra_mailman.edit.mailshot.data', array());

        if (empty($data)) {
            $data = $this->getItem();
        }

        if ($data) {
            return $data;
        }

        return array();
    }

        /**
     * Method to auto-populate the model state.
     *
     * Note. Calling getState in this method will result in recursion.
     *
     * @return  void
     *
     * @since   1.0.2
     *
     * @throws  Exception
     */
    protected function populateState() {
        $app = Factory::getApplication('com_ra_mailman');

        // Load state from the request userState on edit or from the passed variable on default
        if (Factory::getApplication()->input->get('layout') == 'edit') {
            $id = Factory::getApplication()->getUserState('com_ra_mailman.edit.mailshot.id');
        } else {
            $id = Factory::getApplication()->input->get('id');
            Factory::getApplication()->setUserState('com_ra_mailman.edit.mailshot.id', $id);
        }

        $this->setState('mailshot.id', $id);

        // Load the parameters.
        $params = $app->getParams();
        $params_array = $params->toArray();

        if (isset($params_array['item_id'])) {
            $this->setState('mailshot.id', $params_array['item_id']);
        }

        $this->setState('params', $params);
    }


    /**
     * Method to save the form data.
     *
     * @param   array $data The form data
     *
     * @return  bool
     *
     * @throws  Exception
     * @since   1.0.2
     */
    public function save($data) {
        // The event field is hidden when invitation_preamble is No, so it is
        // omitted from the request. Clear the saved event explicitly instead
        // of retaining the invitation selected during an earlier edit.
        if (array_key_exists('invitation_preamble', $data) && (int) $data['invitation_preamble'] === 0) {
            $data['event_id'] = 0;
        }

        $id = (!empty($data['id'])) ? $data['id'] : (int) $this->getState('mailshot.id');
        $state = (!empty($data['state'])) ? 1 : 0;
        $list_id = $data['mail_list_id'];
//               var_dump($data);
//               die($list_id);
        $mailHelper = new Mailhelper;
        if ($mailHelper->isAuthor($list_id)) {
            $authorised = true;
        } else {
            if ($id) {
                // Check the user can edit this item
                $authorised = $user->authorise('core.edit', 'com_ra_mailman') || $authorised = $user->authorise('core.edit.own', 'com_ra_mailman');
            } else {
                // Check the user can create new items in this section
                $authorised = $user->authorise('core.create', 'com_ra_mailman');
            }
        }
        $user = $this->getCurrentUser();

        if ($authorised !== true) {
            throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $table = $this->getTable();

        if (!empty($id)) {
            $table->load($id);
        }

        try {
            if ($table->save($data) === true) {
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

  
    /**
     * Check if data can be saved
     *
     * @return bool
     */
    public function getCanSave() {
        $table = $this->getTable();

        return $table !== false;
    }

}
