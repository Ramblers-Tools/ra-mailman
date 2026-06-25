
<?php

/**
 * @version    4.3.4
 * @package    com_ra_mailman
 * @copyright  Copyright (C) 2020. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * @author     Charlie Bigley <webmaster@bigley.me.uk>
 * 21/11/24 CB Created
 * 03/04/25 CB show user name
 */
// No direct access
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Ramblers\Component\Ra_mailman\Site\Helpers\Mailhelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsTable;

defined('_JEXEC') or die;
$objHelper = new ToolsHelper;
$objMailhelper = new Mailhelper;
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

$objTable = new ToolsTable();
$objTable->add_header(',Group,Title,Expiry date,Reminder, Action');

$target_info = 'index.php?option=com_ra_mailman&task=profile.showSubscriptionDetails&menu_id=' . $this->menu_id . '&id=';
$target_renew = 'index.php?option=com_ra_mailman&task=mail_lst.renew&Itemid=' . $this->menu_id;
$target_renew .= '&user_id=' . $this->user->id . '&list_id=';

$sql = 'SELECT l.id AS list_id, ';
$sql .= 's.id, s.state, s.expiry_date, s.reminder_sent, ';
$sql .= 'l.group_code, l.name AS `list`, l.record_type AS list_record_type ';
$sql .= 'FROM `#__ra_mail_lists` AS l ';
$sql .= 'LEFT JOIN `#__ra_mail_subscriptions` AS s ';
$sql .= 'ON s.list_id = l.id AND s.user_id=' . $this->user->id . ' ';
$sql .= 'WHERE l.state=1 ';
$sql .= 'ORDER BY l.group_code, l.name ';
$rows = $objHelper->getRows($sql);
if ($objHelper->rows == 0) {
    echo 'No mailing lists <br>';
    return;
} else {
    $count = 1;
    foreach ($rows as $row) {
        $lists[] = $row->list_id;
        $is_subscribed = (!is_null($row->id) && $row->state == 1);
        $is_open = ($row->list_record_type == 'O');
        $objTable->add_item('<b>' . $count . '</b>. ');
        $objTable->add_item($row->group_code);
        $objTable->add_item($row->list);
        $objTable->add_item($is_subscribed ? HTMLHelper::_('date', $row->expiry_date, 'd-M-Y') : '');
        $objTable->add_item($is_subscribed ? HTMLHelper::_('date', $row->reminder_sent, 'd-M-Y') : '');
        $details = '';
        if (!is_null($row->id)) {
            $details .= $objHelper->buildButton($target_info . $row->id, 'Details');
        }
        if ($is_subscribed) {
            $target = 'index.php?option=com_ra_mailman&view=mailshots&Itemid=' . $this->menu_id;
            $target .= '&list_id=' . $row->list_id . '&callback=profile_subscriptions';
            $details .= $objHelper->buildButton($target, 'Mailshots', false, 'sunrise');
            $target = 'index.php?option=com_ra_mailman&task=mail_lst.unsubscribe&Itemid=' . $this->menu_id;
            $target .= '&user_id=' . $this->user->id . '&list_id=' . $row->list_id . '&callback=profile_subscriptions';
            $details .= $objHelper->buildButton($target, 'Unsubscribe', false, 'rosycheeks');
        } else {
            if ($is_open) {
                $target = 'index.php?option=com_ra_mailman&task=mail_lst.subscribe&Itemid=' . $this->menu_id;
                $target .= '&user_id=' . $this->user->id . '&list_id=' . $row->list_id . '&callback=profile_subscriptions';
                $details .= $objHelper->buildButton($target, 'Subscribe', false, 'sunset');
            }
        }
        // $details .= $objHelper->buildButton($target_renew . $row->list_id, 'Renew');
        $objTable->add_item($details);
        $objTable->generate_line();
        $count++;
    }
}

$objTable->generate_table();

//$i = 1;
//foreach ($lists as $list) {
//    $details = $objMailhelper->mailshotDetails($list, $this->user->id);
//    if ($details != '') {
//    echo '<b>' . $i . '</b>. ' . $details;
//    }
//    $i++;
//}
