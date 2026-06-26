<?php

/**
 * @version    4.7.3
 * @package    com_ra_mailman
 * @author     Charlie Bigley <webmaster@bigley.me.uk>
 * @copyright  2023 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 15/06/23 CB use ra_mail_access
 * 20/06/23 CB copy code from 1.1.4
 * 22/12/23 CB prettify date sent
 * 30/01/24 CB return to mailshots view, not mail-lists
 * 08/04/24 CB created function initiate (does not work)
 * 05/09/24 CB don't use JURI when linking to attachment
 * 14/10/24 CB show link(s) to attachments(s)
 * 30/10/24 CB show names of recipients from profile, not user
 * 04/11/24 CB use getIdentity instead of getUser
 * 12/02/25 CB set up $this->user from Factory::getApplication()->getSession()->get('user')
 * 21/02/25 CB only show first 516 chars of body, fix callback for showIndividualMailshots
 * 01/06/25 CB show final_message, not body
 * 08/08/25 CB new send
 * 12/10/25 CB correct display of user name in showIndividualMailshots
 * 14/11/25 CB callback to recipients
 * 06/05/26 CB cancelSending function
 * 20/05/26 CB correct cancelSending
 */

namespace Ramblers\Component\Ra_mailman\Administrator\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Ramblers\Component\Ra_mailman\Site\Helpers\Mailhelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsTable;

/**
 * Mail_lst controller class.
 *
 * @since  1.0.6
 */
class MailshotController extends FormController {

    protected $app;
    protected $db;
    protected $toolsHelper;
    protected $user;
    protected $view_item = 'mailshot';
    protected $view_list = 'mail_lsts';

    public function __construct(array $config = array(), \Joomla\CMS\MVC\Factory\MVCFactoryInterface $factory = null) {
        parent::__construct($config, $factory);
        $this->db = Factory::getDbo();
        $this->toolsHelper = new ToolsHelper;
        $this->app = Factory::getApplication();
        $this->user = $this->app->getSession()->get('user');
        $wa = $this->app->getDocument()->getWebAssetManager();
        $wa->registerAndUseStyle('ramblers', 'com_ra_tools/ramblers.css');
    }

    public function apply($key = null, $urlVar = null) {
        $return = parent::apply($key, $urlVar);

        $this->setRedirect(Route::_($this->getEditRedirectTarget(), false));

        return $return;
    }

    public function cancel($key = null, $urlVar = null) {
        $this->setRedirect('/administrator/index.php?option=com_ra_mailman&view=mail_lsts');
    }

    public function cancelSending() {
        $mailshot_id = (int) $this->app->input->getCmd('mailshot_id', '');
        $scope = $this->app->input->getCmd('scope', '');
        $sql = 'SELECT l.id, l.emails_outstanding, m.processing_started, m.date_sent, m.title ';
        $sql .= 'FROM #__ra_mail_shots AS m ';
        $sql .= 'INNER JOIN #__ra_mail_lists AS l ON l.id = m.mail_list_id ';
        $sql .= 'WHERE m.id=' . $mailshot_id;
        $item = $this->toolsHelper->getItem($sql);
 //       var_dump($item);
//        die;
        if ($item) {
            $sql = 'UPDATE #__ra_mail_lists SET emails_outstanding=0 WHERE id=' . $item->id;
            $this->toolsHelper->executeCommand($sql);

            $sql = 'UPDATE #__ra_mail_shots SET date_sent=NOW() WHERE id=' . $mailshot_id;
            $this->toolsHelper->executeCommand($sql);

            $message = 'Mailshot "' . $item->title . '" cancelled. Outstanding count reset from ' . $item->emails_outstanding . ' to 0.';
            $this->toolsHelper->createLog('RA Mailman', '27', $mailshot_id, $message);
            $this->app->enqueueMessage('Sending cancelled. The mailshot has been closed and cannot be restarted.', 'success');
        } else {
            $this->app->enqueueMessage('No mailshot found for mailshot ID ' . $mailshot_id, 'notice');
        }
        $this->setRedirect('/administrator/index.php?option=com_ra_mailman&task=reports.recentMailshots&scope=' . $scope);
    }

    public function save($key = null, $urlVar = null) {
        $task = $this->app->input->getCmd('task', '');
        $return = parent::save($key, $urlVar);
        if ($return) {
            if ($this->isApplyTask($task)) {
                $target = $this->getEditRedirectTarget();
            } else {
                // Redirect back to mailing lists
                $target = '/administrator/index.php?option=com_ra_mailman&view=mail_lsts';
            }
        } else {
            // Get the parameters passed as part of the URL
            $app = $this->app;
            $id = $app->input->getInt('id', '1');
            $list_id = $app->input->getInt('list_id', '0');
            // Redirect back to edit form
            $target = 'index.php?option=com_ra_mailman&view=mailshot&layout=edit';
            $target .= '&id=' . $id . '&list_id=' . $list_id;
        }
        $this->setRedirect(Route::_($target, false));
        return $return;
    }

    private function isApplyTask($task) {
        return $task === 'apply' || str_ends_with($task, '.apply');
    }

    private function getEditRedirectTarget() {
        $data = $this->app->input->get('jform', array(), 'array');
        $id = (int) ($data['id'] ?? 0);
        $list_id = (int) ($data['mail_list_id'] ?? 0);

        if ($id === 0) {
            $id = (int) $this->app->getUserState('com_ra_mailman.edit.mailshot.id');
        }

        if ($id === 0) {
            $id = $this->app->input->getInt('id', 0);
        }

        if ($list_id === 0) {
            $list_id = $this->app->input->getInt('list_id', 0);
        }

        $target = 'index.php?option=com_ra_mailman&view=mailshot&layout=edit';
        $target .= '&id=' . $id . '&list_id=' . $list_id;

        return $target;
    }

    public function showIndividualMailshots() {
        // Show mailshots for given User or Profile

        $user_id = (int) $this->app->input->getCmd('user_id', 0);
        ToolBarHelper::title('Ramblers MailMan');
        $db = Factory::getDbo();
        $query = $db->getQuery(true);

        $query->select('a.id, a.title,r.email,r.ip_address');
        $query->select('r.created, a.mail_list_id');
        $query->select('a.body,a.attachment');
        $query->select("CASE when CHAR_LENGTH(a.attachment) = 0 THEN" .
                " '-' ELSE " .
                "'Y' END as filename");
        $query->from('`#__ra_mail_shots` AS a');

        $query->innerJoin($db->qn('#__ra_mail_recipients') . ' AS `r` ON r.mailshot_id = a.id');

        $query->select('mail_list.name AS `list_name`');
        $query->leftJoin($db->qn('#__ra_mail_lists') . ' AS `mail_list` ON mail_list.id = a.mail_list_id');

        $query->select('u.name AS `modified_by`');
        $query->leftJoin($db->qn('#__users') . ' AS `u` ON u.id = a.created_by');

        $query->where($db->qn('r.user_id') . ' = ' . $user_id);
        $query->order('a.date_sent DESC');

        $mailshots = $this->toolsHelper->getRows($query);
//        $this->app->enqueueMessage('Q=' . $query, 'notice');
        $details = '<h2>Mailshots sent to User ' . $user_id;
        $details .= ', ' . $this->toolsHelper->lookupUser($user_id) . '</h2>';
        echo $details;
        $objTable = new ToolsTable;
        $objTable->add_header("Details,List,Title,Message,File,Author");
        $target = 'administrator/index.php?option=com_ra_mailman&task=mailshot.showMailshot';
        $target .= '&callback=individual&user_id=' . $user_id . '&id=';
        foreach ($mailshots as $row) {
            $display_date = HTMLHelper::_('date', $row->created, 'H:i:s d/m/Y');
            $details = $this->toolsHelper->buildLink($target . $row->id, $display_date);
            //$objTable->add_item($details . '<br>' . $row->ip_address . '<br>' . $row->email);
            $objTable->add_item($details . '<br>' . $row->email);
            $objTable->add_item($row->list_name);
            $objTable->add_item($row->title);
            if (strlen($row->body) > 516) {
                $body = strip_tags(substr($row->body, 0, 516)) . ' ....';
            } else {
                $body = strip_tags(rtrim($row->body)) . PHP_EOL;
            }
            $objTable->add_item($body);
            $objTable->add_item($row->filename);
            $objTable->add_item($row->modified_by);
            $objTable->generate_line();
        }
        $objTable->generate_table();
        $back = 'administrator/index.php?option=com_ra_mailman&view=profiles';
        echo $this->toolsHelper->backButton($back);
    }

    public function showMailshot() {
        $id = $this->app->input->getInt('id', 0);
        $callback = $this->app->input->getWord('callback', '');
        $user_id = $this->app->input->getInt('user_id', 0);

        $db = Factory::getDbo();
        $query = $db->getQuery(true);

        $query->select('a.id, a.date_sent,a.mail_list_id');
        $query->select("DATE_FORMAT(a.date_sent, '%d/%b/%y') as sent_date");
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
        if (!$item = $db->loadObject()) {
            Factory::getApplication()->enqueueMessage('Unable to find details for ' . $id, 'notice');
            $this->setRedirect('/administrator/index.php?option=com_ra_mailman&view=mailshots');
            return;
        }
        ToolBarHelper::title('Ramblers MailMan');

        echo "<h3>List: " . $item->list . "</h3>";
        echo "<h3>Sent: " . $item->sent_time . " " . $item->sent_date . "</h3>";
        echo "<h2>" . $item->title . "</h2>";

        // Find any more Mailshots

        $sql = 'SELECT id FROM #__ra_mail_shots WHERE mail_list_id=' . $item->mail_list_id;
        $sql .= ' AND id>' . $item->id;
        $sql .= ' ORDER BY id ASC LIMIT 1';
        $next_id = $this->toolsHelper->getValue($sql);

        $sql = 'SELECT id FROM `#__ra_mail_shots` WHERE mail_list_id=' . $item->mail_list_id;
        $sql .= ' AND id<' . $item->id;
        $sql .= ' ORDER BY id DESC LIMIT 1';
        $prev_id = $this->toolsHelper->getValue($sql);

//        echo "<p>" . $item->body . "</p>";
        echo $this->toolsHelper->makeEmailImageUrlsAbsolute($item->final_message);
        if ($item->attachment !== '') {
            $attach_array = explode(',', $item->attachment);
            echo 'Attachment: ';
            foreach ($attach_array as $file) {
                echo $this->toolsHelper->buildLink('../images/com_ra_mailman/' . $file, $file, true) . '<br> ';
            }
        }
        echo "<p>Created by " . $item->creator . ' at ' . HTMLHelper::_('date', $item->created, 'H:i D d/m/y');
        if (($item->modified_by > 0) AND (HTMLHelper::_('date', $item->created, 'H:i D d/m/y') != HTMLHelper::_('date', $item->modified, 'H:i D d/m/y'))) {
            echo ', Updated by ' . $item->updater . ' at ' . HTMLHelper::_('date', $item->created, 'H:i D d/m/y');
        }
        echo "</p>";
        echo "<p>";

//        echo JSITE_BASE . '<br>';
        if ($callback == 'individual') {
            $back = 'administrator/index.php?option=com_ra_mailman&task=mailshot.showIndividualMailshots&user_id=' . $user_id;
        } elseif ($callback == 'recipients') {
            $back = 'administrator/index.php?option=com_ra_mailman&view=recipients';
        } else {
            $back = 'administrator/index.php?option=com_ra_mailman&view=mailshots&list_id=' . $item->mail_list_id;
        }
        echo $this->toolsHelper->backButton($back);

        $target = "administrator/index.php?option=com_ra_mailman&task=mailshot.showMailshot&id=";
        if ($prev_id) {
            $prev = $this->toolsHelper->buildButton($target . $prev_id, "Prev", False, 'grey');
            echo $prev;
        }
        if ($next_id) {
            $next = $this->toolsHelper->buildButton($target . $next_id, "Next", False, 'teal');
            echo $next;
        }
        echo "<p>";
    }

    public function showRecipients() {
//        $this->toolsHelper->showQuery('SELECT * FROM #__ra_mail_recipients');
        /*
          $sql = "SELECT r.id, r.user_id, r.email AS recipient, u.name, u.email FROM #__ra_mail_recipients AS r INNER JOIN `#__users` AS u ON r.user_id = u.id"; //WHERE r.email = ''";
          $rows = $this->toolsHelper->getRows($sql);
          foreach ($rows as $row) {
          $sql = "UPDATE #__ra_mail_recipients SET email='" . $row->email . "' WHERE id=" . $row->id;
          echo "Updating $row->name from $row->recipient $sql<br>";
          $this->toolsHelper->executeCommand($sql);
          }
         */

        $id = (int) $this->app->input->getCmd('id', 0);
        $list_id = (int) $this->app->input->getCmd('list_id', 0);

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
        ToolBarHelper::title('Ramblers MailMan');
        echo "<h3>List: " . $row->list . "</h3>";
        echo "<h3>Processing started: " . $row->processing_started;
//        echo ", Completed: " . $row->sent_time . " " . $row->sent_date . "</h3>";
        echo ", Completed: " . $row->date_sent . "</h3>";
        echo "<h2>" . $row->title . "</h2>";

//        $sql = 'SELECT u.name AS Recipient, a.email ';
//        $sql .= 'FROM `#__ra_mail_recipients` AS a ';
//        $sql .= 'LEFT JOIN `#__users` AS u ON u.id = a.user_id ';
//        $sql .= 'WHERE a.mailshot_id = ' . $id;
        $query = $db->getQuery(true);
        $query->select("p.preferred_name as Recipient, u.email AS 'user_email', a.email AS 'target_email'");
        $query->select("DATE_FORMAT(a.created, '%d/%b/%y') as sent_date");
        $query->select("DATE_FORMAT(a.created, '%k:%i:%s') as sent_time");
        $query->from('`#__ra_mail_recipients` AS a');
        $query->leftJoin('#__ra_profiles AS p ON p.id = a.user_id');
        $query->leftJoin('#__users AS u ON u.id = a.user_id');
        $query->where('a.mailshot_id = ' . $id);
        $query->order($db->escape('u.username'));

        //      Show link that allows page to be printed
//        $target = "index.php?option=com_ra_mailman&task=mailshot.showRecipients&id=" . $id;
//        echo $this->toolsHelper->showPrint($target) . '<br>' . PHP_EOL;
//        echo (string) $query;
        $sql = (string) $query;
        $rows = $this->toolsHelper->getRows($sql);
        $objTable = new ToolsTable;
        $objTable->add_header("Recipient,Date,Time,email");
//        $rows = $db->loadObjectList();
        $count = 0;
        foreach ($rows as $row) {
            $count++;
            $objTable->add_item($row->Recipient);
            $objTable->add_item($row->sent_date);
            $objTable->add_item($row->sent_time);

            $detail = $row->target_email;
            if ($row->target_email != $row->user_email) {
                $detail .= '<br><b>' . $row->user_email . '</b>';
            }
            $objTable->add_item($detail);
            $objTable->generate_line();
        }
        $objTable->generate_table();
        echo $count . ' Recipients<br>';
        echo $this->toolsHelper->backButton("administrator/index.php?option=com_ra_mailman&view=mailshots&list_id=" . $list_id);
        echo "<p>";
    }

    public function send($force = 'N') {
        $mailshot_id = (int) $this->app->input->getCmd('mailshot_id', '');
        $total = $this->app->input->getInt('total', '0');

        $user_id = $this->user->id;
        if ($user_id == 0) {
            $this->app->enqueueMessage('You must log in to access this function', 'error');
        } else {
            $mailHelper = new Mailhelper;
            if ($force == 'Y') {
                // Ignore any limit on the numbers to be send on-line
                $mailHelper->sendEmails($mailshot_id);
                if (!empty($mailHelper->messages)) {
                    foreach ($mailHelper->messages as $message) {
                        $this->app->enqueueMessage($message, 'info');
                    }
                }   
            } else {
                $mailHelper->send($mailshot_id, $total);
            }
        }

        $this->setRedirect('index.php?option=com_ra_mailman&view=mail_lsts');
    }

}
