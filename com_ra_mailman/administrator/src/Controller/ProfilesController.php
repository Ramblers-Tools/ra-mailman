<?php

/**
 * @version    4.0.0
 * @package    com_ra_mailman
 * @author     Charlie Bigley <webmaster@bigley.me.uk>
 * @copyright  2023 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Ramblers\Component\Ra_mailman\Administrator\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Multilanguage;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\AdminController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\Utilities\ArrayHelper;
use Ramblers\Component\Ra_mailman\Site\Helpers\UserHelper;
//use Ramblers\Component\Ra_mailman\Site\Helpers\LoadHelper;
use Ramblers\Component\Ra_members\Site\Helper\LoadHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

/**
 * Profiles list controller class.
 *
 * @since  4.0.0
 */
class ProfilesController extends AdminController {

    protected $app;
    protected $back = 'index.php?option=com_ra_tools&view=dashboard'; 
    protected $toolsHelper;

    public function __construct(
        $config = [],
        MVCFactoryInterface $factory = null,
        CMSApplication $app = null,
        Input $input = null
    ) {
        parent::__construct($config, $factory, $app, $input);

        $this->toolsHelper = new ToolsHelper;
        $this->app = Factory::getApplication();
        $this->back = 'administrator/index.php?option=com_ra_tools&view=dashboard';

        $wa = $this->app->getDocument()->getWebAssetManager();
        $wa->registerAndUseStyle('ramblers', 'com_ra_tools/ramblers.css');
    }   

    public function cancel($key = null, $urlVar = null) {
        $this->setRedirect($this->back);
    }

    /**
     * Proxy for getModel.
     *
     * @param   string  $name    Optional. Model name
     * @param   string  $prefix  Optional. Class prefix
     * @param   array   $config  Optional. Configuration array for model
     *
     * @return  object	The Model
     *
     * @since   4.0.0
     */
    public function getModel($name = 'Profile', $prefix = 'Administrator', $config = array()) {
        return parent::getModel($name, $prefix, array('ignore_request' => true));
    }

    public function back() {
        $this->setRedirect($this->back);
    }

    public function load($code = 'NS03') {
        // temp code for invoking the load process
        $code = $this->app->input->getAlnum('code');
        $loadHelper = new LoadHelper;
        $result = $loadHelper->loadMembers($code);
        echo 'After loadMembers<br>';
        foreach ($loadHelper->messages as $message) {
             echo $message . '<br>';
        }   
        die;
        if ($result === true) {
            $this->setMessage(Text::_('COM_RA_MAILMAN_LOAD_SUCCESS'), 'success');
        } else {
            $this->setMessage(Text::_('COM_RA_MAILMAN_LOAD_FAILURE'), 'error');
        }

        $this->setRedirect($this->back);
    }   

    public function purgeTestdata() {
        echo 'Not implemented<br>';


//        $objUserHelper->purgeTestData();
        echo $this->toolsHelper->backButton('administrator/' . $this->back);
    }

}
