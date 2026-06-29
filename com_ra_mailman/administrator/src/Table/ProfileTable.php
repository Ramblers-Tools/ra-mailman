<?php

/**
 * @version    4.7.8
 * @package    com_ra_mailman
 * @author     Charlie Bigley <webmaster@bigley.me.uk>
 * @copyright  2023 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 01/12/23 CB set default value of privacy_level to 3
 * 04/11/24 CB use id derived from getIdentity instead of getUser
 * 12/02/25 CB don't use getIdentity
 * 17/02/25 CB reinstate parent::store
 * 22/06/26 CB extra processing of subs etc when deleting a profile record
 */

namespace Ramblers\Component\Ra_mailman\Administrator\Table;

// No direct access
defined('_JEXEC') or die;

//use \Joomla\Utilities\ArrayHelper;
use \Joomla\CMS\Factory;
use \Joomla\CMS\Access\Access;
//use \Joomla\CMS\Language\Text;
use \Joomla\CMS\Table\Table as Table;
use \Joomla\CMS\Versioning\VersionableTableInterface;
use Joomla\CMS\Tag\TaggableTableInterface;
use Joomla\CMS\Tag\TaggableTableTrait;
use \Joomla\Database\DatabaseDriver;
//use \Joomla\CMS\Filter\OutputFilter;
//use \Joomla\CMS\Filesystem\File;
use \Joomla\Registry\Registry;
use \Joomla\CMS\Helper\ContentHelper;

/**
 * Profile table
 *
 * @since 4.0.0
 */
class ProfileTable extends Table implements VersionableTableInterface, TaggableTableInterface {

    use TaggableTableTrait;

    /**
     * Constructor
     *
     * @param   JDatabase  &$db  A database connector object
     */
    public function __construct(DatabaseDriver $db) {
        $this->typeAlias = 'com_ra_mailman.profile';
        parent::__construct('#__ra_profiles', 'id', $db);
        $this->setColumnAlias('published', 'state');
    }

    /**
     * Get the type alias for the history table
     *
     * @return  string  The alias as described above
     *
     * @since   4.0.0
     */
    public function getTypeAlias() {
        return $this->typeAlias;
    }

    /**
     * Overloaded bind function to pre-process the params.
     *
     * @param   array  $array   Named array
     * @param   mixed  $ignore  Optional array or list of parameters to ignore
     *
     * @return  boolean  True on success.
     *
     * @see     Table:bind
     * @since   4.0.0
     * @throws  \InvalidArgumentException
     */
    public function bind($array, $ignore = '') {
        $date = Factory::getDate();
//        $task = Factory::getApplication()->input->get('task');
        $app = Factory::getApplication();
        $user = $app->getSession()->get('user');

        $input = $app->input;
        $task = $input->getString('task', '');
        // Ensure group code is upper case
        $array['home_group'] = strtoupper($array['home_group']);

        if ($array['id'] == 0) {
            $array['created'] = Factory::getDate('now', Factory::getConfig()->get('offset'))->toSql(true);
            $array['created_by'] = $user->id;
        }

        // Support for checkbox field: acknowledge_follow
        if (!isset($array['acknowledge_follow'])) {
            $array['acknowledge_follow'] = 0;
        }

        // Support for multiple field: privacy_level
        if (isset($array['privacy_level'])) {
            if (is_array($array['privacy_level'])) {
                $array['privacy_level'] = implode(',', $array['privacy_level']);
            } elseif (strpos($array['privacy_level'], ',') != false) {
                $array['privacy_level'] = explode(',', $array['privacy_level']);
            } elseif (strlen($array['privacy_level']) == 0) {
                $array['privacy_level'] = '3';
            }
        } else {
            $array['privacy_level'] = '3';
        }

        // Support for blank membershipNumber
        if (isset($array['membershipNumber'])) {
            if (($array['membershipNumber'] == '') OR ($array['membershipNumber'] == '0')) {
                $array['membershipNumber'] = NULL;
            }
            if (!isset($array['memberType'])) {
                $array['memberType'] = 'Member';
            }
            if (!isset($array['memberTerm'])) {
                $array['memberTerm'] = 'Annual';
            }
        } else {
            $array['membershipNumber'] = NULL;
        }

        // Support for checkbox field: contactviaemail
        if (!isset($array['contactviaemail'])) {
            $array['contactviaemail'] = 0;
        }

        // Support for checkbox field: contactviatextmessage
        if (!isset($array['contactviatextmessage'])) {
            $array['contactviatextmessage'] = 0;
        }

        // Support for checkbox field: notify_joiners
        if (!isset($array['notify_joiners'])) {
            $array['notify_joiners'] = 0;
        }

        if (isset($array['params']) && is_array($array['params'])) {
            $registry = new Registry;
            $registry->loadArray($array['params']);
            $array['params'] = (string) $registry;
        }

        if (isset($array['metadata']) && is_array($array['metadata'])) {
            $registry = new Registry;
            $registry->loadArray($array['metadata']);
            $array['metadata'] = (string) $registry;
        }

        if (!$user->authorise('core.admin', 'com_ra_mailman.profile.' . $array['id'])) {
            $actions = Access::getActionsFromFile(
                            JPATH_ADMINISTRATOR . '/components/com_ra_mailman/access.xml',
                            "/access/section[@name='profile']/"
            );
            $default_actions = Access::getAssetRules('com_ra_mailman.profile.' . $array['id'])->getData();
            $array_jaccess = array();

            foreach ($actions as $action) {
                if (key_exists($action->name, $default_actions)) {
                    $array_jaccess[$action->name] = $default_actions[$action->name];
                }
            }

            $array['rules'] = $this->JAccessRulestoArray($array_jaccess);
        }

        // Bind the rules for ACL where supported.
        if (isset($array['rules']) && is_array($array['rules'])) {
            $this->setRules($array['rules']);
        }

        return parent::bind($array, $ignore);
    }

    /**
     * Method to store a row in the database from the Table instance properties.
     *
     * If a primary key value is set the row with that primary key value will be updated with the instance property values.
     * If no primary key value is set a new row will be inserted into the database with the properties from the Table instance.
     *
     * @param   boolean  $updateNulls  True to update fields even if they are null.
     *
     * @return  boolean  True on success.
     *
     * @since   4.0.0
     */
    public function store($updateNulls = true) {
        if ($this->id > 0) {
            $this->modified_by = Factory::getApplication()->getSession()->get('user')->id;
            $this->modified = Factory::getDate('now', Factory::getConfig()->get('offset'))->toSql(true);
        }
        return parent::store($updateNulls);
    }

    /**
     * This function convert an array of Access objects into an rules array.
     *
     * @param   array  $jaccessrules  An array of Access objects.
     *
     * @return  array
     */
    private function JAccessRulestoArray($jaccessrules) {
        $rules = array();

        foreach ($jaccessrules as $action => $jaccess) {
            $actions = array();

            if ($jaccess) {
                foreach ($jaccess->getData() as $group => $allow) {
                    $actions[$group] = ((bool) $allow);
                }
            }

            $rules[$action] = $actions;
        }

        return $rules;
    }

    /**
     * Overloaded check function
     *
     * @return bool
     */
    public function check() {
        // If there is an ordering column and this is a new row then get the next ordering value
        if (property_exists($this, 'ordering') && $this->id == 0) {
            $this->ordering = self::getNextOrder();
        }



        return parent::check();
    }

    /**
     * Define a namespaced asset name for inclusion in the #__assets table
     *
     * @return string The asset name
     *
     * @see Table::_getAssetName
     */
    protected function _getAssetName() {
        $k = $this->_tbl_key;

        return $this->typeAlias . '.' . (int) $this->$k;
    }

    /**
     * Returns the parent asset's id. If you have a tree structure, retrieve the parent's id using the external key field
     *
     * @param   Table   $table  Table name
     * @param   integer  $id     Id
     *
     * @see Table::_getAssetParentId
     *
     * @return mixed The id on success, false on failure.
     */
    protected function _getAssetParentId($table = null, $id = null) {
        // We will retrieve the parent-asset from the Asset-table
        $assetParent = Table::getInstance('Asset');

        // Default: if no asset-parent can be found we take the global asset
        $assetParentId = $assetParent->getRootId();

        // The item has the component as asset-parent
        $assetParent->loadByName('com_ra_mailman');

        // Return the found asset-parent-id
        if ($assetParent->id) {
            $assetParentId = $assetParent->id;
        }

        return $assetParentId;
    }

    //XXX_CUSTOM_TABLE_FUNCTION

    /**
     * Delete a record by id
     *
     * @param   mixed  $pk  Primary key value to delete. Optional
     *
     * @return bool
     */
    public function delete($pk = null) {
        $this->load($pk);
        $this->deleteLinkedRecords($pk);
        $result = parent::delete($pk);

        return $result;
    }

    private function deleteLinkedRecords($id) {
        // Update membership of UserGroups as required
        $toolsHelper = new ToolsHelper;
        Factory::getApplication()->enqueueMessage("Deleting linked records for " . $id, 'info');
    }

}
