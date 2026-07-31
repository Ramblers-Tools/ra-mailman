<?php

/**
 * @version    4.2.0
 * @package    com_ra_mailman
 * @author     Charlie Bigley <webmaster@bigley.me.uk>
 * @copyright  2023 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 14/11/23 CB remove reference to author_id
 * 13/02/25 CB replace getIdentity with getCurrentUser()
 */

namespace Ramblers\Component\Ra_mailman\Administrator\Model;

// No direct access.
defined('_JEXEC') or die;

use \Joomla\CMS\Factory;
use \Joomla\CMS\Language\Text;
use \Joomla\CMS\Plugin\PluginHelper;
use \Joomla\CMS\MVC\Model\AdminModel;
use \Joomla\CMS\Helper\TagsHelper;
use \Joomla\CMS\Filter\OutputFilter;
use Joomla\CMS\Table\Table;
use \Joomla\CMS\User\CurrentUserInterface;
use Joomla\Database\DatabaseDriver;

/**
 * Mailshot model.
 *
 * @since  1.0.2
 */
class MailshotModel extends AdminModel implements CurrentUserInterface {

    /**
     * @var    string  The prefix to use with controller messages.
     *
     * @since  1.0.2
     */
    protected $text_prefix = 'RA Mailman';
    protected $id;
    protected $list_id;
    protected $read_only;

    /**
     * @var    string  Alias to manage history control
     *
     * @since  1.0.2
     */
    public $typeAlias = 'com_ra_mailman.mailshot';

    /**
     * @var    null  Item data
     *
     * @since  1.0.2
     */
    protected $item = null;

    /**
     * Method to duplicate an Mailshot
     *
     * @param   array  &$pks  An array of primary key IDs.
     *
     * @return  boolean  True if successful.
     *
     * @throws  Exception
     */
    public function duplicate(&$pks) {
        $app = Factory::getApplication();
        $user = $this->getCurrentUser();

        // Access checks.
        if (!$user->authorise('core.create', 'com_ra_mailman')) {
            throw new \Exception(Text::_('JERROR_CORE_CREATE_NOT_PERMITTED'));
        }

        $context = $this->option . '.' . $this->name;

        // Include the plugins for the save events.
        PluginHelper::importPlugin($this->events_map['save']);

        $table = $this->getTable();

        foreach ($pks as $pk) {

            if ($table->load($pk, true)) {
                // Reset the id to create a new record.
                $table->id = 0;

                if (!$table->check()) {
                    throw new \Exception($table->getError());
                }

                if (!empty($table->event_type_id)) {
                    if (is_array($table->event_type_id)) {
                        $table->event_type_id = implode(',', $table->event_type_id);
                    }
                } else {
                    $table->event_type_id = '';
                }


                // Trigger the before save event.
                $result = $app->triggerEvent($this->event_before_save, array($context, &$table, true, $table));

                if (in_array(false, $result, true) || !$table->store()) {
                    throw new \Exception($table->getError());
                }

                // Trigger the after save event.
                $app->triggerEvent($this->event_after_save, array($context, &$table, true));
            } else {
                throw new \Exception($table->getError());
            }
        }

        // Clean cache
        $this->cleanCache();

        return true;
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
     * @since   1.0.2
     */
    public function getTable($type = 'Mailshot', $prefix = 'Administrator', $config = array()) {
        return parent::getTable($type, $prefix, $config);
    }

    /**
     * Method to get the record form.
     *
     * @param   array    $data      An optional array of data for the form to interogate.
     * @param   boolean  $loadData  True if the form is to load its own data (default case), false if not.
     *
     * @return  \JForm|boolean  A \JForm object on success, false on failure
     *
     * @since   1.0.2
     */
    public function getForm($data = array(), $loadData = true) {
        // Initialise variables.
        $app = Factory::getApplication();
        $list_id = $app->input->getInt('list_id', '0');
        // Get the form.
        $form = $this->loadForm(
                'com_ra_mailman.mailshot',
                'mailshot',
                array(
                    'control' => 'jform',
                    'load_data' => $loadData
                )
        );

        if (empty($form)) {
            return false;
        }
        // Set value of list_id from input
        $form->setFieldAttribute('mail_list_id', 'default', $list_id);

        // Hide audit fields for new record
        $id = $form->getvalue('id');
        if ($id == 0) {
            $form->removeField('date_sent');
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
     * Method to get the data that should be injected in the form.
     *
     * @return  mixed  The data for the form.
     *
     * @since   1.0.2
     */
    protected function loadFormData() {
        // Check the session for previously entered form data.
        $data = Factory::getApplication()->getUserState('com_ra_mailman.edit.mailshot.data', array());

        if (empty($data)) {
            if ($this->item === null) {
                $this->item = $this->getItem();
            }

            $data = $this->item;
        }

        // getItem() returns false when the id can't be loaded (e.g. a stale id from
        // the post-save redirect target) - Form::bind() requires object|array and
        // throws a FormEvent::onSetData() TypeError on false.
        if ($data === false) {
            $data = new \stdClass();
        }

        return $data;
    }

    /**
     * Method to get a single record.
     *
     * @param   integer  $pk  The id of the primary key.
     *
     * @return  mixed    Object on success, false on failure.
     *
     * @since   1.0.2
     */
    public function getItem($pk = null) {

        if ($item = parent::getItem($pk)) {
            if (isset($item->params)) {
                $item->params = json_encode($item->params);
            }

            // Do any procesing on fields here if needed
            if (!empty($item->attachment) && is_string($item->attachment)) {
                $item->attachment = array_values(array_filter(array_map('trim', explode(',', $item->attachment))));
            }
        }

        return $item;
    }

    /**
     * Prepare and sanitise the table prior to saving.
     *
     * @param   Table  $table  Table Object
     *
     * @return  void
     *
     * @since   1.0.2
     */
    protected function prepareTable($table) {
        jimport('joomla.filter.output');

        if (empty($table->id)) {

        }
    }

    public function setId($id) {
        $this->id = $id;
//        echo "model:id= $id<br>";
    }

    public function setList($list_id) {
        $this->list_id = $list_id;
//        echo "model:list id= $list_id<br>";
    }

    public function setReadonly($value) {
        $this->read_only = $value;
    }

}
