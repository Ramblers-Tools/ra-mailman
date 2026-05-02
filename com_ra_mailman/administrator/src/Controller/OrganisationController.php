<?php

/**
 * @version    4.7.0
 * @package    com_ra_mailman
 * @author     Charlie Bigley <webmaster@bigley.me.uk>
 * @copyright  2024 Charlie Bigley
 * @license    GNU General Public License version 3 or later; see LICENSE.txt
 * 21/02/25 CB created
 * 22/02/25 CB
 * 08/04/26 Claude Refactored from com_ra_tools
 * 20/04/26 drilldown by date
 */

namespace Ramblers\Component\Ra_mailman\Administrator\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\Input\Input;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsTable;

/**
 * Organisation controller class.
 *
 * @since  3.0.0
 */
class OrganisationController extends FormController {

    protected $back;
    protected $callback;
    protected $toolsHelper;
    protected $view_item = 'organisation';
    protected $view_list = 'organisations';

    public function __construct(
        $config = [],
        MVCFactoryInterface $factory = null,
        CMSApplication $app = null,
        Input $input = null
    ) {
        parent::__construct($config, $factory, $app, $input);

        $this->toolsHelper = new ToolsHelper;
        $this->back = '/administrator/index.php?option=com_ra_mailman&view=organisations';
        $this->callback = $app->getUserState('com_ra_mailman.reports.callback');
        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
        $wa->registerAndUseStyle('ramblers', 'com_ra_mailman/ramblers.css');
    }

    public function cancel($key=null){
        // return to the calling page, which may be the dashboard or the list of organisations
        if ($this->callback == 'dashboard') {
             $this->setRedirect('/administrator/index.php?option=com_ra_tools&view=dashboard');
        } else {
            $this->setRedirect($this->back);
        }
    }

    public function configure(){
        // Invoked from the dashboard, allows a user to update the configuration files for their Group or Area
        
        // Get the code parameter
        $code = Factory::getApplication()->input->getCmd('code', '');
        
        if (empty($code)) {
            Factory::getApplication()->enqueueMessage('Code parameter is required', 'error');
            $this->setRedirect('index.php?option=com_ra_tools&view=dashboard');
            return;
        }
        
        // Look up the record ID by code
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select('id')
            ->from('#__ra_organisations')
            ->where('code = ' . $db->quote($code));
        
        $db->setQuery($query);
        $id = $db->loadResult();
        
        if (empty($id)) {
            Factory::getApplication()->enqueueMessage('Organisation with code ' . htmlspecialchars($code) . ' not found', 'error');
            $this->setRedirect('index.php?option=com_ra_tools&view=dashboard');
            return;
        }
        
        // Redirect to edit view with return URL pointing to dashboard
        $return = base64_encode('index.php?option=com_ra_tools&view=dashboard');
        $this->setRedirect('index.php?option=com_ra_mailman&view=organisation&layout=edit&id=' . $id . '&return=' . $return);
    }

    /**
     * Method to get a model object, loading it if required.
     *
     * @param   string  $name    The model name. Optional.
     * @param   string  $prefix  The class prefix. Optional.
     * @param   array   $config  Configuration array for model. Optional.
     *
     * @return  object  The model.
     *
     * @since   3.0.0
     */
    public function getModel($name = 'Organisation', $prefix = 'Administrator', $config = array('ignore_request' => true)) {
        // Some mis-wirings/toolbars end up requesting the plural model name; normalise it.
        if ($name === '' || strcasecmp($name, 'Organisations') === 0)
        {
            $name = 'Organisation';
        }

        $model = parent::getModel($name, $prefix, $config);

        // Defensive fallback: avoid returning false which causes FormController::save() to fatal.
        if ($model === false && strcasecmp($name, 'Organisation') !== 0)
        {
            $model = parent::getModel('Organisation', $prefix, $config);
        }

        return $model;
    }

    public function save($key = null, $urlVar = null) {
        // save the record, then return to the calling page, which may be the dashboard or the list of organisations
        $result = parent::save();
        if ($result) {
            if ($this->callback == 'dashboard') {
                $this->setRedirect('/administrator/index.php?option=com_ra_tools&view=dashboard');
            } else {
                $this->setRedirect($this->back);
            }
        }
    }   

    public function showMembers(){
        $code = Factory::getApplication()->input->getCmd('code', '');
        $sql = 'SELECT name from #__ra_organisations ';
        $sql .= 'WHERE code ="' . $code . '"';
        $area = $this->toolsHelper->getValue($sql);
        ToolBarHelper::title('Members in Organisation ' . $area);
        $sql = 'SELECT * from #__ra_profiles ';
        if (strlen($code) == 2  ){
            // this is an area, so match home_group like code%
            $sql .= 'WHERE home_group like"' . $code . '%" ';
        } else {
            // this is an area, so match home_group like code%
            $sql .= 'WHERE home_group ="' . $code . '" ';
        }
        $sql .= 'ORDER BY lastName, firstName ';
//echo $sql;
        $table = new ToolsTable;
        $table->add_header("Mem No,Preferred name,Home group,Join date,Expiry date,Member type,Member term,Status,Volunteer");
        $rows = $this->toolsHelper->getRows($sql);
        foreach ($rows as $row) {
            $table->add_item($row->membershipNumber);
            $table->add_item($row->preferred_name);
            $table->add_item($row->home_group);
            $table->add_item(HTMLHelper::_('date', $row->ramblersJoinDate, 'd M y'));
            $table->add_item(HTMLHelper::_('date', $row->membershipExpiryDate, 'd M y'));    
            $table->add_item($row->memberType);
            $table->add_item($row->memberTerm);
            $table->add_item($row->memberStatus);
            $table->add_item($row->volunteer);
            $table->generate_line();
        }
        $table->generate_table();

        echo $this->toolsHelper->backButton($this->back);   
    }
    public function showOrganisation() {
        $code = Factory::getApplication()->input->getCmd('code', 'NS');
        $this->toolsHelper = new ToolsHelper;
        $sql = "SELECT * FROM #__ra_organisations  ";
        $sql .= "WHERE code = '" . $code . "'";
//            echo $sql;
        $area = $this->toolsHelper->getItem($sql);
        ToolBarHelper::title($area->name);
        if ($area->record_type == 'A') {
            $sql = 'SELECT name from #__ra_nations ';
            $sql .= 'WHERE id ="' . $area->nation_id . '"';
            $nation = $this->toolsHelper->getValue($sql);
            echo 'Nation <b>' . $nation . '</b><br>';
            echo 'Cluster <b>' . $area->cluster . '</b><br>';
        }
        echo 'Code <b>' . $area->code . '</b><br>';
        
        echo 'Name <b>' . $area->name . '</b><br>';
        echo 'Details <b>' . $area->details . '</b><br>';
        echo 'Website <b>' . $area->website . '</b><br>';
        echo 'Head office site <b>' . $area->co_url . '</b><br>';
        echo 'Latitude <b>' . $area->latitude . '</b><br>';
        echo 'Longitude <b>' . $area->longitude . '</b><br>';
        //       echo 'Website <b>' . $area->website . '</b><br>';
        echo $this->toolsHelper->backButton($this->back);
    }

    public function showGroups() {
        $code = Factory::getApplication()->input->getCmd('area', '');
        $sql = 'SELECT name from #__ra_organisations ';
        $sql .= 'WHERE code ="' . $code . '"';
        $area = $this->toolsHelper->getValue($sql);
        ToolBarHelper::title('Groups in Organisation ' . $area);
        $sql = 'SELECT * from #__ra_groups ';
        $sql .= 'WHERE code like"' . $code . '%"';
        $sql .= 'ORDER BY name ';

        $table = new ToolsTable;
        $table->add_header("Code,Name,Website,CO link,Location");
        $rows = $this->toolsHelper->getRows($sql);
        foreach ($rows as $row) {
            $table->add_item($row->code);
            $table->add_item($row->name);
            if ($row->website == '') {
                $table->add_item('');
            } else {
                $table->add_item($this->toolsHelper->buildLink($row->website, $row->website, true));
            }
            if ($row->co_url == '') {
                $table->add_item('');
            } else {
                $table->add_item($this->toolsHelper->buildLink($row->co_url, $row->co_url, true));
            }
            $map_pin = $this->toolsHelper->showLocation($row->latitude, $row->longitude, 'O');
            $table->add_item($map_pin);
            $table->generate_line();
        }
        $table->generate_table();

        echo $this->toolsHelper->backButton($this->back);
    }

    public function convert1(){
         $db = Factory::getContainer()->get('DatabaseDriver');
        $sql = 'SELECT * FROM j5_ra_members ' ;
//        $sql .= 'LIMIT 4';
        $db->setQuery($sql);
        $rows = $this->toolsHelper->getRows($sql);
        $count = 0;
        foreach ($rows as $row){
            $count++;
            $ref = 'SF' . str_pad($count, 3, '0', STR_PAD_LEFT);
            $membershipNumber = random_int(100000, 999999);
            $part1 = $this->firstWord($row->firstName);
            $part2 = $this->firstWord($row->lastName);
            $email = strtolower($part1. '.' .  $part2 . '@protonmail.com');

            $sql = 'UPDATE j5_ra_members ';
//            $sql .= 'SET salesforceId=' . $db->quote($ref) . ', ';
//            $sql .= 'membershipNumber=' . $db->quote($membershipNumber) . ', ';
            $sql .= 'SET email=' . $db->quote($email) . ' ';
            $sql .= 'WHERE Original =' . $db->quote($row->Original);

            echo $sql . '<br>';
            $this->toolsHelper->executeCommand($sql);

        }
        echo 'Total records processed: ' . $count . '<br>';
    }

    private function firstWord($token){
        // returns a since word if two are given
        $space = strpos($token,' ');
//        echo 'space found at ' . $space . '<br>';
        if ($space == 0){
            return $token;
        }
        // check for O Reilly
        if (($space == 1) AND (substr($token,0,1) == 'O')) {
            return "O'" . substr($token,2);
        }        
        return substr($token,0,$space);
        }

    private function formatDateForSql($value, $db) {
        if (empty($value)) {
            return 'NULL';
        }

        $value = trim((string) $value);

        if ($value === '') {
            return 'NULL';
        }

        $formats = ['d/m/y', 'd/m/Y', 'Y-m-d', 'd-m-y', 'd-m-Y'];

        foreach ($formats as $format) {
            $date = \DateTime::createFromFormat('!' . $format, $value);

            if ($date !== false) {
                return $db->quote($date->format('Y-m-d'));
            }
        }

        return 'NULL';
    }

    public function test(){
        // http://127.0.0.0/administrator/index.php?option=com_ra_mailman&task=organisation.test
        $db = Factory::getContainer()->get('DatabaseDriver');
        $sql = 'SELECT * FROM j5_ra_members ' ;
//        $sql .= 'LIMIT 4';
        $db->setQuery($sql);
        $rows = $this->toolsHelper->getRows($sql);
        $count = 0;
        foreach ($rows as $row){
            $count++;
            $sql = 'SELECT u.id FROM #__users AS u ';
            $sql .= 'INNER JOIN #__ra_profiles AS p ON p.id = u.id ';
            $sql .= 'WHERE u.email = ' . $db->quote($row->email);
            $id = $this->toolsHelper->getValue($sql);
            if ($id){   
                $membershipExpiryDate = $this->formatDateForSql($row->membershipExpiryDate, $db);
                $ramblersJoinDate = $this->formatDateForSql($row->ramblersJoinDate, $db);
                $areaJoinedDate = $this->formatDateForSql($row->areaJoinedDate, $db);
                $groupJoinedDate = $this->formatDateForSql($row->groupJoinedDate, $db);
                $emailPermissionLastUpdated = $this->formatDateForSql($row->emailPermissionLastUpdated, $db);
                $postPermissionLastUpdated = $this->formatDateForSql($row->postPermissionLastUpdated, $db);
                $telephonePermissionLastUpdated = $this->formatDateForSql($row->telephonePermissionLastUpdated, $db);

                $sql = 'UPDATE #__ra_profiles ';
                $sql .= 'SET salesforceId=' . $db->quote($row->salesforceId) . ', ';
                $sql .= 'home_group=' . $db->quote($row->groupCode) . ', ';
                $sql .= 'groupName=' . $db->quote($row->groupName) . ', ';
                $sql .= 'membershipNumber=' . $db->quote($row->membershipNumber) . ', ';
                $sql .= 'memberType=' . $db->quote($row->memberType) . ', ';
                $sql .= 'memberTerm=' . $db->quote($row->memberTerm) . ', ';
                $sql .= 'memberStatus=' . $db->quote($row->memberStatus) . ', ';
                $sql .= 'type=' . $db->quote($row->type) . ', ';
                $sql .= 'jointWith=' . $db->quote($row->jointWith) . ', ';
                $sql .= 'title=' . $db->quote($row->title) . ', ';
                $sql .= 'initials=' . $db->quote($row->initials) . ', ';
                $sql .= 'firstName=' . $db->quote($row->firstName) . ', ';
                $sql .= 'lastName=' . $db->quote($row->lastName) . ', ';
                $preferredName = $this->firstWord($row->firstName) . ' ' . $this->firstWord($row->lastName);
                $sql .= 'preferred_name=' . $db->quote($preferredName) . ', ';
                $sql .= 'address1=' . $db->quote($row->address1) . ', ';
                $sql .= 'address2=' . $db->quote($row->address2) . ', ';
                $sql .= 'address3=' . $db->quote($row->address3) . ', ';
                $sql .= 'town=' . $db->quote($row->town) . ', ';
                $sql .= 'county=' . $db->quote($row->county) . ', ';
                $sql .= 'country=' . $db->quote($row->country) . ', ';
                $sql .= 'postCode=' . $db->quote($row->postCode) . ', ';                
                $sql .= 'email=' . $db->quote($row->email) . ', ';            
                $sql .= 'landlineTelephone=' . $db->quote($row->landlineTelephone) . ', ';
                $sql .= 'mobileNumber=' . $db->quote($row->mobileNumber) . ', ';
                $sql .= 'membershipExpiryDate=' . $membershipExpiryDate . ', ';
                $sql .= 'ramblersJoinDate=' . $ramblersJoinDate . ', ';
                $sql .= 'areaName=' . $db->quote($row->areaName) . ', ';
                $sql .= 'areaJoinedDate=' . $areaJoinedDate . ', ';           
                $sql .= 'groupJoinedDate=' . $groupJoinedDate . ', ';                
                $sql .= 'volunteer=' . $db->quote($row->volunteer) . ', ';                
                $sql .= 'emailMarketingConsent=' . $db->quote($row->emailMarketingConsent) . ', ';
                $sql .= 'emailPermissionLastUpdated=' . $emailPermissionLastUpdated . ', ';
                $sql .= 'postDirectMarketing=' . $db->quote($row->postDirectMarketing) . ', ';           
                $sql .= 'postPermissionLastUpdated=' . $postPermissionLastUpdated . ', ';                
                $sql .= 'telephoneDirectMarketing=' . $db->quote($row->telephoneDirectMarketing) . ', ';                
                $sql .= 'telephonePermissionLastUpdated=' . $telephonePermissionLastUpdated . ', ';
                $sql .= 'walkProgrammeOptOut=' . $db->quote($row->walkProgrammeOptOut) . ', ';
                $sql .= 'affiliateMemberPrimaryGroup=' . $db->quote($row->affiliateMemberPrimaryGroup) . ' ';
                $sql .= 'WHERE id=' . $id; 
//                echo $sql . '<br>';
//                die;
                $this->toolsHelper->executeCommand($sql);
            }    
        }
        echo 'Total records processed: ' . $count . '<br>';
    }

}
