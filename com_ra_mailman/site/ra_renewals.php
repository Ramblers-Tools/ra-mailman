<?php

/**
 * @package    Joomla.Cli
 *
 * @copyright  Copyright (C) 2005 - 2020 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 *
 * This script firstly defines a class called "ra_renewals", then the final line invokes this class
 *
 * 08/02/23 CB Created
 * 19/08/23 CB copied from cli
 * 05/01/24 CB extra parameter to logMessage
 * 04/11/26 CB probably not used anywhere - use container DatabaseInterface for J6 compatibility
 */

/**
 * A command line cron job that sends renewal messages to those whose Mail list subscriptions are about to expire
 * The interval before the due date is taken from the compoanent parameters.
 */
use Ramblers\Component\Ra_mailman\Site\Helpers\Mailhelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

require_once 'framework.inc.php';

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;

class ra_renewals extends JApplicationCli {

    /**
     * Entry point for CLI script
     *
     * @return  void
     *
     * @since   3.0
     */
    public function doExecute() {
//        echo 'Executing ' . __FILE__ . '<br>ROOT=' . JPATH_ROOT . '<br>';
        // All required classes will be sought in SITE /helpers
        $library = '/components/com_ra_mailman/src/Helpers';
        if (file_exists(JPATH_ROOT . $library . '/Mailhelper.php')) {
            require_once JPATH_BASE . $library . '/Mailhelper.php';
        } else {
            echo 'Mailhelper not found in ' . $library . '<br>';
        }

        $library = '/components/com_ra_tools/src/Helpers';
        if (file_exists(JPATH_ROOT . $library . '/ToolsHelper.php')) {
            require_once JPATH_BASE . $library . '/ToolsHelper.php';
        } else {
            echo 'ToolsHelper not found in ' . $library . '<br>';
        }


        // log the fact that the batch program has successfully been called
        $this->logMessage("R1", 1, "ra_renewals.php Started");

// initialise the Helper classes
        $Mailhelper = new Mailhelper();
        $objHelper = new ToolsHelper;

//==============================================================================
// find Subscription records close to their expiry date
//==============================================================================

        $notify_interval = JComponentHelper::getParams('com_ra_mailman')->get('notify_interval');

        $this->logMessage("R2", 2, "reminders.php: Seeking Subscriptions in " . $notify_interval) . ' days time';
        echo "Seeking Subscriptions in " . $notify_interval . ' days time' . PHP_EOL;

        $sql = "SELECT s.id, s.list_id, s.user_id, expiry_date, datediff(expiry_date, CURRENT_DATE) AS days_to_go ";
        $sql .= "FROM #__ra_mail_subscriptions AS s ";
        $sql .= "WHERE (s.state =1) ";
        $sql .= "AND datediff(expiry_date, CURRENT_DATE) <= " . $notify_interval;
        $sql .= " AND (s.reminder_sent IS NULL) ";
        $sql .= "ORDER BY s.id ";
        $sql .= 'LIMIT 5';
        //       echo $sql . PHP_EOL;


        $rows = $objHelper->getRows($sql);
        $this->logMessage("R3", 3, "Number of Subscriptions due=" . $objHelper->rows);
        $sql = 'UPDATE #__ra_mail_subscriptions SET reminder_sent=CURRENT_DATE WHERE id=';
        if ($rows) {
            foreach ($rows as $row) {
                $this->logMessage("R4", $row->user_id, "id:" . $row->id . "," . $row->expiry_date);
                if ($Mailhelper->sendRenewal($row->id)) {
                    echo $sql . $row->id . PHP_EOL;
                    $objHelper->executeCommand($sql . $row->id);
                }
            }
        }

        // log the successful completion
        $this->logMessage("R9", 9, "renewals.php Completed");
    }

    // This function is required by the framework
    function getName() {

    }

    function logMessage($record_type, $ref, $message) {
        $db = Factory::getContainer()->get(DatabaseInterface::class);

// Create a new query object.
        $query = $db->getQuery(true);
// Prepare the insert query.
        $query
                ->insert($db->quoteName('#__ra_logfile'))
                ->set('record_type =' . $db->quote($record_type))
                ->set('ref = ' . $db->quote($record_type))
                ->set('message =' . $db->quote($message));

// Set the query using our newly populated query object and execute it.
        $db->setQuery($query);
        $db->execute();
    }

}

// Instantiate the application object, passing the class name to JCli::getInstance
// and use chaining to execute the application.
$cli = JApplicationCli::getInstance('ra_renewals');

JFactory::$application = $cli;

$cli->doExecute();

