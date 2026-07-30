<?php

/**
 * @version    4.5.3
 * @package    com_ra_mailman
 * @author     Charlie Bigley <webmaster@bigley.me.uk>
 * @copyright  2023 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 01/01/23 CB set up canDo for
 * 01/01/24 CB use ContentHelper->getActions
 * 29/01/24 CB delete button
 * 04/10/24 CB use getIdentity instead of getUser
 * 13/02/25 CB replace getIdentity with $this->getCurrentUser
 * 18/02/25 CB always show Cancel button
 * 20/03/25 CB Return to Dashboard
 * 06/-8/25 CB warning if email logging is active
 * 11/08/25 CB new mechanism for send
 * 25/08/25 CB Help
 */

namespace Ramblers\Component\Ra_mailman\Administrator\View\Mail_lsts;

// No direct access
defined('_JEXEC') or die;

use \Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use \Joomla\CMS\Component\ComponentHelper;
use \Joomla\CMS\Factory;
use Joomla\CMS\Helper\ContentHelper;
use \Joomla\CMS\Toolbar\Toolbar;
use \Joomla\CMS\Toolbar\ToolbarHelper;
use \Joomla\CMS\Language\Text;
use \Joomla\CMS\Form\Form;
use \Joomla\CMS\HTML\Helpers\Sidebar;
use \Joomla\CMS\Uri\Uri;
use \Joomla\CMS\User\CurrentUserInterface;
use \Joomla\Component\Content\Administrator\Extension\ContentComponent;
use Ramblers\Component\Ra_mailman\Site\Helpers\Mailhelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

/**
 * View class for a list of Mail_lists.
 *
 * @since  1.0.6
 */
class HtmlView extends BaseHtmlView implements CurrentUserInterface {

    protected $canDo;
    protected $items;
    protected $mailshot_send_message;
    protected $max_online_send;
    public $mailHelper;
    public $toosHelper;
    protected $pagination;
    protected $state;
    protected $user;
    protected $user_id;

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
        $layout = Factory::getApplication()->input->getWord('layout', '');
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
        $this->sidebar = Sidebar::render();
        $params = ComponentHelper::getParams('com_ra_tools');
        $email_log_level = $params->get('email_log_level', '0');
        // Log level -2 is solely to benchmark the overhead of sending via SMTP
        if ($email_log_level < 0) {
            Factory::getApplication()->enqueueMessage('Email logging is in Benchmark mode: ' . $email_log_level, 'warning');
        }
        $params = ComponentHelper::getParams('com_ra_mailman');
        $this->max_online_send = $params->get('max_online_send', 100);
        $this->mailshot_send_message = $params->get('mailshot_send_message', 'Waiting for batch job');
        $this->mailHelper = new MailHelper;
        $this->toolsHelper = new ToolsHelper;
        parent::display($tpl);
    }

    /**
     * Add the page title and toolbar.
     *
     * @return  void
     *
     * @since   1.0.6
     */
    protected function addToolbar() {
        $app = Factory::getApplication();
// Suppress menu side panel
        $app->input->set('hidemainmenu', true);
// Set sidebar action
        Sidebar::setAction('index.php?option=com_ra_mailman&view=mail_lsts');
        $state = $this->get('State');
        $this->canDo = ContentHelper::getActions('com_ra_mailman');
        // find which layout is in use

        $layout = $app->input->getCmd('layout', 'new');
        if ($layout == 'statistics') {
            if (!$this->canDo->get('core.delete')) {
                throw new \Exception('Access denied', 404);
            }
            ToolbarHelper::title(Text::_('Delete Mailing list'), "generic");
        } else {
            ToolbarHelper::title(Text::_('Mailing lists'), "generic");

            $toolbar = Toolbar::getInstance('toolbar');

// Check if the form exists before showing the add/edit buttons
            $formPath = JPATH_COMPONENT_ADMINISTRATOR . '/src/View/Mail_lsts';

            if (file_exists($formPath)) {
                if ($this->canDo->get('core.create')) {
                    $toolbar->addNew('mail_lst.add');
                }
            } else {
                if (JDEBUG) {
                    echo 'Unable to find ' . $formpath . '<br>';
                }
            }

            if ($this->canDo->get('core.edit.state')) {
                $dropdown = $toolbar->dropdownButton('status-group')
                        ->text('JTOOLBAR_CHANGE_STATUS')
                        ->toggleSplit(false)
                        ->icon('fas fa-ellipsis-h')
                        ->buttonClass('btn btn-action')
                        ->listCheck(true);

                $childBar = $dropdown->getChildToolbar();

                //          if (isset($this->items[0]->state)) {
                //              $childBar->publish('mail_lst.publish')->listCheck(true);
                //              $childBar->unpublish('mail_lst.unpublish')->listCheck(true);
//                $childBar->edit('mail_lst.edit')->listCheck(true);
//            } elseif (isset($this->items[0])) {
// If this component does not use state then show a direct delete button as we can not trash}
            }
            $toolbar->delete('mail_lst.delete')
                    ->text('JTOOLBAR_DELETE')
                    ->message('JGLOBAL_CONFIRM_DELETE')
                    ->listCheck(true);
//        }
            $toolbar->standardButton('nrecords')
                    ->icon('fa fa-info-circle')
                    ->text(number_format($this->pagination->total) . ' Records')
                    ->task('')
                    ->onclick('return false')
                    ->listCheck(false);
        }
        ToolbarHelper::cancel('mail_lsts.cancel', 'Return to Dashboard');
        $help_url = 'https://docs.stokeandnewcastleramblers.org.uk/mail-manager.html?view=article&id=420:mm-02-2-mailing-lists&catid=34';
        ToolbarHelper::help('', false, $help_url);
    }

    /**
     * Method to order fields
     *
     * @return void
     */
    protected function getSortFields() {
        return array(
            'a.`description`' => Text::_('COM_RA_MAILMAN_MAIL_LSTS_DESCRIPTION'),
            'a.`group_code`' => Text::_('COM_RA_MAILMAN_MAIL_LSTS_GROUP_CODE'),
            'a.`name`' => Text::_('COM_RA_MAILMAN_MAIL_LSTS_NAME'),
            'a.`home_group_only`' => Text::_('COM_RA_MAILMAN_MAIL_LSTS_HOME_GROUP_ONLY'),
            'a.`chat_list`' => Text::_('COM_RA_MAILMAN_MAIL_LSTS_CHAT_LIST'),
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

    function defineActions($list_id, $list_type, $unsent_mailshot, $mailshot_id) {
        /*
         * invoked from the template to set up the required action buttons for the last column of the report
         * 01/01/24 CB this seems to be unused - code copied from site view?
         */

        if ($this->user_id == 0) {
            return '';
        }

        if ($this->mailHelper->isAuthor($list_id)) {
            $target = 'index.php?option=com_ra_mailman&task=mailshot.edit';
            if ($unsent_mailshot == true) {
                $caption = 'Edit';
                $target .= '&id=' . $mailshot_id;
            } else {
                $caption = 'Create';
            }
// this will over-ride any options already set
            return $this->toolsHelper->buildLink($target . '&list_id=' . $list_id, $caption, False, "link-button button-p0159");
        }

// See if User is already subscribed
        $sql = 'SELECT id, state FROM #__ra_mail_subscriptions WHERE user_id=' . $this->user_id;
        $sql .= ' AND list_id=' . $list_id;
        $subscription = $this->toolsHelper->getItem($sql);
        if ($list_type == 'Open') {
            if (is_null($subscription)) {
                $target = 'index.php?option=com_ra_mailman&task=mail_lst.subscribe';
                $caption = 'Subscribe';
                $colour = '0583';  // light green
            } else {
//                echo "View: state $subscription->state<br>";
                if ($subscription->state == 0) {
                    $target = 'index.php?option=com_ra_mailman&task=mail_lst.subscribe';
                    $caption = 'Re-subscribe';
                    $colour = '0555';  // dark green
                }
            }
        }
// For unsubscribing, we don't care if the list is open or closed
        if (!is_null($subscription)) {
            if ($subscription->state > 0) {
                $target = 'index.php?option=com_ra_mailman&task=mail_lst.unsubscribe&list_id=';
                $caption = 'Un-subscribe';
                $colour = '0186'; // red
                return $this->toolsHelper->buildLink($target . $list_id . '&user_id=' . $this->user_id, $caption, False, "link-button button-p" . $colour);
            }
        }
        if ($list_type == 'Open') {
            if (is_null($subscription)) {
                $target = 'index.php?option=com_ra_mailman&task=mail_lst.subscribe';
                $caption = 'Subscribe';
                $colour = '0583';  // light green
            } else {
                if ($subscription->state == 0) {
                    $target = 'index.php?option=com_ra_mailman&task=mail_lst.subscribe';
                    $caption = 'Re-subscribe';
                    $colour = '0555';  // dark green
                }
            }
            return $this->toolsHelper->buildLink($target . '&list_id=' . $list_id . '&user_id=' . $this->user_id, $caption, False, "link-button button-p" . $colour);
        } else {
            return '';
        }
    }

    protected function sendButton($last_mailshot, $count_subscribers, $canEdit, $isAuthor) {
        //      print_r($last_mailshot);
        //      echo '<br>';
        $target_send = 'administrator/index.php?option=com_ra_mailman&task=mailshot.send&menu_id=' . $this->menu_id . '&mailshot_id=';
        if (($last_mailshot->id > 0) AND is_null($last_mailshot->date_sent)) {
            if (($canEdit) OR ($isAuthor)) {
                if ($last_mailshot->attachment != '') {
                    $target = 'images/com_ra_mailman/' . $last_mailshot->attachment;
                    $label = '<span class="icon-paperclip"></span>';
                    echo $this->toolsHelper->buildLink($target, $label, True);
                }
                $target = $target_send . $last_mailshot->id . '&total=' . $count_subscribers;
//                                           $target = 'administrator/index.php?option=com_ra_mailman&task=mailshot.send&mailshot_id='  . '&menu_id=' . $this->menu_id;
                if (is_null($last_mailshot->processing_started)) {
                    $label = 'Send';
                } else {
                    $count = $this->mailHelper->countSubscribersOutstanding($last_mailshot->id);
                    $label = 'Resend to ' . $count;
                }
                return $this->toolsHelper->buildButton($target, $label, False);
            }
        }
    }

    protected function scheduleButton($last_mailshot, $count_subscribers, $canEdit, $isAuthor, $label = 'Schedule') {
        if (($last_mailshot->id > 0) AND is_null($last_mailshot->date_sent) AND is_null($last_mailshot->processing_started) AND (($canEdit) OR ($isAuthor))) {
            $html = '<button type="button" class="link-button button-p0159" ';
            $html .= 'onclick="raMailmanOpenSchedule(' . (int) $last_mailshot->id . ', ' . (int) $count_subscribers . ', \'' . rtrim(Uri::root(), '/') . '/administrator/index.php\', \'' . addslashes((string) $last_mailshot->send_after) . '\')">';
            $html .= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</button>';
            return $html;
        }
    }

    protected function discardButton($last_mailshot) {
        $target = 'administrator/index.php?option=com_ra_mailman&task=mailshot.discard&mailshot_id=' . $last_mailshot->id;
        $html = '<a class="link-button button-p0186" href="/' . $target . '" ';
        $html .= 'onclick="return confirm(\'Discard this mailshot? All its data will be permanently lost.\');">';
        $html .= 'Discard</a>';
        return $html;
    }

    protected function cancelButton($last_mailshot, $canEdit, $isAuthor) {
        if (($canEdit) OR ($isAuthor)) {
            $target = 'administrator/index.php?option=com_ra_mailman&task=mailshot.cancelSending&mailshot_id=' . $last_mailshot->id . '&return=mail_lsts';
            return $this->toolsHelper->buildButton($target, 'Cancel sending', False, 'rosycheeks');
        }
    }

}
