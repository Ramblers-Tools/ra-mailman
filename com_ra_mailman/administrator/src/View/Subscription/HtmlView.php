<?php

/**
 * Administrator view for manually creating a subscription.
 * 08/11/26 CB Created
 */

namespace Ramblers\Component\Ra_mailman\Administrator\View\Subscription;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;


class HtmlView extends BaseHtmlView {
    protected $db;
    protected $form;
    protected $filterSearch;
    protected $toolsHelper;

    public function display($tpl = null) {
        $this->toolsHelper = new ToolsHelper;
        $this->db =Factory::getContainer()->get('DatabaseDriver');
        $this->form = $this->get('Form');
        $this->filterSearch = $this->get('FilterSearch');

        if (count($errors = $this->get('Errors'))) {
            throw new \RuntimeException(implode("\n", $errors));
        }

        if (!$this->form) {
            throw new \RuntimeException('Unable to load the subscription form.');
        }

        if (!ContentHelper::getActions('com_ra_mailman')->get('core.create')) {
            throw new \RuntimeException(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $this->addToolbar();

        return parent::display($tpl);
    }

    protected function addToolbar(): void {
        Factory::getApplication()->input->set('hidemainmenu', true);
        ToolbarHelper::title(Text::_('Add Subscription'), 'users');
        ToolbarHelper::save('subscription.save', 'JTOOLBAR_SAVE');
        ToolbarHelper::cancel('subscription.cancel', 'JTOOLBAR_CANCEL');
    }
}
