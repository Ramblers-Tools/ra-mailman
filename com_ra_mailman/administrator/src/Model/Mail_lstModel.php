<?php

/**
 * @version    4.2.0
 * @package    com_ra_mailman
 * @author     Charlie Bigley <webmaster@bigley.me.uk>
 * @copyright  2023 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 27/01/24 CB publish
 * 22/10/24 CB comment out diagnostics
 * 12/02/25 CB replace getIdentity with getCurrentUser
 * 16/03/26 CB use defauls group if not full_version
 * 04/11/26 CB use container DatabaseInterface for J6 compatibility
 */

namespace Ramblers\Component\Ra_mailman\Administrator\Model;

// No direct access.
defined('_JEXEC') or die;

use \Joomla\CMS\Table\Table;
use \Joomla\CMS\Factory;
use \Joomla\CMS\Language\Text;
use \Joomla\CMS\Plugin\PluginHelper;
use \Joomla\CMS\MVC\Model\AdminModel;
use \Joomla\CMS\Helper\TagsHelper;
use \Joomla\CMS\Filter\OutputFilter;
use Joomla\Database\DatabaseInterface;
use \Ramblers\Component\Ra_mailman\Site\Helpers\Mailhelper;

/**
 * Mail_lst model.
 *
 * @since  1.0.6
 */
class Mail_lstModel extends AdminModel {

    /**
     * @var    string  The prefix to use with controller messages.
     *
     * @since  1.0.6
     */
    protected $text_prefix = 'RA Mailman';

    /**
     * @var    string  Alias to manage history control
     *
     * @since  1.0.6
     */
    public $typeAlias = 'com_ra_mailman.mail_lst';

    /**
     * @var    null  Item data
     *
     * @since  1.0.6
     */
    protected $item = null;

    /**
     * Returns a reference to the a Table object, always creating it.
     *
     * @param   string  $type    The table type to instantiate
     * @param   string  $prefix  A prefix for the table class name. Optional.
     * @param   array   $config  Configuration array for model. Optional.
     *
     * @return  Table    A database object
     *
     * @since   1.0.6
     */
    public function getTable($type = 'Mail_lst', $prefix = 'Administrator', $config = array()) {
        //       die('Model getting table ' . $type);
        return parent::getTable($type, $prefix, $config);
    }

    /**
     * Method to get the record form.
     *
     * @param   array    $data      An optional array of data for the form to interrogate.
     * @param   boolean  $loadData  True if the form is to load its own data (default case), false if not.
     *
     * @return  \JForm|boolean  A \JForm object on success, false on failure
     *
     * @since   1.0.6
     */
    public function getForm($data = array(), $loadData = true) {
        // Initialise variables.
        $app = Factory::getApplication();

        // See if we are running the full version
        $mailHelper = new MailHelper;
        $group = $mailHelper->getDefaultGroup();

        // Get the form.
        $form = $this->loadForm(
                'com_ra_mailman.mail_lst',
                'mail_lst',
                array(
                    'control' => 'jform',
                    'load_data' => $loadData
                )
        );

        if (empty($form)) {
            return false;
        }
        // If not full version, cannot choose group
        if ($group !== 'N') {
            $form->setFieldAttribute('group_code', 'default', $group);
            $form->setFieldAttribute('group_code', 'readonly', 'true');
//            $form->setFieldAttribute('chat_list', 'readonly', 'true');
            $form->removeField('chat_list');
        }
        $list_id = $form->getvalue('list_id');
        $form->setFieldAttribute('mail_list_id', 'default', $list_id);

        // Hide audit fields for new record
        $id = $form->getvalue('id');
        if ($id == 0) {
            $form->removeField('created');
            $form->removeField('created_by');
            $form->removeField('modified');
            $form->removeField('modified_by');
        }
        $date_sent = $form->getvalue('date_sent');
        $processing_started = $form->getvalue('processing_started');
//        var_dump($list_id);
//        echo '<br>';
//        var_dump($data);
        //       die;
        return $form;
    }

    /**
     * Method to get the data that should be injected in the form.
     *
     * @return  mixed  The data for the form.
     *
     * @since   1.0.6
     */
    protected function loadFormData() {
        // Check the session for previously entered form data.
        $data = Factory::getApplication()->getUserState('com_ra_mailman.edit.mail_lst.data', array());

        if (empty($data)) {
            if ($this->item === null) {
                $this->item = $this->getItem();
            }

            $data = $this->item;
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
     * @since   1.0.6
     */
    public function getItem($pk = null) {

        if ($item = parent::getItem($pk)) {
            if (isset($item->params)) {
                $item->params = json_encode($item->params);
            }

            // Do any procesing on fields here if needed
        }

        return $item;
    }

    /**
     * Method to duplicate an Mail_lst
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
     * Prepare and sanitise the table prior to saving.
     *
     * @param   Table  $table  Table Object
     *
     * @return  void
     *
     * @since   1.0.6
     */
    protected function prepareTable($table) {
        jimport('joomla.filter.output');

        if (empty($table->id)) {
            // Set ordering to the last item if not set
            if (@$table->ordering === '') {
                $db = Factory::getContainer()->get(DatabaseInterface::class);
                $db->setQuery('SELECT MAX(ordering) FROM `#__ra_mail_lists`');
                $max = $db->loadResult();
                $table->ordering = $max + 1;
            }
        }
    }

    /**
     * Method to change the published state of one or more records.
     *
     * @param   array    &$pks   A list of the primary keys to change.
     * @param   integer  $value  The value of the published state.
     *
     * @return  boolean  True on success.
     *
     * @since   4.0.0
     */
    public function publish(&$pks, $value = 1) {
        /* this is a very simple method to change the state of each item selected */
        $db = Factory::getContainer()->get(DatabaseInterface::class);

        $query = $db->getQuery(true);

        $query->update('`#__ra_mail_lists`');
        $query->set('state = ' . $value);
        $query->where('id IN (' . implode(',', $pks) . ')');
        $db->setQuery($query);
        $db->execute();
    }

    /**
     * Publish the element
     *
     * @param   int $id    Item id
     * @param   int $state Publish state
     *
     * @return  boolean
     */
//    public function publish($id, $state) {
//        $table = $this->getTable();
//        $table->load($id);
//        $table->state = $state;
//        return $table->store();
//    }
}
