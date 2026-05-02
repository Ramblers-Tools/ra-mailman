<?php
/**
 * @version    4.7.0
 * @package    com_ra_mailman
 * @author     Charlie Bigley <webmaster@bigley.me.uk>
 * @copyright  2024 Charlie Bigley
 * @license    GNU General Public License version 3 or later; see LICENSE.txt
 * 08/04/26 Claude Refactored from com_ra_tools
 */
// No direct access
defined('_JEXEC') or die;

use \Joomla\CMS\HTML\HTMLHelper;
use \Joomla\CMS\Factory;
use \Joomla\CMS\Uri\Uri;
use \Joomla\CMS\Router\Route;
use \Joomla\CMS\Language\Text;

$wa = $this->document->getWebAssetManager();
$wa->useScript('keepalive')
        ->useScript('form.validate');
HTMLHelper::_('bootstrap.tooltip');
?>

<form
    action="<?php echo Route::_('index.php?option=com_ra_mailman&view=organisation&layout=edit&id=' . (int) $this->item->id); ?>"
    method="post" enctype="multipart/form-data" name="adminForm" id="adminForm" class="form-validate form-horizontal">

    <?php echo HTMLHelper::_('uitab.startTabSet', 'myTab', array('active' => 'organisation')); ?>
    
    <?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'organisation', Text::_('Organisation', true)); ?>
    <div class="row-fluid">
        <div class="col-md-12 form-horizontal">
            <fieldset class="adminform">
                <?php 
                echo $this->form->renderField('nation_id'); 
                echo $this->form->renderField('code'); 
                echo $this->form->renderField('name');
                echo $this->form->renderField('details');
                echo $this->form->renderField('mailman_active');
                echo $this->form->renderField('website');
                echo $this->form->renderField('co_url');
                if ($this->item->record_type === 'A') {
                    echo $this->form->renderField('cluster');
                }
                echo $this->form->renderField('latitude'); 
                echo $this->form->renderField('longitude');
                
                ?>
            </fieldset>
        </div>
    </div>
    <?php echo HTMLHelper::_('uitab.endTab'); ?>

    <?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'email_header', Text::_('Email Header', true)); ?>
    <div class="row-fluid">
        <div class="col-md-12 form-horizontal">
            <fieldset class="adminform">
                <?php if ($this->form->getField('email_header')): ?>
                    <?php echo $this->form->renderField('email_header'); ?>
                <?php endif; ?>
                <?php echo $this->form->renderField('logo'); ?>
                <?php if ($this->form->getField('logo_align')): ?>
                    <?php echo $this->form->renderField('logo_align'); ?>
                <?php endif; ?>
            <?php if ($this->form->getField('colour_header')): ?>
                    <?php echo $this->form->renderField('colour_header'); ?>
                <?php endif; ?>
                <?php if ($this->form->getField('colour_body')): ?>
                    <?php echo $this->form->renderField('colour_body'); ?>
                <?php endif; ?>
                <?php if ($this->form->getField('colour_footer')): ?>
                    <?php echo $this->form->renderField('colour_footer'); ?>
                <?php endif; ?>
            </fieldset>
        </div>
    </div>
    <?php echo HTMLHelper::_('uitab.endTab'); ?>

    <?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'welcome_letter', Text::_('Welcome Letter', true)); ?>
    <div class="row-fluid">
        <div class="col-md-12 form-horizontal">
            <fieldset class="adminform">
                <?php if ($this->form->getField('welcome_letter')): ?>
                    <?php echo $this->form->renderField('welcome_letter'); ?>
                <?php endif; ?>
            </fieldset>
        </div>
    </div>
    <?php echo HTMLHelper::_('uitab.endTab'); ?>

    <?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'reminder_letter', Text::_('Reminder Letter', true)); ?>
    <div class="row-fluid">
        <div class="col-md-12 form-horizontal">
            <fieldset class="adminform">
                <?php if ($this->form->getField('reminder_letter')): ?>
                    <?php echo $this->form->renderField('reminder_letter'); ?>
                <?php endif; ?>
            </fieldset>
        </div>
    </div>
    <?php echo HTMLHelper::_('uitab.endTab'); ?>

    <?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'publishing', Text::_('Publishing', true)); ?>
    <div class="row-fluid">
        <div class="col-md-12 form-horizontal">
            <fieldset class="adminform">
                <?php echo $this->form->renderField('id'); ?>
                <?php echo $this->form->renderField('state'); ?>
                <?php echo $this->form->renderField('created'); ?>
                <?php echo $this->form->renderField('created_by'); ?>
                <?php echo $this->form->renderField('modified'); ?>
                <?php echo $this->form->renderField('modified_by'); ?>
            </fieldset>
        </div>
    </div>
    <?php echo HTMLHelper::_('uitab.endTab'); ?>

    <?php echo HTMLHelper::_('uitab.endTabSet'); ?>

    <input type="hidden" name="task" value=""/>
    <?php echo HTMLHelper::_('form.token'); ?>

</form>
