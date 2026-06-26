<?php
/**
 * @version    4.5.7
 * @package    com_ra_mailman
 * @author     Charlie Bigley <webmaster@bigley.me.uk>
 * @copyright  2023 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 19/10/25 CB Breadcrumbs
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
use \Ramblers\Component\Ra_mailman\Site\Helpers\Mailhelper;

HTMLHelper::_('bootstrap.tooltip');
HTMLHelper::_('behavior.multiselect');

// Import CSS
$wa = $this->document->getWebAssetManager();
$wa->registerAndUseStyle('ramblers', 'com_ra_tools/ramblers.css');

$listOrder = $this->state->get('list.ordering');
$listDirn = $this->state->get('list.direction');
$objMailHelper = new Mailhelper;
$self = 'index.php?option=com_ra_mailman&view=list_select';
$self .= '&user_id=' . $this->user_id;
$breadcrumbs = $this->objHelper->buildLink('aindex.php', 'Main menu');
$breadcrumbs .= '>' . $this->objHelper->buildLink('index.php?option==com_ra_mailman&view=mail_lsts', 'Mailing lists');
echo $breadcrumbs;

echo '<h2>';
// Cannot use this buton here as array of ids will not be passed unless
// a standard toolbar button is being display (or bespoke javascript provided(
//$target = 'index.php?option=com_ra_mailman&task=user_select.subscribeAll';
//echo $this->objHelper->buildButton($target, 'sunset');
echo 'Select subscriptions for ' . $this->user_name . '</h2>';
?>

<form action="<?php echo Route::_($self); ?>" method="post"
      name="adminForm" id="adminForm">
    <div class="row">
        <div class="col-md-12">
            <div id="j-main-container" class="j-main-container">
                <?php echo LayoutHelper::render('joomla.searchtools.default', array('view' => $this)); ?>

                <div class="clearfix"></div>
                <table class="table mintcake table-striped" id="listselectList">
                    <thead>
                        <tr>


                            <?php
                            echo '<th class="left">';
                            echo JHtml::_('searchtools.sort', 'Group', 'a.group_code', $listDirn, $listOrder);
                            echo '</th>';

                            echo '<th class="left">';
                            echo JHtml::_('searchtools.sort', 'Name', 'a.name', $listDirn, $listOrder);
                            echo '</th>';

                            echo '<th class="left">';
                            echo JHtml::_('searchtools.sort', 'Owner', 'g.name', $listDirn, $listOrder);
                            echo '</th>';

                            echo '<th class="left">';
                            echo JHtml::_('searchtools.sort', 'Type', 'a.record_type', $listDirn, $listOrder);
                            echo '</th>';

                            echo '<th class="left">';
                            echo JHtml::_('searchtools.sort', 'Home group only', 'a.home_group_only', $listDirn, $listOrder);
                            echo '</th>';

                            echo "<th>Subscribers</th>";
                            echo "<th>Current</th>";
                            echo '<th>Action</th>' . PHP_EOL;
                            echo '</tr>';
                            ?>

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
                        foreach ($this->items as $i => $item) {
                            $target = 'index.php?option=com_ra_mailman&record_type=1';
                            $target .= '&user_id=' . $this->user_id . '&list_id=' . $item->id;
                            $target .= '&callback=list_select&task=mail_lst.';

                            $sql = 'SELECT COUNT(id) FROM #__ra_mail_subscriptions ';
                            $sql .= 'WHERE state=1 AND list_id=' . $item->id;
                            $count = $this->objHelper->getValue($sql);

                            if (($item->home_only == 'Yes') AND ($this->group_code != $item->group_code)) {
                                $action = '';
                                $icon = 'minus';
                            } else {
                                // See if User is already subscribed
                                $sql = 'SELECT id, state FROM #__ra_mail_subscriptions WHERE user_id=' . $this->user_id;
                                $sql .= ' AND list_id=' . $item->id;
//                                echo "$sql<br>";
                                $subscription = $this->objHelper->getItem($sql);
//        var_dump($subscription);
                                if (is_null($subscription)) {
                                    $icon = 'delete';
                                    $target .= 'subscribe';
                                    $caption = 'Subscribe';
                                    $colour = 'sunset';
                                } else {
                                    //
                                    if ($subscription->state == 0) {
                                        $icon = 'delete';
                                        $target .= 'subscribe';
                                        $caption = 'Re-subscribe';
                                        $colour = 'mud';
                                    } else {
                                        $icon = 'publish';
                                        $target .= 'unsubscribe';
                                        $caption = 'Un-subscribe';
                                        $colour = 'rosycheeks';
                                    }
                                }
                                $action = $this->objHelper->buildButton($target, $caption, False, $colour);
                            }

                            echo "<tr>" . PHP_EOL;
                            // Not allowed to check the box if home_group_only)
                            //                           echo '<td class="';
                            //                           $hidden = false;
                            //                           if ($item->public == 'Yes') {
                            //                               if ($this->group_code != $item->group_code) {
                            //                                   $hidden = true;
                            //                               }
                            //                           }
//                            echo 'center">';
//                            echo JHtml::_('grid.id', $i, $item->id) . '</td>' . PHP_EOL;

                            echo '<td>' . $item->group_code . '</td>';
                            echo '<td>' . $item->name . '</td>';
                            echo '<td>' . $item->owner . '</td>';
                            echo '<td>' . $item->list_type . '</td>';     // Open or Closed
                            echo '<td>' . $item->home_only . '</td>';        // Open to other groups?
                            echo '<td>' . $count . '</td>';
                            echo '<td><span class="icon-' . $icon . '"></span>' . '</td>';
                            echo '<td>' . $action . '</td>';

                            echo "</tr>" . PHP_EOL;
                        }  // end foreach
                        ?>
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
