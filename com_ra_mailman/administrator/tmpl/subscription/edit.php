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
                $selectedList = $this->activeList;
                if (!$selectedList && !empty($search)) {
                    $pattern = '%' . $this->db->escape($search, true) . '%';
                    $sql = 'SELECT id, group_code, name FROM #__ra_mail_lists WHERE name LIKE ';
                    $sql .= $this->db->quote($pattern, false);
                    $sql .= ' OR group_code LIKE ' . $this->db->quote($pattern, false);
                    $rows = $this->toolsHelper->getRows($sql);
                    if (count($rows) === 1) {
                        $selectedList = $rows[0];
                        $this->form->setValue('list_id', null, $selectedList->id);
                    }
                }

                if ($selectedList) {
                    echo '<p>Selecting user for <strong>'
                            . htmlspecialchars($selectedList->group_code . ' - ' . $selectedList->name, ENT_QUOTES, 'UTF-8')
                            . '</strong></p>';
                }
                if ($this->activeList) {
                    echo '<input type="hidden" name="jform[list_id]" value="' . (int) $this->activeList->id . '">';
                } else {
                    echo $this->form->renderField('list_id');
                }
                echo $this->form->renderField('user_id');
                echo $this->form->renderField('record_type');
                ?>

            </fieldset>
        </div>
    </div>

    <input type="hidden" name="task" value="subscription.save" />
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
