<?php

/**
 * @version    4.5.8
 * @package    com_ra_mailman
 * @author     Charlie Bigley <webmaster@bigley.me.uk>
 * @copyright  2023 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 01/01/24 CB use ContentHelper->getActions
 * 13/02/25 CB set up $this->user from getCurrentUser
 * 14/11/25 CB warning if checked out
 */

namespace Ramblers\Component\Ra_mailman\Administrator\View\Mailshot;

// No direct access
defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use \Joomla\CMS\Component\ComponentHelper;
use \Joomla\CMS\Toolbar\Toolbar;
use \Joomla\CMS\Toolbar\ToolbarHelper;
use \Joomla\CMS\Factory;
use Joomla\CMS\Helper\ContentHelper;
use \Joomla\CMS\User\CurrentUserInterface;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

/**
 * View class for a single Mailshot.
 *
 * @since  1.0.2
 */
class HtmlView extends BaseHtmlView implements CurrentUserInterface {

    protected $state;
    protected $item;
    protected $form;
    protected $objApp;
    protected $objHelper;
    protected $id;
    protected $date_sent;
    protected $list_id;  // Passed as parameter,set up by the model
    protected $list_name;
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
        // Load the component params
        $this->component_params = ComponentHelper::getParams('com_ramblers');

        $this->state = $this->get('State');
        $this->item = $this->get('Item');
        $this->form = $this->get('Form');

        // Check for errors.
        if (count($errors = $this->get('Errors'))) {
            throw new \Exception(implode("\n", $errors));
        }

        $app = Factory::getApplication();
        $this->user = $this->getCurrentUser();
        $this->objHelper = new ToolsHelper;

        // Get the id of the Mailshot
        $this->id = $app->input->getInt('id', '0');

        // Get the  id of the Mailing list, passed as part of the URL
        $this->list_id = $app->input->getInt('list_id', '0');
        if ($this->list_id == 0) {
            Factory::getApplication()->enqueueMessage('this->list_name is Zero', 'message');
        } else {
            $sql = 'SELECT group_code, name from `#__ra_mail_lists` WHERE id=' . $this->list_id;
            $row = $this->objHelper->getItem($sql);
            $this->list_name = $row->group_code . ' ' . $row->name;
        }

        // Get a direct reference to the model, and pass the list_id to it
        $model = $this->getModel();
        $model->setId($this->id);
        $model->setList($this->list_id);
        $sql = 'SELECT date_sent from #__ra_mail_shots WHERE id=' . $this->id;
        $this->date_sent = $this->objHelper->getValue($sql);
        if (($this->date_sent == '0000-00-00') or ($this->date_sent == '')) {
            $model->setReadonly(False);
        } else {
            $model->setReadonly(True);
        }

        $this->addToolbar();
        parent::display($tpl);
    }

    /**
     * Add the page title and toolbar.
     *
     * @return void
     *
     * @throws Exception
     */
    protected function addToolbar() {
        Factory::getApplication()->input->set('hidemainmenu', true);

        $isNew = ($this->item->id == 0);

        if (isset($this->item->checked_out)) {
            $checkedOut = !($this->item->checked_out == 0 || $this->item->checked_out == $this->user->get('id'));
            $user_name = $this->objHelper->lookupUser($this->item->checked_out);
            Factory::getApplication()->enqueueMessage('This item is currently checked out by ' . $user_name, 'warning');
        } else {
            $checkedOut = false;
        }

        $canDo = ContentHelper::getActions('com_ra_mailman');

        if ($this->id == 0) {
            $title = 'New';
        } else {
            $title = 'Editing';
        }
        $title .= ' Mailshot for ' . $this->list_name;
        ToolbarHelper::title($title);

        // If not checked out, can save the item.
        if (!$checkedOut && ($canDo->get('core.edit') || ($canDo->get('core.create')))) {
            ToolbarHelper::apply('mailshot.apply', 'JTOOLBAR_APPLY');
            ToolbarHelper::save('mailshot.save', 'JTOOLBAR_SAVE');
            Toolbar::getInstance('toolbar')
                    ->standardButton('testsend')
                    ->text('Send Test')
                    ->task('mailshot.testsend')
                    ->icon('icon-envelope')
                    ->formValidation(true);
        }

        if (empty($this->item->id)) {
            ToolbarHelper::cancel('mailshot.cancel', 'JTOOLBAR_CANCEL');
        } else {
            ToolbarHelper::cancel('mailshot.cancel', 'JTOOLBAR_CLOSE');
        }
    }

}
