<?php

/**
 * @version    4.1.10
 * @package    com_ra_mailman
 * @author     Charlie Bigley <webmaster@bigley.me.uk>
 * @copyright  2023 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 *
 * This view is invoked from DataloadController after an input file has been uploaded.
 * It can be run in two modes, firstly producing a report on the proposed actions,
 * and if confirmation is given, it invokes itself again with a different mode to make the database changes.
 *
 * Processing is invoked by the LoadHelper adapter from the Process template.
 * 14/07/25 CB support for template check
 */

namespace Ramblers\Component\Ra_mailman\Administrator\View\Process;

// No direct access
defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use \Joomla\CMS\Factory;
use \Joomla\CMS\User\CurrentUserInterface;
use Joomla\Database\DatabaseInterface;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

/**
 * This is invoked after an upload file has been specified
 *
 * @author charlie
 */
class HtmlView extends BaseHtmlView implements CurrentUserInterface {

    protected $date;
    protected $file_name;
    protected $list_id;
    protected $method_id;
    protected $processing;
    protected $user;
    protected $report_id;
    protected $toolsHelper;
    protected $working_file;

    public function display($tpl = null) {
        $data = Factory::getApplication()->getUserState('com_ra_mailman.edit.upload.data', array());
        $uploadPath = JPATH_ROOT . '/images/com_ra_mailman/';
        $this->user = $this->getCurrentUser();
        $this->method_id = $data['data_type'];
        $this->processing = $data['processing'];
        $this->list_id = $data['mail_list'];
        $this->file_name = $data['file'];
        $this->working_file = $uploadPath . $this->file_name;
        $this->date = Factory::getDate('now', Factory::getConfig()->get('offset'))->toSql(true);
        $this->toolsHelper = new ToolsHelper;
        if ($this->processing == '0') {
            $this->createReport();
        } else {
            $this->findReport();
        }
        parent::display($tpl);
    }

    public function createReport() {
        $ip_address = $_SERVER['REMOTE_ADDR'];
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true);
        $query->insert('#__ra_import_reports')
                ->set("date_phase1 = " . $db->quote($this->date))
                ->set("method_id = " . $db->quote($this->method_id))
                ->set("input_file = " . $db->quote($this->file_name))
                ->set("list_id = " . $db->quote($this->list_id))
                ->set("user_id = " . $db->quote($this->user->id))
                ->set("ip_address = " . $db->quote($ip_address))
                ->set("state=0")
        ;
        $query->insert('#__ra_import_reports');
        $result = $db->setQuery($query)->execute();
        $sql = 'SELECT MAX(id) FROM #__ra_import_reports';
        $this->report_id = $this->toolsHelper->getValue($sql);
//        echo 'Created ' . $this->report_id . '<br>';
    }

    public function findReport() {
        $sql = 'SELECT MAX(id) FROM #__ra_import_reports';
        $this->report_id = $this->toolsHelper->getValue($sql);
    }

}
