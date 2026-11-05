<?php

/**
 * 17/07/25 CB regenerated, adjust buttons, use ContentHelp and CurrentUserInterface
 * 25/08/25 CB Help
 * 05/11/26 CB Delete references to SidebarHelper for J6 compatibility
 */

namespace Ramblers\Component\Ra_mailman\Administrator\View\Subscriptions;

// No direct access
defined('_JEXEC') or die;

use \Joomla\Component\Content\Administrator\Extension\ContentComponent;
use Joomla\CMS\Helper\ContentHelper;
use \Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use \Joomla\CMS\Toolbar\Toolbar;
use \Joomla\CMS\Toolbar\ToolbarHelper;
use \Joomla\CMS\Language\Text;
use \Joomla\CMS\Form\Form;
use \Joomla\CMS\User\CurrentUserInterface;

/**
 * View class for a list of Subscriptions.
 *
 * @since  4.4.11
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
        $this->user = $this->getCurrentUser();
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
     * @since   4.4.11
     */
    protected function addToolbar() {
        $state = $this->get('State');
        $canDo = ContentHelper::getActions('com_ra_mailman');

        ToolbarHelper::title(Text::_('Mail Subscriptions'), "generic");

        $toolbar = Toolbar::getInstance('toolbar');

        if ($canDo->get('core.edit.state')) {
            $dropdown = $toolbar->dropdownButton('status-group')
                    ->text('JTOOLBAR_CHANGE_STATUS')
                    ->toggleSplit(false)
                    ->icon('fas fa-ellipsis-h')
                    ->buttonClass('btn btn-action')
                    ->listCheck(true);

            $childBar = $dropdown->getChildToolbar();

            if (isset($this->items[0]->state)) {
                $childBar->publish('subscriptions.publish', 'Subscribe')->listCheck(true);
                $childBar->unpublish('subscriptions.unpublish', 'Unsubscribe')->listCheck(true);
//                $childBar->archive('subscriptions.archive')->listCheck(true);
//            } elseif (isset($this->items[0])) {
//                // If this component does not use state then show a direct delete button as we can not trash
//                $toolbar->delete('subscriptions.delete')
//                        ->text('JTOOLBAR_EMPTY_TRASH')
//                        ->message('JGLOBAL_CONFIRM_DELETE')
//                        ->listCheck(true);
//            }


                if (isset($this->items[0]->checked_out)) {
                    $childBar->checkin('subscriptions.checkin')->listCheck(true);
                }

//            if (isset($this->items[0]->state)) {
//                $childBar->trash('subscriptions.trash')->listCheck(true);
//            }
            }



            // Show trash and delete for components that uses the state field
//        if (isset($this->items[0]->state)) {
//            if ($this->state->get('filter.state') == ContentComponent::CONDITION_TRASHED && $canDo->get('core.delete')) {
//                $toolbar->delete('subscriptions.delete')
//                        ->text('JTOOLBAR_EMPTY_TRASH')
//                        ->message('JGLOBAL_CONFIRM_DELETE')
//                        ->listCheck(true);
//            }
        }
        $toolbar->standardButton('nrecords')
                ->icon('fa fa-info-circle')
                ->text(number_format($this->pagination->total) . ' Records')
                ->task('')
                ->onclick('return false')
                ->listCheck(false);
        ToolbarHelper::cancel('subscriptions.cancel', 'Return to Dashboard');
        $help_url = 'https://docs.stokeandnewcastleramblers.org.uk/mail-manager.html?view=article&id=432:mm-02-4-subscriptions&catid=34';
        ToolbarHelper::help('', false, $help_url);
    }

    /**
     * Method to order fields
     *
     * @return void
     */
    protected function getSortFields() {
        return array(
            'a.`id`' => Text::_('JGRID_HEADING_ID'),
            'a.`state`' => Text::_('JSTATUS'),
            'a.`user_id`' => Text::_('COM_RA_MAILMAN_SUBSCRIPTIONS_USER_ID'),
            'a.`list_id`' => Text::_('COM_RA_MAILMAN_SUBSCRIPTIONS_LIST_ID'),
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
