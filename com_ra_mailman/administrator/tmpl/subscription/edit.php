<?php
/*
 * 08/11/26 CB Created
*/

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;

$wa = $this->document->getWebAssetManager();
$wa->useScript('keepalive')->useScript('form.validate');
HTMLHelper::_('bootstrap.tooltip');
?>

<form action="<?php echo Route::_('index.php?option=com_ra_mailman&task=subscription.save'); ?>"
      method="post" name="adminForm" id="subscription-form" class="form-validate form-horizontal">
    <div class="row">
        <div class="col-md-12">
            <fieldset class="adminform">
                <?php 
                
                
                $search = $this->filterSearch;
                if (!empty($search)) {
                    $sql = 'SELECT * FROM #__ra_mail_lists ';
                    $sql .= ' WHERE name LIKE ' . $this->db->quote('%' . $search . '%');
                    $sql .= ' OR group_code LIKE ' . $this->db->quote('%' . $search . '%');
                    $rows = $this->toolsHelper->getRows($sql);
                    if (COUNT($rows) == 1) {
                        $row = $rows[0];
                        $this->form->setValue('list_id', null, $row->id);
                        echo 'Selecting User for list <b>' . htmlspecialchars($row->name, ENT_QUOTES, 'UTF-8') . '</b><br><br>'; 
                        echo $this->form->renderField('user_id');    
                    } else {
                        echo $this->form->renderField('user_id');     
                        echo $this->form->renderField('list_id');
                    }
                }
                echo $this->form->renderField('record_type');
                ?>

            </fieldset>
        </div>
    </div>

    <input type="hidden" name="task" value="subscription.save" />
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
