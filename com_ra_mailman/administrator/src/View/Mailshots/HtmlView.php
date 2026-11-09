<?php

/**
 * 01/01/24 CB use ContentHelper->getActions
 * 30/01/24 CB if showing mailshots for a single list, return to Mailshots view, not dashboard
 * 13/02/25 CB set up $this->user from getCurrentUser
 * 20/03/25 CB Return to Dashboard
 * 25/08/25 CB Help
 * 05/11/26 CB Delete references to SidebarHelper for J6 compatibility
 */

namespace Ramblers\Component\Ra_mailman\Administrator\View\Mailshots;

// No direct access
defined('_JEXEC') or die;

use \Joomla\CMS\Factory;
use \Joomla\CMS\Helper\ContentHelper;
use \Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use \Joomla\CMS\Toolbar\Toolbar;
use \Joomla\CMS\Toolbar\ToolbarHelper;
use \Joomla\CMS\Language\Text;
use \Joomla\Component\Content\Administrator\Extension\ContentComponent;
use \Joomla\CMS\Form\Form;
use \Joomla\CMS\User\CurrentUserInterface;
use \Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

/**
 * View class for a list of Mailshots.
 *
 * @since  1.0.2
 */
class HtmlView extends BaseHtmlView implements CurrentUserInterface {

    protected $items;
    protected $pagination;
    protected $state;
    public $list_id;
    protected $user;
    public $list_name;
    public $group_code;
    public $objHelper;

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
//      If invoked from list a Mailing List, only mailshot for given list are required
//      get the input parameters
        $app = Factory::getApplication();
        $this->list_id = $app->input->getInt('list_id', '0');
        // Lookup names for List and Group
        $this->objHelper = new ToolsHelper;
        if ($this->list_id > 0) {
            $sql = 'SELECT group_code, name FROM `#__ra_mail_lists` WHERE id=' . $this->list_id;
            $list = $this->objHelper->getItem($sql);
            $this->group_code = $list->group_code;
            $this->list_name = $list->name;
        }

        $this->user = $this->getCurrentUser();
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
     * @since   1.0.2
     */
    protected function addToolbar() {
        // Suppress menu side panel
        Factory::getApplication()->input->set('hidemainmenu', true);

        $state = $this->get('State');
        $canDo = ContentHelper::getActions('com_ra_mailman');

        $title = 'Mailshots';
        if ($this->list_id > 0) {
            $title .= ' for ' . $this->group_code . ' ' . $this->list_name;
        }
        ToolbarHelper::title($title);
        $toolbar = Toolbar::getInstance('toolbar');

        $toolbar->standardButton('nrecords')
                ->icon('fa fa-info-circle')
                ->text(number_format($this->pagination->total) . ' Records')
                ->task('')
                ->onclick('return false')
                ->listCheck(false);
        if ($this->list_id > 0) {
            ToolbarHelper::cancel('mailshots.cancel2', 'JTOOLBAR_CANCEL');
        } else {
            ToolbarHelper::cancel('mailshots.cancel', 'Return to Dashboard');
        }
        $help_url = 'https://docs.stokeandnewcastleramblers.org.uk/mail-manager.html?view=article&id=427:mm-02-3-mailshots&catid=34';
        ToolbarHelper::help('', false, $help_url);
    }

    /**
     * Method to order fields
     *
     * @return void
     */
    protected function getSortFields() {
        return array(
            'a.`id`' => 'id',
            'a.`date_sent`' => 'Date sent',
            'a.`title`' => 'Title',
            'mail_list.`name`' => 'Mail list',
            'a.`modified`' => 'Last updated',
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
