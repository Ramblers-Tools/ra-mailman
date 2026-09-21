<?php

/**
 * @version    4.5.3
 * @package    com_ra_mailman
 * @author     Charlie Bigley <webmaster@bigley.me.uk>
 * @copyright  2023 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 01/01/24 CB use ContentHelper->getActions
 * 17/02/25 CB set up $this->user from getCurrentUser
 * 20/03/25 CB Return to Dashboard
 * 27/08/25 CB Help
 * 17/09/26 CB correct back
 */

namespace Ramblers\Component\Ra_mailman\Administrator\View\Profiles;

// No direct access
defined('_JEXEC') or die;

use \Joomla\CMS\Factory;
use Joomla\CMS\Helper\ContentHelper;
use \Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use \Joomla\CMS\Toolbar\Toolbar;
use \Joomla\CMS\Toolbar\ToolbarHelper;
use \Joomla\CMS\Language\Text;
use \Joomla\Component\Content\Administrator\Extension\ContentComponent;
use \Joomla\CMS\Form\Form;
use \Joomla\CMS\HTML\Helpers\Sidebar;
use \Joomla\CMS\User\CurrentUserInterface;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

/**
 * View class for a list of Profiles.
 *
 * @since  4.0.0
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

//        $this->sidebar = Sidebar::render();
        parent::display($tpl);
    }

    /**
     * Add the page title and toolbar.
     *
     * @return  void
     *
     * @since   4.0.0
     */
    protected function addToolbar() {
        // Suppress menu side panel
        Factory::getApplication()->input->set('hidemainmenu', true);
        // Set sidebar action
        Sidebar::setAction('index.php?option=com_ra_mailman&view=profiles');

        $state = $this->get('State');
        $canDo = ContentHelper::getActions('com_ra_mailman');

        ToolbarHelper::title(Text::_('Mailman Users'), "generic");

        $toolbar = Toolbar::getInstance('toolbar');

        // Check if the form exists before showing the add/edit buttons
        $formPath = JPATH_COMPONENT_ADMINISTRATOR . '/src/View/Profiles';

        if (file_exists($formPath)) {
            if ($canDo->get('core.create')) {
                $toolbar->addNew('profile.add');
            }
        }
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
          $childBar->publish('profiles.publish')->listCheck(true);
          $childBar->unpublish('profiles.unpublish')->listCheck(true);
          $childBar->archive('profiles.archive')->listCheck(true);
          } elseif (isset($this->items[0])) {
          // If this component does not use state then show a direct delete button as we can not trash
          $toolbar->delete('profiles.delete')
          ->text('JTOOLBAR_EMPTY_TRASH')
          ->message('JGLOBAL_CONFIRM_DELETE')
          ->listCheck(true);
          }

          $childBar->standardButton('duplicate')
          ->text('JTOOLBAR_DUPLICATE')
          ->icon('fas fa-copy')
          ->task('profiles.duplicate')
          ->listCheck(true);

          if (isset($this->items[0]->checked_out)) {
          $childBar->checkin('profiles.checkin')->listCheck(true);
          }

          if (isset($this->items[0]->state)) {
          $childBar->trash('profiles.trash')->listCheck(true);
          }
         *
         */
        $toolbar->standardButton('nrecords')
                ->icon('fa fa-info-circle')
                ->text(number_format($this->pagination->total) . ' Records')
                ->task('')
                ->onclick('return false')
                ->listCheck(false);

        // 17/06/23 - does not work
        /*
          $toolbar->standardButton('back')
          ->icon('fa fa-info-circle')
          ->text('Back')
          ->task('profiles.back')
          ->onclick('return true')
          ->listCheck(false);

          }
         */


        // Show trash and delete for components that uses the state field
        if (isset($this->items[0]->state)) {

            if ($this->state->get('filter.state') == ContentComponent::CONDITION_TRASHED && $canDo->get('core.delete')) {
                $toolbar->delete('profiles.delete')
                        ->text('JTOOLBAR_EMPTY_TRASH')
                        ->message('JGLOBAL_CONFIRM_DELETE')
                        ->listCheck(true);
            }
        }

        ToolbarHelper::cancel('profiles.cancel', 'Return to Dashboard');
        $help_url = 'https://docs.stokeandnewcastleramblers.org.uk/mail-manager.html?view=article&id=441:mm-02-5-users&catid=34';
        ToolbarHelper::help('', false, $help_url);
    }

    /**
     * Method to order fields
     *
     * @return void
     */
    protected function getSortFields() {
        return array(
            'a.`acknowledge_follow`' => Text::_('COM_RA_PROFILE_PROFILES_ACKNOWLEDGE_FOLLOW'),
            'a.`groups_to_follow`' => Text::_('COM_RA_PROFILE_PROFILES_GROUPS_TO_FOLLOW'),
            'a.`home_group`' => Text::_('COM_RA_PROFILE_PROFILES_HOME_GROUP'),
            'a.`preferred_name`' => Text::_('COM_RA_PROFILE_PROFILES_preferred_NAME'),
            'a.`privacy_level`' => Text::_('COM_RA_PROFILE_PROFILES_PRIVACY_LEVEL'),
            'a.`min_miles`' => Text::_('COM_RA_PROFILE_PROFILES_MIN_MILES'),
            'a.`max_miles`' => Text::_('COM_RA_PROFILE_PROFILES_MAX_MILES'),
            'a.`max_radius`' => Text::_('COM_RA_PROFILE_PROFILES_MAX_RADIUS'),
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
