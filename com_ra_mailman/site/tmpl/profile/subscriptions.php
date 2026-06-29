
<?php

/**
 * @version    4.3.4
 * @package    com_ra_mailman
 * @copyright  Copyright (C) 2020. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * @author     Charlie Bigley <webmaster@bigley.me.uk>
 * 21/11/24 CB Created
 * 03/04/25 CB show user name
 * 22/06/26 CB tidy fieldnames
 */
// No direct access
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Ramblers\Component\Ra_mailman\Site\Helpers\Mailhelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsTable;

defined('_JEXEC') or die;
$toolsHelper = new ToolsHelper;
$mailHelper = new Mailhelper;
$page_intro = Factory::getApplication()->getMenu()->getActive()->getParams()->get('page_intro', '');
//if ($this->params->get('show_page_heading') == 1) {
echo '<h2>' . $this->params->get('page_title') . '</h2>';
//}
if ($this->user->id == 0) {
//    return Error::raiseWarning(404, "Please login to gain access to this function");
    //throw new \Exception('Please login to gain access to this function', 404);
    echo '<h4>Please login to gain access to this function</h4>';
    return false;
}
echo 'You are logged in as ' . $this->user->name . ' (' . $this->user->id . ')<br>';
if ($page_intro != '') {
    echo $page_intro . "<br>";
}

$table = new ToolsTable();
$table->add_header(',Group,Title,Expiry date,Reminder, Action');

$target_info = 'index.php?option=com_ra_mailman&task=profile.showSubscriptionDetails&menu_id=' . $this->menu_id . '&id=';
$target_renew = 'index.php?option=com_ra_mailman&task=mail_lst.renew&Itemid=' . $this->menu_id;
$target_renew .= '&user_id=' . $this->user->id . '&list_id=';

$sql = 'SELECT s.id, s.list_id, ';
$sql .= 'u.name AS `Subscriber`, ';
$sql .= 'DATE(s.created) AS `Created`, ';
$sql .= 's.modified, s.expiry_date, s.reminder_sent,';
$sql .= 'l.group_code, l.name AS `list`, ';
$sql .= 'm.name AS `Method`, ma.name as Access ';
$sql .= 'FROM `#__ra_mail_subscriptions` AS s ';
$sql .= 'INNER JOIN `#__ra_mail_methods` AS `m` ON m.id = s.method_id ';
$sql .= 'LEFT JOIN `#__users` AS `u` ON u.id = s.user_id ';
$sql .= 'LEFT JOIN `#__ra_mail_lists` AS `l` ON l.id = s.list_id ';
$sql .= 'LEFT JOIN #__ra_mail_access AS ma ON ma.id = s.record_type ';
$sql .= 'LEFT JOIN #__ra_profiles as p ON p.id = s.user_id ';
$sql .= 'WHERE s.user_id=' . $this->user->id;
//$sql .= ' OR l.owner_id=' . $this->user->id;
$sql .= ' ORDER BY l.group_code, l.name ';
echo
$rows = $toolsHelper->getRows($sql);
if ($toolsHelper->rows == 0) {
    echo 'No subscriptions <br>';
    return;
} else {
    $count = 1;
    foreach ($rows as $row) {
        $lists[] = $row->list_id;
        $table->add_item('<b>' . $count . '</b>. ');
        $table->add_item($row->group_code);
        $table->add_item($row->list);
        $table->add_item(HTMLHelper::_('date', $row->expiry_date, 'd-M-Y')); // $pretty_date = HTMLHelper::_('date', $row->expiry_date, 'd-M-Y');
        $table->add_item(HTMLHelper::_('date', $row->reminder_sent, 'd-M-Y'));
        $details = $toolsHelper->buildButton($target_info . $row->id, 'Details');
        $details .= $toolsHelper->buildButton($target_renew . $row->list_id, 'Renew');
        $table->add_item($details);
        $table->generate_line();
        $count++;
    }
}

$table->generate_table();

$i = 1;
foreach ($lists as $list) {
    $details = $mailHelper->mailshotDetails($list, $this->user->id);
//    if ($details != '') {
    echo '<b>' . $i . '</b>. ' . $details;
//    }
    $i++;
}
