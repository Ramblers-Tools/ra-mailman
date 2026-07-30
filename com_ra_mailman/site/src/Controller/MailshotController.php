<?php

/**
 * @version    4.5.7
 * @package    com_ra_mailman
 * @author     Charlie Bigley <webmaster@bigley.me.uk>
 * @copyright  2023 Charlie Bigley
 * 02/01/24 CB correct formatting of dates
 * 27/05/24 CB only show email address if superuser
 * 27/05/24 CB check user is logged in when showing recipients
 * 05/09/24 CB don't use JURI when linking to attachment
 * 14/10/24 CB delete all functions except send and showMailshot
 * 20/10/24 CB showMailshot - use getInt
 * 30/10/24 CB show recipients name from profile record, not user record
 * 04/11/24 CB use getIdentity not getUser
 * 12/02/25 CB replace getIdentity with Factory::getApplication()->getSession()->get('user')
 * 01/06/25 CB show final_message, not body
 * 14/08/25 CB new send
 * 20/10/25 CB correct updateOutstanding
 */

namespace Ramblers\Component\Ra_mailman\Site\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Router\Route;
use Ramblers\Component\Ra_mailman\Site\Helpers\Mailhelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsTable;

/**
 * Mailshot class.
 *
 * @since  1.0.2
 */
class MailshotController extends FormController {

    public function send() {
        $objApp = Factory::getApplication();
        $mailshot_id = $objApp->input->getInt('mailshot_id', '');
        $total = $objApp->input->getInt('total', '');
        $user_id = Factory::getApplication()->getSession()->get('user')->id;
        if ($user_id == 0) {
            Factory::getApplication()->enqueueMessage('You must be logged in to access this function', 'error');
        } else {
            $mailHelper = new Mailhelper;
            $params = ComponentHelper::getParams('com_ra_mailman');
            $max_emails = $params->get('max_emails', 120);
            $max_online_send = $params->get('max_online_send', 100);
            $mailHelper->send($mailshot_id, $total);
            Factory::getApplication()->enqueueMessage($objMailHelper->message, 'notice');
        }

        $this->setRedirect('index.php?option=com_ra_mailman&view=mail_lsts');
    }

    public function schedule() {
        $objApp = Factory::getApplication();
        $mailshot_id = $objApp->input->getInt('mailshot_id', 0);
        $total = $objApp->input->getInt('total', 0);
        $send_at = $objApp->input->getString('send_at', '');
        $user_id = Factory::getApplication()->getSession()->get('user')->id;
        if ($user_id == 0) {
            $objApp->enqueueMessage('You must be logged in to access this function', 'error');
        } else {
            $mailHelper = new Mailhelper;
            $mailHelper->scheduleSend($mailshot_id, $total, $send_at);
        }

        $this->setRedirect('index.php?option=com_ra_mailman&view=mail_lsts');
    }

    public function cancelSending() {
        $objApp = Factory::getApplication();
        $toolsHelper = new ToolsHelper;
        $mailshot_id = $objApp->input->getInt('mailshot_id', 0);
        $sql = 'SELECT l.id, l.emails_outstanding, m.title ';
        $sql .= 'FROM #__ra_mail_shots AS m ';
        $sql .= 'INNER JOIN #__ra_mail_lists AS l ON l.id = m.mail_list_id ';
        $sql .= 'WHERE m.id=' . $mailshot_id;
        $item = $toolsHelper->getItem($sql);
        if ($item) {
            $mailHelper = new Mailhelper;
            if ($mailHelper->isAuthor($item->id)) {
                $sql = 'UPDATE #__ra_mail_lists SET emails_outstanding=0 WHERE id=' . $item->id;
                $toolsHelper->executeCommand($sql);

                // Return the mailshot to draft (edit/send/schedule), not permanently closed -
                // "cancelled" halts any in-flight batch loop (see Mailhelper::isMailshotCancelled())
                // without leaving date_sent set, which would make it look like it had actually sent.
                $sql = 'UPDATE #__ra_mail_shots SET processing_started=NULL, send_after=NULL, is_scheduled=0, cancelled=1 WHERE id=' . $mailshot_id;
                $toolsHelper->executeCommand($sql);

                $message = 'Mailshot "' . $item->title . '" cancelled. Outstanding count reset from ' . $item->emails_outstanding . ' to 0.';
                $toolsHelper->createLog('RA Mailman', '27', $mailshot_id, $message);
                $objApp->enqueueMessage('Sending cancelled. The mailshot has been returned to draft - you can edit, resend, reschedule, or discard it.', 'success');
            } else {
                $objApp->enqueueMessage('You are not authorised to cancel this mailshot', 'error');
            }
        } else {
            $objApp->enqueueMessage('No mailshot found for mailshot ID ' . $mailshot_id, 'notice');
        }
        $this->setRedirect('index.php?option=com_ra_mailman&view=mail_lsts');
    }

    public function discard() {
        $objApp = Factory::getApplication();
        $toolsHelper = new ToolsHelper;
        $mailshot_id = $objApp->input->getInt('mailshot_id', 0);
        $sql = 'SELECT mail_list_id, title FROM #__ra_mail_shots WHERE id=' . $mailshot_id;
        $item = $toolsHelper->getItem($sql);
        $mailHelper = new Mailhelper;
        if ($item && $mailHelper->isAuthor($item->mail_list_id)) {
            $mailHelper->discardMailshot($mailshot_id);
            $toolsHelper->createLog('RA Mailman', '28', $mailshot_id, 'Mailshot "' . $item->title . '" discarded');
            $objApp->enqueueMessage('Mailshot "' . $item->title . '" discarded.', 'success');
        } else {
            $objApp->enqueueMessage('Unable to discard mailshot', 'error');
        }
        $this->setRedirect('index.php?option=com_ra_mailman&view=mail_lsts');
    }

    public function showMailshot() {
        $objHelper = new ToolsHelper;
        $objApp = Factory::getApplication();
        $id = $objApp->input->getInt('id', 0);
        $menu_id = $objApp->input->getInt('Itemid', 0);

        $db = Factory::getDbo();
        $query = $db->getQuery(true);

        $query->select('a.id, a.date_sent,a.mail_list_id,a.date_sent');
        $query->select("DATE_FORMAT(a.date_sent, '%k:%i') as sent_time");
        $query->select("a.title,a.attachment");
        $query->select("CONCAT(l.group_code,' ',l.name) as list");
        $query->select("a.final_message, a.created, a.created_by, a.modified, a.modified_by");

        $query->from('`#__ra_mail_shots` AS a');
        $query->where('a.id = ' . $id);
        $query->innerJoin('#__ra_mail_lists AS l ON l.id = a.mail_list_id');
        $query->select("u.name as creator");
        $query->leftJoin('#__users AS u ON u.id = a.created_by');
        $query->select("u2.name as updater");
        $query->leftJoin('#__users AS u2 ON u2.id = a.modified_by');
        $db->setQuery($query);
        $item = $db->loadObject();
        echo "<h3>List: " . $item->list . "</h3>";
        echo "<h3>Sent: " . $item->sent_time . " " . HTMLHelper::_('date', $item->date_sent, 'D d/m/y') . "</h3>";
        echo "<h2>" . $item->title . "</h2>";
//        echo HTMLHelper::_('date', $item->date_sent, 'H:i D d/m/y');
        // Find any more Mailshots

        $sql = 'SELECT id FROM #__ra_mail_shots WHERE mail_list_id=' . $item->mail_list_id;
        $sql .= ' AND date_sent IS NOT NULL';
        $sql .= ' AND id>' . $item->id;
        $sql .= ' ORDER BY id ASC LIMIT 1';
        $next_id = $objHelper->getValue($sql);

        $sql = 'SELECT id FROM `#__ra_mail_shots` WHERE mail_list_id=' . $item->mail_list_id;
        $sql .= ' AND id<' . $item->id;
        $sql .= ' ORDER BY id DESC LIMIT 1';
        $prev_id = $objHelper->getValue($sql);

//        echo "<p>" . $item->body . "</p>";
        echo $item->final_message;
        if ($item->attachment !== '') {
            $attach_array = explode(',', $item->attachment);
            echo 'Attachment: ';
            foreach ($attach_array as $file) {
                echo $objHelper->buildLink('images/com_ra_mailman/' . $file, $file, true) . '<br> ';
            }
            //echo  . $objHelper->buildLink(Juri::Base() . 'images/com_ra_mailman/' . $item->attachment, $item->attachment, true);
        }
        echo "<p>Created by " . $item->creator . ' at ' . HTMLHelper::_('date', $item->created, 'H:i D d/m/y');
        if (($item->modified_by > 0) AND (HTMLHelper::_('date', $item->created, 'H:i D d/m/y') != HTMLHelper::_('date', $item->modified, 'H:i D d/m/y'))) {
            echo ', Updated by ' . $item->updater . ' at ' . HTMLHelper::_('date', $item->modified, 'H:i D d/m/y');
        }
        echo "</p>";
        echo "<p>";

        $back = 'index.php?option=com_ra_mailman&view=mailshots&list_id=' . $item->mail_list_id;
        $back .= '&Itemid=' . $menu_id;
        echo $objHelper->backButton($back);

        $target = "index.php?option=com_ra_mailman&task=mailshot.showMailshot&Itemid=$menu_id&id=";
        if ($prev_id) {
            $prev = $objHelper->buildLink($target . $prev_id, "Prev", False, "link-button button-p0159");
            echo $prev;
        }
        if ($next_id) {
            $next = $objHelper->buildLink($target . $next_id, "Next", False, "link-button button-p0159");
            echo $next;
        }
        echo "<p>";
    }

    public function showRecipients() {
        $user_id = Factory::getApplication()->getSession()->get('user')->id;
        if ($user_id == 0) {
            $this->setRedirect(Route::_('index.php?option=com_ra_mailman&view=mailshots', false));
            $this->redirect();
            return;
        }
        $objHelper = new ToolsHelper;
        $superuser = $objHelper->isSuperuser();
        $objApp = Factory::getApplication();
        $id = $objApp->input->getInt('id', 0);
        $list_id = $objApp->input->getInt('list_id', 0);
        $menu_id = $objApp->input->getInt('Itemid', 0);

        $db = Factory::getDbo();
        $query = $db->getQuery(true);

        $query->select('a.id, a.date_sent');
//        $query->select("DATE_FORMAT(a.date_sent, '%d/%b/%y') as sent_date");
//        $query->select("DATE_FORMAT(a.date_sent, '%k:%i') as sent_time");
        $query->select("a.processing_started, a.date_sent, a.title");
        $query->select("CONCAT(l.group_code,' ',l.name) as list");
        $query->from('`#__ra_mail_shots` AS a');
        $query->innerJoin('#__ra_mail_lists AS l ON l.id = a.mail_list_id');
        $query->where('a.id = ' . $id);

        $db->setQuery($query);
        $row = $db->loadObject();
        echo "<h3>List: " . $row->list . "</h3>";
        echo "<h3>Processing started: " . $row->processing_started;
//        echo ", Completed: " . $row->sent_time . " " . $row->sent_date . "</h3>";
        echo ", Completed: " . $row->date_sent . "</h3>";
        echo "<h2>" . $row->title . "</h2>";

        $query = $db->getQuery(true);
        $query->select("p.preferred_name as Recipient, u.email AS 'user_email', a.email AS 'target_email'");
        $query->select("DATE_FORMAT(a.created, '%d/%b/%y') as sent_date");
        $query->select("DATE_FORMAT(a.created, '%k:%i:%s') as sent_time");
        $query->from('`#__ra_mail_recipients` AS a');
//        $query->innerJoin('#__ra_mail_lists AS l ON l.id = a.mail_list_id');
        $query->leftJoin('#__ra_profiles AS p ON p.id = a.user_id');
        $query->leftJoin('#__users AS u ON u.id = a.user_id');
        $query->where('a.mailshot_id = ' . $id);
        $query->order($db->escape('u.username'));

        //      Show link that allows page to be printed
        $target = 'index.php?option=com_ra_mailman&task=mailshot.showRecipients&id=' . $id;
        $target .= '&list_id=' . $list_id;
        $target .= '&Itemid=' . $menu_id;
        echo $objHelper->showPrint($target) . '<br>' . PHP_EOL;
        $sql = (string) $query;
        $rows = $objHelper->getRows($sql);
        $objTable = new ToolsTable;
        $title = 'Recipient,Date,Time';
        if ($superuser) {
            $title .= ',email';
        }
        $objTable->add_header($title);
//        $rows = $db->loadObjectList();
        $count = 0;
        foreach ($rows as $row) {
            $count++;
            $objTable->add_item($row->Recipient);
            $objTable->add_item($row->sent_date);
            $objTable->add_item($row->sent_time);
            if ($superuser) {
                $detail = $row->target_email;
                if ($row->target_email != $row->user_email) {
                    $detail .= '<br><b>' . $row->user_email . '</b>';
                }
            }
            $objTable->add_item($detail);
            $objTable->generate_line();
        }
        $objTable->generate_table();
        echo $count . ' Recipients<br>';
        $back = 'index.php?option=com_ra_mailman&view=mailshots&list_id=' . $list_id;
        $back .= '&Itemid=' . $menu_id;
        echo $objHelper->backButton($back);
        echo "<p>";
    }

    public function test() {
        $file = 'Logo-90px.png';
        $working_file = JPATH_ROOT . '/images/com_ra_mailman/' . $file;
        Factory::getApplication()->enqueueMessage('Attaching file "' . $file, 'notice');
        if (file_exists($working_file)) {
            echo $working_file . ' found<br>';
        } else {
            echo $working_file . ' not found!<br>';
        }
    }

}
