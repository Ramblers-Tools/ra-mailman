<?php

/**
 * Contains functions used in the back end and the front end
 *
 * @version    4.6.6
 * @package    com_ra_mailman
 * @author     charles

 * 13/10/24 CB when seeking outstanding mailshot, return object
 * 17/10/24 CB don't prepend JPATH_ROOT to attachment file names
 * 28/10/24 CB don't include users if awaiting password reset
 * 29/10/24 CB show pretty dates
 * 04/11/24 CB use getIdentity instead of getUser
 * 17/11/24 CB set expiry_date in subscription helper/update
 * 12/02/25 CB replace getIdentity with Factory::getApplication()->getSession()->get('user')
 * 17/02/25 CB eliminate getUser
 * 09/03/25 CB correct message when unsubscribing
 * 03/04/25 CB correct isAuthor and lastMailshot
 * 05/04/25 CB use JPATH_ROOT for attachments (otherwise does not work from Admin)
 * 30/04/25 CB add logo to email header
 * 19/05/25 CB remove logo, check for duplicates when sending, don't append sender's email address
 * 31/05/25 CB show colours, warning if no subscribers, correct restart, X on behalf of Y
 * 17/06/25 CB revert X on behalf of Y (fix error in buildMessage)
 * 06/08/25 CB warning if email logging is active
 * 19/08/25 CB make user_id public
 * 03/10/25 CB optionally include invitation to an event
 * 06/10/25 CB add colour for event link
 * 25/02/26 CB restructured buildMessage to build the email as a whole, with header and footer,
 *             so that the event invitation can be included in the correct place
 * 16/03/26 CB changes for sub-set
 * 19/03/26 CB Deleted sendEmail (always used ToolsHelper->sendEmail)
 */

namespace Ramblers\Component\Ra_mailman\Site\Helpers;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Object\CMSObject;
use Joomla\CMS\Uri\Uri;
use Ramblers\Component\Ra_events\Site\Helpers\BookingHelper;
use Ramblers\Component\Ra_mailman\Site\Helpers\SubscriptionHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsTable;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

class Mailhelper {

    public $message;
    public $messages;
    private $bookingHelper;
    private $event_id;
    private $footer;
    protected $db;
    protected $attachments;
    protected $email_log_level;
    protected $email_title;
    protected $app;
    protected $toolsHelper;
    protected $query;
    public $user_id;

    public function __construct() {
        $this->db = Factory::getDbo();
        $this->toolsHelper = new ToolsHelper;
        $this->app = Factory::getApplication();
        $this->user_id = $this->app->getSession()->get('user')->id;
    }

    public function buildEmailHeader($setup = null) {
        /*
         * Generates the responsive email header with text left-aligned and logo right-aligned
         * Uses flexbox for responsive layout that works on all screen sizes
         */

        if (is_null($setup)) {
            $setup = $this->getEmailSetup();
        }

        $logo = $this->getLogoPath($setup->logo_file);
        $logo_align = $setup->logo_align;
        $text_align = ($logo_align === 'right') ? 'left' : 'right';
        $flex_direction = ($logo_align === 'right') ? 'row' : 'row-reverse';

// Set the div for the header as a whole using flexbox for responsive layout
// In due course, can just user $header = $this->toolsHelper->buildEmailPreamble();
        $header = '<div style="';
        $header .= 'display: flex; ';
        $header .= 'flex-direction: ' . $flex_direction . '; ';
        $header .= 'justify-content: space-between; ';
        $header .= 'align-items: center; ';
        $header .= 'gap: 20px; ';
        $header .= 'background: ' . $setup->colour_header . '; ';
        $header .= 'border-radius: 5%; ';
        $header .= 'padding: 20px; ';
        $header .= 'box-sizing: border-box; ';
        $header .= 'width: 100%; ';
        $header .= 'max-width: 100%; ';
        $header .= 'overflow: hidden; ';
        $header .= '">';

//      Set the div for the header text (left-aligned, flexible width, shrinks on small screens)
        $header .= '<div style="flex: 1 1 auto; text-align: ' . $text_align . '; min-width: 0; overflow-wrap: break-word;">';
        $header .= $setup->email_header;
        $header .= '</div>';

//      Logo (right-aligned, non-shrinking)
        if (($logo != '') && file_exists(JPATH_ROOT . $logo)) {
            $image_data = file_get_contents(JPATH_ROOT . $logo);
            $encoded = base64_encode($image_data);
            $header .= '<a href="' . $setup->website . '" style="flex-shrink: 0; display: flex;">';
            $header .= '<img src="data:image/jpeg;base64,' . $encoded . '" ';
            $header .= 'style="height: ' . $setup->height . 'px; width: ' . $setup->width . 'px; display: block; max-width: 100%; height: auto;" ';
            $header .= 'alt="Logo">';
            $header .= '</a>';
        } elseif ($logo != '') {
            Factory::getApplication()->enqueueMessage('Logo file "' . $logo . '" not found', 'warning');
        }

        $header .= '</div>';
        return $header;
    }
    public function buildMenu(){
//      Invoked from com_ra_tools / Admin / dashboard
//      set callback in globals so organisation can return as appropriate after configuration
        $app = Factory::getApplication();
        $app->setUserState('com_ra_mailman.reports.callback', 'dashboard');
        $canDo = ContentHelper::getActions('com_ra_tools');
        $super = $this->toolsHelper->isSuperUser();
        if ($super) {
            $text = '<h3>System Tools</h3>';
            $text .= '<ul>';
//            $this->user = $this->getCurrentUser();
//            if ($this->user->id == 1) { 
              $text .= '<li><a href="index.php?option=com_ra_tools&view=clusters" target="_self">Clusters</a></li>';
//            }
            $text .= '<li><a href="index.php?option=com_ra_members&view=organisations" target="_self">Organisations</a></li>';
            $text .= '<li><a href="index.php?option=com_ra_members&view=members" target="_self">Members</a></li>';
            $text .= '<li><a href="index.php?option=com_ra_mailman&amp;view=profiles" target="_self">MailMan Users</a></li>';
            $versions = $this->toolsHelper->getVersions('com_ra_mailman');
            $text .= '<li><a href="index.php?option=com_config&view=component&component=com_ra_mailman" target="_self">';
            $text .= "Configure system defaults (version " . $versions->component . ")</a></li>" . PHP_EOL;
            $text .= '<li><a href="index.php?option=com_ra_mailman&task=profiles.load" target="_self">Test data load</a></li>';
            $text .= '</ul>';
        }   
        // find current scope
        $code = $this->getDefaultGroup();

        if (!empty($code) && $code !== 'N') {
            $sql = 'SELECT id, name ';
            $sql .= 'FROM #__ra_organisations ';
            $sql .= 'WHERE code=' . $this->db->quote($code);
            $item = $this->toolsHelper->getItem($sql);  
            $subheading =  $code . ' ' . (!empty($item->name) ? htmlspecialchars($item->name) : 'N/A');
        } else {
            $subheading = 'All records';
        }   
        $text .= '<h3>Mail Manager</h3>';
        $text .= '<h4>Scope '  . $subheading . '</h4>';
        $text .= '<ul>';
        

        $text .= '<li><a href="index.php?option=com_ra_mailman&view=mail_lsts" target="_self">Mailing lists</a></li>';
        $text .= '<li><a href="index.php?option=com_ra_mailman&amp;view=mailshots" target="_self">Mailshots</a></li>';
        $text .= '<li><a href="index.php?option=com_ra_mailman&amp;view=reports" target="_self">Mailman Reports</a></li>';
        
        if ($canDo->get('core.create')) {
            $text .= '<li><a href="index.php?option=com_ra_mailman&amp;view=subscriptions" target="_self">Subscriptions</a></li>';
           
        } 
        $area_code = substr($code, 0, 2);
        $area_id = $this->toolsHelper->getValue('SELECT id FROM #__ra_organisations WHERE code="' . $area_code . '"');
        if (!empty($area_id)) {
            $text .= '<li><a href="index.php?option=com_ra_mailman&view=reports&area=' . $area_code . '" target="_self">Area reports</a></li>';
            $text .= '<li><a href="index.php?option=com_ra_mailman&view=organisation&layout=edit&callback=dashboard&id=' .  $area_id  . ' " target="_self">Configure ' . $area_code  . '</a></li>'; 
        }   
        $text .= '<li><a href="index.php?option=com_ra_mailman&view=organisation&layout=edit&callback=dashboard&id=' . (!empty($item->id) ? $item->id : '') . ' " target="_self">Configure ' . $code  . '</a></li>'; 
         if ($this->toolsHelper->isSuperuser()){

//            $text .= '<li>(DB version is ' . $versions->db_version . ')</li>';
        }
        $text .= '</ul>' . PHP_EOL;
        return $text;
    }

    public function buildMessage($mailshot_id) {
        /*
         * called by send to compile the final message:
         * 1. system header, from config options (text left-aligned, logo right-aligned)
         * 2. Name of the mailing list
         * 3. Actual body of the message as entered by the author
         * 4. Name of the member who last edited the list, plus the name of the owner if different
         * 5. The footer defined for the list, followed by the email address of the list owner
         * 6. The system footer, from config options
         */
//
// Get details of the Mailing list
        $sql = "SELECT "
                . "m.title, m.body, m.attachment, m.event_id, "
                . "m.created, m.modified, m.modified_by, m.date_sent, "
                . "l.name, l.owner_id, l.footer, owner.email AS 'reply_to',"
                . "p.preferred_name AS 'Owner', modifier.preferred_name as 'Modifier', creator.name as 'Creator' ";
        $sql .= 'FROM #__ra_mail_shots AS m ';
        $sql .= 'INNER JOIN `#__ra_mail_lists` AS l ON l.id = m.mail_list_id ';
        $sql .= 'LEFT JOIN #__users AS owner ON owner.id = l.owner_id ';
        $sql .= 'LEFT JOIN #__ra_profiles AS modifier ON modifier.id = m.modified_by ';
        $sql .= 'LEFT JOIN #__users AS creator ON creator.id = m.created_by ';
        $sql .= 'LEFT JOIN #__ra_profiles AS p ON p.id = l.owner_id ';
        $sql .= 'WHERE m.id=' . $mailshot_id;

        $db = Factory::getDbo();
        $query = $db->getQuery(true);
        $db->setQuery($sql);
        $db->execute();
        $item = $db->loadObject();
        // Save the Event id in case an invitation link is required
        $this->event_id = $item->event_id;
        if ($item->event_id > 0) {
            $this->bookingHelper = new BookingHelper;
        }
        if ((is_null($item->modified_by) OR ($item->modified_by == 0))) {
            $date = HTMLHelper::_('date', $item->created, 'd M y');
            $signatory = $item->Creator;
        } else {
            $date = HTMLHelper::_('date', $item->modified, 'd M y');
            $signatory = $item->Modifier;
        }

// Save the title for the email (used in $this->send)
        $this->email_title = $item->title;

        $setup = $this->getEmailSetup();

// Start building the complete email HTML structure
        $mailshot_body = '<!DOCTYPE html>';
        $mailshot_body .= '<html>';
        $mailshot_body .= '<head>';
        $mailshot_body .= '<meta charset="UTF-8">';
        $mailshot_body .= '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
        $mailshot_body .= '<style>';
        $mailshot_body .= 'body { font-family: Arial, sans-serif; margin: 0; padding: 10px; }';
        $mailshot_body .= 'div { box-sizing: border-box; }';
        $mailshot_body .= '</style>';
        $mailshot_body .= '</head>';
        $mailshot_body .= '<body>';

        // Build and insert the email header
        $mailshot_body .= $this->buildEmailHeader($setup);

        // Add the email body
        $mailshot_body .= '<div style="background: ' . $setup->colour_body;
        $mailshot_body .= '; padding-top: 10px; padding-bottom: 10px; ">';

        $mailshot_body .= $item->body;

        $mailshot_body .= '<br>From ';
        if ($signatory == $item->Owner) {
            $mailshot_body .= $item->Owner;
        } else {
            $mailshot_body .= $signatory;
            if ($item->Owner !== '') {
                $mailshot_body .= ' on behalf of ' . $item->Owner;
            }
        }


        // Save any attachment for the email (used in $this->send)
        if ($item->attachment != '') {
            $attach_array = explode(',', $item->attachment);
            foreach ($attach_array as $file) {
                $working_file = JPATH_ROOT . '/images/com_ra_mailman/' . $file;
                Factory::getApplication()->enqueueMessage('Attaching file "' . $file, 'notice');
                if (file_exists($working_file)) {
                    $this->attachments[] = $working_file;
                } else {
                    $mailshot_body .= 'File ' . $file . ' not found<br>';
                    $this->message .= $working_file . ' not found';
                }
            }
        }
        // N.B. final </div> not included in case an event invitation is required
// Footer comprises the footer from the list, plus the owners email address, plus the component footer
// Created without closing tag so the unsubscribe link can be added
        $this->footer = '<div style="background: ' . $setup->colour_footer;
        $this->footer .= '; padding: 10px;  border-radius: 5%; ">';
        $this->footer .= $item->footer . '<br>';
        $this->footer .= $setup->email_footer;
        $this->footer .= '<br>';
        $this->footer .= 'To unsubscribe from future emails, click ';
        // N.B. final </div> not included

        return $mailshot_body;
    }
    
    private function canDo($option = 'core.manage') {
        // 04/11/24 this function probably not used
// Checks that the current user is authorised for the given option

        if (Factory::getApplication()->getSession()->get('user')->authorise($option, 'com_ra_mailman')) {
            return true;
        } else {
            return false;
        }
    }

    public function countAudit($user_id) {
        $sql = 'SELECT COUNT(a.id) ';
        $sql .= 'FROM `#__ra_mail_subscriptions_audit` AS a ';
        $sql .= 'INNER JOIN `#__ra_mail_subscriptions` AS s ON s.id = a.object_id ';
        $sql .= 'WHERE s.user_id=' . $user_id;
        echo $sql . '<br>';
//        $count = $toolsHelper->getValue($sql);
        return $this->toolsHelper->getValue($sql);
    }

    public function countLists($user_id) {
        $sql = 'SELECT COUNT(id) ';
        $sql .= 'FROM `#__ra_mail_subscriptions` ';
        $sql .= 'WHERE user_id=' . $user_id;
        echo $sql . '<br>';
//        $count = $toolsHelper->getValue($sql);
        return $this->toolsHelper->getValue($sql);
    }

    function countMailshots($list_id, $unsent = False) {
        $sql = 'SELECT COUNT(id) FROM `#__ra_mail_shots` ';
        $sql .= 'WHERE mail_list_id=' . $list_id;
        if ($unsent) {
            $sql .= ' AND date_sent IS NOT NULL';
        }
        return $this->toolsHelper->getValue($sql);
    }

    function countSubscribers($list_id, $author = 'N') {
        // Returns number of active subscribers for given mail list
        $sql = 'SELECT COUNT(*) ';
        $sql .= 'FROM  `#__ra_mail_lists` AS l ';
        $sql .= 'INNER JOIN #__ra_mail_subscriptions AS s ON s.list_id = l.id ';
        $sql .= 'INNER JOIN #__users AS u ON u.id = s.user_id ';
        $sql .= 'WHERE s.state=1 AND u.block=0 AND u.requireReset=0 AND l.id=' . $list_id;
        if ($author == 'Y') {
            $sql .= ' AND s.record_type=2';
        }
        return $this->toolsHelper->getValue($sql);
    }

    public function countSubscribersOutstanding($mail_shot_id) {
// returns the count of users currently subscribed to the given list
        $sql = 'SELECT COUNT(u.id)  ';
        $sql .= 'FROM #__ra_mail_shots AS m ';
        $sql .= 'INNER JOIN `#__ra_mail_lists` AS l ON l.id = m.mail_list_id ';
        $sql .= 'INNER JOIN #__ra_mail_subscriptions AS s ON s.list_id = l.id ';
        $sql .= 'INNER JOIN #__users AS u ON u.id = s.user_id ';
        $sql .= 'LEFT JOIN #__ra_mail_recipients AS mr ON mr.mailshot_id =m.id ';
        $sql .= 'AND u.id = mr.user_id ';
        $sql .= 'WHERE mr.id IS NULL ';
        $sql .= 'AND m.id=' . $mail_shot_id;
        $sql .= ' AND s.state=1';
        $sql .= ' AND u.block=0 AND u.requireReset=0';
        //       echo $sql;
        return $this->toolsHelper->getValue($sql);
    }

    private function createRecipent($mailshot_id, $user_id) {
        if ($mailshot_id == 0) {
            Factory::getApplication()->enqueueMessage('Recipient ' . $user_id . ', mailshot id =0', 'comment');
            return false;
        }
        if ($this->user_id == 0) {
            Factory::getApplication()->enqueueMessage("You are not logged in", 'error');
            return false;
        }
        $db = Factory::getDbo();
        $jinput = Factory::getApplication()->input;
        $ip_address = $jinput->server->get('REMOTE_ADDR');
        $sql = 'SELECT email FROM #__users WHERE id=' . $user_id;
        $email = $this->toolsHelper->getValue($sql);
        $columns = array('mailshot_id', 'user_id', 'email', 'ip_address', 'created_by');

        $values = array($db->quote($mailshot_id),
            $db->quote($user_id),
            $db->quote($email),
            $db->quote($ip_address),
            $db->quote($this->user_id));
//        $date = Factory::getDate();

        $query = $db->getQuery(true);
// Prepare the insert query.
        $query
                ->insert($db->quoteName('#__ra_mail_recipients'))
                ->columns($db->quoteName($columns))
                ->values(implode(',', $values));

// Set the query using our newly populated query object and execute it.
        $db->setQuery($query);
        $db->execute();
        $id = $db->insertid();
        if ($id == 0) {
            return 0;
        }
        return 1;
    }

    function decode($encoded, &$subscription_id, &$mode, $debug = False) {
        /*
         * Takes the string that has been obfuscated by function "encode",
         *  and splits it into constituents
         */
        if ($debug) {
            echo "Mail/decode: " . $encoded . "<br>";
        }
        $temp = $encoded;
        $temp = strrev(substr($temp, 0, strlen($temp) - 1));

        $token = "";
        for ($i = 0; $i < strlen($temp); $i++) {
            $char = substr($temp, $i, 1);
            $token .= (hexdec($char) - 6);
        }
        if ($debug) {
            echo "After decoding: " . $token . "<br>";
        }
//      Split the string into constituents:
        $mode = substr($token, 6, 1);
        $temp = substr($token, 7);
        if ($debug) {
            echo "Mode " . $mode . ", temp " . $temp . "<br>";
        }
        $length_id = (int) substr($temp, 0, 2);
        $subscription_id = substr($temp, 2, $length_id);

        if ($debug) {
            echo "Length " . $length_id . ', temp=' . $temp . ", parts " . $mode . "-" . $subscription_id . "<br>";
        }
        $subscription_id = $subscription_id / 7;
    }

    public function encode($subscription_id, $mode) {
        /*
          Generates a token so that a User can subscribe/Cancel their subscription
          without having to log on. This token can safely be embedded into an email
          $subscription id points to the record in ra_mail_subscribers
          mode = 0 Cancel or 1 Subscribe

          The first six characters are a random number, this is followed by the mode,
          then 7 times the subscription_id.  Since the length of the id field is unpredictable,
          it is preceded by a two character length

          The string thus generated is obfuscated in two stages by processing each digit in turn,
          firstly by adding 6, then changing its representation to Hexadecimal Thus 0123789 would become 678def

          Finally the whole string is reversed

         */
        $debug = 0;
// generate a random 6 character number
        $part1 = mt_rand(100000, 999999);
        $part2 = $mode;

        $id = (string) (7 * $subscription_id);
        $length = strlen($id);
        $part3 = sprintf("%02d", $length);
        if ($debug) {
            echo "subscription id = $subscription_id, length $length, parts:" . $part1 . "-" . $part2 . "-" . $part3 . $id . "<br>";
        }
        $temp = $part1 . $part2 . $part3 . $id;
        if ($debug) {
            echo "2: token before coding: " . $temp . "<br>";
        }
        $token = "";
        for ($i = 0; $i < strlen($temp); $i++) {
//            echo $i . substr($encoded, $i, 1) . " " . dechex(substr($encoded, $i, 1) + 6) . "<br>";
            $token .= dechex(substr($temp, $i, 1) + 6);
        }
        if ($debug) {
            echo "3: token:" . $token . "<br>";
        }

        return strrev($token) . "M";
    }
    public function getDefaultGroup() {
        /*
            * Returns the default group for the current User, which is used to determine which mailing lists they can see
            * If the full version is running, this is set to 'N' and all users see all lists
            * If the sub-set is running, this is set to the home group of the user, which is stored in the profile record
        */

        $user = $this->app->getSession()->get('user');
        $context = 'com_ra_mailman.default_group.';

        $default_group = $this->app->getUserState($context, '');
        if ($default_group == '') {
            $params = ComponentHelper::getParams('com_ra_mailman');
            $full_version = $params->get('full_version');
            if ($full_version == 'Y') {
                return 'N';
            } else {
                // Get home group from profile record
                $sql = 'SELECT home_group FROM #__ra_profiles WHERE id=' . $user->id;
                $group = $this->toolsHelper->getValue($sql);
                if (is_null($group)) {
                   return false;                
                    Factory::getApplication()->enqueueMessage('Can\'t find User record ' . $sql, 'error');
 //                   throw new Exception('Can\'t find User record', 404);
                }
                $this->app->setUserState($context, $group);
                return $group;
            }
        }
    }

    public function getDescription($list_id) {
        if ($list_id == 0) {
            return 'No description for list 0';
        }
        $sql = 'SELECT group_code, name, record_type, state FROM `#__ra_mail_lists` WHERE id=' . $list_id;
        $row = $this->toolsHelper->getItem($sql);
        if (is_null($row)) {
            return false;
        } else {
            $description = $row->group_code . ' ' . $row->name . ' ';
            if ($row->state == 0) {
                $description .= '(Inactive)';
            } else {
                if ($row->record_type == 'O') {
                    $description .= '(Open)';
                } else {
                    $description .= '(Closed)';
                }
            }
            return $description;
        }
    }
    public function getEmailSetup() {
        $params = ComponentHelper::getParams('com_ra_mailman');

        $setup = (object) [
            'website' => $params->get('website', ''),
            'email_header' => $params->get('email_header', ''),
            'email_footer' => $params->get('email_footer', ''),
            'logo_file' => $params->get('logo_file', ''),
            'logo_align' => $params->get('logo_align', 'right'),
            'colour_header' => $params->get('colour_header', 'rgba(20, 141, 168, 0.5)'),
            'colour_body' => $params->get('colour_body', 'rgba(20, 141, 168, 0.5)'),
            'colour_footer' => $params->get('colour_footer', 'rgba(20, 141, 168, 0.8)'),
            'height' => $params->get('height', 90),
            'width' => $params->get('width', 90),
            'setup_source' => 'Component configuration',
            'setup_code' => '',
        ];

        $code = $this->getDefaultGroup();

        if (!empty($code) && $code !== 'N') {
            $sql = 'SELECT code, name, website, email_header, logo, logo_align, colour_header, colour_body, colour_footer ';
            $sql .= 'FROM #__ra_organisations ';
            $sql .= 'WHERE code=' . $this->db->quote($code);
            $item = $this->toolsHelper->getItem($sql);

            if (!is_null($item)) {
                $setup->setup_source = 'Organisation table';
                $setup->setup_code = $item->code;
                if (!empty($item->website)) {
                    $setup->website = $item->website;
                }
                if (!empty($item->email_header)) {
                    $setup->email_header = $item->email_header;
                }
                if (!empty($item->logo)) {
                    $setup->logo_file = $item->logo;
                }
                if (!empty($item->logo_align)) {
                    $setup->logo_align = $item->logo_align;
                }
                if (!empty($item->colour_header)) {
                    $setup->colour_header = $item->colour_header;
                }
                if (!empty($item->colour_body)) {
                    $setup->colour_body = $item->colour_body;
                }
                if (!empty($item->colour_footer)) {
                    $setup->colour_footer = $item->colour_footer;
                }
            }
        }

        return $setup;
    }

    public function getHome_group() {
// Returns the home group of the current User
        if ($this->user_id == 0) {
            Factory::getApplication()->enqueueMessage("You are not logged in", 'error');
            return false;
        }
        $sql = 'SELECT home_group FROM #__ra_profiles WHERE id=' . $user_id;
        return $this->toolsHelper->getValue($sql);
    }

    private function getLogoPath($logo_file) {
        if (empty($logo_file)) {
            return '';
        }

        $logo_file = trim($logo_file);

        if (strpos($logo_file, '/images/') === 0) {
            return $logo_file;
        }

        if (strpos($logo_file, 'images/') === 0) {
            return '/' . $logo_file;
        }

        return '/images/com_ra_mailman/' . ltrim($logo_file, '/');
    }

    public function getOwner_id($list_id) {
        $sql = 'SELECT owner_id FROM `#__ra_mail_lists` WHERE id=' . $list_id;
        $row = $this->toolsHelper->getItem($sql);
        if (is_null($row)) {
            return 0;
        } else {
            return $row->owner_id;
        }
    }

    public function getSubscription($list_id, $user_id) {
// Returns the record in the subscription table for the given
// list_id and user_id
        $sql = "SELECT s.id, s.record_type, m.name as 'Method', s.state, ma.name as Access ";
        $sql .= "FROM #__ra_mail_subscriptions  AS s ";
        $sql .= "LEFT JOIN  #__ra_mail_methods as m ON m.id = s.method_id ";
        $sql .= 'LEFT JOIN `#__ra_mail_access` AS `ma` ON ma.id = s.record_type ';
        $sql .= 'WHERE s.list_id=' . $list_id;
        $sql .= ' AND s.user_id=' . $user_id;
        $row = $this->toolsHelper->getItem($sql);
//        echo $sql . '<br>';
        if (is_null($row)) {
            return false;
        } else {
            return $row;
        }
    }

    public function getSubscribers($mailshot_id, $restart = 'N') {
//        $this->message .= 'getSubscribers mailshot_id=' . $mailshot_id . ', ';
// returns an array of users currently subscribed to the given list
        if ($mailshot_id == '') {
            echo 'Mailshot id is blank<br>';
            Factory::getApplication()->enqueueMessage('Mailshot id is blank', 'error');
            return;
        }
        $sql = "SELECT s.id AS subscription_id, l.id AS list_id, ";
//      $sql .= "u.name AS 'User', ";          
        $sql .= "u.id as user_id, u.email AS 'email' ";
        $sql .= 'FROM #__ra_mail_shots AS m ';
        $sql .= 'INNER JOIN `#__ra_mail_lists` AS l ON l.id = m.mail_list_id ';
        $sql .= 'INNER JOIN #__ra_mail_subscriptions AS s ON s.list_id = l.id ';
        $sql .= 'INNER JOIN #__users AS u ON u.id = s.user_id ';
        if ($restart == 'N') {
            $sql .= 'WHERE ';
        } else {
            $sql .= 'LEFT JOIN #__ra_mail_recipients AS mr ON mr.mailshot_id =m.id ';
            $sql .= 'AND u.id = mr.user_id ';
            $sql .= 'WHERE mr.id IS NULL ';
            $sql .= 'AND ';
        }
        $sql .= 'm.id=' . $mailshot_id;

        $sql .= ' AND s.state=1';
        $sql .= ' AND u.block=0 AND u.requireReset=0';
        $sql .= ' ORDER BY u.email';

//        echo $sql;
//        $this->toolsHelper->showSql($sql);
//        die;
        $db = Factory::getDbo();
        $query = $db->getQuery(true);
        $db->setQuery($sql);
        $db->execute();
        $rows = $db->loadObjectList();
        return $rows;
    }

    public function isAuthor($list_id) {
// check that the current user is either:
//   - owner of the list, or
//   - included in the list of Subscriptions, record type = 2
//   - subscribed, and the list is a "Chat list"
//
        if ($this->getOwner_id($list_id) == $this->user_id) {
            return true;
        }
        if ($this->user_id == 0) {
//            echo 'User_id =0<br>';
            return false;
        }
//      If a chat list, all subscribers are allowed to send
        if ($this->isChatlist($list_id) == 1) {
            if ($this->isSubscriber($list_id, $this->user_id) != '') {
                return true;
            }
        }

//     Get the list of all Authors for this list
        $sql = 'SELECT user_id FROM #__ra_mail_subscriptions '
                . 'WHERE list_id=' . $list_id
                . ' AND record_type=2'
                . ' AND state=1';
        $this->db->setQuery($sql);
        $this->db->execute();
        $authors = $this->db->loadObjectList();
//        if (JDEBUG) {
//            print_r($authors);
//        }
        foreach ($authors as $author) {
            if ($this->user_id == $author->user_id) {
                return true;
            }
        }
        return false;
    }

    public function isChatlist($list_id) {
// check that the current list is a chat_list, where all members can send emails
// it will return (as integer) 0 or 1
        $sql = 'SELECT chat_list FROM `#__ra_mail_lists` ';
        $sql .= 'WHERE id = ' . $list_id;
        return $this->toolsHelper->getValue($sql);
    }

    public function isSubscriber($list_id, $user_id) {
// check if the given user is a Subscriber, and if so
// what method was used to enrol them
//
//      Get the list of all Active subscribers for this list
        $sql = 'SELECT s.method_id, s.record_type, m.name  as "Method" '
                . 'FROM #__ra_mail_subscriptions AS s '
                . 'INNER JOIN #__ra_mail_methods as m ON m.id = s.method_id '
                . 'INNER JOIN #__users as u ON u.id = s.user_id '
                . 'WHERE s.list_id=' . $list_id
                . ' AND s.user_id=' . $user_id
                . ' AND s.state=1'
                . ' AND u.block=0';
        $this->db->setQuery($sql);
        $this->db->execute();
        $subscriber = $this->db->loadObject();
        if (is_null($subscriber)) {
            return '';
        }
//       return $subscriber->Method;
        switch ($subscriber->method_id) {
            case 1: return $subscriber->Method;   // Self Registered
            case 2:                               // Administrator
                $role = $subscriber->Method . ' (';
                if ($subscriber->record_type == 2) {
                    return $role . 'Author)';
                } else {
                    return $role . 'Subscriber)';
                }
            case 3: return $subscriber->Method;   // Corporate feed
            case 4: return $subscriber->Method;   // Mailchimp
            case 5: return $subscriber->Method;   // CSV
            case 6: return $subscriber->Method;   // Email
            default: return $subscriber->method_id;
        }

        return false;
    }

    public function lastMailshot($list_id) {
        // returns an object with details of the most recent mailshot for the given list
        $result = new CMSObject;
        $query = $this->db->getQuery(true);
        $query->select('mail_list_id,id, date_sent,processing_started,attachment');
        $query->from($this->db->qn('#__ra_mail_shots'));
        $query->where('mail_list_id=' . $list_id);
        $query->order('id DESC');
        $query->setLimit('1');
        $this->db->setQuery($query);
        $this->db->execute();
        $item = $this->db->loadObject();
        if (is_null($item)) {
            $this->message .= 'Helper/lastMailshot: mail_list_id=' . $list_id . ' not found';
            $result->set('mail_list_id', 0);
            $result->set('id', 0);
            $result->set('date_sent', NULL);
            $result->set('processing_started', NULL);
            $result->set('date', '');
            $result->set('attachment', '');
        } else {
//          A record has been found for this list
            $result->set('mail_list_id', $item->mail_list_id);
            $result->set('id', $item->id);
            $result->set('date_sent', $item->date_sent);
            $result->set('processing_started', $item->processing_started);
            if (is_null($item->date_sent)) {
                if (is_null($item->processing_started)) {
                    // Mailshot is present, but not yet sent
                    //  Need to find the penultimate mailshot
                    $result->set('date', $this->lastSent($list_id));
                } else {
                    $result->set('date', 'Started ' . HTMLHelper::_('date', $item->processing_started, 'G:H:i d M Y'));
                }
            } else {

                $result->set('date', HTMLHelper::_('date', $item->date_sent, 'd M Y'));
            }
            $result->set('attachment', $item->attachment);
            $this->message .= 'id=' . $item->id . ', ' . $item->date_sent . ', ' . $item->processing_started;
        }
        return $result;
    }

    private function lastSent($list_id) {
// Called internally from lastMailshot if a mailshot exists that has not yet been sent
// returns the date the most recent mailshot
        if (JDEBUG) {
            echo 'Seeking last date for list ' . $list_id . '<br>';
        }
        $query = $this->db->getQuery(true);
        $query->select('id, processing_started, date_sent');
        $query->from($this->db->qn('#__ra_mail_shots'));
        $query->order('processing_started DESC');
        $query->setLimit('2');
        $query->where('mail_list_id=' . (INT) $list_id);
        $this->db->setQuery($query);
        $this->db->execute();
        $rows = $this->db->loadObjectList();

        if (count($rows) == 0) {
            $this->message .= 'No records found';
            return '';
        } else {
            // one or more mailshots have previously been sent
            foreach ($rows as $row) {
                if (JDEBUG) {
                    var_dump($row);
                    echo '<br><br>';
                }
                if (!is_null($row->processing_started)) {
                    if (is_null($row->date_sent)) {
                        // Sending has started, has not been completed
                        return 'Started ' . HTMLHelper::_('date', $row->processing_started, 'G:H:i d M Y');
                    } else {
                        $this->message .= $row->date_sent;
                        return HTMLHelper::_('date', $row->date_sent, 'd M Y');
                    }
                }
            }
        }
        return '';
    }

    public function loadUsers($code){
        // Invoked from plg_ra_mailman_userload to find the users to be loaded for the given code
        $this->messages = [];
        $this->messages[] = 'No records for ' . $code;
    } 

    public function lookupList($list_id) {
        $sql = 'SELECT name FROM #__ra_mail_lists ';
        $sql .= 'WHERE id = ' . $list_id;
        return $this->toolsHelper->getValue($sql);
    }

    public function lookupOwner($list_id) {
        // returns details about the owner of the given list
        $sql = 'SELECT u.name, u.email, p.preferred_name ';
        $sql .= 'FROM #__ra_mail_lists AS l ';
        $sql .= 'LEFT JOIN `#__ra_profiles` AS p ON p.id = l.owner_id ';
        $sql .= 'LEFT JOIN `#__users` AS u ON u.id = l.owner_id ';
        $sql .= 'WHERE l.id = ' . $list_id;
        $item = $this->toolsHelper->getItem($sql);
        return $item;
    }

    public function lookupUser($user_id) {
        $sql = 'SELECT preferred_name FROM #__ra_profiles ';
        $sql .= 'WHERE id = ' . $user_id;
        return $this->toolsHelper->getValue($sql);
    }

    public function mailshotDetails($list_id, $user_id) {
        // Finds details for mailshots sent
        $details = '';
        $sql = 'SELECT m.date_sent, m.title ';
        $sql .= 'FROM #__ra_mail_shots AS m ';
        $sql .= 'INNER JOIN #__ra_mail_recipients AS r ON r.mailshot_id  = m.id ';
        $sql .= ' WHERE m.date_sent IS NOT NULL ';
        $sql .= 'AND m.mail_list_id=' . $list_id . ' ';
        $sql .= 'AND r.user_id=' . $user_id . ' ';
        $sql .= 'GROUP BY m.date_sent, m.title ';
        $sql .= 'ORDER BY m.date_sent DESC ';
        $sql .= 'LIMIT 6';
        //       echo $sql . '<br>';
        $mailshots = $this->toolsHelper->getRows($sql);
        if ($this->toolsHelper->rows > 0) {
            $details .= 'Mailshots for ' . $this->lookupList($list_id) . ':<br>';
            $details .= '<ul>';
            foreach ($mailshots as $mailshot) {
                $pretty_date = HTMLHelper::_('date', $mailshot->date_sent, 'd-M-Y H:i');
                $details .= '<li>' . $pretty_date . ': <b>' . $mailshot->title . '</b></li>';
            }
            $details .= '</ul>';
        } else {
            $details .= 'No mailshots for ' . $this->lookupList($list_id) . '<br>';
        }
        return $details;
    }

    public function resubscribe($subscription_id) {
        /*
         * Invoked from view Subscriptions
         */

        $sql = 'SELECT state, list_id, record_type, user_id FROM #__ra_mail_subscriptions ';
        $sql .= 'WHERE id=' . $subscription_id;
        $item = $this->toolsHelper->getItem($sql);
        if ($item->state == 0) {
            $this->subscribe($item->list_id, $item->user_id, $item->record_type, 2);
        } else {
            $this->unsubscribe($item->list_id, $item->user_id, 2);
        }
    }

    public function send($mailshot_id, $total) {
        // Only invoked if processing carried out online

        $params = ComponentHelper::getParams('com_ra_tools');
        $this->email_log_level = $params->get('email_log_level', '0');
        // Log level -2 is solely to benchmark the overhead of sending via SMTP
        //       if ($this->email_log_level == -2) {
        //           Factory::getApplication()->enqueueMessage('Email logging is in Benchmark mode', 'warning');
        //       }
        // See if processing can be done on-line

        $params = ComponentHelper::getParams('com_ra_mailman');
        $max_emails = $params->get('max_emails', 100);
        $max_online_send = $params->get('max_online_send', 100);
        if ($total > $max_online_send) {
//            Find the list id
            $sql = 'SELECT ms.mail_list_id FROM #__ra_mail_shots AS ms ';
            $sql .= 'WHERE ms.id=' . $mailshot_id;
            $mail_list_id = $this->toolsHelper->getValue($sql);
            $this->updateOutstanding($mail_list_id, $total);
            return;
//               $mailshot_send_message = $params->get('mailshot_send_message', 'Processing for batch job initiated');
//               Factory::getApplication()->enqueueMessage($mailshot_send_message, 'info');
        }

        $this->sendEmails($mailshot_id);
//die ('After helper sendEmails ' . count($this->messages) . ' messages' );
        foreach ($this->messages as $message) {
            Factory::getApplication()->enqueueMessage($message, 'info');
        }
    }

    public function sendDraft($mailshot_id) {
//        die('helper sendDraft ' . $mailshot_id);
        // Compile the final message from its components
        $mailshot_body = $this->buildMessage($mailshot_id);

//      Find the email address of the list's owner
        $sql = 'SELECT u.email FROM #__ra_mail_shots AS ms ';
        $sql .= 'INNER JOIN `#__ra_mail_lists` AS l ON l.id = ms.mail_list_id ';
        $sql .= 'INNER JOIN #__users AS u ON u.id = l.owner_id ';
        $sql .= 'WHERE ms.id=' . $mailshot_id;
        $owner_email = $this->toolsHelper->getValue($sql);

//      Find the email address of the current user
        $user_email = Factory::getApplication()->getSession()->get('user')->email;

        $title = 'DRAFT MESSAGE: ' . $this->email_title;
        if (count($this->attachments) == 0) {
            $this->message .= '(no attachment) ';
        } else {
            $this->message .= '(' . count($this->attachments) . ' attachment(s)) ';
        }

        $count = 0;
        // Send message to the editor of the message
        if ($this->toolsHelper->sendEmail($user_email, $owner_email, $title, $mailshot_body . '</div></body></html>', $this->attachments)) {
            $this->message .= ' [' . $this->email_title . '] sent to you at ' . $user_email;
            $count++;
        } else {
            $this->message .= ' Unable to send Draft "' . $this->email_title . '" to ' . $user_email . ' ';
            return 0;
        }
//        die('user email ' . $user_email . '<br>' . $this->message);
//      If current user not the list owner, send another copy to the owner, reply_to = author
        if ($user_email != $owner_email) {
            if ($this->toolsHelper->sendEmail($owner_email, $user_email, $title, $mailshot_body . '</div></body></html>', $this->attachments)) {
                $this->message .= ', also sent to the owner at ' . $owner_email;
                $count++;
            } else {
                $this->message .= ' Unable to send ' . $title . ' to ' . $owner_email;
                return 0;
            }
        }
        if ($count > 0) {
            $this->message .= ' ' . $count . ' emails sent';
        }
//        die($this->message);
        return true;
    }

    public function sendEmails($mailshot_id) { // before version 4.5.1, this was function send
//    This bypasses the check for on-line maximum and is only invoked from send() 
//    if the total number of emails to be sent is less than the on-line maximum
//
//   It is also invoked from the batch job, and from task mailshot,send, which in turn is onvoked by ForceSend

    //      Find level of email logging
        $params = ComponentHelper::getParams('com_ra_tools');
        $this->email_log_level = $params->get('email_log_level', '0');
        // Find the reference point for the un-subscribe link
        $setup = $this->getEmailSetup();
        $website_base = rtrim($setup->website, '/') . '/';
//        if ($website_base == '') {
//            $params = ComponentHelper::getParams('com_ra_mailman');
//            $website_base = rtrim($params->get('website'), '/') . '/';
//        }
// Find the maximumun number of emails to be sent at one time
        $params = ComponentHelper::getParams('com_ra_mailman');
        $max_emails = $params->get('max_emails');

// Compile the final message from its components
        $mailshot_body = $this->buildMessage($mailshot_id);
// Find the email address of the list's owner
        $sql = 'SELECT l.id, u.email FROM #__ra_mail_shots AS ms ';
        $sql .= 'INNER JOIN `#__ra_mail_lists` AS l ON l.id = ms.mail_list_id ';
        $sql .= 'INNER JOIN #__users AS u ON u.id = l.owner_id ';
        $sql .= 'WHERE ms.id=' . $mailshot_id;
        $item = $this->toolsHelper->getItem($sql);
        $reply_to = $item->email;
        $mail_list_id = $item->id;

// See if the send is only part way through
        $sql = 'SELECT processing_started, date_sent, title FROM #__ra_mail_shots ';
        $sql .= 'WHERE id=' . $mailshot_id;
//        echo "$sql<br>";
        $item = $this->toolsHelper->getItem($sql);
        echo $item->processing_started . ' ' . $item->date_sent . '<br>';
        if (is_null($item->date_sent)) {
            echo $item->date_sent . ' date_sent is null<br>';
        }
        if ($item->date_sent > '') {
            $this->messages[] = 'Mailshot "' . $item->title . '" was sent ' . $item->date_sent;
            $this->updateOutstanding($mail_list_id, 0);
            return 0;
        }
//      Set up maximum time of 10 mins (should be parameter in config
        $max = 10 * 60;
        set_time_limit($max);

        if (is_null($item->processing_started)) {
            $this->messages[] = 'Sending of Mailshot "' . $item->title . '" started at ' . date('d-M-Y H:i:s A');
// Save the status that processing has started
            if (!$this->updateDate($mailshot_id, 'processing_started')) {
                $this->message .= ', Unable to update ProcessingDate';
                return 0;
            }
            $restart = false;
// Store the final composite message on the mailshot record
            if (!$this->storeMessage($mailshot_id, $mailshot_body . 'Un-subscribe')) {
                $this->message .= ', Unable to update final message';
                return 0;
            }
            $subscribers = $this->getSubscribers($mailshot_id);
            $count_subscribers = count($subscribers);
            if ($count_subscribers == 0) {
                $this->messages[] = 'No subscribers';
            }
        } else {
//          Send had started but not completed
            $message = 'Sending of Mailshot "' . $item->title . '" restarting ' . $item->processing_started;
            $restart = true;
// Only get users who have not yet received their message
            $subscribers = $this->getSubscribers($mailshot_id, 'Y');
            $count_subscribers = count($subscribers);
            $message .= ', ' . $count_subscribers . ' users outstanding';
            $this->messages[] = $message;
        }

        $error_count = 0;
        $count = 0;
        $outstanding = $count_subscribers;
        $current_email = '';
        foreach ($subscribers as $subscriber) {
            $count++;
            $outstanding--;
//            if ($restart) {
//                $this->messages[] = $count . ' ' . $subscriber->email;
//            }
            // Check not already sent an email to this subscriber
            if ($subscriber->email == $current_email) {
                $this->messages[] = 'Duplicate message for ' . $subscriber->email;
            } else {
                $message = $mailshot_body;
                $message .= '</div>';
                if ($this->event_id > 0) {
                    $message .= '<div style="background: ' . $setup->colour_body;
                    $message .= '; padding-top: 10px; ">';
                    $message .= $this->bookingHelper->generateInvitation($website_base, $this->event_id, $subscriber->user_id);
                    $message .= '</div>';
                }

                $current_email = $subscriber->email;
                $token = $this->encode($subscriber->subscription_id, 0);

                $link = $this->toolsHelper->buildLink($website_base . 'index.php?option=com_ra_mailman&task=mail_lst.processEmail&token=' . $token, 'Un-subscribe');
//                if ($count == 1) {
//                    Factory::getApplication()->enqueueMessage('Website base is ' . $website_base, 'error');
//                }

                $message .= $this->footer . $link . '</div>';
                $message .= '</body></html>';
                if (!$this->toolsHelper->sendEmail($subscriber->email, $reply_to, $this->email_title, $message, $this->attachments)) {
                    $error_count++;
                }

                if ($outstanding % 10 == 0) {
                    $this->updateOutstanding($mail_list_id, $outstanding);
                }
                $this->createRecipent($mailshot_id, $subscriber->user_id);
                if ($count >= $max_emails) {
                    break;
                }
            }
        }
        if ($error_count > 0) {
            $this->message .= ' ' . $error_count . ' Errors';
        }
        $this->updateOutstanding($mail_list_id, $outstanding);
        $this->messages[] = ' Mailshot ' . $this->email_title . ' sent to ' . $count . ' users ';
        if ($outstanding == 0) {
            if (!$this->updateDate($mailshot_id, 'date_sent')) {
                $this->messages[] = ', Unable to update DateSent';
                return false;
            }
        } else {
            $this->messages[] = $outstanding . ' messages still outstanding';
        }
        return true;
    }

    public function sendRenewal($user_id, $list_id = 0) {
        /*
         * Invoked from batch program renewals.php and via dashboard
         * seeks all subscription for the given user, processes renewals
         * If value given for list_id restricts processing to that list
         *
         * first check that user_id is still valid (user may have been deleted)
         */
//        if ($this->validSubscription($subscription_id) == false) {
//            return;
//        }
//        die("id=$id<br>");
        $app = Factory::getApplication();

        $sql = 'SELECT username, block, requireReset ';
        $sql .= 'from `#__users` WHERE id=' . $user_id;
//        echo $sql . '<br>';

        $item = $this->toolsHelper->getItem($sql);
        // This check should not be necessary, but inserted as belt-and-braces
        if ($item->block == 1) {
            echo "User $item->username is blocked<br>";
            $app->enqueueMessage('User ' . $item->username . ' is blocked', 'warning');
            return false;
        }
        // This check should not be necessary, but inserted as belt-and-braces
        if ($item->requireReset == 1) {
            echo "A password reset is required for User $item->username<br>";
            $app->enqueueMessage('A reset is required for User ' . $item->username, 'warning');
            return false;
        }

//      get component parameters
    $setup = $this->getEmailSetup();
    $body = '<i>' . $setup->email_header . '</i><br>';
    $website_base = rtrim($setup->website, '/') . '/';
//        echo 'base ' . $website_base . '<br>';

        $sql = 'SELECT s.id, s.user_id, s.list_id, s.expiry_date, datediff(s.expiry_date, CURRENT_DATE) AS days_to_go, ';
        $sql .= 'l.group_code, l.name as "List", ';
        $sql .= 'u.name AS "Recipient", u.email, u.block, p.preferred_name, ';
        $sql .= 'o.name as "Owner", o.email as reply_to ';
        $sql .= 'FROM `#__ra_mail_lists` AS l ';
        $sql .= 'INNER JOIN `#__ra_mail_subscriptions` AS s ON s.list_id = l.id ';
        $sql .= 'LEFT JOIN `#__users` AS u ON u.id = s.user_id ';
        $sql .= 'INNER JOIN `#__ra_profiles` AS p ON p.id = u.id ';
        $sql .= 'LEFT JOIN `#__users` AS o ON o.id = l.owner_id ';
        $sql .= 'WHERE s.user_id=' . $user_id;
        if ($list_id > 0) {
            $sql .= ' AND s.list_id=' . $list_id;
        }
        echo $sql . '<br>';

//        var_dump($owner);
//        die;
//        echo $this->toolsHelper->showQuery($sql);
        $rows = $this->toolsHelper->getRows($sql);

        foreach ($rows as $row) {
//            var_dump($row);
            $owner = $this->lookupOwner($row->list_id);
            $email = $row->email;
            $pretty_date = HTMLHelper::_('date', $row->expiry_date, 'd-M-Y');
            $body .= 'Hi ' . $row->preferred_name . '(' . $row->Recipient . ')<br>';
            $body .= '<b>List: ' . $row->group_code . ' ' . $row->List . '</b><br>';
            $body .= '<b>Owned by: ' . $owner->preferred_name . '</b><br>';
            $body .= 'Your subscription to receive emails from MailMan ';
            if ($row->days_to_go < 0) {
                $body .= 'expired on ' . $pretty_date;
            } else {
                $body .= 'will expire on ' . $pretty_date;
            }
            $body .= ', so we want to make sure you are still happy to continue.<br>';
            $body .= '<br>';

// Show details of Mailshots for this list
            $body .= $this->mailshotDetails($row->list_id, $row->user_id);

            $token = $this->encode($row->id, 3);   // bump
            $link = $this->toolsHelper->buildLink($website_base . 'index.php?option=com_ra_mailman&task=mail_lst.processEmail&token=' . $token, 'Renew');
            $body .= 'To renew your subscription for another year please click ' . $link . '<br>';

            $token = $this->encode($row->id, 2);   // cancel
            $link = $this->toolsHelper->buildLink($website_base . 'index.php?option=com_ra_mailman&task=mail_lst.processEmail&token=' . $token, 'Cancel');
            $body .= 'If you want to cancel your subscription please click ' . $link . '<br>';
            $body .= '<br>';
            if ($list_id == 0) {
                $token = $this->encode($row->id, 5);   // bump all
                $link = $this->toolsHelper->buildLink($website_base . 'index.php?option=com_ra_mailman&task=mail_lst.processEmail&token=' . $token, 'Renew');
                $body .= 'To renew <b>ALL</b> your subscription for another year please click ' . $link . '<br>';

                $token = $this->encode($row->id, 4);   // cancel all
                $link = $this->toolsHelper->buildLink($website_base . 'index.php?option=com_ra_mailman&task=mail_lst.processEmail&token=' . $token, 'Cancel');
                $body .= 'If you want to cancel <b>ALL</b> your subscription please click ' . $link . '<br>';
            }
            $objSubscription = new SubscriptionHelper;
            $objSubscription->list_id = $row->list_id;
            $objSubscription->user_id = $row->user_id;
            $objSubscription->getData();
            $date = Factory::getDate('now', Factory::getConfig()->get('offset'));
            $objSubscription->reminder_sent = $date->toSql(true);
            $objSubscription->update();
        }

        $body .= $setup->email_footer;
        $body .= '';

        $title = 'MailMan Renewal required - ' . $row->List . ' for group ' . $row->group_code;
        echo $item->email . '<br>';
        echo $title . '<br>';
        echo $body . '<br>';
        echo 'emails ' . $row->email . '&' . $owner->email . '<br>';
//        return $this->sendEmail('webmaster@bigley.me.uk', $item->reply_to, $title, $body, '');
        $this->message = 'An email has been sent to User ' . $row->preferred_name;
        return $this->toolsHelper->sendEmail($row->email, $owner->email, $title, $body, '');
    }

    private function storeMessage($mailshot_id, $mailshot_body) {
        $db = Factory::getDbo();
        $query = $db->getQuery(true);

        $fields = array(
            $db->quoteName('final_message') . '=' . $db->quote($mailshot_body)
        );

// Conditions for which records should be updated.
        $conditions = array(
            $db->quoteName('id') . ' = ' . $db->quote($mailshot_id)
        );

        $query->update($db->quoteName('#__ra_mail_shots'))->set($fields)->where($conditions);
        $db->setQuery($query);
        if ($db->execute()) {
//            $this->message .= $mailshot_body;                           // Debug
            return 1;
        }
        return 0;
    }

    public function showSubscriptionDetails($id) {
        // Invoked from the backend - SubscriptionController
        // and front end - ProfileController


        $toolsHelper = new ToolsHelper;
//        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
//        $wa->registerAndUseStyle('ramblers', 'com_ra_tools/ramblers.css');
//        $db = Factory::getDbo();
//        $query = $db->getQuery(true);

        $sql = 'SELECT u.name, u.email, l.name as `list`, l.group_code, ';
        $sql .= 'a.list_id, a.user_id, a.record_type, a.method_id, a.expiry_date, ';
        $sql .= 'a.reminder_sent, a.ip_address, a.state, ';
        $sql .= 'p.preferred_name, m.name AS `method`, ma.name AS `access` ';
        $sql .= 'FROM #__ra_mail_subscriptions AS a ';
        $sql .= 'LEFT JOIN #__ra_mail_access AS ma ON ma.id = a.record_type ';
        $sql .= 'LEFT JOIN `#__ra_mail_methods` AS `m` ON m.id = a.method_id ';
        $sql .= 'LEFT JOIN `#__ra_mail_lists` AS l ON l.id = a.list_id ';
        $sql .= 'LEFT JOIN `#__users` AS u ON u.id = a.user_id ';
        $sql .= 'LEFT JOIN `#__ra_profiles` AS p ON p.id = a.user_id ';
        $sql .= "WHERE a.id=$id";

        $item = $toolsHelper->getItem($sql);
        if ($toolsHelper->isSuperuser()) {
            echo 'User_id <b>' . $item->user_id . '</b><br>';
        }
        echo 'Real Name: <b>' . $item->name . '</b><br>';
        echo 'Preferred Name: <b>' . $item->preferred_name . '</b><br>';
        echo 'Email: <b>' . $item->email . '</b><br>';
        echo 'List: ' . $item->list_id . ' <b>' . $item->group_code . ' <b>' . $item->list . '</b></b><br>';
        echo 'Access ' . $item->record_type . ' <b>' . $item->access . '</b><br>';
        echo 'Method ' . $item->method_id . ' <b>' . $item->method . '</b><br>';
        //
        echo 'Expires <b>' . HTMLHelper::_('date', $item->expiry_date, 'd-M-Y') . '</b><br>';
        if (!is_null($item->reminder_sent)) {
            echo 'Reminder <b>' . HTMLHelper::_('date', $item->reminder_sent, 'd-M-Y') . '</b><br>';
        }
        echo 'Last IP address <b>' . $item->ip_address . '</b><br>';
        echo 'Status <b>' . $item->state . '</b><br>';
        echo '<h2>Audit details</h2>';

        $sql = "SELECT date_format(a.created,'%d/%m/%y') as 'Date', ";
        $sql .= "time_format(a.created,'%H:%i') as 'Time', ";
        $sql .= "a.field_name, a.old_value, ";
        $sql .= "a.new_value, a.ip_address, ";
        $sql .= "u.name ";
        $sql .= "FROM #__ra_mail_subscriptions_audit AS a ";
        $sql .= "LEFT JOIN `#__users` AS u ON u.id = a.created_by ";
        $sql .= "WHERE object_id=$id ORDER BY created DESC";

        $rows = $toolsHelper->getRows($sql);
        echo "<br>ShowAudit<br>";
        $objTable = new ToolsTable;
        $objTable->add_header("Update user,Date,Time,Field,Old,New,IP Address");
        foreach ($rows as $row) {
            $objTable->add_item($row->name);
            $objTable->add_item($row->Date);
            $objTable->add_item($row->Time);
            $objTable->add_item($row->field_name);
            $objTable->add_item($row->old_value);
            $objTable->add_item($row->new_value);
            $objTable->add_item($row->ip_address);
            $objTable->generate_line();
        }
        $objTable->generate_table();
    }

    public function subscribe($list_id, $user_id, $record_type, $method_id) {
// Subscribes the given user to the given list
// if invoked from the front-end, $user_id will usually be the current user,
// but from the back-end, or if invoked from view list_select, it could be any user
//
// $record_type (from back end) could be 1=Subscription or 2=author
        if (JDEBUG) {
            $message = "Creating subscription for list=" . $list_id . ', user=' . $user_id;
            $message .= ", record_type=" . $record_type . ', method_id=' . $method_id;
            Factory::getApplication()->enqueueMessage($message, 'info');
        }
        // in mail_lists, record_type signifies open/closed
        $sql = 'SELECT group_code, name, record_type, home_group_only ';
        $sql .= 'FROM `#__ra_mail_lists` ';
//        $sql .= 'LEFT JOIN #__ra_mail_access AS ma ON ma.id = s.record_type ';
        $sql .= 'WHERE id=' . $list_id;
        $list = $this->toolsHelper->getItem($sql);

        // in subscriptions, record_type signifies type of access
        $sql = 'SELECT s.id, s.record_type, s.state, ma.name ';
        $sql .= 'FROM #__ra_mail_subscriptions as s ';
        $sql .= 'INNER JOIN `#__ra_mail_lists` AS l ON l.id = s.list_id ';
        $sql .= 'LEFT JOIN #__ra_mail_access AS ma ON ma.id = s.record_type ';
        $sql .= 'WHERE s.user_id=' . $user_id;
        $sql .= ' AND s.list_id=' . $list_id;
        $item = $this->toolsHelper->getItem($sql);
        if ($item) {
            if (($item->state == 1) AND ($item->record_type == $record_type)) {
                $this->message = 'User is already subscribed to ' . $list->name . ' as ' . $item->name;
                return false;
            }
        }

//  Check that user is in the correct group
        if ($list->home_group_only == '1') {
            $sql = 'SELECT home_group FROM #__ra_profiles ';
            $sql .= 'WHERE id=' . $user_id;
            $home_group = $this->toolsHelper->getValue($sql);
            if ($list->group_code != $home_group) {
                $this->message = 'You cannot subscribe to ' . $list->name . ' because it is only open to ' . $list->group_code;
                return false;
            }
        }

        $state = 1;       // Active
        if ($this->updateSubscription($list_id, $user_id, $record_type, $method_id, $state)) {
            if ($this->user_id == $user_id) {
                $message = 'You have ';
            } else {
                $message = 'User has been ';
            }
            $message .= $this->message;
            $message .= ' ' . $list->group_code . ' ' . $list->name;
            $this->message = $message;
            return true;
        } else {
// error will already have been displayed
            return false;
        }
    }

    public function unsubscribe($list_id, $user_id, $method_id) {
        $sql = 'SELECT id, record_type FROM #__ra_mail_subscriptions WHERE user_id=' . $user_id;
        $sql .= ' AND list_id=' . $list_id;
        $item = $this->toolsHelper->getItem($sql);
        if (is_null($item)) {
            $this->message = 'You are not already subscribed to this list';
            $this->message .= $sql;
            return false;
        }
        $record_type_original = $item->record_type;
// Check that this is not a closed list
        $sql = 'SELECT * FROM `#__ra_mail_lists` WHERE id=' . $list_id;
//        Factory::getApplication()->enqueueMessage($sql, 'notice');
        $item = $this->toolsHelper->getItem($sql);
// Extra check if we are in the front end
        if (JPATH_BASE == JPATH_SITE) {
//            $this->message = 'You are in the front end';
            if (!$item->record_type == 'O') {
                $this->message = 'You cannot unsubscribe from ' . $item->name . ' because it is a Closed list';
                return false;
            }
        }
        $state = 0;       // Cancelled
        if ($this->updateSubscription($list_id, $user_id, $record_type_original, $method_id, $state)) {
            $message = (JPATH_BASE == JPATH_SITE) ? 'User has been ' : $message = 'User has been Unsubscribed from ';
        }
        $message .= ' ' . $item->group_code . ' ' . $item->name;
        $this->message = $message;
    }

    private function updateDate($mailshot_id, $date_field) {
        $db = Factory::getDbo();
        $query = $db->getQuery(true);

//        $dateTimeNow = new DateTime('NOW');
        $dateTimeNow = Factory::getDate()->toSql();

        $fields = array(
            $db->quoteName($date_field) . '=' . $db->quote($dateTimeNow)
        );

// Conditions for which records should be updated.
        $conditions = array(
            $db->quoteName('id') . ' = ' . $db->quote($mailshot_id)
        );
        $query->update($db->quoteName('#__ra_mail_shots'))->set($fields)->where($conditions);

        $db->setQuery($query);
        if ($db->execute()) {
            return 1;
        }
        $this->message = "Unable to set date";
        return 0;
    }

    public function updateOutstanding($maillist_id, $value) {
        $sql = 'UPDATE #__ra_mail_lists ';
        $sql .= 'SET emails_outstanding=' . $value;
        $sql .= ' WHERE id=' . $maillist_id;
        //       die($sql);
        $this->toolsHelper->executeCommand($sql);
    }

    public function updateSubscription($list_id, $user_id, $record_type, $method_id, $state) {
        /*
         * Can cancel a subscription or set one up
         * If no record exists in #__ra_mail_subscriptions, one will be created
         * list_id - id of the particular mailing list
         * record_type - 1 = user, 2 = Author, 3 = Owner
         * method_id - 1 = User self registered, 2 = Administrator, 3 = corporate feed,
         *             4 = MailChimp, 5 = CSV file, 6 = Unscribed via link, 7 = Administrator from front end
         *             8 = User self registration, 9 = Batch housekeeping
         * user_id -  User that subscribed/Unsubscribed
         * state - 1 = current, 0 = Cancelled

         */
        //       $message = 'Helper: subscribing user ' . $user_id . ' to ' . $list_id;
        //       $message .= ', method=' . $method_id . ', state=' . $state;
        //       Factory::getApplication()->enqueueMessage($message, 'info');
        $objSubscription = new SubscriptionHelper;
        $objSubscription->list_id = $list_id;
        $objSubscription->user_id = $user_id;

        if ($objSubscription->getData()) {
//            Factory::getApplication()->enqueueMessage('UpdateSubscription1: type=' . $objSubscription->record_type . ',method=' . $objSubscription->record_type . ', state=' . $objSubscription->state . ',modified_by=' . $objSubscription->modified_by . ', id=' . $objSubscription->id, 'notice');
            $objSubscription->record_type = $record_type;
            // don't allow MaillChimp or self registration to be overwritten by Corporate feed
            $valid = array(2, 5, 7, 9);
            if (in_array($objSubscription->method_id, $valid)) {
                $objSubscription->method_id = $method_id;
            }
            $objSubscription->state = $state;
            if ($state < 1) {  // 0 or -2
                $objSubscription->resetExpiry();
            } else {
                // advance expiry_date by 12 months
                $objSubscription->bumpExpiry();
            }
//            Factory::getApplication()->enqueueMessage("UpdateSubscription2: type=$record_type,method=$record_type, state=$state", 'notice');
            $return = $objSubscription->update();
        } else {
            /*
              Subscribe
              $objSubscription->bumpExpiry();

              Unsubscribe
              $objSubscription->resetExpiry();

             */
// If state is zero, we are unsubscribing the old user - so we
// don't care if record is present or not
            if ($state == 0) {
                $return = 0;
            } else {
                // Subscription record not yet present
                $objSubscription->message = '';
//                Factory::getApplication()->enqueueMessage("UpdateSubscription: record NOT found", 'notice');
                $objSubscription->record_type = $record_type;
                $objSubscription->method_id = $method_id;
                $objSubscription->state = $state;
                // Set expiry date to 12 months time
                $objSubscription->bumpExpiry();
// If enrolment is via MailChimp or Corporate feed, expiry date will be set in the class
                $return = $objSubscription->add();
            }
        }
        if ($return) {

        } else {
            Factory::getApplication()->enqueueMessage('UpdateSubscription: action=' . $objSubscription->message, 'error');
        }
        $this->message = $objSubscription->action;
        return $return;
    }

    private function validSubscription($id) {
        $sql = 'SELECT s.user_id, l.name, u.username, u.block ';
        $sql .= 'FROM `#__ra_mail_subscriptions` AS s  ';
        $sql .= 'LEFT JOIN `#__ra_mail_lists` AS l ON l.id  = s.list_id ';
        $sql .= 'LEFT JOIN `#__users` AS u ON u.id = s.user_id ';
        $sql .= 'WHERE s.id=' . $id;
        $item = $this->toolsHelper->getItem($sql);
        if ($item->block == 1) {
            echo "User $item->username is blocked<br>";
            // Cancel this subscription

            return false;
        }
        if ((is_null($item->name) OR (is_null($item->username)))) {
            echo "Duff  $id, user=$item->user_id<br>";
//            return false;



            Factory::getApplication()->enqueueMessage('Invalid subscription deleted ' . $id, 'info');
            $sql = 'DELETE FROM `#__ra_mail_subscriptions_audit` WHERE object_id=' . $id;
            $this->toolsHelper->executeCommand($sql);
            $sql = 'DELETE FROM `#__ra_mail_subscriptions` WHERE id=' . $id;
            $this->toolsHelper->executeCommand($sql);
            return false;
        }
        return true;
    }

}
