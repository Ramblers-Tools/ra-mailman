<?php

/**
 * Abortive attempt 5 May 2025 to cater for illegal files names
 * Validation seems OK, but file with the incorrect name seems to get uploaded anyway
 * Possibly could rename the file in folder com_ra_mailman
 *
 *
 * @version    4.5.7
 * @package    com_ra_mailman
 * @author     Charlie Bigley <webmaster@bigley.me.uk>
 * @copyright  2023 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 *
 * This model can be invoked more than once for the same file.
 * The first time, the file details are taken from the input->files array and
 * stored in the form->data. If processing is aborted, for example because the
 * wrong input parameters were given, the file details are taken from the form data.
 *
 * 18/10/23 CB take files from images/com_ra_mailman
 * 18/09/24 CB add function validate
 * 25/09/24 CB code copied from com_ra_tools / Model / UploadModel
 * 08/10/24 CB use view process for the actual processing
 * 10/10/24 CB upload the file in function save
 * 12/02/25 CB replace getIdentity with getCurrentUser
 * 14/04/25 CB delete any existing copy of the upload file
 * 02/05/25 CN remove diagnostic
 * 04/05/25 CB cater for update of file name on upload
 * 18/10/25 CB allow text/comma-separated-values
 */

namespace Ramblers\Component\Ra_mailman\Administrator\Model;

// No direct access.
defined('_JEXEC') or die;

use \Joomla\CMS\Table\Table;
use \Joomla\CMS\Factory;
use \Joomla\CMS\Filesystem\File;
use \Joomla\CMS\Helper\TagsHelper;
use \Joomla\CMS\Language\Text;
use \Joomla\CMS\Plugin\PluginHelper;
use \Joomla\CMS\MVC\Model\AdminModel;
use \Joomla\CMS\Object\CMSObject;
use \Joomla\Utilities\ArrayHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

/**
 * Mail_lst model.
 *
 * @since  1.0.6
 */
class DataloadModel extends AdminModel {

    /**
     * @var    string  The prefix to use with controller messages.
     *
     * @since  1.0.6
     */
    protected $text_prefix = 'RA Mailman';
    protected $csv_file;
    protected $tmp_name;

    /**
     * @var    string  Alias to manage history control
     *
     * @since  1.0.6
     */
    public $typeAlias = 'com_ra_mailman.dataload';
    private $item = null;

    /**
     * Method to auto-populate the model state.
     *
     * Note. Calling getState in this method will result in recursion.
     *
     * @return  void
     *
     * @since   1.0.4
     *
     * @throws  Exception
     */
    protected function populateState() {
        $app = Factory::getApplication('com_ra_mailman');

        // Load state from the request userState on edit or from the passed variable on default
        if (Factory::getApplication()->input->get('layout') == 'edit') {
            $id = Factory::getApplication()->getUserState('com_ra_mailman.edit.upload.id');
        } else {
            $id = Factory::getApplication()->input->get('id');
            Factory::getApplication()->setUserState('com_ra_mailman.edit.upload.id', $id);
        }
        return true; ///////////////////////

        $this->setState('upload.id', $id);

        // Load the parameters.
        $params = $app->getParams();
        $params_array = $params->toArray();

        if (isset($params_array['item_id'])) {
            $this->setState('upload.id', $params_array['item_id']);
        }

        $this->setState('params', $params);
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
        return $this->item;
    }

    /**
     * Method to get the data form.
     *
     * The base form is loaded from XML
     *
     * @param   array   $data     An optional array of data for the form to interogate.
     * @param   boolean $loadData True if the form is to load its own data (default case), false if not.
     *
     * @return  Form    A Form object on success, false on failure
     *
     * @since   1.0.4
     */
    public function getForm($data = array(), $loadData = true) {
        // Get the form.
        $form = $this->loadForm('com_ra_mailman.upload', 'dataload', array(
            'control' => 'jform',
            'load_data' => $loadData
                )
        );

        if (empty($form)) {
            return false;
        }
// if a file has been selected, show its name
        $data = Factory::getApplication()->getUserState('com_ra_mailman.edit.upload.data', array());
        $file = $data['file'];
        if ($file != '') {
            $form->removeField('csv_file');
            $form->setFieldAttribute('csv_file', 'hidden', "true");
            $form->setFieldAttribute('file', 'type', "textfield");
        }
        return $form;
    }

    /**
     * Method to get the data that should be injected in the form.
     *
     * @return  array  The default data is an empty array.
     * @since   1.0.4
     */
    protected function loadFormData() {
        $data = Factory::getApplication()->getUserState('com_ra_mailman.edit.upload.data', array());
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
     * @since   1.0.4
     */
    public function save($data) {
        // The file details will have been set up in the function validate.
        $app = Factory::getApplication();
        $user = $this->getCurrentUser();

        // Check the user can create new items in this section
        $authorised = $user->authorise('core.create', 'com_ra_mailman');

        if ($authorised !== true) {
            throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }


        $files = $app->input->files->get('jform', array(), 'raw');

        $file_array = $files['csv_file'];
        if (is_null($file_array)) {
            // We have already validated and uploaded the file
            echo '<br> file_array is null<br>';
        } else {
            // This is the first time this function has been invoked
            //           var_dump($file_array);
            //           die;
            $csv_file = $file_array['name'];
            $tmp_name = $file_array['tmp_name'];
            $delete = true;
        }
//        $app->enqueueMessage('DataloadModel/Save: file is  ' . $csv_file, 'info');
        jimport('joomla.filesystem.file');
        $filename = File::stripExt($csv_file);
        $extension = File::getExt($csv_file);
        $filename = $filename . '.' . $extension;
        $fileTemp = $tmp_name;
        $upload_file = JPATH_ROOT . '/images/com_ra_mailman/' . $filename;
        if ($delete == true) {
            if (File::exists($upload_file)) {
                $message = 'File ' . $filename . ' already present in /images/com_ra_mailman/';
                //            $app->enqueueMessage($message, 'info');
                if (file_exists($upload_file) && !is_dir($upload_file)) {
                    unlink($upload_file);
                }
            }
            if (!File::upload($fileTemp, $upload_file)) {
                $app->enqueueMessage('Model: Error moving ' . $fileTemp . ' to ' . $filename, 'warning');
                return false;
            } else {

                $app->enqueueMessage($message . ' uploaded to ' . $data['file'], 'info');
            }
        }

//        $app->enqueueMessage('DataloadModel: returning TRUE from save', 'info');
        return true;
    }

    public function validate($form, $data, $group = true) {
        $app = Factory::getApplication();

        $MIMETypes = 'text/plain,text/csv,text/comma-separated-values';

        $array = $app->input->get('jform', array(), 'ARRAY');

        $files = $app->input->files->get('jform', array(), 'raw');
        $file_array = $files['csv_file'];
        $csv_file = $file_array['name'];

        if ($array['file'] != '') {
            // File has already been validated
            $this->csv_file = $array['file'];
            $this->tmp_file = $array['tmp_file'];
            return $data;
        } else {
            // Replace any special characters in the filename
            jimport('joomla.filesystem.file');
            $filename = File::stripExt($csv_file);
            $extension = File::getExt($csv_file);
            $filename = preg_replace("/[^A-Za-z0-9]/i", "-", $filename);
            $filename = $filename . '.' . $extension;
            $fileTemp = $tmp_name;
            if ($filename !== $csv_file) {
                $message = 'File ' . $file_array['name'] . ' contains invalid characters, please rename to ' . $filename . ',';
                $app->enqueueMessage($message, 'info');
                return false;
            }
        }
        $singleFile = $files['csv_file'];
        if ($singleFile['size'] == 0) {
            $app->enqueueMessage('Please select a file', 'error');
            return false;
        }

//        jimport('joomla.filesystem.file');
        // Check if the server found any error.
        $fileError = $singleFile['error'];
        $message = '';

        if ($fileError > 0 && $fileError != 4) {
            switch ($fileError) {
                case 1:
                    $message = Text::_('File size exceeds allowed by the server');
                    break;
                case 2:
                    $message = Text::_('File size exceeds allowed by the html form');
                    break;
                case 3:
                    $message = Text::_('Partial upload error');
                    break;
            }

            if ($message != '') {
                $app->enqueueMessage($message, 'warning');
                return false;
            }
        } elseif ($fileError == 4) {
            if (isset($array['csv_file'])) {
                $this->csv_file = $array['csv_file'];
            }
        } else {
            // Check for filetype
            $MIMETypes = 'text/plain,text/csv,text/comma-separated-values';
            $validMIMEArray = explode(',', $MIMETypes);
            $fileMime = $singleFile['type'];

            if (!in_array($fileMime, $validMIMEArray)) {
                $app->enqueueMessage('Filetype <b>' . $fileMime . '</b> is not allowed (must be ' . $MIMETypes . ')', 'warning');

                return false;
            }
        }
        $data['file'] = $singleFile['name'];
        $data['tmp_name'] = $singleFile['tmp_name'];
        $this->csv_file = $data['file'];
        $this->tmp_name = $data['tmp_name'];
        return $data;
    }

}
