<?php

/**
 * 13/02/25 CB Replace getIdentity with $this->getCurrentUser();
 * 18/02/25 CB define $this->objHelper
 * 07/07/25 CB set up $this->group_code
 * 05/11/26 CB Delete referernces to SidebarHelper for J6 compatibility
 */

namespace Ramblers\Component\Ra_mailman\Site\View\List_select;

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
    protected $user_name;
    protected $objHelper;
    protected $user_id;
    protected $group_code;

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
        // In the front end, only invoked after a Manager has created a new user
        // The user_id will have been passed as parameter

        $app = Factory::getApplication();

        // Check the user is logged in, and is in the MailMan group
        $this->user = $this->getCurrentUser();
        if ($this->user->id == 0) {
            //return Error::raiseWarning(404, "Please login to gain access to this function");
            throw new \Exception('Please login to gain access to this function', 404);
        }
        $this->objHelper = new ToolsHelper;
        // Load the component params
        $this->params = ComponentHelper::getParams('com_ra_mailman');
//      get the input parameters
        $this->user_id = $app->input->getInt('user_id', '0');

        // Save these variable to the session's userState for use by the model
        $app->setUserState('user_id', $this->user_id);

        $sql = 'SELECT home_group, preferred_name FROM #__ra_profiles WHERE id=' . $this->user_id;
        $row = $this->objHelper->getItem($sql);
        if (is_null($row)) {
            throw new \Exception('Can\'t find User', 404);
        }
        $this->group_code = $row->home_group;
        $this->user_name = $row->home_group . ' ' . $row->preferred_name;
//        echo '<h2>Selecting subscriptions for ' . $this->user_name . '</h2>';
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
        $title = 'Select ';

        $title .= 'Lists';

        ToolbarHelper::title(Text::_($title . ' for ' . $this->user_name));
        //       ToolbarHelper::cancel('subscription.cancel', 'Cancel', true);
        ToolbarHelper::publish('subscriptions.subscribe', 'Subscribe', true);
        ToolbarHelper::unpublish('subscriptions.unsubscribe', 'Unsubscribe', true);
        ToolbarHelper::cancel('user_select.cancel', 'JTOOLBAR_CANCEL');
        $state = $this->get('State');
    }

    /**
     * Method to order fields
     *
     * @return void
     */
    protected function getSortFieldsxx() {
        return array(
            'a.`id`' => Text::_('JGRID_HEADING_ID'),
            'a.`state`' => Text::_('JSTATUS'),
            'a.`ordering`' => Text::_('JGRID_HEADING_ORDERING'),
            'a.`organiser`' => Text::_('COM_RA_MAILMAN_USER_SELECT_ORGANISER'),
            'a.`event_date`' => Text::_('COM_RA_MAILMAN_USER_SELECT_EVENT_DATE'),
            'a.`description`' => Text::_('COM_RA_MAILMAN_USER_SELECT_DESCRIPTION'),
            'a.`group_code`' => Text::_('COM_RA_MAILMAN_USER_SELECT_GROUP_CODE'),
            'a.`location`' => Text::_('COM_RA_MAILMAN_USER_SELECT_LOCATION'),
            'a.`event_time`' => Text::_('COM_RA_MAILMAN_USER_SELECT_EVENT_TIME'),
        );
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
