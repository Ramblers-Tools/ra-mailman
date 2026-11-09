<?php

/**
 * 08/08/23 CB if home_group_only, include message in title
 * 07/09/23 CB as button with number of records
 * 09/03/25 CB direct "subscribe" to user_select Controller, not Subscriptions
 * 25/08/25 CB Help
 * 12/02/26 CB change page title
 * 05/11/26 CB Delete references to SidebarHelper for J6 compatibility
 */

namespace Ramblers\Component\Ra_mailman\Administrator\View\User_select;

// No direct access
defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use \Joomla\CMS\Factory;
use \Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use \Joomla\CMS\Toolbar\Toolbar;
use \Joomla\CMS\Toolbar\ToolbarHelper;
use \Joomla\CMS\Language\Text;
use \Joomla\Component\Content\Administrator\Extension\ContentComponent;
use \Joomla\CMS\Form\Form;
use \Joomla\CMS\User\CurrentUserInterface;
use \Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

/**
 * View class for a list of User_select.
 *
 * @since  1.0.3
 */
class HtmlView extends BaseHtmlView implements CurrentUserInterface {

    protected $items;
    protected $pagination;
    protected $state;
    protected $user;
//
    protected $list_id;
    protected $list_name;
    protected $home_group_only;
    protected $group_code;
    protected $objHelper;
    protected $record_type;

    /**
     * Display the view
     *
     * @param   string  $tpl  Template name
     *
     * @return void
     *
     * @throws Exception
     */
    public function display($tpl = null) {
        // Only invoked from mail_lists
        // The specific list_code will have been passed as parameter
        // record_type = 1 is for an ordinary subscribers, 2 = Author
        // Load the component params
        $app = Factory::getApplication();
        $this->user = $this->getCurrentUser();
        $this->params = ComponentHelper::getParams('com_ra_mailman');

//      get the input parameters
        $this->list_id = $app->input->getInt('list_id', '1');
        $this->record_type = $app->input->getInt('record_type', '1');

        // Save these variable to the session's userState for use by the model
        $app->setUserState('com_ra_mailman.user_select.user_id', $this->list_id);
        $app->setUserState('com_ra_mailman.user_select.record_type', $this->record_type);

        // Lookup names for List and Group
        $this->objHelper = new ToolsHelper;
        $sql = 'SELECT group_code, name, home_group_only FROM `#__ra_mail_lists` WHERE id=' . $this->list_id;
        $list = $this->objHelper->getItem($sql);
        $this->group_code = $list->group_code;
        $this->list_name = $list->name;
        $this->home_group_only = $list->home_group_only;

        $this->state = $this->get('State');
        $this->items = $this->get('Items');
        $this->pagination = $this->get('Pagination');
        $this->filterForm = $this->get('FilterForm');
        $this->activeFilters = $this->get('ActiveFilters');

        // Check for errors.
        if (count($errors = $this->get('Errors'))) {
            throw new \Exception(implode("\n", $errors));
        }

        $this->addToolbar();

        parent::display($tpl);
    }

    /**
     * Add the page title and toolbar.
     *
     * @return  void
     *
     * @since   1.0.3
     */
    protected function addToolbar() {
        // Suppress menu side panel
        Factory::getApplication()->input->set('hidemainmenu', true);
        $title = 'Manage ';
        if ($this->record_type == 1) {
            $title .= 'Subscriptions';
        } else {
            $title .= 'Authorship rights';
        }
        $title .= ' for ' . $this->group_code . ' ' . $this->list_name;
        if ($this->home_group_only == 1) {
            $title .= ' (home group only)';
        }
        ToolbarHelper::title($title);
        ToolbarHelper::publish('user_select.subscribe', 'Subscribe', true);
        $toolbar = Toolbar::getInstance('toolbar');
        $toolbar->standardButton('nrecords')
                ->icon('fa fa-info-circle')
                ->text(number_format($this->pagination->total) . ' Records')
                ->task('')
                ->onclick('return false')
                ->listCheck(false);
        ToolbarHelper::cancel('user_select.cancel', 'Cancel');
        $help_url = 'https://docs.stokeandnewcastleramblers.org.uk/mail-manager.html?view=article&id=425:mm-02-2-2-authors&catid=34';
        ToolbarHelper::help('', false, $help_url);
    }

    /**
     * Check if state is set
     *
     * @param   mixed  $state  State
     *
     * @return bool
     */
    public function getState($state) {
        return isset($this->state->{$state}) ? $this->state->{$state} : false;
    }

}
