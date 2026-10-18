<?php

/**
 * @version    4.3.4
 * @package    Ra_tools
 * @author     Charlie Bigley <webmaster@bigley.me.uk>
 * @copyright  2024 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 25/09/24 Created from com_ra_tools UploadTable
 * 04/05/25 CB probably no longer needed - die  statement added
 */

namespace Ramblers\Component\Ra_mailman\Administrator\Table;

// No direct access
defined('_JEXEC') or die;

use \Joomla\CMS\Access\Access;
use \Joomla\CMS\Factory;
use \Joomla\CMS\Filesystem\File;
// use \Joomla\CMS\Filter\OutputFilter;
use \Joomla\CMS\Helper\ContentHelper;
use \Joomla\CMS\Language\Text;
use \Joomla\CMS\Table\Table as Table;
use \Joomla\CMS\Versioning\VersionableTableInterface;
use Joomla\CMS\Tag\TaggableTableInterface;
use Joomla\CMS\Tag\TaggableTableTrait;
use \Joomla\Database\DatabaseDriver;
use \Joomla\Registry\Registry;
use \Joomla\Utilities\ArrayHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

/**
 * Upload table
 *
 * @since 1.0.4
 */
class DataloadTable extends Table implements VersionableTableInterface, TaggableTableInterface {

    use TaggableTableTrait;

    /**
     * Constructor
     *
     * @param   JDatabase  &$db  A database connector object
     */
    public function __construct(DatabaseDriver $db) {
        echo 1 / 0;
        $this->typeAlias = 'com_ra_mailman.upload';
        // Don't access the database, but must give a valid database table
        parent::__construct('#__ra_areas', 'id', $db);
    }

    /**
     * Get the type alias for the history table
     *
     * @return  string  The alias as described above
     *
     * @since   1.0.4
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
     * @since   1.0.4
     * @throws  \InvalidArgumentException
     */
    public function bind($array, $ignore = '') {
        $input = Factory::getApplication()->input;

        // Support for multi file field: file_name
        if (!empty($array['file_name'])) {
            if (is_array($array['file_name'])) {
                $array['file_name'] = implode(',', $array['file_name']);
            } elseif (strpos($array['file_name'], ',') != false) {
                $array['file_name'] = explode(',', $array['file_name']);
            }
        } else {
            $array['file_name'] = '';
        }

        return parent::bind($array, $ignore);
    }

    /**
     *
     * @param   boolean  $updateNulls  True to update fields even if they are null.
     *
     * @return  boolean  True on success.
     *
     * @since   1.0.4
     */
    public function store($updateNulls = true) {
        echo 'Table store<br>';
        die('Table store');
        return true;
    }

    /**
     * Overloaded check function
     *
     * @return bool
     */
    public function check() {
        die('Table check');
        return true;
    }

}
