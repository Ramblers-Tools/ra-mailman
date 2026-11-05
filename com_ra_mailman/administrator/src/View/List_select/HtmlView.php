<?php

/**
 * 08/04/24 CB set up $this->group_code
 * 29/10/24 CB show message if password reset required
 * 03/09/25 CB don't use back button
 * 05/11/26 CB Delete references to SidebarHelper for J6 compatibility
 */

namespace Ramblers\Component\Ra_mailman\Administrator\View\List_select;

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
 * View class for a list of List_select.
 *
 * @since  1.0.3
 */
class HtmlView extends BaseHtmlView implements CurrentUserInterface {

    protected $items;
    protected $pagination;
    protected $state;
//
    protected $user_name;
    protected $objHelper;
    protected $user_id;
    protected $group_code;
    protected $user;

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
        $app = Factory::getApplication();
        // Load the component params
        $this->params = ComponentHelper::getParams('com_ra_mailman');
//      get the input parameters
        $this->user = $this->getCurrentUser();
        $this->user_id = $app->input->getInt('user_id', '0');

        // Save these variable to the session's userState for use by the model
        $app->setUserState('user_id', $this->user_id);

        $this->objHelper = new ToolsHelper;
        $sql = 'SELECT p.home_group, p.preferred_name, u.requireReset  ';
        $sql .= 'FROM #__ra_profiles AS p ';
        $sql .= 'INNER JOIN #__users AS u ON u.id = p.id ';
        $sql .= 'WHERE p.id=' . $this->user_id;
        $row = $this->objHelper->getItem($sql);
        if (is_null($row)) {
            throw new \Exception('Can\'t find User', 404);
        }
        $this->group_code = $row->home_group;
        $this->user_name = $row->home_group . ' ' . $row->preferred_name;
        if ($row->requireReset == 1) {
            Factory::getApplication()->enqueueMessage('This User requires a Password reset', 'warning');
        }
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
        $title = 'Select Lists';

        ToolbarHelper::title(Text::_($title . ' for ' . $this->user_name));
        /*
         * These buttons render correctly but do not work, possible because of
         * missing / incorrect Javascript
          //       ToolbarHelper::cancel('subscription.cancel', 'Cancel', true);

          ToolbarHelper::cancel('list_select.cancel', 'JTOOLBAR_CANCEL');
          ToolbarHelper::custom('subscriptions.cancel2', 'process.png', 'process_f2.png', 'Test button', false);
         */


        $state = $this->get('State');
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
