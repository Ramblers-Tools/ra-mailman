<?php
/**
 * @version    4.7.0
 * @package    com_ra_mailman
 * @author     Charlie Bigley <webmaster@bigley.me.uk>
 * @copyright  2024 Charlie Bigley
 * @license    GNU General Public License version 3 or later; see LICENSE.txt
 * 21/02/26 CB use area.showGroups, not view grouplist
 * 22/02/25 CB delete percentage widths, only show one link to CO
 *             don't allow editing
 * 08/04/26 Claude Refactored from com_ra_tools
 * 27/04/26 CB refresh members
 */
defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

//echo __FILE__ . '<br>';
$listOrder = $this->escape($this->state->get('list.ordering'));
$listDirn = $this->escape($this->state->get('list.direction'));

$editIcon = '<span class="fa fa-pen-square me-2" aria-hidden="true"></span>';
$objHelper = new ToolsHelper;
$self = 'index.php?option=com_ra_mailman&view=organisations';
//$target = 'administrator/index.php?option=com_ra_mailman&task=organisation.showOrganisation&code=';
$target = 'administrator/index.php?option=com_ra_mailman&view=organisation&layout=edit&id=';
echo '<form action="' . Route::_($self) . '" method="post" name="adminForm" id="adminForm">' . PHP_EOL;
echo '<div class="row">' . PHP_EOL;
echo '<div class="col-md-12">' . PHP_EOL;
echo '<div id="j-main-container" class="j-main-container">' . PHP_EOL;
echo LayoutHelper::render('joomla.searchtools.default', array('view' => $this));
if (empty($this->items)) {
    echo '<div class="alert alert-info">' . PHP_EOL;
    echo '<span class="fa fa-info-circle" aria-hidden="true"></span><span class="sr-only">';
    echo Text::_('INFO') . '</span>' . PHP_EOL;
    echo Text::_('JGLOBAL_NO_MATCHING_RESULTS') . PHP_EOL;
    echo '</div>' . PHP_EOL;
} else {
    echo '<table class="table" id="ra_areasList">' . PHP_EOL;
    echo '<thead>' . PHP_EOL;
    echo '<tr>' . PHP_EOL;

    echo '<td style="width:1%" class="text-center">' . PHP_EOL;
    echo HTMLHelper::_('grid.checkall') . PHP_EOL;
    echo '</th>' . PHP_EOL;

    echo '<th scope="col" style="width:1%; min-width:85px" class="text-center">' . PHP_EOL;
    echo HTMLHelper::_('searchtools.sort', 'Code', 'a.code', $listDirn, $listOrder) . PHP_EOL;
    echo '</th>' . PHP_EOL;

    echo '<th scope="col" s>' . PHP_EOL;
    echo HTMLHelper::_('searchtools.sort', 'Nation', 'n.name', $listDirn, $listOrder) . PHP_EOL;
    echo '</th>' . PHP_EOL;

    echo '<th class="left">' . PHP_EOL;
    echo HTMLHelper::_('searchtools.sort', 'Cluster', 'a.cluster', $listDirn, $listOrder);
    echo '</th>' . PHP_EOL;

    echo '<th scope="col" >' . PHP_EOL;
    echo HTMLHelper::_('searchtools.sort', 'Area Name', 'a.name', $listDirn, $listOrder) . PHP_EOL;
    echo '</th>' . PHP_EOL;

    echo '<th scope="col" >' . PHP_EOL;
    echo HTMLHelper::_('searchtools.sort', 'Email Header', 'a.email_header', $listDirn, $listOrder) . PHP_EOL;
    echo '</th>' . PHP_EOL;

    echo '<th scope="col"  class="d-none d-md-table-cell">' . PHP_EOL;
    echo HTMLHelper::_('searchtools.sort', 'Logo', 'a.logo', $listDirn, $listOrder) . PHP_EOL;
    echo '</th>' . PHP_EOL;

    echo '<th scope="col"  class="d-none d-md-table-cell">' . PHP_EOL;
    echo 'Groups' . PHP_EOL;
    echo '</th>' . PHP_EOL;

    echo '<th scope="col"  class="d-none d-md-table-cell">' . PHP_EOL;
    echo 'Members' . PHP_EOL;
    echo '</th>' . PHP_EOL;

    echo '<th scope="col" class="d-none d-md-table-cell">' . PHP_EOL;
    echo 'ID' . PHP_EOL;
    echo '</th>' . PHP_EOL;

    echo '</tr>' . PHP_EOL;
    echo '</thead>' . PHP_EOL;
    echo '<tbody>' . PHP_EOL;

    $n = count($this->items);
    foreach ($this->items as $i => $item) {
        
        echo '<tr class="row' . $i % 2 . '">' . PHP_EOL;

        echo '<td class="text-center">' . PHP_EOL;
        echo HTMLHelper::_('grid.id', $i, $item->id) . PHP_EOL;
        echo '</td>' . PHP_EOL;

        echo '<td class="article-status">' . PHP_EOL;
        echo $item->code . PHP_EOL;
        echo '</td>' . PHP_EOL;

        echo '<td class="">' . PHP_EOL;
        echo $item->nation . PHP_EOL;
        echo '</td>' . PHP_EOL;

        echo '<td class="">' . PHP_EOL;
        echo $item->cluster . PHP_EOL;
        echo '</td>' . PHP_EOL;
        echo '<td class="has-context">' . PHP_EOL;
        if ($this->canDo->get('core.edit')) {
//            echo $target . $item->id . '">' . PHP_EOL;
            echo $objHelper->buildLink($target . $item->id, $item->name, False, "");
            //          echo '<a class="hasTooltip" href="' . Route::_('index.php?option=com_ra_mailman&view=organisation&layout=edit&id=' . $item->id) . '">' . PHP_EOL;
            // $target = 'index.php?option=com_ra_mailman&task=organisation.showOrganisation&code=';
            //           echo $editIcon . $this->escape($item->name) . '</a>' . PHP_EOL;
        } else {
            echo $this->escape($item->name) . PHP_EOL;
        }
        echo '</td>' . PHP_EOL;

        echo '<td class="">' . PHP_EOL;
        if ($item->email_header != '') {
            echo $this->escape($item->email_header) . PHP_EOL;
        }
        echo '</td>' . PHP_EOL;

        echo '<td class="d-none d-md-table-cell">' . PHP_EOL;
        if ($item->logo != "") {
            $logo = (strpos($item->logo, '/') === false) ? 'images/com_ra_mailman/' . $item->logo : $item->logo;
            echo $objHelper->buildLink($logo, $item->logo, True, "");
        }
        echo '</td>' . PHP_EOL;

        echo '<td class="">' . PHP_EOL;
        if ($item->record_type == 'A'){
            $group_count = $objHelper->getValue('SELECT COUNT(id) FROM #__ra_groups WHERE code LIKE "' . $item->code . '%"');
            if ($group_count > 0) {

                echo '<a href="' . Route::_('index.php?option=com_ra_mailman&task=organisation.showGroups&area=' . $item->code) . '">' . PHP_EOL;
                echo $group_count . '</a>' . PHP_EOL;
        }
    }
        echo '</td>' . PHP_EOL;
        echo '<td class="">' . PHP_EOL;
        if ($item->record_type == 'A'){
            $member_count = $objHelper->getValue('SELECT COUNT(id) FROM #__ra_profiles WHERE home_group LIKE "' . $item->code . '%"');
        } else {
            $member_count = $objHelper->getValue('SELECT COUNT(id) FROM #__ra_profiles WHERE home_group = "' . $item->code . '"');
        }    
        if ($member_count > 0) {
            echo '<a href="' . Route::_('index.php?option=com_ra_mailman&task=organisation.showMembers&code=' . $item->code) . '">' . PHP_EOL;
            echo $member_count . '</a>' . PHP_EOL;
        }
        if ($item->mailman_active == 'Y') {
            // UPDATE `j5_ra_organisations` SET mailman_active = "Y" WHERE code = "NS12";
            $load = 'index.php?option=com_ra_mailman&task=profiles.load&code=' . $item->code;
            echo ' <a href="' . Route::_($load) . '" class="ms-2">' . PHP_EOL;
            echo '<span class="fa fa-sync" aria-hidden="true"></span><span class="sr-only">Load</span>' . PHP_EOL;
            echo '</a>' . PHP_EOL;
        }
        echo '</td>' . PHP_EOL;

        echo '<td class="d-none d-md-table-cell">' . PHP_EOL;
        echo $item->id . PHP_EOL;
        echo '</td>' . PHP_EOL;
        echo '</tr>' . PHP_EOL;
    }
    echo '</tbody>' . PHP_EOL;
    echo '</table>' . PHP_EOL;

// load the pagination.
    echo $this->pagination->getListFooter();
}
?>

<input type="hidden" name="task" value="">
<input type="hidden" name="boxchecked" value="0">
<?php echo HTMLHelper::_('form.token'); ?>
</div>
</div>
</div>
</form>

