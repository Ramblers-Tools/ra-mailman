<?php
/**
 * 17/07/25 CB regenerated, but use registerAndUseStyle
 * 21/09/26 Cb show audit even if unpublished
 */
// No direct access
defined('_JEXEC') or die;

use \Joomla\CMS\HTML\HTMLHelper;
use \Joomla\CMS\Factory;
use \Joomla\CMS\Uri\Uri;
use \Joomla\CMS\Router\Route;
use \Joomla\CMS\Layout\LayoutHelper;
use \Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

HTMLHelper::_('bootstrap.tooltip');
HTMLHelper::_('behavior.multiselect');

// Import CSS
$wa = $this->document->getWebAssetManager();
$wa->registerAndUseStyle('ramblers', 'com_ra_tools/ramblers.css');

$user = Factory::getApplication()->getIdentity();
$userId = $user->get('id');
$listOrder = $this->state->get('list.ordering');
$listDirn = $this->state->get('list.direction');
$canOrder = $user->authorise('core.edit.state', 'com_ra_mailman');
$target_info = 'administrator/index.php?option=com_ra_mailman&task=subscription.showDetails&id=';
$target_email = 'administrator/index.php?option=com_ra_mailman&task=subscription.sendRenewal&user_id=';
$objHelper = new ToolsHelper;

if (!empty($saveOrder)) {
    $saveOrderingUrl = 'index.php?option=com_ra_mailman&task=subscriptions.saveOrderAjax&tmpl=component&' . Session::getFormToken() . '=1';
    HTMLHelper::_('draggablelist.draggable');
}
?>

<form action="<?php echo Route::_('index.php?option=com_ra_mailman&view=subscriptions'); ?>" method="post"
      name="adminForm" id="adminForm">
    <div class="row">
        <div class="col-md-12">
            <div id="j-main-container" class="j-main-container">
                <?php echo LayoutHelper::render('joomla.searchtools.default', array('view' => $this)); ?>

                <div class="clearfix"></div>
                <table class="table table-striped" id="subscriptionList">
                    <thead>
                        <tr>
                            <th class="w-1 text-center">
                                <input type="checkbox" autocomplete="off" class="form-check-input" name="checkall-toggle" value=""
                                       title="<?php echo Text::_('JGLOBAL_CHECK_ALL'); ?>" onclick="Joomla.checkAll(this)"/>
                            </th>
                            <th  scope="col" class="w-1 text-center">
                                <?php echo HTMLHelper::_('searchtools.sort', 'JSTATUS', 'a.state', $listDirn, $listOrder); ?>
                            </th>
                            <?php
                            echo '<th class="left">' . HTMLHelper::_('searchtools.sort', 'Group', 'l.group_code', $listDirn, $listOrder) . '</th>';
                            echo '<th class="left">' . HTMLHelper::_('searchtools.sort', 'List', 'l.name', $listDirn, $listOrder) . '</th>';

                            echo '<th class="left">' . HTMLHelper::_('searchtools.sort', 'Real name', 'u.name', $listDirn, $listOrder) . '</th>';
                            echo '<th class="left">' . HTMLHelper::_('searchtools.sort', 'Method', 'm.name', $listDirn, $listOrder) . '</th>';
                            echo '<th class="left">' . HTMLHelper::_('searchtools.sort', 'Last updated', 'a.modified', $listDirn, $listOrder) . '</th>';
                            echo '<th class="left">' . HTMLHelper::_('searchtools.sort', 'Expires', 'a.expiry_date', $listDirn, $listOrder) . '</th>';
                            echo '<th>Audit</th>';
                            ?>

                        </tr>
                    </thead>
                    <tfoot>
                        <tr>
                            <td colspan="<?php echo isset($this->items[0]) ? count(get_object_vars($this->items[0])) : 10; ?>">
                                <?php echo $this->pagination->getListFooter(); ?>
                            </td>
                        </tr>
                    </tfoot>
                    <tbody <?php if (!empty($saveOrder)) : ?> class="js-draggable" data-url="<?php echo $saveOrderingUrl; ?>" data-direction="<?php echo strtolower($listDirn); ?>" <?php endif; ?>>
                        <?php
                        foreach ($this->items as $i => $item) :
                            $ordering = ($listOrder == 'a.ordering');
                            $canCreate = $user->authorise('core.create', 'com_ra_mailman');
                            $canEdit = $user->authorise('core.edit', 'com_ra_mailman');
                            $canCheckin = $user->authorise('core.manage', 'com_ra_mailman');
                            $canChange = $user->authorise('core.edit.state', 'com_ra_mailman');
                            ?>
                            <tr class="row<?php echo $i % 2; ?>" data-draggable-group='1' data-transition>
                                <td class="text-center">
                                    <?php echo HTMLHelper::_('grid.id', $i, $item->id); ?>
                                </td>


                                <td class="text-center">
                                    <?php echo HTMLHelper::_('jgrid.published', $item->state, $i, 'subscriptions.', $canChange, 'cb'); ?>
                                </td>

                                <?php
                                echo '<td>' . $item->group . '</td>';
                                echo '<td>' . $item->list;
                                if ($item->requireReset == 1) {
                                    echo '<span class="icon-warning"></span>';
                                }
                                echo '</td>';
                                echo '<td>' . $item->subscriber . '</td>';
                                echo '<td>' . $item->Method . '</td>';

                                echo '<td>';
                                if (($item->modified != '') AND ($item->modified != '0000-00-00 00:00:00')) {
                                    echo $item->modified;
                                }
                                echo '</td>';

                                echo '<td>';
                                if (($item->expiry_date != '') AND ($item->expiry_date != '0000-00-00 00:00:00')) {
                                    echo $item->expiry_date;
                                    $target = 'administrator/index.php?option=com_ra_mailman&task=subscriptions.forceRenewal';
                                    $target .= '&list_id=' . $item->list_id . '&user_id=' . $item->user_id;
                                    //echo $objHelper->buildlink($target_email . $item->user_id . '&list_id=' . $item->list_id, '<i class="fa-solid fa-calendar-days"></i>');
                                    echo $objHelper->buildlink($target_email . $item->user_id . '&list_id=' . $item->list_id, '<i class="icon-envelope"></i>');
//                                echo $objHelper->imageButton('X', $target);
                                }
                                echo '</td>';
                                echo '<td>';
                                echo $objHelper->buildlink($target_info . $item->id, '<i class="icon-info"></i>');
                                echo '</td>';
                                ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <input type="hidden" name="task" value=""/>
                <input type="hidden" name="boxchecked" value="0"/>
                <input type="hidden" name="list[fullorder]" value="<?php echo $listOrder; ?> <?php echo $listDirn; ?>"/>
                <?php echo HTMLHelper::_('form.token'); ?>
            </div>
        </div>
    </div>
</form>