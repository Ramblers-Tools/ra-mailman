<?php
/**
 * @version    4.5.3
 * @package    com_ra_mailman
 * @author     Charlie Bigley <webmaster@bigley.me.uk>
 * @copyright  2023 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 01/01/24 CB ensure read only if not in Group com_ra_mailman
 * 02/01/24 CB only show audit if canEdit
 * 16/01/24 CB always allow Authors to create/send mailshots
 * 27/01/24 CB use mailhelper / countMailshots
 * 29/01/24 CB selections
 * 30/01/24 CB remove attempt to show and change status (gives error in controller /submit)
 * 08/04/24 CB disallow send if list not published
 * 30/07/24 CB show last_sent
 * 14/10/24 CB disallow edit of mailshot if only partly sent
 * 16/10/24 CB use lastMailshot to find details of outstanding mailshot(s)
 * 22/10/24 CB separate link for mailshot/attachment
 * 04/11/24 CB use $this->user, not getUser
 * 13/06/25 CB show state, correct label of button when no previous mailshots
 * 08/08/25 CB show emails_outstanding on Resend
 * 11/08/25 CB new mechanism for send
 * 25/08/25 unpublished list in red
 */
// No direct access
defined('_JEXEC') or die;

use \Joomla\CMS\Factory;
use \Joomla\CMS\HTML\HTMLHelper;
use \Joomla\CMS\Uri\Uri;
use \Joomla\CMS\Router\Route;
use \Joomla\CMS\Layout\LayoutHelper;
use \Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;

HTMLHelper::_('bootstrap.tooltip');
HTMLHelper::_('behavior.multiselect');

// Import CSS
$wa = $this->document->getWebAssetManager();
$wa->registerAndUseStyle('ramblers', 'com_ra_tools/ramblers.css');
$this->document->addStyleDeclaration(
    "#ra-mailman-schedule-modal { display:none; position:fixed; top:0; left:0; width:100%; height:100%;" .
    " background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center; }" .
    "#ra-mailman-schedule-modal .ra-mailman-schedule-box { background:#fff; color:#000; padding:20px;" .
    " border-radius:6px; min-width:280px; box-shadow:0 2px 12px rgba(0,0,0,0.3); }" .
    "#ra-mailman-schedule-modal .ra-mailman-schedule-box input[type=date] { width:100%; margin:5px 0; }" .
    "#ra-mailman-schedule-modal .ra-mailman-schedule-box select { margin:5px 0; min-width:60px; }"
);
$this->document->addScriptDeclaration(
    "function raMailmanOpenSchedule(mailshotId, total, basePath, existingSendAfter) {" .
    " var modal = document.getElementById('ra-mailman-schedule-modal');" .
    " modal.dataset.mailshotId = mailshotId; modal.dataset.total = total; modal.dataset.basePath = basePath;" .
    " var pad = function(n) { return (n < 10 ? '0' : '') + n; };" .
    " var d = existingSendAfter ? new Date(existingSendAfter.replace(' ', 'T')) : new Date(Date.now() + 30 * 60000);" .
    " var today = new Date();" .
    " var todayStr = today.getFullYear() + '-' + pad(today.getMonth() + 1) + '-' + pad(today.getDate());" .
    " document.getElementById('ra-mailman-schedule-date').min = todayStr;" .
    " document.getElementById('ra-mailman-schedule-date').value = d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());" .
    " var hourSel = document.getElementById('ra-mailman-schedule-hour');" .
    " var minSel = document.getElementById('ra-mailman-schedule-minute');" .
    " if (!hourSel.options.length) {" .
    "  for (var h = 0; h < 24; h++) { var o = document.createElement('option'); o.value = pad(h); o.text = pad(h); hourSel.appendChild(o); }" .
    "  for (var m = 0; m < 60; m += 5) { var o2 = document.createElement('option'); o2.value = pad(m); o2.text = pad(m); minSel.appendChild(o2); }" .
    " }" .
    " hourSel.value = pad(d.getHours());" .
    " minSel.value = pad(Math.round(d.getMinutes() / 5) * 5 % 60);" .
    " modal.style.display = 'flex';" .
    " }" .
    "function raMailmanCloseSchedule() {" .
    " document.getElementById('ra-mailman-schedule-modal').style.display = 'none';" .
    " }" .
    "function raMailmanConfirmSchedule() {" .
    " var modal = document.getElementById('ra-mailman-schedule-modal');" .
    " var d = document.getElementById('ra-mailman-schedule-date').value;" .
    " var h = document.getElementById('ra-mailman-schedule-hour').value;" .
    " var m = document.getElementById('ra-mailman-schedule-minute').value;" .
    " if (!d) { alert('Please choose a date and time to schedule the send.'); return; }" .
    " var val = d + 'T' + h + ':' + m;" .
    " if (new Date(val) <= new Date()) { alert('Please choose a date and time in the future.'); return; }" .
    " window.location.href = modal.dataset.basePath + '?option=com_ra_mailman&task=mailshot.schedule&mailshot_id=' + modal.dataset.mailshotId + '&total=' + modal.dataset.total + '&send_at=' + encodeURIComponent(val);" .
    " }"
);

$listOrder = $this->state->get('list.ordering');
$listDirn = $this->state->get('list.direction');

// Ensure display is read only if not in Group com_ra_mailman
$canUpdate = $this->canDo->get('core.edit');
$canCreate = $this->user->authorise('core.create', 'com_ra_mailman');
$canEdit = $this->user->authorise('core.edit', 'com_ra_mailman');
?>

<form action="<?php echo Route::_('index.php?option=com_ra_mailman&view=mail_lsts'); ?>" method="post"
      name="adminForm" id="adminForm">
    <div class="row">
        <div class="col-md-12">
            <div id="j-main-container" class="j-main-container">
                <?php echo LayoutHelper::render('joomla.searchtools.default', array('view' => $this)); ?>

                <div class="clearfix"></div>
                <table class="table mintcake table-striped" id="mail_lstList">
                    <thead>
                        <tr>
                            <?php
                            echo '<th class="w-1 text-center">';
                            echo '<input type="checkbox" autocomplete="off" class="form-check-input" name="checkall-toggle" value="" title=""';
                            echo Text::_('JGLOBAL_CHECK_ALL');
                            echo '" onclick="Joomla.checkAll(this)"/>';
                            echo '</th>';

//                            echo '<th  scope="col" class="w-1 text-center">' . HTMLHelper::_('searchtools.sort', 'JSTATUS', 'a.state', $listDirn, $listOrder) . '</th>';
                            ?>
                            <th class='left'>
                                <?php echo HTMLHelper::_('searchtools.sort', 'Group', 'a.group_code', $listDirn, $listOrder); ?>
                            </th>
                            <th class='left'>
                                <?php echo HTMLHelper::_('searchtools.sort', 'List name', 'a.name', $listDirn, $listOrder); ?>
                            </th>
                            <th class='left'>
                                <?php echo HTMLHelper::_('searchtools.sort', 'Owner', 'g.name', $listDirn, $listOrder); ?>
                            </th>
                            <th class='left'>
                                <?php echo HTMLHelper::_('searchtools.sort', 'Type', 'a.record_type', $listDirn, $listOrder); ?>
                            </th>
                            <th class='left'>
                                <?php echo HTMLHelper::_('searchtools.sort', 'Home', 'a.home_group_only', $listDirn, $listOrder); ?>
                            </th>
                            <th class='left'>
                                <?php echo HTMLHelper::_('searchtools.sort', 'Pub', 'a.state', $listDirn, $listOrder); ?>
                            </th>
                            <?php
                            echo '<th class="left">';
                            echo 'Last sent';
                            echo '</th>';

                            echo '<th>Outstanding</th>';
                            echo '<th></th>';

                            echo '<th class="left">';
                            echo 'Authors';
                            echo '</th>';

                            echo '<th class="left">';
                            echo 'Subscribers';
                            echo '</th>';

                            echo '<th class="left">';
                            echo 'Mailshots';
                            echo '</th>';

                            if ($canEdit) {
                                echo '<th class="left">';
                                echo 'Audit';
                                echo '</th>';
                            }
                            ?>
                        </tr>
                    </thead>
                    <tfoot>
                        <tr>
                            <td colspan="<?php echo $canEdit ? 14 : 13; ?>">
                                <?php echo $this->pagination->getListFooter(); ?>
                            </td>
                        </tr>
                    </tfoot>
                    <tbody>
                        <?php
                        foreach ($this->items as $i => $item) :
                            $canCheckin = $this->user->authorise('core.manage', 'com_ra_mailman');
                            $canChange = $this->user->authorise('core.edit.state', 'com_ra_mailman');
                            if ($item->emails_outstanding > 0) {
                                $message = $item->group_code . '/' . $item->name . ': ' . $this->mailshot_send_message;
                                Factory::getApplication()->enqueueMessage($message, 'notice');
                            }
                            // Find details of the mailshots sent
                            $last_mailshot = $this->mailHelper->lastMailshot($item->id); //
//                            var_dump($last_mailshot);
//                            echo '<br>';
                            // see if the current user is an Author of this list
                            $isAuthor = $this->mailHelper->isAuthor($item->id);

                            // Count number of Authors
                            $count_authors = $this->mailHelper->countSubscribers($item->id, 'Y');
                            // Count number of subscribers
                            $count_subscribers = $this->mailHelper->countSubscribers($item->id);
                            if ($item->state == 1) {
                                $name = $this->escape($item->name);
                            } else {
                                $name = '<div style="color:red">' . $this->escape($item->name) . '</div>';
                            }
                            ?>
                            <tr class="row<?php echo $i % 2; ?>" data-draggable-group='1' data-transition>
                                <td class="text-center">
                                    <?php echo HTMLHelper::_('grid.id', $i, $item->id); ?>
                                </td>
                                <?php
//                                echo '<td>' . HTMLHelper::_('jgrid.published', $item->state, $i, 'mail_lst.', $canChange, 'cb') . '</td>';
                                echo '<td>' . $item->group_code . '</td>';
                                ?>
                                <td>
                                    <?php if (isset($item->checked_out) && $item->checked_out && ($canEdit || $canChange)) : ?>
                                        <?php echo HTMLHelper::_('jgrid.checkedout', $i, $item->uEditor, $item->checked_out_time, 'mail_lsts.', $canCheckin); ?>
                                    <?php endif; ?>
                                    <?php if ($canEdit) : ?>
                                        <a href="<?php echo Route::_('index.php?option=com_ra_mailman&task=mail_lst.edit&id=' . (int) $item->id); ?>">
                                            <?php echo $name; ?>
                                        </a>
                                    <?php else : ?>
                                        <?php echo $name; ?>
                                    <?php
                                    endif;
                                    if ($item->group_primary != '') {
                                        echo '<i class="fas fa-star fa-fw"></i>';
                                    }
                                    echo '</td>';
                                    echo '<td>' . $item->owner . '</td>';
                                    echo '<td>' . $item->Type . '</td>';
                                    echo '<td>';
                                    if ($item->home_group_only == 1) {
                                        echo 'Yes';
                                    } else {
                                        echo 'No';
                                    }
                                    echo '</td>';
                                    echo '<td>';
                                    if ($item->state == 1) {
                                        echo 'Y';
                                    } else {
                                        echo 'N';
                                    }
                                    echo '</td>';

                                    echo '<td>';
                                    echo $last_mailshot->date;
                                    echo '</td>';

                                    echo '<td>';
                                    if ($item->emails_outstanding > 0) {
                                        echo $item->emails_outstanding;
                                        if ($last_mailshot->is_scheduled) {
                                            echo ' (scheduled for ' . HTMLHelper::_('date', $last_mailshot->send_after, 'H:i d M Y') . ')';
                                        }
                                    }
                                    echo '</td>';

                                    echo '<td>'; // . $item->owner_id;
                                    if ($item->emails_outstanding == 0) {
                                        echo $this->sendButton($last_mailshot, $count_subscribers, $canEdit, $isAuthor);
                                        echo $this->scheduleButton($last_mailshot, $count_subscribers, $canEdit, $isAuthor);
                                    } else {
                                        if ($last_mailshot->is_scheduled) {
                                            echo $this->scheduleButton($last_mailshot, $count_subscribers, $canEdit, $isAuthor, 'Edit schedule');
                                        }
                                        echo $this->cancelButton($last_mailshot, $canEdit, $isAuthor);
                                    }
                                    echo '</td>';

                                    echo '<td>';
                                    $target_info = 'administrator/index.php?option=com_ra_mailman&task=mail_lst.showSubscribers&list_id=' . $item->id;
                                    if ($count_authors > 0) {
                                        echo $count_authors;
                                        echo $this->toolsHelper->imageButton('I', $target_info . '&author=Y');
                                    }
                                    if (($item->state == 1) AND ($canEdit)) {
                                        $target = 'administrator/index.php?option=com_ra_mailman&view=user_select&record_type=2&list_id=' . $item->id;
//                        echo $this->toolsHelper->buildLink($target, 'Select', False, "btn btn-small button-new");
                                        //             echo '<span class="icon-pencil-2"></span>';
                                        echo $this->toolsHelper->buildLink($target, 'edit');
                                    }
                                    echo '</td>';

                                    echo '<td>';
                                    if ($count_subscribers > 0) {
                                        echo $count_subscribers;
                                        echo $this->toolsHelper->imageButton('I', $target_info);
                                    }
                                    if (($item->state == 1) AND ($canEdit)) {
                                        $target = '/administrator/index.php?option=com_ra_mailman&view=user_select&record_type=1&list_id=' . $item->id;
//                                    echo '<a href="' . $target . '"><span class="icon-pencil-2"></span></a>';
                                        //            echo $this->toolsHelper->buildIconlink($target, 'icon-pencil-2');
                                        echo $this->toolsHelper->buildLink($target, 'edit');
                                    }
                                    echo '</td>';
                                    // Mailshots
                                    echo '<td>';
                                    $count = $this->mailHelper->countMailshots($item->id);
                                    $target_info = 'administrator/index.php?option=com_ra_mailman&view=mailshots&callback=mail_lsts&list_id=' . $item->id;
                                    if ($count > 0) {
                                        // show a link to browse the mailshots
                                        echo $this->toolsHelper->buildLink($target_info, $count, False);
                                    }
                                    if ($item->emails_outstanding > 0) {
                                        // Can't edit
                                    } elseif (($item->state == 0) OR (is_null($last_mailshot->date_sent))
                                            AND (!is_null($last_mailshot->processing_started))) {
                                        // Mailing list is inactive
                                        // or the mailshot is only partly send
                                    } else {
                                        if (($canEdit) OR ($isAuthor)) {
                                            $target_edit = 'administrator/index.php?option=com_ra_mailman&view=mailshot&layout=edit&list_id=' . $item->id;
                                            if ((is_null($last_mailshot->date_sent))
                                                    AND ($count > 0)
                                                    AND (is_null($last_mailshot->processing_started))) {
                                                // Set up the link to Edit
                                                $target_edit .= '&id=' . $last_mailshot->id;
                                                $label = 'Edit';
                                                $colour = 'sunrise';
                                            } else {
                                                // if (($count == 0) or ($last_mailshot->date_sent > '')) {
                                                $target_edit .= '&id=0';
                                                $label = 'New';
                                                $colour = 'sunset';
                                                //  }
                                            }
                                            echo $this->toolsHelper->buildButton($target_edit, $label, False, $colour);
                                            if ($label === 'Edit') {
                                                echo $this->discardButton($last_mailshot);
                                            }
                                        }
                                    }

                                    echo '</td>';
                                    // Audit

                                    if ($canEdit) {
                                        echo '<td>';
                                        $target_audit = 'administrator/index.php?option=com_ra_mailman&task=mail_lst.showAuditAll&list_id=' . $item->id;
                                        echo $this->toolsHelper->buildLink($target_audit, 'Show', False, 'gray');
                                        echo '</td>';
                                    }
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

<div id="ra-mailman-schedule-modal">
    <div class="ra-mailman-schedule-box">
        <h4>Schedule mailshot</h4>
        <label for="ra-mailman-schedule-date">Send at:</label>
        <input type="date" id="ra-mailman-schedule-date">
        <span class="fas fa-clock" aria-hidden="true"></span>
        <select id="ra-mailman-schedule-hour"></select> :
        <select id="ra-mailman-schedule-minute"></select>
        <div>
            <button type="button" class="link-button button-p0159" onclick="raMailmanConfirmSchedule()">Queue</button>
            <button type="button" class="link-button" onclick="raMailmanCloseSchedule()">Cancel</button>
        </div>
    </div>
</div>