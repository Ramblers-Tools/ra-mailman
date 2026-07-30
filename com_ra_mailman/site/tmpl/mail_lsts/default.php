<?php
/**
 * @version    4.5.7
 * @package    com_ra_mailman
 * @author     Charlie Bigley <webmaster@bigley.me.uk>
 * @copyright  2023 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 14/11/23 CB pass menu_id to mailshots list
 * 21/11/23 CB only count mailshots that have been sent
 * 16/01/24 CB show button for owner to display subscribers
 * 27/01/24 CB use mailhelper / countMailshots
 * 26/08/24 CB don't allow editing if mailshot only partially sent
 * 14/10/24 CB show link(s) if attachment(s) present
 * 16/10/24 CB use lastMailshot to find details of outstanding mailshot(s)
 * 20/10/24 CB check user is logged in before showing Send button
 * 22/10/24 CB separate link for mailshot/attachment
 * 14/11/24 CB allow sort by preferred_name
 * 13/02/25 CB don't set up canEdit etc (never used)
 * 03/04/25 CB check isAuthore before creating Send button
 * 14/07/25 CB message if Resend
 * 08/08/25 CB show emails_outstanding on Resend
 * 20/10/25 CB new mechanism for send
 * 05/06/26 CB correct count of subscribers
 */
// No direct access
defined('_JEXEC') or die;

use \Joomla\CMS\HTML\HTMLHelper;
use \Joomla\CMS\Factory;
use \Joomla\CMS\Uri\Uri;
use \Joomla\CMS\Router\Route;
use \Joomla\CMS\Language\Text;
use \Joomla\CMS\Layout\LayoutHelper;
use \Joomla\CMS\Session\Session;
use \Joomla\CMS\User\UserFactoryInterface;
use Ramblers\Component\Ra_mailman\Site\Helpers\Mailhelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

HTMLHelper::_('bootstrap.tooltip');
HTMLHelper::_('behavior.multiselect');
HTMLHelper::_('formbehavior.chosen', 'select:not(.ra-mailman-plain)');

$mailHelper = new Mailhelper;
$toolsHelper = new ToolsHelper;
$listOrder = $this->state->get('list.ordering');
$listDirn = $this->state->get('list.direction');

// Import CSS
$wa = $this->document->getWebAssetManager();
$wa->registerAndUseStyle('ramblers', 'com_ra_tools/ramblers.css');
$this->document->addStyleDeclaration(
    "#ra-mailman-schedule-modal { display:none; position:fixed; top:0; left:0; width:100%; height:100%;" .
    " background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center; }" .
    "#ra-mailman-schedule-modal .ra-mailman-schedule-box { background:#fff; color:#000; padding:20px;" .
    " border-radius:6px; min-width:280px; box-shadow:0 2px 12px rgba(0,0,0,0.3); }" .
    "#ra-mailman-schedule-modal .ra-mailman-schedule-box input[type=date] { width:100%; margin:5px 0; }" .
    "#ra-mailman-schedule-modal .ra-mailman-schedule-box select.ra-mailman-plain { margin:5px 0; min-width:60px; }"
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
?>

<form action="<?php echo htmlspecialchars(Uri::getInstance()->toString()); ?>" method="post"
      name="adminForm" id="adminForm">
          <?php
          if (!empty($this->filterForm)) {
              echo LayoutHelper::render('joomla.searchtools.default', array('view' => $this));
          }
          ?>
    <div class="table-responsive">
        <table class="table table-striped mintcake" id="mail_lstList">
            <?php
            echo '<thead>';
            echo '<tr>';

            echo '<th class="left">';
            echo HTMLHelper::_('searchtools.sort', 'Group', 'a.group_code', $listDirn, $listOrder);
            echo '</th>';

            echo '<th class="left">';
            echo HTMLHelper::_('searchtools.sort', 'Name', 'a.name', $listDirn, $listOrder);
            echo '</th>';

            echo '<th class="left">';
            echo HTMLHelper::_('searchtools.sort', 'Owner', 'p.preferred_name', $listDirn, $listOrder);
            echo '</th>';

            echo '<th class="left">';
            echo HTMLHelper::_('searchtools.sort', 'Type', 'a.record_type', $listDirn, $listOrder);
            echo '</th>';

            echo '<th class="left">';
            echo HTMLHelper::_('searchtools.sort', 'Home group only', 'a.home_group_only', $listDirn, $listOrder);
            echo '</th>';

            echo '<th class="left">';
            echo 'Subscribers';
            echo '</th>';

            echo '<th class="left">';
            echo 'Mailshots';
            echo '</th>';

            echo '<th class="left">';
            echo 'Last sent';
            echo '</th>';
            echo '<th>Outstanding</th>';
            if ($this->user->id > 0) {
                echo '<th class="left">';
                echo 'Actions';
                echo '</th>';
            }
            echo '</tr>';
            echo '</thead>';
            ?>
            <tfoot>
                <tr>
                    <td colspan="<?php echo isset($this->items[0]) ? count(get_object_vars($this->items[0])) : 10; ?>">
                        <div class="pagination">
                            <?php echo $this->pagination->getPagesLinks(); ?>
                        </div>
                    </td>
                </tr>
            </tfoot>
            <tbody>
                <?php
                foreach ($this->items as $i => $item) {
                    // See if unsent mailshot is present
                    $last_mailshot = $mailHelper->lastMailshot($item->id);
                    // Count number of subscribers
                    $count_subscribers = $mailHelper->countSubscribers($item->id);
                    // Set message if mailshot is pending
                    if ($item->emails_outstanding > 0) {
                        $message = $item->group_code . '/' . $item->name . ': ' . $this->mailshot_send_message;
                        Factory::getApplication()->enqueueMessage($message, 'notice');
                    }
                    echo '<tr class="row' . $i % 2 . '">';
                    echo '<td>' . $item->group_code . '</td>';
                    echo '<td>' . $item->name . '</td>';
                    echo '<td>' . $item->owner . '</td>';
                    echo '<td>' . $item->list_type . '</td>';     // Open or Closed
                    echo '<td>' . $item->public . '</td>';        // Open to other groups?
                    // Find the number of subscribers to this list
                    echo '<td>';
                    $sql = 'SELECT COUNT(s.id) FROM #__ra_mail_subscriptions AS s ';
                    $sql .= 'INNER JOIN #__ra_profiles AS p ON p.id = s.user_id ';
                    $sql .= 'WHERE s.state=1 AND s.list_id=' . $item->id;
                    $count = $toolsHelper->getValue($sql);
                    // Allow the owner of the list to see who the subscribers are
                    if ($count > 0) {
                        echo $count;
                        if ($item->owner_id == $this->user->id) {
                            $target = 'index.php?option=com_ra_mailman&task=mail_lst.showSubscribers&list_id=' . $item->id . '&Itemid=' . $this->menu_id;
                            echo $toolsHelper->imageButton('I', $target);
                        }
                    }
                    echo '</td>';

                    echo '<td>';
                    $count = $mailHelper->countMailshots($item->id, True);
                    if ($count > 0) {
                        echo $count;
                        $target = 'index.php?option=com_ra_mailman&view=mailshots&list_id=' . $item->id . '&Itemid=' . $this->menu_id;
                        echo $toolsHelper->imageButton('I', $target);
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

                    if ($this->user->id > 0) {
                        echo '<td>';
                        // Actions are determined by a function in the View itself
                        $actions = $this->defineActions($item->id, $item->list_type, $item->emails_outstanding, $last_mailshot, $count_subscribers);
                        echo $actions;
                        echo '</td>';
                    }

                    echo '</tr>';
                    echo '';
                }  // end foreach
                ?>
            </tbody>
        </table>
    </div>
    <input type="hidden" name="task" value=""/>
    <input type="hidden" name="boxchecked" value="0"/>
    <input type="hidden" name="filter_order" value=""/>
    <input type="hidden" name="filter_order_Dir" value=""/>
    <?php echo HTMLHelper::_('form.token'); ?>
</form>

<div id="ra-mailman-schedule-modal">
    <div class="ra-mailman-schedule-box">
        <h4>Schedule mailshot</h4>
        <label for="ra-mailman-schedule-date">Send at:</label>
        <input type="date" id="ra-mailman-schedule-date">
        <span class="fas fa-clock" aria-hidden="true"></span>
        <select id="ra-mailman-schedule-hour" class="ra-mailman-plain"></select> :
        <select id="ra-mailman-schedule-minute" class="ra-mailman-plain"></select>
        <div>
            <button type="button" class="link-button button-p0159" onclick="raMailmanConfirmSchedule()">Queue</button>
            <button type="button" class="link-button" onclick="raMailmanCloseSchedule()">Cancel</button>
        </div>
    </div>
</div>
