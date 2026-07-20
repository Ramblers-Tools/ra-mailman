<?php

/**
 * @version    4.4.5
 * @package    com_ra_mailman
 * @author     Charlie Bigley <webmaster@bigley.me.uk>
 * @copyright  2023 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 02/06/25 CB Created
 */

namespace Ramblers\Component\Ra_mailman\Administrator\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\SiteApplication;
use \Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Multilanguage;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\AdminController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\Utilities\ArrayHelper;
use Ramblers\Component\Ra_mailman\Site\Helpers\Mailhelper;
use Ramblers\Component\Ra_mailman\Site\Helpers\SubscriptionHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsTable;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

/**
 *
 * @since  1.0.4
 */
class Import_reportsController extends AdminController {

    protected $view_item = 'dataload';
// Ensure control returns to Dashboard, not import_reports
    protected $view_list = 'dashboard';
    protected $back = 'administrator/index.php?option=com_ra_mailman&view=import_reports';
    protected $db;
    protected $app;
    protected $toolsHelper;

    function __construct($config = array(), \Joomla\CMS\MVC\Factory\MVCFactoryInterface $factory = null) {
        parent::__construct($config, $factory);
        $this->db = Factory::getDbo();
        $this->toolsHelper = new ToolsHelper;
        $this->app = Factory::getApplication();
        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
        $wa->registerAndUseStyle('ramblers', 'com_ra_tools/ramblers.css');
    }

    public function cancel($key = null, $urlVar = null) {
        $this->setRedirect('index.php?option=com_ra_tools&view=dashboard');
    }

    public function cancel2($key = null, $urlVar = null) {
        // temp test from button on list_select
        die('cancel2');
        $this->setRedirect('index.php?option=com_ra_tools&view=dashboard');
    }

    /**
     * Proxy for getModel.
     *
     * @param   string  $name    Optional. Model name
     * @param   string  $prefix  Optional. Class prefix
     * @param   array   $config  Optional. Configuration array for model
     *
     * @return  object	The Model
     *
     * @since   1.0.4
     */
    public function getModel($name = 'Import_reports', $prefix = 'Administrator', $config = array()) {
        return parent::getModel($name, $prefix, array('ignore_request' => true));
    }

    public function showDetails($id) {
        $sql = 'SELECT r.date_phase1, r.date_completed,r.input_file, ';
        $sql .= 'r.num_records, r.num_errors, r.num_users, r.num_subs, r.num_lapsed, ';
        $sql .= 'l.name, m.name AS `Method` ';
        $sql .= 'FROM `#__ra_import_reports` AS r ';
        $sql .= 'INNER JOIN #__ra_mail_lists as l ON l.id = r.list_id ';
        $sql .= 'INNER JOIN #__ra_mail_methods AS `m` ON m.id = r.method_id ';
        $sql .= 'WHERE r.id= ' . $id;
//        $target = 'administrator/index.php?option = com_users&view = users ';
        $item = $this->toolsHelper->getItem($sql);
        echo '<b>Report</b>: ' . $id . '<br>';
        echo '<b>List</b>: ' . $item->name . '<br>';
        if (is_null($item->date_completed)) {
            echo '<div style="color:red"> <b>Date 1</b>: ' . HTMLHelper::_('date', $item->date_phase1, 'H:i d/m/y') . ' Validation only!</div>';
        } else {
            echo '<b>Date started</b>: ' . HTMLHelper::_('date', $item->date_phase1, 'H:i:s d/m/y');
            echo ', <b>Date completed</b>: ' . HTMLHelper::_('date', $item->date_completed, 'H:i:s d/m/y');
            echo '<br>';
        }
        echo '<b>Method</b>: ' . $item->Method . '<br>';
        echo '<b>File</b>: ' . $item->input_file . '<br>';
        echo '<b>Number of records</b>: ' . $item->num_records . '<br>';
        echo '<b>Number of errors</b>: ' . $item->num_errors . '<br>';
        echo '<b>Number of new users</b>: ' . $item->num_users . '<br>';
        echo '<b>Number of new subscriptions</b>: ' . $item->num_subs . '<br>';
        if ($item->num_lapsed > 0) {
            echo '<b>Number of members lapsed</b>: ' . $item->num_lapsed . '<br>';
        }
        echo '<br>';
    }

    public function showErrors() {
        $id = $this->app->input->getInt('id', '0');
        ToolBarHelper::title('Import Report');
        $this->showDetails($id);
        $sql = 'SELECT error_report FROM `#__ra_import_reports` ';
        $sql .= ' WHERE id= ' . $id;
        echo '<h4>Validation errors</h4>';
        echo $this->toolsHelper->getValue($sql);
//       echo '<br>';
        echo $this->toolsHelper->backButton($this->back);
    }

    public function showFile() {
        $objTable = new ToolsTable;
        $id = $this->app->input->getInt('id', '0');
        $working_folder = '../images/com_ra_mailman/';
        ToolBarHelper::title('Input file');
        $this->showDetails($id);

        $sql = 'SELECT input_file FROM `#__ra_import_reports` ';
        $sql .= ' WHERE id= ' . $id;
        $file = $this->toolsHelper->getValue($sql);
        echo "<h4>$file</h4>";
        $target = $working_folder . $file;
        if (!file_exists($target)) {
            $text = 'File ' . $target . ' does not exist';
            // Add a message to the message queue
            Factory::getApplication()->enqueueMessage($text, 'error');
            return;
        }
        $objTable->show_csv($target);
        echo $this->toolsHelper->backButton($this->back);
    }

    public function showLapsed() {
        $id = $this->app->input->getInt('id', '0');
        ToolBarHelper::title('Import report');
        $this->showDetails($id);
        $sql = 'SELECT lapsed_members FROM `#__ra_import_reports` ';
        $sql .= ' WHERE id= ' . $id;
        echo '<h4>Lapsed members</h4>';
        echo $this->toolsHelper->getValue($sql);
        echo '<br>';
        echo $this->toolsHelper->backButton($this->back);
    }

    public function showSubs() {
        $id = $this->app->input->getInt('id', '0');
        ToolBarHelper::title('Import report');
        $this->showDetails($id);
        $sql = 'SELECT new_subs FROM `#__ra_import_reports` ';
        $sql .= ' WHERE id= ' . $id;
        echo '<h4>New subscriptions</h4>';
        echo $this->toolsHelper->getValue($sql);
        echo '<br>';
        echo $this->toolsHelper->backButton($this->back);
    }

    public function showSummary() {
        $id = $this->app->input->getInt('id', '0');
        ToolBarHelper::title('Import report');
        $this->showDetails($id);
        echo $this->toolsHelper->backButton($this->back);
    }

    public function showUsers() {
        $id = $this->app->input->getInt('id', '0');
        ToolBarHelper::title('Import report');
        $this->showDetails($id);
        echo '<h4>New users</h4>';
        $sql = 'SELECT new_users FROM `#__ra_import_reports` ';
        $sql .= ' WHERE id= ' . $id;
        echo $this->toolsHelper->getValue($sql);
        echo '<br>';
        echo $this->toolsHelper->backButton($this->back);
    }

}
