<?php
/**
 * 02/06/25 CB Created
 * 28/09/26 CB show num_records
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
$wa->registerAndUseStyle('com_ra_tools', 'ramblers.css');

$listOrder = $this->state->get('list.ordering');
$listDirn = $this->state->get('list.direction');
$target = 'administrator/index.php?option=com_ra_mailman&task=import_reports.';
$target_info = 'administrator/index.php?option=com_ra_mailman&task=subscription.showDetails&id=';
$objHelper = new ToolsHelper;
?>

<form action="<?php echo Route::_('index.php?option=com_ra_mailman&view=import_reports'); ?>" method="post"
      name="adminForm" id="adminForm">
    <div class="row">
        <div class="col-md-12">
            <div id="j-main-container" class="j-main-container">
                <?php echo LayoutHelper::render('joomla.searchtools.default', array('view' => $this)); ?>

                <div class="clearfix"></div>
                <table class="table table-striped" id="subscriptionList">
                    <thead>
                        <tr>
                            <?php
                            echo '<th class="left">' . HTMLHelper::_('searchtools.sort', 'id', 'a.id', $listDirn, $listOrder) . '</th>';
                            echo '<th class="left">' . HTMLHelper::_('searchtools.sort', 'Date', 'a.date_phase1', $listDirn, $listOrder) . '</th>';
                            echo '<th class="left">Time</th>';
                            echo '<th class="left">Complete</th>';
                            echo '<th class="left">' . HTMLHelper::_('searchtools.sort', 'Group', 'l.group_code', $listDirn, $listOrder) . '</th>';
                            echo '<th class="left">' . HTMLHelper::_('searchtools.sort', 'List', 'l.name', $listDirn, $listOrder) . '</th>';
                            echo '<th class="left">' . HTMLHelper::_('searchtools.sort', 'Method', 'm.name', $listDirn, $listOrder) . '</th>';
                            echo '<th class="left">' . HTMLHelper::_('searchtools.sort', 'Records', 'a.num_records', $listDirn, $listOrder) . '</th>';
                            echo '<th class="left">' . HTMLHelper::_('searchtools.sort', 'Errors', 'a.num_errors', $listDirn, $listOrder) . '</th>';
                            echo '<th class="left">' . HTMLHelper::_('searchtools.sort', 'Users', 'a.num_users', $listDirn, $listOrder) . '</th>';
                            echo '<th class="left">' . HTMLHelper::_('searchtools.sort', 'Subs', 'a.num_subs', $listDirn, $listOrder) . '</th>';
                            echo '<th class="left">' . HTMLHelper::_('searchtools.sort', 'Lapsed', 'a.num_lapsed', $listDirn, $listOrder) . '</th>';
                            echo '<th class="left">' . HTMLHelper::_('searchtools.sort', 'User', 'p.preferred_name', $listDirn, $listOrder) . '</th>';
                            echo '<th class="left">' . HTMLHelper::_('searchtools.sort', 'File', 'a.input_file', $listDirn, $listOrder) . '</th>';
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
                    <tbody
                    <?php
                    foreach ($this->items as $i => $item) :
                        ?>
                            <tr class="row<?php echo $i % 2; ?>" data-transition>

                            <?php
                            echo '<td>';
                            $link = $target . 'showSummary&id=' . $item->id;
                            echo $objHelper->buildlink($link, $item->id);
                            echo '</td>';
                            echo '<td>' . HTMLHelper::_('date', $item->date_phase1, 'd/m/y') . '</td>';
                            echo '<td>' . HTMLHelper::_('date', $item->date_completed, 'H:i') . '</td>';
                            echo '<td>';
                            if (!is_null($item->date_completed)) {
                                echo HTMLHelper::_('date', $item->date_completed, 'H:i');
                            }
                            echo '</td>';
                            echo '<td>' . $item->group . '</td>';
                            echo '<td>' . $item->list . '</td>';
                            echo '<td>' . $item->Method . '</td>'; 
                            echo '<td>' . $item->num_records . '</td>';
                            echo '<td>';
                            if ($item->num_errors > 0) {
                                $link = $target . 'showErrors&id=' . $item->id;
                                echo $objHelper->buildlink($link, $item->num_errors);
                            }
                            echo '</td>';

                            echo '<td>';
                            if ($item->num_users > 0) {
                                $link = $target . 'showUsers&id=' . $item->id;
                                echo $objHelper->buildlink($link, $item->num_users);
                            }
                            echo '</td>';

                            echo '<td>';
                            if ($item->num_subs > 0) {
                                $link = $target . 'showSubs&id=' . $item->id;
                                echo $objHelper->buildlink($link, $item->num_subs);
                            }
                            echo '</td>';

                            echo '<td>';
                            if ($item->num_lapsed > 0) {
                                $link = $target . 'showLapsed&id=' . $item->id;
                                echo $objHelper->buildlink($link, $item->num_lapsed);
                            }
                            echo '</td>';

                            echo '<td>' . $item->preferred_name . '</td>';
//                            echo '<td>' . $item->input_file . '</td>';
                            echo '<td>';
                            $link = $target . 'showFile&id=' . $item->id;
                            echo $objHelper->buildlink($link, $item->input_file);
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
