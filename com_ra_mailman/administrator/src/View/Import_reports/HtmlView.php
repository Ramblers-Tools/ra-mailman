<?php

/**
 * 02/06/25 CB Created
 * 05/11/26 CB Delete referernces to SidebarHelper for J6 compatibility
 */

namespace Ramblers\Component\Ra_mailman\Administrator\View\Import_reports;

// No direct access
defined('_JEXEC') or die;

use \Joomla\CMS\Factory;
use Joomla\CMS\Helper\ContentHelper;
use \Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use \Joomla\CMS\Toolbar\Toolbar;
use \Joomla\CMS\Toolbar\ToolbarHelper;
use \Joomla\CMS\Language\Text;
use \Joomla\CMS\User\CurrentUserInterface;
use \Joomla\Component\Content\Administrator\Extension\ContentComponent;
use \Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;
use \Ramblers\Component\Ra_mailman\Site\Helpers\MailHelper;

/**
 * View class for a list of Input_reports.
 *
 * @since  1.0.4
 */
class HtmlView extends BaseHtmlView implements CurrentUserInterface {

    protected $items;
    protected $pagination;
    protected $state;
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
        $this->state = $this->get('State');
        $this->items = $this->get('Items');
        $this->pagination = $this->get('Pagination');
        $this->filterForm = $this->get('FilterForm');
        $this->activeFilters = $this->get('ActiveFilters');

        // Check for errors.
        if (count($errors = $this->get('Errors'))) {
            throw new \Exception(implode("\n", $errors));
        }
        $this->user = $this->getCurrentUser();
        $this->addToolbar();

        parent::display($tpl);
    }

    /**
     * Add the page title and toolbar.
     *
     * @return  void
     *
     * @since   1.0.4
     */
    protected function addToolbar() {
        // Suppress menu side panel
        Factory::getApplication()->input->set('hidemainmenu', true);
        $state = $this->get('State');
        $canDo = ContentHelper::getActions('com_ra_mailman');

        ToolbarHelper::title(Text::_('Import reports'), "generic");

        $toolbar = Toolbar::getInstance('toolbar');
        /*
          if ($canDo->get('core.edit.state')) {
          $dropdown = $toolbar->dropdownButton('status-group')
          ->text('JTOOLBAR_CHANGE_STATUS')
          ->toggleSplit(false)
          ->icon('fas fa-ellipsis-h')
          ->buttonClass('btn btn-action')
          ->listCheck(true);

          $childBar = $dropdown->getChildToolbar();

          if (isset($this->items[0]->state)) {
          $childBar->publish('import_reports.publish')->listCheck(true);
          $childBar->unpublish('import_reports.unpublish')->listCheck(true);
          }
          }
         *
         */
        $toolbar->standardButton('nrecords')
                ->icon('fa fa-info-circle')
                ->text(number_format($this->pagination->total) . ' Records')
                ->task('')
                ->onclick('return false')
                ->listCheck(false);
        ToolbarHelper::cancel('import_reports.cancel', 'Return to Dashboard');
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
