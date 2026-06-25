<?php
/**
 * @version    4.6.4
 * @package    com_ra_mailman
 * @author     Charlie Bigley <webmaster@bigley.me.uk>
 * @copyright  2023 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 14/11/23 CB pass menu_id to mail-lists list, add table-responsive
 * 21/11/23 CB show recipients from site (not Admin)
 * 22/12/23 CB prettify date sent
 * 27/05/24 CB only show recipients if Author or superuser
 * 14/10/24 CB show link(s) to attachments(s)
 * 12/02/25 CB set up $this->user from getCurrentUser
 * 16/03/26 CB filter by group if not full_version
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
HTMLHelper::_('formbehavior.chosen', 'select');

$this->user = $this->getCurrentUser();
$userId = $this->user->get('id');
$listOrder = $this->state->get('list.ordering');
$listDirn = $this->state->get('list.direction');

// Import CSS
$wa = $this->document->getWebAssetManager();
$wa->registerAndUseStyle('com_ra_tools', 'ramblers.css');

$show_recipients = false;
//$objHelper = new ToolsHelper;

if ($userId == true) {
    if (($this->toolsHelper->isSuperuser()) or ($this->mailHelper->isAuthor == true)) {
        $show_recipients = true;
    }
}
if ($this->list_id >'0') {
    echo '<h2>Mailshots for ' . $this->group_code . ' ' . $this->list_name . '</h2>';
} else {
    if ($this->group !== 'N') {
        echo '<h2>Mailshots for ' . $this->toolsHelper->lookupGroup($this->group) . '</h2>';
    } else {        
        echo '<h2>Mailshots for all lists</h2>';    
    }
}
?>

<form action="<?php echo htmlspecialchars(Uri::getInstance()->toString()); ?>" method="post"
      name="adminForm" id="adminForm">
    <?php
    if (!empty($this->filterForm)) {
        echo LayoutHelper::render('joomla.searchtools.default', array('view' => $this));
    }
    echo '<div class="table-responsive">' . PHP_EOL;
    echo '<table class="table mintcake table-striped" id="mailshotList">' . PHP_EOL;
    echo '<thead>' . PHP_EOL;
    echo '<tr>' . PHP_EOL;
    echo '<th class="left">' . HTMLHelper::_('searchtools.sort', 'Sent<br>Started', 'a.date_sent', $listDirn, $listOrder) . '</th>';
    if ($this->list_id == '0') {
        echo '<th class="left">' . HTMLHelper::_('searchtools.sort', 'List', 'mail_list.name', $listDirn, $listOrder) . '</th>';
    }   
    echo '<th class="left">' . HTMLHelper::_('searchtools.sort', 'Title', 'a.title', $listDirn, $listOrder) . '</th>';
    echo '<th class="left">Details</th>';
    echo '<th class="left"><span class="icon-paperclip"></span></th>';
    echo '<th class="left">Recipients</th>' . PHP_EOL;

    echo '</tr>' . PHP_EOL;
    echo '</thead>' . PHP_EOL;

    echo '<tbody>' . PHP_EOL;
    foreach ($this->items as $i => $item) {

        echo '<tr class="' . $i % 2 . '">';

        echo '<td style="vertical-align: top" class="date_sent">';
        echo HTMLHelper::_('date', $item->date_sent, 'H:i d/m/y');
        if (HTMLHelper::_('date', $item->date_sent, 'H:i d/m/y') != HTMLHelper::_('date', $item->processing_started, 'H:i d/m/y')) {
            echo '<br>' . HTMLHelper::_('date', $item->processing_started, 'H:i d/m/y');
        }
        echo '</td>' . PHP_EOL;
        //if (($this->list_id > '0') or ($this->group !== 'N')) {
        if ($this->list_id == '0')  {
            echo '<td style="vertical-align: top">' . $item->list_name . '</td>' . PHP_EOL;
        }
        echo '<td style="vertical-align: top">';
        $link = 'index.php?option=com_ra_mailman&task=mailshot.showMailshot&id=' . $item->id . '&tmpl=component';
        $link .= '&Itemid=' . $this->menu_id;
        echo $this->toolsHelper->buildLink($link, $item->title);
        echo '</td>' . PHP_EOL;

        echo '<td class = "item-details">';
        /*
          if (strlen($item->full_details) > $this->max_chars) {
          $details = strip_tags($item->full_details);
          echo substr($item->full_details, 0, $this->max_chars);
          echo $this->toolsHelper->buildLink($link, 'Read more', True, 'readmore');
          } else {
          echo $item->full_details;
          }
         */
        if (strlen($item->body) > $this->max_chars) {
            echo strip_tags(substr($item->body, 0, $this->max_chars)) . ' ....';
            $details = strip_tags($item->body);
            echo substr($details, 0, $this->max_chars);
            echo $this->toolsHelper->buildLink($link, 'Read more', True, 'readmore');
        } else {
            echo rtrim($item->body) . PHP_EOL;
        }
        echo '</td>' . PHP_EOL;

        echo '<td style="vertical-align: top">';
        if ($item->attachment != '') {
            $attach_array = explode(',', $item->attachment);
            foreach ($attach_array as $file) {
                echo $this->toolsHelper->buildLink('images/com_ra_mailman/' . $file, $file, true) . '<br>';
            }
        }
        echo '</td>';

        echo '<td style="vertical-align: top">';
        $count = $this->toolsHelper->getValue('SELECT COUNT(id) FROM #__ra_mail_recipients WHERE mailshot_id=' . $item->id);
        if ($count > 0) {
            if ($show_recipients) {
                $target = 'index.php?option=com_ra_mailman&task=mailshot.showRecipients&list_id=' . $this->list_id . '&id=' . $item->id;
                $target .= '&Itemid=' . $this->menu_id;
                echo $this->toolsHelper->buildLink($target, $count);
            } else {
                echo $count;
            }
        }
        echo '</td>' . PHP_EOL;

        echo '</tr>' . PHP_EOL;
    }
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

<?php
echo $this->pagination->getPagesLinks();
 if ($this->list_id >'0') {
    if ($this->callback == 'profile_subscriptions') {
        $back = 'index.php?option=com_ra_mailman&view=profile&layout=subscriptions&Itemid=' . $this->menu_id;
    } else {
        $back = 'index.php?option=com_ra_mailman&view=mail_lsts&Itemid=' . $this->menu_id;
    }
    echo $this->toolsHelper->backButton($back);
 }
?>
