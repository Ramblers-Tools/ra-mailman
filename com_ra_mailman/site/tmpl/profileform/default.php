<?php
/**
 * @version    4.5.7
 * @package    com_ra_mailman
 * @author     Charlie Bigley <charlie@bigley.me.uk>
 * @copyright  2025 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */
// No direct access
defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use \Joomla\CMS\HTML\HTMLHelper;
use \Joomla\CMS\Factory;
use \Joomla\CMS\Uri\Uri;
use \Joomla\CMS\Router\Route;
use \Joomla\CMS\Language\Text;

$wa = $this->document->getWebAssetManager();
$wa->useScript('keepalive')
        ->useScript('form.validate');
HTMLHelper::_('bootstrap.tooltip');

// Load admin language file
$lang = Factory::getLanguage();
$lang->load('com_ra_mailman', JPATH_SITE);

// $this->title is derived from the menu
echo '<h1>' . $this->title . '</h1>';
if ($this->user->id == 0) {   // Self registering
    if (!$page_intro == '') {
        echo $page_intro;
    }
} else {
    echo '<p>After creating the User, you can select the lists to which (s)he should be subscribed</p>';
}
?>

<div class="profile-edit front-end-edit">

    <?php if ($this->params->get('show_page_heading')) : ?>
        <div class="page-header">
            <h1> <?php echo $this->escape($this->params->get('page_heading')); ?> </h1>
        </div>
    <?php endif; ?>

    <form id="form-profile"
          action="<?php echo Route::_('index.php?option=com_ra_mailman&task=profileform.save'); ?>"
          method="post" class="form-validate form-horizontal" enctype="multipart/form-data">

        <input type="hidden" name="jform[id]" value="<?php echo isset($this->item->id) ? $this->item->id : ''; ?>" />

        <div class="control-group">
            <?php if (!$canState): ?>
                <div class="control-label"><?php echo $this->form->getLabel('state'); ?></div>
                <div class="controls"><?php echo $state_string; ?></div>
                <input type="hidden" name="jform[state]" value="<?php echo $state_value; ?>" />
            <?php else: ?>
                <div class="control-label"><?php echo $this->form->getLabel('state'); ?></div>
                <div class="controls"><?php echo $this->form->getInput('state'); ?></div>
            <?php endif; ?>
        </div>

        <?php
        //echo $this->form->renderField('groups_to_follow');
        //echo $this->form->renderField('notify_email');
        echo $this->form->renderField('real_name');
        echo $this->form->renderField('preferred_name');
        echo $this->form->renderField('email');
        echo $this->form->renderField('home_group');
// echo $this->form->renderField('confirmed');
        ?>


        <div class="control-group">
            <div class="controls">

                <?php if ($this->canSave): ?>
                    <button type="submit" class="validate btn btn-primary">
                        <span class="fas fa-check" aria-hidden="true"></span>
                        <?php echo Text::_('JSUBMIT'); ?>
                    </button>
                <?php endif; ?>
                <a class="btn btn-danger"
                   href="<?php echo Route::_('index.php?option=com_ra_mailman&task=profileform.cancel'); ?>"
                   title="<?php echo Text::_('JCANCEL'); ?>">
                    <span class="fas fa-times" aria-hidden="true"></span>
                    <?php echo Text::_('JCANCEL'); ?>
                </a>
            </div>
        </div>

        <input type="hidden" name="option" value="com_ra_mailman"/>
        <input type="hidden" name="task"
               value="profileform.save"/>
               <?php echo HTMLHelper::_('form.token'); ?>
    </form>
</div>
