<?php
/**
 * @version    4.7.9
 * @package    com_ra_mailman
 * @author     Charlie Bigley <webmaster@bigley.me.uk>
 * @copyright  2023 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 05/11/24 CB separate tab for publishing
 * 06/12/25 CB add fields requireReset and block
 * 22/06/26 CB add membershipNumber
 */
// No direct access
defined('_JEXEC') or die;

use \Joomla\CMS\Factory;
use \Joomla\CMS\HTML\HTMLHelper;
use \Joomla\CMS\Uri\Uri;
use \Joomla\CMS\Router\Route;
use \Joomla\CMS\Language\Text;

$wa = $this->document->getWebAssetManager();
$wa->useScript('keepalive')
        ->useScript('form.validate');
HTMLHelper::_('bootstrap.tooltip');
?>

<form
    action="<?php echo Route::_('index.php?option=com_ra_mailman&layout=edit&id=' . (int) $this->item->id); ?>"
    method="post" enctype="multipart/form-data" name="adminForm" id="profile-form" class="form-validate form-horizontal">
        <?php echo HTMLHelper::_('uitab.startTabSet', 'myTab', array('active' => 'Maillist')); ?>
        <?php echo HTMLHelper::_('uitab.addTab', 'myTab', 'profile', 'Profile'); ?>
    <div class="row-fluid">
        <div class="span10 form-horizontal">
            <fieldset class="adminform">

                <?php
//                echo '<legend>Edit</legend>';
                echo $this->form->renderField('real_name');
                echo $this->form->renderField('preferred_name');
                echo $this->form->renderField('home_group');
                echo $this->form->renderField('email');
                echo $this->form->renderField('membershipNumber');
                echo $this->form->renderField('requireReset');
                echo $this->form->renderField('block');
                ?>

            </fieldset>
        </div>
    </div>
    <input type="hidden" name="jform[id]" value="<?php echo $this->item->id; ?>" />
    <input type="hidden" name="jform[state]" value="<?php echo $this->item->state; ?>" />
    <input type="hidden" name="task" value=""/>
    <?php
    echo HTMLHelper::_('uitab.endTab');
    echo HTMLHelper::_('uitab.addTab', 'myTab', 'profile2', Text::_('Publishing', true));
    if ($this->item->id > 0) {
        echo $this->form->renderField('created');
        echo $this->form->renderField('created_by');
        echo $this->form->renderField('modified');
        echo $this->form->renderField('modified_by');
    }
    echo $this->form->renderField('state');
    echo $this->form->renderField('group_primary');
    echo $this->form->renderField('id');
    echo HTMLHelper::_('uitab.endTab');
    echo HTMLHelper::_('uitab.endTabSet');
    ?>
    <?php echo HTMLHelper::_('form.token'); ?>

</form>